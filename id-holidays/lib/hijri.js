/**
 * Tabular Islamic ("civil") calendar <-> Gregorian conversion.
 *
 * This is an ARITHMETIC approximation of the Hijri calendar (30-year cycle,
 * 11 leap years per cycle). It does NOT do real moon-sighting (rukyat) like
 * Indonesia's Kemenag officially uses to declare Ramadan/Idul Fitri/Idul
 * Adha. In practice it lands within 0-2 days of the real government
 * announcement, which is precise enough for generating test/sample data,
 * but should NOT be trusted as the authoritative date for payroll/legal use.
 */

const { gregorianToJDN, jdnToGregorian } = require('./julian');

const HIJRI_EPOCH_JDN = 1948439; // JDN - 1 of 1 Muharram 1 AH (civil/Friday tabular epoch)

function hijriToJDN(year, month, day) {
  return (
    HIJRI_EPOCH_JDN +
    (year - 1) * 354 +
    Math.floor((3 + 11 * year) / 30) +
    Math.ceil(29.5 * (month - 1)) +
    day
  );
}

// Inverse is defined directly in terms of hijriToJDN (a short search from a
// rough estimate) rather than a separate closed-form formula, so the two
// directions can never drift out of sync with each other.
function jdnToHijri(jdn) {
  let year = Math.floor((30 * (jdn - HIJRI_EPOCH_JDN) + 10646) / 10631);
  while (hijriToJDN(year, 1, 1) > jdn) year -= 1;
  while (hijriToJDN(year + 1, 1, 1) <= jdn) year += 1;

  let month = 1;
  while (hijriToJDN(year, month + 1, 1) <= jdn) month += 1;

  const day = jdn - hijriToJDN(year, month, 1) + 1;
  return { year, month, day };
}

function hijriToGregorian(year, month, day) {
  return jdnToGregorian(hijriToJDN(year, month, day));
}

function gregorianToHijri(year, month, day) {
  return jdnToHijri(gregorianToJDN(year, month, day));
}

/**
 * Find the Gregorian date on which a given (Hijri month, Hijri day) falls
 * within a target Gregorian year. Searches a small window of Hijri years
 * around the estimate since the Hijri year is ~11 days shorter than the
 * Gregorian year and boundaries drift.
 */
function findHijriEventInGregorianYear(targetGregorianYear, hijriMonth, hijriDay) {
  const approxHijriYear = Math.floor(((targetGregorianYear - 622) * 33) / 32);
  const candidates = [];
  for (let offset = -1; offset <= 1; offset++) {
    const hy = approxHijriYear + offset;
    const g = hijriToGregorian(hy, hijriMonth, hijriDay);
    if (g.year === targetGregorianYear) {
      candidates.push({ hijriYear: hy, gregorian: g });
    }
  }
  return candidates;
}

module.exports = {
  hijriToGregorian,
  gregorianToHijri,
  findHijriEventInGregorianYear,
};
