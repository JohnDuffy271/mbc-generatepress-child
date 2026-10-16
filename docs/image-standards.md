# Image standards

How photos are prepared for the MBC site. Written 16 Oct 2026 from measurements of the rendered
templates. The look and feel will change after the branding exercise, so the rule is the *method*
(measure the slot, make the preset 2x that), not the numbers: re-measure after any layout change.

## Presets

Every preset is **2x the largest rendered CSS size of the slot it serves** (the "2x rule"), so
images stay sharp on high-density phones and laptops.

| Preset | Pixels | Shape | Cap | Serves | Slot (CSS px, 1280 / 360 wide) |
|---|---|---|---|---|---|
| `banner` | 2240 x 896 | 5:2 | 350 kB | Info-page full-width image | 1120 x 448 / 328 x 246 |
| `post` | 1500 x 1000 | 3:2 | 250 kB | Post featured image and images inside post text | up to 704 wide / 328 wide |
| `band` | 1200 x 900 | 4:3 | 200 kB | Activity, Mission-partner and front-page bands (Media & Text) | 560 x 420 (544 x 408 on the front page) / 328 x 246 |
| `card` | 900 x 600 | 3:2 | 120 kB | News and listing cards | 355 x 237 / 326 x 217 |
| `square` | 700 x 700 | 1:1 | 120 kB | Meet the team photos | 336 x 350 and 146 x 146 / 156 x 156 |
| `newsletter-header` | 600 wide, height follows the crop | free | 100 kB | Email header (and its web version) | 600 x 338 |

Notes:

- **Newsletter header is 1x by design.** The email is 600 px wide and the header is shown at 600.
  A future redesign should supply 1200 x 676 shown at 600 (2x); the check then needs no change
  except raising this slot's minimum from 1x to 2x in `SLOT_MAP`.
- **Newsletter list thumbnails** (web version and email) come from WordPress's `medium_large`
  size (768 px wide), shown in a fixed 3:2 box (180 px wide on desktop, full column on a phone)
  and 180 px wide in the email. They are made from the post's featured image, so a `post` or
  `card` image is more than enough.
- **Slideshow photos** ("More at Moortown", `docs/slideshow-guide.md`) are `band` images: they sit in a
  4:3 frame (560 x 420 on desktop, full column on a phone) and every slide is cropped to it with
  `object-fit: cover`, so a photo that is not 4:3 is cropped from the centre. Prepare each one with
  `image_prep.py` so the crop is chosen, not left to chance. `measure_slots.py` checks them against `band`.
- **Logos** (Mission-partner pages) are shown whole, never cropped, and are not covered by a
  preset: supply the partner's own logo at 2x its displayed size.
- **Not covered:** the front-page hero is text only today; there is no hero or teaser preset (removed
  16 Oct 2026). Add one only when a template has a real slot for it.

## Where the numbers come from

