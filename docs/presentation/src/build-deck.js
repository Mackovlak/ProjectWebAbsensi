// Usage: NODE_PATH=<node_modules with pptxgenjs, react-icons, sharp> node build-deck.js <out.pptx> <applyTheme.js path>
// Indonesian presentation of the AbsenKita Javag user guide. Plain wording, native (editable) diagrams.
const pptxgen=require('pptxgenjs'),React=require('react'),{renderToStaticMarkup}=require('react-dom/server'),sharp=require('sharp'),fa=require('react-icons/fa');
const [,,OUT,APPLY]=process.argv; const {applyTheme}=require(APPLY);
const THEME={name:'AbsenKita',headFontFace:'Cambria',bodyFontFace:'Calibri',colors:{dk1:'12261F',lt1:'FFFFFF',dk2:'0B3D2E',lt2:'EEF5F1',accent1:'0D7A5A',accent2:'E0A030',accent3:'3B6EA5',accent4:'B03A5B',accent5:'6A5FB0',accent6:'3C8A4E',hlink:'0D7A5A',folHlink:'6A5FB0'}};
const pres=new pptxgen(); pres.layout='LAYOUT_16x9'; pres.theme={headFontFace:THEME.headFontFace,bodyFontFace:THEME.bodyFontFace};
pres.title='AbsenKita Javag: Panduan Penggunaan'; pres.author='AbsenKita Javag';
const C=pres.SchemeColor;
const icon=async(name,hex)=>{const svg=renderToStaticMarkup(React.createElement(fa[name],{color:'#'+hex,size:256}));const b=await sharp(Buffer.from(svg)).png().toBuffer();return 'image/png;base64,'+b.toString('base64');};

pres.defineSlideMaster({title:'JUDUL',background:{color:C.text2},objects:[],
  slideNumber:undefined});
pres.defineSlideMaster({title:'ISI',background:{color:C.background1},
  objects:[{text:{text:'AbsenKita Javag',options:{x:0.6,y:5.25,w:3,h:0.25,fontSize:10,color:C.text2,margin:0}}}],
  slideNumber:{x:9.0,y:5.25,w:0.4,h:0.25,fontSize:10,color:C.text2,align:'right',margin:0}});
pres.defineSlideMaster({title:'PENUTUP',background:{color:C.text2},objects:[]});

const T={fontSize:36,bold:true,color:C.text2,x:0.6,y:0.5,w:8.8,h:0.75,margin:0,valign:'middle',isTextBox:true};
const tag=(s,t)=>s.addText(t.toUpperCase(),{x:0.6,y:0.25,w:6,h:0.25,fontSize:11,bold:true,color:C.accent1,charSpacing:2,margin:0,isTextBox:true});
const title=(s,t,sec)=>{tag(s,sec);s.addText(t,{...T});};
const card=(s,o)=>s.addShape(pres.ShapeType.roundRect,{rectRadius:0.1,...o});
const note=(s,txt)=>s.addNotes(txt);

