// Usage: NODE_PATH=<node_modules> node build-panduan-id.js <imagesDir> <outFile>
// Indonesian user guide in the layout of AbsenKita-Javag-Panduan-flow.docx, plain wording only.
const fs=require('fs'),{execSync}=require('child_process');
const {Document,Packer,Paragraph,TextRun,ImageRun,Table,TableRow,TableCell,AlignmentType,WidthType,ShadingType,BorderStyle,LevelFormat,Footer,PageNumber,HeadingLevel}=require('docx');
const [,,IM,OUT]=process.argv;
const W=9936, FONT='Calibri';
const r=(t,o={})=>new TextRun({text:t,font:FONT,...o});
// **bold** inline
const rich=s=>s.split(/(\*\*[^*]+\*\*)/).filter(Boolean).map(x=>x.startsWith('**')?r(x.slice(2,-2),{bold:true}):r(x));
const p=(s,o={})=>new Paragraph({spacing:{after:140,line:269},...o,children:rich(s)});
const title=t=>new Paragraph({spacing:{before:0,after:140},children:[r(t,{size:50})]});
const h1=(t,pb)=>new Paragraph({heading:HeadingLevel.HEADING_1,keepNext:true,pageBreakBefore:!!pb,spacing:{before:360,after:140},children:[r(t,{bold:true,size:36})]});
const h2=t=>new Paragraph({heading:HeadingLevel.HEADING_2,keepNext:true,spacing:{before:240,after:100},children:[r(t,{bold:true,size:24})]});
const li=(s,ref)=>new Paragraph({numbering:{reference:ref,level:0},spacing:{after:80,line:269},children:rich(s)});
const bullets=a=>a.map(s=>li(s,'b'));
let nref=0; const numbered=a=>{const ref='n'+(++nref);numbering.push({reference:ref,levels:[{level:0,format:LevelFormat.DECIMAL,text:'%1.',alignment:AlignmentType.LEFT,style:{paragraph:{indent:{left:540,hanging:360}}}}]});return a.map(s=>li(s,ref));};
const example=s=>new Paragraph({spacing:{before:60,after:160,line:269},children:[r('Contoh: ',{bold:true,italics:true}),...rich(s).map(x=>x)]});
function img(file,cap){
  const path=`${IM}/${file}`; const [w,h]=execSync(`identify -format "%w %h" ${path}`).toString().split(' ').map(Number);
  const s=Math.min(600/w,(w/h>2?300:380)/h); 
  return [new Paragraph({alignment:AlignmentType.CENTER,keepNext:true,spacing:{before:120,after:60},children:[new ImageRun({type:'png',data:fs.readFileSync(path),transformation:{width:Math.round(w*s),height:Math.round(h*s)},altText:{title:cap,description:cap,name:file}})]}),
    new Paragraph({alignment:AlignmentType.CENTER,spacing:{after:200},children:[r(cap,{italics:true,size:19,color:'555555'})]})];
}
const bd={style:BorderStyle.SINGLE,size:4,color:'B8C4D6'},borders={top:bd,bottom:bd,left:bd,right:bd};
function table(cols,head,rows,keep=true){
  const cell=(t,w,hd,fill,kn)=>new TableCell({borders,width:{size:w,type:WidthType.DXA},margins:{top:70,bottom:70,left:110,right:110},shading:{fill,type:ShadingType.CLEAR,color:'auto'},children:[new Paragraph({keepNext:kn,spacing:{after:0,line:259},children:hd?[r(t,{bold:true,size:21})]:rich(t).map(x=>x)})]});
  return [new Table({width:{size:W,type:WidthType.DXA},columnWidths:cols,rows:[new TableRow({tableHeader:true,children:head.map((t,i)=>cell(t,cols[i],true,'DCE6F1',keep))}),...rows.map((x,k)=>new TableRow({cantSplit:true,children:x.map((t,i)=>cell(t,cols[i],false,k%2?'F5F7FA':'FFFFFF',keep&&k<rows.length-1))}))]}),new Paragraph({spacing:{after:140},children:[]})];
}
const numbering=[{reference:'b',levels:[{level:0,format:LevelFormat.BULLET,text:'•',alignment:AlignmentType.LEFT,style:{paragraph:{indent:{left:540,hanging:270}}}}]}];

