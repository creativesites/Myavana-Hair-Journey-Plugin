#!/usr/bin/env bash
# Runs every Mya widget suite against the real bundle in assets/js/.
# Usage:  ./tests/run.sh
#         MYAVANA_JSDOM=/path/to/node_modules/jsdom ./tests/run.sh
set -uo pipefail
cd "$(dirname "$0")/widget"

total_pass=0; total_fail=0; suites_failed=0

for suite in [0-9]*.js; do
    echo "── $suite"
    output="$(node "$suite" 2>&1)"; status=$?
    echo "$output" | grep -E '^\s+(PASS|FAIL)' || true

    line="$(echo "$output" | grep -oE '[0-9]+ passed, [0-9]+ failed' | tail -1)"
    p="$(echo "$line" | grep -oE '^[0-9]+' || echo 0)"
    f="$(echo "$line" | grep -oE '[0-9]+ failed' | grep -oE '^[0-9]+' || echo 0)"
    total_pass=$((total_pass + p)); total_fail=$((total_fail + f))

    if [ "$status" -ne 0 ] || [ "${f:-0}" -ne 0 ]; then
        suites_failed=$((suites_failed + 1))
        [ "$status" -eq 2 ] && echo "$output" | tail -3
    fi
    echo
done

echo "═══════════════════════════════════════"
echo "  $total_pass passed, $total_fail failed across $(ls [0-9]*.js | wc -l | tr -d ' ') suites"
[ "$suites_failed" -eq 0 ] || { echo "  $suites_failed suite(s) failing"; exit 1; }
