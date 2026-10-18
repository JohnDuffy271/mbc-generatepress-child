#!/usr/bin/env python3
"""Import posts from the LIVE site into the SANDBOX, on demand.

Read-only towards live: every live request goes through LiveReader (import_live_demo_posts.py),
which can only GET, sends no credentials, follows redirects only within the live host and fetches
only the public REST API and /wp-content/uploads/ files. Live URLs are built inside that class,
never on the command line. All writes go through SandboxWriter (sandbox host only).

Dry run by default: prints a table (live id, date, title, categories, images, action, warnings)
and writes a report. --apply writes to the sandbox. --self-test checks this file statically.

Selection: --since YYYY-MM-DD, --until YYYY-MM-DD, --ids 1,2, --category slug[,slug], --limit N
(default 50; 0 = no limit). Posts the sandbox already holds are skipped unless live changed them
(live modified date is stored as meta); --force re-imports.

Content is stored as clean HTML (WordPress's Classic content, which the block editor opens in a
Classic block) with YouTube embeds as core/embed blocks. It is NOT converted to core blocks: the
cleaned HTML round-trips exactly and stays diffable; paragraphs, headings and lists convert in
one click in the editor if ever wanted.

Needs Pillow for images (apply only): run with ~/.venvs/mbc-test/bin/python.

Usage:
  ~/.venvs/mbc-test/bin/python bin/import_live_posts.py --self-test
  ~/.venvs/mbc-test/bin/python bin/import_live_posts.py --since 2026-07-18          # dry run
  ~/.venvs/mbc-test/bin/python bin/import_live_posts.py --ids 123,456 --apply
"""
import argparse
import ast
import datetime as dt
import hashlib
import html
import io
import json
import mimetypes
import re
import sys
import urllib.parse
from html.parser import HTMLParser
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import import_live_demo_posts as base  # noqa: E402  (LiveReader, SandboxWriter and the shared helpers)
from import_live_demo_posts import LiveError, LiveReader, SandboxWriter, host_of, load_env, plain, short, unwrap_elementor  # noqa: E402

LIVE_HOST = base.LIVE_HOST
_QuickConnect = base._QuickConnect  # re-exported so the check-script logger can hook connect retries
CACHE_DIR = Path.home() / "src" / "mbc-images" / "live-cache"
REPORT_DIR = Path.home() / "src" / "mbc-tests" / "logs"
POST_FIELDS = ("id,date,date_gmt,modified_gmt,slug,link,status,title,excerpt,content,categories,tags,"
               "featured_media,author,_links,_embedded")
EMBED = "author,wp:featuredmedia,wp:term"

AUTHORS_FILE = Path(__file__).resolve().parent / "import_authors.json"
PREVIEW_DIR = CACHE_DIR / "alt-previews"
BOLD_UNWRAP_RATIO = 0.8  # more than this share of a post's text inside <strong> and the bold is dropped
MAX_SIDE = 1500          # post preset (docs/image-standards.md): longest side, never upscaled
CAP_BYTES = 250 * 1000   # post preset cap
QUALITIES = (80, 77, 74, 72, 70)  # start at 80, lower only if over the cap, never below 70
FLOOR_SIDE = 1408        # 2x the widest post column (704px): never shrink below this
OVERSIZED_CHARS = 60000
DEFAULT_LIMIT = 50

# The live categories (docs/newsletter/category-map.md) and the two renames (the minister's blog and message).
KNOWN_LIVE_SLUGS = {"news", "notices", "haddons-blog", "grahams-blog", "children", "romania", "news-archive", "mission",
                    "china-mission", "grahams-message", "choir", "a-short-history-of-mbc", "cass-blog", "covid19",
                    "church-at-home", "church-at-home-past-services", "nathans-blog"}
RENAMES = {"grahams-blog": ("ministers-blog", "Minister's Blog"),
           "grahams-message": ("ministers-message", "Minister's Message")}
# Media the demo import uploaded (CLAUDE.md cutover list): never reused, they are deleted at cutover.
DEMO_MEDIA = {670, 671, 672, 673, 675, 676, 678, 680, 681, 683, 684, 686, 688, 689, 692, 693, 695, 696, 697}

READ_MORE_RE = re.compile(r"\s*(?:…|\.\.\.|\[…\])?\s*Read\s+more\s*(?:…|\.\.\.|»)?\s*$", re.I)
YT_ID = r"[\w-]{6,}"
YT_IFRAME_RE = re.compile(r"""<iframe\b[^>]*?\bsrc\s*=\s*["'](?:https?:)?//(?:www\.)?youtube(?:-nocookie)?\.com/embed/(%s)[^"']*["'][^>]*>\s*</iframe>""" % YT_ID, re.I | re.S)
YT_URL_RE = re.compile(r"https?://(?:www\.)?(?:youtube\.com/watch\?(?:[^\s<\"']*&)?v=|youtu\.be/)(%s)[^\s<\"']*" % YT_ID, re.I)
YT_PARA_RE = re.compile(r"<p>\s*(?:<a\b[^>]*>)?\s*(%s)\s*(?:</a>)?\s*</p>" % YT_URL_RE.pattern, re.I)
SIZE_SUFFIX_RE = re.compile(r"-(?:scaled|\d{2,5}x\d{2,5})(?=\.[a-z0-9]+$)", re.I)


# ============================================================ content cleaning

VOID = {"br", "hr", "img", "input", "meta", "link", "source", "wbr", "embed", "area", "col", "param", "track"}
DROP = {"script", "style", "noscript", "template", "svg", "form", "button", "select", "textarea", "head", "title",
        "canvas", "audio", "video", "object", "embed", "iframe", "map"}
BLOCK = {"p", "h2", "h3", "h4", "h5", "h6", "ul", "ol", "blockquote", "hr", "table", "figure", "pre", "dl"}
BREAKERS = {"div", "section", "article", "header", "footer", "main", "aside", "nav", "center", "address", "fieldset", "form"}
INLINE = {"a", "strong", "em", "br", "img", "sup", "sub", "code"}
KEEP = BLOCK | INLINE | {"li", "thead", "tbody", "tfoot", "tr", "th", "td", "figcaption", "dt", "dd"}
MAP = {"b": "strong", "i": "em", "h1": "h2"}
ATTRS = {"a": ("href",), "img": ("src", "alt", "width", "height"), "th": ("colspan", "rowspan"), "td": ("colspan", "rowspan"), "ol": ("start",)}