const c=[];
c.push(title('Panduan Penggunaan'));
c.push(p('AbsenKita Javag membantu karyawan mencatat kehadiran, mengajukan cuti atau izin, dan melihat slip gaji. Supervisor (SPV) memeriksa kehadiran dan pengajuan di cabangnya. Admin mengelola pengaturan dan menyusun slip gaji, sedangkan Owner memantau laporan.'));
c.push(p('Panduan ini ditulis untuk pengguna sehari-hari. Setiap bagian dimulai dengan gambar alur, lalu penjelasannya.'));

c.push(h2('Pembagian peran'));
c.push(...img('01-peran.png','Gambar 1. Apa yang bisa dilakukan setiap peran.'));
c.push(...table([2100,7836],['Pengguna','Tugas dalam aplikasi'],[
 ['Karyawan','Mendaftarkan wajah, absen masuk dan pulang, mengajukan cuti, izin, atau dinas luar, meminta persetujuan lembur, serta melihat absensi dan slip gaji sendiri.'],
 ['Supervisor (SPV)','Memantau kehadiran dan menyetujui atau menolak pengajuan karyawan di cabangnya. Akses SPV terbatas pada cabang yang ditugaskan.'],
 ['Admin','Mengelola data karyawan, cabang, jadwal, hari libur, aturan keterlambatan, dan slip gaji. Admin juga memperbaiki catatan yang salah.'],
 ['Owner','Melihat laporan, statistik, dan data penggajian. Peran ini terutama untuk memantau, bukan menyetujui.']]));

c.push(h2('Gambaran alur kerja'));
c.push(...img('02-alur-kerja.png','Gambar 2. Dari absen sampai slip gaji.'));
c.push(p('Karyawan mencatat kehadiran atau mengirim pengajuan. SPV memeriksa dan memutuskan pengajuan di cabangnya. Admin memakai catatan kehadiran untuk menyusun slip gaji. Karyawan dan Owner kemudian melihat informasi sesuai haknya masing-masing. Setiap karyawan hanya melihat data dirinya sendiri.'));

// 1
c.push(h1('1 Cara mencatat kehadiran'));
c.push(p('Karyawan memakai halaman absen untuk mencatat jam masuk dan jam pulang. Aplikasi menyimpan satu catatan per karyawan per hari. Jam pulang melengkapi catatan hari yang sama.'));
c.push(...img('03-absen-masuk.png','Gambar 3. Absen masuk.'));
c.push(h2('Saat mulai bekerja'));
c.push(...numbered(['Buka halaman absen dan masukkan nomor karyawan Anda.','Pilih status hari ini. Biasanya **Hadir**.','Untuk status Hadir, lihat ke kamera. Wajah Anda harus sudah didaftarkan agar dikenali.','Jika Anda bekerja di lokasi tetap, izinkan akses lokasi di ponsel. Aplikasi memeriksa bahwa Anda berada di area cabang.','Pastikan muncul pesan bahwa absen berhasil. Jam masuk Anda dipakai untuk menilai ketepatan waktu.']));
c.push(h2('Saat selesai bekerja'));
c.push(...img('04-absen-pulang.png','Gambar 4. Absen pulang.'));
c.push(p('Buka halaman absen lagi dan absen pulang. Kamera dipakai sekali lagi untuk status Hadir atau Dinas Luar. Jika Anda pulang setelah jam pulang jadwal, aplikasi meminta alasan dan foto, lalu mencatatnya sebagai lembur. Setelah jam pulang tersimpan, absen pulang kedua di hari yang sama ditolak.'));
c.push(h2('Perbedaan kondisi kerja'));
c.push(...table([2600,7336],['Kondisi','Yang perlu dipahami'],[
 ['Lokasi kerja tetap','Status Hadir memerlukan kamera dan pemeriksaan bahwa Anda berada di area cabang.'],
 ['Karyawan lapangan (mode onsite)','Pemeriksaan area cabang dilewati. Kamera tetap diperlukan. Admin yang mengatur mode ini pada data karyawan.'],
 ['Dinas Luar','Memiliki alur sendiri (lihat bagian 2). Dinas yang disetujui tidak menggantikan kewajiban absen.'],
 ['OFF, Sakit, Cuti, Alpha','Tidak memerlukan kamera dan lokasi. Untuk cuti atau izin yang direncanakan, kirim pengajuan.']]));
