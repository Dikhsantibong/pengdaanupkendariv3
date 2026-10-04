# Template Aplikasi Management Pengadaan UP Kendari

## Nama Aplikasi
**Sistem Management Pengadaan UP Kendari**

## Tema
**Corporate & Premium** — tampilan profesional kelas enterprise, bukan tampilan "khas AI-generated" (bukan gradien ungu-biru generik, bukan bento-grid default, bukan ikon emoji, bukan bayangan/rounded-corner berlebihan tanpa alasan).

---

# Panduan Tema & Gaya Visual (Corporate Premium)

Aplikasi ini digunakan oleh manajemen dan staf PLN Nusantara Power di lingkungan kerja formal, sehingga tampilan harus terasa **terpercaya, rapi, dan matang** — selevel dengan sistem enterprise/ERP korporat, bukan landing page startup atau template AI generik.

### Prinsip Utama

- **Serius, bukan playful.** Hindari ilustrasi kartun, emoji sebagai ikon fungsional, warna-warni cerah tanpa makna, atau animasi berlebihan.
- **Identitas korporat kuat.** Palet ditetapkan **biru muda dan putih** selaras logo PLN: putih sebagai warna dasar, satu aksen biru PLN yang konsisten untuk elemen penting (tombol utama, status aktif, highlight data, navigasi). **Kuning pisang** (kuning logo PLN) dipakai sangat terbatas — hanya sebagai penanda status "berjalan" — bukan sebagai warna dominan.
- **Tema tunggal, tidak dapat diubah.** Aplikasi dikunci pada satu tema terang biru-putih di seluruh halaman. Fitur pengaturan tampilan (appearance) dan mode gelap dinonaktifkan agar tampilan seragam di semua perangkat.
- **Hierarki visual jelas.** Data penting (nilai HPE, status progres, deadline) ditonjolkan lewat ukuran, bobot font, dan warna — bukan lewat dekorasi.
- **Densitas informasi tinggi tapi rapi.** Karena ini aplikasi kerja (bukan marketing page), gunakan tabel, grid data, dan card yang padat informasi namun tetap punya spacing dan alignment presisi, bukan padat berantakan.
- **Konsistensi komponen.** Satu jenis tombol, satu gaya badge status, satu gaya card di seluruh aplikasi — hindari campur-campur gaya antar halaman.

### Yang Harus Dihindari (ciri khas "AI-generated" generik)

- Gradien warna ungu-ke-biru atau pink-ke-oranye sebagai background utama.
- Ikon emoji (📊 📁 ✅) sebagai pengganti ikon UI — gunakan icon set profesional (mis. line-icon set seperti Lucide/Feather) dengan gaya konsisten.
- Card dengan shadow tebal melayang tanpa border, terkesan "mengambang" berlebihan.
- Font default sans-serif generik tanpa hierarki (semua teks terlihat sama beratnya).
- Layout simetris sempurna dengan whitespace berlebihan yang membuat aplikasi kerja terasa kosong/tidak padat informasi.
- Badge/status berwarna neon atau pastel yang tidak korporat.

### Tipografi

- Font utama: sans-serif profesional dengan karakter tegas dan mudah dibaca dalam tabel data (mis. Inter, IBM Plex Sans, Söhne, atau setara).
- Hierarki jelas: judul halaman (bold, ukuran besar), label section (medium, uppercase-tracking tipis untuk kesan formal), body text (regular), angka/data penting (semi-bold, tabular numerals agar rapi di tabel).

### Palet Warna (ditetapkan — biru muda & putih PLN)

Palet ini sudah tetap dan diterapkan di seluruh halaman melalui `resources/css/app.css`. Tema tunggal (light), tanpa mode gelap.

- **Base:** putih untuk kartu/permukaan, biru-putih sangat terang untuk background halaman, biru keabuan gelap untuk teks utama (bukan hitam pekat penuh).
- **Aksen Korporat:** satu warna **biru muda PLN** yang jelas untuk tombol utama, tautan, highlight data, dan elemen navigasi aktif (teks putih di atasnya).
- **Navigasi:** sidebar tetap berwarna biru PLN dengan teks putih, menjadi jangkar visual di setiap halaman.
- **Kuning pisang (logo PLN):** dipakai sangat terbatas — hanya pada badge status "berjalan" — sebagai sentuhan identitas, bukan warna utama.
- **Warna Status (Status Progres):** abu-abu untuk Pending, merah gelap untuk Batal, kuning pisang untuk proses berjalan (Penyusunan RKS, Kelengkapan Dokumen, Penawaran Harga, Disposisi AMS, Proses Validasi), hijau tua untuk selesai/tervalidasi.
- Hindari warna neon, pastel childish, atau kombinasi lebih dari 2 warna aksen sekaligus dalam satu tampilan.

### Layout & Komponen

- **Navigasi:** sidebar tetap (fixed) di kiri untuk menu utama, header atas berisi identitas pengguna/notifikasi — pola umum aplikasi enterprise/ERP.
- **Dashboard:** kombinasi kartu ringkasan (angka besar + label kecil) di bagian atas, diikuti tabel/list detail di bawahnya — bukan grafik dekoratif tanpa fungsi.
- **Tabel data** (Daftar Pengadaan, Monitoring, dsb.): baris rapi dengan garis pemisah tipis, kolom status memakai badge warna sesuai kategori di atas, dapat di-sort dan difilter.
- **Form input** (termasuk Form Input Awal Pengadaan): label di atas field, spacing konsisten, validasi jelas dengan warna merah gelap untuk error — bukan merah terang.
- **Aksen dekoratif seminimal mungkin.** Setiap elemen visual harus punya fungsi (menunjukkan status, hierarki, atau navigasi), bukan sekadar hiasan.

### Referensi Kelas Tampilan

Tampilan yang dituju setara dengan aplikasi internal korporat/BUMN kelas enterprise (mis. sistem ERP, dashboard keuangan korporat, portal internal perusahaan besar) — rapi, presisi, dan "mahal" secara visual tanpa terlihat ramai atau seperti template gratis.

