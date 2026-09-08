const { toISODate } = require('./lib/julian');
const { findHijriEventInGregorianYear } = require('./lib/hijri');
const { easterSunday, goodFriday, ascensionDay } = require('./lib/christian');

// Ground truth: officially published SKB 3 Menteri dates for 2025 & 2026.
const KNOWN = [
  { year: 2025, label: 'Isra Miraj', hijriMonth: 7, hijriDay: 27, expected: '2025-01-27' },
  { year: 2025, label: 'Idul Fitri (day 1)', hijriMonth: 10, hijriDay: 1, expected: '2025-03-31' },
  { year: 2025, label: 'Idul Adha', hijriMonth: 12, hijriDay: 10, expected: '2025-06-06' },
  { year: 2025, label: 'Tahun Baru Islam', hijriMonth: 1, hijriDay: 1, expected: '2025-06-27' },
  { year: 2026, label: 'Isra Miraj', hijriMonth: 7, hijriDay: 27, expected: '2026-01-16' },
  { year: 2026, label: 'Idul Fitri (day 1)', hijriMonth: 10, hijriDay: 1, expected: '2026-03-21' },
  { year: 2026, label: 'Idul Adha', hijriMonth: 12, hijriDay: 10, expected: '2026-05-27' },
  { year: 2026, label: 'Tahun Baru Islam', hijriMonth: 1, hijriDay: 1, expected: '2026-06-16' },
  { year: 2026, label: 'Maulid Nabi', hijriMonth: 3, hijriDay: 12, expected: '2026-08-25' },
];

console.log('--- Hijri (Islamic calendar) accuracy check ---');
let maxDiffDays = 0;
for (const test of KNOWN) {
  const candidates = findHijriEventInGregorianYear(test.year, test.hijriMonth, test.hijriDay);
  const best = candidates[0];
  const computedISO = best ? toISODate(best.gregorian) : 'NOT FOUND';
  const diff = best
    ? (Date.parse(computedISO) - Date.parse(test.expected)) / 86400000
    : null;
  maxDiffDays = Math.max(maxDiffDays, diff === null ? 99 : Math.abs(diff));
  console.log(
    `${test.year} ${test.label.padEnd(22)} computed=${computedISO}  official=${test.expected}  diff=${diff}d`
  );
}
console.log(`Max deviation across all test cases: ${maxDiffDays} day(s)\n`);

console.log('--- Christian calendar (exact) check ---');
const christianChecks = [
  { year: 2025, label: 'Good Friday', fn: goodFriday, expected: '2025-04-18' },
  { year: 2025, label: 'Easter', fn: easterSunday, expected: '2025-04-20' },
  { year: 2025, label: 'Ascension', fn: ascensionDay, expected: '2025-05-29' },
  { year: 2026, label: 'Good Friday', fn: goodFriday, expected: '2026-04-03' },
  { year: 2026, label: 'Easter', fn: easterSunday, expected: '2026-04-05' },
  { year: 2026, label: 'Ascension', fn: ascensionDay, expected: '2026-05-14' },
];
for (const test of christianChecks) {
  const computed = toISODate(test.fn(test.year));
  const match = computed === test.expected ? 'OK' : 'MISMATCH';
  console.log(`${test.year} ${test.label.padEnd(14)} computed=${computed}  official=${test.expected}  [${match}]`);
}
