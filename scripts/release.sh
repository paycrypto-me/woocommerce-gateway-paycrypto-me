#!/usr/bin/env bash
set -euo pipefail

# Release helper for PayCrypto.Me plugin
# Usage: ./scripts/release.sh -v VERSION -s SLUG [--no-build] [--no-tests] [--no-zip] [--git] [--svn]

show_help() {
  cat <<EOF
Usage: $0 -v VERSION -s SLUG [options]

Options:
  -v VERSION    Release version (required)
  -s SLUG       Plugin slug / folder name (required)
  --no-build    Skip npm build
  --no-tests    Skip phpunit tests
  --no-zip      Skip creating the zip
  --git         Commit changes and create git tag (push not automatic)
  --svn         Prepare SVN trunk/tags (requires SVN credentials)
  -h|--help     Show this help
EOF
}

if [[ ${#@} -eq 0 ]]; then
  show_help
  exit 1
fi

VERSION=""
SLUG=""
DO_BUILD=1
DO_TESTS=1
DO_ZIP=1
DO_GIT=0
DO_SVN=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    -v) VERSION="$2"; shift 2;;
    -s) SLUG="$2"; shift 2;;
    --no-build) DO_BUILD=0; shift;;
    --no-tests) DO_TESTS=0; shift;;
    --no-zip) DO_ZIP=0; shift;;
    --git) DO_GIT=1; shift;;
    --svn) DO_SVN=1; shift;;
    -h|--help) show_help; exit 0;;
    *) echo "Unknown option: $1"; show_help; exit 1;;
  esac
done

if [[ -z "$VERSION" || -z "$SLUG" ]]; then
  echo "ERROR: VERSION and SLUG are required." >&2
  show_help
  exit 1
fi

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
TRUNK="$ROOT_DIR/source/trunk"

echo "Preparing release $SLUG v$VERSION"

if [[ $DO_BUILD -eq 1 ]]; then
  if [[ -f "$TRUNK/package.json" ]]; then
    echo "Running npm ci && npm run build in $TRUNK"
    (cd "$TRUNK" && npm ci && npm run build)
  else
    echo "No package.json in $TRUNK, skipping build"
  fi
fi

if [[ $DO_TESTS -eq 1 ]]; then
  if [[ -x "$TRUNK/vendor/bin/phpunit" ]]; then
    echo "Running phpunit"
    (cd "$TRUNK" && ./vendor/bin/phpunit --configuration phpunit.xml.dist)
  else
    echo "phpunit not available in $TRUNK/vendor/bin, skipping tests"
  fi
fi

# Update version in plugin header and readme
PLUGIN_FILE="$TRUNK/paycrypto-me-for-woocommerce.php"
README_FILE="$TRUNK/readme.txt"

if [[ -f "$PLUGIN_FILE" ]]; then
  echo "Updating Version: header in $PLUGIN_FILE"
  # Replace the first occurrence of "Version: ..."
  sed -E -i.bak "0,/Version:[[:space:]]*/s//Version: $VERSION/" "$PLUGIN_FILE" || true
fi

if [[ -f "$README_FILE" ]]; then
  echo "Updating Stable tag in $README_FILE"
  sed -E -i.bak "s/^(Stable tag:[[:space:]]*).*/\1$VERSION/" "$README_FILE" || true
fi

# Try update composer.json and package.json if jq is available
if command -v jq >/dev/null 2>&1; then
  for f in "$TRUNK/composer.json" "$TRUNK/package.json"; do
    if [[ -f "$f" ]]; then
      echo "Updating version in $f"
      tmp=$(mktemp)
      jq --arg v "$VERSION" '.version=$v' "$f" > "$tmp" && mv "$tmp" "$f"
    fi
  done
else
  echo "jq not found, skipping composer.json/package.json version updates (install jq to enable)"
fi

BUILD_DIR=$(mktemp -d -t ${SLUG}-release-XXXX)
echo "Creating build dir $BUILD_DIR"

echo "Copying files to build directory (excluding dev files)"
rsync -a --delete \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='.git' \
  --exclude='*.map' \
  --exclude='webpack.config.js' \
  --exclude='package-lock.json' \
  --exclude='composer.lock' \
  "$TRUNK/" "$BUILD_DIR/$SLUG/"

# Remove dev files from copied tree
rm -rf "$BUILD_DIR/$SLUG/node_modules" 
rm -f "$BUILD_DIR/$SLUG/package-lock.json" 
rm -f "$BUILD_DIR/$SLUG/webpack.config.js" 

if [[ $DO_ZIP -eq 1 ]]; then
  mkdir -p "$ROOT_DIR/releases"
  echo "Creating zip: releases/${SLUG}-${VERSION}.zip"
  (cd "$BUILD_DIR" && zip -r "$ROOT_DIR/releases/${SLUG}-${VERSION}.zip" "$SLUG") >/dev/null
  echo "Zip created: $ROOT_DIR/releases/${SLUG}-${VERSION}.zip"
fi

if [[ $DO_GIT -eq 1 ]]; then
  echo "Committing release changes and tagging v$VERSION"
  (cd "$ROOT_DIR" && git add -A && git commit -m "Release v$VERSION" || echo "No changes to commit" )
  (cd "$ROOT_DIR" && git tag -a "v$VERSION" -m "Release v$VERSION" || echo "Tag exists or failed")
  echo "Created git tag v$VERSION. Push manually with: git push origin --tags"
fi

if [[ $DO_SVN -eq 1 ]]; then
  echo "Preparing SVN export (you must have SVN configured)."
  echo "This will create a checkout directory './svn-checkout' and copy trunk into it."
  svn_dir="$BUILD_DIR/svn-checkout"
  svn_url="https://plugins.svn.wordpress.org/${SLUG}"
  echo "Checking out $svn_url"
  svn checkout "$svn_url" "$svn_dir" || true
  echo "Copying files to svn trunk"
  rm -rf "$svn_dir/trunk/*" || true
  rsync -a "$BUILD_DIR/$SLUG/" "$svn_dir/trunk/"
  echo "You can now svn add --force and svn commit from $svn_dir"
fi

echo "Cleaning up build dir"
rm -rf "$BUILD_DIR"

echo "Release process finished for ${SLUG} v${VERSION}"

exit 0