---

# Deskripsi

Aplikasi ini digunakan untuk mengelola seluruh proses pengadaan barang dan jasa di UP Kendari, mulai dari perencanaan, pelaksanaan, hingga monitoring dan pelaporan. Sistem bertujuan meningkatkan efisiensi proses pengadaan, meningkatkan transparansi, mempermudah koordinasi antar personel, serta mengotomatisasi penyusunan dokumen pengadaan.

---

# Tujuan

- Mempermudah proses pengadaan barang dan jasa.
- Meningkatkan transparansi dan akuntabilitas pengadaan.
- Memusatkan seluruh data pengadaan dalam satu sistem.
- Mempermudah monitoring progres pengadaan.
- Menghasilkan dokumen pengadaan secara otomatis (UPB, RKS, HPE, dan dokumen pendukung lainnya).

---

# Prinsip Arsitektur: Data Master Modular

Seluruh data master pada aplikasi ini (Direksi Pekerjaan, Unit Tujuan, Status Progres, Daftar PIC, Jenis Dokumen, Template Dokumen, dsb.) **wajib dirancang sebagai data dinamis**, bukan hardcode di kode program. Ketentuan wajib:

- Setiap data master disimpan di tabel/koleksi tersendiri di database (bukan enum/array yang ditulis langsung di kode).
- Tersedia menu **Data Master** yang bisa diakses Administrator untuk melakukan **Create, Read, Update, Delete (CRUD)** pada setiap data master, tanpa perlu deploy ulang aplikasi.
- Penambahan/pengubahan/penghapusan item data master (mis. menambah unit baru, mengganti nama jabatan direksi, menambah pilihan status) **tidak boleh mengubah struktur skema data pengadaan yang sudah berjalan**, cukup relasi/referensi ke ID data master.
- Data master yang sudah pernah dipakai di sebuah pengadaan **tidak boleh dihapus permanen** (gunakan soft delete / status nonaktif), agar riwayat data pengadaan lama tetap utuh meski daftar master berubah.
- Struktur ini berlaku untuk semua data master yang disebutkan di seluruh dokumen ini, termasuk: Direksi Pekerjaan, Unit Tujuan, Status Progres, PIC Perencana, PIC Pelaksana, dan Template Dokumen.

---

---

# Prinsip Hak Akses: Visibilitas Data Per PIC

Setiap **PIC Perencana** dan **PIC Pelaksana** hanya dapat melihat data pengadaan yang **ditugaskan kepada dirinya sendiri** — bukan seluruh data pengadaan yang ada di sistem. Ketentuan wajib:

- Saat login, PIC Perencana hanya melihat daftar pengadaan di mana dirinya ditunjuk sebagai PIC Perencana pada pengadaan tersebut.
- Saat login, PIC Pelaksana hanya melihat daftar pengadaan di mana dirinya ditunjuk sebagai PIC Pelaksana pada pengadaan tersebut.
- Dashboard, daftar pengadaan, checklist dokumen, dan riwayat aktivitas yang ditampilkan ke seorang PIC **difilter otomatis** berdasarkan penugasan (assignment) miliknya, bukan hasil filter manual oleh pengguna.
- Seorang PIC **tidak dapat membuka, mengedit, atau melihat detail** pengadaan milik PIC lain, baik melalui menu maupun akses langsung (mis. lewat URL/ID pengadaan).
- Ketentuan ini berlaku di seluruh fitur yang menampilkan data pengadaan: Daftar Pengadaan, Perencanaan, Pelaksanaan, Monitoring, Generate Dokumen, dan Laporan.
- **Team Leader Pengadaan** dan **Administrator** dikecualikan dari pembatasan ini — keduanya tetap dapat melihat seluruh data pengadaan sebagai pengawas/pengelola sistem.
- Saat Team Leader mengganti/menunjuk ulang PIC pada suatu pengadaan (mis. PIC lama digantikan PIC baru), akses PIC lama terhadap pengadaan tersebut otomatis dicabut, dan PIC baru otomatis mendapat akses.
- **Pembuat pengadaan** selalu dapat melihat pengadaan yang ia buat, meskipun tidak ditunjuk sebagai PIC.

---

# Hak Akses (Dapat Diatur Administrator)

Fitur berbasis peran tidak lagi terkunci di kode, tetapi diatur Administrator melalui menu **Administrasi → Hak Akses** dalam bentuk tabel fitur × peran (centang). Contoh: memberi PIC Perencana hak **Buat perencanaan pengadaan** agar tidak hanya akun TL yang dapat membuat perencanaan.

### Hak yang dapat diatur

| Kelompok | Hak | Bawaan |
|----------|-----|--------|
| Pengadaan | Buat perencanaan pengadaan | TL Pengadaan |
| Pengadaan | Ubah data pengadaan | TL Pengadaan |
| Pengadaan | Arsipkan pengadaan | TL Pengadaan |
| Pengadaan | Tunjuk PIC perencana & pelaksana (menu Penunjukan PIC) | TL Pengadaan |
| Pengadaan | Setujui / tolak perencanaan | TL ICC |
| Pengadaan | Nyatakan pengadaan selesai | TL Pengadaan |
| Pengadaan | Lihat seluruh pengadaan | TL Pengadaan, TL ICC |
| Penilaian Penyedia | Kelola penilaian penyedia (menu Penilaian Penyedia) — dapat diberikan mis. ke PIC Pelaksana | (hanya Administrator) |
| Administrasi | Kelola data master (seluruh menu Data Master) | (hanya Administrator) |
| Akses Menu | Menu Perencanaan | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Pelaksanaan | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Approval | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Arsip Dokumen | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Monitoring | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Laporan (termasuk ekspor) | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Monitoring Publik (tautan di menu; halaman publik tetap terbuka) | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |

### Fleksibilitas Peran oleh Super Admin

