# Cutover plan: content decisions

The full cutover checklist is in `CLAUDE.md` ("Cutover checklist"). This file records content decisions that shape it.

## Demo posts (decided 18 Oct 2026)

- The ten demo posts imported on 15 Oct 2026 were copies of real live posts. They have been **replaced by proper imports**
  (`bin/import_live_posts.py`: cleaned content, processed images, author mapping, consent flag). The old copies are in the
  trash, with JSON backups in `~/src/mbc-elementor-backups/demo-posts/`.
- **Rule from now on:** a demo post that duplicates a live post is replaced by an import before cutover. Only demo content with
  **no live equivalent** is deleted at cutover. At the moment there is none: every demo post had a live original.
- Demo media (the 19 listed in `CLAUDE.md`) is deleted once nothing references it; imports never reuse it.
- `inc/demo-import.php`, its `require` in `functions.php` and `bin/import_live_demo_posts.py`'s demo flow can go once the trashed
  demo posts are emptied from the trash. `inc/live-import.php` (meta for the real importer) stays until the migration is done.

## Imported posts (consent review)

Every imported post carries `_mbc_needs_consent_review = 1` and some carry drafted alt text (`_mbc_alt_auto = 1` on the image).
Both are cleared in the consent pass with John S before cutover (counts are in `docs/for-john-s.md`).
