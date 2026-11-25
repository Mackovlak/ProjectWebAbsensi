# Archived migrations

`migrate.php` only reads `migrations/*.php` (non-recursive `glob`), so files in
this folder are **never applied**. They are kept for features that were built
and then shelved, in case the policy changes back.

To revive one: move it back into `migrations/`, rename it to the next free
number (after the current highest file), restore its code (below), then run
`php migrate.php status` / `php migrate.php migrate --yes`.

---

## 017_pengajuan_izin_potongan_telat.php: half-day late-cut penalty (shelved 2026-11-24)

**What it was:** from the 4th half-day request in a month
(`IZIN_SETENGAH_HARI_MAKS_PER_BULAN`), the approver could tick
"Kenakan potongan keterlambatan" when approving. If ticked, at check-in that day
was recorded `Terlambat` with `menit_terlambat` = the branch's max
(`maks_jam * 60`), so payroll applied the existing tiered late deduction.

**Why shelved:** company policy is that exceeding the monthly limit (3 half-days,
and separately 3 early leaves) has **no penalty**. It is only tagged for
approvers. There was also an unresolved question of whether an early-leave
penalty should match the half-day one.

**Never committed to git:** this code existed only as uncommitted work, so this
README is the only copy. To restore, re-add these pieces:

### `kelola_pengajuan_izin.php`: inside the review `<form>`, before the "Catatan" label

```php
<?php if (!empty($row['setengah_hari']) && $lewat_batas): ?>
    <label class="flex items-start gap-3 mb-4 px-4 py-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 cursor-pointer">
        <input type="checkbox" name="kenakan_potongan_telat" value="1" class="mt-1 accent-amber-600">
        <span class="text-sm text-amber-900 dark:text-amber-200">
            <b>Kenakan potongan keterlambatan</b><br>
            <span class="text-xs">Ini setengah hari ke-<?php echo $setengah_ke; ?> bulan ini (batas wajar <?php echo IZIN_SETENGAH_HARI_MAKS_PER_BULAN; ?>x).
            Bila dicentang dan disetujui, hari tersebut dicatat terlambat pada slip gaji dengan tarif potongan keterlambatan yang berlaku.
            Dibiarkan kosong = disetujui tanpa potongan.</span>
        </span>
    </label>
<?php endif; ?>
```

Plus a badge next to the "Setengah hari ke-N" line (a similar one existed in
`staff_pengajuan_izin.php`'s request list):

```php
<?php if (!empty($row['kena_potongan_telat'])): ?>
    <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-semibold">
        <i class="ph-bold ph-scissors"></i> Dikenai potongan keterlambatan
    </span>
<?php endif; ?>
```

### `proses_pengajuan_izin.php`: `review_izin` handler

```php
$kenakan_potongan_telat = !empty($_POST['kenakan_potongan_telat']);
// ... in the approve branch, server re-verifies it is really past the limit:
$potongan_telat = 0;
if ($is_setengah_hari) {
    $setengah_ke = urutanPengajuanSetengahHariBulan($conn, $data['id_karyawan'], $data['tanggal_mulai'], $id_pengajuan);
    if ($kenakan_potongan_telat && $setengah_ke > IZIN_SETENGAH_HARI_MAKS_PER_BULAN) {
        $potongan_telat = 1;
    }
}
// ... and the approval UPDATE also sets: kena_potongan_telat = ?   (bind $potongan_telat)
```

### `izin_functions.php`: `getIzinSetengahHariDisetujui()`

Add `kena_potongan_telat` back to the `SELECT` column list.

### `proses_absen.php`: check-in (masuk) branch, after the Terlambat calculation

```php
if ($keterangan === 'Hadir' && $izin_setengah_hari_ini && !empty($izin_setengah_hari_ini['kena_potongan_telat'])) {
    $status_masuk = 'Terlambat';
    $menit_terlambat = (int)round(getPengaturanKeterlambatan($conn, $id_cabang)['maks_jam'] * 60);
}
```

No payroll file needs changing: `slip_gaji_*` already deducts by `status_masuk` / `menit_terlambat`.