c.push(h2('Terlambat dan hari Sabtu'));
c.push(p('Setiap cabang punya jam masuk dan batas toleransi. Jika Anda absen setelah batas itu, catatan hari tersebut ditandai **Terlambat**. Hari Sabtu adalah hari lembur, sehingga tidak ada penilaian terlambat pada hari Sabtu. Lama kerja di hari Sabtu dihitung dari jam Anda benar-benar bekerja.'));
c.push(example('jika jam masuk adalah 08.00 dan Anda absen pada 08.12, selisihnya 12 menit. Apakah ini dihitung terlambat dan berapa potongannya mengikuti toleransi dan tarif yang ditetapkan perusahaan. Angka ini hanya ilustrasi, bukan jadwal atau kebijakan perusahaan.'));
c.push(h2('Jika lupa absen masuk'));
c.push(p('Anda tetap bisa absen pulang. Hari itu tersimpan tanpa jam masuk dan ditandai agar Admin memeriksanya. Beri tahu Admin atau SPV supaya catatannya dapat dikoreksi.'));

// 2
c.push(h1('2 Pengajuan cuti, izin, dan dinas luar'));
c.push(...img('05-pengajuan-izin.png','Gambar 5. Perjalanan sebuah pengajuan.'));
c.push(p('Satu pengajuan mencakup semua hari yang Anda butuhkan, dari tanggal mulai sampai tanggal selesai. SPV di cabang Anda memutuskan setuju atau tolak. Setelah disetujui, hari yang berlaku muncul di absensi Anda. Hanya hari kerja yang dihitung. Akhir pekan, hari libur, dan tanggal yang sudah punya catatan absen tidak mengurangi jatah.'));
c.push(h2('Jenis pengajuan'));
c.push(...table([2000,4936,3000],['Jenis','Kegunaan','Mengurangi jatah cuti?'],[
 ['Cuti','Libur tahunan','Ya'],['Sakit','Anda tidak sehat','Ya'],['Izin','Keperluan pribadi','Ya'],['Dinas Luar','Bekerja di luar kantor, misalnya kunjungan klien','Tidak']]));
c.push(h2('Cara sisa jatah dihitung'));
c.push(p('Jatah cuti awal biasanya 12 hari per tahun dan dapat diatur Admin. Pengajuan yang masih menunggu ikut menahan jatah cuti. Sisa yang bisa diajukan adalah jatah cuti tahunan dikurangi hari yang sudah dipakai dan hari yang sedang ditahan.'));
c.push(example('jatah cuti 12 hari, sudah terpakai 3 hari, dan ada 2 hari yang masih menunggu persetujuan. Jatah cuti yang tersedia untuk pengajuan berikutnya adalah 12 − 3 − 2 = 7 hari.'));
c.push(h2('Dinas luar tetap membutuhkan absen'));
c.push(...img('06-dinas-luar.png','Gambar 6. Absen pada hari dinas luar.'));
c.push(p('Jika dinas luar sudah disetujui untuk tanggal itu, Anda tetap absen dan melihat ke kamera. Catatannya menjadi **Dinas Luar**. Jika belum ada persetujuan, catatannya menjadi **Pending Dinas** dan menunggu Admin menyetujuinya.'));
c.push(h2('Membatalkan pengajuan'));
c.push(p('Anda dapat membatalkan pengajuan. Hari yang belum punya jam masuk dihapus dari absensi. Hari yang sudah benar-benar Anda absen tidak pernah dihapus, sehingga riwayat kerja tetap utuh.'));
c.push(p('**Perlu diingat:** memilih Sakit atau Cuti saat absen berbeda dengan mengirim pengajuan. Untuk libur yang direncanakan, selalu kirim pengajuan agar jatah cuti dan persetujuannya tercatat.'));