// ---- native flow diagram: main column of cards, side outcomes on the right
function flow(s,items,o={}){
  const top=o.top||1.35, avail=o.avail||3.85, n=items.length, pitch=avail/n, ch=Math.min(0.66,pitch-0.24), fs=n>=6?13:14, cfs=n>=6?12:13;
  const x=0.6,w=3.9,cx=5.95,cw=3.45;
  const fill={step:[C.background2,C.text1],ask:[C.background1,C.text1],warn:[C.accent2,C.text1],end:[C.accent6,C.background1]};
  const chip={ok:[C.accent6,C.background1],bad:[C.accent4,C.background1],warn:[C.accent2,C.text1]};
  items.forEach((it,i)=>{
    const y=top+i*pitch,[f,tc]=fill[it.t];
    card(s,{x,y,w,h:ch,fill:{color:f},line:{color:C.accent1,width:it.t=='ask'?2:1},objectName:'Langkah '+(i+1)});
    s.addText(it.x,{x:x+0.1,y,w:w-0.2,h:ch,fontSize:fs,bold:it.t=='ask'||it.t=='end',color:tc,align:'center',valign:'middle',margin:0,isTextBox:true});
    if(i<n-1){
      s.addShape(pres.ShapeType.line,{x:x+w/2,y:y+ch,w:0,h:pitch-ch,line:{color:C.accent1,width:1.5,endArrowType:'triangle'}});
      if(it.down) s.addText(it.down,{x:x+w/2+0.08,y:y+ch,w:1.4,h:pitch-ch,fontSize:11,bold:true,color:C.accent1,valign:'middle',margin:0,isTextBox:true});
    }
    if(it.side){
      const [sf,st]=chip[it.side.c];
      s.addShape(pres.ShapeType.line,{x:x+w,y:y+ch/2,w:cx-x-w,h:0,line:{color:C.accent1,width:1.5,endArrowType:'triangle'}});
      s.addText(it.side.l,{x:x+w+0.05,y:y+ch/2-0.28,w:cx-x-w-0.1,h:0.26,fontSize:11,bold:true,color:C.accent1,align:'center',valign:'bottom',margin:0,isTextBox:true});
      card(s,{x:cx,y:y+(ch-Math.min(ch,0.62))/2,w:cw,h:Math.min(ch,0.62),fill:{color:sf},line:{color:sf,width:0.5},objectName:'Hasil '+(i+1)});
      s.addText(it.side.x,{x:cx+0.1,y:y+(ch-Math.min(ch,0.62))/2,w:cw-0.2,h:Math.min(ch,0.62),fontSize:cfs,color:st,align:'center',valign:'middle',margin:0,isTextBox:true});
    }
  });
  if(o.note){card(s,{x:0.6,y:4.62,w:8.8,h:0.5,fill:{color:C.background2},line:{color:C.background2,width:0.5}});
    s.addText(o.note,{x:0.75,y:4.62,w:8.5,h:0.5,fontSize:13,color:C.text1,valign:'middle',margin:0,isTextBox:true});}
}
const table=(s,rows,colW,o={})=>{
  const head=rows[0].map(t=>({text:t,options:{bold:true,color:C.background1,fill:{color:C.accent1},fontSize:o.fs||14}}));
  const body=rows.slice(1).map((r,k)=>r.map((t,i)=>({text:t,options:{bold:i==0,color:C.text1,fill:{color:k%2?C.background2:C.background1},fontSize:o.fs||14}})));
  s.addTable([head,...body],{x:o.x||0.6,y:o.y||1.5,w:colW.reduce((a,b)=>a+b,0),colW,border:{type:'solid',pt:0.75,color:'D5E2DB'},margin:[0.07,0.12,0.07,0.12],valign:'middle',fontFace:'Calibri'});
};

