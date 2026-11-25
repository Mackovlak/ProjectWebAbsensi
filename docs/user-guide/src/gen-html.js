// Usage: node gen-html.js <en|id> <template.html> <out.html> [otherUrl]
const fs=require('fs'); const D=require('./data.js');
const [,, lg, tpl, out, otherUrl]=process.argv; const L=D[lg];
const esc=s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;');
const t=fs.readFileSync(tpl,'utf8');
const head=t.slice(t.indexOf('<title>'),t.indexOf('<div class="wrap">')).replace(/<title>.*?<\/title>/,`<title>${L.title}</title>`);
const script=t.slice(t.indexOf('<script>'),t.indexOf('</script>')+9)+'\n';
const flow=(f,n,cap)=>`<figure>\n    <ol class="flow">\n${f.map((x,i)=>`      <li${i==f.length-1?' class="end"':''}><b>${esc(x[0])}</b><span>${esc(x[1])}</span></li>`).join('\n')}\n    </ol>\n    <figcaption>${L.figure} ${n}. ${esc(cap)}</figcaption>\n  </figure>`;
const notes=a=>`<div class="grid2">\n${a.map(n=>`    <div class="note ${n[0]}"><b>${esc(n[1])}</b>${esc(n[2])}</div>`).join('\n')}\n  </div>`;
const ul=(a,c)=>`<ul class="tick${c?' no':''}">\n${a.map(x=>`        <li>${esc(x)}</li>`).join('\n')}\n      </ul>`;
const tbl=(h,r)=>`<div class="tw"><table>\n    <thead><tr>${h.map(x=>`<th>${esc(x)}</th>`).join('')}</tr></thead>\n    <tbody>\n${r.map(x=>`      <tr>${x.map(c=>`<td>${esc(c)}</td>`).join('')}</tr>`).join('\n')}\n    </tbody>\n  </table></div>`;
const C=L.checkin,V=L.leave,S=L.slip,H=L.help,W=L.words,R=L.role;
const body=`<div class="wrap">
<header class="top">
  <p class="eyebrow">${esc(L.eyebrow)}</p>
  <h1>${esc(L.name)}</h1>
  <p class="lede">${esc(L.lede)}</p>
</header>

<nav class="jump" aria-label="${esc(L.tabsLabel)}">
  ${L.nav.map((n,i)=>`<a href="#${L.navId[i]}">${esc(n)}</a>`).join('')}
</nav>

<section id="role">
  <h2>${esc(R.h)}</h2>
  <p class="sub">${esc(R.sub)}</p>
  <div class="tabs" role="tablist" aria-label="${esc(L.tabsLabel)}">
${R.roles.map((r,i)=>`    <button role="tab" id="t-${r.id}" aria-selected="${i==0}" aria-controls="p-${r.id}">${esc(r.name)}</button>`).join('\n')}
  </div>
${R.roles.map((r,i)=>`
  <div class="panel" role="tabpanel" id="p-${r.id}" aria-labelledby="t-${r.id}"${i?' hidden':''}>
    <p class="who">${esc(r.who)}</p>
    <div class="cols">
      <div><h3>${esc(L.youCan)}</h3>${ul(r.can)}</div>
      <div><h3>${esc(L.know)}</h3>${ul(r.know,1)}</div>
    </div>
  </div>`).join('\n')}
</section>

<section id="checkin">
  <h2>${esc(C.h)}</h2>
  <p class="sub">${esc(C.sub)}</p>
  ${flow(C.flow,1,C.cap)}
  ${notes(C.notes)}
${C.subs.map(s=>`  <h3>${esc(s[0])}</h3>\n  <p>${esc(s[1])}</p>`).join('\n\n')}
</section>

<section id="leave">
  <h2>${esc(V.h)}</h2>
  <p class="sub">${esc(V.sub)}</p>
  ${flow(V.flow,2,V.cap)}
  <div style="margin-bottom:16px">${tbl(V.thead,V.rows)}</div>
  ${notes(V.notes)}
</section>

<section id="slip">
  <h2>${esc(S.h)}</h2>
  <p class="sub">${esc(S.sub)}</p>
  ${flow(S.flow,3,S.cap)}
  ${notes(S.notes)}
  <h3>${esc(S.h3)}</h3>
  <ol class="small">
${S.steps.map(x=>`    <li>${esc(x)}</li>`).join('\n')}
  </ol>
</section>

<section id="help">
  <h2>${esc(H.h)}</h2>
  <p class="sub">${esc(H.sub)}</p>
${H.qa.map(q=>`  <details><summary>${esc(q[0])}</summary><div>\n    <p>${esc(q[1])}</p></div></details>`).join('\n')}
</section>

<section id="words">
  <h2>${esc(W.h)}</h2>
  <p class="sub">${esc(W.sub)}</p>
  ${tbl(W.thead,W.rows)}
  <footer>${esc(W.footer)}${otherUrl?` <a href="${otherUrl}" style="color:var(--accent)">${esc(W.other)}</a>`:''}</footer>
</section>
</div>

`;
fs.writeFileSync(out,head+body+script);
