#!/usr/bin/python3
"""Elementor exit tool (sandbox only): back up, draft and publish block versions
of Elementor-built pages. Never destroys the Elementor version before approval.

  elementor_exit.py backup  PAGE_ID
      Save the page's Elementor data to ~/src/mbc-elementor-backups/<id>-<slug>.json
      (outside the repo) and verify the file is written and valid JSON.

  elementor_exit.py draft   PAGE_ID [--when ..] [--where ..] [--who ..] [--contact ..]
                            [--alt TEXT] [--image-id N] [--fix 'OLD=>NEW' ...] [--facts-file FILE.json] [--apply]
      Build an Activity-pattern block version of the page's text and image as a
      NEW DRAFT page ("<title> (block draft)"). The original page is untouched.

  elementor_exit.py assets  PAGE_ID [--alts FILE.json] [--apply]
      Download the images and documents the page references from the live site's
      /wp-content/uploads/ (GET only, no credentials, paced; the safety extension in
      CLAUDE.md) and upload them to the SANDBOX media library with mbc_live_source_url
      meta. Idempotent (matched on that meta). The next `draft` relinks to the sandbox
      copies. --alts maps a file name to alt text (applied to the media item).

  elementor_exit.py self-test
      Static checks that this file can only reach live through import_live_posts.LiveReader.

  elementor_exit.py publish PAGE_ID DRAFT_ID [--apply]
      On approval: back up, copy the draft's content and template into the
      page, remove its Elementor meta, then TRASH the draft (never force-delete).

Typo rule: "verbatim" means don't lose content, not preserve errors; use --fix for
each correction (it must match, and is reported). --facts-file takes the same options
as JSON (keys when, where, who, contact, alt, image_id, fix as a list; also
description and description_prefix as lists of paragraphs, and image_fit "contain"). Dry run by default for
draft and publish. Backup rule: before any Elementor meta
is removed, _elementor_data, _elementor_edit_mode, _elementor_page_settings and
post_content are saved and the file is read back as valid JSON.
"""
import argparse
import ast
import html
import json
import mimetypes
import re
import sys
import urllib.parse
from datetime import datetime, timezone
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import import_live_posts as lp  # noqa: E402  (its LiveReader is the only live client)
import scaffold_site as ss  # noqa: E402
import seed_patterns as sp  # noqa: E402

BACKUP_DIR = Path.home() / "src" / "mbc-elementor-backups"
ELEMENTOR_KEYS = ("_elementor_data", "_elementor_edit_mode", "_elementor_page_settings", "_elementor_template_type")
DROPPED_STYLES = {"color", "background", "background-color", "font-family", "font-size", "line-height", "text-shadow"}
ACTIVITY_TEMPLATE = "page-templates/activity.php"
NEEDS = "Needs confirming"


def get_page(wp, page_id):
    return wp.call("GET", f"pages/{page_id}", {"context": "edit",
                   "_fields": "id,slug,title,status,template,parent,menu_order,link,featured_media,content.raw,meta"})


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


def typography(fragment):
    """Curly apostrophes and quotes in text (never inside tags or attributes).

    WordPress curls them on display anyway; this makes the stored text match.
    Double quotes are only converted when they pair up within the fragment.
    """
    parts = re.split(r"(<[^>]+>)", fragment)
    pair_ok = "".join(x for x in parts if not x.startswith("<")).count('"') % 2 == 0
    is_open, out = True, []
    for part in parts:
        if part.startswith("<"):
            out.append(part)
            continue
        part = re.sub(r"(?<=\w)'(?=\w)", "\u2019", part)             # it's, MBC's
        part = re.sub(r"(?<=[sS])'(?=[\s.,;:!?)]|$)", "\u2019", part)  # Seniors' Worker
        if pair_ok:
            def curl(_m):
                nonlocal is_open
                ch = "\u201c" if is_open else "\u201d"
                is_open = not is_open
                return ch
            part = re.sub(r'"', curl, part)
        out.append(part)
    return "".join(out)


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
                part = re.sub(r"\s*<br\s*/?>\s*", " ", part).strip()  # a single <br> is a stray line break
                if part:
                    out.append(f"<!-- wp:paragraph -->\n<p>{typography(part)}</p>\n<!-- /wp:paragraph -->")
    return "\n\n".join(out), warnings


