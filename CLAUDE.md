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
- Tagline:   "Loving God, living generously, following Christ."

## Source of truth for structure

The page menu and wireframes come from Andy Berry (deacon). Build to his
design, not the old three-column homepage layout, which is abandoned.
The old layout is preserved at git tag `split-layout-final` (commit 1007816).

### Menu (parents are NOT links — toggles only, for touch devices)

- Logo (left) = Home link
- **About Us** → Who we are · What we believe · Meet the team · FAQs
- **What's On** → Sunday Mornings · Events · Beacon Cafe · Rock Solid ·
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
      domain is baked into page content; the tagline is CLAUDE.md's
      ("...following Christ."), whereas Andy's wireframe says "Loving God.
      Living Generously. Following Jesus." (John to confirm); band text is
      left-aligned throughout (the wireframe right-aligns headings beside a
      left-hand image); band images are neutral placeholders.
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
- [ ] Remaining page templates (front page is done): `page-templates/info.php`, `activity.php`,
      `mission-partner.php`, `listing.php`. Then re-run
      `bin/scaffold_site.py --apply`: it assigns templates only when the
      file exists (About Us/Resources children → info, What's On children
      → activity, Mission children → mission-partner, News/Events/Contact
      → listing).
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
- [ ] Newsletter builder (open item, no action yet): John has custom
      PHP/HTML code that builds a Mailchimp newsletter from posts. It must
      be reviewed and adapted for the new build before cutover (category
      IDs and post markup will differ). John will share the code in a later
      session.
      Known design flaw to fix in the rebuild: the current newsletter links
      to a *live* post list, so posts added after sending change what
      readers see. Each issue should be a fixed snapshot. Options to
      discuss: freeze by send time (a cut-off date stored per issue), a
      per-issue tag or category, content baked into the email itself, or a
      per-issue archive page.

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
