#!/usr/bin/env bash
#
# Build a reproducible, versioned installable ZIP of the Myavana Hair Journey
# plugin from a given git ref (default: main).
#
# Why this exists: Winston has been manually copying files and uploading a
# ZIP through wp-admin for every release. That has no record of which commit
# produced a given ZIP, and the plugin's declared version has already drifted
# from what's actually running at least once this week. This script is the
# smallest fix for both problems that needs zero new credentials or hosting
# access - it only touches this git repo.
#
# What it does:
#   1. Resolves the ref you give it (or `main`) to an exact commit.
#   2. Reads the version from BOTH the plugin header comment and the
#      MYAVANA_NEXT_VERSION PHP constant, AT THAT COMMIT (not the working
#      tree) - and refuses to build if they disagree. A build nobody can
#      trust the version number of is worse than no build.
#   3. Uses `git archive` to export exactly what's tracked at that commit -
#      no working-tree contamination, no risk of pulling in someone else's
#      uncommitted work-in-progress from a different branch.
#   4. Names the output with the version and commit SHA, and writes a
#      sha256 checksum next to it, so a given ZIP can always be traced back
#      to exactly what git state produced it.
#
# Usage:
#   ./bin/build-plugin-zip.sh [ref]
#
# Output:
#   dist/myavana-hair-journey-next-<version>-<short-sha>.zip
#   dist/myavana-hair-journey-next-<version>-<short-sha>.zip.sha256
#
set -euo pipefail

REF="${1:-main}"
PLUGIN_FILE="myavana-hair-journey-next.php"
DIST_DIR="dist"

# Must be run from inside the plugin's git repo.
REPO_ROOT="$(git rev-parse --show-toplevel 2>/dev/null || true)"
if [ -z "$REPO_ROOT" ]; then
  echo "ERROR: not inside a git repository. Run this from within the plugin repo." >&2
  exit 1
fi
cd "$REPO_ROOT"

if git rev-parse --verify "$REF" >/dev/null 2>&1; then
  RESOLVED_REF="$REF"
elif git rev-parse --verify "origin/$REF" >/dev/null 2>&1; then
  # A fresh clone only has a local branch for the default branch - anything
  # else exists only as origin/<name> until checked out. Fall back rather
  # than forcing every caller to know that.
  RESOLVED_REF="origin/$REF"
else
  echo "ERROR: '$REF' is not a valid ref (checked '$REF' and 'origin/$REF')." >&2
  exit 1
fi
REF="$RESOLVED_REF"

COMMIT_SHA="$(git rev-parse "$REF")"
SHORT_SHA="${COMMIT_SHA:0:7}"

if ! git cat-file -e "$REF:$PLUGIN_FILE" 2>/dev/null; then
  echo "ERROR: $PLUGIN_FILE not found at ref '$REF'." >&2
  exit 1
fi

PLUGIN_FILE_CONTENT="$(git show "$REF:$PLUGIN_FILE")"

# Note: [[:space:]] not \s - BSD/macOS sed and grep don't support \s (a GNU
# extension), and this needs to work in whatever shell actually runs it.
HEADER_VERSION="$(echo "$PLUGIN_FILE_CONTENT" | grep -m1 -E '^[[:space:]]*\*[[:space:]]*Version:' | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//' | tr -d '[:space:]')"
CONSTANT_VERSION="$(echo "$PLUGIN_FILE_CONTENT" | grep -m1 "MYAVANA_NEXT_VERSION" | sed -E "s/.*MYAVANA_NEXT_VERSION['\"]?[[:space:]]*,[[:space:]]*['\"]([0-9A-Za-z.-]+)['\"].*/\1/")"

if [ -z "$HEADER_VERSION" ] || [ -z "$CONSTANT_VERSION" ]; then
  echo "ERROR: could not read one or both version strings from $PLUGIN_FILE at $REF." >&2
  echo "  Header comment version: '${HEADER_VERSION:-<not found>}'" >&2
  echo "  MYAVANA_NEXT_VERSION:   '${CONSTANT_VERSION:-<not found>}'" >&2
  exit 1
fi

if [ "$HEADER_VERSION" != "$CONSTANT_VERSION" ]; then
  echo "ERROR: version mismatch at $REF ($SHORT_SHA) - refusing to build an untrustworthy artifact." >&2
  echo "  Plugin header comment says: $HEADER_VERSION" >&2
  echo "  MYAVANA_NEXT_VERSION says:  $CONSTANT_VERSION" >&2
  echo "  Fix the mismatch in $PLUGIN_FILE and commit before packaging." >&2
  exit 1
fi

VERSION="$HEADER_VERSION"
mkdir -p "$DIST_DIR"

OUT_NAME="myavana-hair-journey-next-${VERSION}-${SHORT_SHA}.zip"
OUT_PATH="${DIST_DIR}/${OUT_NAME}"

echo "Building $OUT_NAME from $REF ($COMMIT_SHA)..."

# git archive exports exactly what's tracked at this commit - nothing from
# the working tree, nothing gitignored, nothing from another branch's
# in-progress changes.
git archive --format=zip --prefix="myavana-hair-journey-next/" "$REF" -o "$OUT_PATH"

shasum -a 256 "$OUT_PATH" | awk '{print $1}' > "${OUT_PATH}.sha256"

echo ""
echo "Built: $OUT_PATH"
echo "  Version:    $VERSION"
echo "  Ref:        $REF"
echo "  Commit:     $COMMIT_SHA"
echo "  SHA-256:    $(cat "${OUT_PATH}.sha256")"
echo ""
echo "This ZIP is what a human uploads through wp-admin, or what a future"
echo "automated deploy step consumes - either way, it is traceable back to"
echo "exactly this commit, and its version number is guaranteed consistent."
