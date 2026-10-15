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

## Findings

- **IDs 10, 36, 38 (included) and 19 (excluded) do not exist** on the live site: not as
  categories and not as tags. They are probably categories that were deleted, so those entries in
  the v1.8 code do nothing. To confirm with John whether they were meant to be something else.
- **Cas's Blog (42) and Graham's Message (27) have no posts**, so including 42 and excluding 27
  currently changes nothing.
- **Graham's Message (27) is a child of Graham's Blog (9).** If the code includes 9 with `cat`,
  WordPress also includes children, so excluding 27 matters in principle; with `category__in` it
  does not. Check against the code once it is saved here.
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
