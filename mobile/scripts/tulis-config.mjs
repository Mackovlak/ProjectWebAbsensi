// Dijalankan otomatis oleh Capacitor sebelum `cap copy`/`cap sync`
// (hook "capacitor:copy:before" di package.json). Menulis URL server ke
// www/config.js supaya halaman cadangan (offline.html) bisa mencoba lagi.
import { writeFileSync } from 'node:fs';

const url = process.env.ABSENKITA_URL;
if (!url) {
  console.error('ABSENKITA_URL belum diisi. Contoh: ABSENKITA_URL=https://absen.perusahaan.co.id/ npx cap sync');
  process.exit(1);
}
const target = new URL('../www/config.js', import.meta.url);
writeFileSync(target, `window.ABSENKITA_URL = ${JSON.stringify(new URL(url).href)};\n`);
console.log(`www/config.js -> ${new URL(url).href}`);
