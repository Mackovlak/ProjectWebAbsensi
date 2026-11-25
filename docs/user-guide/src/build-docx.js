// Usage: node build-docx.js <flowsDir> <outDir>   (run with docx installed: NODE_PATH=<node_modules>)
const fs=require('fs'),{execSync}=require('child_process');
const D=require('docx'),DATA=require('./data.js');
const {Document,Packer,Paragraph,TextRun,ImageRun,Table,TableRow,TableCell,HeadingLevel,AlignmentType,WidthType,ShadingType,BorderStyle,LevelFormat,Footer,PageNumber}=D;
const [,,FL,OUT]=process.argv; fs.mkdirSync(OUT,{recursive:true});
const W=9026,FONT='Calibri',ACC='0D7A5A',MUTED='51655B',TINT='E6F1EC',WARN='FDF1DC',WARNT='9A5B00';
const r=(t,o={})=>new TextRun({text:t,...o});
const p=(t,o={})=>new Paragraph({spacing:{after:140,line:310},...o,children:Array.isArray(t)?t:[r(t)]});
const h1=(t,pb=true)=>new Paragraph({heading:HeadingLevel.HEADING_1,pageBreakBefore:pb,children:[r(t)]});
const h2=t=>new Paragraph({heading:HeadingLevel.HEADING_2,children:[r(t)]});
const bullet=t=>new Paragraph({numbering:{reference:'b',level:0},spacing:{after:80,line:300},children:[r(t)]});
const num=t=>new Paragraph({numbering:{reference:'n',level:0},spacing:{after:80,line:300},children:[r(t)]});
const sp=()=>new Paragraph({spacing:{after:100},children:[]});
function img(file,maxW=590){
  const [w,h]=execSync(`identify -format "%w %h" ${file}`).toString().split(' ').map(Number); const s=maxW/w;
  return new Paragraph({alignment:AlignmentType.CENTER,spacing:{before:120,after:80},keepNext:true,children:[new ImageRun({type:'png',data:fs.readFileSync(file),transformation:{width:Math.round(w*s),height:Math.round(h*s)},altText:{title:'diagram',description:'diagram',name:file}})]});
}
const cap=t=>new Paragraph({alignment:AlignmentType.CENTER,spacing:{after:240},children:[r(t,{italics:true,size:19,color:MUTED})]});
const bd={style:BorderStyle.SINGLE,size:4,color:'D5E2DB'}, borders={top:bd,bottom:bd,left:bd,right:bd};
const none={style:BorderStyle.NONE,size:0,color:'FFFFFF'};
function table(cols,head,rows){
  const cell=(t,w,hd,first)=>new TableCell({borders,width:{size:w,type:WidthType.DXA},margins:{top:80,bottom:80,left:120,right:120},shading:hd?{fill:TINT,type:ShadingType.CLEAR,color:'auto'}:undefined,children:[new Paragraph({children:[r(t,{bold:hd||first,size:hd?20:22})]})]});
  return new Table({width:{size:W,type:WidthType.DXA},columnWidths:cols,rows:[new TableRow({tableHeader:true,children:head.map((t,i)=>cell(t,cols[i],true))}),...rows.map(x=>new TableRow({cantSplit:true,children:x.map((t,i)=>cell(t,cols[i],false,i==0))}))]});
}
function note(kind,title,text){
  const fill=kind=='tip'?TINT:WARN, col=kind=='tip'?ACC:WARNT;
  return new Table({width:{size:W,type:WidthType.DXA},columnWidths:[W],rows:[new TableRow({cantSplit:true,children:[new TableCell({width:{size:W,type:WidthType.DXA},margins:{top:100,bottom:100,left:200,right:200},
    borders:{top:none,bottom:none,right:none,left:{style:BorderStyle.SINGLE,size:24,color:col}},shading:{fill,type:ShadingType.CLEAR,color:'auto'},
    children:[new Paragraph({spacing:{after:40},children:[r(title,{bold:true,color:col})]}),new Paragraph({spacing:{line:300},children:[r(text)]})]})]})]});
}
const noteSet=a=>a.flatMap(n=>[note(...n),sp()]);