class Node:
    __slots__ = ("tag", "attrs", "kids")

    def __init__(self, tag, attrs=None):
        self.tag, self.attrs, self.kids = tag, dict(attrs or {}), []


class _Builder(HTMLParser):
    """Forgiving HTML to a small tree. Dropped elements (script, iframe, ...) are skipped with their content."""

    def __init__(self):
        super().__init__(convert_charrefs=False)
        self.root = Node("#root")
        self.stack = [self.root]
        self.skip = None   # (tag, depth) while inside a dropped element
        self.dropped = []  # (tag, src) of dropped iframes/videos/objects, for the report

    def _add(self, node):
        self.stack[-1].kids.append(node)

    def handle_starttag(self, tag, attrs):
        a = {k: (v or "") for k, v in attrs}
        if self.skip:
            if tag == self.skip[0] and tag not in VOID:
                self.skip[1] += 1
            return
        if tag in DROP:
            if tag in ("iframe", "video", "object", "embed", "audio"):
                self.dropped.append((tag, a.get("src", "")))
            if tag not in VOID:
                self.skip = [tag, 1]
            return
        # implied end tags (a new <p> or <li> closes an open one)
        if tag in BLOCK | {"li"} and self.stack[-1].tag == ("li" if tag == "li" else "p"):
            self.stack.pop()
        node = Node(tag, a)
        self._add(node)
        if tag not in VOID:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        if tag not in VOID and not self.skip and self.stack[-1].tag == tag:
            self.stack.pop()

    def handle_endtag(self, tag):
        if self.skip:
            if tag == self.skip[0]:
                self.skip[1] -= 1
                if self.skip[1] <= 0:
                    self.skip = None
            return
        for i in range(len(self.stack) - 1, 0, -1):
            if self.stack[i].tag == tag:
                del self.stack[i:]
                return

    def handle_data(self, data):
        if not self.skip:
            self._add(data)

    def handle_entityref(self, name):
        if not self.skip:
            self._add(f"&{name};")

    def handle_charref(self, name):
        if not self.skip:
            self._add(f"&#{name};")


def _esc(value):
    return html.escape(html.unescape(value), quote=True)


def _has_content(inner):
    text = re.sub(r"<(?!img\b)[^>]+>", "", inner)
    text = html.unescape(text).replace("\xa0", " ").strip()
    return bool(text) or "<img" in inner


def _flatten(node):
    """Items of a node's children: ('inline', html) or ('block', html); wrapper elements are looked through."""
    items = []
    for kid in node.kids:
        if isinstance(kid, str):
            items.append(("inline", re.sub(r"[ \t\r\n]+", " ", kid)))
            continue
        tag = MAP.get(kid.tag, kid.tag)
        if tag not in KEEP:
            wrapper = kid.tag in BREAKERS  # a div or section ends the paragraph before it and starts a new one after it
            if wrapper:
                items.append(("break", ""))
            items.extend(_flatten(kid))  # div, span, section, font, ...: keep the content, lose the wrapper
            if wrapper:
                items.append(("break", ""))
        else:
            rendered = _render(kid, tag)
            if rendered:
                items.append(("block" if tag in BLOCK | {"li", "thead", "tbody", "tfoot", "tr", "th", "td", "figcaption", "dt", "dd"} else "inline", rendered))
    return items


def _paragraphs(items):
    """Blocks as they are; runs of inline items become <p>."""
    out, run = [], []

    def flush():
        inner = "".join(run).strip()
        run.clear()
        # a picture sits in its own paragraph, whatever text surrounds it in the source
        for piece in re.split(r"(<a\b[^>]*>\s*<img\b[^>]*>\s*</a>|<img\b[^>]*>)", inner):
            piece = re.sub(r"^(?:\s|<br>)+|(?:\s|<br>)+$", "", piece)
            if _has_content(piece):
                out.append(f"<p>{piece}</p>")

    for kind, text in items:
        if kind == "inline":
            run.append(text)
        elif kind == "break":
            flush()
        else:
            flush()
            out.append(text)
    flush()
    return "\n".join(out)


def _clean_href(value):
    href = html.unescape(value).strip()
    if re.match(r"^\s*(javascript|data|vbscript):", href, re.I):
        return ""
    return href


def _render(node, tag):
    if tag == "br":
        return "<br>"
    if tag == "hr":
        return "<hr>"
    if tag == "img":
        a = node.attrs
        src = a.get("src", "")
        if src.startswith("data:") or not src:
            src = a.get("data-src") or a.get("data-lazy-src") or a.get("data-orig-src") or ""
        if not src or src.startswith("data:"):
            return ""
        parts = [f'src="{_esc(src)}"', f'alt="{_esc(a.get("alt", ""))}"']
        for k in ("width", "height"):
            if re.fullmatch(r"\d{1,5}", a.get(k, "")):
                parts.append(f'{k}="{a[k]}"')
        return "<img " + " ".join(parts) + ">"
    inner_items = _flatten(node)
    blocks = [text for kind, text in inner_items if kind == "block"]
    if blocks and tag in ("li", "td", "th", "dd") and all(b.startswith(("<ul", "<ol")) for b in blocks):
        inner = "".join(text for _, text in inner_items).strip()  # text plus a nested list: no <p> wrapper
    elif blocks and tag in ("li", "blockquote", "td", "th", "figure", "dd"):
        inner = _paragraphs(inner_items)
    else:
        inner = "".join(text for _, text in inner_items).strip()
    if tag in ("ul", "ol", "table", "thead", "tbody", "tfoot", "tr", "dl"):
        inner = "".join(text for _, text in inner_items if text.strip())
    if not _has_content(inner) and tag not in ("table", "thead", "tbody", "tfoot", "tr", "td", "th"):
        return ""
    attrs = ""
    if tag == "a":
        href = _clean_href(node.attrs.get("href", ""))
        if not href:
            return inner  # an anchor without a usable link: keep the text
        attrs = f' href="{_esc(href)}"'
        if node.attrs.get("target", "").lower() == "_blank":
            attrs += ' target="_blank" rel="noopener noreferrer"'
    else:
        for k in ATTRS.get(tag, ()):
            v = node.attrs.get(k, "")
            if v and re.fullmatch(r"[\w .-]{1,20}", v):
                attrs += f' {k}="{_esc(v)}"'
    return f"<{tag}{attrs}>{inner}</{tag}>"


