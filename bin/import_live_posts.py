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
import http.client
import json
import mimetypes
import re
import sys
import time
import urllib.parse
from html.parser import HTMLParser
from pathlib import Path

LIVE_BASE = "https://moortownbaptistchurch.org.uk"
LIVE_HOST = "moortownbaptistchurch.org.uk"
SANDBOX_HOST = "new.moortownbaptistchurch.org.uk"
USER_AGENT = "MBC-demo-import/1.0 (John, Moortown Baptist Church; read-only public GETs)"

POST_FIELDS = "id,date,date_gmt,slug,link,title,excerpt,content,categories,featured_media"
DEFAULT_COUNT = 10
MAX_PAGES = 5            # never scan more than 5 pages of 10 posts
LIVE_TIMEOUT = 60        # seconds to wait for a live response once connected
CONNECT_TIMEOUT = 8      # seconds per TCP connect attempt (the host sometimes stalls connects 20-68 s)
CONNECT_ATTEMPTS = 4     # connect attempts before giving up
CONNECT_BACKOFF = (1, 2, 4)  # seconds between connect attempts
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


class _QuickConnect(http.client.HTTPSConnection):
    """HTTPS connection with a short connect timeout and quick retries.

    From John's IP, TCP connects to the server intermittently stall for
    20-68 s (about 1 in 10). A fresh attempt almost always succeeds at once,
    so connect with a short timeout and retry instead of waiting it out. Once
    connected, the (longer) read timeout applies. Connections are reused
    (keep-alive) by the clients below.
    """

    read_timeout = LIVE_TIMEOUT

    def connect(self):
        last = None
        for attempt in range(CONNECT_ATTEMPTS):
            self.timeout = CONNECT_TIMEOUT
            try:
                super().connect()
                self.sock.settimeout(self.read_timeout)
                return
            except OSError as exc:  # includes socket timeouts
                last = exc
                self.close()
                if attempt < CONNECT_ATTEMPTS - 1:
                    time.sleep(CONNECT_BACKOFF[attempt])
        raise last


