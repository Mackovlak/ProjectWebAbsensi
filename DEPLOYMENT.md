# Deploying AbsenKita Javag to a production VPS (Nginx)

This is a step-by-step guide to deploy this app on a bare Linux VPS with
**Nginx + PHP-FPM + MySQL**, systemd-managed, with HTTPS. The Docker setup
(`DOCKER.md`) is for local dev only — this document is the production path.

Target stack: **Ubuntu 22.04/24.04 LTS**, **Nginx**, **PHP 8.1-FPM**,
**MySQL 8.0**, **Let's Encrypt/Certbot**. Commands assume `apt`; adapt for
other distros.

> Read this whole file before running anything — several steps (schema
> import, cron secrets, file permissions) depend on earlier ones.

---

## 0. Why HTTPS is not optional here

Two core features stop working over plain HTTP, because browsers only
expose these APIs in a "secure context" (HTTPS or `localhost`):

- **Face recognition check-in** (`assets/js/face-recognition.js`) uses
  `getUserMedia()` to access the camera.
- **GPS geofencing** (`proses_absen.php` / `validateLokasiAbsen()`) uses the
  browser Geolocation API.

`config.php` also only applies the hardened session-cookie flags
(`Secure`, `SameSite=Strict`) when it detects a non-localhost **HTTPS**
request — over plain HTTP in production you'd silently keep the looser
dev-mode cookie settings. So: get a domain, get a cert, done before you
consider this "deployed."

---

## 1. Prerequisites

- A VPS (2 vCPU / 2GB RAM is comfortable; this app has no heavy
  server-side compute — face recognition runs in the browser).
- A domain name with an **A record** pointing at the VPS's public IP.
- Root or sudo SSH access.
- The project's `db_absensi_qr_schema.sql` (the real schema dump) and the
  app source, ready to upload (via `git clone` or `rsync`/`scp`).

---

## 2. Base server hardening

```bash
# As root, on first login
apt update && apt upgrade -y

# Create a non-root sudo user, stop using root for day-to-day work
adduser deploy
usermod -aG sudo deploy
# log out, log back in as `deploy` from here on

# Firewall: allow SSH, HTTP, HTTPS only
sudo apt install -y ufw
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status

# Brute-force protection for SSH (and later, can watch nginx/php logs too)
sudo apt install -y fail2ban
sudo systemctl enable --now fail2ban
```

Do **not** open port 3306 (MySQL) or any Docker-adjacent port to the
internet — MySQL should only ever be reached from `localhost` in this
setup (see §5).

---

## 3. Install Nginx, PHP-FPM, MySQL

```bash
sudo apt install -y nginx mysql-server \
  php8.1-fpm php8.1-mysqli php8.1-mbstring php8.1-xml php8.1-curl \
  php8.1-gd php8.1-zip php8.1-opcache

php -v            # sanity check: should report PHP 8.1.x
systemctl status nginx php8.1-fpm mysql
```

Notes:
- `php8.1-mysqli` is the only *required* extension the app calls directly
  (`config.php` opens the DB via `mysqli`). The others above are common
  PHP hygiene/perf extras, safe defaults.
- Use **MySQL**, not MariaDB. The real schema (`db_absensi_qr_schema.sql`)
  uses `utf8mb4_0900_ai_ci` on a couple of tables (`face_recognition_logs`,
  `system_settings`), a MySQL 8.0+-only collation that MariaDB doesn't
  support — importing it as-is onto MariaDB will error.

---

## 4. Secure MySQL and create the app database/user

```bash
sudo mysql_secure_installation
# set a strong root password, remove anonymous users, disallow remote
# root login, remove the test database — answer "Y" to all prompts.
```

Create a dedicated, least-privilege DB user (never use `root` from the app):

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE `db_absensi.kry` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'absensi_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_TO_A_LONG_RANDOM_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON `db_absensi.kry`.* TO 'absensi_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

`SELECT/INSERT/UPDATE/DELETE` only — the app never runs DDL at runtime
(schema changes are applied by hand, per `CLAUDE.md`), so the app account
doesn't need `CREATE`/`ALTER`/`DROP`. Keep a separate admin login (root,
or another account with DDL rights) for you to run migrations with.