def youtube_block(video_id):
    url = f"https://www.youtube.com/watch?v={video_id}"
    return ('<!-- wp:embed {"url":"%s","type":"video","providerNameSlug":"youtube","responsive":true,'
            '"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->\n'
            '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">'
            '<div class="wp-block-embed__wrapper">\n%s\n</div></figure>\n<!-- /wp:embed -->') % (url, url)


def clean_content(raw):
    """Live rendered HTML to clean HTML. Returns {"html", "widgets", "dropped", "empty"}.

    Keeps headings, paragraphs, lists, links, images, simple tables and quotes; YouTube iframes, Elementor
    video widgets and bare YouTube links become core/embed blocks. Removes Elementor wrappers and classes,
    ids, inline styles, data attributes, scripts, empty containers and other embeds (reported in "dropped").
    """
    raw = raw or ""
    widgets = {}
    if re.search(r"elementor-|data-elementor", raw, re.I):
        # Elementor video widgets keep the YouTube link in data-settings, outside the widget container:
        # put it into the container as a paragraph so it survives the unwrap (and becomes an embed below).
        pieces, pos = [], 0
        for m in re.finditer(r"<div\b[^>]*elementor-widget-video[^>]*>", raw):
            settings = html.unescape((re.search(r'data-settings="([^"]*)"', m.group(0)) or [None, ""])[1]).replace("\\/", "/")
            url = re.search(r'"youtube_url"\s*:\s*"([^"]+)"', settings)
            box = raw.find('<div class="elementor-widget-container">', m.end())
            if url and box != -1:
                end = box + len('<div class="elementor-widget-container">')
                pieces.append(raw[pos:end] + f"<p>{url.group(1)}</p>")
                pos = end
        raw = "".join(pieces) + raw[pos:]
        raw, widgets = unwrap_elementor(raw)
    raw = YT_IFRAME_RE.sub(lambda m: f"<p>https://www.youtube.com/watch?v={m.group(1)}</p>", raw)
    parser = _Builder()
    parser.feed(raw)
    parser.close()
    out = _paragraphs(_flatten(parser.root))
    out = YT_PARA_RE.sub(lambda m: "\n" + youtube_block(m.group(2)) + "\n", out)
    out = re.sub(r"\n{3,}", "\n\n", out).strip()
    out, share = unwrap_bold(out)
    dropped = [(t, s) for t, s in parser.dropped if not (t == "iframe" and "youtube" in s)]
    return {"html": out, "widgets": widgets, "dropped": dropped, "empty": not out, "bold_unwrapped": share if share > BOLD_UNWRAP_RATIO else 0.0}