function build(lg){
  const L=DATA[lg],C=L.checkin,V=L.leave,S=L.slip,H=L.help,Wd=L.words,R=L.role, F=n=>`${FL}/${lg}-flow${n}.png`;
  const c=[];
  c.push(new Paragraph({spacing:{before:2600,after:200},children:[r(L.eyebrow.toUpperCase(),{bold:true,size:22,color:ACC,characterSpacing:40})]}));
  c.push(new Paragraph({spacing:{after:200},children:[r(L.name,{bold:true,size:72})]}));
  c.push(new Paragraph({spacing:{after:400},border:{bottom:{style:BorderStyle.SINGLE,size:12,color:ACC,space:8}},children:[r(L.tagline,{size:30,color:MUTED})]}));
  c.push(p(L.lede,{spacing:{after:200,line:340}}));
  c.push(h1(lg=='en'?'Contents':'Daftar isi'));
  [R.h,C.h,V.h,S.h,H.h,Wd.h].forEach((t,i)=>c.push(p(`${i+1}.  ${t}`,{spacing:{after:160}})));
  // 1 roles
  c.push(h1(`1. ${R.h}`)); c.push(p(R.sub));
  R.roles.forEach(ro=>{
    c.push(h2(ro.name)); c.push(p(ro.who,{spacing:{after:80}}));
    c.push(p([r(L.youCan,{bold:true,color:ACC})],{spacing:{after:60}})); ro.can.forEach(x=>c.push(bullet(x)));
    c.push(p([r(L.know,{bold:true,color:MUTED})],{spacing:{before:100,after:60}})); ro.know.forEach(x=>c.push(bullet(x)));
  });
  // 2 checkin
  c.push(h1(`2. ${C.h}`)); c.push(img(F(1))); c.push(cap(`${L.figure} 1. ${C.cap}`)); c.push(p(C.sub));
  c.push(...noteSet(C.notes)); C.subs.forEach(s=>{c.push(h2(s[0]));c.push(p(s[1]));});
  // 3 leave
  c.push(h1(`3. ${V.h}`)); c.push(img(F(2))); c.push(cap(`${L.figure} 2. ${V.cap}`)); c.push(p(V.sub));
  c.push(table([1700,4100,3226],V.thead,V.rows)); c.push(sp()); c.push(...noteSet(V.notes));
  // 4 slip
  c.push(h1(`4. ${S.h}`)); c.push(img(F(3))); c.push(cap(`${L.figure} 3. ${S.cap}`)); c.push(p(S.sub));
  c.push(...noteSet(S.notes)); c.push(h2(S.h3)); S.steps.forEach(x=>c.push(num(x)));
  // 5 help
  c.push(h1(`5. ${H.h}`)); H.qa.forEach(q=>{c.push(h2(q[0]));c.push(p(q[1]));});
  // 6 words
  c.push(h1(`6. ${Wd.h}`)); c.push(p(Wd.sub)); c.push(table([2600,6426],Wd.thead,Wd.rows)); c.push(sp()); c.push(p([r(Wd.footer,{italics:true,color:MUTED,size:20})]));
  const doc=new Document({creator:'AbsenKita Javag',title:L.title,
    styles:{default:{document:{run:{font:FONT,size:23}}},paragraphStyles:[
      {id:'Heading1',name:'Heading 1',basedOn:'Normal',next:'Normal',quickFormat:true,run:{size:36,bold:true,font:FONT,color:'17261F'},paragraph:{spacing:{before:0,after:240},outlineLevel:0,border:{bottom:{style:BorderStyle.SINGLE,size:8,color:ACC,space:4}}}},
      {id:'Heading2',name:'Heading 2',basedOn:'Normal',next:'Normal',quickFormat:true,run:{size:27,bold:true,font:FONT,color:ACC},paragraph:{spacing:{before:260,after:100},outlineLevel:1,keepNext:true}}]},
    numbering:{config:[{reference:'b',levels:[{level:0,format:LevelFormat.BULLET,text:'•',alignment:AlignmentType.LEFT,style:{paragraph:{indent:{left:540,hanging:270}}}}]},
      {reference:'n',levels:[{level:0,format:LevelFormat.DECIMAL,text:'%1.',alignment:AlignmentType.LEFT,style:{paragraph:{indent:{left:540,hanging:360}}}}]}]},
    sections:[{properties:{page:{size:{width:11906,height:16838},margin:{top:1200,bottom:1100,left:1440,right:1440}}},
      footers:{default:new Footer({children:[new Paragraph({alignment:AlignmentType.CENTER,children:[r(`${L.name} · ${L.eyebrow} · ${lg=='en'?'Page':'Halaman'} `,{size:18,color:MUTED}),new TextRun({children:[PageNumber.CURRENT],size:18,color:MUTED})]})]})},
      children:c}]});
  return Packer.toBuffer(doc).then(b=>{fs.writeFileSync(`${OUT}/${L.file}.docx`,b);console.log('wrote',L.file);});
}
(async()=>{await build('en');await build('id');})();