- Super Admin (Administrator) dapat memberikan peran / hak akses approval (`Setujui / tolak perencanaan` atau `Nyatakan pengadaan selesai`) kepada **PIC Perencana** maupun **PIC Pelaksana** melalui menu **Administrasi → Hak Akses**.
- Super Admin juga dapat langsung mengubah peran pengguna menjadi **Team Leader ICC** atau **Team Leader Pengadaan** melalui menu **Administrasi → Pengguna**.
- Prinsip pemisahan tugas tetap terjaga: pengguna yang memiliki hak approval perencanaan tetap **tidak dapat menyetujui pengadaan yang ia rencanakan sendiri**.

### Akses menu

- Setiap menu adalah hak tersendiri. Mencabut hak menu **menyembunyikan menunya** dan **menutup halamannya** (akses langsung lewat URL ditolak), bukan sekadar menyembunyikan tautan.
- Kelompok menu yang seluruh isinya dicabut tidak ditampilkan.
- **Dashboard** dan **Daftar Pengadaan** selalu tersedia, karena keduanya pintu masuk ke pengadaan yang ditugaskan.
- Hak baru ditambahkan di katalog `App\Enums\Permission` (kode, label, keterangan, kelompok, peran bawaan); setelah itu otomatis muncul di layar Hak Akses dan dapat dipakai di rute sebagai `can:<kode>`.

### Ketentuan keamanan

- Nilai bawaan sama persis dengan perilaku sebelumnya, sehingga tidak ada akses yang berubah sebelum Administrator mengubah pengaturan. Tombol **Kembalikan Bawaan** tersedia.
- **Administrator selalu memiliki seluruh hak** dan kolomnya terkunci, agar sistem tidak pernah terkunci.
- **Kelola pengguna & hak akses** hanya untuk Administrator dan tidak dapat diberikan ke peran lain (mencegah peran menaikkan aksesnya sendiri).
- Peran yang diberi hak menyetujui perencanaan **tetap tidak dapat menyetujui perencanaan yang ia ajukan sendiri** (pemisahan tugas).
- Hak yang mengikuti penugasan — mengisi checklist, generate/unggah dokumen, mengajukan perencanaan — tetap mengikuti PIC yang ditunjuk, bukan peran.
- Perubahan berlaku pada permintaan berikutnya tanpa perlu login ulang.

---

# Master Data: Direksi Pekerjaan

Direksi Pekerjaan adalah pejabat yang dipilih sebagai penanggung jawab/pengarah pekerjaan saat sebuah pengadaan dibuat. Daftar ini digunakan sebagai pilihan dropdown pada form pembuatan pengadaan.

### Daftar Direksi Pekerjaan

- Asman Pemeliharaan
- Asman Business Support
- Asman Operasi
- Team Leader K3
- Asman Engineering
- Team Leader Lingkungan

> Catatan: Data ini dikelola oleh Administrator pada menu Master Data, sehingga dapat ditambah/diubah tanpa mengubah kode aplikasi.

---

# Master Data: Unit Tujuan

Daftar unit yang dapat dipilih sebagai unit tujuan pengadaan:

- PLTU Moramo
- PLTD Wua Wua
- PLTD Poasia
- PLTD Poasia Containerized
- PLTD Kolaka
- PLTD Lanipa
- PLTD Ladumpi
- PLTM Sabilambo
- PLTM Mikuasi
- PLTD Bau Bau
- PLTD Pasar Wajo
- PLTD Winning
- PLTD Raha
- PLTD Wangi Wangi (Sistem Isolated Wangi Wangi)
- PLTD Ereke (Sistem Isolated Ereke)
- PLTD Langara (Sistem Isolated Langara)
- PLTM Rongi

---

# Form Input Awal Pembuatan Pengadaan

Form ini (menu **Buat Perencanaan Pengadaan**) diisi oleh **Team Leader Pengadaan** — atau peran lain yang diberi hak lewat menu Hak Akses — saat pertama kali membuat sebuah pengadaan baru, sebelum proses perencanaan dimulai. Data pada form ini menjadi identitas utama pengadaan dan akan tampil pada dashboard, monitoring, serta laporan.

### Bagian Identitas Pengadaan

| No | Field | Tipe Input | Keterangan |
|----|-------|-----------|------------|
| 1 | Jenis No Kontrak & No Kontrak | Dropdown + Text | Format dari master Format No Kontrak (SPK, PJ, SPPL); nomor terisi otomatis berurutan dan dapat diubah |
| 2 | Nama Pengadaan | Text | Nama/judul pekerjaan pengadaan |
| 3 | Nama Mitra / Pelaksana | Text | Opsional; nama mitra/penyedia yang melaksanakan pekerjaan |
| 4 | Direksi Pekerjaan | Dropdown (single select) | Diambil dari Master Data Direksi Pekerjaan |
| 5 | Unit Tujuan | Checklist (multi select) | **Dapat lebih dari 1 unit**, minimal 1; diambil dari Master Data Unit Tujuan |
| 6 | Metode Pengadaan | Dropdown | Diambil dari master Metode Pengadaan |
| 7 | Sumber Anggaran | Dropdown | Diambil dari master Sumber Anggaran |

### Bagian Usulan Pekerjaan

| No | Field | Tipe Input | Keterangan |
|----|-------|-----------|------------|
| 1 | No. PRK | Text | Opsional (Berpasangan dengan No. COA) |
| 2 | No. COA | Text | Opsional |
| 3 | No. PR | Text (input manual) | Opsional (Berpasangan dengan No. WO) |
| 4 | No. WO | Text | Opsional |
| 5 | No. Nodis Usulan & Tgl. Nodis Usulan | Text & Date | Nomor dan tanggal nota dinas usulan (`proposal_memo_date`) |
| 6 | No. Nodis Manager & Tgl. Nodis Manager | Text & Date | Nomor dan tanggal nota dinas ke manager (`icc_memo_date`, sebelumnya Nota Dinas ICC) |
| 7 | No. Surat Penawaran & Tgl. Surat Penawaran | Text & Date | Nomor dan tanggal surat penawaran dari penyedia (`quotation_number`, `quotation_date`) |
| 8 | Nilai (Sebelum Nego) | Number (currency, Rupiah) | Nilai HPE / anggaran sebelum negosiasi |
| 9 | Nilai Setelah Nego | Number (currency, Rupiah) | Opsional; hasil kesepakatan negosiasi harga |
| 10 | Status Progres | Dropdown (single select) | Status progres tahapan pengadaan |

