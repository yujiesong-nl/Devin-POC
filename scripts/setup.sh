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
    --title="Coffee4U" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email
fi

wp option update siteurl "$WP_URL"
wp option update home "$WP_URL"
wp option update timezone_string "UTC"
wp rewrite structure '/%postname%/' --hard
wp option update blogname "Coffee4U"
wp option update blogdescription "Order online for pick-up"
wp theme activate coffee4u

# No third-party plugins: remove the bundled samples and activate only the project's own plugin.
wp plugin delete akismet hello >/dev/null 2>&1 || true
wp plugin activate coffee4u-core
wp coffee4u seed
wp rewrite flush --hard

echo "Done: ${WP_URL}  (admin / admin)"
