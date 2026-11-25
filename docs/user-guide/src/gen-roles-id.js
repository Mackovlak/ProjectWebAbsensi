// Usage: node gen-roles-id.js <outFile.png>  -> role overview as four cards
const fs=require('fs'),{execSync}=require('child_process');const out=process.argv[2];
const R=[['KARYAWAN','#DCE6F1','#4F6D9A',['Absen masuk dan pulang','Daftarkan wajah','Ajukan cuti, izin, atau dinas luar','Minta persetujuan lembur','Lihat absensi dan slip gaji sendiri']],
['SUPERVISOR (SPV)','#FDF1DC','#B4750A',['Setujui atau tolak pengajuan','Setujui lembur dan dinas luar','Pantau kehadiran di cabangnya']],
['ADMIN','#E6E3F4','#6A5FB0',['Kelola karyawan, cabang, dan jadwal','Atur hari libur dan aturan terlambat','Susun dan simpan slip gaji','Izinkan daftar wajah ulang','Periksa dan koreksi catatan absen']],
['OWNER','#E3F1E3','#3C8A4E',['Lihat laporan dan statistik','Lihat data penggajian']]];
const h=`<meta charset=utf8><style>body{margin:0;padding:18px;background:#fff;font:16px/1.3 "Noto Sans","DejaVu Sans",Arial,sans-serif;color:#14253D;width:660px}
.g{display:grid;grid-template-columns:1fr 1fr;gap:16px}.k{border:2px solid;border-radius:12px;overflow:hidden}
.k b{display:block;padding:8px 14px;font-size:15px;letter-spacing:.06em}.k ul{margin:0;padding:10px 14px 12px 30px;background:#fff}.k li{margin:4px 0}</style>
<div class="g">${R.map(r=>`<div class="k" style="border-color:${r[2]}"><b style="background:${r[1]}">${r[0]}</b><ul>${r[3].map(x=>`<li>${x}</li>`).join('')}</ul></div>`).join('')}</div>`;
const f=require('path').resolve(out).replace(/\.png$/,''); const abs=require('path').resolve(out); fs.writeFileSync(f+'.html',h);
execSync(`chromium --headless --no-sandbox --disable-gpu --hide-scrollbars --virtual-time-budget=3000 --force-device-scale-factor=2 --window-size=720,700 --screenshot=${abs} file://${f}.html 2>/dev/null; magick ${abs} -trim -bordercolor white -border 24 ${abs}`);
