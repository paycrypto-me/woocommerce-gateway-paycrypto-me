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
# Support repositories using either `src/trunk` (this repo) or `source/trunk` (other layouts).
if [[ -d "$ROOT_DIR/src/trunk" ]]; then
  TRUNK="$ROOT_DIR/src/trunk"
elif [[ -d "$ROOT_DIR/source/trunk" ]]; then
  TRUNK="$ROOT_DIR/source/trunk"
else
  # fallback to original path (will likely fail later with descriptive error)
  TRUNK="$ROOT_DIR/source/trunk"
fi

echo "Using trunk path: $TRUNK"

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
  # Replace the line that contains the Version header inside the plugin file comment block.
  # Match lines like: " * Version: ..." and replace the value only.
  sed -E -i.bak "s/^(\\s*\\*\\s*Version:[[:space:]]*).*/\\1$VERSION/" "$PLUGIN_FILE" || true
fi

if [[ -f "$README_FILE" ]]; then
  echo "Updating Stable tag in $README_FILE"
  sed -E -i.bak "s/^(Stable tag:[[:space:]]*).*/\1$VERSION/" "$README_FILE" || true
fi

# Clean up sed backup files created by -i.bak
if [[ -f "$PLUGIN_FILE.bak" ]]; then
  rm -f "$PLUGIN_FILE.bak" || true
fi
if [[ -f "$README_FILE.bak" ]]; then
  rm -f "$README_FILE.bak" || true
fi

# Update composer.json and package.json by replacing the "version" line in-place
# Use a line-based sed replacement to preserve existing whitespace and formatting
for f in "$TRUNK/composer.json" "$TRUNK/package.json"; do
  if [[ -f "$f" ]]; then
    echo "Updating version in $f (preserve formatting)"
    # Replace the value of the top-level "version" property on its line only.
    sed -E -i.bak 's/^([[:space:]]*"version"[[:space:]]*:[[:space:]]*")[^"]+("[[:space:]]*,?)/\1'"$VERSION"'\2/' "$f" || true
    rm -f "$f.bak" || true
  fi
done

# Update JS source header @version if present
JS_FILE="$TRUNK/src/paycrypto-me-script.js"
if [[ -f "$JS_FILE" ]]; then
  echo "Updating @version in $JS_FILE"
  # Replace the @version line in the leading comment block
  sed -E -i.bak "s/^([[:space:]]*\*[[:space:]]*@version[[:space:]]+).*/\\1$VERSION/" "$JS_FILE" || true
  rm -f "$JS_FILE.bak" || true
fi

# Update PHP class constant VERSION inside the main plugin file if present
if [[ -f "$PLUGIN_FILE" ]]; then
  echo "Updating WC_PayCryptoMe::VERSION constant in $PLUGIN_FILE"
  sed -E -i.bak "s/^(\s*public\s+const\s+string\s+VERSION\s*=\s*')[^']+('\s*;)/\\1$VERSION\\2/" "$PLUGIN_FILE" || true
  rm -f "$PLUGIN_FILE.bak" || true
fi

BUILD_DIR=$(mktemp -d -t ${SLUG}-release-XXXX)
echo "Creating build dir $BUILD_DIR"

echo "Copying files to build directory (excluding dev files)"
rsync -a --delete \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='.git' \
  --exclude='.phpunit.result.cache' \
  --exclude='phpunit.xml.dist' \
  --exclude='*~' \
  --exclude='*.po~' \
  --exclude='*.map' \
  --exclude='webpack.config.js' \
  --exclude='package-lock.json' \
  --exclude='composer.lock' \
  "$TRUNK/" "$BUILD_DIR/$SLUG/"

