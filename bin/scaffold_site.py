#!/usr/bin/python3
"""Idempotent scaffold of the MBC sandbox: pages, hierarchy, slugs, templates
and the primary menu, built from the structure in CLAUDE.md.

Dry run by default; pass --apply to make changes. Safe to re-run: only
differences are changed. Sandbox only (URL comes from ~/.config/mbc/wp.env).
"""
import argparse
import base64
import html
import http.client
import json
import sys
import time
import urllib.parse
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent
THEME = REPO / "generatepress-child"
DOCS = REPO / "docs"
SNAPSHOT = DOCS / "url-snapshot.json"
REPORT = DOCS / "url-changes.md"

# Services on YouTube = the channel's Live tab (past Sunday service streams).
# Keep in step with youtube_services_url in inc/site-info.php.
YOUTUBE_SERVICES_URL = "https://www.youtube.com/@MoortownBaptistChurch/streams"
USER_AGENT = "MBC-scaffold/1.0 (John, Moortown Baptist Church)"
CONNECT_TIMEOUT = 8          # seconds per TCP connect attempt
CONNECT_ATTEMPTS = 4
CONNECT_BACKOFF = (1, 2, 4)  # seconds between connect attempts
READ_TIMEOUT = 240           # sandbox writes can be slow
MENU_NAME = "Primary"
MENU_LOCATION = "primary"
TOGGLE_CLASS = "mbc-menu-toggle"

# Template group -> theme file. Applied only if the file exists in the theme.
TEMPLATES = {
    "info": "page-templates/info.php",
    "activity": "page-templates/activity.php",
    "mission": "page-templates/mission-partner.php",
    "listing": "page-templates/listing.php",
}

# (slug, existing id or None, title, template group or None)
TOP = [
    ("about-us", 341, "About Us", None),
    ("whats-on", 327, "What's On", None),
    ("mission", 374, "Mission", None),
    ("resources", None, "Resources", None),
    ("news", None, "News", "listing"),
    ("contact-us", 211, "Contact Us", "listing"),
]
# parent slug -> children in menu order. A fifth element "external" marks a
# menu-only external link (the page itself is kept for the legacy feed).
CHILDREN = {
    "about-us": [
        ("who-we-are", 343, "Who we are", "info"),
        ("what-we-believe", 345, "What we believe", "info"),
        ("meet-the-team", 42, "Meet the team", "info"),
        ("faqs", None, "FAQs", "info"),
    ],
    "whats-on": [
        ("sunday-mornings", 44, "Sunday Mornings", "activity"),
        ("events", None, "Events", "listing"),
        ("beacon-cafe", 251, "Beacon Cafe", "activity"),
        ("rock-solid", 60, "Rock Solid", "activity"),
        ("stepping-stones", 48, "Stepping Stones", "activity"),
        ("lunch-club", 56, "Lunch Club", "activity"),
        ("craft-group", 565, "Craft Group", "activity"),
        ("house-groups", 46, "House Groups", "activity"),
    ],
    "mission": [
        ("mission-group", None, "Mission Group", "mission"),
        ("bms", 467, "BMS", "mission"),
        ("leeds-furniture-mission", 452, "Leeds Furniture Mission", "mission"),
        ("mission-aviation-fellowship", 455, "Mission Aviation Fellowship", "mission"),
        ("romania", 525, "Romania", "mission"),
        ("moortown-emergency-relief-fund", None, "Moortown Emergency Relief Fund", "mission"),
        ("pathways", None, "Pathways", "mission"),
    ],
    "resources": [
        ("bu-and-yba", None, "BU & YBA", "info"),
        ("services-on-youtube", 350, "Services on YouTube", "info", "external"),
        ("room-hire", 391, "Room Hire", "info"),
        ("policies-and-documents", 276, "Policies & Documents", "info"),
        ("giving", None, "Giving", "info"),
    ],
}
# Existing pages that only need reparenting (drafts, not in the menu).
HOLD = [(54, "whats-on"), (556, "whats-on")]
# Old parents to trash once empty.
TRASH_PARENTS = [237, 270]