INFO_TEMPLATE = "page-templates/info.php"
ALLOWED_INLINE = {"a", "strong", "em", "br", "sup", "sub"}
OFFICE_CTA = "Church office: Lesley, 0113 2693750, admin@moortownbaptistchurch.org.uk"


def relativise(url):
    """Same-site absolute URLs become relative, so no domain is baked into content."""
    return re.sub(r"^https?://(?:www\.|new\.)?moortownbaptistchurch\.org\.uk(?=/)", "", url)


def clean_inline(fragment, unbold=False):
    """Keep only simple inline markup: links, strong, em, br, sup, sub. Drops all
    classes, styles, ids and other attributes (and span/div/font wrappers)."""
    def fix(m):
        closing, tag, attrs = m.group(1), m.group(2).lower(), m.group(3)
        tag = {"b": "strong", "i": "em"}.get(tag, tag)
        if tag not in ALLOWED_INLINE or (unbold and tag == "strong"):
            return ""
        if closing:
            return f"</{tag}>"
        if tag != "a":
            return "<br />" if tag == "br" else f"<{tag}>"
        keep = []
        for name in ("href", "target", "rel"):
            mm = re.search(rf'\b{name}\s*=\s*"([^"]*)"', attrs)
            if mm:
                keep.append(f'{name}="{relativise(mm.group(1)) if name == "href" else mm.group(1)}"')
        if 'target="_blank"' in keep and not any(k.startswith("rel=") for k in keep):
            keep.append('rel="noopener"')
        return "<a " + " ".join(keep) + ">"
    return re.sub(r"<(/?)([a-zA-Z0-9]+)([^>]*)>", fix, fragment)


def para_block(text, unbold=False, headings=()):
    """A paragraph block (or an h2 if the whole text is one of the given heading words)."""
    text = typography(re.sub(r"[ \u00a0]{2,}", " ", re.sub(r"\s*<br\s*/?>\s*", " ", clean_inline(text, unbold))).strip())
    plain = re.sub(r"<[^>]+>", "", text).strip()
    if not plain:
        return []
    if plain in headings:
        return [f'<!-- wp:heading -->\n<h2 class="wp-block-heading">{plain}</h2>\n<!-- /wp:heading -->']
    return [f"<!-- wp:paragraph -->\n<p>{text}</p>\n<!-- /wp:paragraph -->"]


def split_paragraphs(fragment, unbold=False, headings=()):
    out = []
    for part in re.split(r"(?:\s*<br\s*/?>\s*){2,}", fragment):
        out += para_block(part, unbold, headings)
    return out


def list_block(tag, inner, unbold=False):
    items = re.findall(r"<li\b[^>]*>(.*?)</li>", inner, re.S | re.I)
    attrs = ' {"ordered":true}' if tag == "ol" else ""
    lis = "\n\n".join(f"<!-- wp:list-item -->\n<li>{typography(clean_inline(i, unbold).strip())}</li>\n<!-- /wp:list-item -->" for i in items)
    return [f'<!-- wp:list{attrs} -->\n<{tag} class="wp-block-list">{lis}</{tag}>\n<!-- /wp:list -->']


def strip_wrappers(text):
    """Drop pasted <div> wrappers; each closing </div> is a paragraph boundary."""
    text = re.sub(r"</div>", "<br><br>", text, flags=re.I)
    text = re.sub(r"<div\b[^>]*>", "", text, flags=re.I)
    return text.strip()


