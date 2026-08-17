# Running AbsenKita Javag in Docker

This is a local **testing/dev** setup, not a production deployment (see the
"not for production" notes at the bottom).

## What's included

- `web` — PHP 8.2 + Apache (mysqli, mod_rewrite, mod_headers, `.htaccess`
  support enabled), serving this repo directly via a bind mount so edits on
  the host show up immediately, no rebuild needed.
- `db` — MySQL 8.0, auto-initialized on first start from
  `docker/mysql-init/01-schema.sql` (copied from a real `mysqldump` of the
  production database, `db_absensi_qr_schema.sql` at the repo root — not
  reverse-engineered) and `docker/mysql-init/02-seed-demo-data.sql` (one
  demo branch/position/shift so dropdowns aren't empty; delete this file
  before first run if you don't want it).
- `phpmyadmin` — optional DB browser.

## Quick start

```bash
cp .env.example .env      # optional — defaults work as-is
docker compose up -d --build
```

Then open:
- **App**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081 (server `db`, user `root`, password
  from `.env`'s `DB_ROOT_PASSWORD`, default `rootpass`)

On first load, `login.php` will show a **"Buat Master Admin"** button —
that's the app's own bootstrap flow (see `buat_akun.php`), not something
this Docker setup adds. Use it to create the first Admin account, then log
in and start adding branches/positions/shifts/employees from there.

## Useful commands

```bash
docker compose logs -f web        # PHP/Apache logs
docker compose logs -f db         # MySQL logs
docker compose exec db mysql -u${DB_USER:-absensi} -p${DB_PASS:-absensi} ${DB_NAME:-db_absensi.kry}
docker compose down               # stop (keeps the db_data volume)
docker compose down -v            # stop AND wipe the database
```

## How the pieces connect

- `config.php` now reads `DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME` from the
  environment, falling back to the original hardcoded
  `localhost`/`root`/``/`db_absensi.kry` values if unset — so this change is
  backward compatible with a plain XAMPP/Laragon setup outside Docker.
  `docker-compose.yml` sets those four vars on the `web` service to point at
  the `db` service.
- Uploaded files (attendance photos, signatures, profile pictures) land in
  `assets/uploads/`, which is part of the bind-mounted repo — they persist
  on your host filesystem, not just inside the container.
- `docker/php/entrypoint.sh` chmods `assets/uploads/` to `777` on container
  start. That's specifically to sidestep host/container UID mismatches on a
  bind mount for local testing — do not carry that into any real deployment.

## Not for production

This setup optimizes for "get the app running to click around in it":
- DB credentials are placeholders in `.env.example`.
- `assets/uploads/` is world-writable inside the container.
- No HTTPS/TLS termination — `config.php`'s stricter cookie flags
  (`Secure`, `SameSite=Strict`) won't engage, since they only trigger when
  the app detects non-localhost HTTPS.
- The schema in `docker/mysql-init/01-schema.sql` is copied from a real
  production dump, with one addition: `face_admin_logs`, which
  `toggle_face_reset_permission.php` requires but which was absent from
  the dump (see the comment at the top of the file). If the running app
  ever errors with "Unknown column" or "Table doesn't exist", trust the
  PHP code over this file and patch the schema to match.