> **Catatan Penyempurnaan Form:**
> - Inputan nomor dan tanggal nota dinas disatukan di Edit Identitas Pengadaan.
> - Form "Nota Dinas ke Manager" yang terpisah telah dihapus seluruh inputannya, dan field Nota Dinas ICC diganti namanya menjadi **Nota Dinas ke Manager** lengkap dengan tanggal suratnya (`icc_memo_date`).
> - Ditambahkan inputan **No. Surat Penawaran** dan **Tgl. Surat Penawaran** yang terintegrasi dengan template Surat Pesanan (PO).
> - Target Penyelesaian sudah dihapus dari form. Data lama yang sudah terisi tetap tersimpan.

### Daftar Pilihan Status Progres

1. Pending
2. Batal
3. Inisiasi Laksdan
4. Penyusunan RKS
5. Kelengkapan Dokumen
6. Penawaran Harga
7. Disposisi AMS
8. Proses Validasi

> Field **Status Progres** dapat diubah kapan saja oleh PIC terkait (Perencana/Pelaksana) maupun Team Leader sesuai perkembangan pengadaan, dan setiap perubahan status tercatat pada histori aktivitas pengadaan.

### Output Form

- Data pengadaan tersimpan sebagai record baru dengan status awal (default: **Pending** atau **Inisiasi Laksdan**).
- Sistem generate Nomor Pengadaan otomatis (ID unik) sebagai referensi internal.
- Setelah form ini tersimpan, Team Leader dapat melanjutkan ke tahap **Penunjukan PIC Perencana**.

---

# Fitur Generate Dokumen

Fitur ini digunakan untuk menghasilkan dokumen-dokumen pengadaan (Nota Dinas Usulan, TOR, RAB, HPE, UPB, RKS, Berita Acara, Kontrak, dll.) secara otomatis berdasarkan data pengadaan yang sudah diinput di sistem.

### Ketentuan Pengembangan Saat Ini

- **Template dokumen resmi UP Kendari belum tersedia** dan akan diberikan menyusul untuk masing-masing jenis dokumen.
- Untuk sementara, sistem dibangun terlebih dahulu menggunakan **template dokumen standar/umum** yang lazim dipakai untuk masing-masing jenis dokumen (format profesional, layout wajar, mengikuti kaidah dokumen resmi pada umumnya), agar seluruh alur generate dokumen sudah bisa berfungsi end-to-end.
- Setiap jenis dokumen tetap harus otomatis terisi (auto-fill) dari data pengadaan yang relevan, misalnya: Nama Pengadaan, Direksi Pekerjaan, Unit Tujuan, Nomor PRK, Nilai HPE/Anggaran, tanggal, serta data checklist tahap perencanaan/pelaksanaan yang sesuai dengan jenis dokumennya.

### Wajib Modular: Template Dokumen sebagai Data Master

Agar template standar ini nantinya dapat diganti dengan template resmi **tanpa merombak sistem**, penyimpanan template wajib mengikuti aturan berikut:

- Template dokumen disimpan sebagai **data master tersendiri** (mis. tabel `document_templates`), bukan ditulis permanen di dalam kode program.
- Setiap jenis dokumen (Nota Dinas Usulan, TOR, RAB, HPE, UPB, RKS, Berita Acara, Kontrak, dst.) memiliki referensi ke satu template aktif yang dapat diganti oleh Administrator.
- Struktur template memisahkan antara **layout/format dokumen** dan **data variabel/placeholder** (mis. `{{nama_pengadaan}}`, `{{direksi_pekerjaan}}`, `{{unit_tujuan}}`, `{{nilai_hpe}}`), sehingga saat template resmi diberikan, Administrator cukup mengunggah/mengganti file template dan memetakan ulang placeholder-nya, tanpa perlu mengubah logika aplikasi.
- Sistem harus mendukung penggantian template per jenis dokumen secara independen — mengganti template RKS, misalnya, tidak boleh memengaruhi template dokumen lain yang sudah berjalan.
- Riwayat dokumen yang sudah pernah digenerate dengan template lama tetap tersimpan apa adanya (tidak berubah retroaktif saat template baru dipasang).

### Output

- Dokumen hasil generate dapat diunduh dalam format standar (mis. Word/PDF sesuai jenis dokumen).
- Dokumen tersimpan otomatis pada Arsip Dokumen pengadaan terkait.

---

# Alur Bisnis Pengadaan

Setiap pengadaan mengikuti tahapan yang telah ditentukan. Setiap tahapan memiliki PIC, status, checklist dokumen, serta histori aktivitas yang dapat dipantau.

## 0. Tahap Pembuatan Pengadaan

Team Leader Pengadaan mengisi **Form Input Awal Pembuatan Pengadaan** (lihat bagian di atas) untuk mendaftarkan pengadaan baru ke dalam sistem.

## 1. Tahap Perencanaan

### Penunjukan PIC Perencana

Sebelum proses perencanaan dimulai, **Team Leader Pengadaan** wajib menunjuk **1 orang PIC Perencana** yang akan bertanggung jawab menyusun seluruh dokumen perencanaan.

### Daftar PIC Perencana

- Himatullah
- Bastial
- Iklan Nano
- Putu Wisna

### Checklist Perencanaan