---

## 5. Import the real schema

Upload `db_absensi_qr_schema.sql` to the VPS (or use it straight from the
repo checkout) and import it **as `root`, not as `absensi_app`**:

```bash
mysql -u root -p 'db_absensi.kry' < db_absensi_qr_schema.sql
# or, if root auth is via the unix socket (common on a fresh Ubuntu
# mysql-server install, no password set for root@localhost):
sudo mysql -u root 'db_absensi.kry' < db_absensi_qr_schema.sql
```

> **Why root and not `absensi_app`**: the dump is schema DDL, not data —
> every table starts with `DROP TABLE IF EXISTS`, then `CREATE TABLE`, and
> it ends with a `CREATE VIEW ... DEFINER=`root`@`localhost``. The
> `absensi_app` user from §4 only has `SELECT/INSERT/UPDATE/DELETE`
> (deliberately — the app never runs DDL at runtime), so it can't `DROP`,
> `CREATE`, or set a `DEFINER` — importing with it fails partway through
> with something like `ERROR 1142 (42000): DROP command denied to user
> 'absensi_app'@'localhost' for table 'absensi'`. Load the schema with a
> privileged account once; `absensi_app` only needs to exist for the app's
> normal runtime queries afterward, which is what §4 already set up.

**Before you do**, check one known gap: `toggle_face_reset_permission.php`
writes to a table called `face_admin_logs` on every face-reset admin
action, but that table was **absent** from the real dump this repo's
Docker setup was built against (see `docker/mysql-init/01-schema.sql` for
the exact `CREATE TABLE` this project used to patch that gap locally).
Run this against your production DB before going live:

```sql
SHOW TABLES LIKE 'face_admin_logs';
```

If it returns nothing, create it (matches what `toggle_face_reset_permission.php`
inserts — `admin_id`, `target_id_karyawan`, `action_type`, `ip_address`):

```sql
CREATE TABLE `face_admin_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `target_id_karyawan` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `action_type` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_face_admin_logs_target` (`target_id_karyawan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

Without this table, every "allow reset / delete face / lock face" admin
action will throw and roll back (it's inside a transaction with no
silent-catch, per `toggle_face_reset_permission.php`).

---

## 6. Deploy the application code

```bash
sudo mkdir -p /var/www/absenslip
sudo chown deploy:deploy /var/www/absenslip
git clone <your-repo-url> /var/www/absenslip
cd /var/www/absenslip
```