def bold_share(clean_html):
    """Share (0-1) of the text that sits inside <strong>. Embed blocks and tags do not count."""
    body = re.sub(r"<!-- wp:embed.*?<!-- /wp:embed -->", "", clean_html, flags=re.S)

    def length(fragment):
        return len(re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", fragment))).strip())
    total = length(body)
    bold = sum(length(m) for m in re.findall(r"<strong>(.*?)</strong>", body, flags=re.S))
    return bold / total if total else 0.0


def unwrap_bold(clean_html):
    """If most of the text is bold (a post pasted in bold), drop the bold; genuine partial bold stays."""
    share = bold_share(clean_html)
    if share > BOLD_UNWRAP_RATIO:
        return re.sub(r"</?strong>", "", clean_html), share
    return clean_html, share


def normalise_media_url(url):
    """The original file's URL: no -scaled and no -WxH size suffix, no query or fragment."""
    u = urllib.parse.urlsplit(html.unescape(url))
    path = SIZE_SUFFIX_RE.sub("", u.path)
    return urllib.parse.urlunsplit((u.scheme or "https", u.netloc, path, "", ""))


def live_upload(url):
    u = urllib.parse.urlsplit(html.unescape(url))
    return host_of(url) == LIVE_HOST and u.path.startswith("/wp-content/uploads/")


IMG_TAG_RE = re.compile(r"<img\b[^>]*>", re.I)


def content_images(clean_html):
    """[{src, alt, key}] for each distinct image (key = normalised original URL), in order; external ones flagged."""
    seen, out = set(), []
    for tag in IMG_TAG_RE.findall(clean_html):
        src = html.unescape(re.search(r'src="([^"]*)"', tag).group(1))
        alt = html.unescape((re.search(r'alt="([^"]*)"', tag) or [None, ""])[1])
        key = normalise_media_url(src) if live_upload(src) else src
        if key not in seen:
            seen.add(key)
            out.append({"src": src, "alt": alt, "key": key, "live": live_upload(src)})
    return out


HREF_RE = re.compile(r'<a\b[^>]*?\bhref="([^"]*)"', re.I)


def content_links(clean_html):
    return [html.unescape(h) for h in HREF_RE.findall(clean_html)]


def rewrite_content(clean_html, media, post_by_slug, post_by_id, alts=None):
    """Sandbox version of the content. media: key -> {"id","url","width","height"}; post_by_*: live slug/id -> sandbox path;
    alts: key -> alt text used where the live image has none.
    Returns (html, live_links_left) where live_links_left are hrefs still pointing at live."""
    def fix_img(m):
        tag = m.group(0)
        src = html.unescape(re.search(r'src="([^"]*)"', tag).group(1))
        key = normalise_media_url(src) if live_upload(src) else src
        item = media.get(key)
        if not item:
            return tag
        alt = (re.search(r'alt="([^"]*)"', tag) or [None, ""])[1]
        if not alt.strip() and alts and alts.get(key):
            alt = _esc(alts[key])
        size = f' width="{item["width"]}" height="{item["height"]}"' if item.get("width") and item.get("height") else ""
        return f'<img class="wp-image-{item["id"]}" src="{_esc(item["url"])}" alt="{alt}"{size}>'
    out = IMG_TAG_RE.sub(fix_img, clean_html)
    left = []

    def fix_link(m):
        href = html.unescape(m.group(1))
        if host_of(href) != LIVE_HOST:
            return m.group(0)
        u = urllib.parse.urlsplit(href)
        key = normalise_media_url(href)
        if key in media:
            return m.group(0).replace(m.group(1), _esc(media[key]["url"]))
        slug = u.path.strip("/").split("/")[-1] if u.path.strip("/") else ""
        q = urllib.parse.parse_qs(u.query)
        target = post_by_slug.get(slug) if slug and "/" not in u.path.strip("/") else None
        if not target and q.get("p", [""])[0].isdigit():
            target = post_by_id.get(int(q["p"][0]))
        if not target and u.path in ("", "/") and not u.query:
            target = "/"
        if target:
            return m.group(0).replace(m.group(1), _esc(target + (("#" + u.fragment) if u.fragment else "")))
        left.append(href)
        return m.group(0)
    out = re.sub(r'<a\b[^>]*?\bhref="([^"]*)"', fix_link, out, flags=re.I)
    return out, left


def clean_excerpt(rendered):
    text = html.unescape(re.sub(r"<[^>]+>", " ", rendered or ""))
    text = re.sub(r"\s+", " ", text.replace("\xa0", " ")).strip()
    text = READ_MORE_RE.sub("", text).strip()
    return text


# ============================================================ mapping

def map_category(live_slug, live_name):
    """(sandbox slug, sandbox name, known) for a live category."""
    if live_slug in RENAMES:
        return RENAMES[live_slug][0], RENAMES[live_slug][1], True
    return live_slug, html.unescape(live_name), live_slug in KNOWN_LIVE_SLUGS


def load_authors(path=None):
    """bin/import_authors.json: {"map": {live author slug: sandbox user id}, "fallback": sandbox user slug}."""
    try:
        data = json.loads((path or AUTHORS_FILE).read_text())
    except (OSError, ValueError):
        return {"map": {}, "fallback": "mbc"}
    return {"map": {str(k).lower(): int(v) for k, v in (data.get("map") or {}).items()}, "fallback": str(data.get("fallback") or "mbc")}


def map_author(live_author, users, authors=None):
    """Sandbox user id for a live author: the mapping file, then slug, then name; else the fallback user; else None.
    Returns (id, how) with how in mapped, matched, mbc, none."""
    authors = authors or {"map": {}, "fallback": "mbc"}
    name = html.unescape((live_author or {}).get("name", "")).strip().lower()
    slug = (live_author or {}).get("slug", "").lower()
    ids = {u["id"] for u in users}
    if slug in authors["map"] and authors["map"][slug] in ids:
        return authors["map"][slug], "mapped"
    for u in users:
        if slug and u["slug"].lower() == slug:
            return u["id"], "matched"
    for u in users:
        if name and u["name"].strip().lower() == name:
            return u["id"], "matched"
    for u in users:
        if u["slug"].lower() == authors["fallback"].lower():
            return u["id"], "mbc"
    return None, "none"


# ============================================================ images

def process_image(data, post_date):
    """Downscale per the post preset. Returns (bytes, extension, mime, notes). Needs Pillow."""
    try:
        from PIL import Image, ImageOps
    except ImportError:
        sys.exit("Pillow is needed to process images: run with ~/.venvs/mbc-test/bin/python")
    notes = []
    img = Image.open(io.BytesIO(data))
    if img.format in ("GIF", "WEBP", "PNG") and getattr(img, "is_animated", False):  # (an MPO camera JPEG also has "frames": only the first is used)
        notes.append("animated image kept as it is")
        return data, "." + (img.format or "gif").lower(), Image.MIME.get(img.format, "image/gif"), notes
    img = ImageOps.exif_transpose(img)
    alpha = img.mode in ("RGBA", "LA") or (img.mode == "P" and "transparency" in img.info)
    if alpha and img.convert("RGBA").getchannel("A").getextrema()[0] == 255:
        alpha = False  # an alpha channel that is fully opaque is just a photo or graphic: treat it as one
    w, h = img.size
    if max(w, h) > MAX_SIDE:
        scale = MAX_SIDE / max(w, h)
        img = img.resize((max(1, round(w * scale)), max(1, round(h * scale))), Image.LANCZOS)
    else:
        notes.append(f"kept at {w}x{h} (never upscaled)")
    buf = io.BytesIO()
    if alpha:
        img.convert("RGBA").save(buf, "PNG", optimize=True)
        ext, mime = ".png", "image/png"
        if buf.tell() > CAP_BYTES:  # real transparency, too heavy as PNG: lossy WebP keeps the alpha
            buf = io.BytesIO()
            img.convert("RGBA").save(buf, "WEBP", quality=80, method=6)
            ext, mime = ".webp", "image/webp"
            notes.append("transparent PNG over the cap saved as WebP")
    else:
        img = img.convert("RGB")
        ext, mime = ".jpg", "image/jpeg"
        for q in QUALITIES:
            buf = io.BytesIO()
            img.save(buf, "JPEG", quality=q, optimize=True, progressive=True, subsampling=2)
            if buf.tell() <= CAP_BYTES:
                break
        if buf.tell() > CAP_BYTES and max(img.size) > FLOOR_SIDE:
            # Still over the cap at quality 70: quality is never lowered further, so shrink toward the 2x floor
            # (704px column x 2) and try the ladder again.
            scale = FLOOR_SIDE / max(img.size)
            img = img.resize((max(1, round(img.size[0] * scale)), max(1, round(img.size[1] * scale))), Image.LANCZOS)
            notes.append(f"shrunk to {img.size[0]}x{img.size[1]} (the 2x floor) to get under the cap")
            for q in QUALITIES:
                buf = io.BytesIO()
                img.save(buf, "JPEG", quality=q, optimize=True, progressive=True, subsampling=2)
                if buf.tell() <= CAP_BYTES:
                    break
    if buf.tell() > CAP_BYTES:
        notes.append(f"OVER THE CAP: {buf.tell() // 1000} kB")
    check = Image.open(io.BytesIO(buf.getvalue()))
    leaked = [k for k in ("exif", "icc_profile", "xmp", "comment") if check.info.get(k)]
    if leaked:
        raise RuntimeError(f"metadata left in processed image: {leaked}")
    return buf.getvalue(), ext, mime, notes


def media_filename(key, ext, post_date):
    stem = Path(urllib.parse.urlsplit(key).path).stem
    stem = re.sub(r"[^a-z0-9]+", "-", stem.lower()).strip("-")[:60] or "image"
    return f"{stem}-{post_date.replace('-', '')}{ext}"


class Importer:
    def __init__(self, live, sb, apply, force):
        self.live, self.sb, self.apply, self.force = live, sb, apply, force
        self.media = {}      # key -> {"id","url","width","height"}
        self.broken = []     # (post id, url, reason)
        self.image_notes = []
        self.auto_alts = []  # (media id, live post id, drafted alt text) for review in the consent pass

    def download(self, key, src):
        CACHE_DIR.mkdir(parents=True, exist_ok=True)
        candidates = [key] + ([src] if src != key else [])
        last = None
        for url in candidates:
            cached = CACHE_DIR / (hashlib.sha1(url.encode()).hexdigest()[:16] + "-" + Path(urllib.parse.urlsplit(url).path).name)
            if cached.exists():
                return cached.read_bytes()
            try:
                data = self.live.get_bytes(url)
                cached.write_bytes(data)
                return data
            except LiveError as exc:
                last = exc
        raise last

    def ensure_media(self, key, src, alt, post_date, post_id, auto=False):
        """The sandbox attachment for a live image (uploaded once). `auto` marks an alt text drafted by Claude (meta _mbc_alt_auto)."""
        if key in self.media:
            item = self.media[key]
            if alt and not (item.get("alt") or "").strip():  # reused attachment with no alt yet: give it one
                body = {"alt_text": alt}
                if auto:
                    body["meta"] = {"_mbc_alt_auto": 1}
                self.sb.call("POST", f"media/{item['id']}", body=body)
                item["alt"] = alt
                if auto:
                    self.auto_alts.append((item["id"], post_id, alt))
            return item
        if not live_upload(src):
            return None
        try:
            data = self.download(key, src)
            out, ext, mime, notes = process_image(data, post_date)
        except (LiveError, OSError, RuntimeError, ValueError) as exc:
            self.broken.append((post_id, src, str(exc)[:120]))
            return None
        name = media_filename(key, ext, post_date)
        try:
            made, _ = self.sb.call("POST", "media", raw=out, headers={"Content-Type": mime, "Content-Disposition": f'attachment; filename="{name}"'})
            meta = {"mbc_live_source_url": key}
            if auto and alt:
                meta["_mbc_alt_auto"] = 1
            self.sb.call("POST", f"media/{made['id']}", body={"alt_text": alt, "meta": meta})
        except SystemExit as exc:  # the sandbox refused the file: report it and carry on with the post
            self.broken.append((post_id, src, f"sandbox refused the upload: {exc}"[:160]))
            return None
        detail = made.get("media_details", {})
        self.media[key] = {"id": made["id"], "url": made["source_url"], "width": detail.get("width"), "height": detail.get("height"), "alt": alt}
        if auto and alt:
            self.auto_alts.append((made["id"], post_id, alt))
        self.image_notes.append(f"{name}: {len(out) // 1000} kB" + (f" ({'; '.join(notes)})" if notes else ""))
        print(f"  uploaded {name} -> media {made['id']} ({len(out) // 1000} kB)")
        return self.media[key]


# ============================================================ selection and planning

def fetch_live_posts(live, args):
    params = {"_embed": EMBED, "_fields": POST_FIELDS, "orderby": "date", "order": "desc"}
    if args.ids:
        params["include"] = ",".join(str(i) for i in args.ids)
        params["per_page"] = min(100, len(args.ids))
    else:
        if args.since:
            params["after"] = f"{args.since}T00:00:00"
        if args.until:
            params["before"] = (dt.date.fromisoformat(args.until) + dt.timedelta(days=1)).isoformat() + "T00:00:00"
        if args.category:
            cats, _ = live.get_json("categories", {"slug": args.category, "per_page": 100, "_fields": "id,slug"})
            found = {c["slug"] for c in cats}
            for slug in args.category.split(","):
                if slug.strip() not in found:
                    sys.exit(f"Category not found on live: {slug}")
            params["categories"] = ",".join(str(c["id"]) for c in cats)
        params["per_page"] = min(100, args.limit) if args.limit else 100
    posts, page = [], 1
    while True:
        params["page"] = page
        data, headers = live.get_json("posts", params)
        posts += data
        if args.ids or (args.limit and len(posts) >= args.limit) or page >= int(headers.get("x-wp-totalpages", 1)) or page >= 40:
            break
        page += 1
    if args.limit:
        posts = posts[:args.limit]
    return posts


def terms_of(post):
    cats, tags = [], []
    for group in (post.get("_embedded") or {}).get("wp:term", []) or []:
        for t in group:
            (cats if t.get("taxonomy") == "category" else tags if t.get("taxonomy") == "post_tag" else []).append(t)
    return cats, tags


def sandbox_state(sb):
    posts = base.sandbox_pages(sb, "posts", {"status": "any"}) + base.sandbox_pages(sb, "posts", {"status": "trash"})
    pages = base.sandbox_pages(sb, "pages", {"status": "any"})
    media = base.sandbox_pages(sb, "media", {})
    cats = base.sandbox_pages(sb, "categories", {})
    tags = base.sandbox_pages(sb, "tags", {})
    users, _ = sb.call("GET", "users", {"per_page": 100, "context": "edit", "_fields": "id,slug,name"}, retries=2)
    return posts, pages, media, cats, tags, users


AUTHORS = load_authors()


def plan_post(p, state, force, alts=None):
    posts, pages, media, cats, tags, users = state
    slug = p["slug"]
    rec = {"id": p["id"], "slug": slug, "date": p["date"], "date_gmt": p["date_gmt"], "modified_gmt": p.get("modified_gmt", ""),
           "title": plain(p["title"]["rendered"]), "warnings": [], "live": p}
    clean = clean_content(p["content"]["rendered"])
    rec["clean"] = clean
    rec["images"] = content_images(clean["html"])
    feat = ((p.get("_embedded") or {}).get("wp:featuredmedia") or [None])[0]
    rec["feat"] = feat if feat and feat.get("source_url") else None
    rec["links"] = content_links(clean["html"])
    c, t = terms_of(p)
    rec["cats"], rec["tags"] = c, t
    rec["unmapped"] = [x["slug"] for x in c if not map_category(x["slug"], x["name"])[2]]
    rec["author"] = ((p.get("_embedded") or {}).get("author") or [None])[0]
    if clean["empty"]:
        rec["warnings"].append("content is empty after cleaning")
    if len(clean["html"]) > OVERSIZED_CHARS:
        rec["warnings"].append(f"oversized content ({len(clean['html']) // 1000} kB of HTML)")
    for tag, src in clean["dropped"]:
        rec["warnings"].append(f"dropped <{tag}> {short(src) if src else ''}".strip())
    odd = sorted(set(clean["widgets"]) - base.KNOWN_WIDGETS - {"video"})
    if odd:
        rec["warnings"].append(f"unreviewed Elementor widgets: {', '.join(odd)}")
    noalt = [i for i in rec["images"] if not i["alt"].strip() and not (alts or {}).get(i["key"])]
    rec["noalt"] = noalt
    if noalt:
        rec["warnings"].append(f"{len(noalt)} inline image(s) without alt text (needs a drafted description)")
    drafted = [i for i in rec["images"] if not i["alt"].strip() and (alts or {}).get(i["key"])]
    if drafted:
        rec["warnings"].append(f"{len(drafted)} inline image alt text drafted automatically (review)")
    if clean.get("bold_unwrapped"):
        rec["warnings"].append(f"bold removed ({round(clean['bold_unwrapped'] * 100)}% of the text was bold)")
    ext_imgs = [i for i in rec["images"] if not i["live"]]
    if ext_imgs:
        rec["warnings"].append(f"{len(ext_imgs)} image(s) hosted elsewhere (left as they are)")
    if rec["unmapped"]:
        rec["warnings"].append("unmapped categories: " + ", ".join(rec["unmapped"]))
    uid, how = map_author(rec["author"], users, AUTHORS)
    rec["author_id"], rec["author_how"] = uid, how
    if how == "none":
        rec["warnings"].append("live author not exposed by the REST API; no fallback user: left as the importing user" if not rec["author"]
                               else "author not matched and no fallback user: left as the importing user")
    elif how == "mbc" and rec["author"]:
        rec["warnings"].append(f"author {rec['author'].get('slug')!r} not in import_authors.json: posted as the fallback user")
    # Action: by live id (ours), then by slug clash (demo or other posts).
    mine = next((x for x in posts if (x.get("meta") or {}).get("mbc_live_id") == p["id"]), None)
    demo = next((x for x in posts if (x.get("meta") or {}).get("mbc_live_post_id") == p["id"] and not (x.get("meta") or {}).get("mbc_live_id")), None)
    clash = next((x for x in posts if x["slug"] == slug and x is not mine and x is not demo), None)
    page_clash = next((x for x in pages if x["slug"] == slug), None)
    rec["existing"] = mine
    if mine:
        same = (mine["meta"].get("mbc_live_modified") or "") == rec["modified_gmt"]
        rec["action"] = "skip: unchanged" if same and not force else "update"
    elif demo:
        rec["action"] = f"skip: demo copy exists (sandbox #{demo['id']})"
    elif clash or page_clash:
        other = clash or page_clash
        rec["action"] = f"skip: slug clash with sandbox {'post' if clash else 'page'} #{other['id']}"
    else:
        rec["action"] = "create"
    return rec


def category_ids(sb, recs, state, apply):
    """Map live categories to sandbox ids, creating missing ones when applying. Returns ({live slug: id}, created, parent notes)."""
    cats = {c["slug"]: c for c in state[3]}
    created, notes, out = [], [], {}
    for rec in recs:
        for x in rec["cats"]:
            if x["slug"] in out:
                continue
            slug, name, _ = map_category(x["slug"], x["name"])
            if slug in cats:
                out[x["slug"]] = cats[slug]["id"]
                continue
            created.append(slug)
            if not apply:
                out[x["slug"]] = None
                continue
            body = {"name": name, "slug": slug}
            if x.get("parent"):
                notes.append(f"{slug}: parent (live category #{x['parent']}) not recreated; imported as a top-level category")
            made, _ = sb.call("POST", "categories", body=body)
            cats[slug] = made
            out[x["slug"]] = made["id"]
            print(f"  created category {name!r} ({slug}) id {made['id']}")
    return out, created, notes


def tag_ids(sb, recs, state, apply):
    tags = {t["slug"]: t for t in state[4]}
    out, created = {}, []
    for rec in recs:
        for x in rec["tags"]:
            if x["slug"] in out:
                continue
            if x["slug"] in tags:
                out[x["slug"]] = tags[x["slug"]]["id"]
                continue
            created.append(x["slug"])
            if not apply:
                out[x["slug"]] = None
                continue
            made, _ = sb.call("POST", "tags", body={"name": html.unescape(x["name"]), "slug": x["slug"]})
            tags[x["slug"]] = made
            out[x["slug"]] = made["id"]
    return out, created


def link_targets(state, todo):
    """Sandbox paths a link to a live post can point at: posts already imported plus this batch."""
    by_slug = {x["slug"]: f"/{x['slug']}/" for x in state[0] if (x.get("meta") or {}).get("mbc_live_id")}
    by_slug.update({r["slug"]: f"/{r['slug']}/" for r in todo})
    by_id = {int(x["meta"]["mbc_live_id"]): f"/{x['slug']}/" for x in state[0] if (x.get("meta") or {}).get("mbc_live_id")}
    by_id.update({r["id"]: f"/{r['slug']}/" for r in todo})
    return by_slug, by_id


def prepare_alts(live, sb, todo, state):
    """Download the inline images that have neither live alt text nor an alt on the sandbox attachment, write a small
    preview of each (max 800px) and alt-needed.json for the alt-drafting step. Writes nothing to the sandbox."""
    try:
        from PIL import Image, ImageOps
    except ImportError:
        sys.exit("Pillow is needed: run with ~/.venvs/mbc-test/bin/python")
    have = {}
    for m in state[2]:
        key = (m.get("meta") or {}).get("mbc_live_source_url")
        if key and m["id"] not in DEMO_MEDIA and (m.get("alt_text") or "").strip():
            have[normalise_media_url(key) if live_upload(key) else key] = m["alt_text"]
    imp = Importer(live, sb, False, False)
    PREVIEW_DIR.mkdir(parents=True, exist_ok=True)
    needed = {}
    for r in todo:
        for im in r["images"]:
            if im["alt"].strip() or im["key"] in have or not im["live"]:
                continue
            entry = needed.setdefault(im["key"], {"posts": [], "post_title": r["title"], "preview": ""})
            entry["posts"].append(r["id"])
            if entry["preview"]:
                continue
            try:
                img = ImageOps.exif_transpose(Image.open(io.BytesIO(imp.download(im["key"], im["src"]))))
                img.thumbnail((800, 800))
                out = PREVIEW_DIR / (hashlib.sha1(im["key"].encode()).hexdigest()[:12] + ".jpg")
                img.convert("RGB").save(out, "JPEG", quality=70)
                entry["preview"] = str(out)
            except (LiveError, OSError, ValueError) as exc:
                print(f"  could not prepare {short(im['key'])}: {exc}")
    needed = {k: v for k, v in needed.items() if v["preview"]}
    target = CACHE_DIR / "alt-needed.json"
    target.write_text(json.dumps(needed, indent=1))
    print(f"\n{len(needed)} image(s) need an alt text: {target}")


# ============================================================ report

def write_report(args, recs, summary, extra, applied):
    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    path = REPORT_DIR / f"import-{dt.datetime.now():%Y%m%d-%H%M%S}.md"
    lines = [f"# Live post import: {'APPLIED' if applied else 'dry run'}", "",
             f"Selection: {' '.join(sys.argv[1:]) or '(defaults)'}", "", "## Counts", ""]
    for k, v in summary.items():
        lines.append(f"- {k}: {v}")
    lines += ["", "## Posts", "", "| Live id | Date | Title | Categories | Images | Action | Warnings |", "|---|---|---|---|---|---|---|"]
    for r in recs:
        cats = ", ".join(map_category(c["slug"], c["name"])[0] for c in r["cats"]) or "-"
        warn = "; ".join(r["warnings"]).replace("|", "/") or "-"
        lines.append(f"| {r['id']} | {r['date'][:10]} | {r['title'][:60].replace('|', '/')} | {cats} | {len(r['images']) + (1 if r['feat'] else 0)} | {r['action']} | {warn} |")
    for title, items in extra.items():
        lines += ["", f"## {title}", ""]
        lines += [f"- {i}" for i in items] or ["- none"]
    path.write_text("\n".join(lines) + "\n")
    return path


# ============================================================ self-test

def self_test():
    """Static checks of this file (no network), then the shared live client's own checks."""
    src = Path(__file__).read_text()
    tree = ast.parse(src)
    results = []

    def check(ok, text):
        results.append(ok)
        print(("PASS " if ok else "FAIL ") + text)

    allowed = {"argparse", "ast", "datetime", "hashlib", "html", "html.parser", "io", "json", "mimetypes", "pathlib", "re", "sys",
               "urllib.parse", "import_live_demo_posts", "PIL"}
    imported = {a.name for n in ast.walk(tree) if isinstance(n, ast.Import) for a in n.names}
    imported |= {n.module for n in ast.walk(tree) if isinstance(n, ast.ImportFrom) and n.module}
    check(imported <= allowed, f"imports limited to the whitelist (extra: {sorted(imported - allowed) or 'none'})")
    calls = [n for n in ast.walk(tree) if isinstance(n, ast.Call) and isinstance(n.func, ast.Attribute)]
    net = [n for n in calls if n.func.attr in ("request", "urlopen", "putrequest", "connect", "create_connection", "send", "sendall", "getresponse")]
    check(not net, "no network call is made in this file (only through LiveReader and SandboxWriter)")
    live_calls = [n for n in calls if isinstance(n.func.value, ast.Attribute) and n.func.value.attr == "live" or (isinstance(n.func.value, ast.Name) and n.func.value.id == "live")]
    check(live_calls and {n.func.attr for n in live_calls} <= {"get_json", "get_bytes"}, "the live reader is only used through get_json and get_bytes")
    routes = [n.args[0].value for n in live_calls if n.func.attr == "get_json" and n.args and isinstance(n.args[0], ast.Constant)]
    nonconst = [n for n in live_calls if n.func.attr == "get_json" and not (n.args and isinstance(n.args[0], ast.Constant))]
    check(not nonconst and set(routes) <= {"posts", "categories"}, f"live routes are literals and only {sorted(set(routes))}")
    verbs = {n.args[0].value for n in calls if n.func.attr == "call" and n.args and isinstance(n.args[0], ast.Constant)}
    check(verbs <= {"GET", "POST", "OPTIONS"}, f"the sandbox is only read or created/updated with GET, POST and OPTIONS, never delete ({sorted(verbs)})")
    print("-- shared client checks (import_live_demo_posts.py) --")
    check(base.self_test(), "the shared live client's own self-test passes")
    print("\nSELF-TEST", "PASSED" if all(results) else "FAILED")
    return all(results)


# ============================================================ main

def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--apply", action="store_true", help="write to the sandbox (default: dry run)")
    ap.add_argument("--self-test", action="store_true")
    ap.add_argument("--since", help="YYYY-MM-DD")
    ap.add_argument("--until", help="YYYY-MM-DD (inclusive)")
    ap.add_argument("--ids", help="comma-separated live post IDs")
    ap.add_argument("--category", help="live category slug(s), comma-separated")
    ap.add_argument("--limit", type=int, default=DEFAULT_LIMIT, help=f"most posts to consider (default {DEFAULT_LIMIT}; 0 = no limit)")
    ap.add_argument("--force", action="store_true", help="re-import posts even if live has not changed")
    ap.add_argument("--alt-file", help="JSON {normalised live image URL: alt text} used where live has none (drafted for review; stored with meta _mbc_alt_auto)")
    ap.add_argument("--prepare-alts", action="store_true", help="download the images that need alt text, write small previews and alt-needed.json, and stop (no sandbox write)")
    args = ap.parse_args()
    if args.self_test:
        sys.exit(0 if self_test() else 1)
    for d in (args.since, args.until):
        if d:
            try:
                dt.date.fromisoformat(d)
            except ValueError:
                sys.exit(f"Not a date (YYYY-MM-DD): {d}")
    args.ids = [int(x) for x in args.ids.split(",")] if args.ids else []

    sb = SandboxWriter(load_env())
    live = LiveReader()
    print(f"{'APPLY' if args.apply else 'DRY RUN'}: live (GET only) -> sandbox")
    posts = fetch_live_posts(live, args)
    state = sandbox_state(sb)
    schema, _ = sb.call("OPTIONS", "posts", retries=2)
    props = schema.get("schema", {}).get("properties", {}).get("meta", {}).get("properties", {})
    if not {"mbc_live_id", "mbc_live_modified", "_mbc_needs_consent_review"} <= set(props):
        msg = "the sandbox does not expose the import meta keys (deploy inc/live-import.php first)"
        if args.apply:
            sys.exit("Refusing to apply: " + msg)
        print("WARNING:", msg)
    alts = json.loads(Path(args.alt_file).read_text()) if args.alt_file else {}
    if alts and args.apply:
        mprops = sb.call("OPTIONS", "media", retries=2)[0].get("schema", {}).get("properties", {}).get("meta", {}).get("properties", {})
        if "_mbc_alt_auto" not in mprops:
            sys.exit("Refusing to apply: the sandbox does not expose media meta _mbc_alt_auto (deploy inc/live-import.php first)")
    recs = [plan_post(p, state, args.force, alts) for p in posts]
    todo = [r for r in recs if r["action"] in ("create", "update")]

    counts = {"live posts selected": len(recs)}
    for key in ("create", "update", "skip"):
        counts[key] = len([r for r in recs if r["action"].startswith(key)])
    print(f"\n{'live id':>8}  {'date':10}  {'title':44}  {'categories':28}  img  action")
    for r in recs:
        cats = ",".join(map_category(c["slug"], c["name"])[0] for c in r["cats"]) or "-"
        print(f"{r['id']:>8}  {r['date'][:10]}  {r['title'][:44]:44}  {cats[:28]:28}  {len(r['images']) + (1 if r['feat'] else 0):>3}  {r['action']}")
        for w in r["warnings"]:
            print(f"{'':>10}! {w}")
    print(f"\nPlan: {counts['create']} create, {counts['update']} update, {counts['skip']} skip (of {len(recs)})")

    if args.prepare_alts:
        prepare_alts(live, sb, todo, state)
        print(f"\nLive requests made: {live.request_count}")
        return

    applied = False
    cat_new, tag_new, parent_notes = [], [], []
    if args.apply and todo:
        applied = True
        cmap, cat_new, parent_notes = category_ids(sb, todo, state, True)
        tmap, tag_new = tag_ids(sb, todo, state, True)
        imp = Importer(live, sb, True, args.force)
        # Known sandbox posts a link may point at: those already imported plus this batch.
        by_slug, by_id = link_targets(state, todo)
        for m in state[2]:  # media already uploaded from live (not the demo ones)
            key = (m.get("meta") or {}).get("mbc_live_source_url")
            if key and m["id"] not in DEMO_MEDIA:
                d = m.get("media_details") or {}
                imp.media[normalise_media_url(key) if live_upload(key) else key] = {"id": m["id"], "url": m["source_url"], "width": d.get("width"), "height": d.get("height"), "alt": m.get("alt_text") or ""}
        for r in todo:
            print(f"\nlive #{r['id']} {r['title'][:60]!r}: {r['action']}")
            day = r["date"][:10]
            for im in r["images"]:
                draft = alts.get(im["key"], "") if not im["alt"].strip() else ""
                imp.ensure_media(im["key"], im["src"], im["alt"].strip() or draft, day, r["id"], auto=bool(draft))
            feat_id = 0
            if r["feat"]:
                fm = imp.ensure_media(normalise_media_url(r["feat"]["source_url"]), r["feat"]["source_url"], (r["feat"].get("alt_text") or "").strip(), day, r["id"])  # no live alt: stays empty (decorative, beside the title)
                feat_id = fm["id"] if fm else 0
            alt_map = {k: v.get("alt", "") for k, v in imp.media.items() if v.get("alt")}
            alt_map.update(alts)
            content, left = rewrite_content(r["clean"]["html"], imp.media, by_slug, by_id, alt_map)
            r["left_links"] = left
            body = {"title": r["title"], "slug": r["slug"], "status": "publish", "date": r["date"], "date_gmt": r["date_gmt"],
                    "content": content, "excerpt": clean_excerpt(r["live"]["excerpt"]["rendered"]),
                    "categories": [cmap[c["slug"]] for c in r["cats"] if cmap.get(c["slug"])],
                    "tags": [tmap[t["slug"]] for t in r["tags"] if tmap.get(t["slug"])], "featured_media": feat_id,
                    "meta": {"mbc_live_id": r["id"], "mbc_live_modified": r["modified_gmt"], "_mbc_needs_consent_review": 1}}
            if r["author_id"]:
                body["author"] = r["author_id"]
            if not body["categories"]:
                del body["categories"]
            if r["existing"]:
                made, _ = sb.call("POST", f"posts/{r['existing']['id']}", body=body)
            else:
                made, _ = sb.call("POST", "posts", body=body)
            r["sandbox_id"], r["sandbox_link"] = made["id"], made["link"]
            if made["slug"] != r["slug"]:
                r["warnings"].append(f"sandbox slug became {made['slug']}")
            print(f"  -> sandbox post {made['id']}  {made['link']}")
    else:
        by_slug, by_id = link_targets(state, todo)
        for r in todo:  # what would still point at live after the rewrite (images and files are handled when applying)
            r["left_links"] = [h for h in rewrite_content(r["clean"]["html"], {}, by_slug, by_id)[1] if not live_upload(h)]
        cmap, cat_new, parent_notes = category_ids(sb, todo, state, False)
        _, tag_new = tag_ids(sb, todo, state, False)

    extra = {"Categories to create / created": cat_new, "Unmapped categories (not in docs/newsletter/category-map.md)": sorted({s for r in recs for s in r["unmapped"]}),
             "Category parents not recreated": parent_notes, "Tags created": tag_new}
    ext = {}
    for r in recs:
        for h in r["links"]:
            if host_of(h) not in (LIVE_HOST, "") and h.startswith("http"):
                ext.setdefault(host_of(h), set()).add(r["id"])
    extra["External links (host: live post ids)"] = [f"{h}: {sorted(ids)}" for h, ids in sorted(ext.items())]
    extra["Links still pointing at live (not imported posts)"] = [f"live #{r['id']}: {short(h)}" for r in todo for h in (r.get("left_links") or [])]
    extra["Broken images"] = [f"live #{p}: {short(u)} ({why})" for p, u, why in (imp.broken if applied else [])]
    extra["Images uploaded"] = imp.image_notes if applied else []
    extra["Oversized content"] = [f"live #{r['id']}: {w}" for r in recs for w in r["warnings"] if w.startswith("oversized")]
    seen = {}
    for r in recs:
        a = r["live"].get("_embedded", {}).get("author", [{}])[0] if r["live"].get("_embedded") else {}
        label = f"{a.get('slug', '?')} ({html.unescape(a.get('name', '?'))}) -> {r.get('author_how', '?')}"
        seen[label] = seen.get(label, 0) + 1
    extra["Authors (live slug (name) -> how mapped: posts)"] = [f"{k}: {v}" for k, v in sorted(seen.items())]
    if applied:
        extra["Alt text drafted automatically: review in the consent pass (media id, live post, text)"] = [f"media {m} (live #{pid}): {txt}" for m, pid, txt in imp.auto_alts]
    extra["Posts needing the consent review"] = [f"{len(todo)} flagged now (meta _mbc_needs_consent_review)"] if applied else []
    if applied:
        extra["Created / updated"] = [f"live #{r['id']} -> sandbox #{r['sandbox_id']} {r['sandbox_link']}" for r in todo]
    path = write_report(args, recs, counts, extra, applied)
    print(f"\nLive requests made: {live.request_count}\nReport: {path}")
    if not args.apply:
        print("Dry run only: nothing was written. Re-run with --apply to import.")


if __name__ == "__main__":
    main()
