#!/usr/bin/env python3
"""Copy the newsletter header image from live to the sandbox and make it the default header image.

Live access: ONE read-only GET of one /wp-content/uploads/ file through import_live_posts.LiveReader
(GET only, no credentials, uploads path only; John, 15 Oct 2026). Sandbox: uploads it to the media
library (meta mbc_live_source_url) and stores its ID in the option mbc_nl_default_header_id
(registered with show_in_rest, so /wp/v2/settings). Idempotent. Dry run unless --apply.

    python3 bin/newsletter_header_image.py /wp-content/uploads/FILE [--alt TEXT] [--apply]
"""
import argparse
import mimetypes
import struct
import sys
import urllib.parse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import import_live_posts as lp  # noqa: E402

SOF = (0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7, 0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF)


def jpeg_size(data):
    """(width, height) of a JPEG, or None."""
    if data[:2] != b"\xff\xd8":
        return None
    i = 2
    while i + 9 < len(data):
        if data[i] != 0xFF:
            i += 1
            continue
        marker = data[i + 1]
        if marker in (0xD8, 0x01) or 0xD0 <= marker <= 0xD7:
            i += 2
            continue
        length = struct.unpack(">H", data[i + 2:i + 4])[0]
        if marker in SOF:
            h, w = struct.unpack(">HH", data[i + 5:i + 9])
            return w, h
        i += 2 + length
    return None


def png_size(data):
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        return struct.unpack(">II", data[16:24])
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("path", help="a /wp-content/uploads/ path on live (the host is fixed in the script)")
    ap.add_argument("--alt", default="")
    ap.add_argument("--apply", action="store_true")
    args = ap.parse_args()

    if not args.path.startswith("/wp-content/uploads/") or ".." in args.path or "?" in args.path:
        sys.exit("only plain /wp-content/uploads/ paths are accepted")
    url = lp.LIVE_BASE + args.path

    data = lp.LiveReader().get_bytes(url)
    size = jpeg_size(data) or png_size(data)
    if not size:
        sys.exit(f"downloaded {len(data)} bytes, but not a JPEG or PNG")
    print(f"downloaded {len(data)} bytes; pixel size: {size[0]} x {size[1]}")
    if size[0] < 600:
        print(f"NOTE: narrower than the 600 px email width ({size[0]} px); it will be shown at {size[0]} px, never stretched")
    if not args.apply:
        print("dry run: nothing uploaded (use --apply)")
        return

    sb = lp.SandboxWriter(lp.load_env())
    found, _ = sb.call("GET", "media", {"context": "edit", "per_page": 100, "_fields": "id,meta"}, retries=2)
    existing = [m for m in found if (m.get("meta") or {}).get("mbc_live_source_url") == url]
    if existing:
        media_id = existing[0]["id"]
        print(f"already in the sandbox library: media {media_id}")
    else:
        name = Path(urllib.parse.urlsplit(url).path).name
        mime = mimetypes.guess_type(name)[0] or "application/octet-stream"
        made, _ = sb.call("POST", "media", raw=data, headers={"Content-Type": mime,
                          "Content-Disposition": f'attachment; filename="{name}"'})
        media_id = made["id"]
        sb.call("POST", f"media/{media_id}", body={"alt_text": args.alt, "title": "Newsletter header",
                "meta": {"mbc_live_source_url": url}})
        print(f"uploaded as media {media_id}")
    sb.call("POST", "settings", body={"mbc_nl_default_header_id": media_id})
    back, _ = sb.call("GET", "settings", {"_fields": "mbc_nl_default_header_id"}, retries=2)
    print(f"default header image is now media {back['mbc_nl_default_header_id']}")


if __name__ == "__main__":
    main()
