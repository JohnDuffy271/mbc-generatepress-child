#!/usr/bin/python3
"""Create the "Footer" menu, assign it to the `footer` location and keep its links in step.

Idempotent (looks up by slug and title). Dry run by default; --apply writes. Sandbox only.

Links are listed in FOOTER_LINKS. A link given as {"media_slug": ...} points at a file in the
sandbox media library (resolved to its relative /wp-content/uploads/ path, so no domain is
baked into the menu). Cookies is deliberately absent: its page does not exist yet.
"""
import argparse
import sys
import urllib.parse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import scaffold_site as ss  # noqa: E402

MENU_NAME = "Footer"
MENU_SLUG = "footer"
LOCATION = "footer"
FOOTER_LINKS = [
    {"title": "Privacy Notice", "media_slug": "privacy-notice-church-members-and-attendees-2"},
]


def resolve_url(wp, link):
    items = wp.call("GET", "media", {"slug": link["media_slug"], "_fields": "id,source_url"})
    if not items:
        sys.exit(f"no media item with slug {link['media_slug']!r} on the sandbox (upload it first)")
    return urllib.parse.urlsplit(items[0]["source_url"]).path


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()
    wp = ss.WP(ss.load_env())
    menus = wp.call("GET", "menus", {"slug": MENU_SLUG, "context": "edit", "_fields": "id,name,slug,locations"})
    menu = menus[0] if menus else None
    print(f"{'APPLY' if args.apply else 'DRY RUN'}: menu {MENU_NAME!r}: {'exists (id ' + str(menu['id']) + ')' if menu else 'to create'}")
    wanted = [(link["title"], resolve_url(wp, link)) for link in FOOTER_LINKS]
    existing = {}
    if menu:
        for it in wp.call("GET", "menu-items", {"menus": menu["id"], "per_page": 100, "context": "edit", "_fields": "id,title,url,menu_order"}):
            existing[it["title"]["raw"]] = it
    for title, url in wanted:
        cur = existing.get(title)
        state = "create" if not cur else ("unchanged" if cur["url"].endswith(url) else "update url")
        print(f"   {title}: {url}  [{state}]")
    if menu and LOCATION not in menu.get("locations", []):
        print(f"   assign menu to location {LOCATION!r}")
    if not args.apply:
        print("dry run: nothing written (use --apply)")
        return
    if not menu:
        menu = wp.call("POST", "menus", body={"name": MENU_NAME, "slug": MENU_SLUG, "locations": [LOCATION]}, params={"_fields": "id,locations"})
    elif LOCATION not in menu.get("locations", []):
        wp.call("POST", f"menus/{menu['id']}", body={"locations": [LOCATION]}, params={"_fields": "id"})
    for order, (title, url) in enumerate(wanted, start=1):
        body = {"title": title, "url": url, "type": "custom", "object": "custom", "status": "publish", "menus": menu["id"], "menu_order": order}
        cur = existing.get(title)
        if cur and cur["url"].endswith(url):
            continue
        wp.call("POST", f"menu-items/{cur['id']}" if cur else "menu-items", body=body, params={"_fields": "id"})
    print("done.")


if __name__ == "__main__":
    main()
