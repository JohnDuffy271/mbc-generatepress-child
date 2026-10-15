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
- [ ] YouTube feed (`includes/mbc-youtube-feed.php`,
      `assets/css/mbc-youtube-feed.css`, shortcode `[mbc_youtube_feed]`)
      stays while page 350 (`/resources/services-on-youtube`) exists. The
      menu already links externally to the channel, so page 350 is no
      longer in the menu; when it is retired, remove the page, include,
      CSS and the `functions.php` lines together.
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
      placeholders. John approved the front page; the block editor opens
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
      - The `footer` menu location has no menu assigned until the Privacy
        Notice and Cookies pages exist (cutover checklist).
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
         office). Alt text is added to the image and to the media item.
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
         **Batch 1 (Activity pages), 15 Oct 2026, decisions applied,
         awaiting John's final look, nothing published:** drafts 725 Sunday
         Mornings (page 44), 726 Beacon Café (251), 727 Rock Solid (60), 728
         Stepping Stones (48), 729 Lunch Club (56); all five backed up.
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
- [ ] Contact form plugin decision
- [x] Demo post import (15 Oct 2026, `bin/import_live_posts.py
      --unwrap-elementor`): 10 live posts (23 Sep to 12 Oct), all in
      "News", with 19 images, as sandbox posts 674-698 tagged
      `mbc_demo_import=1`. All were Elementor-built on live, so they were
      reduced to plain text and images (galleries flattened). Idempotent:
      a second dry run reports 10 unchanged. Open for the single-post
      template: each post has a featured image *and* often the same photo
      inline, so it may show twice. Cleanup is in the cutover checklist.
- [ ] Newsletter builder (research only, **no build yet**): John has
      custom PHP/HTML code (v1.8) that builds a Mailchimp newsletter from
      posts. It must be reviewed and adapted for the new build before
      cutover. Saved unchanged, for reference only, as
      `docs/newsletter/newsletter-code-v1.8.php` (valid PHP; no secrets in
      it). Category IDs it uses are mapped in
      `docs/newsletter/category-map.md` (included 5, 8, 9, 10, 36, 38, 42,
      59; excluded 19, 27): IDs 10, 36, 38 and 19 do not exist on live, and
      the rebuild must refer to categories by **slug**, never by ID.
      **Known design flaw:** the current newsletter links to a *live* post
      list, so posts added after sending change what readers see.
      **Proposed design (to confirm with John before building):**
      - A **"Newsletter issue" content type** (custom post type) that
        stores a **fixed list of post IDs**: pre-filled with the posts
        published since the last issue, then editable, so an issue is a
        snapshot that later posts cannot change.
      - **Email-safe HTML** generated per issue for Mailchimp (inline
        styles and tables as email clients need; images at an email-sized
        width, not full size).
      - A **public web archive** of past issues, each at a stable URL.
      - **Editorial fields** (intro, sign-off, per-item notes) replacing
        the `[sc]` shortcodes in v1.8.
      - Access gated by a custom capability, **`mbc_manage_newsletter`**,
        granted explicitly (not to every editor).
      **v1.8 review (15 Oct 2026, read against the code and WordPress
      core):** the five reported bugs are all confirmed.
      1. *Full-size thumbnails:* `wp_get_attachment_url( $id, 'medium' )`
         takes only an ID, so `'medium'` is ignored; the full-size file is
         forced into a 200x200 box (distorted and heavy for email).
      2. *`$query->$post->ID`:* `$post` is not defined in the snippet; it
         only works because `get_post_thumbnail_id()` falls back to the
         current post, and it raises warnings on PHP 8.
      3. *Sticky logic:* `found_posts` counts every sticky post regardless
         of age or the limit, so old stickies always appear; if there are
         more stickies than the limit, the second query adds that many
         *extra* posts instead of none; `showposts` is deprecated (it works).
      4. *`//` comments in CSS* are invalid (use `/* */`), as are
         `align="left"` and `width="100%"` written as CSS; the `<style>`
         block sits in the body, which Gmail ignores.
      5. *Hard-coded live URLs:* the header image, body background and
         "no image" fallback, plus a Mailchimp **click-tracking link with a
         fixed recipient hash** (`...list-manage.com/track/click?u=...&id=...&e=...`,
         over plain http): every click on the header is credited to one old
         recipient.
      Also found: the first `articleTable` is opened and never closed;
      stray `;` inside HTML attributes; the title and image URL are echoed
      unescaped; the title is not linked and the "read more" `<a>` is
      empty; there is no "since the last issue" date window; the issue's
      title, image and post count come from Shortcoder `[sc name=...]`
      values (`newsletter-title`, `-sub-title`, `-image-url`, `-image-width`,
      `-image-height`, `-no-of-posts`) that the editorial fields must
      replace. Passing an array to `cat` is fine (core joins it).
      **Why John S is an administrator today, and the plan:** John S has
      the Administrator role only because the newsletter lives in code
      snippets (the Code Snippets plugin needs that). The rework moves the
      code into git (this theme), exposes **only the editorial settings**
      (title, intro, image, post selection) through an admin screen gated
      by the custom capability `mbc_manage_newsletter`, lets John S work as
      **Editor plus that capability**, and then **removes the code snippets
      plugin from the new site** (ask before removing any plugin).

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

## Content questions for John S

1. **Rock Solid:** local copy (what happens, who comes, who to ask) to
   replace the Youth for Christ text, and the **age range** (school years).
2. **Lunch Club:** the **venue** (which room).
3. **Stepping Stones:** confirm the **rooms** used (main room, Corner Room,
   Sports Hall, taken from the old text).
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
`Version` in `generatepress-child/style.css`** (currently 1.0.2). Edits to a
pattern's body markup need no bump. A pattern used for seeding
(`bin/seed_patterns.py`) must contain no PHP after its header. Every pattern
file has `defined( 'ABSPATH' ) || exit;` after its header comment (so a
direct request to the file returns nothing).

**Images rule:** the new templates place their own image; GeneratePress's
featured-image banner is **never** output on them (`mbc_take_over_layout()`
in `inc/layout.php` removes it for the MBC page templates and single posts;
legacy Elementor pages keep it until converted). The tests assert there is
no `.page-header-image` on new-template pages.

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

## Cutover checklist (before launch)

- [ ] Disable the look switcher (`add_filter( 'mbc_looks_enabled',
      '__return_false' )`) or keep only the chosen look's file, and remove
      the others.
- [ ] Remove the `/style-guide` page (ID 668, ask first).
- [ ] Remove the demo import: delete posts where `mbc_demo_import=1` (10
      posts selected from live on 15 Oct 2026, all in the pre-existing
      "News" category, so no categories were created and "News" must stay),
      delete the media with meta `mbc_live_source_url` that the import
      uploaded, then delete `inc/demo-import.php` and its `require` in
      `functions.php`.
- [ ] Newsletter builder code reviewed and adapted for the new build
      (category IDs and post markup differ from the old site).
- [ ] Untick "Discourage search engines" (Settings → Reading). Currently
      set on the sandbox (`noindex, nofollow`).
- [ ] Privacy Notice and Cookies pages published and linked in the footer
      menu. The newsletter signup collects email addresses, so a privacy
      notice is required before launch (page 3 `privacy-policy` is a draft).
- [ ] Live-old → new redirect map (see Status).
- [ ] Remove the E2E test page 620 (ask first).
