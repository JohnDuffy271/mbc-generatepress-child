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

### One-line descriptions for the section cards (John S to confirm)
Each card shows the page's **excerpt** (page editor > Excerpt box, one sentence, about 20 words). If a page has no
excerpt, the card shows the first clean paragraph of the page, or nothing: never the key-facts block (When, Where...),
a paragraph that opens with a bold label, or any placeholder text ("Add the ...", "Needs confirming", the scan's list).

**Set on 17 Oct 2026 (John's wording; John S to confirm the facts, times in particular):**
| Page (ID) | Excerpt |
|---|---|
| Sunday Mornings (44) | Our main service, every Sunday at 11am, in church and live on YouTube. |
| Events (628) | Special services, one-off events and what’s coming up at MBC. |
| Beacon Café (251) | A free warm space on Monday mornings, with tea, coffee and a food pantry. |
| Rock Solid (60) | Our youth club for teenagers, Sunday evenings from 7 to 8pm. |
| Stepping Stones (48) | A toddler group for under-5s and their carers, Tuesday mornings in term time. |
| Lunch Club (56) | Games, quizzes and a hot lunch for older friends every Wednesday. |
| Craft Group (565) | A relaxed Thursday afternoon craft group and warm space. No experience needed. |
| House Groups (46) | Small groups meeting through the week to read the Bible, pray and share life. |

**Drafted by Claude, NOT set yet (from each page's own text; John S to confirm or reword, then they are set):**
| Section | Page (ID) | Draft excerpt |
|---|---|---|
| About Us | Who we are (343) | A local church of individuals and families who seek to love God and live generously. |
| About Us | What we believe (345) | What we believe, how we make decisions as a Baptist church, and what membership means. |
| About Us | Meet the team (42) | Our minister and the nine deacons who lead the church. |
| About Us | FAQs (626) | Wait for the page text (the page is still the template). |
| Mission | Mission Group (631) | Wait for the page text (still the template). |
| Mission | BMS (467) | Our long-standing support for BMS World Mission, working in over 30 countries. |
| Mission | Leeds Furniture Mission (452) | The furniture reuse charity that began at MBC in 1986, giving furniture to people in need. (Update when the new name is announced.) |
| Mission | Mission Aviation Fellowship (455) | Our support for Mission Aviation Fellowship, the world’s largest humanitarian air operator. |
| Mission | Romania (525) | Our links with partner churches in Romania, including our friends in Cluj. |
| Mission | Moortown Emergency Relief Fund (633) | Wait for the page text (still the template). |
| Mission | Pathways (634) | Wait for the page text (still the template). |
| Resources | BU & YBA (635) | Our links with the Baptist Union and the Yorkshire Baptist Association. (Page text not written yet.) |
| Resources | Room Hire (391) | Rooms of many sizes, from the full sanctuary to a small corner space, to hire for your event. |
| Resources | Policies & Documents (276) | Legal and governance documents for the church, as PDFs you can read or download. |
| Resources | Giving (637) | How to give to the work of the church. (Page text not written yet.) |
Services on YouTube has its description on the menu item ("Watch our Sunday services on YouTube.").
Until these are set, the cards on the published Mission page and the About Us and Resources drafts show the page's
first clean sentence (cut at 20 words) or nothing.

### Photos: consent and replacements (John S)
- **Stepping Stones photo (media 443):** shows an identifiable child. Consent check needed.
- **Rock Solid photo (media 476, `Rock_Solid_Baptism`):** shows identifiable young people, and it looks like a
  baptism, not the youth club. Consent check needed, and **replace it** with a youth club photo.
- Also identifiable people, presumably already covered by John S's consent records, to be confirmed: Lunch Club
  (443 is Stepping Stones; Lunch Club is media 442) and the Who we are / What we believe congregation photos.
- Sunday Mornings now uses the congregation photo from the home page (media 1024, approved for publication), set as
  the page's featured image on 17 Oct 2026; the old text-bearing graphic (media 362) is still in the library.

### Featured images behind the cards (audit, 17 Oct 2026)
| Hub | Page | Image now | Verdict |
|---|---|---|---|
| What's On | Sunday Mornings | congregation photo (1024) | done |
| What's On | Events, House Groups | none | grey block; photo wanted |
| What's On | Beacon Café | two mugs, 4.1:1 panorama (299) | soft and very wide: replace |
| What's On | Rock Solid | baptism photo (476) | wrong subject and consent: replace |
| What's On | Stepping Stones, Lunch Club, Craft Group | photos (443, 442, 566) | fine; consent for 443 and 442 |
| Mission | Mission Group, Moortown Emergency Relief Fund, Pathways | none | grey block; logo or photo wanted |
| Mission | BMS, MAF | illustrations 2.7:1 (480, 487) | crop in the 3:2 card; logos wanted |
| Mission | Leeds Furniture Mission | photo of the van, square (590) | fine; logo wanted |
| Mission | Romania | map of Romania (531) | fine as a stopgap; a photo wanted |
| About Us | Who we are | hands (496), 2.9:1 | crops in the card; a squarer photo would suit |
| About Us | What we believe | congregation standing (495) | fine |
| About Us | Meet the team | `page-banner-leadership` (306), 2.6:1, no alt text | crops; alt text missing |
| About Us | FAQs | none | grey block; fine |
| Resources | BU & YBA, Giving | none | grey block |
| Resources | Room Hire | `Room_Hire.png` graphic with text, 1.6 MB (392) | replace with a photo of the rooms |
| Resources | Policies & Documents | 2017 photo (271), no alt text | fine; alt text missing |
Cards use a 3:2 frame, so anything wider than about 2:1 crops. The old PNG graphics (Room Hire, Sundays) are still
1.6 MB as featured images (the pages' own copies were converted to WebP); they only reach the card as a resized
version.