`~/src/mbc-tests/bin/measure_slots.py` (outside this repo, sandbox only, read-only) loads one
page per template (front page, Info, Activity, Mission partner, Listing, a post, a newsletter
issue, the News listing and archive, Meet the team and others) at 360 and 1280 px wide in
**every look**, records each body image's rendered box, and maps each slot to its preset
(`SLOT_MAP` in the script). It **fails** if any preset gives a slot less than 2x (worst case over
both widths, all looks and the slot's `object-fit`), and warns about a slot that is not mapped to
a preset or a preset that serves no slot. Run it after any template or CSS change that touches
images; it is part of the check suite.

## Quality, size cap and metadata

- **JPEG, quality 80.** The only adjustment is *downwards*: if the file is over its preset's cap,
  quality is lowered a step at a time, **never below 70**. Quality is never raised to use up the
  cap. A file still over the cap at 70 is reported "OVER THE CAP": use a simpler crop or find a
  different photo.
- **sRGB**, 8-bit, progressive, 4:2:0.
- **Never upscale.** If the crop is smaller than the preset the image stays at its own size and the
  tool says so: ask for a larger original.
- **All metadata stripped** (EXIF including GPS and camera, ICC profile, XMP, comments) and the
  result is checked for it before saving.
- **File name** `slug-YYYYMMDD.jpg` (lower case, hyphens).
- **Crop:** faces and heads, and any text in the picture, kept clear of the edges. Photos with
  text in them are avoided where possible (the text becomes unreadable when the slot crops on a
  phone).
- **Alt text** on every image: say what the picture shows, in one sentence, British English.

## Consent check (before upload)

Photo consent is managed by **John S**. Before any photo that shows identifiable people is uploaded,
check with John S that consent covers website use, even to the sandbox. The tool never decides this.
Flag new photos of identifiable people to John; do not remove existing ones without being told.

## Workflow: `~/src/mbc-tests/bin/image_prep.py`

Run it with the test venv: `~/.venvs/mbc-test/bin/python ~/src/mbc-tests/bin/image_prep.py ...`.
Everything lives in `~/src/mbc-images/` (outside both repos): `inbox/` (originals), `preview/`,
`output/` (finished files) and `logs/image_prep.log` (one JSON line per step).

1. **Inbox.** Put the original in `~/src/mbc-images/inbox/`. The tool refuses files from anywhere else.
2. **Consent.** Confirm consent with John S if people can be identified.
3. **Grid.** `image_prep.py grid FILE` writes `preview/<name>-grid.jpg`: the photo with a labelled
   10% grid in source pixels, to choose the crop from.
4. **Preview.** `image_prep.py FILE PRESET --crop x,y,w,h` (pixels of the upright source, or four
   fractions). It crops, resizes, picks the quality and writes
   `preview/<name>-<preset>-preview.jpg`: the original with the crop box drawn, beside the result
   at its real size, with the final file name, quality and kB. **Nothing is saved or uploaded.**
   A crop that is not the preset's shape is refused with the height that fits.
5. **OK.** Look at the preview and approve (or change the crop and repeat).
6. **Apply.** The same command plus `--alt "..." --apply` (and `--slug NAME` to name the file,
   `--attach ID` to set it as the featured image of that page or post). It saves to `output/`,
   uploads to the **sandbox** media library (REST; never live), sets the alt text, and logs it.
   Re-running does not upload twice (found by file name).

`image_prep.py presets` lists the presets and caps. Keep the preset table in the script, in
`measure_slots.py` and in this file in step.

## Sizes attributes and the 600px size

- **`sizes` attributes** (`inc/image-sizes.php`): WordPress writes `sizes="(max-width: Wpx) 100vw, Wpx"`, which tells the
  browser an image fills the screen, so a card in a 3-column grid fetched a 1536px file for a 355px box. The theme sets
  accurate values for the four slots WordPress cannot work out (News cards, slideshow slides, team grid and minister
  photo, newsletter list thumbnails), from the widths measured at 360, 600, 768, 1024 and 1280px (table in the file).
  **When a layout changes, re-measure and update that table.** Other images keep WordPress's default.
- **`mbc-600`**: an extra, uncropped 600px-wide size (`add_image_size`), so a ~330px slot is not served the 768px file
  (WordPress's own steps jump from 300 to 768). It applies to **new uploads only**; existing images were not regenerated
  (decision 16 Oct 2026), so they keep the 300/768 gap until they are re-uploaded.
- **Accepted findings:** `~/src/mbc-tests/images_weight_accepted.json` lists the findings that are deliberately left
  (file, reasons, why). They report as INFO; a listed finding that has gone FAILs as stale, so remove entries as the
  images are replaced or the demo content is deleted.

## Text in images

Several legacy graphics carry their words as pixels (Room Hire, Sunday Mornings, the Mission partner banners). Text in an
image cannot be edited, searched, translated or resized for readers, and is unreadable when a slot crops it on a phone.
**When replacement photos arrive, put the words back as real page text over (or beside) the photo.** Until then such
graphics are served as lossy WebP (`bin/convert_graphic.py`, same dimensions, 20-26x smaller than the PNG) and keep their
alt text.

## Migration requirement (content from the live site)

The importer that brings real posts and pages over **must re-link every inline image to a media-library attachment**:
import or match the attachment, then write the image with the `wp-image-<ID>` class (and the block's `id`), so WordPress
builds `srcset` and `sizes` itself. Imported markup with a bare `<img src>` gets neither, which is why the demo post
"Minister's Appreciation Day" is 1.27 MB. Also: **never reference `-scaled` originals** (2560px), and prepare
migrated images at the `post` preset (1500 x 1000, 250 kB cap) with `image_prep.py`, or at least use the generated sizes.
`images_weight_check.py` catches a migrated post that breaks this (`NO_SRCSET`, `HEAVY`).

## Web exposure

`docs/` is deployed to the server with the rest of the repo and is blocked by `docs/.htaccess`
(`Require all denied`); `exposure_check.py` confirms it returns 403.