def html_to_blocks(fragment, unbold=False, headings=()):
    """Block markup for a text-editor fragment: p, h1-h6, ul/ol and loose text."""
    out, pos = [], 0
    for m in re.finditer(r"<(p|h[1-6]|ul|ol|blockquote)\b[^>]*>(.*?)</\1>", fragment, re.S | re.I):
        out += split_paragraphs(strip_wrappers(fragment[pos:m.start()]), unbold, headings)
        tag, inner = m.group(1).lower(), m.group(2)
        if tag == "p":
            out += split_paragraphs(inner, unbold, headings)
        elif tag[0] == "h":
            level = int(tag[1])
            text = typography(clean_inline(inner, True).strip())
            attrs = "" if level == 2 else f' {{"level":{level}}}'
            out.append(f'<!-- wp:heading{attrs} -->\n<h{level} class="wp-block-heading">{text}</h{level}>\n<!-- /wp:heading -->')
        elif tag in ("ul", "ol"):
            out += list_block(tag, inner, unbold)
        else:  # blockquote
            out.append("<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">" + "".join(
                f"<!-- wp:paragraph -->\n<p>{typography(clean_inline(x, unbold).strip())}</p>\n<!-- /wp:paragraph -->"
                for x in re.findall(r"<p\b[^>]*>(.*?)</p>", inner, re.S)) + "</blockquote>\n<!-- /wp:quote -->")
        pos = m.end()
    out += split_paragraphs(strip_wrappers(fragment[pos:]), unbold, headings)
    return out


def image_block(img):
    rel = relativise(img["url"])
    mid = int(img["id"]) if str(img.get("id", "")).isdigit() else None
    attrs = (f'"id":{mid},' if mid else "") + '"sizeSlug":"large","linkDestination":"none"'
    cls = f' class="wp-image-{mid}"' if mid else ""
    return (f'<!-- wp:image {{{attrs}}} -->\n<figure class="wp-block-image size-large"><img src="{rel}" '
            f'alt="{html.escape(img.get("alt", "") or "", quote=True)}"{cls}/></figure>\n<!-- /wp:image -->')


def cta_block(heading, text):
    return ('<!-- wp:group {"className":"mbc-cta"} -->\n<div class="wp-block-group mbc-cta"><!-- wp:heading {"level":3} -->\n'
            f'<h3 class="wp-block-heading">{html.escape(heading)}</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>{html.escape(text)}</p>\n<!-- /wp:paragraph -->\n\n'
            '<!-- wp:buttons -->\n<div class="wp-block-buttons"><!-- wp:button -->\n<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/contact-us/">Contact us</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:group -->')


def build_info(page, facts, extra, fixes, image_override):
    """Info-pattern content from the page's Elementor widgets, in document order."""
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    tree = json.loads(meta.get("_elementor_data") or "[]")
    widgets, notes = [], []

    def walk(nodes):
        for n in nodes:
            if n.get("elType") == "widget":
                widgets.append(n)
            walk(n.get("elements", []))
    walk(tree)
    unbold, headings = bool(extra.get("unbold")), tuple(extra.get("headings", ()))
    blocks = []
    for w in widgets:
        kind, st = w["widgetType"], w.get("settings", {})
        if kind == "text-editor":
            blocks += html_to_blocks(st.get("editor", ""), unbold, headings)
        elif kind == "heading":
            level = int(str(st.get("header_size", "h2"))[-1]) if str(st.get("header_size", "h2"))[-1].isdigit() else 2
            attrs = "" if level == 2 else f' {{"level":{level}}}'
            blocks.append(f'<!-- wp:heading{attrs} -->\n<h{level} class="wp-block-heading">{typography(html.escape(html.unescape(st.get("title", ""))))}</h{level}>\n<!-- /wp:heading -->')
        elif kind == "image" and isinstance(st.get("image"), dict):
            blocks.append(image_block(dict(st["image"], alt=extra.get("alt") or st["image"].get("alt", ""))))
        else:
            notes.append(f"Elementor widget NOT converted: {kind}")
    if extra.get("lead", True) and blocks and blocks[0].startswith("<!-- wp:paragraph -->"):
        blocks[0] = blocks[0].replace("<!-- wp:paragraph -->", '<!-- wp:paragraph {"className":"mbc-lead"} -->', 1).replace("<p>", '<p class="mbc-lead">', 1)
        notes.append("first paragraph is the lead")
    if image_override:
        img = dict(image_override, alt=extra.get("alt") or image_override.get("alt", ""))
        blocks.insert(1 if blocks else 0, image_block(img))
        notes.append(f"image: media {img['id']} (featured image)")
    if extra.get("cta", True):
        blocks.append(cta_block(extra.get("cta_heading", "Got a question?"), facts["contact"] if facts.get("contact") else OFFICE_CTA))
    body = "\n\n".join(blocks)
    for old, new in fixes:
        count = body.count(old)
        if not count:
            sys.exit(f"--fix {old!r}: not found in the converted text")
        body = body.replace(old, new)
        notes.append(f"fixed ({count}x): {old!r} -> {new!r}")
    return body, notes


