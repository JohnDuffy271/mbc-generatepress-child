#!/usr/bin/python3
"""Seed pages from block patterns and assign page templates (sandbox only).

For each entry in SEEDS: the page's template is set to the template file (if
it exists in the theme) and, if the page's content is EMPTY, the pattern's
block markup is saved into it so it is visible and editable in the block
editor. A page that already has content is never overwritten.

Pattern files used for seeding must contain no PHP after the header comment,
so the markup is exactly what ends up in the page (and stays diffable).

Dry run by default; --apply to write. Idempotent. Reuses the sandbox client
from scaffold_site.py (keep-alive, short connect timeout with retries).
"""
import argparse
import re
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import scaffold_site as ss  # noqa: E402

THEME = HERE.parent / "generatepress-child"

# slug, page id, pattern file (patterns/<name>.php), template file
SEEDS = [
    ("home", 182, "front-page", "page-templates/front-page.php"),
]


def pattern_markup(name):
    text = (THEME / "patterns" / f"{name}.php").read_text()
    body = text.split("?>", 1)[1].strip()
    if "<?php" in body or "<?=" in body:
        sys.exit(f"patterns/{name}.php has PHP after its header; it cannot be used for seeding")
    return body


def main():
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--apply", action="store_true", help="write changes (default: dry run)")
    args = ap.parse_args()
    wp = ss.WP(ss.load_env())
    print(f"{'APPLY' if args.apply else 'DRY RUN'} against {wp.base}")
    changes = 0
    for slug, pid, pattern, template in SEEDS:
        page = wp.call("GET", f"pages/{pid}", {"context": "edit", "_fields": "id,slug,template,content"})
        if page["slug"] != slug:
            sys.exit(f"page {pid} is {page['slug']!r}, expected {slug!r}")
        patch = {}
        if (THEME / template).is_file() and page["template"] != template:
            patch["template"] = template
        elif not (THEME / template).is_file():
            print(f"  {slug}: template {template} not in the theme yet, left alone")
        markup = pattern_markup(pattern)
        if not page["content"]["raw"].strip():
            patch["content"] = markup
        elif page["content"]["raw"].strip() != markup:
            print(f"  {slug}: has its own content ({len(page['content']['raw'])} bytes), left alone")
        if not patch:
            print(f"  {slug}: nothing to do")
            continue
        changes += 1
        print(f"  {slug} (page {pid}): " + ", ".join(
            f"set {k}" + (f" -> {v}" if k == "template" else f" from pattern {pattern} ({len(v)} bytes)")
            for k, v in patch.items()))
        if args.apply:
            wp.call("POST", f"pages/{pid}", body=patch, params={"_fields": "id"})
    print(f"\n{changes} change(s) {'made' if args.apply else 'planned'}.")


if __name__ == "__main__":
    main()