| Tahapan | Cara penyelesaian |
|---------|-------------------|
| Checklist Perencanaan | Centang |
| Nota Dinas Usulan | **Unggah saja** |
| TOR / KAK | **Unggah saja** |
| RAB (Rencana Anggaran Biaya) | **Unggah saja** — dilewati untuk format SPPL |
| Penawaran | **Unggah saja** — untuk SPPL sekaligus mencakup RAB |
| CSMS (Sertifikat) — opsional | **Unggah saja** |
| Nota Dinas ke Pengadaan | **Unggah saja** |
| HPE (Harga Perkiraan Engineer) | Generate dari template, lalu unggah hasil tanda tangan |
| UPB | Generate dari template, lalu unggah hasil tanda tangan |
| RKS (Rencana Kerja dan Syarat) | Generate dari template, lalu unggah hasil tanda tangan |
| Inisiasi SMART SCM | Centang |
| PR / RO — opsional | Centang |

- Tahapan **unggah saja** tidak menampilkan tombol generate maupun edit: dokumen disiapkan di luar sistem, diunggah (boleh beberapa berkas, PDF/JPG/PNG), lalu tahapan dapat dicentang dan lanjut ke tahap berikutnya.
- Tahapan yang memiliki dokumen tetap **wajib diunggah** sebelum dapat dicentang.
- Sifat "unggah saja" diatur per jenis dokumen di **Data Master → Jenis Dokumen** (saklar *Hanya diunggah (tanpa generate)*).

### Susunan Tahapan per Format Kontrak & Metode

Susunan tahapan dapat disesuaikan tanpa mengubah kode, di **Data Master → Item Checklist**:

- **Dilewati oleh Format Kontrak** — tahapan tidak dipakai oleh format kontrak tertentu. Contoh bawaan: format **SPPL** melewati RAB, sehingga RAB dan Penawaran disatukan menjadi Penawaran.
- **Dilewati oleh Metode Pengadaan** — contoh bawaan: Surat Pesanan melewati RKS, Inisiasi SMART SCM, PR/RO, UPB, dan HPE.
- Format kontrak baru dapat ditambah di **Data Master → Format No Kontrak**, lalu diatur tahapan mana yang dilewatinya.
- Susunan berlaku untuk pengadaan baru, dan untuk pengadaan lama saat datanya disimpan ulang (tahapan yang sudah selesai tetap tersimpan sebagai riwayat).

### Output

- Seluruh dokumen perencanaan selesai.
- Dokumen RKS, UPB, dan HPE berhasil dibuat.
- Status siap untuk pelaksanaan.
- Team Leader melakukan persetujuan dokumen.
- Team Leader menunjuk PIC Pelaksana.

---

## 2. Tahap Pelaksanaan

### Penunjukan PIC Pelaksana

Setelah dokumen perencanaan disetujui, **Team Leader Pengadaan** menunjuk **1 orang PIC Pelaksana** yang bertanggung jawab menjalankan seluruh proses pengadaan hingga selesai.

### Daftar PIC Pelaksana

- Sabrin
- Ahmad Bukhari
- Supriadi

### Checklist Pelaksanaan — format SPK & PJ

| Tahapan | Cara penyelesaian |
|---------|-------------------|
| Evaluasi Dokumen | Centang |
| Penyusunan HPS | Unggah saja |
| Proses SMART SCM | Unggah saja |
| Berita Acara | **Generate** 6 dokumen (Aanwijzing, Lampiran BAPP, Evaluasi Teknis, Evaluasi Harga, Hasil Evaluasi, Klarifikasi) lalu unggah hasil TTD |
| Purchase Order (PO) | Centang |
| Jaminan Bank | Unggah saja |
| Kontrak | **Generate** SPK, Lampiran SPK, BA Negosiasi lalu unggah hasil TTD |
| Rentang Waktu | Centang |
| Amandemen — opsional | Unggah saja |
| Masa Pemeliharaan | Unggah saja |

- **Generate dokumen hanya pada Berita Acara dan Kontrak**; tahapan lain cukup diunggah.
- **Penyusunan Kontrak dihapus** (dinonaktifkan) untuk semua format.

### Checklist Pelaksanaan — format SPPL

| Tahapan | Cara penyelesaian |
|---------|-------------------|
| Evaluasi Dokumen | Centang |
| BA Negosiasi | **Generate** Berita Acara Negosiasi (format SPPL) lalu unggah hasil TTD |
| Purchase Order (PO) | Centang |
| Rekening Pelaksana | **Isian**: nomor rekening, bank, dan nama pelaksana (pemilik rekening) |
| Surat Pesanan | **Generate** Surat Pesanan + **pilih salah satu**: Lampiran SP Barang **atau** Lampiran SP Jasa; unggah hasil TTD |
| Rentang Waktu Pelaksanaan | **Isian**: tanggal mulai + jumlah hari; **tanggal akhir dihitung otomatis** (tanggal mulai dihitung hari ke-1, mis. 1 Okt + 30 hari = 30 Okt) |
| Masa Garansi | **Isian**: jumlah bulan (mis. 2 atau 3 bulan) — menggantikan Masa Pemeliharaan |

- Tidak ada pada SPPL: Penyusunan HPS, Proses SMART SCM, Berita Acara (6 dokumen), Kontrak (SPK), Jaminan Bank, Rentang Waktu (centang biasa), Amandemen, Masa Pemeliharaan, Penyusunan Kontrak.
- **Surat Pesanan mengikuti BA Negosiasi**: template Surat Pesanan dan lampirannya otomatis memuat nilai hasil negosiasi (Nilai Setelah Nego) beserta terbilangnya, rekening pelaksana, jangka waktu dan tanggal mulai–akhir pelaksanaan, serta masa garansi.
- Tahapan berisian tidak dapat dicentang sebelum isiannya lengkap; tahapan berdokumen tidak dapat dicentang sebelum dokumennya diunggah.

### Menyusun tahapan secara modular (Data Master → Item Checklist)

Seluruh susunan di atas adalah data, bukan kode, dan dapat diubah kapan saja:

| Pengaturan per tahapan | Fungsi |
|------------------------|--------|
| Dilewati oleh Format Kontrak | Tahapan tidak dipakai oleh format tertentu (SPK, PJ, SPPL, atau format baru). |
| Dilewati oleh Metode Pengadaan | Tahapan tidak dipakai oleh metode tertentu. |
| Dokumen yang Dihasilkan | Dokumen wajib — semuanya harus diunggah. |
| Dokumen Pilihan (cukup salah satu) | Dokumen alternatif — cukup satu yang diunggah (mis. Lampiran SP Barang / Jasa). |
| Isian Tahapan | Data wajib sebelum dicentang: Rentang waktu, Masa garansi, atau Rekening pelaksana. |

- Sifat **generate** atau **unggah saja** diatur per jenis dokumen di **Data Master → Jenis Dokumen**.
- Isi template (termasuk BA Negosiasi SPPL, Surat Pesanan, Lampiran SP Barang/Jasa) diubah di **Data Master → Template Dokumen**.
- Placeholder baru untuk template: `{{nilai_setelah_nego_terbilang}}`, `{{tanggal_mulai_pelaksanaan}}`, `{{jangka_waktu_hari}}`, `{{tanggal_selesai_pelaksanaan}}`, `{{masa_garansi_bulan}}`, `{{nomor_rekening}}`, `{{nama_bank}}`, `{{nama_pemilik_rekening}}`.

### Output

- Pengadaan selesai.
- Kontrak / Surat Pesanan selesai.
- Masa pemeliharaan / masa garansi selesai.
- Arsip dokumen lengkap.

---

# Role

## Administrator (Super Admin)

- Memiliki akses penuh terhadap seluruh fitur, menu, data pengadaan, dan konfigurasi sistem.
- Mengelola data master (Direksi Pekerjaan, Unit Tujuan, Status Progres, Template Dokumen, dll.).
- Mengelola akun pengguna dan pembagian peran (User Management).
- Mengatur matriks **Hak Akses** per peran secara dinamis, termasuk memberikan hak approval (`procurement.review-planning` atau `procurement.complete`) kepada PIC Perencana atau PIC Pelaksana.

---

## Team Leader Pengadaan (`team_leader_pengadaan`)

- Mengisi form input awal dan membuat pengadaan baru (`procurement.create`).
- Mengubah dan mengarsipkan data pengadaan (`procurement.update`, `procurement.delete`).
- Menunjuk PIC Perencana dan PIC Pelaksana (`procurement.assign-pic`).
- Memantau seluruh pengadaan di sistem (`procurement.view-all`).
- Menandai dan menutup bahwa pengadaan telah selesai (`procurement.complete`).

---

## Team Leader ICC (`team_leader_icc`)

- Menyetujui atau menolak dokumen perencanaan pengadaan yang diajukan oleh PIC Perencana (`procurement.review-planning`).
- Membuka kembali penolakan perencanaan jika diperlukan revisi ulang.
- Memantau seluruh pengadaan di sistem (`procurement.view-all`).
- Mengakses antrean menu Approval, Monitoring, dan laporan.

---

## PIC Perencana (`pic_perencana`)

- Bertanggung jawab menyusun seluruh dokumen pada tahap perencanaan dan mengajukan persetujuan (submit approval).
- Secara bawaan hanya dapat melihat dan mengakses data pengadaan yang ditugaskan kepadanya (lihat **Prinsip Hak Akses: Visibilitas Data Per PIC**).
- Dapat diberikan wewenang approval atau peran TL oleh Administrator melalui menu Hak Akses / Pengguna jika ditugaskan sebagai pengelola approve.

---

## PIC Pelaksana (`pic_pelaksana`)

- Bertanggung jawab melaksanakan seluruh proses pengadaan hingga pengadaan siap diselesaikan.
- Secara bawaan hanya dapat melihat dan mengakses data pengadaan yang ditugaskan kepadanya (lihat **Prinsip Hak Akses: Visibilitas Data Per PIC**).
- Dapat diberikan wewenang penutupan pengadaan atau peran TL oleh Administrator melalui menu Hak Akses / Pengguna jika ditugaskan sebagai pengelola approve.

---

# Fitur Tambahan & Penyempurnaan Terkini

### 1. Fitur Preview Dokumen pada Checklist Perencanaan & Pelaksanaan
- Setiap dokumen yang diunggah pada tabel checklist perencanaan maupun pelaksanaan kini dilengkapi tombol **Preview** (ikon mata `Eye`) di sebelah tombol Unduh dan Hapus.
- Tombol Preview memunculkan modal dialog responsif yang menampilkan dokumen secara langsung di dalam browser (menggunakan `iframe` untuk berkas PDF dan tag `img` untuk berkas PNG/JPG), sehingga pengguna tidak perlu mengunduh file untuk memeriksa kelengkapan dokumen.

### 2. Standarisasi Logo Perusahaan pada Dokumen
- Seluruh template dokumen (Nota Dinas Usulan, TOR, UPB, RKS, SPK, BA Negosiasi, Surat Pesanan, Lampiran SP Barang/Jasa) menggunakan logo korporat resmi PLN yang bersumber dari `public/logo/sidebar-logo.png`.
- Mendukung embedding gambar base64 otomatis pada render DOMPDF/HTML untuk memastikan logo selalu tampil sempurna baik di lingkungan lokal maupun server production tanpa terhalang konfigurasi allow-url-fopen atau path asset.

### 3. Penataan Form Usulan Pekerjaan & Nota Dinas
- Urutan inputan Usulan Pekerjaan disusun simetris dan rapi:
  1. No. PRK & No. COA
  2. No. PR & No. WO
  3. No. Nodis Usulan & Tgl. Nodis Usulan (`proposal_memo_date`)
  4. No. Nodis Manager & Tgl. Nodis Manager (`icc_memo_date`)
  5. Nilai Sebelum Nego & Nilai Setelah Nego
  6. Status Progres
