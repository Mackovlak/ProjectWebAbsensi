const { toISODate, dayOfWeekID } = require('./julian');
const { goodFriday, easterSunday, ascensionDay } = require('./christian');
const { findHijriEventInGregorianYear } = require('./hijri');
const { getLunisolarHoliday, hasYear } = require('./lunisolar');

// month/day are fixed by Indonesian law every year - zero calculation needed.
const FIXED_HOLIDAYS = [
  { month: 1, day: 1, label: 'Tahun Baru Masehi' },
  { month: 5, day: 1, label: 'Hari Buruh Internasional' },
  { month: 6, day: 1, label: 'Hari Lahir Pancasila' },
  { month: 8, day: 17, label: 'Hari Proklamasi Kemerdekaan Republik Indonesia' },
  { month: 12, day: 25, label: 'Hari Raya Natal' },
];

// (hijriMonth, hijriDay) for each Hijri-calendar holiday. Month numbers are
// standard Islamic calendar months (1=Muharram ... 12=Dzulhijjah).
const HIJRI_HOLIDAYS = [
  { hijriMonth: 7, hijriDay: 27, label: "Isra Mi'raj Nabi Muhammad SAW" },
  { hijriMonth: 10, hijriDay: 1, label: 'Hari Raya Idul Fitri (hari ke-1)' },
  { hijriMonth: 10, hijriDay: 2, label: 'Hari Raya Idul Fitri (hari ke-2)' },
  { hijriMonth: 12, hijriDay: 10, label: 'Hari Raya Idul Adha' },
  { hijriMonth: 1, hijriDay: 1, label: 'Tahun Baru Islam' },
  { hijriMonth: 3, hijriDay: 12, label: 'Maulid Nabi Muhammad SAW' },
];

function makeEntry(g, label, type, confidence) {
  return {
    date: toISODate(g),
    day: dayOfWeekID(g.year, g.month, g.day),
    name: label,
    type, // 'fixed' | 'christian' | 'hijri' | 'lunisolar'
    confidence, // 'exact' | 'estimated (±1 day)' | 'reference (looked up)'
  };
}

function generateNationalHolidays(year) {
  const holidays = [];

  for (const h of FIXED_HOLIDAYS) {
    holidays.push(makeEntry({ year, month: h.month, day: h.day }, h.label, 'fixed', 'exact'));
  }

  holidays.push(makeEntry(goodFriday(year), 'Wafat Yesus Kristus (Jumat Agung)', 'christian', 'exact'));
  holidays.push(makeEntry(easterSunday(year), 'Kebangkitan Yesus Kristus (Paskah)', 'christian', 'exact'));
  holidays.push(makeEntry(ascensionDay(year), 'Kenaikan Yesus Kristus', 'christian', 'exact'));

  for (const h of HIJRI_HOLIDAYS) {
    const candidates = findHijriEventInGregorianYear(year, h.hijriMonth, h.hijriDay);
    for (const c of candidates) {
      holidays.push(makeEntry(c.gregorian, h.label, 'hijri', 'estimated (±1 day - confirm against Kemenag/SKB)'));
    }
  }

  if (hasYear(year)) {
    for (const key of ['imlek', 'nyepi', 'waisak']) {
      const entry = getLunisolarHoliday(year, key);
      const [y, m, d] = entry.date.split('-').map(Number);
      holidays.push(makeEntry({ year: y, month: m, day: d }, entry.label, 'lunisolar', 'reference (looked up)'));
    }
  } else {
    holidays.push({
      date: null,
      day: null,
      name: 'Tahun Baru Imlek / Nyepi / Waisak',
      type: 'lunisolar',
      confidence: `MISSING - no data on file for ${year}. Add it to lib/lunisolar.js (see comments there).`,
    });
  }

  holidays.sort((a, b) => (a.date || '9999').localeCompare(b.date || '9999'));
  return holidays;
}

module.exports = { generateNationalHolidays };