# Remove dev files from copied tree
rm -rf "$BUILD_DIR/$SLUG/node_modules" 
rm -f "$BUILD_DIR/$SLUG/package-lock.json" 
rm -f "$BUILD_DIR/$SLUG/webpack.config.js" 
# Remove phpunit artifacts if accidentally copied
rm -f "$BUILD_DIR/$SLUG/.phpunit.result.cache" 
rm -f "$BUILD_DIR/$SLUG/phpunit.xml.dist" 
# Remove any backup files (e.g. PO backups ending with ~)
find "$BUILD_DIR/$SLUG" -name '*~' -type f -print -delete || true
find "$BUILD_DIR/$SLUG" -name '*.po~' -type f -print -delete || true

# Clean vendor directory: remove VCS metadata, phpunit files and tests directories
if [[ -d "$BUILD_DIR/$SLUG/vendor" ]]; then
  echo "Cleaning vendor: removing VCS metadata, phpunit files and tests directories"
  # Remove .git directories and other VCS files
  find "$BUILD_DIR/$SLUG/vendor" -type d -name '.git' -prune -exec rm -rf {} + || true
  find "$BUILD_DIR/$SLUG/vendor" -type f -name '.git*' -delete || true
  find "$BUILD_DIR/$SLUG/vendor" -type f -name '.gitignore' -delete || true
  # Remove phpunit configuration/related files
  find "$BUILD_DIR/$SLUG/vendor" -type f -iname 'phpunit.xml*' -delete || true
  # Remove common CI/config files
  find "$BUILD_DIR/$SLUG/vendor" -type f -iname '.travis.yml' -delete || true
  find "$BUILD_DIR/$SLUG/vendor" -type d -name '.github' -prune -exec rm -rf {} + || true
  # Remove tests folders inside vendor packages
  find "$BUILD_DIR/$SLUG/vendor" -type d \( -iname 'tests' -o -iname 'test' -o -iname 'Tests' \) -prune -exec rm -rf {} + || true
  # Remove heavy font files and unrelated images from endroid QR code assets
  if [[ -d "$BUILD_DIR/$SLUG/vendor/endroid/qr-code/assets" ]]; then
    echo "Removing heavy fonts and unrelated images from endroid/qr-code/assets"
    find "$BUILD_DIR/$SLUG/vendor/endroid/qr-code/assets" -type f \( -iname '*.ttf' -o -iname '*.otf' -o -iname 'blackfire.png' \) -print -delete || true
  fi
  # Remove example/exampleS directories from vendor packages
  find "$BUILD_DIR/$SLUG/vendor" -type d \( -iname 'examples' -o -iname 'example' -o -iname 'Examples' \) -prune -exec rm -rf {} + || true
fi

# Further prune common non-runtime files from vendor to reduce package size
if [[ -d "$BUILD_DIR/$SLUG/vendor" ]]; then
  echo "Pruning documentation, build and binary files from vendor"
  # Remove license files, markdown docs, CI configs, shell scripts, XML, neon, yaml
  find "$BUILD_DIR/$SLUG/vendor" -type f \( -iname 'license' -o -iname 'license.*' -o -iname '*.md' -o -iname '*.markdown' -o -iname '*.yml' -o -iname '*.yaml' -o -iname '*.sh' -o -iname '*.neon' -o -iname '*.xml' -o -iname 'Makefile' \) -delete || true
  # Remove bin directories from vendor packages
  find "$BUILD_DIR/$SLUG/vendor" -type d -name 'bin' -prune -exec rm -rf {} + || true
fi

# Remove root-level non-distributable files/dirs from package
rm -f "$BUILD_DIR/$SLUG/LICENSE" || true
rm -rf "$BUILD_DIR/$SLUG/examples" || true

if [[ $DO_ZIP -eq 1 ]]; then
  mkdir -p "$ROOT_DIR/releases"
    echo "Creating zip: releases/${SLUG}-${VERSION}.zip"
    # Remove any existing zip to avoid keeping stale entries
    rm -f "$ROOT_DIR/releases/${SLUG}-${VERSION}.zip" || true
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
