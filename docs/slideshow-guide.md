# Slideshow: changing the photos in "More at Moortown"

For anyone editing the Home page in the block editor. The band called **More at Moortown** shows its
photos as a slideshow: one photo at a time in a 4:3 frame, changing every 5 seconds.

## Change the photos

1. **Pages → Home → Edit.** Find the "More at Moortown" band and click the pictures (the Gallery block).
2. **Add** photos with the Gallery's "Add" button (choose from the Media Library or upload).
   **Remove** a photo by clicking it and pressing Remove (or Delete). **Reorder** by dragging in
   List View, or with the arrows in the block toolbar.
3. **Update** the page.

## How many, and which first

- **3 to 5 photos.** Fewer feels static; more than 5 and most visitors never see the last ones.
- **Put the strongest photo first.** It is what everyone sees before the slideshow moves, and what
  people who prefer reduced motion see first. If a visitor's browser cannot run the script, the
  photos show as an ordinary gallery instead.
- Mix the subjects: the band is about "lots going on through the week", so one photo per activity
  works better than five of the same thing.

## Prepare the photos

- Any photo works. The frame is **4:3** and every photo is cropped to fit it from the centre, so
  heads or text near the edge of a photo can be cut off.
- For the best result prepare each photo as a **`band`** image (1200 x 900, 4:3) with
  `image_prep.py` (see `docs/image-standards.md`). It chooses the crop so faces and text stay clear
  of the edges, shrinks the file, and removes the hidden camera data.
- **Alt text on every photo** (say what the picture shows, in one sentence): click the photo, then
  "Alt text" in the sidebar.
- **Consent:** any photo with identifiable people needs John S's OK first.

## Captions (optional)

Click a photo and type under it. The caption appears as a strip along the bottom of that photo.
Leave it empty for no caption. Keep it to one short line (about 60 characters).

## Change the speed

Select the Gallery, open **Advanced → Additional CSS class(es)** and change the number in
`mbc-slideshow--5s`, for example to `mbc-slideshow--8s` for 8 seconds. Keep the class `mbc-slideshow`
too. The shortest it will go is 2 seconds. (A hand-written `data-interval="8"` on the gallery also
works, but the block editor would flag it as unexpected content, so use the class.)

## What visitors can do

- **Pause or play** (top right), **previous and next** (left and right), or **jump with the dots**.
- **Swipe** left or right on a phone or tablet (up and down still scrolls the page); the arrow keys
  work too.
- It **stops** while the mouse is over it, while the keyboard focus is inside it, while the browser
  tab is in the background and while it is off screen.
- Visitors who have asked their device for **reduced motion** get no automatic changes and no fade;
  they can still use the buttons and dots.

## Please don't

- Remove the class `mbc-slideshow` from the Gallery: it would turn back into a plain gallery.
- Add links to the photos (leave "Link to" as None).
- Add other blocks inside the Gallery.

## Adding a slideshow band elsewhere

Insert the pattern **MBC → Slideshow band** (block inserter → Patterns). This is a deliberate
exception to the "bands are Media & Text blocks" rule, agreed for "More at Moortown" only; ask before
using it on other pages.
