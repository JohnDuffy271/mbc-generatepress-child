#!/usr/bin/python3
"""Import recent posts from the LIVE site into the SANDBOX as demo content.

Read-only towards live: LiveReader can only GET. It has no method, body or
credentials parameter, sends no cookies or Authorization header, only
follows redirects within the live host, and only fetches
/wp-json/wp/v2/{posts,categories,media} and /wp-content/uploads/ files.
All writes go through SandboxWriter, which refuses any host other than the
sandbox and is the only place credentials are used.

Dry run by default (reads live and sandbox, writes nothing). --apply writes
to the sandbox. --self-test statically checks this file's own safety
properties (no network).

Idempotent: a post is matched on meta mbc_live_post_id, an image on meta
mbc_live_source_url. Every imported post carries mbc_demo_import=1 so it
can be removed cleanly later (CLAUDE.md cutover checklist).

Usage:
  python3 bin/import_live_posts.py --self-test
  python3 bin/import_live_posts.py                 # dry run
  python3 bin/import_live_posts.py --ids 12,34     # choose specific live posts
  python3 bin/import_live_posts.py --apply
"""
import argparse
import ast
import base64
import html
import json
import mimetypes
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from pathlib import Path

LIVE_BASE = "https://moortownbaptistchurch.org.uk"
LIVE_HOST = "moortownbaptistchurch.org.uk"
SANDBOX_HOST = "new.moortownbaptistchurch.org.uk"
USER_AGENT = "MBC-demo-import/1.0 (John, Moortown Baptist Church; read-only public GETs)"

POST_FIELDS = "id,date,date_gmt,slug,link,title,excerpt,content,categories,featured_media"
DEFAULT_COUNT = 10
MAX_PAGES = 5            # never scan more than 5 pages of 10 posts
LIVE_TIMEOUT = 60        # seconds
LIVE_RETRIES = 3         # retries after the first attempt
LIVE_BACKOFF = (3, 8, 20)  # seconds before retries 1, 2, 3
LIVE_PAUSE = 2.0         # minimum seconds between live requests
SANDBOX_TIMEOUT = 240    # sandbox writes can be slow
SANDBOX_WRITE_PAUSE = 1.0  # seconds between sandbox writes

ELEMENTOR_RE = re.compile(r"elementor-|data-elementor", re.I)
IMG_RE = re.compile(r"<img\b[^>]*>", re.I)
HREF_RE = re.compile(r"""<a\b[^>]*?\bhref\s*=\s*["']([^"']+)["']""", re.I)
FILE_EXT = (".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx", ".zip", ".mp3", ".mp4",
            ".jpg", ".jpeg", ".png", ".gif", ".webp")


def host_of(url):
    return urllib.parse.urlsplit(url).netloc.lower().removeprefix("www.")


def short(url):
    return url if len(url) < 90 else url[:87] + "..."


class LiveError(Exception):
    pass


