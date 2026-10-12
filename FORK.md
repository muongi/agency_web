# SUNU Assurances Vie — Agency Back Office (fork)

Isolated fork of the agency banking platform for SUNU premium collection (pilot).

- Source: `/var/www/html/agency`
- DB: `sunu_agency`
- APP_URL: `http://localhost:8001`
- Seed: `php artisan db:seed --class=SunuPilotSeeder`

## Pilot credentials

| Role | Login | Password |
|------|-------|----------|
| Web super admin | sunu@gmail.com | 123456789 |
| Field collector | collector1@sunu.bf | SunuPilot2026! |
| Terminal serial | SUNU-TPE-001 | |

## API smoke

```bash
php artisan serve --host=127.0.0.1 --port=8001
curl "http://127.0.0.1:8001/api/v1/sunu/products?business_unit_id=1"
```

See `docs/SUNU_BACKEND_API.md`.

## Hosted pilot instance

| Item | Value |
|------|-------|
| URL | http://213.136.64.220:8888 |
| Server | `ssh -p 2223 muongi@213.136.64.220` (alias `mw`) |
| Path | `/var/www/html/sunu_agency` |
| Apache vhost | `/etc/httpd/conf.d/sunu.conf` (owns its own `Listen 8888`) |
| Database | `sunu` on local MySQL, user `root`, empty password (same convention as the other apps on this box) |

Deploy an update from the workstation:

```bash
rsync -az --delete -e "ssh -p 2223" \
  --exclude '.git' --exclude 'node_modules' --exclude '.env' \
  --exclude 'storage/logs/*' --exclude 'storage/framework/cache/*' \
  --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  --exclude 'public/storage' \
  /var/www/html/sunu_agency/ muongi@213.136.64.220:/var/www/html/sunu_agency/
ssh -p 2223 muongi@213.136.64.220 'cd /var/www/html/sunu_agency && php artisan migrate --force && php artisan view:clear'
```

`vendor/` is shipped by rsync because the server has no composer, and no asset build is needed
(`public/css/app.css` is committed). `storage/` and `bootstrap/cache` are owned by `apache`, so
writes into `storage/app/public` need `sudo` on the server.

## Product catalogue

`Collecte primes > Produits` is the editable catalogue (`business/sunu/products`). Each product carries
its DGTCP visa number, category and guarantee text; active products are served by
`GET /api/v1/sunu/products?business_unit_id=1` and shown as a dropdown on the TPE before any detail or
photo is captured. Products are activated or deactivated rather than deleted, because proposals and
policies reference them.
