# Calendar views plan: month grid and cards

**Decisions (John, 17 Oct 2026):** cards = 3, and each card links to the Events page at its
day (`#d-YYYY-MM-DD`, relative URL); the month view may go back 1 month; weeks start Monday.
**Cards: built 17 Oct 2026** (see CLAUDE.md, "Events agenda"). **Month view: not built**, the
plan below stands (the (b) section below is kept as written; where it says a card is not a link,
the decision above wins).

Written 17 Oct 2026, for John's OK. Both views read the same normalised data as
the agenda (`mbc_calendar_get()` in `inc/calendar/fetch.php`) and are
server-rendered by the existing `[mbc_calendar]` shortcode through a new
`view` attribute (`list` default, `month`, `cards`). No JavaScript is needed
for either; nothing loads from Google in the browser.

## Shared groundwork (both views)
- **Built 18 Oct 2026 (for the Weekly diary):** `mbc_calendar_get_range( $from, $to, $args )`
  in `inc/calendar/fetch.php` fetches events between two dates (the same request with
  `timeMin`/`timeMax` set, the same fixture and last-good handling, follows `nextPageToken`
  for up to five pages). `$args['key']` sets the cache identity (default: the two dates),
  `$args['refresh']` bypasses the cache. `mbc_calendar_get( $weeks )` is now a wrapper over it.
  Normalised events also carry `id` and `series_id`. The month view needs nothing more here.
- Cache and last-good keys hash the range; the month view passes `key` = `YYYY-MM` so each
  month has its own transient and last-good copy.
- `maxResults` is 250. A busy month with repeating events fits; log a warning if
  the response is truncated (`nextPageToken` present).
- Effort for the groundwork: about half a day, included in each estimate below.

## (a) `view="month"` for the Events page

### Behaviour
- **Container width 768px or more:** a month grid, Monday first. **Below
  768px:** the agenda for that month (the existing list markup), not a squeezed
  grid. The switch is a CSS container query on `.mbc-cal` (`container-type:
  inline-size`), so it follows the content column, not the viewport. Both
  markups are in the HTML only when needed: the server outputs the grid and
  the agenda together, CSS hides one (the agenda is the accessible fallback
  and costs little).
- **Navigation:** Previous month and Next month are plain links
  `?month=YYYY-MM` (server-rendered, work without JavaScript, shareable,
  cacheable). The parameter is validated against `^\d{4}-(0[1-9]|1[0-2])$`,
  then bounded to -1 to +12 months from the current month; anything else
  falls back to the current month (no error page). Links beyond the bounds
  are omitted, not disabled. `rel="prev"`/`rel="next"`. The heading is
  the month name (`<h3>October 2026</h3>`) and is the page's `aria-live`
  region only if we later add JavaScript navigation (not planned).
- **Toggle "List | Month":** two links styled as a segmented control,
  `?view=list` and `?view=month` (default `list`, so today's page does not
  change). The current one has `aria-current="true"`. The choice is carried
  in the URL only (no cookie, no storage). The toggle appears when the shortcode has
  `toggle="yes"` (Events page only).
- **Past days:** shown, dimmed, with no events (the data starts at "now").
  Past months within the -1 bound show only what the API returns from the
  month start (so the range request starts at the first of the month, and
  the "already finished" filter is skipped for month view).
- **Multi-day events:** the event appears on each day it touches, as a bar
  on the first day and a short continuation label ("Camp (continued)") on the
  following days.
- **Many events in a day:** at most three titles are shown in the cell, then
  "+ 2 more" linking to the day's section in the agenda below (anchor
  `#day-YYYY-MM-DD`).

### Accessible markup: a table, not a list
Choice: a real `<table>` for the grid. Reasons: it is a two-dimensional
structure (weeks by weekdays), screen reader users get row and column
navigation, and header cells give each date its weekday. A list of days with labels
is simpler but loses the weekday-by-week structure; it stays as the agenda
below 768px.

```html
<div class="mbc-cal mbc-cal--month" data-source="fresh">
  <nav class="mbc-cal__nav" aria-label="Months">
    <a rel="prev" href="?month=2026-09">September 2026</a>
    <h3>October 2026</h3>
    <a rel="next" href="?month=2026-11">November 2026</a>
  </nav>
  <table class="mbc-cal__grid">
    <caption class="screen-reader-text">Events in October 2026</caption>
    <thead><tr>
      <th scope="col"><abbr title="Monday">Mon</abbr></th> ... Sun
    </tr></thead>
    <tbody><tr>
      <td class="is-today is-past is-other-month">
        <time datetime="2026-10-24">24<span class="screen-reader-text"> October</span></time>
        <ul><li><a href="#day-2026-10-24">10:30am Sunday Club</a></li></ul>
      </td> ...
    </tr></tbody>
  </table>
  <div class="mbc-cal__agenda"> ...the existing agenda for the month... </div>
</div>
```
Cell links jump to the matching day in the agenda below, where the full
details (location, description, More disclosure) already exist. The grid is
a summary; the agenda is the detail. Today is marked with a class and
`aria-current="date"` on its `<time>`. No colour-only cues (today has an
outline plus the word "Today" visually hidden). Focus ring from `--mbc-focus`.

### Files
- `inc/calendar/render.php`: `view` attribute, month parsing and validation
  (`mbc_calendar_month_param()`), grid builder (`mbc_calendar_month_html()`).
