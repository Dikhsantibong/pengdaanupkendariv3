import{c as e,d as t,l as n,s as r}from"./pdf-preview-CHdDm8Cp.js";var i=/\{\{\s*([a-z0-9_]+)\s*\}\}/gi,a=`data-editor-only`,o=`data-template-style`;function s(e){return/<html[\s>]/i.test(e)}function c(e){return n(e).landscape}var l=`data-page-setup`;function u(e){let{top:t,right:n,bottom:r,left:i}=e.margins;return`${t}mm ${n}mm ${r}mm ${i}mm`}function d(t,n){let{width:r,height:i}=e(t),a=`width: ${r}mm; min-height: ${i}mm;`,{right:o,left:s}=t.margins;return`${n?`html { background: #e8ebf0; }
           body { ${a} box-sizing: border-box; margin: 24px auto !important; padding: ${u(t)} !important; background: #fff;
                  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`:`html { background: #e8ebf0; }
           body { ${a} box-sizing: border-box; margin: 28px auto; padding: ${u(t)}; background: #fff;
                  box-shadow: 0 1px 3px rgba(15,23,42,.15), 0 8px 24px rgba(15,23,42,.08); }`}
        body { outline: none; caret-color: #0b5cad; }
        body:empty::before, body.is-empty::before { content: 'Mulai ketik isi dokumen di sini…'; color: #9ca3af; }
        ::selection { background: #cfe3fb; }
        .ph { display: inline-block; padding: 0 4px; margin: 0 1px; border-radius: 3px; background: #e3eefc;
              color: #0b4a8f; border: 1px solid #b9d3f5; font-family: system-ui, sans-serif; font-size: .85em;
              line-height: 1.35; white-space: nowrap; cursor: default; user-select: all; text-indent: 0; }
        .ph.is-unknown { background: #fdecec; color: #9b1c1c; border-color: #f5c2c2; }
        .page-break { height: 0; border-top: 2px dashed #94a3b8; margin: 18px -${o}mm 18px -${s}mm; position: relative; }
        .page-break::after { content: 'Halaman baru'; position: absolute; left: 50%; top: -9px; transform: translateX(-50%);
              background: #e8ebf0; color: #475569; font: 600 10px system-ui, sans-serif; padding: 1px 8px; border-radius: 8px; }
        .signature td, .lampiran-head td, table.plain td, table.plain th { outline: 1px dashed #cbd5e1; outline-offset: -1px; }
        td:focus-within, th:focus-within { background-color: #f5f9ff; }
        img { max-width: 100%; }
        /* Keep absolute cover footers and running PDF headers in normal flow in the
           visual editor so multi-page templates or long placeholder chips never overlap. */
        .cover-page { display: flex !important; flex-direction: column !important; min-height: 262mm !important; }
        .cover-footer { position: static !important; margin-top: auto !important; padding-top: 24px !important; }
        .header-logo { position: static !important; top: auto !important; height: auto !important; margin-bottom: 10px !important; }
        /* The RKS cover's artwork runs past the cover to the edge of the
           paper, but never over the page after it. */
        .rks-cover { clip-path: inset(-${t.margins.top}mm -${o}mm 0 -${s}mm); }`}function f(e){return e.replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`).replace(/"/g,`&quot;`)}function p(e,t){let n=t.get(e);return`<span class="${n===void 0?`ph is-unknown`:`ph`}" contenteditable="false" data-ph="${f(e)}" title="{{${f(e)}}}">${f(n??e)}</span>`}function m(e,t){let r=s(e),i=`<style ${a} id="editor-page">${d(n(e),r)}</style>`;return r?/<\/head>/i.test(e)?e.replace(/<\/head>/i,`${i}</head>`):e.replace(/<html([^>]*)>/i,`<html$1><head>${i}</head>`):`<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style ${a}>${t}</style>
${i}
</head>
<body>${e}</body>
</html>`}function h(e,t){let n=new Map(t.map(e=>[e.key,e.label]));e.body.querySelectorAll(`style`).forEach(t=>{t.setAttribute(o,``),e.head.appendChild(t)});let r=e.createTreeWalker(e.body,NodeFilter.SHOW_TEXT),a=[];for(;r.nextNode();){let e=r.currentNode;i.test(e.data)&&a.push(e),i.lastIndex=0}a.forEach(t=>{let r=f(t.data).replace(i,(e,t)=>p(t.toLowerCase(),n)),a=e.createElement(`span`);a.innerHTML=r,t.replaceWith(...Array.from(a.childNodes))}),e.body.contentEditable=`true`,e.body.spellcheck=!1}function g(e){e.querySelectorAll(`span.ph[data-ph]`).forEach(e=>{let t=e.getAttribute(`data-ph`)??``;e.replaceWith(e.ownerDocument.createTextNode(`{{${t}}}`))})}function _(e,t){if(t){let t=e.documentElement.cloneNode(!0);t.querySelectorAll(`[${a}]`).forEach(e=>e.remove()),t.querySelectorAll(`[${o}]`).forEach(e=>e.removeAttribute(o));let n=t.querySelector(`body`);return n?.removeAttribute(`contenteditable`),n?.removeAttribute(`spellcheck`),n?.classList.remove(`is-empty`),n?.getAttribute(`class`)===``&&n.removeAttribute(`class`),g(t),`<!DOCTYPE html>\n${t.outerHTML}`}let n=e.body.cloneNode(!0);g(n);let r=Array.from(e.head.querySelectorAll(`style[${o}]`)).map(e=>e.hasAttribute(l)?`<style ${l}>${e.textContent??``}</style>`:`<style>${e.textContent??``}</style>`).join(`
`),i=n.innerHTML.trim();return r===``?i:`${r}\n${i}`}function v(e){return n(t(e))}function y(e,t,n,i){if(e.querySelectorAll(`style[${l}]`).forEach(e=>e.remove()),t!==null){let n=e.createElement(`style`);n.setAttribute(o,``),n.setAttribute(l,``),n.textContent=r(t),e.head.appendChild(n)}let a=e.getElementById(`editor-page`);a!==null&&(a.textContent=d(t??n,i),e.head.appendChild(a))}var b={nomor_pengadaan:`001/PENG/612/UPKD/2026`,nama_pengadaan:`JASA PEMBUATAN WEB DIGITALISASI PLN NP UP KENDARI`,nama_mitra:`PT KREATIF TEKNOLOGI MAJU BERSAMA`,nama_direktur:`Budi Santoso`,alamat_mitra:`Jl. Malaka No. 12, Kendari, Sulawesi Tenggara`,alamat_perusahaan:`Jl. Malaka No. 12, Kendari, Sulawesi Tenggara`,direksi_pekerjaan:`Manager UPDK Kendari`,unit_tujuan:`PLN NP UP Kendari`,metode_pengadaan:`Pengadaan Langsung`,sumber_anggaran:`AO`,sumber_anggaran_keterangan:`Anggaran Operasi`,jenis_kontrak:`Lumsum`,nomor_nota_dinas_manager:`ND-012/UPKD/2026`,nomor_pr_ro:`PR-2026-0042`,nomor_prk:`KD262O0306`,nilai_hpe:`Rp 85.000.000,00`,nilai_hpe_angka:`85.000.000`,nilai_hpe_terbilang:`Delapan puluh lima juta rupiah`,status_progres:`Penyusunan TOR`,pic_perencana:`Dikhsan Tibong`,pic_pelaksana:`Andi Pratama`,target_penyelesaian:`30 April 2026`,tanggal_dokumen:`04 Maret 2026`,tahun:`2026`,checklist_perencanaan:`<ul><li>[✓] TOR</li><li>[✓] RAB</li><li>[✓] HPE</li></ul>`,checklist_pelaksanaan:`<ul><li>[-] Penawaran</li></ul>`,tanggal_terbit:`06 - 02 - 2023`,tanggal_terbit_tor:`06 Februari 2023`,tahun_terbit_tor:`2023`,nama_penyusun:`Galih Prima Yoga`,nama_asman_pengguna:`Asman Pemeliharaan`,nama_asman_engineering:`Asman Engineering`,pengesahan_tor:`<table class="approval-table"><tr><td><b>DISUSUN OLEH:</b></td></tr><tr><td>PENYUSUN</td></tr><tr><td><div class="gap"></div><span class="name">( GALIH PRIMA YOGA )</span></td></tr></table><table class="approval-table two" style="margin-top:20px;"><tr><td colspan="2"><b>DIPERIKSA/DIKETAHUI OLEH:</b></td></tr><tr><td>ASMAN PEMELIHARAAN</td><td>ASMAN ENGINEERING</td></tr><tr><td><div class="gap"></div><span class="name">( ASMAN PEMELIHARAAN )</span></td><td><div class="gap"></div><span class="name">( ASMAN ENGINEERING )</span></td></tr></table><table class="approval-table" style="margin-top:20px;"><tr><td><b>DISETUJUI OLEH:</b></td></tr><tr><td>MANAGER</td></tr><tr><td><div class="gap"></div><span class="name">( MUHAMMAD RUSLI )</span></td></tr></table>`,jenis_dokumen_kontrak_judul:`Surat Perintah Kerja (SPK)`,nomor_kontrak:`KDD066.SPK/612/UPKD/2026`,tanggal_kontrak:`30 Juli 2026`,nilai_setelah_nego_total_angka:`289.266.000`,nilai_setelah_nego_total_terbilang:`Dua Ratus Delapan Puluh Sembilan Juta Dua Ratus Enam Puluh Enam Ribu Rupiah`,nama_bank:`BANK BRI`,nomor_rekening:`0587.01.000012.30.6`,nama_pemilik_rekening:`CV. BINTANG MAS`,masa_garansi_bulan:`6`,masa_garansi_bulan_terbilang:`enam`,unit_penerima:`PLTD WUA WUA`,up_penerima:`MANAGER ULPLTD WUA WUA`,nama_penerima:`SURYADI PRATAMA`,nama_manager_penerima:`ABDUL RAHMAN KADIR`,judul_berita_acara:`BERITA ACARA PENYELESAIAN PEKERJAAN`,nama_berita_acara:`Berita Acara Pemeriksaan Barang`,tabel_surat_jalan:`<table class="surat-items"><tr><th>No.</th><th>Nama Barang</th><th>Part Number</th><th>Vol.</th><th>Sat.</th><th>Keterangan</th></tr><tr><td class="c">1.</td><td>Packing Oil Cooler</td><td class="c">37739-10200</td><td class="c">1</td><td class="c">Bh</td><td></td></tr><tr><td class="c">2.</td><td>O Ring</td><td class="c">05507-10140</td><td class="c">16</td><td class="c">Bh</td><td></td></tr></table>`};function x(e){return e.replace(i,(e,t)=>b[t.toLowerCase()]??e)}function S(t,r){let a=t.replace(i,(e,t)=>b[t.toLowerCase()]??e),o=n(t),{width:c}=e(o);if(s(a)){let e=`<style>@media screen { html { background: #e8ebf0; } body { max-width: ${c}mm; box-sizing: border-box; margin: 16px auto !important; padding: ${u(o)} !important; background: #fff; } }</style>`;return/<\/head>/i.test(a)?a.replace(/<\/head>/i,`${e}</head>`):e+a}return`<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    ${r??`body { font-family: "Times New Roman", Times, serif; font-size: 10pt; line-height: 1.45; color: #111; }
         table { width: 100%; border-collapse: collapse; margin: 8pt 0; }
         td, th { border: 1px solid #444; padding: 4pt 6pt; vertical-align: top; }
         table.plain td, table.plain th, .signature td, .lampiran-head td { border: none; }
         .signature td { text-align: center; } .signature .space { height: 56pt; }
         .signature .name { font-weight: bold; text-decoration: underline; }`}
    html { background: #fff; }
    body { max-width: ${c}mm; margin: 0 auto; padding: ${u(o)}; box-sizing: border-box; }
    .page-break { border-top: 1px dashed #cbd5e1; margin: 16px 0; }
</style>
</head>
<body>${a}</body>
</html>`}export{p as a,_ as c,c as i,y as l,x as n,h as o,s as r,S as s,m as t,v as u};