class SameHostOnly(urllib.request.HTTPRedirectHandler):
    """Follow redirects only within the live host, and only as GET."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        if host_of(newurl) != LIVE_HOST:
            raise LiveError(f"refusing redirect to another host: {short(newurl)}")
        return urllib.request.Request(newurl, method="GET", headers={"User-Agent": USER_AGENT})


class LiveReader:
    """GET-only reader for the live site. Gentle: paced, retried, cached by callers."""

    def __init__(self):
        self._opener = urllib.request.build_opener(SameHostOnly)
        self._last = 0.0
        self.request_count = 0

    def _pace(self):
        wait = LIVE_PAUSE - (time.monotonic() - self._last)
        if wait > 0:
            time.sleep(wait)
        self._last = time.monotonic()

    def _fetch(self, url):
        if host_of(url) != LIVE_HOST:
            raise LiveError(f"not the live host: {short(url)}")
        reason = "unknown"
        for attempt in range(LIVE_RETRIES + 1):
            self._pace()
            self.request_count += 1
            req = urllib.request.Request(url, method="GET", headers={"User-Agent": USER_AGENT})
            try:
                with self._opener.open(req, timeout=LIVE_TIMEOUT) as resp:
                    headers = {k.lower(): v for k, v in resp.headers.items()}
                    return resp.read(), headers
            except urllib.error.HTTPError as exc:
                if exc.code < 500 and exc.code != 429:
                    raise LiveError(f"HTTP {exc.code} for {short(url)}") from None
                reason = f"HTTP {exc.code}"
            except (urllib.error.URLError, TimeoutError, OSError) as exc:
                reason = str(getattr(exc, "reason", exc))  # timeouts are transient
            if attempt < LIVE_RETRIES:
                delay = LIVE_BACKOFF[attempt]
                print(f"  live request failed ({reason}); retry {attempt + 1}/{LIVE_RETRIES} in {delay}s", file=sys.stderr)
                time.sleep(delay)
        raise LiveError(f"gave up on {short(url)} after {LIVE_RETRIES + 1} attempts: {reason}")

    def get_json(self, route, params=None):
        url = f"{LIVE_BASE}/wp-json/wp/v2/{route}"
        if params:
            url += "?" + urllib.parse.urlencode(params)
        body, headers = self._fetch(url)
        return json.loads(body), headers

    def get_bytes(self, url):
        if not urllib.parse.urlsplit(url).path.startswith("/wp-content/uploads/"):
            raise LiveError(f"only /wp-content/uploads/ files are fetched: {short(url)}")
        body, _ = self._fetch(url)
        return body


class SandboxWriter:
    """The only client that uses credentials, and only for the sandbox."""

    def __init__(self, env):
        base = env["WP_URL"].rstrip("/")
        if host_of(base) != SANDBOX_HOST:
            sys.exit(f"Refusing to write: WP_URL host is not {SANDBOX_HOST}")
        self.base = base
        token = base64.b64encode(f"{env['WP_USER']}:{env['WP_APP_PASSWORD']}".encode()).decode()
        self._auth = f"Basic {token}"
        self._last_write = 0.0

    def call(self, method, route, params=None, body=None, raw=None, headers=None, retries=0):
        url = f"{self.base}/wp-json/wp/v2/{route}"
        if params:
            url += "?" + urllib.parse.urlencode(params)
        if host_of(url) != SANDBOX_HOST:
            sys.exit("Refusing request: not the sandbox host")
        data = raw if raw is not None else (json.dumps(body).encode() if body is not None else None)
        if method not in ("GET", "OPTIONS"):
            wait = SANDBOX_WRITE_PAUSE - (time.monotonic() - self._last_write)
            if wait > 0:
                time.sleep(wait)
            self._last_write = time.monotonic()
        for attempt in range(retries + 1):
            req = urllib.request.Request(url, data=data, method=method)
            req.add_header("Authorization", self._auth)
            req.add_header("User-Agent", USER_AGENT)
            if raw is None and body is not None:
                req.add_header("Content-Type", "application/json")
            for k, v in (headers or {}).items():
                req.add_header(k, v)
            try:
                with urllib.request.urlopen(req, timeout=SANDBOX_TIMEOUT) as resp:
                    return json.load(resp), {k.lower(): v for k, v in resp.headers.items()}
            except urllib.error.HTTPError as exc:
                sys.exit(f"Sandbox HTTP {exc.code} on {method} {route}: {exc.read().decode(errors='replace')[:300]}")
            except (urllib.error.URLError, TimeoutError, OSError) as exc:
                if attempt >= retries:
                    sys.exit(f"Sandbox network error on {method} {route}: {getattr(exc, 'reason', exc)}")
                time.sleep(5)


# ---------------------------------------------------------------- helpers

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


def plain(text):
    return html.unescape(re.sub(r"<[^>]+>", "", text or "")).strip()


def is_elementor(post):
    return bool(ELEMENTOR_RE.search(post["content"]["rendered"]))


def live_uploads(url):
    parts = urllib.parse.urlsplit(url)
    return host_of(url) == LIVE_HOST and parts.path.startswith("/wp-content/uploads/")


def inline_images(content):
    seen, out = set(), []
    for tag in IMG_RE.findall(content):
        m = re.search(r"""\bsrc\s*=\s*["']([^"']+)["']""", tag, re.I)
        if m and live_uploads(html.unescape(m.group(1))) and m.group(1) not in seen:
            seen.add(m.group(1))
            out.append(html.unescape(m.group(1)))
    return out


def live_links(content):
    """hrefs pointing at the live domain (pages and files). Reported, never rewritten."""
    out = []
    for href in HREF_RE.findall(content):
        href = html.unescape(href)
        if host_of(href) == LIVE_HOST:
            kind = "file" if urllib.parse.urlsplit(href).path.lower().endswith(FILE_EXT) or "/wp-content/" in href else "page"
            out.append((kind, href))
    return out


def rewrite_images(content, mapping):
    def fix(match):
        tag = match.group(0)
        m = re.search(r"""\bsrc\s*=\s*["']([^"']+)["']""", tag, re.I)
        if not m:
            return tag
        new = mapping.get(html.unescape(m.group(1)))
        if not new:
            return tag
        tag = tag.replace(m.group(0), f'src="{new}"')
        tag = re.sub(r"""\s(?:srcset|sizes)\s*=\s*["'][^"']*["']""", "", tag, flags=re.I)
        return re.sub(r"\bwp-image-\d+\b\s*", "", tag)
    return IMG_RE.sub(fix, content)


# ------------------------------------------------------- Elementor unwrap

KNOWN_WIDGETS = {"text-editor", "image", "gallery", "heading"}
ATTR_NOISE_RE = re.compile(r"""\s(?:data-(?:elementor|e-|id|element_type|settings|widget_type)[\w-]*)\s*=\s*(?:"[^"]*"|'[^']*')""", re.I)


