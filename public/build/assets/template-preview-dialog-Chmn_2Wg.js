import{n as e,t}from"./jsx-runtime-CTQSNLil.js";import{t as n}from"./button-HA4fTpI7.js";import{a as r,i,o as a,r as o,s,t as c}from"./dialog-CVe3aja3.js";var l=e(),u=/\{\{\s*([a-z0-9_]+)\s*\}\}/gi,d=`data-editor-only`,f=`data-template-style`;function p(e){return/<html[\s>]/i.test(e)}function m(e){return e.toLowerCase().includes(`landscape`)}function h(e,t){return`${t?`html { background: #e8ebf0; } body { background: #fff; margin: 24px auto; box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`:`html { background: #e8ebf0; }
           body { ${e?`width: 297mm; min-height: 210mm;`:`width: 210mm; min-height: 297mm;`} box-sizing: border-box; margin: 28px auto; padding: 20mm 15mm; background: #fff;
                  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`}
        body { outline: none; caret-color: #0b5cad; }
        body:empty::before, body.is-empty::before { content: 'Mulai ketik isi dokumen di sini…'; color: #9ca3af; }
        ::selection { background: #cfe3fb; }
        .ph { display: inline-block; padding: 0 4px; margin: 0 1px; border-radius: 3px; background: #e3eefc;
              color: #0b4a8f; border: 1px solid #b9d3f5; font-family: system-ui, sans-serif; font-size: .85em;
              line-height: 1.35; white-space: nowrap; cursor: default; user-select: all; text-indent: 0; }
        .ph.is-unknown { background: #fdecec; color: #9b1c1c; border-color: #f5c2c2; }
        .page-break { height: 0; border-top: 2px dashed #94a3b8; margin: 18px -15mm; position: relative; }
        .page-break::after { content: 'Halaman baru'; position: absolute; left: 50%; top: -9px; transform: translateX(-50%);
              background: #e8ebf0; color: #475569; font: 600 10px system-ui, sans-serif; padding: 1px 8px; border-radius: 8px; }
        .signature td, .lampiran-head td, table.plain td, table.plain th { outline: 1px dashed #cbd5e1; outline-offset: -1px; }
        td:focus-within, th:focus-within { background-color: #f5f9ff; }
        img { max-width: 100%; }`}function g(e){return e.replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`)}function _(e,t){let n=t.get(e);return`<span class="${n===void 0?`ph is-unknown`:`ph`}" contenteditable="false" data-ph="${g(e)}" title="{{${g(e)}}}">${g(n??e)}</span>`}function v(e,t){let n=p(e),r=`<style ${d} id="editor-page">${h(m(e),n)}</style>`;return n?/<\/head>/i.test(e)?e.replace(/<\/head>/i,`${r}</head>`):e.replace(/<html([^>]*)>/i,`<html$1><head>${r}</head>`):`<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style ${d}>${t}</style>
${r}
</head>
<body>${e}</body>
</html>`}function y(e,t){let n=new Map(t.map(e=>[e.key,e.label]));e.body.querySelectorAll(`style`).forEach(t=>{t.setAttribute(f,``),e.head.appendChild(t)});let r=e.createTreeWalker(e.body,NodeFilter.SHOW_TEXT),i=[];for(;r.nextNode();){let e=r.currentNode;u.test(e.data)&&i.push(e),u.lastIndex=0}i.forEach(t=>{let r=g(t.data).replace(u,(e,t)=>_(t.toLowerCase(),n)),i=e.createElement(`span`);i.innerHTML=r,t.replaceWith(...Array.from(i.childNodes))}),e.body.contentEditable=`true`,e.body.spellcheck=!1}function b(e){e.querySelectorAll(`span.ph[data-ph]`).forEach(e=>{let t=e.getAttribute(`data-ph`)??``;e.replaceWith(e.ownerDocument.createTextNode(`{{${t}}}`))})}function x(e,t){if(t){let t=e.documentElement.cloneNode(!0);t.querySelectorAll(`[${d}]`).forEach(e=>e.remove()),t.querySelectorAll(`[${f}]`).forEach(e=>e.removeAttribute(f));let n=t.querySelector(`body`);return n?.removeAttribute(`contenteditable`),n?.removeAttribute(`spellcheck`),n?.classList.remove(`is-empty`),n?.getAttribute(`class`)===``&&n.removeAttribute(`class`),b(t),`<!DOCTYPE html>\n${t.outerHTML}`}let n=e.body.cloneNode(!0);b(n);let r=Array.from(e.head.querySelectorAll(`style[${f}]`)).map(e=>`<style>${e.textContent??``}</style>`).join(`
`),i=n.innerHTML.trim();return r===``?i:`${r}\n${i}`}function S(e,t){let n=Array.from(e.head.querySelectorAll(`style[${f}]`));if(t){if(!n.some(e=>(e.textContent??``).toLowerCase().includes(`landscape`))){let t=e.createElement(`style`);t.setAttribute(f,``),t.textContent=`@page { size: A4 landscape; }`,e.head.appendChild(t)}}else n.forEach(e=>{let t=e.textContent??``;if(t.trim()===`@page { size: A4 landscape; }`){e.remove();return}e.textContent=t.replace(/landscape/gi,`portrait`)});let r=e.getElementById(`editor-page`);r!==null&&(r.textContent=h(t,!1))}var C={nomor_pengadaan:`001/PENG/612/UPKD/2026`,nama_pengadaan:`JASA PEMBUATAN WEB DIGITALISASI PLN NP UP KENDARI`,nama_mitra:`PT KREATIF TEKNOLOGI MAJU BERSAMA`,nama_direktur:`Budi Santoso`,alamat_mitra:`Jl. Malaka No. 12, Kendari, Sulawesi Tenggara`,alamat_perusahaan:`Jl. Malaka No. 12, Kendari, Sulawesi Tenggara`,direksi_pekerjaan:`Manager UPDK Kendari`,unit_tujuan:`PLN NP UP Kendari`,metode_pengadaan:`Pengadaan Langsung`,sumber_anggaran:`AO`,sumber_anggaran_keterangan:`Anggaran Operasi`,jenis_kontrak:`Lumsum`,nomor_nota_dinas_manager:`ND-012/UPKD/2026`,nomor_pr_ro:`PR-2026-0042`,nomor_prk:`KD262O0306`,nilai_hpe:`Rp 85.000.000,00`,nilai_hpe_angka:`85.000.000`,nilai_hpe_terbilang:`Delapan puluh lima juta rupiah`,status_progres:`Penyusunan TOR`,pic_perencana:`Dikhsan Tibong`,pic_pelaksana:`Andi Pratama`,target_penyelesaian:`30 April 2026`,tanggal_dokumen:`04 Maret 2026`,tahun:`2026`,checklist_perencanaan:`<ul><li>[✓] TOR</li><li>[✓] RAB</li><li>[✓] HPE</li></ul>`,checklist_pelaksanaan:`<ul><li>[-] Penawaran</li></ul>`};function w(e,t){let n=e.replace(u,(e,t)=>C[t.toLowerCase()]??e);if(p(n))return n;let r=m(e);return`<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    ${t??`body { font-family: "Times New Roman", Times, serif; font-size: 10pt; line-height: 1.45; color: #111; }
         table { width: 100%; border-collapse: collapse; margin: 8pt 0; }
         td, th { border: 1px solid #444; padding: 4pt 6pt; vertical-align: top; }
         table.plain td, table.plain th, .signature td, .lampiran-head td { border: none; }
         .signature td { text-align: center; } .signature .space { height: 56pt; }
         .signature .name { font-weight: bold; text-decoration: underline; }`}
    html { background: #fff; }
    body { max-width: ${r?`297mm`:`210mm`}; margin: 0 auto; padding: 15mm; box-sizing: border-box; }
    .page-break { border-top: 1px dashed #cbd5e1; margin: 16px 0; }
</style>
</head>
<body>${n}</body>
</html>`}var T=t();function E(e){let t=(0,l.c)(26),{title:u,body:d,documentStylesheet:f,onClose:p}=e,h;t[0]===d?h=t[1]:(h=d!==null&&m(d),t[0]=d,t[1]=h);let g=h,_=d!==null,v;t[2]===p?v=t[3]:(v=e=>{e||p()},t[2]=p,t[3]=v);let y=`flex h-[92vh] flex-col p-4 sm:p-6 ${g?`sm:max-w-6xl`:`sm:max-w-5xl`}`,b;t[4]===u?b=t[5]:(b=(0,T.jsxs)(s,{children:[`Pratinjau: `,u]}),t[4]=u,t[5]=b);let x;t[6]===Symbol.for(`react.memo_cache_sentinel`)?(x=(0,T.jsx)(i,{children:`Tampilan dokumen dengan contoh data pengadaan.`}),t[6]=x):x=t[6];let S;t[7]===b?S=t[8]:(S=(0,T.jsxs)(a,{className:`shrink-0`,children:[b,x]}),t[7]=b,t[8]=S);let C;t[9]!==d||t[10]!==f||t[11]!==g?(C=d!==null&&(0,T.jsx)(`iframe`,{title:`Pratinjau Template Dokumen`,srcDoc:w(d,f),className:`h-full w-full border border-border bg-white shadow-sm ${g?`max-w-[297mm]`:`max-w-[210mm]`}`}),t[9]=d,t[10]=f,t[11]=g,t[12]=C):C=t[12];let E;t[13]===C?E=t[14]:(E=(0,T.jsx)(`div`,{className:`flex min-h-0 w-full flex-1 justify-center overflow-hidden rounded-md border border-border bg-muted/40 p-2 sm:p-4`,children:C}),t[13]=C,t[14]=E);let D;t[15]===p?D=t[16]:(D=(0,T.jsx)(r,{className:`shrink-0`,children:(0,T.jsx)(n,{type:`button`,variant:`outline`,onClick:p,children:`Tutup`})}),t[15]=p,t[16]=D);let O;t[17]!==D||t[18]!==y||t[19]!==S||t[20]!==E?(O=(0,T.jsxs)(o,{className:y,children:[S,E,D]}),t[17]=D,t[18]=y,t[19]=S,t[20]=E,t[21]=O):O=t[21];let k;return t[22]!==O||t[23]!==_||t[24]!==v?(k=(0,T.jsx)(c,{open:_,onOpenChange:v,children:O}),t[22]=O,t[23]=_,t[24]=v,t[25]=k):k=t[25],k}export{_ as a,S as c,m as i,v as n,y as o,p as r,x as s,E as t};