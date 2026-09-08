# Database Migrations

This replaces the old scattered `update_db_*.php` scripts with one tracked
system: `migrate.php` (the runner), `migrations/` (one file per schema
change, in order), and `migration_helpers.php` (shared checks every
migration uses). If you're here because a production update went wrong
before, read **"Running in production"** below before you run anything.

## Why this exists

The old approach was: one standalone `.php` file per feature
(`update_db_izin.php`, `update_db_kalender.php`, ...), each checking
`SHOW COLUMNS`/`information_schema` before altering anything, so re-running
one was harmless. That part was fine. What was missing:

- **No record of what had actually been run on a given database.** Whether
  a script had already been applied to *this* environment was only ever a
  guess from memory or from reading its idempotent-check output.
- **No enforced order.** `update_db_kalender.php` and
  `update_db_izin_khusus.php` both explicitly required `update_db_izin.php`
  to run first, but nothing stopped you from running them out of order —
  you'd just get a confusing SQL error partway through, on production, with
  no clean way to know what state you'd been left in.
- **No authentication.** Anyone who knew (or guessed) the URL could load
  `update_db_izin.php` on a live server and it would run — no login check
  at all.

`migrate.php` fixes all three: a `schema_migrations` table records exactly
which migrations have run and when, migrations always apply in filename
order and each runs exactly once, and the web UI is `requireAdmin()`-gated.

## How it works

```
migrate.php               <- the runner (web + CLI)
migration_helpers.php     <- shared schema-check helpers + MigrationLog
migrations/
  001_users_wa_token.php
  002_users_active_dan_unique_karyawan.php
  003_pengajuan_izin.php
  004_kalender_hari_libur_dan_hari_kerja.php
  005_izin_khusus_dan_pulang_cepat.php
  006_hari_libur_unique_key_fix.php
```

Each file in `migrations/` returns an array:

```php
<?php
return [
    'name' => 'One-line description shown in the status list',
    'up' => function ($conn, MigrationLog $log) {
        if (!kolomAda($conn, 'users', 'wa_token')) {
            $conn->query("ALTER TABLE users ADD wa_token VARCHAR(255) NULL DEFAULT NULL");
            $log->ok("Kolom users.wa_token berhasil ditambahkan.");
        } else {
            $log->skip("Kolom users.wa_token sudah ada.");
        }
    },
];
```

`migrate.php` scans `migrations/*.php` sorted by filename, checks each
against the `schema_migrations` table, and runs every one that isn't
recorded yet — **in order, stopping at the first failure.** A migration is
only recorded once its `up()` callback finishes with no `$log->error(...)`
calls. If it fails, everything before it in that run stays recorded (they
already succeeded); the failed one and everything after it are left pending
for the next attempt.

### Why there's no automatic rollback

MySQL/InnoDB auto-commits every `CREATE TABLE`/`ALTER TABLE` — DDL can't be
wrapped in a transaction and undone the way an `INSERT`/`UPDATE` can. So
"rollback" here means: fix whatever broke, then re-run `migrate.php` — the
migration that failed (and anything after it) will retry; nothing before it
runs again. This is exactly why **backing up before you migrate** (see
below) is not optional, especially in production — it's the only real undo
button.

## Writing a new migration

1. Pick the next number: look at the highest-numbered file in `migrations/`
   and add one (`006_...` exists → your new file is `007_...`).
2. Name it descriptively: `007_short_description.php`.
3. Use the helpers already in `migration_helpers.php` — don't redefine your
   own column/table checks in the migration file:
   - `tabelAda($conn, 'nama_tabel')`
   - `kolomAda($conn, 'tabel', 'kolom')`
   - `indexAda($conn, 'tabel', 'nama_index', $kolom = null)`
   - `definisiKolom($conn, 'tabel', 'kolom')` — returns the raw `COLUMN_TYPE`
     string (useful for enums)
   - `enumMengandung($conn, 'tabel', 'kolom', 'Nilai')` — true if an enum
     column already contains that value