class _Unwrap(HTMLParser):
    """Collect the inner HTML of each elementor-widget-container, in order."""

    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.parts = []          # list of (widget_type, inner_html)
        self._buf, self._depth, self._type = [], 0, ""

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if self._depth == 0:
            if a.get("data-widget_type"):
                self._type = a["data-widget_type"].split(".")[0]
            if tag == "div" and "elementor-widget-container" in (a.get("class") or "").split():
                self._depth, self._buf = 1, []
            return
        if tag not in ("img", "br", "hr", "input", "meta", "link", "source"):
            self._depth += 1
        self._buf.append(self.get_starttag_text())

    def handle_startendtag(self, tag, attrs):
        if self._depth:
            self._buf.append(self.get_starttag_text())

    def handle_endtag(self, tag):
        if not self._depth:
            return
        self._depth -= 1
        if self._depth:
            self._buf.append(f"</{tag}>")
        else:
            self.parts.append((self._type, "".join(self._buf)))

    def handle_data(self, data):
        if self._depth:
            self._buf.append(data)

    def handle_entityref(self, name):
        if self._depth:
            self._buf.append(f"&{name};")

    def handle_charref(self, name):
        if self._depth:
            self._buf.append(f"&#{name};")


def gallery_to_images(inner):
    """Flatten an Elementor gallery to plain full-size <img> elements."""
    imgs = []
    for tag in re.findall(r"<a\b[^>]*\be-gallery-item\b[^>]*>", inner):
        href = re.search(r"""\bhref\s*=\s*["']([^"']+)["']""", tag)
        title = re.search(r"""data-elementor-lightbox-title\s*=\s*["']([^"']*)["']""", tag)
        if href:
            imgs.append(f'<p><img src="{href.group(1)}" alt="{html.escape(html.unescape(title.group(1)) if title else "", quote=True)}"></p>')
    return "\n".join(imgs)


