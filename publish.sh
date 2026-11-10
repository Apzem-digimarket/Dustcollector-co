#!/bin/bash
# Publishes the website to GitHub Pages → https://www.dustcollector.in
#
# It builds static pages from the local site (the same pages the PHP site produces),
# then commits them to the "gh-pages" branch, which GitHub Pages serves.
# Run it from Terminal:   ./publish.sh
# or click "Publish to website" in the admin panel on this Mac.
set -euo pipefail
export PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:$PATH"

SITE="$(cd "$(dirname "$0")" && pwd)"
OUT="$SITE/.publish"
DOMAIN="www.dustcollector.in"
PORT=8771
LOCAL="http://127.0.0.1:$PORT"
cd "$SITE"

step() { printf '• %s\n' "$1"; }
fail() { printf 'ERROR: %s\n' "$1" >&2; exit 1; }

# 1. Make sure the local site is running.
if ! curl -s -o /dev/null --max-time 5 "$LOCAL/robots.txt"; then
  step "Starting the local site on port $PORT"
  PHP_CLI_SERVER_WORKERS=8 nohup php -S "127.0.0.1:$PORT" router.php >/dev/null 2>&1 &
  for _ in 1 2 3 4 5 6 7 8 9 10; do sleep 0.5; curl -s -o /dev/null "$LOCAL/robots.txt" && break; done
  curl -s -o /dev/null "$LOCAL/robots.txt" || fail "Could not start the local site (is PHP installed?)"
fi

# 2. Prepare the gh-pages working folder (.publish).
if [ ! -e "$OUT/.git" ]; then
  step "Preparing the publish folder"
  git fetch -q origin gh-pages 2>/dev/null || true
  if git show-ref -q --verify refs/remotes/origin/gh-pages; then
    git worktree add -q -B gh-pages "$OUT" origin/gh-pages
  else
    git worktree add -q --orphan -b gh-pages "$OUT"
  fi
fi
git -C "$OUT" fetch -q origin gh-pages 2>/dev/null && git -C "$OUT" reset -q --hard origin/gh-pages 2>/dev/null || true
git -C "$OUT" rm -rq --ignore-unmatch . >/dev/null
git -C "$OUT" clean -fdqx

# 3. Build the pages, exactly as visitors to https://$DOMAIN will see them.
get() {   # get <path> <output file> <expected HTTP status>
  mkdir -p "$(dirname "$OUT/$2")"
  local code
  code=$(curl -s --max-time 60 -H "Host: $DOMAIN" -H "X-Forwarded-Proto: https" -o "$OUT/$2" -w '%{http_code}' "$LOCAL$1" || true)
  [ "$code" = "$3" ] || fail "$1 returned $code (expected $3)"
}

step "Building the home page"
get "/" "index.html" 200
get "/no-such-page" "404.html" 404
get "/sitemap.xml" "sitemap.xml" 200

count=0
for url in $(grep -o "<loc>https://$DOMAIN/products/[^<]*</loc>" "$OUT/sitemap.xml" | sed -e 's#<loc>https://[^/]*##' -e 's#</loc>##'); do
  slug="${url#/products/}"
  get "$url" "products/$slug.html" 200
  count=$((count + 1))
done
step "Built $count product pages"

# /products on its own → the equipment range on the home page
cat > "$OUT/products.html" <<'HTML'
<!doctype html><meta charset="utf-8"><title>Equipment range</title>
<meta http-equiv="refresh" content="0; url=/#applications"><link rel="canonical" href="/#applications">
<a href="/#applications">View the equipment range</a>
HTML

# 4. Copy images, styles, scripts and icons (never data/, includes/ or admin/).
step "Copying images, styles and scripts"
mkdir -p "$OUT/assets" "$OUT/uploads"
cp -R assets/. "$OUT/assets/"
find uploads -maxdepth 1 -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' -o -iname '*.webp' -o -iname '*.gif' \) -exec cp {} "$OUT/uploads/" \;
cp robots.txt favicon.svg favicon.ico apple-touch-icon.png "$OUT/"
find "$OUT" -name '.DS_Store' -delete
echo "$DOMAIN" > "$OUT/CNAME"
touch "$OUT/.nojekyll"

# 5. Commit and push.
cd "$OUT"
git add -A
if git diff --cached --quiet; then
  step "No changes since the last publish — the website is already up to date."
else
  git commit -qm "Publish website $(date '+%Y-%m-%d %H:%M')"
  step "Uploading to GitHub"
  git push -q origin gh-pages
  step "Published. The website updates in about 1 minute: https://$DOMAIN/"
fi
date '+%Y-%m-%d %H:%M' > "$SITE/data/last-publish.txt"