// 3
c.push(h1('3 Pemeriksaan rutin oleh SPV'));
c.push(p('SPV memastikan catatan dan pengajuan di cabangnya sesuai keadaan sebenarnya. Urutan pemeriksaan harian yang disarankan:'));
c.push(...numbered(['Lihat karyawan yang belum punya catatan masuk atau pulang. Tanyakan keadaannya sebelum menganggap mereka tidak hadir.','Tinjau pengajuan cuti, izin, dinas luar, dan lembur yang menunggu. Cocokkan tanggal dan alasannya dengan keadaan tim.','Perhatikan catatan Terlambat, Pending Dinas, dan jam masuk yang kosong. Beri tahu Admin jika perlu koreksi.','Untuk lembur, pastikan pekerjaan dan foto buktinya sesuai sebelum menyetujui.']));
c.push(h2('Cara membaca keadaan yang muncul'));
c.push(...table([2700,7236],['Keadaan','Tindak lanjut yang disarankan'],[
 ['Pengajuan menunggu','Belum ada keputusan. Tinjau lalu setujui atau tolak.'],
 ['Pending Dinas','Absen dinas luar yang belum disetujui. Beri tahu Admin agar segera ditinjau.'],
 ['Ada jam pulang tanpa jam masuk','Kemungkinan karyawan lupa absen masuk. Tanyakan, lalu minta Admin mengoreksi.'],
 ['Belum ada catatan sama sekali','Cari tahu penyebabnya. Jika memang tidak masuk tanpa kabar, hari itu dapat menjadi Alpha.'],
 ['Pulang melewati jadwal','Periksa alasan dan foto lembur sebelum menyetujui.']]));
c.push(p('SPV dapat melihat dan memutuskan hal-hal di atas hanya untuk cabangnya sendiri. SPV tidak mengubah data karyawan atau slip gaji.'));

// 4
c.push(h1('4 Keterlambatan dan slip gaji',true));
c.push(p('Catatan kehadiran menjadi bahan slip gaji. Karena itu, kesalahan sebaiknya diperbaiki sebelum slip disusun.'));
c.push(h2('Cara keterlambatan menjadi potongan'));
c.push(...img('07-potongan-terlambat.png','Gambar 7. Dari terlambat sampai potongan.'));
c.push(p('Aplikasi menyimpan jumlah menit terlambat, bukan hanya jumlah rupiah. Besar potongan dihitung dari aturan yang berlaku: ada masa toleransi, lalu potongan tahap pertama, lalu tambahan untuk setiap interval berikutnya sampai batas jam maksimum. Aturan khusus cabang dipakai lebih dulu. Jika sebuah cabang tidak punya aturan sendiri, berlaku aturan umum perusahaan. Besar angkanya ditetapkan perusahaan dan dapat diubah Admin.'));
c.push(p('Karena menit terlambat disimpan, mengubah aturan di kemudian hari tidak mengubah slip yang sudah disimpan. Catatan lama yang belum menyimpan menit terlambat tetap dihitung dengan tarif tetap yang lama.'));
c.push(h2('Menyusun slip gaji (Admin)'));
c.push(...img('08-slip-gaji.png','Gambar 8. Langkah menyusun slip gaji.'));
c.push(...numbered(['Buka formulir slip gaji, lalu pilih satu karyawan dan bulannya.','Baca kotak "cara hitung". Isinya jumlah hari terlambat, total menit, dan aturan yang dipakai, dalam bahasa sederhana.','Lembur hari Sabtu muncul sebagai saran. Tekan **Tambahkan** jika ingin memasukkannya. Saran ini tidak mengubah slip lama secara diam-diam.','Simpan slip gaji. Setelah 5 hari, slip yang tersimpan terkunci dan hanya bisa dilihat.']));
c.push(p('Jika ada kesalahan setelah slip terkunci, sampaikan kepada Admin dengan nama karyawan, tanggal, dan penjelasan singkat.'));