def unwrap_elementor(content):
    """Reduce Elementor-built post HTML to its text and images.

    Returns (clean_html, widget_type_counts). Widgets are emitted in
    document order; galleries become plain images; Elementor classes and
    data attributes are removed.
    """
    parser = _Unwrap()
    parser.feed(content)
    out, counts = [], {}
    for wtype, inner in parser.parts:
        counts[wtype or "unknown"] = counts.get(wtype or "unknown", 0) + 1
        out.append(gallery_to_images(inner) if wtype == "gallery" else inner)
    clean = "\n\n".join(x.strip() for x in out if x.strip())
    clean = ATTR_NOISE_RE.sub("", clean)
    def drop_elementor_classes(match):
        kept = [t for t in match.group(1).split() if not t.startswith("elementor")]
        return f'class="{" ".join(kept)}"' if kept else ""

    clean = re.sub(r'\bclass\s*=\s*"([^"]*)"', drop_elementor_classes, clean)
    return re.sub(r"[ \t]+>", ">", clean), counts


# ------------------------------------------------------------- self-test

def self_test():
    """Static checks of this file's own safety properties. No network."""
    src = Path(__file__).read_text()
    tree = ast.parse(src)
    parents = {c: p for p in ast.walk(tree) for c in ast.iter_child_nodes(p)}

    def cls_of(node):
        while node in parents:
            node = parents[node]
            if isinstance(node, ast.ClassDef):
                return node.name
        return None

    def dotted(node):
        parts = []
        while isinstance(node, ast.Attribute):
            parts.append(node.attr)
            node = node.value
        if isinstance(node, ast.Name):
            parts.append(node.id)
        return ".".join(reversed(parts))

    results = []

    def check(ok, text):
        results.append(ok)
        print(("PASS " if ok else "FAIL ") + text)

    # 1. Network calls happen only inside the two clients.
    net = [(cls_of(n), dotted(n.func)) for n in ast.walk(tree) if isinstance(n, ast.Call)
           and dotted(n.func) in ("urllib.request.urlopen", "self._opener.open")]
    check(bool(net) and all(c in ("LiveReader", "SandboxWriter") for c, _ in net),
          f"urlopen/open only inside LiveReader or SandboxWriter ({len(net)} call sites)")
    check(all(d == "self._opener.open" for c, d in net if c == "LiveReader"),
          "LiveReader only uses its GET-only opener")
    # 2. Every Request built for live is a literal GET; Requests elsewhere only in SandboxWriter.
    reqs = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and dotted(n.func) == "urllib.request.Request"]
    live_ok = True
    for n in reqs:
        owner = cls_of(n)
        method = next((k.value for k in n.keywords if k.arg == "method"), None)
        if owner in ("LiveReader", "SameHostOnly"):
            live_ok &= isinstance(method, ast.Constant) and method.value == "GET"
        else:
            live_ok &= owner == "SandboxWriter"
    check(live_ok, f"every live Request is method='GET' (literal); others confined to SandboxWriter ({len(reqs)} Request calls)")
    # 3. No credentials anywhere near the live client.
    bad = []
    for cname in ("LiveReader", "SameHostOnly"):
        cdef = next(n for n in ast.walk(tree) if isinstance(n, ast.ClassDef) and n.name == cname)
        for n in ast.walk(cdef):
            text = n.value if isinstance(n, ast.Constant) and isinstance(n.value, str) else \
                (n.id if isinstance(n, ast.Name) else (n.attr if isinstance(n, ast.Attribute) else ""))
            if re.search(r"authorization|password|token|wp_user|cookie|_auth", text, re.I):
                bad.append((cname, text))
    check(not bad, f"no credential or cookie references in the live client{(' ' + str(bad)) if bad else ''}")
    # 4. LiveReader exposes only get_json and get_bytes.
    lr = next(n for n in ast.walk(tree) if isinstance(n, ast.ClassDef) and n.name == "LiveReader")
    public = {f.name for f in lr.body if isinstance(f, ast.FunctionDef) and not f.name.startswith("_")}
    check(public == {"get_json", "get_bytes"}, f"LiveReader public methods are {sorted(public)}")
    # 5. The sandbox client never mentions the live host.
    sw = next(n for n in ast.walk(tree) if isinstance(n, ast.ClassDef) and n.name == "SandboxWriter")
    names = {n.id for n in ast.walk(sw) if isinstance(n, ast.Name)}
    check(not names & {"LIVE_BASE", "LIVE_HOST", "LiveReader"}, "SandboxWriter does not reference the live host or reader")
    # 6. Only whitelisted imports.
    allowed = {"argparse", "ast", "base64", "html", "json", "mimetypes", "re", "sys", "time", "urllib", "pathlib"}
    imported = {a.name.split(".")[0] for n in ast.walk(tree) if isinstance(n, ast.Import) for a in n.names}
    imported |= {n.module.split(".")[0] for n in ast.walk(tree) if isinstance(n, ast.ImportFrom) and n.module}
    check(imported <= allowed, f"imports limited to the standard library whitelist (extra: {sorted(imported - allowed) or 'none'})")
    # 7. Runtime guards refuse before any network use.
    refused = []
    for fn, arg in ((LiveReader()._fetch, "https://" + SANDBOX_HOST + "/"), (LiveReader().get_bytes, LIVE_BASE + "/wp-admin/x.php")):
        try:
            fn(arg)
            refused.append(False)
        except LiveError:
            refused.append(True)
    check(all(refused), "live client refuses a non-live host and any path outside /wp-content/uploads/")
    try:
        SandboxWriter({"WP_URL": LIVE_BASE, "WP_USER": "x", "WP_APP_PASSWORD": "x"})
        check(False, "sandbox client refuses the live host")
    except SystemExit:
        check(True, "sandbox client refuses the live host")
    print("\nSELF-TEST", "PASSED" if all(results) else "FAILED")
    return all(results)


