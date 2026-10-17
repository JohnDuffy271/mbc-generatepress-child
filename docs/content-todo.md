# Content to-do (from `bin/scan_placeholders.py --drafts`, 17 Oct 2026)

The scan found 16 pages and posts with placeholder markers (31 hits); after the Furniture Mission text went in on 17 Oct it finds 15 (30 hits). Cutover
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
| Pathways (634) | Summary, how to support (Pray/Give/Get involved) and a logo or image; the website button is done (17 Oct 2026, https://pathwaysleeds.org/) |
| Leeds Furniture Mission (452) | Nothing now (support text published 17 Oct 2026); waits for the organisation's new name, then update the name, title, menu label and slug with a redirect, and confirm an email address before adding one |
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
7. Furniture Mission name: settled for now (the Leeds & Moortown Furniture Store is renaming itself; wait for the announcement).
8. Whether Mission (374) and What's On (327) stay as reachable content pages even though the menu treats them as toggles.

## John (decisions, review and things only you can do)
- Review block drafts **Mission (1093)** and **What's On (1094)** in the block editor; the originals stay until you approve.
- Decide what to do with the stray drafts: post 575 (a "Lorem ipsum" test post) and post 793 (an unfinished news post with the template prompts). Ask before deleting.
- Events (628): the page still says "Our events will be listed here"; the
  listing needs the MBC Events Google calendar (public feed or a calendar ID).
  Claude can build it once you supply that.
- Cookies page and footer link (page 3 `privacy-policy` is a draft).
- Raise the Privacy Notice wording about Mailchimp and the names now collected with the church's data protection lead.
- Contact form: parked (see `docs/contact-form-plan.md`); nothing to do now.

## Not placeholders, but still to resolve
- Run the Mailchimp test (your address only) before the newsletter cutover.
- Photo consent (John S) for any new photos of identifiable people.


## Section hubs (17 Oct 2026)

### Introductions to approve (John S)
Two-sentence introductions, written by Claude from what the pages contain, are on the drafts and
marked "Needs confirming: introduction (John S)". Approve or reword, then remove the marker.
| Draft | Introduction |
|---|---|
| About Us (draft 1168, for page 341) | "Moortown Baptist Church is a Baptist church in Moortown, Leeds. Here you can find out who we are, what we believe, who is on our team and the answers to some common questions." |
| Resources (draft 1169, for page 623) | "Useful links and information for church members and visitors. Here you will find the Baptist Union and Yorkshire Baptist Association, our services on YouTube, room hire, our policies and documents, and ways to give." |

What's On (draft 1094) and Mission (published) keep their existing text as the lead.

### One-line descriptions for the section cards (John S)
Each section card shows the page's **excerpt** (page editor > Excerpt box, one sentence, about 20
words). None of the pages below has one yet, so the cards show the first 20 words of the page,
which is often a heading or a label. Services on YouTube already has one (set in the menu item).
| Section | Pages without an excerpt (ID) |
|---|---|
| About Us | Who we are (343), What we believe (345), Meet the team (42), FAQs (626) |
| What's On | Sunday Mornings (44), Events (628), Beacon Café (251), Rock Solid (60), Stepping Stones (48), Lunch Club (56), Craft Group (565), House Groups (46) |
| Mission | Mission Group (631), BMS (467), Leeds Furniture Mission (452), Mission Aviation Fellowship (455), Romania (525), Moortown Emergency Relief Fund (633), Pathways (634) |
| Resources | BU & YBA (635), Room Hire (391), Policies & Documents (276), Giving (637) |

Cards also show each page's featured image; where there is none they show a plain grey block. Pages
with a featured image today: Craft Group, Romania, BMS, MAF, Leeds Furniture Mission, Room Hire,
What we believe, Who we are, Meet the team, Policies & Documents, Beacon Café, Rock Solid, Lunch
Club, Stepping Stones, Sunday Mornings. Sunday Mornings (8:3) and Beacon Café (about 4:1) have very wide images that will crop heavily in the 3:2 card picture.