// 5
c.push(h1('5 Jika ada masalah'));
c.push(...table([3300,6636],['Masalah','Yang bisa dilakukan'],[
 ['Wajah tidak dikenali','Hadapkan wajah ke kamera dengan cahaya cukup dan lepas topi atau masker. Jika masih gagal, minta Admin mengizinkan Anda mendaftar wajah ulang.'],
 ['Pesan "di luar area cabang"','Dekati gedung, aktifkan lokasi di ponsel, lalu coba lagi. Jika Anda bekerja di lapangan, minta Admin mengatur Anda sebagai karyawan lapangan.'],
 ['Pengajuan cuti tidak terkirim','Biasanya sisa jatah cuti tidak cukup. Periksa sisa jatah cuti di layar pengajuan, atau pilih Dinas Luar jika ini perjalanan kerja.'],
 ['Salah mengisi pengajuan yang masih menunggu','Batalkan pengajuan itu, lalu kirim yang baru.'],
 ['Tidak bisa masuk ke akun','Periksa nama pengguna atau nomor karyawan. Setelah 5 kali salah, akun terkunci selama 15 menit. Jika akun dinonaktifkan, hubungi Admin.']]));

// 6
c.push(h1('6 Daftar istilah',true));
c.push(...table([2600,7336],['Istilah','Artinya'],[
 ['Hadir','Masuk kerja'],['Terlambat','Datang setelah jam masuk dan masa toleransi'],['Sakit','Tidak masuk karena sakit'],['Cuti','Libur tahunan'],['Izin','Izin untuk keperluan pribadi'],['OFF','Hari libur'],['Alpha','Tidak masuk tanpa kabar'],['Dinas Luar','Bekerja di luar kantor'],['Pending Dinas','Absen dinas luar yang menunggu persetujuan Admin'],['Lembur','Bekerja di luar jam biasa'],['Slip gaji','Rincian gaji bulanan'],['Cabang','Kantor cabang'],['SPV','Supervisor, penanggung jawab satu cabang']]));

const doc=new Document({creator:'AbsenKita Javag',title:'Panduan Penggunaan AbsenKita Javag',
  styles:{default:{document:{run:{font:FONT,size:22}}},paragraphStyles:[
    {id:'Heading1',name:'Heading 1',basedOn:'Normal',next:'Normal',quickFormat:true,run:{size:36,bold:true,font:FONT,color:'000000'},paragraph:{outlineLevel:0,keepNext:true}},
    {id:'Heading2',name:'Heading 2',basedOn:'Normal',next:'Normal',quickFormat:true,run:{size:24,bold:true,font:FONT,color:'000000'},paragraph:{outlineLevel:1,keepNext:true}}]},
  numbering:{config:numbering},
  sections:[{properties:{page:{size:{width:12240,height:15840},margin:{top:1037,bottom:1037,left:1152,right:1152}}},
    footers:{default:new Footer({children:[new Paragraph({alignment:AlignmentType.RIGHT,children:[r('AbsenKita Javag  |  ',{size:18,color:'555555'}),new TextRun({children:[PageNumber.CURRENT],size:18,color:'555555',font:FONT})]})]})},
    children:c}]});
Packer.toBuffer(doc).then(b=>{fs.writeFileSync(OUT,b);console.log('wrote',OUT)});
