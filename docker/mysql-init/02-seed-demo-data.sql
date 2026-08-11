-- OPTIONAL demo/reference data — safe to delete this file before first run.
--
-- The app bootstraps its own first Admin account through the UI
-- (buat_akun.php, shown automatically on login.php while no admin exists),
-- so this file does NOT create any user account. It only seeds one branch,
-- one position and one shift rule so a fresh install has something to pick
-- from immediately when adding the first employee, instead of empty dropdowns.

USE `db_absensi.kry`;

INSERT INTO `cabang` (`nama_cabang`, `alamat_cabang`, `latitude`, `longitude`, `radius_meter`)
SELECT 'Cabang Pusat', 'Alamat contoh — silakan sunting di menu Data Cabang', NULL, NULL, 100
WHERE NOT EXISTS (SELECT 1 FROM `cabang`);

INSERT INTO `jabatan` (`nama_jabatan`, `tunjangan_jabatan`)
SELECT 'Staff', 0
WHERE NOT EXISTS (SELECT 1 FROM `jabatan`);

INSERT INTO `jam_kerja` (`id_cabang`, `nama_shift`, `jam_masuk_akhir`, `jam_pulang`)
SELECT c.id, 'Shift Normal', '08:00:00', '17:00:00'
FROM `cabang` c
WHERE NOT EXISTS (SELECT 1 FROM `jam_kerja`)
LIMIT 1;