- `inc/calendar/fetch.php`: `mbc_calendar_get_range()`.
- `css/calendar.css`: grid, container query, toggle, nav (tokens only).
- `patterns/events-calendar.php`: `view` and `toggle` on the Events shortcode
  (header unchanged, so no version bump; the Events page content is updated
  by script, backing up page 628 first).
- `~/src/mbc-tests/calendar_check.py`: new `month` section.

### Mobile
Below 768px of container width the grid is hidden and the month's agenda is
shown, with the same Previous/Next links and toggle (touch targets 44px).
No horizontal scrolling at 360px (asserted).

### Tests (fixture data, fixed "now")
- Month parsing: valid, malformed, out of range (-2, +13), future-month tampering
  (`?month=2026-13`, `?month=%27`, arrays) all fall back safely, never a 500.
- Grid: 7 columns, Monday first, correct weeks for months that begin on each weekday, a 5-
  and a 6-week month, leap day (Feb 2028), and the BST/GMT change days.
- Events on the right cells; multi-day events on every day; all-day; the "+ N more" overflow;
  past dimming; today marker.
- Links: prev/next present only within the bounds; toggle has `aria-current`.
- Table semantics: caption, `th scope="col"`, no empty header cells.
- Browser (Playwright, all five looks, 360 and 1280px): the grid shows at 1280 and
  the agenda at 360, no overflow, contrast, focus ring, keyboard order, no Google requests,
  no console errors.
- Failure states: nothing cached shows the friendly line, not an empty grid.
- Per-month cache keys: two months do not share a transient (assert by fixture).

### Effort
About 2 days: range fetch and cache keys (half a day), grid and navigation (one day),
CSS and accessibility pass (half a day), tests (about one day more, since the
grid has many date cases). Say 3 days with review and the looks pass.

## (b) `view="cards"` for the front page

### Behaviour
- The next N events (`limit`, default 3) as cards: a **date badge**
  (weekday abbreviation and day number, month abbreviation), the title, the
  time, the location (plain text, linked to Google Maps as the agenda does),
  and no description. Intended with `recurring="no"` so the cards show
  one-off events (fairs, special services) rather than the weekly rhythm. With
  fewer events than N it shows what exists; empty or unavailable: the same
  friendly lines and the See all events link as the agenda.
- A card is not a link (the information is complete in the card, and the
  agenda is one click away through See all events). If a later event needs
  its own page, a card can link to it.
- **Layout:** 1 column below 30rem of container width, 2 from 30rem, 3 from
  48rem, by container query on `.mbc-cal--cards` (the same technique the
  newsletter list uses), so it behaves in a narrow column or a full-width
  band.

### Accessible markup
A list of cards (`<ul>` of `<li>`): it is a sequence of independent items, not
a grid of data, so a list is the right element.
```html
<ul class="mbc-cal__cards">
  <li class="mbc-card mbc-cal__card">
    <time class="mbc-cal__badge" datetime="2026-10-24">
      <span class="mbc-cal__badge-day">Sat</span>
      <span class="mbc-cal__badge-num">24</span>
      <span class="mbc-cal__badge-month">Oct</span>
    </time>
    <h3 class="mbc-cal__title">Autumn Fair</h3>
    <p class="mbc-cal__when">10am – 2pm</p>
    <p class="mbc-cal__where"><a href="...">Main hall</a></p>
  </li>
</ul>
```
The badge text is `aria-hidden`; a visually hidden full date ("Saturday 24
October") precedes the title, so a screen reader hears the date once and in full.

### Files
- `inc/calendar/render.php`: `view="cards"` branch (`mbc_calendar_cards_html()`),
  reusing the existing time and location helpers.
- `css/calendar.css`: card layout and badge, built on `.mbc-card` and
  tokens (surface, border, radius, spacing, type steps; no new tokens unless the badge
  needs one, which would be `--mbc-cal-badge-*` in `tokens.css`).
- `patterns/this-week.php` or a new `patterns/events-cards.php`
  (`mbc/events-cards`; a new pattern header means a `Version` bump).
- `~/src/mbc-tests/calendar_check.py`: new `cards` section.

### Mobile
One column at 360px, badge on the left of the title row (flex), 44px minimum
for the See all events link, no overflow (asserted).

### Tests
- Fixture: N cards from a mix of recurring and one-off events with `recurring="no"`;
  order; badge text; the visually hidden full date; time and all-day wording; location link.
- `limit` and fewer-than-N; empty and unavailable states with the More link.
- Column counts at container widths 320, 480 and 768 (Playwright, by computed grid
  columns); all five looks; contrast of badge text; no overflow at 360px.
- Front page placement (when done): the `front_page_check.py` still passes, no layout
  shift (CLS 0), `calendar.css` loads on the front page only if the shortcode is in
  its content (already so).

### Effort
About 1 to 1.5 days (cards and badge, CSS, container queries, tests). It shares the range
groundwork with the month view only if the card window needs dates; for "next N" the
existing 8-week fetch is enough, so it can be built first, independently.

## Suggested order
1. Cards (1 to 1.5 days), then place them on the front page once John wants the band.
2. Month grid (about 3 days) with the toggle on the Events page.

## Decisions
All four answered 17 Oct 2026 (see the top of this file).