class LiveReader:
    """GET-only reader for the live site. Gentle: paced, retried, keep-alive."""

    def __init__(self):
        self._conns = {}
        self._last = 0.0
        self.request_count = 0

    def _pace(self):
        wait = LIVE_PAUSE - (time.monotonic() - self._last)
        if wait > 0:
            time.sleep(wait)
        self._last = time.monotonic()

    def _conn(self, netloc):
        if netloc not in self._conns:
            conn = _QuickConnect(netloc, timeout=CONNECT_TIMEOUT)
            conn.read_timeout = LIVE_TIMEOUT
            self._conns[netloc] = conn
        return self._conns[netloc]

    def _drop(self):
        for conn in self._conns.values():
            conn.close()
        self._conns.clear()

    def _once(self, url, hops=0):
        """One GET (following same-host redirects). Returns (status, headers, body)."""
        if host_of(url) != LIVE_HOST:
            raise LiveError(f"not the live host: {short(url)}")
        parts = urllib.parse.urlsplit(url)
        target = (parts.path or "/") + (("?" + parts.query) if parts.query else "")
        conn = self._conn(parts.netloc)
        for first_try in (True, False):  # one immediate retry if a kept-alive connection went stale
            try:
                conn.request("GET", target, headers={"User-Agent": USER_AGENT, "Accept": "*/*"})
                resp = conn.getresponse()
                body = resp.read()
                break
            except (http.client.RemoteDisconnected, BrokenPipeError, ConnectionResetError):
                conn.close()
                if not first_try:
                    raise
        headers = {k.lower(): v for k, v in resp.getheaders()}
        if resp.status in (301, 302, 303, 307, 308) and headers.get("location"):
            if hops >= 3:
                raise LiveError(f"too many redirects for {short(url)}")
            nxt = urllib.parse.urljoin(url, headers["location"])
            if host_of(nxt) != LIVE_HOST:
                raise LiveError(f"refusing redirect to another host: {short(nxt)}")
            return self._once(nxt, hops + 1)
        return resp.status, headers, body

    def _fetch(self, url):
        if host_of(url) != LIVE_HOST:
            raise LiveError(f"not the live host: {short(url)}")
        reason = "unknown"
        for attempt in range(LIVE_RETRIES + 1):
            self._pace()
            self.request_count += 1
            try:
                status, headers, body = self._once(url)
                if status < 400:
                    return body, headers
                if status < 500 and status != 429:
                    raise LiveError(f"HTTP {status} for {short(url)}")
                reason = f"HTTP {status}"
            except (http.client.HTTPException, OSError) as exc:  # timeouts are transient
                reason = str(exc) or type(exc).__name__
                self._drop()
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
        self._netloc = urllib.parse.urlsplit(base).netloc
        self._conn = None
        token = base64.b64encode(f"{env['WP_USER']}:{env['WP_APP_PASSWORD']}".encode()).decode()
        self._auth = f"Basic {token}"
        self._last_write = 0.0

    def _connection(self):
        if self._conn is None:
            self._conn = _QuickConnect(self._netloc, timeout=CONNECT_TIMEOUT)
            self._conn.read_timeout = SANDBOX_TIMEOUT
        return self._conn

    def _reset(self):
        if self._conn is not None:
            self._conn.close()
        self._conn = None

    def fetch_public(self, path, retries=2):
        """GET a public sandbox page as a visitor would (no credentials sent). Returns (status, body text)."""
        if not path.startswith("/"):
            sys.exit(f"fetch_public needs a site path, got {path!r}")
        for attempt in range(retries + 1):
            try:
                conn = self._connection()
                try:
                    conn.request("GET", path, headers={"User-Agent": USER_AGENT})
                    resp = conn.getresponse()
                except (http.client.RemoteDisconnected, BrokenPipeError, ConnectionResetError):
                    conn.close()
                    conn.request("GET", path, headers={"User-Agent": USER_AGENT})
                    resp = conn.getresponse()
                return resp.status, resp.read().decode("utf-8", "replace")
            except (http.client.HTTPException, OSError) as exc:
                self._reset()
                if attempt >= retries:
                    raise
                time.sleep(2)

    def call(self, method, route, params=None, body=None, raw=None, headers=None, retries=0):
        path = f"/wp-json/wp/v2/{route}"
        if params:
            path += "?" + urllib.parse.urlencode(params)
        data = raw if raw is not None else (json.dumps(body).encode() if body is not None else None)
        hdrs = {"Authorization": self._auth, "User-Agent": USER_AGENT}
        if raw is None and body is not None:
            hdrs["Content-Type"] = "application/json"
        hdrs.update(headers or {})
        safe = method in ("GET", "OPTIONS")
        if not safe:
            wait = SANDBOX_WRITE_PAUSE - (time.monotonic() - self._last_write)
            if wait > 0:
                time.sleep(wait)
            self._last_write = time.monotonic()
        for attempt in range((retries if safe else 0) + 1):
            try:
                conn = self._connection()
                try:
                    conn.request(method, path, body=data, headers=hdrs)
                    resp = conn.getresponse()
                except (http.client.RemoteDisconnected, BrokenPipeError, ConnectionResetError):
                    conn.close()
                    if not safe:  # a write may have reached the server: never resend blindly
                        raise
                    conn.request(method, path, body=data, headers=hdrs)
                    resp = conn.getresponse()
                payload = resp.read()
                break
            except (http.client.HTTPException, OSError) as exc:
                self._reset()
                if attempt >= (retries if safe else 0):
                    sys.exit(f"Sandbox network error on {method} {route}: {exc or type(exc).__name__}"
                             + ("" if safe else " (the script is idempotent: re-run to continue)"))
                time.sleep(2)
        if resp.status >= 400:
            sys.exit(f"Sandbox HTTP {resp.status} on {method} {route}: {payload.decode(errors='replace')[:300]}")
        return json.loads(payload), {k.lower(): v for k, v in resp.getheaders()}


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


STYLE_ATTR_RE = re.compile(r'\sstyle\s*=\s*"([^"]*)"', re.I)
FONT_TAG_RE = re.compile(r"<font\b[^>]*>", re.I)
FONT_ATTR_RE = re.compile(r'\s(?:color|face|size|bgcolor)\s*=\s*"[^"]*"', re.I)
DROPPED_STYLES = {"color", "background", "background-color", "font-family", "font-size", "line-height", "text-shadow"}


