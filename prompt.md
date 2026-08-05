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
- **Identitas korporat kuat.** Gunakan warna dasar netral (putih/abu-abu terang) dipadukan satu warna aksen korporat yang tegas (mis. biru navy tua atau merah maroon — selaras nuansa PLN), digunakan konsisten untuk elemen penting (tombol utama, status aktif, highlight data).
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

### Palet Warna (arah, dapat disesuaikan dengan brand guideline PLN resmi)

- **Base:** putih / abu-abu sangat terang untuk background, abu-abu gelap untuk teks utama (bukan hitam pekat penuh, agar tidak terlalu keras).
- **Aksen Korporat:** satu warna dominan tegas (mis. biru navy `#0B2C4D`–`#1E4D78` atau merah maroon PLN) untuk header, tombol utama, dan elemen navigasi aktif.
- **Warna Status (Status Progres):** dibedakan per kategori secara konsisten dan tidak terlalu terang — misalnya abu-abu untuk Pending, merah gelap untuk Batal, kuning tua/amber untuk proses berjalan (Penyusunan RKS, Kelengkapan Dokumen, Penawaran Harga, Disposisi AMS, Proses Validasi), hijau tua untuk selesai/tervalidasi.
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

Form ini diisi oleh **Team Leader Pengadaan** saat pertama kali membuat sebuah pengadaan baru, sebelum proses perencanaan dimulai. Data pada form ini menjadi identitas utama pengadaan dan akan tampil pada dashboard, monitoring, serta laporan.

| No | Field | Tipe Input | Keterangan |
|----|-------|-----------|------------|
| 1 | Nama Pengadaan | Text | Nama/judul pekerjaan pengadaan |
| 2 | Direksi Pekerjaan | Dropdown (single select) | Diambil dari Master Data Direksi Pekerjaan |
| 3 | Unit Tujuan | Dropdown (single select) | Diambil dari Master Data Unit Tujuan |
| 4 | Nomor PR/RO | Dropdown (single select) | Menampilkan daftar nomor PR/RO yang tersedia/terintegrasi dari sistem (mis. Smart SCM); dapat dikosongkan jika belum tersedia |
| 5 | Nomor PRK (Nota Dinas Usulan) | Text | Nomor referensi nota dinas usulan |
| 6 | Nilai HPE / Anggaran | Number (currency, format Rupiah) | Estimasi nilai Harga Perkiraan Engineer / pagu anggaran |
| 7 | Status Progres | Dropdown (single select) | Lihat daftar status pada bagian di bawah |

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

- Checklist Perencanaan
- Nota Dinas Usulan
- TOR (Term of Reference)
- RAB (Rencana Anggaran Biaya)
- Penawaran
- CSMS (Opsional)
- Nota Dinas Perintah Pekerjaan
- HPE (Harga Perkiraan Engineer)
- UPB
- RKS (Rencana Kerja dan Syarat)
- Smart SCM
- PR / RO (Opsional)

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

### Checklist Pelaksanaan

- Evaluasi Dokumen
- Penyusunan HPS
- Progress Pengadaan
- Berita Acara
- Penyusunan Kontrak
- Purchase Order (PO)
- Jaminan Bank
- Kontrak
- Rentang Waktu
- Amandemen
- Masa Pemeliharaan

### Output

- Pengadaan selesai.
- Kontrak selesai.
- Masa pemeliharaan selesai.
- Arsip dokumen lengkap.

---

# Role

## Administrator

Mengelola data master (termasuk Direksi Pekerjaan, Unit Tujuan, Status Progres), pengguna, konfigurasi sistem, dan hak akses.

---

## Team Leader Pengadaan

- Mengisi form input awal dan membuat proyek pengadaan.
- Menunjuk PIC Perencana.
- Menyetujui dokumen perencanaan.
- Menunjuk PIC Pelaksana.
- Melakukan monitoring seluruh proses pengadaan.
- Menyetujui penyelesaian pengadaan.

---

## PIC Perencana

- Bertanggung jawab menyusun seluruh dokumen pada tahap perencanaan.
- Hanya dapat melihat dan mengakses data pengadaan yang ditugaskan kepadanya (lihat **Prinsip Hak Akses: Visibilitas Data Per PIC**).

---

## PIC Pelaksana

- Bertanggung jawab melaksanakan seluruh proses pengadaan hingga selesai.
- Hanya dapat melihat dan mengakses data pengadaan yang ditugaskan kepadanya (lihat **Prinsip Hak Akses: Visibilitas Data Per PIC**).

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
- Manajemen Data Master (Direksi Pekerjaan, Unit Tujuan, Status Progres)
- Notifikasi

---

# Workflow

```text
Team Leader Mengisi Form Input Awal Pengadaan
│
├── Nama Pengadaan
├── Direksi Pekerjaan
├── Unit Tujuan
├── Nomor PR/RO
├── Nomor PRK (Nota Dinas Usulan)
├── Nilai HPE/Anggaran
└── Status Progres
            │
            ▼
Penunjukan PIC Perencana
            │
            ▼
Tahap Perencanaan
│
├── Nota Dinas Usulan
├── TOR
├── RAB
├── Penawaran
├── CSMS
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
Tahap Pelaksanaan
│
├── Evaluasi Dokumen
├── Penyusunan HPS
├── Progress Pengadaan
├── Berita Acara
├── Penyusunan Kontrak
├── Purchase Order
├── Jaminan Bank
├── Kontrak
├── Amandemen
└── Masa Pemeliharaan
            │
            ▼
Pengadaan Selesai
```

---

# Struktur Menu

```text
Dashboard

Pengadaan
├── Buat Pengadaan Baru (Form Input Awal)
├── Daftar Pengadaan
├── Penunjukan PIC
├── Perencanaan
├── Pelaksanaan
├── Approval
└── Arsip Dokumen

Monitoring

Laporan

Pengguna

Data Master
├── Direksi Pekerjaan
├── Unit Tujuan
└── Status Progres

Pengaturan
```

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

# Teknologi

Sesuai dengan `claude.md`.
