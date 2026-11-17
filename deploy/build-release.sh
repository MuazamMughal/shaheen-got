#!/usr/bin/env bash
# Builds an upload-ready zip for Hostinger (which has PHP + Composer but usually no Node).
#   ./deploy/build-release.sh
# Output: deploy/releases/shaheen-<date>.zip  (no .env, no node_modules, no tests)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M)"
OUT="$ROOT/deploy/releases"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "→ Building production assets (Vite)…"
(cd "$ROOT" && docker compose run --rm --no-deps node sh -c "npm ci && npm run build")

echo "→ Copying app…"
rsync -a "$ROOT/" "$STAGE/app/" \
  --exclude .git --exclude node_modules --exclude vendor --exclude tests \
  --exclude .env --exclude 'storage/logs/*.log' --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' --exclude 'storage/framework/cache/data/*' \
  --exclude 'storage/app/public/*' --exclude deploy/releases --exclude public/hot \
  --exclude docker --exclude docker-compose.yml --exclude 'bootstrap/cache/*.php'

echo "→ Installing PHP dependencies (no dev)…"
docker run --rm -v "$STAGE/app":/var/www/html -e COMPOSER_HOME=/tmp/composer shaheen-php:dev \
  composer install --no-dev --optimize-autoloader --no-interaction -q

mkdir -p "$OUT"
(cd "$STAGE/app" && zip -qr "$OUT/shaheen-$STAMP.zip" .)
rm -rf "$ROOT/public/build"   # local dev uses the Vite dev server

echo "✓ $OUT/shaheen-$STAMP.zip"
