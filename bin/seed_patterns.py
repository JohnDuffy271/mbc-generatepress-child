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

# slug, page id, pattern file (patterns/<name>.php), template file.
# Pages that already have their own (legacy) content are left completely
# alone: neither content nor template is touched, until that page is
# converted (see "Elementor exit" in CLAUDE.md).
SEEDS = [
    ("home", 182, "front-page", "page-templates/front-page.php"),
    # What's On activity pages (Events is a Listing page, handled later)
    ("sunday-mornings", 44, "activity-details", "page-templates/activity.php"),
    ("beacon-cafe", 251, "activity-details", "page-templates/activity.php"),
    ("rock-solid", 60, "activity-details", "page-templates/activity.php"),
    ("stepping-stones", 48, "activity-details", "page-templates/activity.php"),
    ("lunch-club", 56, "activity-details", "page-templates/activity.php"),
    ("craft-group", 565, "activity-details", "page-templates/activity.php"),
    ("house-groups", 46, "activity-details", "page-templates/activity.php"),
    # Info pages (About Us and Resources children)
    ("who-we-are", 343, "info-page", "page-templates/info.php"),
    ("what-we-believe", 345, "info-page", "page-templates/info.php"),
    ("meet-the-team", 42, "info-page", "page-templates/info.php"),
    ("faqs", 626, "info-page", "page-templates/info.php"),
    ("bu-and-yba", 635, "info-page", "page-templates/info.php"),
    ("services-on-youtube", 350, "info-page", "page-templates/info.php"),
    ("room-hire", 391, "info-page", "page-templates/info.php"),
    ("policies-and-documents", 276, "info-page", "page-templates/info.php"),
    ("giving", 637, "info-page", "page-templates/info.php"),
    # Mission partner pages
    ("bms", 467, "mission-partner", "page-templates/mission-partner.php"),
    ("leeds-furniture-mission", 452, "mission-partner", "page-templates/mission-partner.php"),
    ("mission-aviation-fellowship", 455, "mission-partner", "page-templates/mission-partner.php"),
    ("romania", 525, "mission-partner", "page-templates/mission-partner.php"),
    ("mission-group", 631, "mission-partner", "page-templates/mission-partner.php"),
    ("moortown-emergency-relief-fund", 633, "mission-partner", "page-templates/mission-partner.php"),
    ("pathways", 634, "mission-partner", "page-templates/mission-partner.php"),
    # Listing pages
    ("news", 624, "news-listing", "page-templates/listing.php"),
    ("events", 628, "events-placeholder", "page-templates/listing.php"),
    ("contact-us", 211, "contact-placeholder", "page-templates/listing.php"),
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
    ap.add_argument("--force", metavar="SLUG", action="append", default=[],
                    help="overwrite this page's content with its pattern (explicit; use only on pages you know are unedited)")
    args = ap.parse_args()
    wp = ss.WP(ss.load_env())
    print(f"{'APPLY' if args.apply else 'DRY RUN'} against {wp.base}")
    changes = 0
    for slug, pid, pattern, template in SEEDS:
        page = wp.call("GET", f"pages/{pid}", {"context": "edit", "_fields": "id,slug,template,content"})
        if page["slug"] != slug:
            sys.exit(f"page {pid} is {page['slug']!r}, expected {slug!r}")
        markup = pattern_markup(pattern)
        current = page["content"]["raw"].strip()
        patch = {}
        if current and current != markup and slug not in args.force:
            print(f"  {slug} (page {pid}): has its own content ({len(current)} bytes), left alone")
            continue
        if not (THEME / template).is_file():
            print(f"  {slug}: template {template} not in the theme yet, left alone")
        elif page["template"] != template:
            patch["template"] = template
        if current != markup:
            patch["content"] = markup
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