def build_activity(page, facts, alt=None, fixes=(), image_override=None, extra=None):
    texts, images, other = elementor_parts(page)
    body = sp.pattern_markup("activity-details")
    notes = []
    for label, key in (("When", "when"), ("Where", "where"), ("Who it’s for", "who"), ("Contact", "contact")):
        body, n = re.subn(rf"(<p><strong>{re.escape(label)}</strong> ).*?(</p>)", lambda m: m.group(1) + html.escape(facts[key]) + m.group(2), body)
        if n != 1:
            sys.exit(f"pattern has no '{label}' fact line")
    if image_override or images:
        img = image_override or images[0]
        skipped = len(images) - (0 if image_override else 1)
        rel = re.sub(r"^https?://[^/]+", "", img.get("url", ""))
        attrs = f'"mediaId":{int(img["id"])},' if str(img.get("id", "")).isdigit() else ""
        body = body.replace('{"mediaType":"image","className":"mbc-activity__top"}', "{" + attrs + '"mediaType":"image","className":"mbc-activity__top"}', 1)
        cls = f' class="wp-image-{int(img["id"])}"' if attrs else ""
        body = re.sub(r'<img src="/wp-content/themes/generatepress-child/images/placeholder\.svg" alt=""/>',
                      f'<img src="{rel}" alt="{html.escape(alt if alt is not None else img.get("alt", ""), quote=True)}"{cls}/>', body, count=1)
        if skipped > 0:
            notes.append(f"{skipped} Elementor image widget(s) not carried over")
        if image_override:
            notes.append(f"image: media {img['id']} (the page's featured image unless --image-id)")
        if not (alt or img.get("alt")):
            notes.append("the image has no alt text")
    else:
        notes.append("no image in the Elementor page: placeholder kept")
    extra = extra or {}
    if extra.get("description"):  # John-approved wording replaces the extracted text
        desc, warnings = paragraphs([f"<p>{x}</p>" for x in extra["description"]])
        notes.append("description wording supplied (split and tidied from the original text)")
    else:
        desc, warnings = paragraphs(texts)
    if extra.get("description_prefix"):
        pre, _ = paragraphs([f"<p>{x}</p>" for x in extra["description_prefix"]])
        desc = pre + "\n\n" + desc
        notes.append("note paragraph(s) added at the top of the description: remove before publishing")
    for old, new in fixes:
        count = desc.count(old)
        if not count:
            sys.exit(f"--fix {old!r}: not found in the text")
        desc = desc.replace(old, new)
        notes.append(f"typo fixed ({count}x): {old!r} -> {new!r}")
    if not desc:
        notes.append("no text found")
    block = "<!-- wp:paragraph -->\n<p>Describe what happens, what to expect and how to take part.</p>\n<!-- /wp:paragraph -->"
    if block not in body:
        sys.exit("pattern description block not found")
    body = body.replace(block, desc or block, 1)
    if extra.get("image_fit") == "contain":  # wide graphics: show uncropped (see css/patterns.css)
        body = body.replace('"className":"mbc-activity__top"', '"className":"mbc-activity__top mbc-image-contain"', 1)
        body = body.replace('is-stacked-on-mobile mbc-activity__top"', 'is-stacked-on-mobile mbc-activity__top mbc-image-contain"', 1)
        notes.append("image shown uncropped (contain)")
    if other:
        notes.append(f"other Elementor widgets NOT converted: {other}")
    return body, notes + warnings, bool(texts or images)


