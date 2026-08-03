#!/usr/bin/env bash
#
# Copies the pdf.js reader assets out of node_modules into public/vendor/pdfjs.
#
# The digital viewer is a Blade page served under layouts.public, which loads
# its assets with plain <script> tags rather than through Vite. Vendoring the
# prebuilt pdf.js bundle keeps the reader working on a checkout that never runs
# `npm run build`, and keeps it working with no external CDN — reader traffic to
# licensed material must not leak to third parties.
#
# The cmaps and standard_fonts directories are not optional here: the collection
# is largely Russian and Kazakh, and PDFs that use CID-encoded or non-embedded
# fonts render as blank glyphs without them.
#
# Run after `npm install` / when bumping pdfjs-dist: make vendor-pdfjs

set -euo pipefail

cd "$(dirname "$0")/.."

SRC="node_modules/pdfjs-dist"
DEST="public/vendor/pdfjs"

if [ ! -d "$SRC" ]; then
    echo "pdfjs-dist not found in node_modules — run 'npm install' first." >&2
    exit 1
fi

rm -rf "$DEST"
mkdir -p "$DEST/build"

# The legacy build targets a wider range of browsers than the default one.
cp "$SRC/legacy/build/pdf.min.mjs" "$DEST/build/"
cp "$SRC/legacy/build/pdf.worker.min.mjs" "$DEST/build/"
cp -r "$SRC/cmaps" "$DEST/cmaps"
cp -r "$SRC/standard_fonts" "$DEST/standard_fonts"

node -e "console.log(require('./$SRC/package.json').version)" > "$DEST/VERSION"

echo "Vendored pdf.js $(cat "$DEST/VERSION" | tr -d '\n') into $DEST"
