# CLAUDE.md — Moortown Baptist Church website rebuild

Read this first, every session.

## What this is

A rebuild of the Moortown Baptist Church (MBC) website in WordPress on a
GeneratePress child theme. The church is running a branding exercise
(expected to take a couple of months), so **look and feel will change late**.
Build structure and templates now; keep all styling swappable.

**Goal: no page depends on Elementor.** Elementor and Elementor Pro are
deactivated on the sandbox before cutover (see "Elementor exit" in the
Status list).

- Live site: https://moortownbaptistchurch.org.uk (DO NOT TOUCH)
- Sandbox:   https://new.moortownbaptistchurch.org.uk (all work happens here)
- Tagline:   "Loving God. Living Generously. Following Jesus." (Andy's
  wording, used on the front page for now; awaiting church confirmation.
  The earlier wording was "Loving God, living generously, following
  Christ.")

## Source of truth for structure

The page menu and wireframes come from Andy Berry (deacon). Build to his
design, not the old three-column homepage layout, which is abandoned.
The old layout is preserved at git tag `split-layout-final` (commit 1007816).

### Menu (parents are NOT links — toggles only, for touch devices)

- Logo (left) = Home link
- **About Us** → Who we are · What we believe · Meet the team · FAQs
- **What's On** → Sunday Mornings · Events · Beacon Café · Rock Solid ·
  Stepping Stones · Lunch Club · Craft Group · House Groups
- **Mission** → Mission Group · BMS · Leeds Furniture Mission ·
  Mission Aviation Fellowship · Romania · Moortown Emergency Relief Fund ·
  Pathways
- **Resources** → BU & YBA · Services on YouTube (external link) ·
  Room Hire · Policies & Documents · Giving
- **News** (single link)
- **Contact Us** (single link)

Same header and footer on every page.

### Wireframe layout (homepage)

- Header: logo left, nav right. Burger below 1024px (tablet and mobile);
  inline nav with dropdowns from 1024px. Per Andy's notes (wireframe
  `07-Notes.png`) the menu covers the whole screen on tablet and mobile;
  items with submenus get a chevron; tapping one *replaces* the menu with
  its children (drill-down, not an accordion) and a Back button replaces
  the logo to go up a level.
- Hero: church name, tagline, three buttons, a line of small text.
- Four alternating image/text bands (zigzag): About Us, Sunday Mornings,
  More at Moortown, Get in touch (with social icons). Each band has a button.
  Two columns on desktop and tablet; image stacked above text on mobile.
- Footer: logo + name, "Stay Connected" newsletter signup, small links,
  copyright.
- Andy deliberately left out colours and fonts. Do not invent a brand.

## Architecture rules

1. **No Elementor for new pages.** Elementor and Elementor Pro remain active
   for legacy content only. New pages use core Gutenberg blocks (and
   GeneratePress features). Block markup must stay human-readable and
   diffable.
2. **All design values live in `generatepress-child/css/tokens.css`** as CSS
   custom properties on `:root` (colours, fonts, type scale, spacing,
   radii, breakpoints-as-comments). Components reference variables only —
   no hard-coded colours, font names or spacing anywhere else. Rebranding
   must be a tokens-only change.
3. Prefer PHP templates and template parts in the child theme over
   database-stored layouts, so everything is in git.
4. Zigzag bands = core Media & Text block (or a pattern built on it).
   **One deliberate exception (John, 16 Oct 2026):** "More at Moortown" on the
   front page is a slideshow band (a Group beside a Gallery, see "Slideshow"
   below), because a Media & Text block's picture slot holds one image only.
