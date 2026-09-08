/**
 * Julian Day Number <-> Gregorian date conversion.
 * Standard algorithm (Fliegel & Van Flandern / Richards).
 * This is the shared "clock" every other calendar in this project
 * (Hijri, Easter) is built on top of.
 */

function gregorianToJDN(year, month, day) {
  const a = Math.floor((14 - month) / 12);
  const y = year + 4800 - a;
  const m = month + 12 * a - 3;
  return (
    day +
    Math.floor((153 * m + 2) / 5) +
    365 * y +
    Math.floor(y / 4) -
    Math.floor(y / 100) +
    Math.floor(y / 400) -
    32045
  );
}

function jdnToGregorian(jdn) {
  const a = jdn + 32044;
  const b = Math.floor((4 * a + 3) / 146097);
  const c = a - Math.floor((146097 * b) / 4);
  const d = Math.floor((4 * c + 3) / 1461);
  const e = c - Math.floor((1461 * d) / 4);
  const m = Math.floor((5 * e + 2) / 153);

  const day = e - Math.floor((153 * m + 2) / 5) + 1;
  const month = m + 3 - 12 * Math.floor(m / 10);
  const year = 100 * b + d - 4800 + Math.floor(m / 10);

  return { year, month, day };
}

function addDays(year, month, day, offset) {
  return jdnToGregorian(gregorianToJDN(year, month, day) + offset);
}

function toISODate({ year, month, day }) {
  const mm = String(month).padStart(2, '0');
  const dd = String(day).padStart(2, '0');
  return `${year}-${mm}-${dd}`;
}

const DAY_NAMES_ID = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jumat", 'Sabtu'];

function dayOfWeekID(year, month, day) {
  const jdn = gregorianToJDN(year, month, day);
  // JDN 0 was a Monday; (jdn + 1) % 7 gives 0=Sunday.
  return DAY_NAMES_ID[(jdn + 1) % 7];
}

module.exports = { gregorianToJDN, jdnToGregorian, addDays, toISODate, dayOfWeekID };