- Inputan terpisah "Nota Dinas ke Manager" dihapus, dan field Nota Dinas ICC digantikan menjadi "Nota Dinas ke Manager" dengan penambahan inputan tanggal resmi.

### 4. Penataan Role & Kemampuan Approval PIC sebagai TL
- Tidak ada penambahan akun dummy baru untuk Team Leader; akun pengguna tetap akun pegawai riil (PIC Perencana, PIC Pelaksana, dan Administrator).
- Akun PIC Perencana (misal: Bastial) dapat diberikan peran sebagai **Team Leader ICC** atau diberikan hak akses approval perencanaan (`procurement.review-planning`) oleh Super Admin.
- Ketika PIC bertindak sebagai perencana dan juga memiliki hak/peran TL ICC, setelah mengajukan perencanaan (**Ajukan Persetujuan**), tombol **Setujui** dan **Tolak** langsung muncul di halaman detail sehingga dapat langsung menyetujui pengadaan tersebut.
- PIC Pelaksana yang diberikan peran **Team Leader Pengadaan** (atau hak `procurement.complete`) dapat langsung menandai pengadaan selesai, menunjuk PIC, serta membuat pengadaan baru.
- Pilihan dropdown penunjukan PIC Perencana secara otomatis menyertakan akun berstatus `pic_perencana` dan `team_leader_icc`, sementara PIC Pelaksana menyertakan akun `pic_pelaksana` dan `team_leader_pengadaan`.

### 5. Penyesuaian Dokumen Template PO / Surat Pesanan & Data Master Manager Unit
- **Perbaikan Garis Kolom Tanda Tangan**: Garis kolom tabel tanda tangan pada template Surat Pesanan dan Purchase Order (PO) diperbaiki dengan border penutup utuh atas-bawah-kiri-kanan (`border: 1px solid #000; border-top: 1px solid #000; margin-top: 0; margin-bottom: 0;`), sehingga garis kolom tidak terputus atau terbuka di bagian atas.
- **Penandatangan Manager**: Pihak penandatangan di sisi kanan dokumen Surat Pesanan ditetapkan sebagai **Manager** (`PT PLN NUSANTARA POWER UP KENDARI - MANAGER`), dan nama Manager diambil secara dinamis dari **Data Master → Manager Unit** (`{{nama_manager}}`).
- **Data Master Manager Unit**: Disediakan menu **Data Master → Manager Unit** (`unit-managers`) untuk mengelola daftar nama dan jabatan Manager UP Kendari yang aktif.
- **Otomatisasi Nomor & Tanggal Nota Dinas**: Pada template Surat Pesanan, bagian `1. NOTA DINAS` kini memanggil nomor (`{{nomor_nota_dinas_manager}}`) dan tanggal nota dinas (`{{tanggal_nota_dinas_manager}}`) secara dinamis dari form Usulan Pekerjaan.
- **Inputan Nomor & Tanggal Surat Penawaran**: Ditambahkan field **No. Surat Penawaran** (`quotation_number`) dan **Tgl. Surat Penawaran** (`quotation_date`) pada Edit Identitas Pengadaan (bagian Usulan Pekerjaan), yang langsung terhubung ke bagian `2. SURAT PENAWARAN` pada template Surat Pesanan (`{{nomor_surat_penawaran}}` dan `{{tanggal_surat_penawaran}}`).