5. Four page templates cover the site:
   - **Info page** (About Us, Resources): prose, optional image, optional CTA.
   - **Activity page** (What's On ×8): when, where, who it's for, contact,
     image, description. Identical structure — use a block pattern or
     structured fields.
   - **Mission partner page** (×7): logo, summary, external link,
     how to support.
   - **Listing pages**: News (Query Loop), Events (fed by the "MBC Events"
     Google calendar), Contact.
6. Forms: Elementor Pro forms are not used for new pages. Contact form
   plugin is TBD — ask before installing anything. Newsletter signup uses
   MC4WP (Mailchimp for WordPress), already active.

## Repo layout

```
mbc-generatepress-child/        <- repo root (this file lives here)
├── .htaccess                   <- denies web access to *.md, *.zip, *.sql etc.
├── CLAUDE.md
├── docs/                       <- project docs and wireframes
│   └── .htaccess               <- Require all denied
└── generatepress-child/        <- the actual theme folder
```

The theme folder is `generatepress-child`, not `mbc-generatepress-child`.

## Deployment

`git push` to `main` → GitHub webhook → Plesk Git pulls and auto-deploys
the **whole repo root** to `wp-content/themes/` on the sandbox (~30 s).
Verified end to end on 14 Oct 2026.

Consequences of deploying the repo root into `themes/`:

- Everything in the repo becomes reachable under
  `https://new.moortownbaptistchurch.org.uk/wp-content/themes/` unless
  an `.htaccess` blocks it. The repo-root `.htaccess` and `docs/.htaccess`
  exist for this reason — never remove or weaken them.
- Never commit archives, dumps, logs or secrets to the repo. (A stray
  `mbc_git.zip` was publicly downloadable from `themes/` and was deleted
  on 14 Oct 2026.)
- Any new top-level folder that is not a theme needs its own
  `.htaccess` with `Require all denied`.
- The root `.htaccess` denies every dotfile and `.md/.py/.json/.sh/...` files
  (`.gitignore` was readable until 15 Oct 2026). `.git/` is not deployed.
  `~/src/mbc-tests/exposure_check.py` GETs every tracked non-theme path
  plus extras and fails on anything but 403/404; run it after any deploy
  change.
- After adding anything sensitive-adjacent, verify it returns 403:
  ```bash
  /usr/bin/curl -s -o /dev/null -w '%{http_code}\n' "https://new.moortownbaptistchurch.org.uk/wp-content/themes/<path>"
  ```

Deploy semantics (verified 14 Oct 2026): Plesk Git **deletes tracked files
that are removed from git**, leaving no orphans. It never touches
**untracked** files on the server, so anything uploaded manually (FTP,
file manager) must be removed manually. Check with a read-only lftp
listing after removals.

Other rules:

- Commit small, with meaningful messages (not "Aligning Headers" ×4).
- Never commit credentials, `.env` files or anything from `~/.config/`.
- There is no staging between push and sandbox; the sandbox IS staging.
- If a push does not appear on the sandbox, John can click "Deploy now"
  in Plesk → Websites & Domains → new.moortownbaptistchurch.org.uk → Git.

## Server layout and file access

Sandbox and live share ONE Plesk subscription on Rockhost
(`server14.rockhost14.co.uk`). The subscription root holds the live
docroot (`httpdocs/` — NEVER TOUCH) and the sandbox docroot. Claude Code
only has the sandbox:

- **FTP account `mbcsandbox`** is chrooted to the sandbox docroot, so the
  live site is not reachable with it. Paths start at the docroot:

```
/                          <- sandbox docroot (FTP root for mbcsandbox)
└── wp-content/
    └── themes/            <- Plesk Git deploy target
        └── generatepress-child/
```

- **Guard hook:** a PreToolUse hook (`~/.claude/hooks/protect-live.py`)
  blocks any shell command, file path or URL that references `httpdocs`
  or the bare live domain. It checks commands and paths, not file content,
  so documentation may name them. Shell commands must never reference
  them. If the hook blocks something, do not work around it; tell John.

- **SSH:** blocked. Port 22 times out from John's IP; ticket raised with
  Rockhost 14 Oct 2026, no reply yet. Even when fixed, the shell is
  chrooted. Do not plan work that depends on SSH or remote WP-CLI.
- **FTPS (lftp):** works on port 21 as `mbcsandbox`. Credentials in
  `~/.netrc` (never print them). Use for read-only inspection; deploy via
  git, not FTP.
  ```bash
  /usr/bin/lftp -e 'set ftp:ssl-force true; set ftp:ssl-protect-data true; cls -l /wp-content/themes/; bye' server14.rockhost14.co.uk
  ```
  Any FTP write or delete: ask John first.
- **Backups:** Plesk subscription backup taken 14 Oct 2026 (covers live and
  sandbox). Its `.pki/nssdb` permission warning is harmless.

## Content and site config: REST API

Pages, menus and settings are managed via the WordPress REST API.

- Credentials: `~/.config/mbc/wp.env` (WP_URL, WP_USER=claude-code,
  WP_APP_PASSWORD). Never print, echo or log the password.
- Load in a subshell:
  ```bash
  ( set -a; . ~/.config/mbc/wp.env; set +a; /usr/bin/curl -s -u "$WP_USER:$WP_APP_PASSWORD" "$WP_URL/wp-json/wp/v2/pages?per_page=100&_fields=id,slug,title,parent" )
  ```
- User `claude-code` is an Administrator (needed for menus).
- Useful endpoints: `/wp/v2/pages`, `/wp/v2/media`, `/wp/v2/menus`,
  `/wp/v2/menu-items`, `/wp/v2/templates`.
- Note: WP 7.1 returns `rest_not_logged_in` for a wrong password, not
  `incorrect_password`. Check the username/password pairing first.
- Scripts that create pages or menus must be idempotent (look up by slug,
  update if present, create if not).

## Environment

- WSL2 Ubuntu on Windows 10 (PLUTO). Windows PATH is NOT imported.
- Work inside `~/src/`, never under `/mnt/c` or `/mnt/d`.
- Repo: `~/src/mbc-generatepress-child` (GitHub
  `JohnDuffy271/mbc-generatepress-child`, SSH auth working).
- Tools present: git, rsync, ssh, lftp, curl, php, composer. `sudo` needs John's WSL
  password, so ask John to run any `apt install` himself.
- Server: Rockhost shared Plesk, PHP 8.2, Apache 2.4 + PHP-FPM, WP 7.1.
- PHP: local php-cli is 8.5.4, server is 8.2. Write 8.2-compatible code
  only (no typed class constants, `#[\Override]`, `json_validate()` etc.).
  `php -l` on 8.5 cannot catch this, so **run `bin/lint.sh` before every
  push** (`php -l` + PHPCompatibilityWP with `testVersion 8.2`).
- Lint tooling is installed globally (`~/.config/composer`): phpcs 3.13,
  PHPCompatibility 10.0.0-alpha2 and PHPCompatibilityWP 3.0.0-alpha2 (the
  alphas are needed to detect 8.3+ features). Never add `vendor/` or
  `composer.json` to the repo: the repo root deploys to `themes/`.
- `bin/` deploys too; `bin/lint.sh` is covered by the root `.htaccess`
  `.sh` deny (verify 403 after pushing changes to `bin/`).
- **Check-script logs:** every script in `~/src/mbc-tests/` starts with
  `import runlog; runlog.start()` (`runlog.py`). Full output goes to
  `~/src/mbc-tests/logs/<script>-<YYYYmmdd-HHMMSS>.log` (newest 20 kept per
  script); a one-line summary (passed, failed, network retries, exit code)
  ends each run and, on failure, the log path and the first failing check.
  Network stalls are retried up to 2 times with a logged `WARN retry` line
  (Playwright `goto`, the connect step of `_QuickConnect`, `nl_lib.Client`;
  a non-GET is retried only if it cannot have been sent). New check scripts
  must add the two lines. `excerpt_check.php` is not covered.
- **Intermittent TCP connect stalls** (measured 15 Oct 2026): from John's
  IP, about 1 connection in 10 to the server stalls in the TCP connect for
  20-68 s (the server then answers in ~0.5 s). Not the user agent, no
  throttling headers or challenge pages seen; suspected host-side rate
  limiting. Raised with Rockhost. Mitigation: scripts in `bin/` use an
  8 s connect timeout with quick backoff retries and keep-alive
  connections, plus a 1 s pause between sandbox writes. Any new script that
  talks to the server must do the same (copy `_QuickConnect`); do not rely
  on a plain `urlopen` with a long timeout.

## Coding conventions

- British English in all user-facing text and comments.
- SI units.
- Bash: explicit paths (`/usr/bin/curl`, `/bin/ls`); do not assume PATH.
- Python: f-strings, `pathlib`, explicit error handling. pip outside a venv:
  always `--break-system-packages`.
- When showing John code to paste, give complete files, not diffs.
- Be concise.

## Safety

- Sandbox only. Never call the live site's API or touch its files
  (`/httpdocs/` on the server).
  **One exception, read-only:** `bin/import_live_posts.py` may make
  unauthenticated GET requests to the live site's public REST API
  (`posts`, `categories`, `media`) and to `/wp-content/uploads/` files, to
  import demo posts into the sandbox. It has no code path that can POST,
  PUT, PATCH or DELETE to live, sends no credentials, and `--self-test`
  checks this statically. The exception covers that one script only: any
  other live access (including ad-hoc probes) needs John's explicit say-so
  first. Run it as a script file, never with direct curl against live.
  **Extension (John, 15 Oct 2026):** the Elementor-exit tool
  (`bin/elementor_exit.py assets PAGE_ID`) may also download the images and
  documents referenced by the page being converted, from live
  `/wp-content/uploads/` only, using the same `LiveReader` (GET only, no
  credentials, paced, same-host redirects only, path restricted to
  `/wp-content/uploads/`), and upload them to the sandbox media library
  with `mbc_live_source_url` meta. Nothing else on live; the tool's
  `self-test` checks this statically.
  **Second extension (John, 15 Oct 2026):** for the whole Elementor exit,
  the same tool (`elementor_exit.py live-read SLUG...`) may make read-only
  reads of live **pages** through the public REST API (`pages`), via the
  same `LiveReader` (GET only, no credentials, paced), to compare a
  sandbox page with its live equivalent. It never writes anything and
  reads nothing but `pages` and the uploads above.
- Ask before: installing or removing plugins, changing permalinks,
  deleting pages or media, editing any `.htaccess` (site root or repo)
  or `wp-config.php`, any FTP write or delete.
- `.htaccess` in the sandbox docroot has a deliberate `SetEnvIf
  Authorization` line above `# BEGIN WordPress`. Leave it.
- Photo consent is managed by John S; flag any new photos of identifiable
  people to John, but don't remove them without being told.

## Status (Oct 2026)

- [x] Toolchain: WSL, Claude Code, GitHub SSH, push-to-deploy, REST auth
- [x] Push-to-deploy verified end to end (14 Oct 2026)
- [x] Web exposure audit: repo artefacts and `docs/` return 403;
      `mbc_git.zip` removed (14 Oct 2026)
- [x] Old layout tagged `split-layout-final` (1007816)
- [x] FTPS read access via lftp
- [ ] SSH access — awaiting Rockhost (not blocking)
- [ ] Wireframe detail screenshots from Andy's Canva (7 overview PNGs
      already in `docs/wireframes/`)
- [x] E2E smoke test passed (13 Oct 2026): `css/tokens.css` enqueued,
      `page-templates/e2e-test.php`, test page ID 620 (`mbc-e2e-test`) —
      remove before launch (ask first)
- [x] Old-build clean-out (14 Oct 2026): old templates, includes, assets
      removed; `functions.php` trimmed; three stray server files deleted;
      pages 62, 326, 574 trashed and 8, 54, 556 drafted
      (`bin/cleanup-pages.sh`). Parent theme declares title-tag,
      post-thumbnails and html5, so the child does not.
- [x] YouTube feed retired (15 Oct 2026, John's approval): page 350
      ("Services on YouTube") was backed up to
      `~/src/mbc-elementor-backups/350-services-on-youtube.json` and
      **trashed** (not deleted); the `[mbc_youtube_feed]` shortcode,
      `includes/mbc-youtube-feed.php`, `assets/css/mbc-youtube-feed.css` and
      their `functions.php` lines were removed in one commit. The menu
      keeps its external "Services on YouTube" link (the channel's streams
      page); `bin/scaffold_site.py` now marks it `menu-only` (no page) so a
      re-run cannot recreate page 350, and `bin/seed_patterns.py` no longer
      lists it. Checked after deploy: the pages still return 200, page 350
      and the removed files return 404, no orphan folders on the server, the
      header and footer still link to the channel and the streams page. The
      old unassigned "Menu 1" (id 9) still holds an item for page 350: it
      goes when that menu is deleted.
- [x] Front page (15 Oct 2026): `page-templates/front-page.php` +
      `patterns/front-page.php` (hero, four zigzag Media & Text bands).
      Page 182 uses the template and its content was seeded from the
      pattern (`bin/seed_patterns.py`, idempotent; seeds only *empty*
      pages). Edit the content in the block editor. Playwright
      (`~/src/mbc-tests/front_page_check.py`) passes at 360/768/1280px in
      default and dark (also warm and high-contrast); contrast check passes
      for all four looks. Notes: pattern links are relative
      (`/whats-on/...`) and the placeholder image path is relative, so no
      domain is baked into page content. Tagline: Andy's wording "Loving
      God. Living Generously. Following Jesus." (awaiting church
      confirmation). Band text is left-aligned throughout, a **deliberate
      deviation** from the wireframe (which right-aligns headings beside a
      left-hand image) to confirm with Andy. Band images are neutral
      placeholders, except **About Us** (16 Oct 2026): a photo of the church
      noticeboard (media 1020, `mbc-sign-20261016.jpg`, made with
      `image_prep.py` as a `band` image; page content backed up to
      `~/src/mbc-elementor-backups/182-home-pre-aboutus-image.json`). The
      sign names the minister, so it dates if that changes. **Sunday Mornings**
      (16 Oct 2026): a photo of a service from the balcony sound desk (media
      1024, `sunday-service-20261016.jpg`, `band`, widest 4:3 crop, kept the
      sound desk at John's request; John S approved it for publication;
      content backup `182-home-pre-sunday-image.json`). The other two bands
      still use the placeholder. John approved the front page; the block editor opens
      page 182 cleanly (no invalid blocks).
