#!/usr/bin/python3
"""Elementor exit tool (sandbox only): back up, draft and publish block versions
of Elementor-built pages. Never destroys the Elementor version before approval.

  elementor_exit.py backup  PAGE_ID
      Save the page's Elementor data to ~/src/mbc-elementor-backups/<id>-<slug>.json
      (outside the repo) and verify the file is written and valid JSON.

  elementor_exit.py draft   PAGE_ID [--when ..] [--where ..] [--who ..] [--contact ..] [--apply]
      Build an Activity-pattern block version of the page's text and image as a
      NEW DRAFT page ("<title> (block draft)"). The original page is untouched.

  elementor_exit.py publish PAGE_ID DRAFT_ID [--apply]
      On approval: back up, copy the draft's content and template into the
      page, remove its Elementor meta, then TRASH the draft (never force-delete).

Dry run by default for draft and publish. Backup rule: before any Elementor meta
is removed, _elementor_data, _elementor_edit_mode, _elementor_page_settings and
post_content are saved and the file is read back as valid JSON.
"""
import argparse
import html
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import scaffold_site as ss  # noqa: E402
import seed_patterns as sp  # noqa: E402

BACKUP_DIR = Path.home() / "src" / "mbc-elementor-backups"
ELEMENTOR_KEYS = ("_elementor_data", "_elementor_edit_mode", "_elementor_page_settings", "_elementor_template_type")
DROPPED_STYLES = {"color", "background", "background-color", "font-family", "font-size", "line-height", "text-shadow"}
ACTIVITY_TEMPLATE = "page-templates/activity.php"
NEEDS = "Needs confirming"


def get_page(wp, page_id):
    return wp.call("GET", f"pages/{page_id}", {"context": "edit",
                   "_fields": "id,slug,title,status,template,parent,menu_order,link,content.raw,meta"})


def backup(wp, page_id):
    """Write the page's Elementor data to a JSON file and prove it round-trips."""
    page = get_page(wp, page_id)
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    data = {
        "page_id": page["id"], "slug": page["slug"], "title": page["title"]["raw"],
        "saved_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "post_content": page["content"]["raw"],
        "template": page["template"],
        "elementor": {k: meta.get(k) for k in ELEMENTOR_KEYS},
    }
    BACKUP_DIR.mkdir(mode=0o700, exist_ok=True)
    path = BACKUP_DIR / f"{page['id']}-{page['slug']}.json"
    path.write_text(json.dumps(data, indent=1, ensure_ascii=False) + "\n")
    back = json.loads(path.read_text())  # must parse
    ok = back == data and path.stat().st_size > 0
    raw = back["elementor"]["_elementor_data"]
    elementor_ok = (not raw) or isinstance(json.loads(raw), list)  # the page's own Elementor JSON must parse too
    if not (ok and elementor_ok):
        sys.exit(f"Backup of page {page_id} did not verify: {path}")
    print(f"backup written and verified: {path} ({path.stat().st_size} bytes, valid JSON, "
          f"{len(raw or '')} bytes of _elementor_data)")
    return path


def elementor_parts(page):
    """Text-editor HTML blocks and image widgets, in document order."""
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    tree = json.loads(meta.get("_elementor_data") or "[]")
    texts, images, other = [], [], set()

    def walk(nodes):
        for n in nodes:
            if n.get("elType") == "widget":
                kind, s = n.get("widgetType"), n.get("settings", {})
                if kind == "text-editor":
                    texts.append(s.get("editor", ""))
                elif kind == "image" and isinstance(s.get("image"), dict):
                    images.append(s["image"])
                else:
                    other.add(kind)
            walk(n.get("elements", []))

    walk(tree)
    return texts, images, sorted(other)


def clean_styles(fragment):
    def fix(m):
        kept = [d.strip() for d in m.group(1).split(";") if d.strip() and d.split(":")[0].strip().lower() not in DROPPED_STYLES]
        return f' style="{"; ".join(kept)}"' if kept else ""
    return re.sub(r'\sstyle\s*=\s*"([^"]*)"', fix, fragment)


def paragraphs(texts):
    """Paragraph blocks from text-editor HTML. Returns (markup, warnings)."""
    out, warnings = [], []
    for t in texts:
        t = clean_styles(t)
        if re.search(r"<(ul|ol|h[1-6]|table|blockquote|iframe)\b", t, re.I):
            warnings.append("text contains lists, headings or tables: check by hand")
        chunks = re.findall(r"<p\b[^>]*>(.*?)</p>", t, re.S | re.I) or [p for p in re.split(r"\n\s*\n", t) if p.strip()]
        for c in chunks:
            c = re.sub(r"</?div[^>]*>", "", c).strip()
            # Elementor often uses a double <br> where a paragraph break is meant.
            for part in re.split(r"(?:\s*<br\s*/?>\s*){2,}", c):
                part = part.strip()
                if part:
                    out.append(f"<!-- wp:paragraph -->\n<p>{part}</p>\n<!-- /wp:paragraph -->")
    return "\n\n".join(out), warnings


