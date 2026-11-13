#!/usr/bin/env bash
# Bootstraps a fresh WordPress install with the POC theme activated.
set -euo pipefail
cd "$(dirname "$0")/.."

WP_PORT="${WP_PORT:-8080}"
WP_URL="${WP_URL:-http://localhost:${WP_PORT}}"

docker compose up -d db wordpress

wp() { docker compose run --rm -T wpcli wp "$@"; }

echo "Waiting for WordPress files and database..."
for _ in $(seq 1 60); do
  if wp db check >/dev/null 2>&1; then break; fi
  sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
  wp core install \
    --url="$WP_URL" \
    --title="WP Prototype POC" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email
fi

wp option update siteurl "$WP_URL"
wp option update home "$WP_URL"
wp option update timezone_string "UTC"
wp rewrite structure '/%postname%/' --hard
wp theme activate poc-theme

if [ -f scripts/seed.sh ]; then
  docker compose run --rm -T wpcli bash /scripts/seed.sh
fi

echo "Done: ${WP_URL}  (admin / admin)"