- [x] Activity template (15 Oct 2026): `page-templates/activity.php`,
      `patterns/activity-details.php` (image, When, Where, Who it's for,
      Contact, "About this activity"), shared wrapper
      `template-parts/page.php` (reused by Info, Mission partner and
      Listing). Of the What's On children only House Groups (46) was empty,
      so only it was seeded (with obvious "Add ..." placeholder text, to be
      filled in); the six others (Sunday Mornings, Beacon Café, Rock Solid,
      Stepping Stones, Lunch Club, Craft Group) carry legacy Elementor
      content and were left completely alone (content *and* template);
      Events (628) is a Listing page. `bin/seed_patterns.py` and
      `bin/scaffold_site.py` both skip legacy pages. Checked with
      `~/src/mbc-tests/activity_check.py` (360/1280px, default and dark,
      and that the legacy pages are unchanged) and the contrast check
      across all four looks.
- [x] `tokens.css` neutral placeholder tokens + `css/base.css` (tokens
      only). Real brand tokens follow the branding exercise.
- [x] `php-cli` + composer + phpcs/PHPCompatibilityWP for 8.2 linting
      (`bin/lint.sh`)
- [ ] Header and footer (built 15 Oct 2026; Playwright suite passes at
      360/768/1024/1280px; John's phone checks 1, 2 and 4 pass, check 3
      (links work, Services on YouTube opens in a new tab) not yet
      confirmed, so leave unticked until it is). The
      suite is `~/src/mbc-tests/header_check.py`, run with
      `~/.venvs/mbc-test/bin/python header_check.py <screenshot-dir>`
      (outside the repo; sandbox only). Drill-down mobile menu, dropdowns,
      MC4WP footer form 206, `footer` menu location. Menu items with class
      `mbc-menu-toggle` (the four parents, URL `#`) render as
      `<button aria-expanded aria-controls>`, never links. See "Header and
      footer architecture" below.
- [ ] Header/footer pending fixes (16 Oct 2026):
      - Keep the brand name on one line at 1024px and above (it wraps at
        1280px).
      - Remove the sidebar site-wide via the `generate_sidebar_layout`
        filter (old pages still show GP's Search/Recent Posts/Recent
        Comments widgets).
      - The `footer` menu location now has the "Footer" menu (id 15, made
        by `bin/setup_footer_menu.py`, idempotent) with one link, Privacy
        Notice, to the sandbox copy of the PDF (media 766). Cookies is
        still open: add it to `FOOTER_LINKS` in that script once the page
        exists (cutover checklist).
- [x] **Elementor assets and a console error on every non-Elementor page**
      (found and fixed 16 Oct 2026, sandbox only). Every page built with our
      templates loaded 8 unused Elementor files (frontend CSS, Roboto and
      Roboto Slab font CSS, three scripts) and Elementor's `frontend.min.js`
      threw `ReferenceError: elementorFrontendConfig is not defined` on each.
      **Cause:** the Elementor Pro Theme Builder footer template #208 had the
      display condition "Entire site" (`include/general`), which makes Elementor
      enqueue its frontend assets everywhere, but `inc/layout.php` removes the
      footer actions, so the template never rendered and Elementor never
      printed its inline `elementorFrontendConfig`.
      **Fix (no theme code):** the condition was removed over Elementor's own
      REST route (`POST /elementor/v1/site-editor/templates-conditions/208`
      with `{"conditions": []}`). Template #208 is untouched otherwise: still
      published, now inactive ("No instances"). Backup (the whole template
      and its condition) in
      `~/src/mbc-elementor-backups/208-elementor-footer-208.json`. **To undo:**
      POST `{"conditions":[{"type":"include","name":"general","sub_name":"","sub_id":""}]}`
      to the same route. No dequeue code was needed (so none was added).
      **Result** (360px, empty cache, `~/src/mbc-tests/bin/measure_weight.py`):
      0 Elementor files and 0 console errors on every non-Elementor page; the
      three Elementor-built pages (60, 374, 327) still load Elementor and have
      clean consoles. Checked by `~/src/mbc-tests/elementor_assets_check.py`
      (every published page, post and issue, archives, search and 404).
      Before/after table: see "Page weight" below. **Roboto** was never loaded
      from Google: Elementor's "load Google fonts locally" copy in
      `uploads/elementor/google-fonts/`.
      **Still true:** #208 and the Theme Builder remain until the Elementor
      exit (step 5 there).