(Or `rsync -a --exclude .git ./ deploy@your-vps:/var/www/absenslip/` from
your machine if you're not pulling from a remote.)

There is **no build step** — no `composer install`, no `npm install`, no
compiled assets. The checked-out PHP files are served as-is. All frontend
libraries (Tailwind, SweetAlert2, Chart.js/ApexCharts, DataTables,
face-api.js, html2canvas, jsPDF) load from public CDNs at request time —
no local vendoring to worry about.

### File ownership & permissions

```bash
sudo chown -R deploy:www-data /var/www/absenslip
sudo find /var/www/absenslip -type d -exec chmod 750 {} \;
sudo find /var/www/absenslip -type f -exec chmod 640 {} \;

# The app writes here at runtime: attendance photos, signatures/stamps,
# profile pictures, overtime proof photos.
sudo mkdir -p /var/www/absenslip/assets/uploads/absensi
sudo chown -R www-data:www-data /var/www/absenslip/assets/uploads
sudo find /var/www/absenslip/assets/uploads -type d -exec chmod 775 {} \;
sudo find /var/www/absenslip/assets/uploads -type f -exec chmod 664 {} \;
```

Do **not** carry over the Docker dev setup's `chmod 777` on uploads
(`docker/php/entrypoint.sh`) — that exists only to paper over host/container
UID mismatches on a bind mount. In production, `www-data` owns the
directory outright, so `775`/`664` is enough and is the safer default.

---

## 7. Wire up environment variables for PHP-FPM

`config.php` already reads DB credentials from environment variables with
hardcoded-default fallbacks:

```php
$host = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "";
$database = getenv('DB_NAME') ?: "db_absensi.kry";
```

On a bare VPS (no Docker Compose to inject these), set them in the
**PHP-FPM pool** so `getenv()` picks them up for every request. Edit the
pool config:

```bash
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
```

Add (or create a dedicated pool file — either works):

```ini
env[DB_HOST] = localhost
env[DB_USER] = absensi_app
env[DB_PASS] = CHANGE_ME_TO_A_LONG_RANDOM_PASSWORD
env[DB_NAME] = db_absensi.kry
```

**PHP-FPM strips the environment by default** — `env[]` lines in the pool
file are the fix; just exporting shell env vars before starting the
service will *not* work. Restart to apply:

```bash
sudo systemctl restart php8.1-fpm
```

(Alternative, if you'd rather not touch pool config: hardcode the four
values directly in `config.php`'s fallback defaults instead. Either is
fine for this app — there's no secrets manager/vault integration to wire
up, this is a single-VPS deployment.)

---

## 8. Nginx server block

Create `/etc/nginx/sites-available/absenslip`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com www.your-domain.com;

    # Let certbot's HTTP-01 challenge through; everything else -> HTTPS
    location /.well-known/acme-challenge/ {
        root /var/www/absenslip;
    }
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com www.your-domain.com;

    root /var/www/absenslip;
    index index.php;

    # --- SSL (certbot fills these in — see step 9) ---
    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparam.pem;

    # --- Security headers (mirrors what .htaccess sets for Apache/local) ---
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains" always;

    # This app has no public/ split -- the whole repo is the webroot -- so
    # block everything that isn't meant to be served, matching (and going
    # further than) the .htaccess rules used in local/Apache setups.

    # Deny dotfiles (.env, .git, .htaccess, ...)
    location ~ /\. {
        deny all;
    }

    # Deny direct access to config.php outright (it self-guards too, but
    # belt-and-suspenders costs nothing) and to any sensitive extensions.
    location ~* \.(sql|log|ini|conf|bak|backup|swp|old|~|md)$ {
        deny all;
    }
    location = /config.php {
        deny all;
    }

    # Don't serve the docker/ folder, schema dump, or docs in production.
    location ~ ^/(docker|\.claude)/ {
        deny all;
    }
    location ~* ^/(db_absensi_qr_schema\.sql|docker-compose\.yml|DOCKER\.md|DEPLOYMENT\.md|CLAUDE\.md|README\.md)$ {
        deny all;
    }

    # Uploaded content: serve as static files, never execute as PHP.
    location ^~ /assets/uploads/ {
        location ~ \.php$ { deny all; }
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        # Matches docker/php/uploads.ini so overtime/manual-entry photo
        # uploads (up to ~6MB per proses_absen.php) actually go through.
        client_max_body_size 12m;
    }

    # Static assets: long cache, no PHP handling
    location ~* \.(?:css|js|jpg|jpeg|png|gif|ico|svg|woff2?)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    access_log /var/log/nginx/absenslip.access.log;
    error_log  /var/log/nginx/absenslip.error.log;
}
```

Enable it:

```bash
sudo ln -s /etc/nginx/sites-available/absenslip /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
```

`nginx -t` will fail right now because the cert files referenced above
don't exist yet — that's expected, fix it in the next step.

---

## 9. HTTPS via Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx

# Temporarily comment out the ssl_certificate/ssl_certificate_key lines
# in the 443 block (or just run certbot before adding them at all) --
# certbot can also edit the config for you directly:
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

Certbot will fetch the cert, rewrite the server block to point at it, and
offer to add the HTTP→HTTPS redirect (say yes, or keep the manual one
above — don't end up with both). Verify auto-renewal is wired up:

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run
```

Reload nginx once the cert is in place:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Visit `https://your-domain.com` — you should land on the `index.php`
welcome page.

---

## 10. PHP hardening

Edit `/etc/php/8.1/fpm/php.ini`:

```ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php8.1-fpm-absenslip-error.log

; Matches docker/php/uploads.ini -- proses_absen.php accepts up to ~6MB
; photo uploads (overtime/manual-entry proof); leave headroom.
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 256M
max_execution_time = 60

; Session hardening beyond what config.php sets per-request
session.use_strict_mode = 1
```

