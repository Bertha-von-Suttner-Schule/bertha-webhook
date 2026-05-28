#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION=$(grep -oP '<version>\K[^<]+' appinfo/info.xml)
APP_ID="bertha_ki"
OUT="dist/${APP_ID}-${VERSION}.tar.gz"

echo "Building ${APP_ID} v${VERSION}"

mkdir -p dist
rm -f "$OUT"

tar czf "$OUT" \
	--transform "s,^\./,${APP_ID}/," \
	--exclude='./.git' \
	--exclude='./.github' \
	--exclude='./.gitignore' \
	--exclude='./dist' \
	--exclude='./scripts' \
	--exclude='./composer.json' \
	--exclude='./composer.lock' \
	--exclude='./vendor' \
	--exclude='./node_modules' \
	.

echo "✓ ${OUT}"
ls -lh "$OUT"
