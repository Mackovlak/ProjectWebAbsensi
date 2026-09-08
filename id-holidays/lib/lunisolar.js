/**
 * Tahun Baru Imlek (Chinese lunisolar), Hari Suci Nyepi (Balinese Saka
 * calendar), and Hari Raya Waisak (Buddhist lunisolar) are NOT arithmetic
 * like the Hijri calendar - they depend on real astronomical
 * new-moon/full-moon calculations that vary by tradition and region.
 *
 * Rather than faking precision we don't have, this is an explicit lookup
 * table. It's seeded with the real, government-confirmed (SKB 3 Menteri)
 * dates for 2025 and 2026. When you need a year that isn't listed, add it
 * here from an authoritative source:
 *   - Imlek: any published Chinese calendar (astronomically fixed years
 *     ahead, safe to pre-fill for e.g. 2027-2030)
 *   - Nyepi: Parisada Hindu Dharma Indonesia (PHDI) pawukon/Saka calendar
 *   - Waisak: Kemenag / WALUBI (Perwakilan Umat Buddha Indonesia) announcement
 *
 * The generator will throw a clear error rather than silently guess when a
 * year is missing here - wrong data for a religious holiday is worse than
 * an error telling you to go add it.
 */

const LUNISOLAR_HOLIDAYS = {
  2025: {
    imlek: { date: '2025-01-29', label: 'Tahun Baru Imlek 2576 Kongzili' },
    nyepi: { date: '2025-03-29', label: 'Hari Suci Nyepi (Tahun Baru Saka 1947)' },
    waisak: { date: '2025-05-12', label: 'Hari Raya Waisak 2569 BE' },
  },
  2026: {
    imlek: { date: '2026-02-17', label: 'Tahun Baru Imlek 2577 Kongzili' },
    nyepi: { date: '2026-03-19', label: 'Hari Suci Nyepi (Tahun Baru Saka 1948)' },
    waisak: { date: '2026-05-31', label: 'Hari Raya Waisak 2570 BE' },
  },
  // Add future years here once the government/religious body publishes them, e.g.:
  // 2027: {
  //   imlek:  { date: '2027-02-06', label: 'Tahun Baru Imlek ... Kongzili' },
  //   nyepi:  { date: '2027-03-XX', label: 'Hari Suci Nyepi (Tahun Baru Saka ...)' },
  //   waisak: { date: '2027-05-XX', label: 'Hari Raya Waisak ... BE' },
  // },
};

function getLunisolarHoliday(year, key) {
  const yearData = LUNISOLAR_HOLIDAYS[year];
  if (!yearData || !yearData[key]) {
    throw new Error(
      `No "${key}" date on file for ${year}. This one can't be calculated - ` +
        `look up the official date and add it to lib/lunisolar.js, then re-run.`
    );
  }
  return yearData[key];
}

function hasYear(year) {
  return Boolean(LUNISOLAR_HOLIDAYS[year]);
}

module.exports = { getLunisolarHoliday, hasYear, LUNISOLAR_HOLIDAYS };