- [x] **Image weight findings (16 Oct 2026): decided and done.** The news post
      "Minister's Appreciation Day" is 1271 kB at 360px because three imported
      inline JPEGs are 1215 kB of it (two `-scaled` 2560px originals, no
      `srcset`: the imported markup lacks the `wp-image-ID` class). Decisions
      (John): (1) accurate `sizes` for News cards, slideshow, team grid and
      minister photo, newsletter thumbnails (`inc/image-sizes.php`, from the
      measured slots); (2) a **migration requirement**, below; (3) the Room Hire
      and Sunday Mornings PNG graphics converted to WebP (1694 and 1675 kB to 86
      and 65 kB, same 2048x768, alt kept; page backups
      `~/src/mbc-elementor-backups/391-room-hire-pre-webp.json` and
      `44-sunday-mornings-pre-webp.json`; the old PNG media 392 and 362 are
      still in the library, delete only on John's say-so); (4) team photos later,
      via `image_prep.py` `square`, when John supplies them; (5) an intermediate
      `mbc-600` size for new uploads (no regeneration; confirmed in the `srcset`
      of a fresh upload); (6) demo content left. Known findings are in
      `~/src/mbc-tests/images_weight_accepted.json` (INFO; stale entries FAIL).
      **Migration requirement:** the importer for real live content must
      re-link every inline image to an attachment (import or match it, write the
      `wp-image-<ID>` class and the block `id`) so WordPress builds `srcset`;
      never reference `-scaled` originals; prepare images at the `post` preset.
      **Text in images** (Room Hire, Sunday Mornings, the partner banners) should
      become real text over photos when replacements arrive. Details:
      `docs/image-standards.md`. Weights, 360px: Room Hire 277 to 74 kB, Sunday
      Mornings 279 to 73 kB; at 1280px the News listing 4327 to 1672 kB (still
      mostly demo PNGs).
- [ ] Open question (mobile menu back-out): John finds it odd. Current
      behaviour: on-screen Back goes up one level; the X closes the whole
      menu; Android system Back is not handled (no history entries), so it
      leaves the page; following a link loads a fresh page with the menu
      closed. Option under consideration: `history.pushState` for "menu
      open" and "submenu open" so system Back steps up one level at a time
      (submenu → top list → closed → leave), with Back/X/Esc calling
      `history.back()` to keep the stack consistent. No change made yet.
- [ ] Open question (Home menu item): the logo is Home per Andy's spec;
      John to check with Andy whether an explicit "Home" item is wanted.
- [ ] Rockhost SSH ticket (raised 14 Oct 2026): no reply yet; not blocking.
- [x] All page templates built (15 Oct 2026): front page, Activity, Info,
      Mission partner, Listing (News Query Loop; Events and Contact
      placeholders) and the single post, each with a block pattern in
      `patterns/` and styles in `css/layout.css` / `css/patterns.css`.
      `bin/seed_patterns.py` seeds only *empty* pages and assigns the
      template; **Elementor-built (legacy) pages are skipped by both it and
      `bin/scaffold_site.py`** (content and template untouched) until the
      Elementor exit. Currently on the new templates: front page (home);
      Activity: House Groups; Info: FAQs, BU & YBA, Giving; Mission
      partner: Mission Group, Moortown Emergency Relief Fund, Pathways;
      Listing: News, Events; single post: the 10 demo posts. Placeholder
      text ("Add the day and time...") on the seeded pages needs real
      content. The News listing is six per page, newest first. Single post:
      the featured image is not shown when the first content image is the
      same picture (`inc/post-helpers.php`, tolerant of WordPress's
      `-scaled`, `-WxH` and `-N` file-name suffixes). Still blank:
      the parent pages About Us (341) and Resources (623), which are menu
      toggles, not links. Checks: `~/src/mbc-tests/` (`header_check.py`,
      `front_page_check.py`, `activity_check.py`, `templates_check.py`,
      `posts_audit.py`, `contrast_check.py` with `CC_PAGES=` for specific
      pages, `page_inventory.py`).
- [x] Posting news (15 Oct 2026): a "News post" block pattern (MBC
      category, posts only: intro paragraph, image, body text) is in
      `patterns/news-post.php`, and the site's default post category is now
      **News** (id 12 on the sandbox; set via REST `default_category`).
      Checks and pages above still apply. **An editor's how-to guide for
      posting news will follow once the single-post template is final**
      (not written yet).
- [ ] Imported demo content must not carry inline colours or fonts: the
      import strips them (they made post text black on dark). Apply the same
      rule when converting legacy pages in the Elementor exit.
- [ ] **Elementor exit** (after the templates exist). Goal: no page
      depends on Elementor; both plugins deactivated before cutover.
      1. For each of the 24 Elementor-built pages, compare the sandbox
         content with the live page and report which is more current.
         Don't guess.
      2. Convert the content into the matching block pattern as a *draft
         revision*. Never destroy the Elementor version until the draft
         is approved.
      3. John or John S reviews each page.
      4. On approval: publish the block version and remove that page's
         `_elementor_edit_mode` / `_elementor_data` meta.
         **Backup rule (every page):** *before* any Elementor meta is
         removed, save the page's `_elementor_data`, `_elementor_edit_mode`,
         `_elementor_page_settings` and `post_content` to
         `~/src/mbc-elementor-backups/<page-id>-<slug>.json` (outside the
         repo), then confirm the file is written and valid JSON (read it
         back). `bin/elementor_exit.py publish` does this first and stops if
         it fails. The draft copy is **trashed, never force-deleted**.
         Tool: `bin/elementor_exit.py backup|draft|publish` (draft and
         publish are dry runs without `--apply`; the draft is a *new* draft
         page "<title> (block draft)" because WordPress cannot hold an
         unpublished version of a live page; the original is untouched).
         **Typo rule (every page):** "verbatim" means don't lose content,
         not preserve errors. Fix typos when converting (the tool's
         `--fix 'OLD=>NEW'` reports each correction). Facts that are not in
         the old page ("Where", "Contact") come from John; never name
         individual group leaders unless John says so (use the church
         office). **Team pages exception (John, 15 Oct 2026):** the
         stale-names rule does not apply to a page whose purpose is to name
         people (Meet the team): keep the names, roles and "Current as of
         ...". Alt text is added to the image and to the media item.
         Email addresses at the church domain (for example the office
         email) now pass the protect-live hook (fixed by John and retested
         15 Oct 2026 with a command containing one); `--facts-file` (JSON)
         remains available for long text.
         **Pilot done:** Craft Group (page 565) was converted on 15 Oct 2026
         via draft page 720 (reviewed and approved by John); backup in
         `~/src/mbc-elementor-backups/565-craft-group.json`; the draft is in
         the trash. Note: the sandbox "modified" dates cannot show which
         version is newer (the scaffold touched every page on 14 Oct 2026);
         compare content, not dates. Live has no standalone Craft Group page
         (it is a paragraph in a combined live page), so for some pages
         "compare with live" will find nothing to compare.
         **Banner decision (John, 15 Oct 2026):** Craft Group now uses its
         crochet photo (the page's featured image, media 566, with alt text)
         as the activity image; the tiger picture (media 568) stays in the
         media library, unused. When converting a page, use its featured
         image as the activity image if the page has no image of its own,
         and describe it in the alt text. Very wide banner-shaped images
         (Sunday Mornings 8:3, Beacon Café about 4:1) crop heavily in the
         4:3 slot: flag them for John.
         **Batch 1 (Activity pages), 15 Oct 2026: PUBLISHED** Sunday
         Mornings (page 44), Beacon Café (251), Stepping Stones (48) and
         Lunch Club (56), one at a time with the backup-first publish step
         (drafts trashed; Lunch Club wording made neutral: "our guests").
         **Rock Solid (60) is deliberately NOT published:** draft 727 and
         the Elementor original stay as they are until John S supplies the
         local copy. Stepping Stones and Lunch Club went live with a
         visible "need(s) confirming" note each (rooms; venue). History:
         drafts 725 Sunday Mornings, 726 Beacon Café, 727 Rock Solid, 728
         Stepping Stones, 729 Lunch Club; all five backed up.
         Review list and image crop sheet: `~/src/mbc-tests/review/`
         (outside the repo). Applied: Contact is the church office on all
         five; Lunch Club no longer names its seniors' worker (Where is
         "Needs confirming"); Sunday Mornings gains Café Church (first
         Sunday) and Communion (third Sunday) from live and is split into
         paragraphs; "Beacon Café" (accent) everywhere, including the page
         title, menu item and scaffold spec (slug stays `beacon-cafe`); Rock
         Solid keeps the Youth for Christ text with a visible "Needs local
         copy" note at the top of the description (**remove it before
         publishing**) and age range "Needs confirming"; Stepping Stones
         rooms are marked "need confirming"; curly apostrophes and quotes,
         stray line breaks removed; Sunday Mornings and Beacon Café show
         their wide images uncropped (`mbc-image-contain`, set with
         `image_fit` in the facts file) as a stopgap until better photos
         arrive. Compare-with-live finding: the sandbox texts are
         independent rewrites (no shared sentences with live), so "which is
         more current" cannot be answered by comparison; facts were taken
         from the sandbox text and from live pages and weekly news (with
         dates).
         **Batch 2 (Info pages) and Contact Us, 15 Oct 2026: PUBLISHED**
         one at a time with the backup-first step and a per-page check
         (published, Elementor meta gone, no Elementor markup, draft
         trashed, backup present, public URL 200, no GP banner): Who we are
         (page 343), What we believe (345), Meet the team (42), Room Hire
         (391), Policies & Documents (276) and Contact Us (211, Listing
         template). Drafts 741-745 and 771 are in the trash; all six are
         backed up in `~/src/mbc-elementor-backups/`. John's block-editor
         check of all six drafts was clean. Review notes:
         `~/src/mbc-tests/review/elementor-batch-2-review.md`. Tool:
         `elementor_exit.py draft` converts Info pages (`"kind": "info"` in
         the facts file; headings, lists, links and tidy inline markup in
         document order, pasted classes and styles dropped, same-site URLs
         made relative), takes `content_file` for a hand-built page and
         `template` for another template (Contact Us), and
         `elementor_exit.py assets PAGE_ID` copies the page's live uploads
         to the sandbox (see Safety). **Decisions applied:** Meet the team
         keeps the names ("Current as of October 2026") with a photo grid
         (media 752-759 and Claire Taylor's 502); policy PDFs are media
         760-768 and the draft links point at them; Room Hire's graphic is
         kept as a stopgap; Policies has no image; dated document titles
         left as they are; Who we are's rewritten sentence is awaiting John
         S's approval. Contact Us has no form (plugin undecided): details,
         office-hours note, a "View on Google Maps" button instead of the
         Google iframe, social links; the `contact-placeholder` pattern
         holds the same content (theme version 1.0.3).
         **Legacy (Elementor) pages remaining, 11:** Mission partners BMS
         (467), Leeds Furniture Mission (452), Mission Aviation Fellowship
         (455), Romania (525); Rock Solid (60, held: draft 727 waits for
         John S's copy); Services on YouTube (350, holds the
         `[mbc_youtube_feed]` shortcode); the parent pages Mission (374,
         section index template) and What's On (327); and the unlinked
         drafts Welcome (8), Baptism (54) and Sunday School (556).
         **Batch 3 (Mission partner pages), 15 Oct 2026: PUBLISHED** with
         `publish-batch` (backup first, fail-fast, every check passed): BMS
         (page 467), Mission Aviation Fellowship (455), Leeds Furniture
         Mission (452) and Romania (525), all on the Mission partner
         template; drafts 780-783 are in the trash and all four are backed
         up. John's block-editor check was clean. Hand-built blocks (the
         `template` key in the facts file); text kept as it was, with
         typographic fixes. "How you can support" comes only from the old
         text; anything else says "Needs confirming". Websites: BMS
         `https://bmsworldmission.org/`, MAF `https://maf-uk.org/`, Leeds
         Furniture Mission `https://www.leedsandmoortown.org.uk/` (the
         destination of the address on its van), Romania `https://bisericamanastur.ro/`
         labelled "our partner church in Cluj" (not MrsG's own site). The
         founder's name stays (history). Live comparison
         (`elementor_exit.py live-read`, approved for the whole exit): live
         has no BMS or MAF pages; its Romania (2011) and Furniture Store
         (2013, 2023) pages are older than the sandbox text. **Still on the
         pages (flagged by `bin/scan_placeholders.py`):** Furniture Mission
         "How you can support" (Needs confirming) and Romania (start date,
         support lines, placeholder image, because the Google Maps
         screenshot was dropped). Review notes:
         `~/src/mbc-tests/review/elementor-batch-3-review.md`.
         **Verification fails loudly:** `elementor_exit.py publish` runs its
         post-publish checks itself and exits non-zero on any failure;
         `publish-batch PAGE:DRAFT ...` stops at the first failure; `verify
         PAGE DRAFT` runs the checks alone.
         **Legacy (Elementor) pages remaining:** Rock Solid (60, held), the
         parents Mission (374) and What's On (327), and the drafts Welcome
         (8), Baptism (54) and Sunday School (556) (see Questions for Andy).
      5. When no page uses Elementor: check for remaining dependencies
         (Theme Builder templates such as footer #208, shortcodes,
         widgets), then **ask John before deactivating** Elementor and
         Elementor Pro. Measure page speed before and after.
- [x] Idempotent scaffold script `bin/scaffold_site.py` (14 Oct 2026):
      dry run by default, `--apply` to change. Pages, hierarchy, slugs and
      the "Primary" menu (location `primary`) match the menu above; old
      Church Life (237) and Administration (270) trashed; old "Menu 1"
      (id 9) left unassigned, delete once happy. `bin/` has its own
      `.htaccess` (`Require all denied`). New pages are empty and
      published. Sandbox URL changes: `docs/url-changes.md`.
- [ ] Redirect map before launch: sandbox map is in `docs/url-changes.md`
      (WP keeps no old-slug redirects for pages, so some old URLs 404).
      A live-old → new map is still needed, built read-only from the live
      site's public REST API; this needs a narrow exception to the
      protect-live hook at that point.
- [ ] Content migration from live site (via its public REST API, read-only)
      **Requirement:** inline images must be re-linked to attachment IDs (`wp-image-ID`
      class, attachment imported or matched), never `-scaled` originals, prepared at the
      `post` preset (see the image-weight item and `docs/image-standards.md`).
- [ ] Contact form plugin decision
- [x] Demo post import (15 Oct 2026, `bin/import_live_posts.py
      --unwrap-elementor`): 10 live posts (23 Sep to 12 Oct), all in
      "News", with 19 images, as sandbox posts 674-698 tagged
      `mbc_demo_import=1`. All were Elementor-built on live, so they were
      reduced to plain text and images (galleries flattened). Idempotent:
      a second dry run reports 10 unchanged. Open for the single-post
      template: each post has a featured image *and* often the same photo
      inline, so it may show twice. Cleanup is in the cutover checklist.
- [ ] **Newsletter rework** (built 15 Oct 2026, replaces the v1.8 code snippet;
      `inc/newsletter/`, `template-parts/newsletter-email.php`,
      `single-` / `archive-mbc_newsletter.php`). Built and tested on the
      sandbox: steps 0-5 (content type, edit screen, pre-fill and list editor,
      web version, archive, email endpoint, legacy address). Still to do:
      **step 7** (move John S to the role, then remove Code Snippets and
      Shortcoder, **asking first**) and the Mailchimp test (see below).
      v1.8 is kept unchanged in `docs/newsletter/newsletter-code-v1.8.php`
      for reference; its five known bugs (wrong thumbnail size, undefined
      `$post`, sticky logic, invalid CSS/HTML, hard-coded live URLs and a
      click-tracking link with one recipient's hash) do not exist in the
      rebuild.
      - **Content type `mbc_newsletter`**: an issue is a stored snapshot (title,
        optional subtitle `mbc_nl_subtitle`, header image = featured image,
        intro in the classic editor limited to paragraphs/links/lists, and an
        ordered list of post IDs `mbc_nl_posts`). Later posts never change a
        saved issue; a post deleted or unpublished later is skipped. Published
        means public: `/newsletter/` (archive, linked from the News page) and
        `/newsletter/<slug>/` (web version).
      - **Pre-fill** ("Refill from posts since the last issue"): sticky posts
        first (whatever their age; remove per issue), then everything published
        after the previous issue's date, newest first, capped at 6 (`mbc_newsletter_max_posts` filter; the old
        Shortcoder `newsletter-no-of-posts`); 14 days
        when there is no previous issue. Categories are referenced by **slug**:
        `mbc_newsletter_category_slugs` (default `news`) and
        `mbc_newsletter_exclude_category_slugs` (default `ministers-message`).
      - **Capability and role**: `mbc_manage_newsletter` (all the type's
        capabilities map to it; `read` does not, so logged-out visitors read the
        archive). Administrators have it in code. Role "Newsletter editor" =
        Editor + `mbc_manage_newsletter` + `edit_theme_options` (menus);
        created once, re-synced when `MBC_NL_VERSION` changes (also flushes
        rewrite rules). Elementor needs nothing beyond Editor.
      - **Web version layout** (`single-mbc_newsletter.php`): the whole issue
        sits in one centred column, `--mbc-newsletter-measure` (40rem = 640px)
        plus the usual side padding; the header image is shown at its natural
        size, never enlarged; the posts are a single-column list (thumbnail
        `--mbc-newsletter-thumb`, 180px, on the left, date, title and excerpt
        on the right; the thumbnail goes on top when the column is under
        30rem, by container query) built on `.mbc-card`. The thumbnail is the
        post's `medium_large` size in a **fixed 3:2 box** (`object-fit: cover`,
        never stretched to the row's height); the email shows the same size at
        180px wide (`MBC_NL_THUMB_WIDTH`); "View as email" ends
        the column on published issues. The archive keeps the card grid
        (`.mbc-nl__list`); the issue page uses `.mbc-nl-issue__list`.
      - **Email** `/newsletter/<slug>/email/`: standalone 600px table document,
        inline styles, absolute https links, Mailchimp merge tags
        (`*|UNSUB|*`, `*|UPDATE_PROFILE|*`, `*|LIST:ADDRESSLINE|*`,
        `*|CURRENT_YEAR|*`), light colour scheme, VML "Read more" button for
        Outlook, header image links home (the issue's header image, else the
        site logo, else the site name). Colours, font and sizes are read from
        `tokens.css` (`inc/newsletter/email-style.php`; `--mbc-email-*` tokens
        hold the pixel sizes). Always `noindex`. **Access:** a per-issue secret
        key (`_mbc_nl_key`, 40 hex characters, made on first save, never over
        REST) in `?key=` serves the email for any status without a login
        (`no-store`, no referrer, `hash_equals`); without a key a published
        issue is public and anything else needs the capability, else 404. The
        edit screen has "Copy email URL" and "Regenerate key" (the old link
        then stops working). **Mailchimp imports from the keyed URL.**
      - **Old address `/mbc-newsletter-articles/`**: 302 to the latest issue's
        email version if it is 10 days old or less, otherwise a fresh unsaved
        pre-fill as an email. Whether Mailchimp's import follows a redirect is
        **untested**: check it in the Mailchimp test.
        The 10 days is the administrator setting `mbc_nl_legacy_days` (default
        10, 0-3650, REST `/wp/v2/settings`; filter `mbc_newsletter_legacy_days`
        still applies). It exists so the tests can force both branches.
        **Real issues exist on the sandbox** (e.g. #895, John's test issue; never
        delete or edit issues the tests did not create). `newsletter_check.py`
        is independent of them: the pre-fill section computes the window from
        the actual latest issue and asserts on its own fixture posts only; the
        legacy section checks the real state, then both branches with a
        fixture issue and the threshold (restored in `finally`). Only ids a run
        created are ever deleted, and every post or issue a test creates has a
        title starting "[TEST] " (`TEST_PREFIX`), so strays are obvious.
      - **Checks** (outside the repo, sandbox only, all pass):
        `~/src/mbc-tests/newsletter_check.py [caps|prefill|adminjs|web|legacy]`,
        `newsletter_email_check.py <shots-dir>` (access rules, key, static
        email checks, 360/700px), `excerpt_check.php` (excerpt of an
        Elementor-built post is clean prose, including the "... Read More"
        tail of imported excerpts).
      - **Mailchimp test (not done; John's own address only, never the
        audience):** publish a test issue (or use its keyed URL), start a "Code
        your own" campaign with import from URL, check what the import does
        (it is believed to copy the HTML once, when Import is pressed, not at
        send time), send a test to John's address only, view in Gmail (web and
        phone) and one Outlook client; check the merge tags are replaced and
        that the old-address redirect is followed. Never schedule or send to
        the audience from the sandbox.
      - **Code Snippets and Shortcoder are NOT on the sandbox** (checked
        15 Oct 2026: only Elementor, Elementor Pro, Insert PHP Code Snippet
        and MC4WP were installed). They exist on **live** only, where John S
        still runs the v1.8 workflow, so **live Shortcoder and Code Snippets
        stay untouched until cutover.**
      - **Role switch done on the sandbox (15 Oct 2026, John's instruction):**
        John S (user 3) is now "Newsletter editor" on the sandbox (was
        Administrator). The 34 capabilities lost were approved by John
        (`manage_options`, plugin, theme and user management, import/export,
        Elementor Pro notes; generated with `~/src/mbc-tests/cap_diff.py` plus
        PHP `array_diff`). Check: `~/src/mbc-tests/role_access_check.py` (a
        temporary user with the role reaches the newsletter list and edit
        screen, Appearance > Menus, a post edit, a page edit and the Elementor
        editor, and gets 403 on Settings, Plugins, Users, Add plugin, Theme
        editor, Import and MC4WP settings). **The live switch stays a cutover
        item.** Roll back, if ever needed, with REST `POST /wp/v2/users/3`
        `{"roles":["administrator"]}`.
      - **Insert PHP Code Snippet (sandbox plugin) deactivated 15 Oct 2026**
        (John's instruction; not deleted). Its only snippet is
        `GetSalutation()` (a time-of-day greeting with Christmas and New Year
        variants); nothing on the sandbox calls it (no `xyz-ips` shortcode in
        any post, page, template, widget or menu, no greeting text on the 43
        pages rendered). **Delete it after a week with no issues (deactivated
        2026-10-15).** No greeting replacement is built; at cutover check where
        live uses it.
      - **Shortcoder (live) -> new fields.** Live holds exactly six shortcodes:
        | Shortcoder | Live value | Now |
        |---|---|---|
        | `newsletter-title` | per issue | the issue's title |
        | `newsletter-sub-title` | per issue (was hidden) | `mbc_nl_subtitle` |
        | `newsletter-image-url` | `MailchimpHeader.jpg`, later `MBC_Newsletter_Header_20251223-.jpg` (media library; varies) | the issue's featured image, else the **default header image** (option `mbc_nl_default_header_id`, sandbox media 865, copied from live by `bin/newsletter_header_image.py`) |
        | `newsletter-image-width` | 600 px | not needed: the email is 600 px wide and images are never stretched |
        | `newsletter-image-height` | 275 px | not needed: height follows the image |
        | `newsletter-no-of-posts` | 6 (varies; redundant now) | pre-fill cap default 6, filter `mbc_newsletter_max_posts`; the list is editable per issue |
        John replaced the first image (`MailchimpHeader.jpg`, 600 x 100; its
        media 840 was unreferenced and deleted) on 15 Oct 2026 with the one
        now in use on live, **600 x 338 px** ("MBC NEWS" over a line drawing
        of the church). The email shows it at width 600, height 338 (its
        natural size, no upscaling); the web version uses the same image.
        The old Shortcoder height of 275 px did not match either file. Its
        alt text is on the media item.
        Change the default image by uploading another and setting the option
        over REST `/wp/v2/settings` (or the script); an issue's own featured
        image always wins. Check at cutover that the image is on live
        uploads (the sandbox copy carries `mbc_live_source_url`, so it is
        not removed by the demo-import clean-up, which names its 19 media).
      - **Header image and issue copy:** the default header image is the live
        one (above); John may supply a new one later. Editor how-to:
        `docs/newsletter/editor-guide.md`.

## Questions for Andy

Open design questions to put to Andy Berry (John to ask):
1. **Tagline wording:** "Loving God. Living Generously. Following Jesus."
   (his wireframe, used for now) or "Loving God, living generously,
   following Christ." Awaiting church confirmation either way.
2. **Explicit "Home" menu item:** the logo is Home per his spec; does he
   also want a "Home" item in the menu?
3. **X behaviour inside a submenu on mobile:** today the X (burger) closes
   the whole menu from inside a submenu, while Back goes up one level, and
   Android's system Back leaves the page. Should X go up one level instead,
   and should system Back step up a level (see the open question in Status)?
4. **Left-aligned band text:** the front page bands are left-aligned
   throughout (a deliberate deviation); his wireframe right-aligns headings
   beside a left-hand image. Is left-aligned acceptable?
5. **About Us (341) and Resources (623) overview pages:** both are menu
   toggles (not links) but are reachable by URL and currently blank. Decide
   between short overview content (a list of the child pages) or a redirect
   to the first child page. (Decision later; no action yet.)
6. **The Welcome, Baptism and Sunday School drafts:** three old unlinked
   drafts (Welcome 8 under About Us; Baptism 54 and Sunday School 556 under
   What's On, all Elementor, none in the menu). What should become of
   them? Baptism in particular may deserve a real page (an Activity or
   Info page); the other two may simply be dropped or folded into existing
   pages.
7. **Furniture Mission name:** the page is titled "Leeds Furniture Mission"
   (and the charity's van says so), but its text says "Leeds and Moortown
   Furniture Store". Which name should the page use? (Both are left as
   they are for now.)

## Content questions for John S

1. **Rock Solid:** local copy (what happens, who comes, who to ask) to
   replace the Youth for Christ text, and the **age range** (school years).
2. **Lunch Club:** the **venue** (which room).
3. **Stepping Stones:** confirm the **rooms** used (main room, Corner Room,
   Sports Hall, taken from the old text).
4. **Who we are, wording to approve:** the old sentence "Led by our
   full-time minister, Shelley Dring, you'll find us by the big roundabout
   where King Lane and Stonegate Road cross" (a dangling modifier) is
   rewritten in the draft as "We’re led by our full-time minister, and
   you’ll find us by the big roundabout where King Lane and Stonegate Road
   cross." John S to approve or reword.
5. **Mission partners (Leeds Furniture Mission, Romania):** the "How you
   can support" lines (pray, give, get involved) for both, still "Needs
   confirming" on the live pages.
6. **Romania start date:** the page needs the year the Romanian links began;
   the sources disagree: the sandbox text said "since the mid 70's", the
   2011 page on the live site says 1991 (Paul Hicks, the Monastur Baptist
   Church in Cluj and the Hungarian Reformed Church in Stejeris). It reads
   "(start date: Needs confirming)" until settled. MrsG's own contact or
   website, if it has one, would also help.
(The contact for all the Activity pages is the church office, Lesley,
0113 2693750.)

## Image wishlist for John S

The Activity template's image slot is **4:3 landscape** (about 560 x 420 on
a desktop, full width on a phone, cropped to fit). For each photo: 4:3, at
least 1200 x 900 (1600 x 1200 is ideal), subject near the centre, **no text
in the image**.
- **Sunday Mornings:** a photo of a Sunday service (the current image is a
  designed graphic with text in it, shown uncropped as a stopgap).
- **Beacon Café:** a photo of people at the café (the current image is a
  4:1 panorama of two mugs, shown uncropped as a stopgap).
- **Room Hire:** photos of the rooms available for hire (the current image
  is a designed graphic with text and a black-and-gold look, kept as a
  stopgap). The Info page's image is full width, so a wide photo is fine.
- **Contact Us map image (low priority, not now):** John may later add a
  map image on Contact Us that links to Google Maps. The old banner
  (media 607, a screenshot of Google Maps) is still in the media library.
- **Mission partner logos:** the official logos of BMS World Mission, MAF,
  Leeds Furniture Mission and (if it has one) MrsG, ideally from each
  partner's own brand or press page and with their permission. The partner
  template's image slot is designed for a logo (shown whole, never
  cropped). Until then BMS and MAF use designed illustrations (media
  480 and 487, wide banners shown whole) and Furniture Mission a photo of
  its van (which carries the logo).
- **Romania:** a photo for the Romania partnership (the page shows the
  placeholder image; the Google Maps screenshot was dropped because of
  Google's terms; media 531 stays in the library, unused).
Photo consent is managed by John S (see Safety).

## Header and footer architecture

- `template-parts/header.php` and `template-parts/footer.php` are rendered
  by `inc/layout.php`, which on `wp` (priority 100) calls
  `remove_all_actions()` on `generate_header` and `generate_footer` and
  adds its own renderers. **Deliberate:** anything else hooked to those
  two actions (GeneratePress's header/footer/footer widgets, Elementor Pro
  Theme Builder header/footer, any plugin) will not render. GP still owns
  `<head>`, `wp_body_open`, the skip link and body classes.
- Elementor footer template #208 ("Entire site") is bypassed, not deleted
  or edited. Never delete Elementor templates without asking.
- Menu locations: `primary` (header) and `footer` (small links, depth 1;
  assign a menu in wp-admin, nothing renders until one is assigned).
- Menu items with class `mbc-menu-toggle` must render as
  `<button aria-expanded aria-controls>` (`inc/class-mbc-nav-walker.php`).
- The 1024px nav breakpoint is the single `@media` in `css/header.css`
  (documented in `tokens.css`); `js/navigation.js` detects "mobile" by
  whether the burger is displayed. Transitions sit inside
  `prefers-reduced-motion: no-preference`.
- Site facts (address, charity number, social URLs, MC4WP form ID 206) are
  in `inc/site-info.php`. Two YouTube URLs: the channel
  (`https://www.youtube.com/@MoortownBaptistChurch`, footer icon) and the
  services link (`.../@MoortownBaptistChurch/streams`, the channel's Live
  tab, used for the "Services on YouTube" menu item; the channel has no
  public playlists, checked 16 Oct 2026). Keep `YOUTUBE_SERVICES_URL` in
  `bin/scaffold_site.py` in step with `youtube_services_url`. If a services
  playlist is created later, point `youtube_services_url` at it.

### Newsletter signup form (MC4WP form 206)

**The sandbox form posts to the real Mailchimp audience.** Test only with
John's own address, never anyone else's, and never submit it from an
automated test: `~/src/mbc-tests/footer_form_check.py` reads styles and
tabs through the fields but fails if any non-GET request is made.

The form lives in the database (MC4WP → Forms → form 206), not in git, so
its markup is recorded here (changed 16 Oct 2026: First name and Last name
added). If the form is ever lost or rebuilt, this is what the theme CSS
(`css/footer.css`) expects: one `<p>` per field inside the plugin's
`.mc4wp-form-fields` wrapper, with the email first and the submit last
(the 768px layout targets `p:first-child` and `p:last-child`).

```html
<p>
  <label for="mc4wp-email">Email address:</label>
  <input type="email" id="mc4wp-email" name="EMAIL" placeholder="Your email address" autocomplete="email" required />
</p>
<p>
  <label for="mc4wp-fname">First name:</label>
  <input type="text" id="mc4wp-fname" name="FNAME" placeholder="First name" autocomplete="given-name" />
</p>
<p>
  <label for="mc4wp-lname">Last name:</label>
  <input type="text" id="mc4wp-lname" name="LNAME" placeholder="Last name" autocomplete="family-name" />
</p>
<p>
  <input type="submit" value="Sign up" />
</p>
```

- **Merge tags:** `EMAIL` (required), `FNAME`, `LNAME` (both optional). They
  must exist in the Mailchimp audience with exactly these tags, or the names
  are silently dropped.
- The honeypot, timestamp and form-ID hidden fields are added by the
  plugin; the honeypot sits in a `display: none` label.
- Layout: stacked below 768px; from 768px the name fields share one row
  under the email, and Sign up spans the full row. Styling uses the footer
  and input tokens only; labels, inputs, placeholders and focus rings are
  identical across the three fields in all four looks (checked with
  `footer_form_check.py` and `contrast_check.py`).
- The form now collects names as well as email addresses, which the
  Privacy Notice must cover (cutover checklist).

## Permalinks

Decision (15 Oct 2026, approved by John): the sandbox uses the live site's
structure, `/%postname%/` (checked from the live posts' `link` fields:
e.g. `/harvest-festival-sunday-october-4th/`, trailing slash). It was
`/archives/%post_id%` (numeric). It was set from git with a one-shot
(REST cannot change `permalink_structure`; there is no SSH or WP-CLI), with
a soft rewrite flush that leaves `.htaccess` alone; that file has since been
removed. All 42 published pages and posts returned 200 afterwards;
slash-less URLs and `?p=ID` 301 to the new form; only the retired
`/archives/ID` form 404s (nothing links to it). Imported demo posts now sit
at exactly their live URLs. Do not change the structure again without
asking.

Pages are hierarchical (`/about-us/who-we-are/`), whereas live pages are
mostly flat at the root, so the live-old → new redirect map is still needed
(see Status).

## Block patterns

Patterns are files in `generatepress-child/patterns/*.php` (header comment:
Title, Slug, Categories `mbc`, ...). **WordPress caches a theme's pattern
list in a transient keyed on the theme `Version`** (`style.css`), so a new
or renamed pattern file is not registered until the cache expires or the
version changes. **When you add a pattern or change a pattern header, bump
`Version` in `generatepress-child/style.css`** (currently 1.0.3). Edits to a
pattern's body markup need no bump. A pattern used for seeding
(`bin/seed_patterns.py`) must contain no PHP after its header. Every pattern
file has `defined( 'ABSPATH' ) || exit;` after its header comment (so a
direct request to the file returns nothing).

**Images rule:** the new templates place their own image; GeneratePress's
featured-image banner is **never** output on them (`mbc_take_over_layout()`
in `inc/layout.php` removes it for the MBC page templates and single posts;
legacy Elementor pages keep it until converted). The tests assert there is
no `.page-header-image` on new-template pages.

## Page weight (Elementor template condition removed, 16 Oct 2026)

Measured with `~/src/mbc-tests/bin/measure_weight.py` (Chromium at 360px, empty cache, compressed bytes on the
wire, `networkidle`), before and after removing template #208's "Entire site" condition (see Status). The
saving is 11 fewer requests and about 88 kB on every non-Elementor page (8 of the requests are Elementor files; the other 3 went with them) (the bytes left are the page's own
images, fonts and scripts); the Elementor pages lose only `post-208.css`.

| Page (360px, empty cache) | Requests before | after | Transferred before | after | Elementor files before | after | Console errors before | after |
|---|---|---|---|---|---|---|---|---|
| Front page | 36 | 25 | 636 kB | 548 kB | 8 | 0 | 1 | 0 |
| Info page (Who we are) | 28 | 17 | 203 kB | 115 kB | 8 | 0 | 1 | 0 |
| A news post | 30 | 19 | 1358 kB | 1271 kB | 8 | 0 | 1 | 0 |
| News listing | 33 | 22 | 1760 kB | 1672 kB | 8 | 0 | 1 | 0 |
| Newsletter archive | 27 | 16 | 142 kB | 54 kB | 8 | 0 | 1 | 0 |
| Elementor page (Rock Solid, 60) | 36 | 35 | 375 kB | 374 kB | 15 | 14 | 0 | 0 |
| Elementor page (Mission, 374) | 36 | 35 | 360 kB | 360 kB | 15 | 14 | 0 | 0 |

Re-run it after a template or plugin change; it is the baseline for the page-speed comparison planned for the Elementor exit.

## Slideshow

The front page's "More at Moortown" band is a slideshow (built 16 Oct 2026): a
Group (class `mbc-band mbc-band--slides`, text in `mbc-band__content`) beside a
core **Gallery** block with the classes `mbc-slideshow mbc-slideshow--5s`.
Pattern: `patterns/slideshow-band.php` (`mbc/slideshow-band`; the front-page
pattern uses the same markup with placeholder images). Editor guide:
`docs/slideshow-guide.md` (3-5 photos, strongest first, captions, speed).

- **Files:** `css/slideshow.css` and `js/slideshow.js`, loaded only on pages
  whose content contains `mbc-slideshow` (`inc/slideshow.php`); the band's
  two-column layout is in `css/patterns.css`; tokens `--mbc-slide-ratio` (4/3),
  `--mbc-slide-fade`, `--mbc-overlay-bg`, `--mbc-overlay-bg-hover`,
  `--mbc-overlay-text` in `tokens.css`. The slideshow CSS is not loaded in the
  editor, which shows an ordinary gallery.
- **Frame:** fixed 4:3 (`aspect-ratio` plus `object-fit: cover`), so photos of
  any shape never change the box. All frame rules sit under `html.mbc-js` (the
  flag set in `<head>`), so the layout is final at first paint and **CLS is 0**
  (tested). Without JavaScript it stays an ordinary gallery.
- **Behaviour:** 5 s per slide by default; change it with the class
  `mbc-slideshow--Ns` (Additional CSS class, valid in the editor) or
  `data-interval` (hand-written; the editor flags a hand-added attribute).
  Pause/Play, Previous, Next and dots (all at least 44px, focus ring from
  `--mbc-focus`), Left/Right keys, touch or mouse swipe (pointer events,
  horizontal only, `touch-action: pan-y`), captions from the Gallery image
  caption (strip above the controls, none if unset). Pauses on hover (real
  mouse), keyboard focus, hidden tab and off screen; no autoplay and no fade
  under `prefers-reduced-motion`. Only the active slide is exposed to
  assistive technology (`aria-hidden` and `inert` on the rest).
- **Gotchas found while building it:** core wraps a Group's children in
  `.wp-block-group__inner-container` at render whatever the layout setting, so
  the CSS flattens it (`display: contents`); WordPress stores `--` in block
  comment attributes as `\u002d\u002d` (match that when editing page content
  by script); core's gallery CSS needs `:not(#id)` to be out-weighed; a mouse
  swipe needs `draggable=false` on the images.
- **Content now:** placeholder photos from the media library (566, 299, 684,
  568, captions on the first two) until John supplies real ones via the inbox
  (`image_prep.py ... band`). Page 182 content backups before this work:
  `~/src/mbc-elementor-backups/182-home-pre-slideshow.json`.
- **Tests:** `~/src/mbc-tests/slideshow_check.py` (all five looks at 360 and
  1280px for layout, controls, focus ring and contrast; autoplay, pause, hover,
  focus, keys, swipe (mouse drag and real touch events, incl. that a vertical
  swipe scrolls the page), captions, CLS, reduced motion, no-JS, the interval
  override and the block editor). Fake clock, so it does not sleep.

## Image standards

Presets (banner 2240x896, post 1500x1000, band 1200x900, card 900x600, square
700x700, newsletter-header 600 wide), each 2x the slot it serves, the quality
and kB-cap rule, metadata stripping, the consent check (John S) and the
`image_prep.py` workflow (inbox -> grid -> preview -> OK -> `--apply`) are in
**`docs/image-standards.md`**. `~/src/mbc-tests/bin/measure_slots.py` measures
the rendered slots in all looks and **fails if any preset drops below 2x**;
re-run it after any template or CSS change that touches images. `~/src/mbc-tests/images_weight_check.py` loads every published post, page and
issue at 360 and 1280px and flags any image whose file is over 2.3x its largest
rendered width or over 300 kB, saying why (`NO_SRCSET`, `SIZES` = the `sizes`
attribute is too generous, `LADDER` = WordPress's size steps, `HEAVY`, `PNG`).
A real image over 300 kB or over 3x FAILs; 2.3-3x and anything from the imported
demo media is a WARN. It fails on the open findings below until they are fixed.
The tools and
their git repo live in `~/src/mbc-tests/` (outside this repo); photos in
`~/src/mbc-images/`.

## Looks (alternate token sets)

A look is a CSS file in `generatepress-child/css/looks/` that overrides
`:root` token values only. `?look=<name>` on any URL sets the `mbc_look`
cookie and applies that look site-wide; `?look=default` (or an unknown
value) clears it. The name is checked against the `.css` files that exist
(`inc/looks.php`). Logged-in users also get a small floating picker.

To add a look:
1. Copy `css/looks/dark.css` to `css/looks/<name>.css` (lowercase letters,
   digits and hyphens only). Override token values only; never add
   component rules, and never use a hard-coded value outside a look file.
   The role tokens (`--mbc-button-*`, `--mbc-footer-*`, `--mbc-input-*`,
   `--mbc-focus*`, ...) are listed in `css/tokens.css`.
2. Run the contrast check:
   `~/.venvs/mbc-test/bin/python ~/src/mbc-tests/contrast_check.py <dir> <name>`
   (WCAG AA: text 4.5:1, large text, button boundaries, input borders and
   focus rings 3:1; it also checks hover states). The style guide page
   (`/style-guide`, seeded by `bin/seed_style_guide.py`) is the test page.
3. Look at the screenshots it writes, then commit. There is no registry:
   the file appears in the picker automatically.

Legacy Elementor content sets its own colours and ignores looks; the
contrast check reports it as informational only.

### The "original" look (15 Oct 2026)

`css/looks/original.css` recreates, as far as tokens allow, the first sandbox
build (tag `split-layout-final`, `assets/mbc-shared.css` and
`split-layout.css`): near-black surfaces (`#111`, `#0d0d0d`, `#0a0a0a`), gold
accent `#c9a84c` (hover `#b8943d`, `#111` text on gold), Playfair Display
headlines, Lato 300 body, square corners (2px on small boxes), a modest type
scale. Every value is traced to the original in the file's comments. Layout
(fixed sidebar, split homepage) is out of scope.

- **Fonts are self-hosted** in `generatepress-child/fonts/` (woff2, latin
  subset, SIL OFL; licences and source in that folder): Playfair Display 700,
  900, italic 400, italic 700; Lato 300, 400, 700; Outfit 300, 400, 600, 700.
  The `@font-face` rules sit **inside `original.css`** (the one exception to
  "look files override token values only"), so fonts load only while that look
  is active, with `font-display: swap` and fallback stacks (Georgia, system-ui).
  No request goes to Google. `look_shots.py` proves it: under `original` only
  the faces the text uses are fetched, under the other looks none.
- **Contrast:** passes AA in all five looks. One change from the original:
  the field border `#2a2a2a` (1.24:1 on the field, below the 3:1 for field
  borders) is `#666666` (3.09:1). Everything else passes as the original had it.
- **Bug fixed on the way:** GeneratePress's stylesheet set font family, size,
  weight and line height on body, form controls and headings with the same
  specificity and loaded later, so no look could change type. `base.css` now
  lifts those rules (`html body ...`). The default look is unchanged in
  practice (its stack matches GP's).
- **Not reproducible with today's tokens (a role token each would fix it, if
  it is worth it):**
  1. **Outfit**, the original's UI face (nav, buttons, labels, footer
     labels): no UI-font role (`--mbc-font-ui`). The faces are declared and
     ready; nothing uses them yet.
  2. **Uppercase, letter-spaced labels** (nav, buttons, h3, eyebrows): no
     text-transform or letter-spacing tokens.
  3. **Heading colour:** one `--mbc-text` serves body and headings, so
     headings are `#cbcbcb`, not the original's pure white.
  4. **Heading weight 900** for page titles (Playfair 900 is shipped, unused).
  5. **Gold italic `<em>` accent inside headings.**
  6. **Link style:** gold text with a faint gold underline instead of the
     browser underline (no link-decoration token).
  7. **Gold 2px top borders on cards**, the gold gradient line under banners,
     card hover lift.
  8. **Translucent blurred header** (rgba .95 with blur): `--mbc-header-bg`
     is solid `#0a0a0a`.
  9. **Status colours** (the green newsletter confirmation `#4ade80`, blue and
     red social hovers): no tokens.
  10. **Button radius:** no dedicated token, so the look sets
      `--mbc-radius-pill: 0` to square the buttons.
  11. **Type scale:** the original's sizes (body 1rem, title 1.8-2.8rem,
      h2 1.5rem) are squeezed onto our modular steps, so h3-h5 are smaller
      and closer together than before.
- Screenshots (360 and 1280px: front page, Activity page, news post) are
  made by `~/src/mbc-tests/look_shots.py <dir> <look>`. The tagged build
  cannot be rendered locally (it is a WordPress theme needing the sandbox's
  data), so there are no side-by-side shots of the old site.


## Cutover checklist (before launch)

- [ ] Disable the look switcher (`add_filter( 'mbc_looks_enabled',
      '__return_false' )`) or keep only the chosen look's file, and remove
      the others. If the
      chosen look is not `original`, also delete `generatepress-child/fonts/`;
      if it is, keep only the weights its type uses.
- [ ] **Zero placeholder markers:** `python3 bin/scan_placeholders.py`
      exits 0. It scans the rendered content of every published page and
      post (`--drafts` adds drafts) for "Needs confirming" / "need
      confirming", "Needs local copy", the block patterns' prompts ("Add
      the day and time...", "Describe what happens...", "Delete this box
      if..."), "Lorem ipsum", "will appear here" and the placeholder image.
      On 15 Oct 2026 it found 11 pages: Home (placeholder band images),
      FAQs, BU & YBA, Giving, Mission Group, Moortown Emergency Relief
      Fund, Pathways, Events, House Groups, Stepping Stones, Lunch Club.
- [ ] Remove the `/style-guide` page (ID 668, ask first).
- [ ] Remove the demo import: delete posts where `mbc_demo_import=1` (10
      posts selected from live on 15 Oct 2026, all in the pre-existing
      "News" category, so no categories were created and "News" must stay),
      delete **exactly** the 19 media the post importer uploaded (IDs 670,
      671, 672, 673, 675, 676, 678, 680, 681, 683, 684, 686, 688, 689, 692,
      693, 695, 696, 697). **Do not** delete other media carrying
      `mbc_live_source_url`: since 15 Oct 2026 the Elementor-exit tool also
      sets it on photos and PDFs uploaded for converted pages, which are
      real content. Then delete `inc/demo-import.php` and its `require` in
      `functions.php` (this also drops the meta registration; the existing
      values stay in the database, harmlessly).
- [ ] **Rename the minister's categories when posts are migrated:** the two
      categories named after a former minister on the live site become
      `ministers-blog` and `ministers-message`. Create or rename them with
      the new names and slugs, and 301 `/category/<old-slug>/` to the new slug
      (old-to-new table in `docs/newsletter/category-map.md`, section
      "Renames"). The theme's code already uses the new slugs.
- [ ] **Newsletter, live cutover:** switch John S (user 3) to "Newsletter
      editor" on live (done on the sandbox 15 Oct 2026; user admin and plugin
      settings stay with John, the Administrator). Before deactivating Code
      Snippets and Shortcoder on live, **check the live Code Snippets list
      and Shortcoder list for anything beyond the newsletter** (Shortcoder was
      reported as exactly six `newsletter-*` shortcodes; the snippets list has
      not been seen). Deactivate first, **delete after a week with no
      issues** (record the deactivation date here: ____). Insert PHP Code
      Snippet (only `GetSalutation()`, deactivated on the sandbox 15 Oct 2026):
      **check where live calls it** before deciding on a greeting replacement
      or removal.
