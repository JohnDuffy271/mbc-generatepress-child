# Content to-do (from `bin/scan_placeholders.py --drafts`, 17 Oct 2026)

The scan found 16 pages and posts with placeholder markers (31 hits). Cutover
needs `python3 bin/scan_placeholders.py` to exit 0. Items are grouped by who
supplies the missing content, then page by page. "John" means John Duffy.
Page IDs are in brackets. Re-run the scan after each batch of fixes.

## John S (church content and photos)

### Activity pages (What's On)
| Page | Missing |
|---|---|
| House Groups (46) | Everything: when, where, who it's for, contact, description, a photo (the page still carries the template prompts and the placeholder image) |
| Lunch Club (56) | Venue (which room) |
| Stepping Stones (48) | Confirm the rooms (main room, Corner Room, Sports Hall) |
| Rock Solid (60, draft 727, held) | Local copy (what happens, who comes, who to ask) and age range (school years); the draft also has a "Needs local copy" note to remove before it is published |
| What's On (327, draft 1094) | Check the weekly timetable (times, rooms, Country Dancing, Ladies Housegroup) is current |

### Info pages (Resources, About Us)
| Page | Missing |
|---|---|
| FAQs (626) | The questions and answers, an introduction, an image or none, whether to keep the call to action |
| BU & YBA (635) | Introduction and text (Baptist Union and Yorkshire Baptist Association), image, call to action |
| Giving (637) | Introduction and text on how to give, image, call to action |
| Who we are (343) | Approve the rewritten sentence about the minister and the roundabout (no marker, but still awaiting approval) |

### Mission partner pages
| Page | Missing |
|---|---|
| Mission Group (631) | Summary, website, how to support, logo or image |
| Moortown Emergency Relief Fund (633) | Summary, website or details, how to support, logo or image |
| Pathways (634) | Summary, website, how to support, logo or image |
| Leeds Furniture Mission (452) | "How you can support": Pray, Give, Get involved (all "Needs confirming") |
| Romania (525) | The year the links began (the sources disagree: "mid 70s" versus 1991), the support lines, a photo (placeholder now) |
| BMS, MAF, Leeds Furniture Mission | Official logos with permission (illustrations or a photo of the van stand in) |

### Photos (see "Image wishlist" in CLAUDE.md)
- **Front page (182):** the "Get in touch" band image (still the placeholder);
  real photos for the "More at Moortown" slideshow (placeholder library photos
  now).
- **Beacon Café:** people at the café (4:3, no text in the image).
- **Room Hire:** photos of the rooms (the graphic is a stopgap).
- **Team photos** for Meet the team when ready.
- **Sunday School** photos (media 557, 559, 560) if that page is folded into
  Sunday Mornings.

## Andy (design decisions; John to ask)
1. Tagline wording (his wireframe's, used now, or the earlier one).
2. An explicit "Home" menu item as well as the logo?
3. Mobile menu: should X go up one level, and should system Back step up a level?
4. Left-aligned band text on the front page, or right-aligned headings as in the wireframe?
5. About Us (341) and Resources (623): overview content or a redirect to the first child?
6. Welcome (8), Baptism (54), Sunday School (556): drop, fold in or make real pages (proposed redirects are in `docs/url-changes.md`).
7. Furniture Mission name: "Leeds Furniture Mission" or "Leeds and Moortown Furniture Store".
8. Whether Mission (374) and What's On (327) stay as reachable content pages even though the menu treats them as toggles.

## John (decisions, review and things only you can do)
- Review block drafts **Mission (1093)** and **What's On (1094)** in the block editor; the originals stay until you approve.
- Decide what to do with the stray drafts: post 575 (a "Lorem ipsum" test post) and post 793 (an unfinished news post with the template prompts). Ask before deleting.
- Events (628): the page still says "Our events will be listed here"; the
  listing needs the MBC Events Google calendar (public feed or a calendar ID).
  Claude can build it once you supply that.
- Cookies page and footer link (page 3 `privacy-policy` is a draft).
- Raise the Privacy Notice wording about Mailchimp and the names now collected with the church's data protection lead.
- Choose the contact form plugin (plan in the 17 Oct report) so Contact Us can have a form.

## Not placeholders, but still to resolve
- Run the Mailchimp test (your address only) before the newsletter cutover.
- Photo consent (John S) for any new photos of identifiable people.
