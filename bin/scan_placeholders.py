#!/usr/bin/python3
"""Cutover check: find placeholder text left in published pages and posts (sandbox only).

Scans the rendered title, excerpt and content of every published page and post
(add --drafts to include drafts, private and pending items) for markers such as
"Needs confirming", "Needs local copy", the block patterns' prompts ("Add the day
and time...", "Describe what happens..."), "Lorem ipsum", the placeholder image,
and "will appear here". Case-insensitive; reports each hit with a snippet.

Exit status 0 only when there are zero markers: "zero placeholder markers" is on
the CLAUDE.md cutover checklist. Read-only.

  python3 bin/scan_placeholders.py [--drafts] [--quiet]
"""
import argparse
import html
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import scaffold_site as ss  # noqa: E402

MARKERS = [
    # explicit flags
    (r"needs?\s+confirming|needs?\s+confirmation|to be confirmed|\bTBC\b", "needs confirming"),
    (r"needs?\s+local\s+copy", "needs local copy"),
    (r"\[describe\]|\bTODO\b|\bFIXME\b|lorem ipsum", "draft marker"),
    # prompts from the block patterns (patterns/*.php)
    (r"Add the day and time|Add the room or address|Add who is welcome|Add a name and an email", "activity prompt"),
    (r"Describe what happens|Add a short introduction|Add a heading|Add the main text of the page", "info prompt"),
    (r"Add a short summary of this partner|Add how people|Add a sentence or two about how this partnership", "partner prompt"),
    (r"Write a short introduction|Write the main text here|Delete this box if the page", "post/info prompt"),
    (r"will appear here|will be listed here|coming soon", "placeholder text"),
    # the neutral placeholder image
    (r"images/placeholder\.svg", "placeholder image"),
]
COMPILED = [(re.compile(p, re.I), label) for p, label in MARKERS]


def visible_text(fragment):
    fragment = re.sub(r"<(script|style)\b.*?</\1>", " ", fragment or "", flags=re.S | re.I)
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", fragment))).strip()


def scan(item):
    """Return [(label, snippet)] for one REST item."""
    hits, seen_labels = [], set()
    sources = {
        "content": item["content"]["rendered"],
        "title": item["title"]["rendered"],
        "excerpt": item.get("excerpt", {}).get("rendered", ""),
    }
    for where, fragment in sources.items():
        # markers can sit in attributes (the placeholder image src), so test the raw markup too
        text = visible_text(fragment)
        for rx, label in COMPILED:
            if label in seen_labels:  # one line per marker type per item (content comes after title and excerpt)
                continue
            for source_text in (text, fragment or ""):
                m = rx.search(source_text)
                if m:
                    start = max(0, m.start() - 30)
                    snippet = re.sub(r"\s+", " ", source_text[start:m.end() + 40])
                    hits.append((label, f"{where}: ...{snippet}..."))
                    seen_labels.add(label)
                    break
    return hits


def fetch_all(wp, route, statuses):
    out, page = [], 1
    while True:
        batch = wp.call("GET", route, {"per_page": 100, "page": page, "status": statuses, "context": "view" if statuses == "publish" else "edit",
                                       "_fields": "id,link,status,title,excerpt,content"})
        out += batch
        if len(batch) < 100:
            return out
        page += 1


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--drafts", action="store_true", help="also scan draft, private and pending items")
    ap.add_argument("--quiet", action="store_true", help="only print the summary")
    args = ap.parse_args()
    wp = ss.WP(ss.load_env())
    statuses = "publish,draft,private,pending" if args.drafts else "publish"
    total_items, flagged = 0, []
    for route in ("pages", "posts"):
        for item in fetch_all(wp, route, statuses):
            total_items += 1
            hits = scan(item)
            if hits:
                flagged.append((route[:-1], item, hits))
    for kind, item, hits in sorted(flagged, key=lambda f: f[1]["link"]):
        if not args.quiet:
            print(f"{kind} {item['id']} [{item['status']}] {item['link'].replace(wp.base, '') or '/'}")
            seen = set()
            for label, snippet in hits:
                if (label, snippet) not in seen:
                    seen.add((label, snippet))
                    print(f"    - {label}: {snippet[:150]}")
    n_hits = sum(len({h for h in hits}) for _, _, hits in flagged)
    print(f"\nScanned {total_items} {'page(s) and post(s) (including drafts)' if args.drafts else 'published page(s) and post(s)'}: "
          f"{len(flagged)} with placeholder markers ({n_hits} hit(s)).")
    if not flagged:
        print("ZERO placeholder markers.")
    sys.exit(1 if flagged else 0)


if __name__ == "__main__":
    main()