def cmd_draft(wp, args):
    page = get_page(wp, args.page_id)
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    if meta.get("_elementor_edit_mode") != "builder":
        sys.exit(f"page {args.page_id} is not Elementor-built")
    extra = {}
    if args.facts_file:  # same options as JSON; command-line options win
        given = json.loads(Path(args.facts_file).read_text())
        for key in ("when", "where", "who", "contact", "alt", "image_id"):
            if getattr(args, key, None) is None and key in given:
                setattr(args, key, given[key])
        extra = {k: given[k] for k in ("description", "description_prefix", "image_fit", "kind", "lead", "unbold", "headings",
                                       "cta", "cta_heading", "image", "content_file") if k in given}
        args.fix = list(args.fix or []) + list(given.get("fix", []))
    facts = {"when": args.when or NEEDS, "where": args.where or NEEDS, "who": args.who or NEEDS, "contact": args.contact or NEEDS}
    fixes = []
    for f in args.fix or []:
        if "=>" not in f:
            sys.exit(f"--fix needs OLD=>NEW, got {f!r}")
        fixes.append(tuple(f.split("=>", 1)))
    kind = extra.get("kind", "activity")
    template = INFO_TEMPLATE if kind == "info" else ACTIVITY_TEMPLATE
    image_override = None
    widget_images = elementor_parts(page)[1]
    media_id = getattr(args, "image_id", None) or (None if widget_images else page.get("featured_media"))
    if kind == "info" and extra.get("image") is False:
        media_id = None
    if media_id:
        m = wp.call("GET", f"media/{int(media_id)}", {"context": "edit", "_fields": "id,source_url,alt_text,media_details"})
        large = m["media_details"].get("sizes", {}).get("large", {}).get("source_url") or m["source_url"]
        image_override = {"id": m["id"], "url": large, "alt": m["alt_text"]}
    if extra.get("content_file"):  # hand-authored block markup (for bespoke pages)
        body, notes = Path(extra["content_file"]).read_text(), ["content supplied from a file (hand-authored blocks)"]
        for bad, why in ((r"<style\b", "a <style> element"), (r"\bstyle\s*=", "an inline style"), (r"elementor", "Elementor markup"),
                         (r"#[0-9a-fA-F]{6}\b", "a hard-coded colour"), (r"moortownbaptistchurch\.org\.uk/wp-content", "an absolute site URL")):
            if re.search(bad, body):
                sys.exit(f"content file contains {why}: not allowed (tokens and relative URLs only)")
    elif kind == "info":
        body, notes = build_info(page, facts, dict(extra, alt=args.alt), fixes, image_override)
    else:
        body, notes, ok = build_activity(page, facts, args.alt, fixes, image_override, extra)
    if "/wp-content/uploads/" in body:  # files already copied to the sandbox (see `assets`): link to those
        body = relink_assets(body, sandbox_assets(lp.SandboxWriter(lp.load_env())))
    title = f"{page['title']['raw']} (block draft)"
    slug = f"{page['slug']}-block-draft"
    existing = wp.call("GET", "pages", {"slug": slug, "status": "draft,publish,private,pending", "_fields": "id"})
    print(f"source page {page['id']} {page['link']} (left untouched)")
    print(f"draft: {title!r}, parent {page['parent']}, template {template}, {'update ' + str(existing[0]['id']) if existing else 'create'}")
    if kind == "activity":
        for k, v in facts.items():
            print(f"   {k:<8} {v}")
    for n in notes:
        print("   NOTE:", n)
    if not args.apply:
        print("dry run: nothing written (use --apply)")
        return
    if kind == "info" and image_override and (args.alt or image_override.get("alt")) and args.alt:
        wp.call("POST", f"media/{int(image_override['id'])}", body={"alt_text": args.alt}, params={"_fields": "id"})
        print(f"alt text set on media {image_override['id']}")
    elif args.alt and kind == "activity":  # also put the alt text on the media library item
        target = image_override or (widget_images[0] if widget_images else None)
        if target and str(target.get("id", "")).isdigit():
            wp.call("POST", f"media/{int(target['id'])}", body={"alt_text": args.alt}, params={"_fields": "id"})
            print(f"alt text set on media {target['id']}")
    payload = {"title": title, "slug": slug, "status": "draft", "parent": page["parent"], "template": template, "content": body}
    if existing:
        made = wp.call("POST", f"pages/{existing[0]['id']}", body=payload, params={"_fields": "id,link"})
    else:
        made = wp.call("POST", "pages", body=payload, params={"_fields": "id,link"})
    print(f"draft page {made['id']} saved (status draft, not published).")
    print(f"preview (log in first): {wp.base}/?page_id={made['id']}&preview=true")