- [ ] Newsletter cutover: check the migrated data (categories `news` and the two `ministers-*` slugs exist; the sticky
      posts are what you expect); Mailchimp test done; the role switch and the
      Code Snippets / Shortcoder clean-up (previous item); point Mailchimp's
      import at the keyed email URL (or the old address); default header
      image set on live (option `mbc_nl_default_header_id`); the type adds `/newsletter/` and its rewrite
      rules are flushed once by the version option on first load (confirm
      `/newsletter/` returns 200 after deploy).
- [ ] Untick "Discourage search engines" (Settings → Reading). Currently
      set on the sandbox (`noindex, nofollow`).
- [ ] Privacy Notice and Cookies linked in the footer menu. The Privacy
      Notice (the church's PDF, which covers consent for newsletters) is
      linked from the footer since 15 Oct 2026; **Cookies is still open**
      (no page yet; page 3 `privacy-policy` is a draft).
- [ ] **Privacy Notice should name Mailchimp (Intuit) as processor and
      mention the US data transfer, now that names are collected. Raise
      with the church's data protection lead.**
- [ ] Live-old → new redirect map (see Status).
- [ ] Remove the E2E test page 620 (ask first).
- [ ] **Live:** if live shows the same symptom (Elementor assets and the `elementorFrontendConfig`
      error on non-Elementor pages), the same fix applies (remove template #208's "Entire site"
      condition) but only after checking what the footer template does on live. Not touched.