4. Log every branch with `$log->ok(...)`, `$log->skip(...)`, `$log->warn(...)`,
   or `$log->error(...)` — these are what the status page and CLI output
   show. Call `$log->error(...)` for anything that should stop the whole
   migration run; everything else keeps going.
5. If a later step in the same migration genuinely depends on an earlier
   step succeeding, `return;` right after logging the error, same as the
   existing migrations do (see `002_...php`'s duplicate-check for an
   example) — don't let it fall through and hit a fatal error instead.
6. Test locally first: `php migrate.php status` (see it listed as pending),
   `php migrate.php migrate --yes` (run it), `php migrate.php status` again
   (confirm it's now applied), then run `migrate --yes` **again** to confirm
   it's a no-op the second time (this is what idempotent actually means —
   don't skip this check).

You never need to touch `migrate.php` itself to add a migration — just drop
a new numbered file in `migrations/`.

## Running it

**Web** (requires being logged in as Admin): open `migrate.php`. It always
shows a status/preview list first — applied migrations with who ran them
and when, pending ones below. Nothing changes until you click "Jalankan
Migrasi Pending", which asks for confirmation and shows which
database/host you're about to touch before submitting.

**CLI:**
```bash
php migrate.php status          # preview only, changes nothing
php migrate.php migrate         # same preview + a reminder to add --yes
php migrate.php migrate --yes   # actually applies pending migrations
```

Both paths print the resolved `DB_HOST`/database name up front — check that
it says what you expect (especially in production) before confirming.

## Running in production

### Attendance camera capture (007)

Migration `007_foto_capture_absensi.php` adds only `absensi_capture`; it does
not alter existing attendance rows or leave/overtime attachments. Camera
JPEGs (maximum 512 KB each, 1280 pixels on the longest side) are stored in
this separate table, so they have no public upload URL and are not loaded
by existing attendance queries. Include this table in database backups;
photo storage will increase database and backup size.

Deploy the capture code, then follow the backup/status/apply/verify steps
below. Until 007 is applied, attendance keeps its existing behavior and the
review page shows a setup notice. Once applied, camera proof is required
for Hadir clock-in and Hadir/Dinas Luar clock-out. Old/manual records show
"Belum ada foto". HRD uses the existing Admin role; supervisors can only
view their assigned branch via `histori_absensi.php` (read-only for supervisors)
and the authenticated image endpoint. The **Bukti Foto** action opens clock-in
and clock-out photos together. Verify both roles, clock-in, clock-out, and the overtime
follow-up form after deployment. Face confidence logic is unchanged.

This is the part that matters if a past update caused instability. Do these
in order, every time:

1. **Back up the database first.** Non-negotiable, since DDL can't be
   rolled back (see above):
   ```bash
   mysqldump -u root -p 'db_absensi.kry' | gzip > backup-$(date +%F-%H%M).sql.gz
   ```
2. **Check status before touching anything**: `php migrate.php status`.
   Confirm the pending list is exactly what you expect from your `git pull`
   — if you see migrations pending that you didn't expect, stop and figure
   out why before proceeding (wrong branch? wrong database?).
3. **Apply during low traffic**, not during business hours if you can help
   it — a long-running `ALTER TABLE` on a large table can lock it.
4. **Run it**: `php migrate.php migrate --yes` (or the web UI, logged in as
   Admin). Read the output. If it stops on a failure, **do not panic and
   re-run repeatedly** — read the specific error, fix the underlying cause
   (often a data issue, like the duplicate-row check in migration `002`),
   then run it again once.
5. **Verify**: `php migrate.php status` should now show everything applied.
   Spot-check the actual feature the migration was for (e.g., after a leave
   quota migration, open a staff account and check the leave request page
   loads).
6. If something looks wrong after a migration and you can't quickly fix it
   forward, restore from the backup in step 1 rather than trying to
   hand-write reverse `ALTER` statements under pressure.

## What happened to the old `update_db_*.php` files

They're gone — their exact logic now lives in `migrations/001` through
`006` (ported faithfully, not rewritten from scratch). If you had any of
their URLs bookmarked, they'll 404 now; use `migrate.php` instead.
