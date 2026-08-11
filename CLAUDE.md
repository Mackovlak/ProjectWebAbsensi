# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

"AbsenSlip Dinia" — an employee attendance (absensi) and payroll-slip (slip gaji) management system for "Dinia House Of Hijab", built as plain procedural PHP + MySQLi with no framework, no build step, and no dependency manager (no composer.json, no vendor/, no package.json). Every page is a standalone `.php` file served directly by Apache. UI language and most identifiers/strings are Indonesian.

There are three roles: `admin`, `owner`, `staff`. Pages are prefixed by role (`admin_*.php`, `owner_*.php`, `staff_*.php`); files without a role prefix (e.g. `absen.php`, `jam_kerja.php`, `data_karyawan.php`) are shared/admin-oriented pages.

## Running the app

This is a classic LAMP-style app with no CLI build/test/lint tooling in the repo. Run it with a local PHP/MySQL stack (e.g. XAMPP/Laragon/`php -S`) pointed at this directory as the document root, with a MySQL database named `db_absensi.kry` (see `config.php`). There are no automated tests, linters, or build commands — verify changes by exercising the relevant page in a browser against a real database.

Useful one-off DB scripts (run directly via browser or `php <file>.php`, not part of any tooling pipeline):
- `check_db.php` — dumps `SHOW TABLES` for the connected database.
- `update_db.php` — example of the pattern used for ad-hoc idempotent schema migrations (check column exists via `SHOW COLUMNS`, `ALTER TABLE` if missing). There is no formal migration system; new schema changes are typically added as similar one-off scripts or applied directly to the DB.

## Architecture

**Bootstrap (`config.php`)**: every page starts with `require 'config.php'` (or `require_once`). It starts output buffering, configures session cookies (stricter flags — `Secure`, `SameSite=Strict` — when detected as non-localhost HTTPS, looser `SameSite=Lax` on localhost/LAN), opens the mysqli `$conn`, sets `Asia/Jakarta` timezone, and defines the core auth/utility helpers used everywhere:
- `isLoggedIn()`, `isAdmin()`, `isOwner()`, `isStaff()` — session role checks.
- `requireLogin()`, `requireAdmin()`, `requireAdminOrOwner()`, `requireStaff()` — guards that redirect on failure (though most `*_header.php` files re-check role directly against `$_SESSION['role']` rather than calling these).
- `sanitizeInput()` — trim/stripslashes/htmlspecialchars for POST/GET input.
- `generateCSRFToken()` / `verifyCSRFToken($token)` — session-based CSRF, `verifyCSRFToken` dies on mismatch.
- `regenerateSession()` — called on successful login.
- `redirect($url)`.

It also pulls in `security_functions.php`, which adds:
- `validatePassword()`, `checkRateLimit($action, $limit_seconds)` (session-based throttling, used e.g. for the attendance endpoint), `safe_output()`, `validateIDKaryawan()` (11-digit numeric employee ID format `YYYYMMDDXXX`), `logActivity($conn, $action, $description, $user_id)` (writes to `activity_logs`).
- `calculateDistance()` / `validateLokasiAbsen()` — Haversine-based geofencing: validates a staff member's submitted GPS coords against their branch (`cabang`) coordinates + `radius_meter`, used to gate "Hadir" (present) check-ins to on-site locations. Backward-compatible: if a branch has no lat/long configured, location validation is bypassed.

**Page pattern**: each role dashboard/list page (`admin_*.php`, `owner_*.php`, `staff_*.php`) is a self-contained file combining PHP data-fetch, inline HTML, and often inline `<script>`. Shared chrome is via `admin_header.php`/`admin_footer.php`, `owner_header.php`/`owner_footer.php`, `staff_header.php`/`staff_footer.php` — each `*_header.php` independently re-verifies the session role and redirects to `login.php` if it doesn't match, builds the avatar path (gendered default avatar via `jenis_kelamin`, or uploaded `foto_profil`), and (for admin/owner) queries pending-approval counts (e.g. "Pending Dinas" requests) for header notifications. `alert_messages.php` is included where flash messages are shown; it reads `$_SESSION['success_message']`/`$_SESSION['error_message']`, strips all but a small safe tag whitelist, and unsets them (so these session keys are single-shot, PRG-pattern flash messages set right before a `header("Location: ...")` redirect).