def clean_inline_styles(content):
    """Remove colour, background and font declarations from inline styles.

    Imported text must take its colours and fonts from the theme tokens (and
    so from every look), not from hard-coded values copied from the old site.
    Other declarations (font-weight, text-align, ...) are kept.
    """
    def fix(match):
        kept = [d.strip() for d in match.group(1).split(";")
                if d.strip() and d.split(":")[0].strip().lower() not in DROPPED_STYLES]
        return f' style="{"; ".join(kept)}"' if kept else ""

    content = STYLE_ATTR_RE.sub(fix, content)
    return FONT_TAG_RE.sub(lambda m: FONT_ATTR_RE.sub("", m.group(0)), content)


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

    def classdef(name):
        return next(n for n in ast.walk(tree) if isinstance(n, ast.ClassDef) and n.name == name)

    results = []

    def check(ok, text):
        results.append(ok)
        print(("PASS " if ok else "FAIL ") + text)

    calls = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and isinstance(n.func, ast.Attribute)]
    # 1. Anything that sends data over a connection lives in the two clients.
    sends = [n for n in calls if n.func.attr in ("request", "putrequest", "urlopen", "build_opener", "create_connection", "send", "sendall")]
    check(len(sends) >= 2 and all(cls_of(n) in ("LiveReader", "SandboxWriter") for n in sends),
          f"requests are only sent from LiveReader or SandboxWriter ({len(sends)} call sites)")
    # 2. Every request the live reader sends is a literal GET.
    live_sends = [n for n in sends if cls_of(n) == "LiveReader"]
    check(bool(live_sends) and all(n.func.attr == "request" and n.args and isinstance(n.args[0], ast.Constant)
                                   and n.args[0].value == "GET" for n in live_sends),
          f"every LiveReader request is method 'GET' as a literal ({len(live_sends)} call site)")
    # 3. connect() is only ever called by the shared connection class.
    connects = [n for n in calls if n.func.attr == "connect"]
    check(all(cls_of(n) == "_QuickConnect" for n in connects), "connect() is only called inside _QuickConnect")
    # 4. No credentials near the live client or the shared connection class.
    bad = []
    for cname in ("LiveReader", "_QuickConnect"):
        for n in ast.walk(classdef(cname)):
            text = n.value if isinstance(n, ast.Constant) and isinstance(n.value, str) else \
                (n.id if isinstance(n, ast.Name) else (n.attr if isinstance(n, ast.Attribute) else ""))
            if re.search(r"authorization|password|token|wp_user|cookie|_auth", text, re.I):
                bad.append((cname, text))
    check(not bad, f"no credential or cookie references in the live client{(' ' + str(bad)) if bad else ''}")
    # 5. LiveReader exposes only get_json and get_bytes.
    public = {f.name for f in classdef("LiveReader").body if isinstance(f, ast.FunctionDef) and not f.name.startswith("_")}
    check(public == {"get_json", "get_bytes"}, f"LiveReader public methods are {sorted(public)}")
    # 6. The sandbox client never mentions the live host.
    names = {n.id for n in ast.walk(classdef("SandboxWriter")) if isinstance(n, ast.Name)}
    check(not names & {"LIVE_BASE", "LIVE_HOST", "LiveReader"}, "SandboxWriter does not reference the live host or reader")
    # 7. Only whitelisted imports (no urllib.request, no sockets, no subprocess).
    allowed = {"argparse", "ast", "base64", "html", "html.parser", "http.client", "json", "mimetypes", "pathlib",
               "re", "sys", "time", "urllib.parse"}
    imported = {a.name for n in ast.walk(tree) if isinstance(n, ast.Import) for a in n.names}
    imported |= {n.module for n in ast.walk(tree) if isinstance(n, ast.ImportFrom) and n.module}
    check(imported <= allowed, f"imports limited to the whitelist (extra: {sorted(imported - allowed) or 'none'})")
    # 8. Runtime guards refuse before any network use.
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