STATUSES = "publish,draft,pending,private,future"
PAGE_FIELDS = "id,slug,title,parent,menu_order,template,status,link,meta._elementor_edit_mode"


def load_env():
    path = Path.home() / ".config" / "mbc" / "wp.env"
    env = {}
    try:
        for line in path.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, v = line.split("=", 1)
                env[k.replace("export ", "").strip()] = v.strip().strip("'\"")
    except OSError as exc:
        sys.exit(f"Cannot read {path}: {exc}")
    for key in ("WP_URL", "WP_USER", "WP_APP_PASSWORD"):
        if not env.get(key):
            sys.exit(f"{key} missing from {path}")
    env["WP_URL"] = env["WP_URL"].rstrip("/")
    return env


class _QuickConnect(http.client.HTTPSConnection):
    """HTTPS connection with a short connect timeout and quick retries.

    From John's IP, TCP connects to the server intermittently stall for
    20-68 s (about 1 in 10). A fresh attempt almost always succeeds at once,
    so connect with a short timeout and retry instead of waiting it out.
    """

    read_timeout = READ_TIMEOUT

    def connect(self):
        last = None
        for attempt in range(CONNECT_ATTEMPTS):
            self.timeout = CONNECT_TIMEOUT
            try:
                super().connect()
                self.sock.settimeout(self.read_timeout)
                return
            except OSError as exc:  # includes socket timeouts
                last = exc
                self.close()
                if attempt < CONNECT_ATTEMPTS - 1:
                    time.sleep(CONNECT_BACKOFF[attempt])
        raise last


class WP:
    """Sandbox REST client: keep-alive connection, quick connect retries."""

    def __init__(self, env):
        self.base = env["WP_URL"]
        self._netloc = urllib.parse.urlsplit(self.base).netloc
        self._conn = None
        token = base64.b64encode(f"{env['WP_USER']}:{env['WP_APP_PASSWORD']}".encode()).decode()
        self.auth = f"Basic {token}"

    def _reset(self):
        if self._conn is not None:
            self._conn.close()
        self._conn = None

    def call(self, method, route, params=None, body=None):
        path = f"/wp-json/wp/v2/{route}"
        if params:
            path += "?" + urllib.parse.urlencode(params)
        data = json.dumps(body).encode() if body is not None else None
        headers = {"Authorization": self.auth, "User-Agent": USER_AGENT}
        if data is not None:
            headers["Content-Type"] = "application/json"
        safe = method == "GET"
        if not safe:  # be gentle: one second between writes
            time.sleep(1.0)
        for attempt in range(3 if safe else 1):
            try:
                if self._conn is None:
                    self._conn = _QuickConnect(self._netloc, timeout=CONNECT_TIMEOUT)
                try:
                    self._conn.request(method, path, body=data, headers=headers)
                    resp = self._conn.getresponse()
                except (http.client.RemoteDisconnected, BrokenPipeError, ConnectionResetError):
                    self._conn.close()
                    if not safe:  # a write may have reached the server: never resend blindly
                        raise
                    self._conn.request(method, path, body=data, headers=headers)
                    resp = self._conn.getresponse()
                payload = resp.read()
                break
            except (http.client.HTTPException, OSError) as exc:
                self._reset()
                if attempt >= (2 if safe else 0):
                    sys.exit(f"Network error on {method} {route}: {exc or type(exc).__name__}"
                             + ("" if safe else " (idempotent: re-run to continue)"))
                time.sleep(2)
        if resp.status >= 400:
            sys.exit(f"HTTP {resp.status} on {method} {route}: {payload.decode(errors='replace')[:300]}")
        return json.loads(payload)


_probe_conns = {}


def probe(url):
    """Return (status, redirect target) for a GET, without following redirects."""
    parts = urllib.parse.urlsplit(url)
    target = (parts.path or "/") + (("?" + parts.query) if parts.query else "")
    for attempt in range(2):
        try:
            conn = _probe_conns.get(parts.netloc)
            if conn is None:
                conn = _probe_conns[parts.netloc] = _QuickConnect(parts.netloc, timeout=CONNECT_TIMEOUT)
                conn.read_timeout = 30
            conn.request("GET", target, headers={"User-Agent": USER_AGENT})
            resp = conn.getresponse()
            resp.read()
            return resp.status, resp.getheader("Location", "") or ""
        except (http.client.HTTPException, OSError) as exc:
            conn = _probe_conns.pop(parts.netloc, None)
            if conn is not None:
                conn.close()
            if attempt:
                return 0, str(exc) or type(exc).__name__
    return 0, "unreachable"


