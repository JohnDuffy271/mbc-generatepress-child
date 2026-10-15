# Newsletter category map (live site)

Fetched 15 Oct 2026 from the live site's public REST API (read-only, GET only) for the
newsletter code (`newsletter-code-v1.8.php`). The IDs are the **live** category IDs. The
sandbox and the rebuild use different IDs, so the new newsletter must refer to categories by
**slug**, never by ID.

| Live ID | Name | Slug | Parent | Posts on live | Newsletter role |
|---|---|---|---|---|---|
| 5 | News | `news` | – | 1430 | included |
| 8 | Haddon's Blog | `haddons-blog` | – | 57 | included |
| 9 | Graham's Blog | `grahams-blog` | – | 32 | included |
| 10 | **does not exist on live** | | | | included |
| 36 | **does not exist on live** | | | | included |
| 38 | **does not exist on live** | | | | included |
| 42 | Cas's Blog | `cass-blog` | – | 0 | included |
| 59 | Nathan's Blog | `nathans-blog` | – | 12 | included |
| 19 | **does not exist on live** | | | | excluded |
| 27 | Graham's Message | `grahams-message` | Graham's Blog (9) | 0 | excluded |

> **Names in the rebuild.** The minister's blog and message categories are named after a
> former minister on the live site. The rebuild uses `ministers-blog` and `ministers-message`
> (see "Renames" at the end). The tables below are a **record of the live site as fetched** and
> keep its names and slugs; the findings use the new names.

## Findings

- **IDs 10, 36, 38 (included) and 19 (excluded) do not exist** on the live site: not as
  categories and not as tags. They are probably categories that were deleted, so those entries in
  the v1.8 code do nothing. To confirm with John whether they were meant to be something else.
- **Cas's Blog (42) and Minister's Message (27) have no posts**, so including 42 and excluding 27
  currently changes nothing.
- **Minister's Message (27) is a child of Minister's Blog (9).** The v1.8 code uses `cat` with
  `array(5, 8, 9, 10, 36, 38, 42, 59, -19, -27)`. WordPress includes the children of every positive
  category (`include_children` is true in core), so including 9 would include 27 and the `-27` is
  what keeps it out. It matters in principle, although 27 has no posts today. (Passing an array to
  `cat` is fine: core joins it with commas.)
- **News (5) holds 1,430 posts** and Church at Home (57) holds 69 but is not in the list: the
  newsletter's set of included categories is narrower than the live site's.

## All 18 categories on live (for reference)

| ID | Name | Slug | Posts | Parent |
|---|---|---|---|---|
| 5 | News | `news` | 1430 | – |
| 6 | Members' Notices | `notices` | 0 | – |
| 8 | Haddon's Blog | `haddons-blog` | 57 | – |
| 9 | Graham's Blog | `grahams-blog` | 32 | – |
| 17 | Children | `children` | 21 | – |
| 18 | Romania | `romania` | 9 | 24 |
| 21 | News Archive | `news-archive` | 0 | – |
| 24 | Mission | `mission` | 49 | – |
| 26 | China | `china-mission` | 0 | 24 |
| 27 | Graham's Message | `grahams-message` | 0 | 9 |
| 28 | Choir | `choir` | 1 | – |
| 31 | A Short History of MBC | `a-short-history-of-mbc` | 0 | – |
| 42 | Cas's Blog | `cass-blog` | 0 | – |
| 56 | COVID19 | `covid19` | 1 | 5 |
| 57 | Church at Home | `church-at-home` | 69 | – |
| 58 | Church at Home - past Services | `church-at-home-past-services` | 0 | – |
| 59 | Nathan's Blog | `nathans-blog` | 12 | – |
| 72 | Jane's Blog | `janes-blog` | 2 | – |

On the sandbox only three categories exist today: News, Mission and Uncategorized. The live
categories the newsletter needs (the blogs) would have to be created, with these slugs, before
posts can be imported or written into them.

## Renames in the rebuild (old slug -> new slug)

| Live ID | Live name | Old slug (live) | New name | New slug |
|---|---|---|---|---|
| 9 | Graham's Blog | `grahams-blog` | Minister's Blog | `ministers-blog` |
| 27 | Graham's Message | `grahams-message` | Minister's Message | `ministers-message` |

When the live posts are migrated: create or rename the categories with the new names and slugs
(Minister's Message stays a child of Minister's Blog) and add two 301 redirects, because a
child category's URL includes its parent:

| Old path | New path |
|---|---|
| `/category/grahams-blog/` | `/category/ministers-blog/` |
| `/category/grahams-blog/grahams-message/` | `/category/ministers-blog/ministers-message/` |

The newsletter code refers to categories by slug only and excludes `ministers-message` by default.