UPLOAD_URL_RE = re.compile(r"https?://(?:www\.)?moortownbaptistchurch\.org\.uk/wp-content/uploads/[^\"'\s<>\\)]+")


def live_upload_urls(*texts):
    """Live /wp-content/uploads/ URLs mentioned in the given texts (JSON escapes undone)."""
    found = []
    for text in texts:
        for url in UPLOAD_URL_RE.findall(html.unescape((text or "").replace("\\/", "/"))):
            if url not in found:
                found.append(url)
    return found


def sandbox_assets(sb):
    """{live URL: media item} for every sandbox media item that carries mbc_live_source_url."""
    found, page = {}, 1
    while True:
        items, headers = sb.call("GET", "media", {"per_page": 100, "page": page, "context": "edit",
                                                   "_fields": "id,source_url,meta"}, retries=2)
        for m in items:
            src = (m.get("meta") or {}).get("mbc_live_source_url") if isinstance(m.get("meta"), dict) else None
            if src:
                found[src] = m
        if page >= int(headers.get("x-wp-totalpages", 1)):
            return found
        page += 1


def relink_assets(body, assets):
    """Point links and images at the sandbox copy of each uploaded asset."""
    for live_url, item in assets.items():
        old = urllib.parse.urlsplit(live_url).path
        new = urllib.parse.urlsplit(item["source_url"]).path
        if old != new:
            body = body.replace(f'"{old}"', f'"{new}"')
    return body


def cmd_assets(wp, args):
    page = get_page(wp, args.page_id)
    meta = page["meta"] if isinstance(page["meta"], dict) else {}
    texts = [meta.get("_elementor_data"), page["content"]["raw"]]
    for path in BACKUP_DIR.glob(f"{page['id']}-*.json"):  # a converted page: its backup still names the files
        data = json.loads(path.read_text())
        texts += [data["elementor"].get("_elementor_data"), data.get("post_content")]
    urls = live_upload_urls(*texts)
    sb = lp.SandboxWriter(lp.load_env())
    have = sandbox_assets(sb)
    alts = json.loads(Path(args.alts).read_text()) if args.alts else {}
    todo = [u for u in urls if u not in have]
    print(f"{'APPLY' if args.apply else 'DRY RUN'}: page {page['id']} ({page['slug']}) references {len(urls)} live upload(s); "
          f"{len(urls) - len(todo)} already on the sandbox, {len(todo)} to fetch")
    for u in urls:
        print(f"   {'have ' if u in have else 'fetch'} {Path(urllib.parse.urlsplit(u).path).name}")
    if not args.apply:
        print("dry run: nothing downloaded or written (use --apply)")
        return
    live = lp.LiveReader()
    for u in todo:
        name = Path(urllib.parse.urlsplit(u).path).name
        data = live.get_bytes(u)
        mime = mimetypes.guess_type(name)[0] or "application/octet-stream"
        made, _ = sb.call("POST", "media", raw=data, headers={"Content-Type": mime,
                          "Content-Disposition": f'attachment; filename="{name}"'})
        sb.call("POST", f"media/{made['id']}", body={"alt_text": alts.get(name, ""), "meta": {"mbc_live_source_url": u}},
                params={"_fields": "id"})
        have[u] = {"id": made["id"], "source_url": made["source_url"]}
        print(f"uploaded {name} -> media {made['id']} ({len(data)} bytes)")
    for u in urls:
        name = Path(urllib.parse.urlsplit(u).path).name
        if u not in todo and name in alts:
            sb.call("POST", f"media/{have[u]['id']}", body={"alt_text": alts[name]}, params={"_fields": "id"})
            print(f"alt text set on media {have[u]['id']} ({name})")
    print(f"\nDone. Live requests made: {live.request_count}")


