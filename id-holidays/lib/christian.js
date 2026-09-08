/**
 * Western (Gregorian) Easter Sunday calculation - Meeus/Jones/Butcher algorithm.
 * This is EXACT (not an approximation) for the Gregorian calendar.
 * Good Friday and Ascension Day are fixed offsets from Easter, so they're
 * exact too - Indonesia's 3 Christian public holidays need zero lookup table.
 */
const { addDays } = require('./julian');

function easterSunday(year) {
  const a = year % 19;
  const b = Math.floor(year / 100);
  const c = year % 100;
  const d = Math.floor(b / 4);
  const e = b % 4;
  const f = Math.floor((b + 8) / 25);
  const g = Math.floor((b - f + 1) / 3);
  const h = (19 * a + b - d - g + 15) % 30;
  const i = Math.floor(c / 4);
  const k = c % 4;
  const l = (32 + 2 * e + 2 * i - h - k) % 7;
  const m = Math.floor((a + 11 * h + 22 * l) / 451);
  const month = Math.floor((h + l - 7 * m + 114) / 31);
  const day = ((h + l - 7 * m + 114) % 31) + 1;
  return { year, month, day };
}

function goodFriday(year) {
  const easter = easterSunday(year);
  return addDays(easter.year, easter.month, easter.day, -2);
}

function ascensionDay(year) {
  const easter = easterSunday(year);
  return addDays(easter.year, easter.month, easter.day, 39);
}

module.exports = { easterSunday, goodFriday, ascensionDay };