def fetch_pages(wp):
    pages = wp.call("GET", "pages", {
        "per_page": 100, "status": STATUSES, "context": "edit", "_fields": PAGE_FIELDS})
    return {p["id"]: p for p in pages}


def template_for(group):
    """Template file for a group, only if it exists in the theme."""
    if group and (THEME / TEMPLATES[group]).is_file():
        return TEMPLATES[group]
    return None


def build_spec():
    """Flat list of page specs in processing order (parents before children)."""
    spec = []
    for order, (slug, pid, title, group) in enumerate(TOP, 1):
        spec.append(dict(key=slug, id=pid, slug=slug, title=title, parent_key=None,
                         order=order, group=group, external=False))
    for parent_key, kids in CHILDREN.items():
        for order, kid in enumerate(kids, 1):
            slug, pid, title, group = kid[:4]
            spec.append(dict(key=slug, id=pid, slug=slug, title=title, parent_key=parent_key,
                             order=order, group=group, external=len(kid) > 4))
    return spec


def path_of(link, base):
    return link[len(base):] if link.startswith(base) else link


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--apply", action="store_true", help="make the changes (default: dry run)")
    args = ap.parse_args()
    apply = args.apply
    env = load_env()
    wp = WP(env)
    base = env["WP_URL"]
    print(f"{'APPLY' if apply else 'DRY RUN'} against {base}")

    pages = fetch_pages(wp)
    baseline = {}
    if SNAPSHOT.exists():
        baseline = {int(k): v for k, v in json.loads(SNAPSHOT.read_text()).items()}
    else:
        baseline = {i: path_of(p["link"], base) for i, p in pages.items() if "?page_id=" not in p["link"]}
        if apply:
            DOCS.mkdir(exist_ok=True)
            SNAPSHOT.write_text(json.dumps(baseline, indent=1, sort_keys=True) + "\n")
            print(f"Saved URL baseline to {SNAPSHOT.relative_to(REPO)}")

    spec = build_spec()
    ids = {s["key"]: s["id"] for s in spec}  # None = not yet created
    changes = 0

    # ---- pages -----------------------------------------------------------
    print("\n== Pages ==")
    for s in spec:
        parent_id = ids[s["parent_key"]] if s["parent_key"] else 0
        tpl = template_for(s["group"])
        want = {"title": s["title"], "slug": s["slug"], "parent": parent_id,
                "menu_order": s["order"]}
        if tpl:
            want["template"] = tpl
        if s["id"] is None and ids[s["key"]] is None and parent_id is not None:
            # Re-run: a page created earlier has no id in the spec; find it.
            found = next((p for p in pages.values()
                          if p["slug"] == s["slug"] and p["parent"] == parent_id), None)
            if found:
                s["id"] = ids[s["key"]] = found["id"]
        if s["id"] is None and ids[s["key"]] is None:
            changes += 1
            print(f"  CREATE {s['slug']!r} ({s['title']}) parent={s['parent_key'] or 'top'}"
                  f"{' template=' + tpl if tpl else ''}")
            if apply:
                body = dict(want, status="publish", content="")
                body["parent"] = parent_id
                made = wp.call("POST", "pages", body=body, params={"_fields": PAGE_FIELDS})
                if made["slug"] != s["slug"]:
                    sys.exit(f"Slug clash creating {s['slug']}: got {made['slug']}")
                ids[s["key"]] = made["id"]
                pages[made["id"]] = made
            continue
        page = pages.get(s["id"])
        if page is None:
            sys.exit(f"Expected page {s['id']} ({s['slug']}) not found")
        have = {"title": page["title"]["raw"], "slug": page["slug"], "parent": page["parent"],
                "menu_order": page["menu_order"], "template": page["template"]}
        diff = {k: v for k, v in want.items() if have.get(k) != v}
        meta = page.get("meta")
        if "template" in diff and isinstance(meta, dict) and meta.get("_elementor_edit_mode") == "builder":
            # Legacy Elementor content: leave the page on its current template
            # until it is converted (CLAUDE.md, "Elementor exit").
            print(f"  LEGACY {s['id']} {have['slug']!r}: built with Elementor, template {diff.pop('template')!r} not assigned")
        # In a dry run a not-yet-created parent has no id: always a change.
        if parent_id is None:
            diff["parent"] = "(new parent)"
        if diff:
            changes += 1
            print(f"  UPDATE {s['id']} {have['slug']!r}: " + ", ".join(
                f"{k}: {have.get(k)!r} -> {v!r}" for k, v in diff.items()))
            if apply:
                done = wp.call("POST", f"pages/{s['id']}", body=diff, params={"_fields": PAGE_FIELDS})
                if "slug" in diff and done["slug"] != diff["slug"]:
                    sys.exit(f"Slug clash on page {s['id']}: wanted {diff['slug']}, got {done['slug']}")
                pages[s["id"]] = done
    for pid, parent_key in HOLD:
        page = pages.get(pid)
        want_parent = ids[parent_key]
        if page and page["parent"] != want_parent:
            changes += 1
            print(f"  REPARENT {pid} {page['slug']!r}: parent {page['parent']} -> {parent_key}")
            if apply:
                pages[pid] = wp.call("POST", f"pages/{pid}", body={"parent": want_parent},
                                     params={"_fields": PAGE_FIELDS})

    # ---- trash old parents ----------------------------------------------
    print("\n== Old parents ==")
    if apply:
        pages = fetch_pages(wp)
    moving = {s["id"] for s in spec if s["id"]} | {pid for pid, _ in HOLD}
    for old in TRASH_PARENTS:
        page = pages.get(old)
        if page is None:
            print(f"  {old}: already gone")
            continue
        left = [p["id"] for p in pages.values()
                if p["parent"] == old and (apply or p["id"] not in moving)]
        if left:
            sys.exit(f"Refusing to trash {old}: children {left} not reparented")
        changes += 1
        print(f"  TRASH {old} {page['slug']!r} (no children left)")
        if apply:
            wp.call("DELETE", f"pages/{old}", params={"_fields": "id"})

    # ---- menu -----------------------------------------------------------
    print("\n== Menu ==")
    menus = wp.call("GET", "menus", {"per_page": 100, "context": "edit"})
    menu = next((m for m in menus if m["name"] == MENU_NAME), None)
    menu_id = menu["id"] if menu else None
    if menu is None:
        changes += 1
        print(f"  CREATE menu {MENU_NAME!r}")
        if apply:
            menu_id = wp.call("POST", "menus", body={"name": MENU_NAME})["id"]
    existing = []
    if menu_id:
        existing = wp.call("GET", "menu-items", {"menus": menu_id, "per_page": 100, "context": "edit"})
    items = {(i["parent"], html.unescape(i["title"]["raw"])): i for i in existing}
    seen = set()

    def want_item(title, parent_item, order, kind, **extra):
        nonlocal changes
        want = dict(title=title, parent=parent_item, menu_order=order, status="publish",
                    menus=menu_id, **extra)
        cur = items.get((parent_item, title)) if parent_item is not None else None
        label = f"{'  ' if parent_item else ''}{title}"
        if cur is None:
            changes += 1
            print(f"  ADD    {label} [{kind}]")
            if not apply:
                return None
            made = wp.call("POST", "menu-items", body=want)
            seen.add(made["id"])
            return made["id"]
        seen.add(cur["id"])
        norm = lambda c: [x for x in c if x]
        diff = {}
        for k, v in want.items():
            have = cur.get(k)
            if k == "title":
                same = html.unescape(cur["title"]["raw"]) == v
            elif k == "classes":
                same = norm(have or []) == norm(v)
            elif k == "menus":
                same = have == v or have == [v]
            else:
                same = have == v
            if not same:
                diff[k] = v
        if diff:
            changes += 1
            print(f"  UPDATE {label}: {', '.join(diff)}")
            if apply:
                wp.call("POST", f"menu-items/{cur['id']}", body=diff)
        return cur["id"]

    for order, (slug, _pid, title, _g) in enumerate(TOP, 1):
        pid = ids[slug]
        if slug in CHILDREN:  # parents are non-linking toggles
            top_item = want_item(title, 0, order, "toggle", type="custom", url="#",
                                 classes=[TOGGLE_CLASS], target="")
        else:
            top_item = want_item(title, 0, order, "page", type="post_type", object="page",
                                 object_id=pid or 0, classes=[], target="")
            continue
        for corder, kid in enumerate(CHILDREN[slug], 1):
            kslug, _kid, ktitle, _kg = kid[:4]
            if len(kid) > 4:
                want_item(ktitle, top_item or -1, corder, "external", type="custom",
                          url=YOUTUBE_SERVICES_URL, classes=[], target="_blank")
            else:
                want_item(ktitle, top_item or -1, corder, "page", type="post_type",
                          object="page", object_id=ids[kslug] or 0, classes=[], target="")
    if apply and menu_id:
        for it in existing:
            if it["id"] not in seen:
                changes += 1
                print(f"  DELETE extra menu item {html.unescape(it['title']['raw'])!r}")
                wp.call("DELETE", f"menu-items/{it['id']}", params={"force": "true"})
        locs = wp.call("GET", f"menus/{menu_id}", {"context": "edit"}).get("locations", [])
        if MENU_LOCATION not in locs:
            changes += 1
            print(f"  ASSIGN menu to location {MENU_LOCATION!r}")
            wp.call("POST", f"menus/{menu_id}", body={"locations": [MENU_LOCATION]})
    elif not apply:
        cur_loc = wp.call("GET", "menu-locations").get(MENU_LOCATION, {}).get("menu")
        if cur_loc != menu_id:
            changes += 1
            print(f"  ASSIGN menu to location {MENU_LOCATION!r} (now menu {cur_loc}; it stays, unassigned)")

    print(f"\n{changes} change(s) {'made' if apply else 'planned'}.")
    if changes == 0:
        print("No changes: already in the desired state.")

    # ---- URL report -----------------------------------------------------
    if apply:
        pages = fetch_pages(wp)
        rows = []
        for pid, old in sorted(baseline.items()):
            page = pages.get(pid)
            new = path_of(page["link"], base) if page else "(trashed)"
            if page and "?page_id=" in page["link"]:
                new = "(draft)"
            if old.rstrip("/") != new.rstrip("/"):  # trailing slash alone is not a change
                status, loc = probe(base + old)
                rows.append((old, new, status, path_of(loc, base) if loc else ""))
        bad = [(p["id"], p["link"]) for p in pages.values()
               if p["status"] == "publish" and probe(p["link"])[0] != 200]
        write_report(rows, bad)
        print(f"Wrote {REPORT.relative_to(REPO)} ({len(rows)} changed URL(s)).")
        print("Published pages not returning 200:", bad or "none")


def write_report(rows, bad):
    lines = [
        "# Sandbox URL changes",
        "",
        "Generated by `bin/scaffold_site.py --apply`. **This maps sandbox URLs only.**",
        "Before launch a live-old to new map is still needed. Build it from the live",
        "site's public REST API (read-only), which needs a narrow exception to the",
        "protect-live hook at that point.",
        "",
        "WordPress does not keep old-slug redirects for pages, so the last two columns",
        "show what the old URL does today: a 404 needs a redirect before launch.",
        "",
        "| Old path | New path | Old URL now | Redirects to |",
        "|---|---|---|---|",
    ]
    for old, new, status, loc in rows:
        lines.append(f"| `{old}` | `{new}` | {status} | {('`' + loc + '`') if loc else ''} |")
    if bad:
        lines += ["", "Published pages not returning 200 after the run: " + ", ".join(f"{i} ({l})" for i, l in bad)]
    DOCS.mkdir(exist_ok=True)
    REPORT.write_text("\n".join(lines) + "\n")


if __name__ == "__main__":
    main()
