#!/bin/bash
# Jalankan dari folder ini:  ./build-production-zip.sh
# Hasil: PRODUCTION-DEPLOY.zip (folder TaskManagement/ di dalamnya)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
OUT="$ROOT/PRODUCTION-DEPLOY.zip"
TMPDIR="$(mktemp -d)"
cleanup() { rm -rf "$TMPDIR"; }
trap cleanup EXIT

echo "Membuat ZIP di: $OUT"

rsync -a \
  --exclude '.env' \
  --exclude 'vendor' \
  --exclude '.cursor' \
  --exclude '.DS_Store' \
  --exclude 'tests' \
  --exclude 'phpunit.xml.dist' \
  --exclude '*.md' \
  --exclude 'LICENSE' \
  --exclude '.gitignore' \
  --exclude 'db' \
  --exclude 'fix_db.php' \
  --exclude 'output.html' \
  --exclude 'warnastyle.html' \
  --exclude 'bcakup' \
  --exclude 'fileudaptefeature' \
  --exclude 'writable/logs/*' \
  --exclude 'writable/session/*' \
  --exclude 'writable/cache/*' \
  --exclude 'writable/debugbar/*' \
  --exclude 'writable/uploads/*' \
  --exclude 'PRODUCTION-DEPLOY.zip' \
  --exclude 'task-management-production-minimal.zip' \
  --exclude 'task-management-production-*.zip' \
  --exclude 'build-production-zip.sh' \
  "$ROOT/" \
  "$TMPDIR/TaskManagement/"

for d in cache logs session uploads debugbar; do
  mkdir -p "$TMPDIR/TaskManagement/writable/$d"
  if [[ -f "$ROOT/writable/$d/index.html" ]]; then
    cp "$ROOT/writable/$d/index.html" "$TMPDIR/TaskManagement/writable/$d/"
  fi
done

rm -f "$OUT"
( cd "$TMPDIR" && zip -rq "$OUT" TaskManagement )

ls -lh "$OUT"
echo "Selesai. File: $OUT"