`config.php` already sets `error_reporting(0)` / `display_errors=0` at
runtime once it detects a non-localhost host, and independently sets
`Secure`/`HttpOnly`/`SameSite=Strict` session cookie flags over HTTPS —
the `php.ini` changes above are the server-level backstop for the same
intent, not a replacement for it.

```bash
sudo systemctl restart php8.1-fpm
```

---

## 11. Cron: the WhatsApp check-in reminder

`cron_reminder_absensi.php` is meant to run outside the request cycle,
documented for **18:15 WIB** (`Asia/Jakarta`). It needs a `wa_token`
(Fonnte API token) already saved on a `users` row — set that from the app
UI (Pengaturan → WhatsApp token) before relying on the cron.

```bash
sudo crontab -u www-data -e
```

Add:

```cron
# AbsenKita Javag - remind staff who haven't checked in yet, 18:15 WIB
15 18 * * * /usr/bin/php8.2 /var/www/absenslip/cron_reminder_absensi.php >> /var/log/absenslip-cron.log 2>&1
```

Make sure the server's system timezone matches, or adjust the cron
schedule to compensate:

```bash
sudo timedatectl set-timezone Asia/Jakarta
timedatectl
```

---

## 12. Backups

Nothing in this repo automates backups — set up your own:

```bash
sudo mkdir -p /var/backups/absenslip
sudo crontab -u root -e
```

```cron
# Nightly DB dump, keep 14 days
0 2 * * * mysqldump -u root -p'ROOT_PASSWORD' 'db_absensi.kry' | gzip > /var/backups/absenslip/db-$(date +\%F).sql.gz && find /var/backups/absenslip -name 'db-*.sql.gz' -mtime +14 -delete
```

Also back up `assets/uploads/` (attendance photos, signatures, stamps,
profile pictures) — it's real user data living on the filesystem, not
just in the DB:

```cron
0 3 * * * tar czf /var/backups/absenslip/uploads-$(date +\%F).tar.gz -C /var/www/absenslip assets/uploads && find /var/backups/absenslip -name 'uploads-*.tar.gz' -mtime +14 -delete
```

Copy both off-box (e.g. `rsync`/`rclone` to another host or object
storage) — a backup that lives only on the same VPS doesn't survive a
disk failure.

---

## 13. App-specific security items to close before going live

These are gaps documented in this codebase's own `README.md`/`CLAUDE.md`
that matter more once the app is on the public internet:

1. **Hardcoded admin/owner self-registration secrets.**
   `daftar_admin.php` and `buat_akun_owner.php` let anyone who can compute
   a time-based code register themselves as **Admin**/**Owner** — the
   salts (`DINIA_ADMIN_SECRET_2026`, `DINIA_OWNER_SECRET_2026`) are
   literal strings in those files. Before going live, do at least one of:
   - Change both salts to long random values only you know, or
   - Restrict both URLs at the nginx layer (IP allowlist or HTTP basic
     auth in front of them) so they aren't reachable by the public at all, or
   - Remove/disable the routes entirely if you don't need self-service
     admin/owner registration in production.

   Example nginx snippet for the IP-allowlist option (add inside the 443
   server block, above the generic `location ~ \.php$` block):
   ```nginx
   location ~ ^/(daftar_admin|buat_akun_owner)\.php$ {
       allow 203.0.113.10;   # your office/home IP
       deny all;
       include snippets/fastcgi-php.conf;
       fastcgi_pass unix:/run/php/php8.1-fpm.sock;
       fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
   }
   ```

2. **`absen.php?id=<id_karyawan>`** is intentionally *not* behind login
   (it's the QR-code/WhatsApp-link kiosk check-in page). That's by
   design, not a bug — but it does mean it's worth rate-limiting at the
   nginx layer against abuse/spam, e.g.:
   ```nginx
   limit_req_zone $binary_remote_addr zone=absen:10m rate=10r/m;
   # ...inside the 443 server block:
   location = /absen.php {
       limit_req zone=absen burst=5 nodelay;
       include snippets/fastcgi-php.conf;
       fastcgi_pass unix:/run/php/php8.1-fpm.sock;
       fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
   }
   ```
   (`limit_req_zone` goes in the `http {}` block of `/etc/nginx/nginx.conf`.)

3. **Login brute-force**: `login.php` already rate-limits at the app
   layer (5 attempts / 15 min, session-based). Fail2ban's nginx jails can
   add a second, IP-based layer on top if you see repeated failed-login
   traffic in `access_log`.

4. **phpMyAdmin**: the Docker dev setup includes it; production doesn't
   need it. If you want a DB GUI in production, reach MySQL over an SSH
   tunnel (`ssh -L 3306:localhost:3306 deploy@your-vps`) rather than
   exposing phpMyAdmin (or MySQL's port) publicly.

---

## 14. Post-deploy verification

Run through this once, on the real domain, over HTTPS:

1. Load `https://your-domain.com/` — landing page renders, no mixed-content
   warnings in the browser console.
2. `login.php` shows **"Buat Master Admin"** (fresh DB, zero admins) — use
   it to create the first Admin account.
3. Log in as that Admin, confirm `admin_dashboard.php` loads with no PHP
   warnings/errors (check `error_log` too, since `display_errors` is off).
4. Add a branch (`data_cabang.php`) with real `latitude`/`longitude`/
   `radius_meter` — geofencing is bypassed for branches without
   coordinates, so this is what turns it on.
5. Create a Staff account, log in as Staff, register a face
   (`register_face.php`) — this only works over HTTPS (camera permission
   prompt should appear; on plain HTTP it silently can't access the
   camera).
6. Do a real check-in from a phone/laptop with location services on,
   confirm it's accepted/rejected correctly based on distance from the
   branch coordinates.
7. Confirm uploaded photos actually land in `assets/uploads/absensi/` on
   the server with `www-data` ownership.
8. Trigger `cron_reminder_absensi.php` manually once
   (`sudo -u www-data php8.1 cron_reminder_absensi.php`) to confirm the
   Fonnte WhatsApp token works before trusting the cron schedule.
9. Run the full payroll path once: build a slip gaji (Admin), ACC as
   Admin (requires TTD upload first), ACC as Owner (requires TTD + stempel
   upload first), confirm the Staff can see the approved slip.

---

## 15. Deploying updates later

No build step means updates are just a file sync + (occasionally) a
manual schema change:

```bash
cd /var/www/absenslip
git pull
# if the change includes a schema update, apply it by hand (or via a new
# one-off script like update_db.php) against the production DB -- there
# is no migration runner in this project.
sudo systemctl reload php8.1-fpm   # only needed if opcache is enabled and
                                    # you want to force a cache bust
```

If you enabled `opcache` (recommended for perf — install
`php8.1-opcache`, it's in the §3 install list), either set a short
`opcache.revalidate_freq` in dev-adjacent environments or reload
`php8.1-fpm` after every deploy so changed files are picked up
immediately.

---

## 16. Quick troubleshooting

| Symptom | Likely cause |
|---|---|
| `ERROR 1142 ... DROP/CREATE command denied to user 'absensi_app'@...` while importing the schema | You imported with the restricted app user instead of `root` — see §5. Re-run the import as `root`, then let `absensi_app` handle runtime queries only. |
| Blank page / 500 | Check `/var/log/nginx/absenslip.error.log` and `error_log` from php.ini — `display_errors` is off in production by design. |
| "Connection failed" on every page | PHP-FPM pool `env[DB_*]` not set/not reloaded, or MySQL user/password/privileges wrong (§4/§7). |
| Camera or GPS prompt never appears | Not actually on HTTPS, or on an `www.`/bare-domain mismatch vs. the cert's SANs. |
| Uploads fail / "Nonaktifkan" photo actions error | `assets/uploads/` not owned by `www-data`, or `client_max_body_size`/`upload_max_filesize`/`post_max_size` too small (§8/§10). |
| Face-reset admin actions fail every time | Missing `face_admin_logs` table — see §5. |
| WhatsApp reminders never send | No `wa_token` saved on any `users` row, or the Fonnte account/token is invalid — test manually per §14 step 8. |
