# WP Prototype POC

A proof of concept that turns design prototypes into a custom WordPress **block theme**.

## Stack

- WordPress 6 (PHP 8.3, Apache) + MariaDB 11.4 via Docker Compose
- WP-CLI for provisioning and seeding content
- Custom block theme in `theme/poc-theme` (`theme.json`, block templates, template parts, patterns)

## Quick start

```bash
./scripts/setup.sh
```

Open http://localhost:8080 (admin: `http://localhost:8080/wp-admin`, `admin` / `admin`).

Use another port with `WP_PORT=8888 ./scripts/setup.sh`.

Run WP-CLI commands:

```bash
docker compose run --rm wpcli wp plugin list
```

Reset everything:

```bash
docker compose down -v
```

## Layout

```
docker-compose.yml        WordPress, MariaDB and WP-CLI services
scripts/setup.sh          Install, configure and activate the theme
scripts/seed.sh           Demo content (optional, run by setup.sh)
theme/poc-theme/          The block theme (mounted into the container)
```

## Running in LocalWP

1. Create a new site in LocalWP.
2. Copy `theme/poc-theme` into the site's `wp-content/themes/` and activate **POC Theme**,
   or import the full-site export package published with each release.