**Form/action processing is centralized, not per-page**: `master_process.php` is the single handler for essentially all admin CRUD (jam kerja/work-hour rules, jabatan/positions, cabang/branches, karyawan/employees including soft-delete via `nonaktifkan_karyawan` vs hard `hapus_karyawan`, face-recognition permission toggles, user/account management including owner creation). It dispatches purely on which `$_POST`/`$_GET` key is present (e.g. `isset($_POST['tambah_jam_kerja'])`), always uses prepared statements, and supports both classic redirect-with-flash-message flow and an `is_ajax` flag that instead returns JSON (`json_encode(['status' => ..., 'message' => ...])`) for use by frontend JS/SweetAlert-style calls. When adding new admin actions, follow this same dispatch-by-POST-key convention inside `master_process.php` rather than creating a new processor file, unless the action is domain-specific enough to warrant its own `proses_*.php` (see e.g. `proses_absen.php`, `proses_biodata.php`, `proses_acc_gaji.php`, `proses_persetujuan_dinas.php`, `proses_ganti_password.php`, `proses_upload_ttd.php`, `proses_buat_akun_mandiri.php`).

**Attendance flow (`proses_absen.php`)**: JSON API hit by the staff-facing check-in UI. Key behaviors to preserve when touching this file:
- Only the `Hadir` (present) keterangan requires face verification + GPS geofencing; `OFF`/`Sakit`/`Cuti`/`Alpha`/dinas-luar flows skip those checks.
- Face verification requires a pre-registered `face_descriptor` on the `users` row and a submitted `face_confidence` ≥ `$MIN_FACE_CONFIDENCE` (62.0).
- "Dinas Luar" (off-site duty) check-ins are recorded with keterangan `Pending Dinas` and require admin approval (`proses_persetujuan_dinas.php`) before being finalized as `Dinas Luar`.
- Same-day check-in/check-out is one row per employee per date in `absensi`; the handler first checks whether today's row already exists (`jam_masuk` set) to decide masuk vs pulang branch, uses a duplicate-submission guard (10s window) and a `FOR UPDATE` row lock inside a transaction on insert to avoid double check-ins.
- Late arrival (`status_masuk = 'Terlambat'`) is computed by comparing check-in time against `jam_kerja` rules for the employee's `id_cabang`, picking the closest shift rule when multiple exist.
- Checking out later than the matched shift's `jam_pulang` is treated as overtime and requires an uploaded photo + reason (`alasan_pulang`/`foto_pulang`) before the checkout is accepted.
- All responses go through `outputJSON()` which clears the output buffer first (defensive against stray warnings corrupting the JSON) and always `exit`s.

**Face recognition**: client-side only, via `assets/js/face-recognition.js` using `face-api.js` loaded from a CDN (`@vladmandic/face-api`) — no server-side ML. The browser computes a face descriptor/confidence and posts it to `proses_absen.php` (check-in) or `process_face_register.php`/`register_face.php` (registration) for the server to store/compare.

**Exports/reporting**: no PDF library is vendored. "Excel" exports (`export_*.php`) are actually CSV with a UTF-8 BOM and `;`-delimited `fputcsv`. "PDF" reports (`laporan_*_print.php`, `slip_gaji_form.php`, etc.) are plain HTML pages styled for browser print-to-PDF rather than server-generated PDFs.

**Security conventions already in place** (follow these for any new code rather than introducing new patterns):
- All DB writes/reads with user input use mysqli prepared statements (`$conn->prepare` + `bind_param`); a few older reads combine `real_escape_string` with prepared statements defensively.
- All state-changing POST forms include and verify a `csrf_token` from `generateCSRFToken()`/`verifyCSRFToken()`.
- `.htaccess` denies direct web access to `config.php`, itself, and files with sensitive extensions (`.sql`, `.log`, `.ini`, `.conf`, `.bak`, etc.), and sets basic security headers.
- Login (`login.php`) implements session-based rate limiting (5 attempts / 15 min lockout), regenerates the session ID on success, and looks up by `username` first, falling back to `id_karyawan` (preferring a `staff`-role account if an employee has multiple accounts).
- Employee soft-delete (`nonaktifkan_karyawan` in `master_process.php`) deletes the linked `users` login row (so the account can't log in) but keeps the `karyawan` row with `status = 'nonaktif'`; attendance/payroll history is never deleted when an employee record is removed, by explicit design.

## Key tables referenced in code (no schema file exists — inferred from queries)

`users` (login accounts; `id_karyawan` FK, `role` enum admin/owner/staff, `face_descriptor`, `face_registered_at`, `face_reset_allowed`, `foto_profil`, `ttd_path`), `karyawan` (employee master data; `id_karyawan` is the public-facing 11-digit ID, `id_jabatan`, `id_cabang`, `status` aktif/nonaktif, `tanggal_resign`), `cabang` (branches; `latitude`/`longitude`/`radius_meter` for geofencing), `jabatan` (positions; `tunjangan_jabatan` allowance), `jam_kerja` (per-branch shift rules; `jam_masuk_akhir`, `jam_pulang`), `absensi` (one row per employee per date; `jam_masuk`/`jam_pulang`, `lokasi_masuk`/`lokasi_pulang`, `keterangan`, `status_masuk`, `face_verified`/`face_confidence`, overtime fields), `activity_logs`, `login_logs`, `face_recognition_logs`.
