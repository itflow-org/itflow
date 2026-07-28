#!/usr/bin/env bash
#
# ITFlow - one-shot CRLF -> LF normalization.
#
# Converts every tracked text file outside libs/ to LF. Vendored libraries are
# left byte-for-byte as shipped upstream (CONTRIBUTING.md: libs/ is replaced
# wholesale, never edited), and binary assets are skipped outright.
#
# Run once, from the repo root, alongside adding .gitattributes. After that
# .gitattributes keeps new files in line and this script should be a no-op.
#
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

BINARY_RE='\.(png|gif|jpg|jpeg|webp|ico|icc|woff|woff2|ttf|eot|crt|ser|z)$'

mapfile -t candidates < <(git ls-files | grep -v '^libs/' | grep -viE "$BINARY_RE")

changed=0
for f in "${candidates[@]}"; do
    [ -f "$f" ] || continue
    # only touch files that actually contain a CR
    if LC_ALL=C grep -qU $'\r' "$f" 2>/dev/null; then
        LC_ALL=C sed -i 's/\r$//' "$f"
        changed=$((changed + 1))
    fi
done

echo "normalized $changed file(s)"