def self_test():
    """Static checks: the only way this file reaches the live site is LiveReader.get_bytes, inside cmd_assets."""
    tree = ast.parse(Path(__file__).read_text())
    parents = {c: p for p in ast.walk(tree) for c in ast.iter_child_nodes(p)}

    def func_of(node):
        while node in parents:
            node = parents[node]
            if isinstance(node, ast.FunctionDef):
                return node.name
        return None

    results = []

    def check(ok, text):
        results.append(ok)
        print(("PASS " if ok else "FAIL ") + text)

    calls = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and isinstance(n.func, ast.Attribute)]
    raw = [n for n in calls if n.func.attr in ("request", "putrequest", "urlopen", "build_opener", "create_connection",
                                               "send", "sendall", "connect", "getresponse")]
    check(not raw, "this file makes no network calls of its own (everything goes through import_live_posts)")
    live_calls = [n for n in calls if isinstance(n.func.value, ast.Name) and n.func.value.id == "live"]
    check(bool(live_calls) and all(n.func.attr == "get_bytes" and func_of(n) == "cmd_assets" for n in live_calls),
          f"the live reader is used only for get_bytes, only in cmd_assets ({len(live_calls)} call site)")
    check(not [n for n in calls if n.func.attr == "get_json"], "live get_json is never called here")
    made = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and getattr(n.func, "attr", "") == "LiveReader"]
    check(len(made) == 1 and func_of(made[0]) == "cmd_assets", "LiveReader is created once, in cmd_assets")
    imported = {a.name for n in ast.walk(tree) if isinstance(n, ast.Import) for a in n.names}
    imported |= {n.module for n in ast.walk(tree) if isinstance(n, ast.ImportFrom) and n.module}
    check(not imported & {"urllib.request", "http.client", "socket", "subprocess", "requests"}, "no networking or subprocess imports")
    check(bool(live_upload_urls('{"a":"https:\\/\\/moortownbaptistchurch.org.uk\\/wp-content\\/uploads\\/x.pdf"}'))
          and not live_upload_urls("https://moortownbaptistchurch.org.uk/wp-admin/x.php"), "URL finder accepts uploads only")
    print("\nSELF-TEST", "PASSED" if all(results) else "FAILED")
    return all(results)


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
    d.add_argument("--alt", help="alt text for the image")
    d.add_argument("--image-id", type=int, help="media ID to use (default: the Elementor image, else the page's featured image)")
    d.add_argument("--fix", action="append", metavar="OLD=>NEW", help="correct a typo in the text (repeatable)")
    d.add_argument("--facts-file", help="JSON file with when/where/who/contact/alt/fix")
    d.add_argument("--apply", action="store_true")
    a = sub.add_parser("assets"); a.add_argument("page_id", type=int); a.add_argument("--alts"); a.add_argument("--apply", action="store_true")
    sub.add_parser("self-test")
    p = sub.add_parser("publish"); p.add_argument("page_id", type=int); p.add_argument("draft_id", type=int); p.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    if args.cmd == "self-test":
        sys.exit(0 if (self_test() and lp.self_test()) else 1)
    wp = ss.WP(ss.load_env())
    {"backup": lambda: backup(wp, args.page_id), "draft": lambda: cmd_draft(wp, args), "assets": lambda: cmd_assets(wp, args),
     "publish": lambda: cmd_publish(wp, args)}[args.cmd]()


if __name__ == "__main__":
    main()
