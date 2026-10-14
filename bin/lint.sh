#!/bin/bash
# Lint the theme for PHP 8.2 compatibility (server runs 8.2; local PHP is newer).
# phpcs and PHPCompatibilityWP live in the global Composer dir, never in the repo.
set -euo pipefail

REPO="$(cd "$(/usr/bin/dirname "${BASH_SOURCE[0]}")/.." && pwd)"
THEME="$REPO/generatepress-child"
PHPCS="$HOME/.config/composer/vendor/bin/phpcs"

if [ ! -x "$PHPCS" ]; then
    echo "phpcs not found at $PHPCS (composer global require squizlabs/php_codesniffer phpcompatibility/phpcompatibility-wp)" >&2
    exit 2
fi

echo "== php -l =="
status=0
while IFS= read -r -d '' f; do
    /usr/bin/php -l "$f" >/dev/null || status=1
done < <(/usr/bin/find "$THEME" -name '*.php' -print0)
[ "$status" -eq 0 ] && echo "Syntax OK"

echo "== PHPCompatibilityWP (PHP 8.2) =="
"$PHPCS" -p --extensions=php --standard=PHPCompatibilityWP \
    --runtime-set testVersion 8.2 "$THEME" || status=1

exit "$status"