# ------------------------------------------------------------------ main

def select_posts(live, ids, count, unwrap):
    """Return (selected, flagged_elementor). Elementor posts are skipped unless unwrap is set."""
    if ids:
        posts, _ = live.get_json("posts", {"include": ",".join(map(str, ids)), "per_page": len(ids),
                                           "orderby": "date", "order": "desc", "_fields": POST_FIELDS})
        found = {p["id"] for p in posts}
        for missing in sorted(set(ids) - found):
            print(f"  live post {missing}: not found (published posts only)")
        return posts, [p for p in posts if is_elementor(p)]
    selected, flagged = [], []
    for page in range(1, MAX_PAGES + 1):
        posts, headers = live.get_json("posts", {"per_page": 10, "page": page, "orderby": "date", "order": "desc",
                                                 "_fields": POST_FIELDS})
        for p in posts:
            if is_elementor(p):
                flagged.append(p)
                if unwrap and len(selected) < count:
                    selected.append(p)
            elif len(selected) < count:
                selected.append(p)
        if len(selected) >= count or page >= int(headers.get("x-wp-totalpages", page)):
            break
    return selected, flagged


def sandbox_pages(sb, route, params):
    out, page = [], 1
    while True:
        data, headers = sb.call("GET", route, dict(params, per_page=100, page=page, context="edit"), retries=2)
        out += data
        if page >= int(headers.get("x-wp-totalpages", 1)):
            return out
        page += 1


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--apply", action="store_true", help="write to the sandbox (default: dry run)")
    ap.add_argument("--self-test", action="store_true", help="check this script's safety properties and exit")
    ap.add_argument("--ids", help="comma-separated live post IDs to import instead of the latest clean ones")
    ap.add_argument("--count", type=int, default=DEFAULT_COUNT)
    ap.add_argument("--unwrap-elementor", action="store_true",
                    help="include Elementor-built posts, reduced to plain text and images (galleries flattened)")
    args = ap.parse_args()
    if args.self_test:
        sys.exit(0 if self_test() else 1)

    ids = [int(x) for x in args.ids.split(",")] if args.ids else []
    sb = SandboxWriter(load_env())
    live = LiveReader()
    print(f"{'APPLY' if args.apply else 'DRY RUN'}: live (GET only, {LIVE_PAUSE:.0f}s between requests) -> sandbox")

    # Live: posts, then small cached lookups for categories and featured images.
    selected, flagged = select_posts(live, ids, args.count, args.unwrap_elementor)
    widget_counts = {}
    for p in selected:  # the content that will actually be imported
        if is_elementor(p) and args.unwrap_elementor:
            p["content"]["rendered"], counts = unwrap_elementor(p["content"]["rendered"])
            for k, v in counts.items():
                widget_counts[k] = widget_counts.get(k, 0) + v
            p["_unwrapped"] = True
    cat_ids = sorted({c for p in selected for c in p["categories"]})
    live_cats = {}
    if cat_ids:
        cats, _ = live.get_json("categories", {"include": ",".join(map(str, cat_ids)), "per_page": 100,
                                               "_fields": "id,name,slug"})
        live_cats = {c["id"]: html.unescape(c["name"]) for c in cats}
    feat = {}
    for mid in sorted({p["featured_media"] for p in selected if p["featured_media"]}):
        try:
            data, _ = live.get_json(f"media/{mid}", {"_fields": "id,source_url,alt_text"})
            feat[mid] = data
        except LiveError as exc:
            print(f"  featured media {mid}: {exc}")

    # Sandbox: current state.
    schema, _ = sb.call("OPTIONS", "posts", retries=2)
    meta_ok = {"mbc_live_post_id", "mbc_demo_import"} <= set(schema.get("schema", {}).get("properties", {}).get("meta", {}).get("properties", {}))
    mschema, _ = sb.call("OPTIONS", "media", retries=2)
    meta_ok &= "mbc_live_source_url" in mschema.get("schema", {}).get("properties", {}).get("meta", {}).get("properties", {})
    if not meta_ok:
        msg = "sandbox does not expose the demo meta keys (deploy inc/demo-import.php first)"
        if args.apply:
            sys.exit("Refusing to apply: " + msg)
        print("WARNING:", msg)
    sb_posts = {p["meta"].get("mbc_live_post_id"): p for p in sandbox_pages(sb, "posts", {"status": "any"})
                if p.get("meta", {}).get("mbc_live_post_id")}
    sb_media = {m["meta"].get("mbc_live_source_url"): m for m in sandbox_pages(sb, "media", {})
                if m.get("meta", {}).get("mbc_live_source_url")}
    sb_cats = {html.unescape(c["name"]).lower(): c["id"] for c in sandbox_pages(sb, "categories", {})}

    # Plan.
    new_cats, new_images, report = set(), set(), []
    for n, p in enumerate(selected, 1):
        title = plain(p["title"]["rendered"])
        names = [live_cats.get(c, f"#{c}") for c in p["categories"]]
        imgs = inline_images(p["content"]["rendered"])
        fsrc = feat.get(p["featured_media"], {}).get("source_url")
        unseen = [u for u in ([fsrc] if fsrc else []) + imgs if u not in sb_media]
        new_cats |= {c for c in names if c.lower() not in sb_cats}
        new_images |= set(unseen)
        links = live_links(p["content"]["rendered"])
        action = "update" if p["id"] in sb_posts else "create"
        print(f"[{n}] live #{p['id']}  {p['date'][:10]}  {title[:60]!r}  -> {action}{'  [Elementor, unwrapped]' if p.get('_unwrapped') else ''}")
        print(f"     categories: " + ", ".join(f"{c}{'' if c.lower() in sb_cats else ' (new)'}" for c in names or ["(none)"]))
        print(f"     featured: {'yes' if fsrc else 'no'}{' (to upload)' if fsrc and fsrc not in sb_media else ''}; "
              f"inline images: {len(imgs)} ({len([u for u in imgs if u not in sb_media])} to upload); live-domain links: {len(links)}")
        if links:
            report.append((p["id"], title, links))
    print(f"\nSelected {len(selected)} post(s); categories to create: {sorted(new_cats) or 'none'}; images to upload: {len(new_images)}")
    if widget_counts:
        odd = sorted(set(widget_counts) - KNOWN_WIDGETS)
        print(f"\nElementor widgets unwrapped: {widget_counts}" + (f"  UNREVIEWED TYPES: {odd}" if odd else ""))
    left = [p for p in flagged if p not in selected]
    if left:
        print(f"\nElementor markup found, not selected: {len(left)} post(s) (use --unwrap-elementor to include)")
        for p in left[:10]:
            print(f"  live #{p['id']}  {p['date'][:10]}  {plain(p['title']['rendered'])[:70]!r}")
        if len(left) > 10:
            print(f"  ... and {len(left) - 10} more")
    print("\nLinks in selected content that point at the live domain (reported, NOT rewritten):")
    if not report:
        print("  none")
    for pid, title, links in report:
        print(f"  live #{pid} {title[:50]!r}:")
        for kind, href in links[:12]:
            print(f"    [{kind}] {short(href)}")
        if len(links) > 12:
            print(f"    ... and {len(links) - 12} more")
    print(f"\nLive requests made: {live.request_count}")
    if not args.apply:
        print("Dry run only: nothing was written. Re-run with --apply to import.")
        return

    # Apply.
    cat_map = dict(sb_cats)
    for name in sorted(new_cats):
        made, _ = sb.call("POST", "categories", body={"name": name})
        cat_map[name.lower()] = made["id"]
        print(f"created category {name!r} (id {made['id']})")
    media_cache = {u: {"id": m["id"], "url": m["source_url"]} for u, m in sb_media.items()}

    def ensure_image(url, alt=""):
        if url in media_cache:
            return media_cache[url]
        data = live.get_bytes(url)
        name = Path(urllib.parse.urlsplit(url).path).name
        mime = mimetypes.guess_type(name)[0] or "application/octet-stream"
        made, _ = sb.call("POST", "media", raw=data, headers={"Content-Type": mime,
                          "Content-Disposition": f'attachment; filename="{name}"'})
        sb.call("POST", f"media/{made['id']}", body={"alt_text": alt, "meta": {"mbc_live_source_url": url}})
        media_cache[url] = {"id": made["id"], "url": made["source_url"]}
        print(f"uploaded {name} (media {made['id']})")
        return media_cache[url]

    for p in selected:
        content = p["content"]["rendered"]
        mapping = {u: ensure_image(u)["url"] for u in inline_images(content)}
        body = {
            "title": plain(p["title"]["rendered"]),
            "slug": p["slug"],
            "status": "publish",
            "date": p["date"],
            "date_gmt": p["date_gmt"],
            "content": rewrite_images(content, mapping),
            "excerpt": plain(p["excerpt"]["rendered"]),
            "categories": [cat_map[live_cats[c].lower()] for c in p["categories"] if c in live_cats],
            "meta": {"mbc_demo_import": 1, "mbc_live_post_id": p["id"]},
        }
        f = feat.get(p["featured_media"])
        if f and f.get("source_url"):
            body["featured_media"] = ensure_image(f["source_url"], f.get("alt_text", ""))["id"]
        existing = sb_posts.get(p["id"])
        if existing:
            same = (existing["title"]["raw"] == body["title"] and existing["content"]["raw"] == body["content"]
                    and existing["excerpt"]["raw"] == body["excerpt"] and existing["date_gmt"] == body["date_gmt"]
                    and sorted(existing["categories"]) == sorted(body["categories"])
                    and existing["featured_media"] == body.get("featured_media", 0))
            if same:
                print(f"live #{p['id']}: unchanged (sandbox post {existing['id']})")
                continue
            sb.call("POST", f"posts/{existing['id']}", body=body)
            print(f"live #{p['id']}: updated sandbox post {existing['id']}")
        else:
            made, _ = sb.call("POST", "posts", body=body)
            print(f"live #{p['id']}: created sandbox post {made['id']}")
    print(f"\nDone. Live requests made: {live.request_count}")


if __name__ == "__main__":
    main()
