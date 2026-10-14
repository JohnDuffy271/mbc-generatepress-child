#!/bin/bash
# Idempotent sandbox clean-out (14 Oct 2026 decisions):
#   trash: 62 projects, 326 empty "What's On" draft, 574 snippet preview
#   draft: 8 welcome, 54 baptism, 556 sunday-school-2
# Trash is reversible (no force delete). Safe to re-run.
set -euo pipefail

CURL=/usr/bin/curl
PY=/usr/bin/python3
TRASH_IDS=(62 326 574)
DRAFT_IDS=(8 54 556)

# shellcheck disable=SC1090
set -a; . "$HOME/.config/mbc/wp.env"; set +a

status_of() {
    "$CURL" -s -u "$WP_USER:$WP_APP_PASSWORD" \
        "$WP_URL/wp-json/wp/v2/pages/$1?context=edit&_fields=status" \
        | "$PY" -I -c 'import json,sys; print(json.load(sys.stdin).get("status","missing"))'
}

for id in "${TRASH_IDS[@]}"; do
    s=$(status_of "$id")
    if [ "$s" = "trash" ] || [ "$s" = "missing" ]; then
        echo "page $id: already $s, skipping"; continue
    fi
    "$CURL" -s -o /dev/null -w "page $id: trashed (HTTP %{http_code})\n" -X DELETE \
        -u "$WP_USER:$WP_APP_PASSWORD" "$WP_URL/wp-json/wp/v2/pages/$id"
done

for id in "${DRAFT_IDS[@]}"; do
    s=$(status_of "$id")
    if [ "$s" = "draft" ] || [ "$s" = "missing" ]; then
        echo "page $id: already $s, skipping"; continue
    fi
    "$CURL" -s -o /dev/null -w "page $id: set to draft (HTTP %{http_code})\n" -X POST \
        -u "$WP_USER:$WP_APP_PASSWORD" -H 'Content-Type: application/json' \
        -d '{"status":"draft"}' "$WP_URL/wp-json/wp/v2/pages/$id"
done
