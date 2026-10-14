#!/usr/bin/python3
"""Create or update the sandbox "Style guide" page (slug style-guide).

A page of core blocks (headings, links, lists, quote, table, buttons, form
fields) for judging looks and templates. Not in the menu; remove before
launch (see the CLAUDE.md cutover checklist). Idempotent: looks the page up
by slug and updates it, or creates it. Sandbox only (URL from wp.env).
"""
import base64
import json
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

SLUG = "style-guide"
TITLE = "Style guide"
CONTENT = """<!-- wp:paragraph -->
<p>Body text with a <a href="#">link</a>, <strong>bold text</strong> and <em>emphasis</em>. Used to check each look against the design tokens.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Heading two</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Heading three</h3>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Heading four</h4>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>First item</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Second item with a <a href="#">link</a></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>A quotation, to check muted text and borders.</p>
<!-- /wp:paragraph --><cite>Someone, somewhere</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>When</th><th>Where</th></tr></thead><tbody><tr><td>Sundays, 11am</td><td>204 King Lane</td></tr></tbody></table></figure>
<!-- /wp:table -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Filled button</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Outline button</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:html -->
<p><label for="sg-email">Email address</label><br><input id="sg-email" type="email" placeholder="you@example.org"></p>
<!-- /wp:html -->
"""


def load_env():
    env = {}
    path = Path.home() / ".config" / "mbc" / "wp.env"
    try:
        for line in path.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, v = line.split("=", 1)
                env[k.replace("export ", "").strip()] = v.strip().strip("'\"")
    except OSError as exc:
        sys.exit(f"Cannot read {path}: {exc}")
    for key in ("WP_URL", "WP_USER", "WP_APP_PASSWORD"):
        if not env.get(key):
            sys.exit(f"{key} missing from {path}")
    return env


def call(env, method, route, params=None, body=None):
    url = f"{env['WP_URL'].rstrip('/')}/wp-json/wp/v2/{route}"
    if params:
        url += "?" + urllib.parse.urlencode(params)
    token = base64.b64encode(f"{env['WP_USER']}:{env['WP_APP_PASSWORD']}".encode()).decode()
    req = urllib.request.Request(url, method=method,
                                 data=json.dumps(body).encode() if body is not None else None)
    req.add_header("Authorization", f"Basic {token}")
    req.add_header("User-Agent", "mbc-seed/1.0")
    if body is not None:
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return json.load(resp)
    except urllib.error.HTTPError as exc:
        sys.exit(f"HTTP {exc.code} on {method} {route}: {exc.read().decode(errors='replace')[:300]}")
    except urllib.error.URLError as exc:
        sys.exit(f"Network error on {method} {route}: {exc.reason}")


def main():
    env = load_env()
    found = call(env, "GET", "pages", {"slug": SLUG, "status": "publish,draft,private",
                                       "context": "edit", "_fields": "id,content"})
    body = {"title": TITLE, "slug": SLUG, "status": "publish", "content": CONTENT}
    if found:
        if found[0]["content"]["raw"].strip() == CONTENT.strip():
            print(f"page {found[0]['id']}: already up to date")
            return
        call(env, "POST", f"pages/{found[0]['id']}", body=body, params={"_fields": "id"})
        print(f"page {found[0]['id']}: updated")
    else:
        made = call(env, "POST", "pages", body=body, params={"_fields": "id,link"})
        print(f"page {made['id']}: created at {made['link']}")


if __name__ == "__main__":
    main()