### 6. Penyederhanaan Halaman Edit Dokumen
- Bagian daftar kartu **Data yang Terpanggil** di bagian bawah editor dokumen telah dihilangkan dari antarmuka halaman edit dokumen ([`document-editor.tsx`](file:///d:/PROJECT_GROUP/Pengadaanv3/resources/js/pages/procurements/document-editor.tsx)). Layar kini lebih bersih, fokus, dan langsung menampilkan lembar kerja Editor Visual dan Pratinjau Cetak.

---

# Fitur Utama

- Dashboard
- Management Pengadaan (termasuk Form Input Awal Pengadaan)
- Penunjukan PIC
- Perencanaan Pengadaan
- Pelaksanaan Pengadaan
- Monitoring Progress (berdasarkan Status Progres)
- Generate Dokumen (UPB, RKS, HPE, dll.)
- Approval
- Laporan
- Manajemen Pengguna
- Hak Akses per peran (dapat diatur Administrator)
- Manajemen Data Master (Direksi Pekerjaan, Unit Tujuan, Status Progres, dll.)
- Penilaian Kinerja Penyedia (Vendor Assessment) dengan riwayat penyedia otomatis, integrasi denda, dan Export Excel (OpenSpout).
- Notifikasi

---

# Workflow

```text
Team Leader Mengisi Form Input Awal Pengadaan
│
├── Identitas: No Kontrak, Nama Pengadaan, Nama Mitra/Pelaksana,
│   Direksi Pekerjaan, Unit Tujuan (bisa lebih dari 1), Metode, Sumber Anggaran
└── Usulan Pekerjaan: No PRK, No Nota Dinas Usulan, No Nota Dinas ICC,
    No PR/PO (manual), No COA, No WO, Nilai Sebelum Nego,
    Nilai Setelah Nego, Status Progres
            │
            ▼
Penunjukan PIC Perencana
            │
            ▼
Tahap Perencanaan
│
├── Nota Dinas Usulan        (unggah)
├── TOR / KAK                (unggah)
├── RAB                      (unggah; dilewati SPPL)
├── Penawaran                (unggah)
├── CSMS (Sertifikat)        (unggah)
├── Nota Dinas ke Pengadaan  (unggah)
├── HPE
├── UPB
├── RKS
├── Smart SCM
└── PR / RO (Opsional)
            │
            ▼
Approval Team Leader
            │
            ▼
Penunjukan PIC Pelaksana
            │
            ▼
Tahap Pelaksanaan — SPK & PJ
│
├── Evaluasi Dokumen
├── Penyusunan HPS            (unggah)
├── Proses SMART SCM          (unggah)
├── Berita Acara              (generate 6 dokumen)
├── Purchase Order
├── Jaminan Bank              (unggah)
├── Kontrak                   (generate SPK, Lampiran SPK, BA Nego)
├── Rentang Waktu
├── Amandemen                 (unggah, opsional)
└── Masa Pemeliharaan         (unggah)

Tahap Pelaksanaan — SPPL
│
├── Evaluasi Dokumen
├── BA Negosiasi              (generate)
├── Purchase Order
├── Rekening Pelaksana        (isian: no rekening, bank, nama)
├── Surat Pesanan             (generate + pilih Lampiran SP Barang/Jasa)
├── Rentang Waktu Pelaksanaan (isian: tanggal mulai + hari → tanggal akhir otomatis)
└── Masa Garansi              (isian: bulan)
            │
            ▼
Pengadaan Selesai
```

---

# Struktur Menu

```text
Dashboard

Pengadaan
├── Buat Perencanaan Pengadaan   (hak: Buat perencanaan pengadaan)
├── Daftar Pengadaan             (selalu tersedia)
├── Penunjukan PIC               (hak: Tunjuk PIC)
├── Perencanaan                  (hak menu)
├── Pelaksanaan                  (hak menu)
├── Approval                     (hak menu)
├── Arsip Dokumen                (hak menu)
└── Penilaian Penyedia           (hak: Kelola penilaian penyedia)

Pengawasan
├── Monitoring                   (hak menu)
├── Laporan                      (hak menu)
└── Monitoring Publik            (hak menu)

Administrasi                     (hanya Administrator)
├── Pengguna
└── Hak Akses

Data Master                      (hak: Kelola data master)
├── Manager Unit
├── Direksi Pekerjaan
├── Unit Tujuan
├── Metode Pengadaan
├── Sumber Anggaran
├── Jenis Kontrak
├── Format No Kontrak
├── Status Progres
├── Item Checklist
├── Jenis Dokumen
├── Template Dokumen
├── Aspek Penilaian
└── Lembar Penilai

Pengaturan

Catatan: master "Nomor PR/RO" sudah dihapus; Nomor PR/PO kini diinput manual pada form pengadaan.
```

---

# Penilaian Kinerja Penyedia — Output Sertifikat Akumulasi

Lembar **Akumulasi** pada detail penilaian dicetak sebagai **Sertifikat "LAPORAN KINERJA SUPPLIER"** (tombol **Cetak Sertifikat**), terpisah dari lembar penilai per-fungsi yang tetap memakai formulir internal `FORMULIR PENILAIAN KINERJA PENYEDIA BARANG DAN JASA`.

Ketentuan tampilan sertifikat:

- **Tata letak berbingkai** dengan hiasan sudut biru navy dan emas (kiri-atas & kanan-bawah) serta **watermark "PLN"** abu-abu samar di latar.
- Judul **LAPORAN KINERJA SUPPLIER**, diikuti "diberikan kepada" dan nama penyedia.
- **Tanpa nomor VO/nomor formulir** pada sertifikat.
- **Narasi**: "Hasil kinerja perusahaan terhadap surat perjanjian nomor *(nomor kontrak)*, tanggal *(tanggal kontrak)* tentang *(nama pekerjaan/pengadaan)*".
- **Tabel berbobot**: kolom Indikator, **Bobot** (persentase per aspek), Level [1-5] (rata-rata level tiap aspek antar penilai), dan Nilai (= Level × Bobot), ditutup baris **Total Nilai** (jumlah seluruh Nilai berbobot). Bobot tiap aspek dikelola sebagai data master pada **Aspek Penilaian** (field Bobot %), total bobot aspek aktif sebaiknya 100%. Aspek yang belum dinilai tampil "-" dan tidak menambah total.
- **Tanggal** diambil dari **tanggal BASTP** (fallback tanggal formulir).
- Ditandatangani oleh **Tim Pengadaan** (menggantikan "Kepala Divisi Supply Chain Management").
- **Tipografi ringkas** setara ukuran Word (± 9pt body, judul 15pt) dengan gaya korporat bersih, diusahakan muat dalam satu halaman A4. Margin diatur pada `@page` sehingga bila isi melebar ke halaman kedua, teksnya tetap berada di dalam margin kertas; ornamen sudut & watermark tetap full-bleed di setiap halaman.

---

# Dashboard

Menampilkan informasi:

- Total Pengadaan
- Pengadaan Berjalan
- Pengadaan Selesai
- Pengadaan Menunggu Approval
- Pengadaan Berdasarkan PIC
- Pengadaan Berdasarkan Direksi Pekerjaan
- Pengadaan Berdasarkan Unit Tujuan
- Progress Pengadaan (berdasarkan Status Progres)
- Jadwal Pengadaan
- Statistik Pengadaan

---

# Standar Orientasi Kertas Template Dokumen (Format Potret / Portrait)

- Seluruh dokumen pengadaan dan berita acara, termasuk **Berita Acara Negosiasi (BA Negosiasi SPPL, SPK, dan Pelelangan/Tender)**, menggunakan format kertas **Potret (A4 Portrait)** secara konsisten.
- Pengaturan cetak HTML dan PDF (`@page { size: A4 portrait; margin: 20mm 15mm; }`) mengunci orientasi kertas agar tidak pernah otomatis terbalik menjadi landscape saat dicetak melalui browser maupun Dompdf.
- Tabel rincian hasil negosiasi menggunakan layout terstruktur (`table-layout: fixed; width: 100%; max-width: 100%; word-break: break-word;`) dengan proporsi persentase kolom yang pas, sehingga seluruh kolom muat dalam 1 halaman A4 potret tanpa terpotong atau melebar keluar batas halaman kertas.
- Dialog pratinjau template dokumen pada Data Master dan editor visual membatasi bingkai tampilan pada ukuran kertas A4 potret (`max-w-[210mm]`).

---

# Teknologi

Sesuai dengan `claude.md`.