def build_body(p, live_cats, cat_map, feat, media):
    """The sandbox post body for live post p, or None if it needs a category or image
    that is not on the sandbox yet. media maps live URL -> {"id", "url"}."""
    content = p["content"]["rendered"]
    mapping = {}
    for url in inline_images(content):
        if url not in media:
            return None
        mapping[url] = media[url]["url"]
    categories = []
    for cid in p["categories"]:
        name = live_cats.get(cid)
        if name is not None:
            if name.lower() not in cat_map:
                return None
            categories.append(cat_map[name.lower()])
    body = {
        "title": plain(p["title"]["rendered"]),
        "slug": p["slug"],
        "status": "publish",
        "date": p["date"],
        "date_gmt": p["date_gmt"],
        "content": rewrite_images(content, mapping),
        "excerpt": plain(p["excerpt"]["rendered"]),
        "categories": categories,
        "meta": {"mbc_demo_import": 1, "mbc_live_post_id": p["id"]},
    }
    f = feat.get(p["featured_media"])
    if f and f.get("source_url"):
        if f["source_url"] not in media:
            return None
        body["featured_media"] = media[f["source_url"]]["id"]
    return body


def matches_existing(existing, body):
    return (existing["title"]["raw"] == body["title"] and existing["content"]["raw"] == body["content"]
            and existing["excerpt"]["raw"] == body["excerpt"] and existing["date_gmt"] == body["date_gmt"]
            and sorted(existing["categories"]) == sorted(body["categories"])
            and existing["featured_media"] == body.get("featured_media", 0))


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
        p["content"]["rendered"] = clean_inline_styles(p["content"]["rendered"]) if not (is_elementor(p) and args.unwrap_elementor) else p["content"]["rendered"]
        if is_elementor(p) and args.unwrap_elementor:
            p["content"]["rendered"], counts = unwrap_elementor(p["content"]["rendered"])
            for k, v in counts.items():
                widget_counts[k] = widget_counts.get(k, 0) + v
            p["content"]["rendered"] = clean_inline_styles(p["content"]["rendered"])
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
    media = {u: {"id": m["id"], "url": m["source_url"]} for u, m in sb_media.items()}
    new_cats, new_images, report = set(), set(), []
    counts = {"create": 0, "update": 0, "unchanged": 0}
    for n, p in enumerate(selected, 1):
        title = plain(p["title"]["rendered"])
        names = [live_cats.get(c, f"#{c}") for c in p["categories"]]
        imgs = inline_images(p["content"]["rendered"])
        fsrc = feat.get(p["featured_media"], {}).get("source_url")
        unseen = [u for u in ([fsrc] if fsrc else []) + imgs if u not in sb_media]
        new_cats |= {c for c in names if c.lower() not in sb_cats}
        new_images |= set(unseen)
        links = live_links(p["content"]["rendered"])
        existing = sb_posts.get(p["id"])
        body = build_body(p, live_cats, sb_cats, feat, media)
        if not existing:
            action = "create"
        elif body is not None and matches_existing(existing, body):
            action = "unchanged"
        else:
            action = "update"
        counts[action] += 1
        print(f"[{n}] live #{p['id']}  {p['date'][:10]}  {title[:60]!r}  -> {action}{'  [Elementor, unwrapped]' if p.get('_unwrapped') else ''}")
        print(f"     categories: " + ", ".join(f"{c}{'' if c.lower() in sb_cats else ' (new)'}" for c in names or ["(none)"]))
        print(f"     featured: {'yes' if fsrc else 'no'}{' (to upload)' if fsrc and fsrc not in sb_media else ''}; "
              f"inline images: {len(imgs)} ({len([u for u in imgs if u not in sb_media])} to upload); live-domain links: {len(links)}")
        if links:
            report.append((p["id"], title, links))
    print(f"\nPlan: {counts['create']} to create, {counts['update']} to update, {counts['unchanged']} unchanged")
    print(f"Selected {len(selected)} post(s); categories to create: {sorted(new_cats) or 'none'}; images to upload: {len(new_images)}")
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
        for url in inline_images(p["content"]["rendered"]):
            ensure_image(url)
        f = feat.get(p["featured_media"])
        if f and f.get("source_url"):
            ensure_image(f["source_url"], f.get("alt_text", ""))
        body = build_body(p, live_cats, cat_map, feat, media_cache)
        existing = sb_posts.get(p["id"])
        if existing:
            if matches_existing(existing, body):
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
