// Usage: node gen-flows.js <template.html> <outDir>  -> writes <lang>-flowN.png using headless chromium (needs ImageMagick for trim)
const fs=require('fs'),{execSync}=require('child_process');const D=require('./data.js');
const [,,tpl,out]=process.argv; fs.mkdirSync(out,{recursive:true});
const t=fs.readFileSync(tpl,'utf8'); const st=t.indexOf('/* Layout'); let css=t.slice(st,t.indexOf('</style>',st));
const esc=s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;');
for(const lg of ['en','id']){ [['checkin'],['leave'],['slip']].forEach(([k],i)=>{
  const f=D[lg][k].flow;
  const cols=f.length>4?3:2; const html=`<meta charset=utf8><html data-theme="light"><style>${css} body{padding:20px;background:#fff;width:820px} .wrap{max-width:none} .flow li:nth-child(${cols}n)::after{display:none}</style><body><ol class="flow" style="grid-template-columns:repeat(${cols},1fr)">${f.map((x,j)=>`<li${j==f.length-1?' class="end"':''}><b>${esc(x[0])}</b><span>${esc(x[1])}</span></li>`).join('')}</ol></body>`;
  const h=`${out}/${lg}-flow${i+1}`; fs.writeFileSync(h+'.html',html);
  execSync(`chromium --headless --no-sandbox --disable-gpu --hide-scrollbars --virtual-time-budget=4000 --force-device-scale-factor=2 --window-size=860,520 --screenshot=${h}.png file://${h}.html 2>/dev/null; magick ${h}.png -trim -bordercolor white -border 16 ${h}.png`);
});}
