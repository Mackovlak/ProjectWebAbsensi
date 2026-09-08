#!/usr/bin/env node
const fs = require('fs');
const path = require('path');
const { generateNationalHolidays } = require('./lib/holidays');
const cutiBersama = require('./data/cuti-bersama.json');

function main() {
  const year = parseInt(process.argv[2], 10);
  if (!year) {
    console.error('Usage: node generate.js <year>   e.g. node generate.js 2027');
    process.exit(1);
  }

  const nationalHolidays = generateNationalHolidays(year);
  const cuti = cutiBersama[year] || [];

  if (!cutiBersama[year]) {
    console.warn(
      `\nNote: no "cuti bersama" data on file for ${year}. Cuti bersama is pure ` +
        `government policy (SKB 3 Menteri), announced 3-4 months before the year ` +
        `starts - it can't be calculated. Add it to data/cuti-bersama.json once ` +
        `it's published, or leave it empty for now (national holidays below are ` +
        `still valid).\n`
    );
  }

  const missing = nationalHolidays.find((h) => h.date === null);
  if (missing) {
    console.warn(`\nWARNING: ${missing.confidence}\n`);
  }

  const output = {
    year,
    generated_at: new Date().toISOString(),
    national_holidays: nationalHolidays,
    cuti_bersama: cuti,
  };

  const outDir = path.join(__dirname, 'data', 'generated');
  fs.mkdirSync(outDir, { recursive: true });
  const outPath = path.join(outDir, `holidays-${year}.json`);
  fs.writeFileSync(outPath, JSON.stringify(output, null, 2));

  console.log(`Hari libur nasional ${year}:`);
  for (const h of nationalHolidays) {
    if (!h.date) continue;
    console.log(`  ${h.date}  (${h.day.padEnd(6)})  ${h.name}  [${h.confidence}]`);
  }
  if (cuti.length) {
    console.log(`\nCuti bersama ${year}:`);
    for (const c of cuti) {
      console.log(`  ${c.date}  ${c.label}`);
    }
  }
  console.log(`\nWritten to ${path.relative(process.cwd(), outPath)}`);
}

main();
