// Usage: node gen-flows-id.js <outDir>   -> 03..07 flow PNGs (headless chromium + ImageMagick)
const fs=require('fs'),{execSync}=require('child_process'); const out=process.argv[2]; fs.mkdirSync(out,{recursive:true});
const F={
'03-absen-masuk':[
 {t:'step',x:'Buka halaman absen dan pilih status hari ini'},
 {t:'ask',x:'Status Hadir?',down:'Ya',side:{l:'Tidak',x:'Tersimpan tanpa kamera dan lokasi (Sakit, Cuti, OFF)',c:'ok'}},
 {t:'step',x:'Lihat ke kamera, wajah harus dikenali'},
 {t:'ask',x:'Bekerja di lokasi tetap?',down:'Ya',side:{l:"Tidak",x:"Karyawan lapangan: jam masuk tersimpan",c:"ok"}},
 {t:'ask',x:'Berada di area cabang?',down:'Ya',side:{l:'Tidak',x:'Absen ditolak. Mendekat ke cabang lalu coba lagi',c:'bad'}},
 {t:'end',x:'Jam masuk tersimpan',c:'ok'}],
'04-absen-pulang':[
 {t:'step',x:'Buka halaman absen saat pulang dan lihat ke kamera'},
 {t:'ask',x:'Pulang setelah jam pulang jadwal?',down:'Ya',side:{l:'Tidak',x:'Jam pulang tersimpan',c:'ok'}},
 {t:'step',x:'Isi alasan dan unggah foto'},
 {t:'end',x:'Jam pulang tersimpan, dicatat sebagai lembur',c:'ok'}],
'05-pengajuan-izin':[
 {t:'step',x:'Karyawan memilih jenis dan tanggal pengajuan'},
 {t:'ask',x:'Sisa jatah cuti cukup?',down:'Ya',side:{l:'Tidak',x:'Pengajuan tidak terkirim. Ubah tanggalnya',c:'bad'}},
 {t:'warn',x:'Menunggu keputusan SPV. Jatah cuti ditahan sementara',side:{l:'Dibatalkan karyawan',x:'Pengajuan batal, jatah cuti kembali',c:'bad'}},
 {t:'ask',x:'SPV memutuskan',down:'Setuju',side:{l:'Tolak',x:'Tidak ada perubahan, jatah cuti kembali',c:'bad'}},
 {t:'end',x:'Hari kerja yang berlaku muncul di absensi',c:'ok'}],
'06-dinas-luar':[
 {t:'step',x:'Pada hari dinas luar, karyawan absen'},
 {t:'ask',x:'Pengajuan dinas luar sudah disetujui?',down:'Sudah',side:{l:'Belum',x:'Tercatat Pending Dinas. Setelah Admin menyetujui, menjadi Dinas Luar',c:'warn'}},
 {t:'step',x:'Kamera tetap diperiksa, lokasi tidak dibatasi'},
 {t:'end',x:'Tercatat Dinas Luar',c:'ok'}],
'07-potongan-terlambat':[
 {t:'step',x:'Karyawan absen setelah jam masuk'},
 {t:'ask',x:'Masih dalam masa toleransi?',down:'Tidak',side:{l:'Ya',x:'Tidak ada potongan',c:'ok'}},
 {t:'step',x:'Jumlah menit terlambat disimpan'},
 {t:'warn',x:'Tahap 1: potongan tetap<br>Tahap 2: tambahan tiap interval<br>Berhenti di batas maksimum'},
 {t:'end',x:'Total potongan muncul di slip gaji',c:'ok'}]};
const css=`body{margin:0;padding:18px;background:#fff;font:17px/1.3 "Noto Sans","DejaVu Sans",Arial,sans-serif;color:#14253D;width:640px}
.row{display:grid;grid-template-columns:290px 1fr;align-items:center;min-height:54px}
.c{border:2px solid #4F6D9A;border-radius:10px;padding:10px 14px;text-align:center;background:#DCE6F1;position:relative}
.ask{background:#fff;font-weight:600}.warn{background:#FDF1DC;border-color:#B4750A;color:#3A2604}
.ok{background:#E3F1E3;border-color:#3C8A4E;color:#12361C}.bad{background:#FBE6EE;border-color:#B03A5B;color:#4A0F20}
.end{border-radius:26px;font-weight:600}
.side{display:flex;align-items:center;gap:0;padding-left:0}
.arr{display:flex;flex-direction:column;align-items:center;min-width:92px;font-size:13px;color:#4F6D9A;font-weight:600;text-align:center;line-height:1.15}
.arr i{display:block;border-top:2px solid #4F6D9A;width:100%;position:relative;margin-top:3px}
.arr i:after{content:"";position:absolute;right:-1px;top:-6px;border:5px solid transparent;border-left:9px solid #4F6D9A;border-right:0}
.side .c{font-size:15px;flex:1;padding:8px 10px}
.down{display:grid;grid-template-columns:290px 1fr;height:34px}
.down div{display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:13px;color:#4F6D9A;font-weight:600}
.down b{display:block;border-left:2px solid #4F6D9A;height:100%;position:relative}
.down b:after{content:"";position:absolute;left:-6px;bottom:-2px;border:5px solid transparent;border-top:9px solid #4F6D9A;border-bottom:0}
.down span{position:absolute;margin-left:16px;background:#fff;padding:0 2px}`;
for(const [name,items] of Object.entries(F)){
  let h='';
  items.forEach((it,i)=>{
    const cls=it.t=='ask'?'c ask':it.t=='step'?'c':it.t=='warn'?'c warn':`c end ${it.c}`;
    h+=`<div class="row"><div class="${cls}">${it.x}</div>${it.side?`<div class="side"><div class="arr">${it.side.l}<i></i></div><div class="c ${it.side.c}">${it.side.x}</div></div>`:'<div></div>'}</div>`;
    if(i<items.length-1) h+=`<div class="down"><div style="position:relative"><b></b>${it.down?`<span>${it.down}</span>`:''}</div><div></div></div>`;
  });
  const f=`${out}/${name}`; fs.writeFileSync(f+'.html',`<meta charset=utf8><style>${css}</style>${h}`);
  execSync(`chromium --headless --no-sandbox --disable-gpu --hide-scrollbars --virtual-time-budget=3000 --force-device-scale-factor=2 --window-size=700,900 --screenshot=${f}.png file://${f}.html 2>/dev/null; magick ${f}.png -trim -bordercolor white -border 24 ${f}.png`);
}