(async()=>{
const I={};
for(const [k,n,h] of [['clip','FaClipboardCheck','FFFFFF'],['cal','FaCalendarAlt','FFFFFF'],['pay','FaFileInvoiceDollar','FFFFFF'],['user','FaUser','FFFFFF'],['tie','FaUserTie','FFFFFF'],['cog','FaUserCog','FFFFFF'],['chart','FaChartPie','FFFFFF'],['cam','FaCamera','FFFFFF'],['clock','FaClock','FFFFFF'],['ok','FaCheck','FFFFFF'],['q','FaQuestion','FFFFFF'],['bag','FaSuitcase','FFFFFF']]) I[k]=await icon(n,h);
const circ=(s,key,x,y,d,col)=>{s.addShape(pres.ShapeType.ellipse,{x,y,w:d,h:d,fill:{color:col},line:{color:col,width:0}});s.addImage({data:I[key],x:x+d*0.25,y:y+d*0.25,w:d*0.5,h:d*0.5,altText:key});};

// 1 Judul
pres.addSection({title:'Pengantar'});
let s=pres.addSlide({masterName:'JUDUL',sectionTitle:'Pengantar'});
circ(s,'clip',0.7,0.9,0.9,C.accent1);
s.addText('AbsenKita Javag',{x:0.7,y:2.0,w:8.6,h:1.0,fontSize:54,bold:true,color:C.background1,fontFace:'Cambria',margin:0,isTextBox:true});
s.addText('Panduan penggunaan',{x:0.7,y:3.0,w:8.6,h:0.6,fontSize:28,color:C.accent2,fontFace:'Cambria',margin:0,isTextBox:true});
s.addText('Sistem absensi dan slip gaji Java Abadi Gemilang',{x:0.7,y:3.8,w:8.6,h:0.4,fontSize:18,color:C.background2,margin:0,isTextBox:true});
note(s,'Selamat datang. Presentasi ini menjelaskan cara memakai AbsenKita Javag dengan bahasa sederhana: siapa melakukan apa, bagaimana absen, bagaimana mengajukan cuti, dan bagaimana keterlambatan memengaruhi slip gaji. Sekitar 25 menit termasuk tanya jawab.');

// 2 Apa itu
s=pres.addSlide({masterName:'ISI',sectionTitle:'Pengantar'}); title(s,'Apa itu AbsenKita?','Pengantar');
[['clip','Catat kehadiran','Absen masuk dan pulang lewat halaman absen, dengan kamera dan lokasi.',C.accent1],
 ['cal','Ajukan cuti dan izin','Kirim pengajuan, tunggu keputusan atasan, dan pantau sisa jatah cuti.',C.accent3],
 ['pay','Lihat slip gaji','Kehadiran dan keterlambatan otomatis menjadi bahan slip gaji bulanan.',C.accent5]].forEach(([k,t,d,col],i)=>{
  const x=0.6+i*3.0; card(s,{x,y:1.6,w:2.8,h:3.3,fill:{color:C.background2},line:{color:C.background2,width:0.5},objectName:'Kartu '+t});
  circ(s,k,x+0.25,1.85,0.8,col);
  s.addText(t,{x:x+0.25,y:2.85,w:2.3,h:0.45,fontSize:20,bold:true,color:C.text2,margin:0,isTextBox:true});
  s.addText(d,{x:x+0.25,y:3.35,w:2.3,h:1.4,fontSize:15,color:C.text1,valign:'top',margin:0,isTextBox:true});});
note(s,'Tiga hal utama yang dilakukan aplikasi ini. Semua orang memakainya dengan cara yang sama di ponsel atau komputer; yang berbeda hanya peran masing-masing.');

// 3 Peran
s=pres.addSlide({masterName:'ISI',sectionTitle:'Pengantar'}); title(s,'Empat peran pengguna','Pengantar');
[['user','Karyawan',C.accent1,['Absen masuk dan pulang','Daftarkan wajah','Ajukan cuti, izin, dinas luar','Lihat slip gaji sendiri']],
 ['tie','Supervisor (SPV)',C.accent2,['Setujui atau tolak pengajuan','Setujui lembur dan dinas luar','Pantau kehadiran cabang']],
 ['cog','Admin',C.accent5,['Kelola karyawan dan jadwal','Atur aturan terlambat','Susun slip gaji','Koreksi catatan absen']],
 ['chart','Owner',C.accent6,['Lihat laporan dan statistik','Lihat data penggajian','Hanya untuk dipantau']]].forEach(([k,t,col,l],i)=>{
  const x=0.6+i*2.25; card(s,{x,y:1.5,w:2.1,h:3.55,fill:{color:C.background2},line:{color:C.background2,width:0.5},objectName:'Peran '+t});
  circ(s,k,x+0.2,1.7,0.7,col);
  s.addText(t,{x:x+0.2,y:2.5,w:1.8,h:0.4,fontSize:16,bold:true,color:C.text2,margin:0,isTextBox:true});
  s.addText(l.map((x,j)=>({text:x,options:{bullet:true,breakLine:j<l.length-1}})),{x:x+0.2,y:2.95,w:1.8,h:2.0,fontSize:13,color:C.text1,valign:'top',paraSpaceAfter:5,margin:0,isTextBox:true});});
note(s,'Setiap orang hanya melihat apa yang menjadi haknya. Karyawan hanya melihat data dirinya. SPV hanya cabangnya. Admin mengelola seluruh perusahaan. Owner terutama memantau.');

// 4 Alur besar
s=pres.addSlide({masterName:'ISI',sectionTitle:'Pengantar'}); title(s,'Dari absen sampai slip gaji','Pengantar');
[['user','1','Karyawan','absen dan mengirim pengajuan',C.accent1],['tie','2','SPV','memeriksa dan memutuskan',C.accent2],['cog','3','Admin','menyusun slip gaji',C.accent5],['chart','4','Karyawan dan Owner','melihat hasilnya',C.accent6]].forEach(([k,n,t,d,col],i)=>{
  const x=0.6+i*2.3; card(s,{x,y:1.8,w:2.0,h:2.5,fill:{color:C.background2},line:{color:col,width:2},objectName:'Tahap '+n});
  circ(s,k,x+0.65,2.0,0.7,col);
  s.addText(t,{x:x+0.1,y:2.85,w:1.8,h:0.55,fontSize:17,bold:true,color:C.text2,align:'center',valign:'middle',margin:0,isTextBox:true});
  s.addText(d,{x:x+0.15,y:3.45,w:1.7,h:0.7,fontSize:14,color:C.text1,align:'center',valign:'top',margin:0,isTextBox:true});
  if(i<3) s.addShape(pres.ShapeType.line,{x:x+2.0,y:3.05,w:0.3,h:0,line:{color:C.accent1,width:2,endArrowType:'triangle'}});});
s.addText('Catatan kehadiran adalah bahan slip gaji, jadi kesalahan sebaiknya diperbaiki lebih dulu.',{x:0.6,y:4.55,w:8.8,h:0.4,fontSize:15,color:C.text1,margin:0,isTextBox:true});
note(s,'Alur sederhana: karyawan mencatat, SPV memeriksa, Admin menyusun slip gaji, lalu hasilnya dilihat karyawan dan Owner. Karena slip gaji bergantung pada catatan kehadiran, koreksi sebaiknya dilakukan sebelum slip disusun.');

// 5 Absen masuk
pres.addSection({title:'Absensi'});
s=pres.addSlide({masterName:'ISI',sectionTitle:'Absensi'}); title(s,'Absen masuk','Absensi');
flow(s,[{t:'step',x:'Buka halaman absen dan pilih status hari ini'},
 {t:'ask',x:'Status Hadir?',down:'Ya',side:{l:'Tidak',x:'Tersimpan tanpa kamera dan lokasi (Sakit, Cuti, OFF)',c:'ok'}},
 {t:'step',x:'Lihat ke kamera, wajah harus dikenali'},
 {t:'ask',x:'Bekerja di lokasi tetap?',down:'Ya',side:{l:'Tidak',x:'Karyawan lapangan: jam masuk tersimpan',c:'ok'}},
 {t:'ask',x:'Berada di area cabang?',down:'Ya',side:{l:'Tidak',x:'Ditolak. Mendekat ke cabang lalu coba lagi',c:'bad'}},
 {t:'end',x:'Jam masuk tersimpan'}]);
note(s,'Langkah harian. Hanya status Hadir yang memerlukan kamera dan pemeriksaan lokasi. Karyawan lapangan, yang diatur Admin, tidak diperiksa lokasinya tetapi kamera tetap dipakai. Wajah harus sudah didaftarkan lebih dulu.');

// 6 Absen pulang
s=pres.addSlide({masterName:'ISI',sectionTitle:'Absensi'}); title(s,'Absen pulang dan lembur','Absensi');
flow(s,[{t:'step',x:'Buka halaman absen saat pulang dan lihat ke kamera'},
 {t:'ask',x:'Pulang setelah jam pulang jadwal?',down:'Ya',side:{l:'Tidak',x:'Jam pulang tersimpan',c:'ok'}},
 {t:'step',x:'Isi alasan dan unggah foto'},
 {t:'end',x:'Jam pulang tersimpan, dicatat sebagai lembur'}],{note:'Absen pulang kedua di hari yang sama ditolak. Lupa absen masuk? Tetap absen pulang, lalu beri tahu Admin atau SPV.',avail:3.1});
note(s,'Pulang tepat waktu cukup absen. Pulang lebih lambat dari jadwal dicatat sebagai lembur dan diminta alasan serta foto. Jika lupa absen masuk, hari itu tersimpan tanpa jam masuk dan ditandai agar Admin memeriksanya.');

// 7 Kondisi kerja
s=pres.addSlide({masterName:'ISI',sectionTitle:'Absensi'}); title(s,'Perbedaan kondisi kerja','Absensi');
table(s,[['Kondisi','Yang perlu dipahami'],
 ['Lokasi kerja tetap','Hadir memerlukan kamera dan pemeriksaan bahwa Anda berada di area cabang.'],
 ['Karyawan lapangan','Pemeriksaan area cabang dilewati. Kamera tetap diperlukan. Admin yang mengatur mode ini.'],
 ['Dinas Luar','Punya alur sendiri. Dinas yang disetujui tidak menggantikan kewajiban absen.'],
 ['OFF, Sakit, Cuti, Alpha','Tidak memerlukan kamera dan lokasi. Untuk libur yang direncanakan, kirim pengajuan.']],[2.6,6.2],{y:1.5,fs:15});
note(s,'Empat kondisi yang paling sering ditanyakan. Perbedaan utamanya ada pada pemeriksaan kamera dan lokasi.');

// 8 Terlambat
s=pres.addSlide({masterName:'ISI',sectionTitle:'Absensi'}); title(s,'Terlambat dan hari Sabtu','Absensi');
[['clock','Jam masuk dan toleransi','Setiap cabang punya jam masuk dan batas toleransi. Lewat batas itu, hari ditandai Terlambat.'],
 ['cal','Sabtu adalah hari lembur','Tidak ada penilaian terlambat. Lama kerja dihitung dari jam Anda benar-benar bekerja.'],
 ['q','Lupa absen masuk?','Tetap absen pulang dan beri tahu Admin atau SPV agar catatannya dikoreksi.']].forEach(([k,t,d],i)=>{
  const y=1.5+i*1.2; circ(s,k,0.6,y,0.65,C.accent1);
  s.addText(t,{x:1.45,y,w:3.9,h:0.32,fontSize:17,bold:true,color:C.text2,margin:0,isTextBox:true});
  s.addText(d,{x:1.45,y:y+0.34,w:3.9,h:0.8,fontSize:14,color:C.text1,valign:'top',margin:0,isTextBox:true});});
card(s,{x:5.7,y:1.5,w:3.7,h:3.4,fill:{color:C.background2},line:{color:C.background2,width:0.5},objectName:'Contoh terlambat'});
s.addText('CONTOH (ILUSTRASI)',{x:5.95,y:1.65,w:3.2,h:0.3,fontSize:11,bold:true,color:C.accent1,charSpacing:2,margin:0,isTextBox:true});
s.addText('08.00 → 08.12',{x:5.95,y:2.1,w:3.2,h:0.7,fontSize:34,bold:true,color:C.text2,fontFace:'Cambria',margin:0,isTextBox:true});
s.addText('Selisih 12 menit',{x:5.95,y:2.85,w:3.2,h:0.4,fontSize:20,bold:true,color:C.accent1,margin:0,isTextBox:true});
s.addText('Apakah dihitung terlambat dan berapa potongannya mengikuti toleransi dan tarif perusahaan. Angka ini bukan kebijakan perusahaan.',{x:5.95,y:3.35,w:3.2,h:1.4,fontSize:14,color:C.text1,valign:'top',margin:0,isTextBox:true});
note(s,'Contoh angka hanya ilustrasi. Aturan toleransi dan tarif ditetapkan perusahaan dan dapat berbeda per cabang.');

// 9 Cuti
pres.addSection({title:'Cuti dan izin'});
s=pres.addSlide({masterName:'ISI',sectionTitle:'Cuti dan izin'}); title(s,'Mengajukan cuti atau izin','Cuti dan izin');
flow(s,[{t:'step',x:'Karyawan memilih jenis dan tanggal pengajuan'},
 {t:'ask',x:'Sisa jatah cuti cukup?',down:'Ya',side:{l:'Tidak',x:'Pengajuan tidak terkirim. Ubah tanggalnya',c:'bad'}},
 {t:'warn',x:'Menunggu keputusan SPV. Jatah cuti ditahan sementara',side:{l:'Dibatalkan karyawan',x:'Pengajuan batal, jatah cuti kembali',c:'bad'}},
 {t:'ask',x:'SPV memutuskan',down:'Setuju',side:{l:'Tolak',x:'Tidak ada perubahan, jatah cuti kembali',c:'bad'}},
 {t:'end',x:'Hari kerja yang berlaku muncul di absensi'}]);
note(s,'Satu pengajuan mencakup semua hari dari tanggal mulai sampai selesai. Hanya hari kerja yang dihitung: akhir pekan, hari libur, dan hari yang sudah punya catatan absen tidak mengurangi jatah.');

// 10 Jenis dan jatah
s=pres.addSlide({masterName:'ISI',sectionTitle:'Cuti dan izin'}); title(s,'Jenis dan jatah cuti','Cuti dan izin');
table(s,[['Jenis','Kegunaan','Kurangi jatah?'],['Cuti','Libur tahunan','Ya'],['Sakit','Tidak sehat','Ya'],['Izin','Keperluan pribadi','Ya'],['Dinas Luar','Kerja di luar kantor','Tidak']],[1.4,2.2,1.5],{y:1.5,fs:14});
card(s,{x:5.7,y:1.5,w:3.7,h:3.3,fill:{color:C.background2},line:{color:C.background2,width:0.5},objectName:'Contoh jatah'});
s.addText('CONTOH (ILUSTRASI)',{x:5.95,y:1.65,w:3.2,h:0.3,fontSize:11,bold:true,color:C.accent1,charSpacing:2,margin:0,isTextBox:true});
s.addText('12 − 3 − 2 = 7',{x:5.95,y:2.05,w:3.2,h:0.7,fontSize:34,bold:true,color:C.text2,fontFace:'Cambria',margin:0,isTextBox:true});
s.addText('Jatah cuti 12 hari, terpakai 3 hari, menunggu persetujuan 2 hari. Tersisa 7 hari untuk pengajuan berikutnya.',{x:5.95,y:2.9,w:3.2,h:1.1,fontSize:14,color:C.text1,valign:'top',margin:0,isTextBox:true});
s.addText('Jatah awal biasanya 12 hari per tahun dan dapat diatur Admin.',{x:5.95,y:4.0,w:3.2,h:0.7,fontSize:12,color:C.text1,valign:'top',margin:0,isTextBox:true});
note(s,'Cuti, Sakit, dan Izin mengurangi jatah cuti tahunan. Dinas Luar tidak. Pengajuan yang masih menunggu ikut menahan jatah, jadi sisa yang tersedia sudah memperhitungkannya.');

// 11 Dinas luar
s=pres.addSlide({masterName:'ISI',sectionTitle:'Cuti dan izin'}); title(s,'Dinas luar tetap absen','Cuti dan izin');
flow(s,[{t:'step',x:'Pada hari dinas luar, karyawan absen'},
 {t:'ask',x:'Pengajuan dinas luar sudah disetujui?',down:'Sudah',side:{l:'Belum',x:'Tercatat Pending Dinas. Setelah Admin menyetujui, menjadi Dinas Luar',c:'warn'}},
 {t:'step',x:'Kamera tetap diperiksa, lokasi tidak dibatasi'},
 {t:'end',x:'Tercatat Dinas Luar'}],{note:'Pengajuan dinas luar yang disetujui tidak menggantikan absen. Hari yang sudah benar-benar Anda absen tidak pernah dihapus saat pembatalan.',avail:3.1});
note(s,'Dinas Luar punya aturan khusus: tidak mengurangi jatah cuti dan tidak dibatasi lokasi, tetapi kamera tetap wajib. Tanpa persetujuan sebelumnya, catatannya menjadi Pending Dinas dan menunggu Admin.');

// 12 SPV
pres.addSection({title:'Peran SPV'});
s=pres.addSlide({masterName:'ISI',sectionTitle:'Peran SPV'}); title(s,'Pemeriksaan harian SPV','Peran SPV');
[['Cek yang belum absen','Tanyakan keadaannya sebelum menganggap tidak hadir.'],['Tinjau pengajuan','Cuti, izin, dinas luar, dan lembur yang menunggu.'],['Perhatikan catatan khusus','Terlambat, Pending Dinas, dan jam masuk kosong. Beri tahu Admin jika perlu koreksi.'],['Periksa lembur','Pastikan pekerjaan dan foto bukti sesuai sebelum menyetujui.']].forEach(([t,d],i)=>{
  const x=0.6+(i%2)*4.5, y=1.5+Math.floor(i/2)*1.75;
  card(s,{x,y,w:4.3,h:1.55,fill:{color:C.background2},line:{color:C.background2,width:0.5},objectName:'Pemeriksaan '+(i+1)});
  s.addShape(pres.ShapeType.ellipse,{x:x+0.2,y:y+0.2,w:0.55,h:0.55,fill:{color:C.accent2},line:{color:C.accent2,width:0}});
  s.addText(String(i+1),{x:x+0.2,y:y+0.2,w:0.55,h:0.55,fontSize:18,bold:true,color:C.text1,align:'center',valign:'middle',margin:0,isTextBox:true});
  s.addText(t,{x:x+0.95,y:y+0.15,w:3.2,h:0.4,fontSize:17,bold:true,color:C.text2,valign:'middle',margin:0,isTextBox:true});
  s.addText(d,{x:x+0.95,y:y+0.6,w:3.2,h:0.85,fontSize:14,color:C.text1,valign:'top',margin:0,isTextBox:true});});
note(s,'SPV hanya melihat dan memutuskan untuk cabangnya sendiri, dan tidak mengubah data karyawan atau slip gaji. Jika menemukan catatan yang salah, beri tahu Admin.');

// 13 Terlambat jadi potongan
pres.addSection({title:'Slip gaji'});
s=pres.addSlide({masterName:'ISI',sectionTitle:'Slip gaji'}); title(s,'Terlambat jadi potongan','Slip gaji');
flow(s,[{t:'step',x:'Karyawan absen setelah jam masuk'},
 {t:'ask',x:'Masih dalam masa toleransi?',down:'Tidak',side:{l:'Ya',x:'Tidak ada potongan',c:'ok'}},
 {t:'step',x:'Jumlah menit terlambat disimpan'},
 {t:'warn',x:'Tahap 1: potongan tetap, lalu tahap 2: tambahan tiap interval',side:{l:'Aturan',x:'Aturan khusus cabang dipakai lebih dulu',c:'warn'}},
 {t:'end',x:'Total potongan muncul di slip gaji',side:{l:'Catatan',x:'Slip yang sudah disimpan tidak berubah jika aturan diganti',c:'ok'}}]);
note(s,'Yang disimpan adalah jumlah menit terlambat, bukan hanya rupiah. Karena itu mengubah aturan di kemudian hari tidak mengubah slip lama. Besar angka ditetapkan perusahaan dan dapat diubah Admin, untuk semua cabang atau satu cabang.');

// 14 Slip gaji admin
s=pres.addSlide({masterName:'ISI',sectionTitle:'Slip gaji'}); title(s,'Menyusun slip gaji','Slip gaji');
const st=['Pilih karyawan dan bulan','Baca kotak "cara hitung"','Tambahkan lembur Sabtu bila perlu','Simpan slip gaji','Terkunci setelah 5 hari'];
st.forEach((t,i)=>{const x=0.6+i*1.78,last=i==4; card(s,{x,y:1.8,w:1.6,h:2.3,fill:{color:last?C.accent6:C.background2},line:{color:last?C.accent6:C.accent1,width:1.5},objectName:'Langkah slip '+(i+1)});
  s.addShape(pres.ShapeType.ellipse,{x:x+0.5,y:1.97,w:0.6,h:0.6,fill:{color:last?C.background1:C.accent1},line:{color:last?C.background1:C.accent1,width:0}});
  s.addText(String(i+1),{x:x+0.5,y:1.97,w:0.6,h:0.6,fontSize:18,bold:true,color:last?C.accent6:C.background1,align:'center',valign:'middle',margin:0,isTextBox:true});
  s.addText(t,{x:x+0.1,y:2.7,w:1.4,h:1.25,fontSize:14,bold:true,color:last?C.background1:C.text1,align:'center',valign:'top',margin:0,isTextBox:true});
  if(i<4) s.addShape(pres.ShapeType.line,{x:x+1.6,y:2.95,w:0.18,h:0,line:{color:C.accent1,width:2,endArrowType:'triangle'}});});
s.addText('Lembur hari Sabtu muncul sebagai saran. Tekan Tambahkan jika ingin memasukkannya. Ada kesalahan setelah slip terkunci? Sampaikan ke Admin.',{x:0.6,y:4.4,w:8.8,h:0.6,fontSize:14,color:C.text1,valign:'top',margin:0,isTextBox:true});
note(s,'Bagian ini untuk Admin. Kotak cara hitung menjelaskan jumlah hari terlambat, total menit, dan aturan yang dipakai dengan bahasa sederhana.');

// 15 Masalah
pres.addSection({title:'Bantuan'});
s=pres.addSlide({masterName:'ISI',sectionTitle:'Bantuan'}); title(s,'Jika ada masalah','Bantuan');
[['Wajah tidak dikenali','Cahaya cukup, lepas topi atau masker. Masih gagal? Minta Admin izinkan daftar wajah ulang.'],
 ['Di luar area cabang','Dekati gedung dan aktifkan lokasi. Kerja di lapangan? Minta Admin mengaturnya.'],
 ['Pengajuan tidak terkirim','Biasanya sisa jatah cuti tidak cukup. Periksa sisa jatah di layar pengajuan.'],
 ['Salah isi pengajuan','Batalkan selama masih menunggu, lalu kirim yang baru.'],
 ['Tidak bisa masuk akun','Periksa nama pengguna. Setelah 5 kali salah, terkunci 15 menit. Akun nonaktif? Hubungi Admin.']].forEach(([a,b],i)=>{
  const y=1.4+i*0.75; card(s,{x:0.6,y,w:2.8,h:0.62,fill:{color:C.accent1},line:{color:C.accent1,width:0},objectName:'Masalah '+(i+1)});
  s.addText(a,{x:0.7,y,w:2.6,h:0.62,fontSize:14,bold:true,color:C.background1,valign:'middle',margin:0,isTextBox:true});
  card(s,{x:3.5,y,w:5.9,h:0.62,fill:{color:C.background2},line:{color:C.background2,width:0},objectName:'Solusi '+(i+1)});
  s.addText(b,{x:3.65,y,w:5.65,h:0.62,fontSize:13,color:C.text1,valign:'middle',margin:0,isTextBox:true});});
note(s,'Lima masalah yang paling sering muncul beserta langkah pertamanya. Hampir semuanya bisa diselesaikan dengan bantuan Admin.');

// 16 Penutup
s=pres.addSlide({masterName:'PENUTUP',sectionTitle:'Bantuan'});
s.addText('Ringkasan',{x:0.7,y:0.6,w:8.6,h:0.8,fontSize:40,bold:true,color:C.background1,fontFace:'Cambria',margin:0,isTextBox:true});
[['Absen setiap hari','Masuk dan pulang, dengan kamera dan lokasi.'],['Ajukan lewat aplikasi','Cuti dan izin dikirim sebagai pengajuan, bukan hanya dipilih saat absen.'],['SPV memutuskan','Pengajuan diperiksa oleh atasan di cabang.'],['Kehadiran menjadi slip gaji','Catatan yang benar menghasilkan slip yang benar.']].forEach(([t,d],i)=>{
  const y=1.65+i*0.8; circ(s,'ok',0.7,y,0.5,C.accent1);
  s.addText(t,{x:1.4,y:y-0.02,w:7.9,h:0.32,fontSize:19,bold:true,color:C.background1,margin:0,isTextBox:true});
  s.addText(d,{x:1.4,y:y+0.3,w:7.9,h:0.35,fontSize:15,color:C.background2,margin:0,isTextBox:true});});
s.addText('Ada pertanyaan?',{x:0.7,y:4.85,w:8.6,h:0.45,fontSize:22,color:C.accent2,fontFace:'Cambria',margin:0,isTextBox:true});
note(s,'Ringkas: absen setiap hari, ajukan cuti lewat pengajuan, SPV memutuskan, dan catatan yang benar menghasilkan slip yang benar. Buka tanya jawab.');

await pres.writeFile({fileName:OUT}); await applyTheme(OUT,THEME); console.log('wrote',OUT);
})();
