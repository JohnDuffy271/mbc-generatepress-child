#!/usr/bin/python3
"""Set the events calendar's Google API key and calendar ID on the sandbox.

Reads GCAL_API_KEY and GCAL_ID from ~/.config/mbc/gcal.env and sends them to the admin-only REST
route /wp-json/mbc/v1/calendar-config (inc/calendar/config.php). The key is never printed. Also
clears the calendar caches. Dry run by default; --apply writes. --outbound-ip asks the server for
its outbound address (to restrict the key). Sandbox only.
"""
import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import scaffold_site as ss  # noqa: E402

ROUTE = "/wp-json/mbc/v1/calendar-config"


def load_gcal():
    path = Path.home() / ".config" / "mbc" / "gcal.env"
    env = {}
    try:
        for line in path.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, v = line.split("=", 1)
                env[k.replace("export ", "").strip()] = v.strip().strip("'\"")
    except OSError as exc:
        sys.exit(f"Cannot read {path}: {exc}")
    for key in ("GCAL_API_KEY", "GCAL_ID"):
        if not env.get(key):
            sys.exit(f"{key} missing from {path}")
    return env


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--outbound-ip", action="store_true")
    args = ap.parse_args()
    wp = ss.WP(ss.load_env())
    if args.outbound_ip:
        print("outbound IP:", wp.call("GET", ROUTE, {"outbound_ip": 1}).get("outbound_ip"))
        return
    env = load_gcal()
    state = wp.call("GET", ROUTE)
    print(f"{'APPLY' if args.apply else 'DRY RUN'}: key set on server: {state['has_key']}; calendar ID there: "
          f"{state['calendar_id'] or '(none)'}; local ID: {env['GCAL_ID']}")
    if args.apply:
        state = wp.call("POST", ROUTE, body={"api_key": env["GCAL_API_KEY"], "calendar_id": env["GCAL_ID"]})
        print(f"done: key set: {state['has_key']}; calendar ID: {state['calendar_id']}")


if __name__ == "__main__":
    main()