def build_activity(page, facts):
    texts, images, other = elementor_parts(page)
    body = sp.pattern_markup("activity-details")
    notes = []
    for label, key in (("When", "when"), ("Where", "where"), ("Who it’s for", "who"), ("Contact", "contact")):
        body, n = re.subn(rf"(<p><strong>{re.escape(label)}</strong> ).*?(</p>)", lambda m: m.group(1) + html.escape(facts[key]) + m.group(2), body)
        if n != 1:
            sys.exit(f"pattern has no '{label}' fact line")
    if images:
        img = images[0]
        rel = re.sub(r"^https?://[^/]+", "", img.get("url", ""))
        attrs = f'"mediaId":{int(img["id"])},' if str(img.get("id", "")).isdigit() else ""
        body = body.replace('{"mediaType":"image","className":"mbc-activity__top"}', "{" + attrs + '"mediaType":"image","className":"mbc-activity__top"}', 1)
        cls = f' class="wp-image-{int(img["id"])}"' if attrs else ""
        body = re.sub(r'<img src="/wp-content/themes/generatepress-child/images/placeholder\.svg" alt=""/>',
                      f'<img src="{rel}" alt="{html.escape(img.get("alt", ""), quote=True)}"{cls}/>', body, count=1)
        if len(images) > 1:
            notes.append(f"{len(images) - 1} more image(s) not carried over")
        if not img.get("alt"):
            notes.append("the image has no alt text")
    else:
        notes.append("no image in the Elementor page: placeholder kept")
    desc, warnings = paragraphs(texts)
    if not desc:
        notes.append("no text found")
    block = "<!-- wp:paragraph -->\n<p>Describe what happens, what to expect and how to take part.</p>\n<!-- /wp:paragraph -->"
    if block not in body:
        sys.exit("pattern description block not found")
    body = body.replace(block, desc or block, 1)
    if other:
        notes.append(f"other Elementor widgets NOT converted: {other}")
    return body, notes + warnings, bool(texts or images)


def cmd_draft(wp, args):
    page = get_page(wp, args.page_id)
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    if meta.get("_elementor_edit_mode") != "builder":
        sys.exit(f"page {args.page_id} is not Elementor-built")
    facts = {"when": args.when or NEEDS, "where": args.where or NEEDS, "who": args.who or NEEDS, "contact": args.contact or NEEDS}
    body, notes, ok = build_activity(page, facts)
    title = f"{page['title']['raw']} (block draft)"
    slug = f"{page['slug']}-block-draft"
    existing = wp.call("GET", "pages", {"slug": slug, "status": "draft,publish,private,pending", "_fields": "id"})
    print(f"source page {page['id']} {page['link']} (left untouched)")
    print(f"draft: {title!r}, parent {page['parent']}, template {ACTIVITY_TEMPLATE}, {'update ' + str(existing[0]['id']) if existing else 'create'}")
    for k, v in facts.items():
        print(f"   {k:<8} {v}")
    for n in notes:
        print("   NOTE:", n)
    if not args.apply:
        print("dry run: nothing written (use --apply)")
        return
    payload = {"title": title, "slug": slug, "status": "draft", "parent": page["parent"], "template": ACTIVITY_TEMPLATE, "content": body}
    if existing:
        made = wp.call("POST", f"pages/{existing[0]['id']}", body=payload, params={"_fields": "id,link"})
    else:
        made = wp.call("POST", "pages", body=payload, params={"_fields": "id,link"})
    print(f"draft page {made['id']} saved (status draft, not published).")
    print(f"preview (log in first): {wp.base}/?page_id={made['id']}&preview=true")


def cmd_publish(wp, args):
    page, draft = get_page(wp, args.page_id), get_page(wp, args.draft_id)
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    if meta.get("_elementor_edit_mode") != "builder":
        sys.exit(f"page {args.page_id} is not Elementor-built (already converted?)")
    if draft["status"] != "draft" or draft["parent"] != page["parent"] or not draft["slug"].startswith(page["slug"]):
        sys.exit(f"page {args.draft_id} does not look like the block draft of page {args.page_id}")
    print(f"publish plan: copy content ({len(draft['content']['raw'])} bytes) and template {draft['template']!r} from draft "
          f"{draft['id']} into page {page['id']} ({page['slug']}); remove Elementor meta; trash the draft")
    if not args.apply:
        print("dry run: no backup written, nothing changed (use --apply)")
        return
    backup(wp, page["id"])  # must succeed first (exits otherwise)
    wp.call("POST", f"pages/{page['id']}", body={"content": draft["content"]["raw"], "template": draft["template"],
            "meta": {k: None for k in ELEMENTOR_KEYS if k in meta}}, params={"_fields": "id"})
    after = get_page(wp, page["id"])
    ameta = after["meta"] if isinstance(after["meta"], dict) else {}
    if ameta.get("_elementor_edit_mode") == "builder":
        sys.exit("Elementor meta is still set after the update: draft NOT trashed; restore from the backup if needed")
    wp.call("DELETE", f"pages/{draft['id']}", params={"_fields": "id"})  # no force: goes to the trash
    print(f"page {page['id']} converted; draft {draft['id']} moved to the trash. Backup: {BACKUP_DIR}/{page['id']}-{page['slug']}.json")


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = ap.add_subparsers(dest="cmd", required=True)
    b = sub.add_parser("backup"); b.add_argument("page_id", type=int)
    d = sub.add_parser("draft"); d.add_argument("page_id", type=int)
    for f in ("when", "where", "who", "contact"):
        d.add_argument(f"--{f}")
    d.add_argument("--apply", action="store_true")
    p = sub.add_parser("publish"); p.add_argument("page_id", type=int); p.add_argument("draft_id", type=int); p.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    wp = ss.WP(ss.load_env())
    {"backup": lambda: backup(wp, args.page_id), "draft": lambda: cmd_draft(wp, args), "publish": lambda: cmd_publish(wp, args)}[args.cmd]()


if __name__ == "__main__":
    main()
