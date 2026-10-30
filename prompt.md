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
- **Warna Status (Status Progres):** mengikuti kategori status. Abu-abu untuk tahap usulan (pending), merah gelap untuk Batal, kuning pisang untuk proses berjalan (Inisiasi Rendan sampai Terkontrak), dan hijau tua untuk Selesai.
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
| Akses Menu | Menu Approval | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) — isi antrean mengikuti hak persetujuan (lihat catatan di bawah) |
| Akses Menu | Menu Arsip Dokumen | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Monitoring | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Laporan (termasuk ekspor) | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |
| Akses Menu | Menu Monitoring Publik (tautan di menu; halaman publik tetap terbuka) | Semua peran (TL Pengadaan, TL ICC, PIC Perencana, PIC Pelaksana) |

> **Revisi menu Approval (antrean sesuai hak):**
> - Antrean **Perencanaan** hanya tampil bagi pemegang hak *Setujui / tolak perencanaan* (TL ICC).
> - Antrean **Pelaksanaan** hanya tampil bagi pemegang hak *Setujui pelaksanaan* (TL Pengadaan).
> - Akun yang memegang keduanya (mis. Administrator) berpindah lewat tab. Akun tanpa hak persetujuan melihat pesan kosong.
> - Parameter `queue` di URL tidak bisa dipakai untuk membuka antrean milik peran lain; permintaan itu dikembalikan ke antrean milik akun sendiri.
> - "Keputusan Terakhir" kini menjadi tab **Riwayat Keputusan** milik antrean itu sendiri (TL Pengadaan melihat keputusan pelaksanaan, bukan keputusan perencanaan).
> - Siap untuk data banyak: filter dan pencarian yang sama dengan menu Pelaksanaan dijalankan di server, paginasi 15 per halaman, dan indeks database untuk antrean (migrasi `add_approval_queue_indexes_to_procurements_table`).
> - **Test:** `ApprovalQueueTest`.

> **Revisi hak Pelaksanaan:**
> - **TL ICC** sekarang sama seperti PIC Perencana biasa di tab Pelaksanaan: **hanya bisa melihat**. Tidak bisa mencentang checklist, mengisi isian tahapan, membuat/mengedit/mengunggah/menghapus dokumen pelaksanaan.
> - Yang bekerja di Pelaksanaan: PIC Pelaksana, TL Pengadaan, dan Administrator.
> - Hak dokumen kini mengikuti **tahap** tempat dokumen itu diminta checklist:
>   - Perencanaan: PIC Perencana dan para TL.
>   - Pelaksanaan: PIC Pelaksana, TL Pengadaan, dan Administrator.
>   - Keuangan: pemegang hak proses pembayaran.
>   - Dokumen yang tidak diminta tahapan mana pun (mis. dokumen usulan) tetap mengikuti hak lama.
> - Akibatnya, PIC Perencana juga tidak lagi bisa membuat dokumen pelaksanaan.
> - **Test:** `ExecutionRightsTest`.

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

# Master Data: Mitra

Menu **Data Master → Mitra** (`master-data.partners`, hak `manage-master-data`) berisi daftar mitra (penyedia barang/jasa).
- **Kolom:** nama, kode rekanan, nama direktur, alamat, urutan, aktif. Bisa ditambah, diubah, dan dinonaktifkan (soft delete).
- **Isi awal:** migrasi `create_partners_table` mengumpulkan semua mitra yang pernah diinput:
  - calon mitra di perencanaan;
  - mitra yang tercatat di pengadaan;
  - nama penyedia di Penilaian Penyedia.

  Nama yang sama (beda huruf besar/kecil atau spasi) digabung jadi satu mitra. Detailnya diambil dari isian terbaru, dan yang kosong dilengkapi dari isian lama.
- **Bertambah otomatis:** setiap calon mitra yang disimpan dan setiap penilaian penyedia yang dibuat atau diubah nama penyedianya mencatat mitranya (`Partner::remember`).
  - Nama baru langsung ditambahkan ke master.
  - Mitra yang sudah ada hanya dilengkapi detail yang masih kosong. Data yang sudah dikoreksi di master tidak ditimpa.
- **Saran saat mengetik:**
  - Lokasinya di kolom **Nama Calon Mitra** (Perencanaan → Penawaran) dan **Penyedia Barang/Jasa** (Buat Formulir Penilaian), memakai komponen `PartnerNameInput`.
  - Sejak huruf pertama diketik, muncul daftar mitra aktif yang cocok (maksimal 8). Nama yang diawali teks ketikan tampil lebih dulu.
  - Pilih dengan klik atau panah + Enter.
  - Di calon mitra, memilih saran langsung mengisi kode rekanan, nama direktur, dan alamat; semuanya tetap bisa diubah.
  - Mitra baru tetap bisa diketik penuh.
- **Endpoint saran:** `GET /mitra/saran?search=` (`partners.suggest`), wajib login, dibatasi 120 permintaan/menit.
- Daftar nama penyedia lama di form penilaian (cache `unique_vendor_names`) digantikan oleh master Mitra.
- **Test:** `PartnerTest`.

---

# Form Input Awal Pembuatan Pengadaan

Form ini (menu **Buat Perencanaan Pengadaan**) diisi oleh **Team Leader Pengadaan** — atau peran lain yang diberi hak lewat menu Hak Akses — saat pertama kali membuat sebuah pengadaan baru, sebelum proses perencanaan dimulai. Data pada form ini menjadi identitas utama pengadaan dan akan tampil pada dashboard, monitoring, serta laporan.

### Bagian Identitas Pengadaan

| No | Field | Tipe Input | Keterangan |
|----|-------|-----------|------------|
| 1 | Jenis Kontrak | Dropdown | Format dari master Format No Kontrak (SPK, PJ, SPPL). Kategori dipilih PIC Perencana; No Kontrak diisi PIC Pelaksana di checklist Pelaksanaan (lihat poin 30) |
| 2 | Nama Pengadaan | Text | Nama/judul pekerjaan pengadaan |
| 3 | Direksi Pekerjaan | Dropdown (single select) | Diambil dari Master Data Direksi Pekerjaan |
| 4 | Unit Tujuan | Checklist (multi select) | **Dapat lebih dari 1 unit**, minimal 1; diambil dari Master Data Unit Tujuan |
| 5 | Sumber Anggaran | Dropdown | Diambil dari master Sumber Anggaran |

**Metode Pengadaan tidak lagi diisi di form.** Metode mengikuti **Format No Kontrak**, dan setiap format punya isian *Metode Pengadaan* di Data Master.

- **Pemetaan bawaan:** SPK → SPK, PJ → Tender, SPPL → Surat Pesanan.
- **Kapan metode diterapkan:** setiap kali format sebuah pengadaan dipilih atau diganti, metodenya ikut berganti (`Procurement::booted`, event `saving`). Mengubah data lain tidak mengubah metode. Format tanpa metode tidak mengubah metode pengadaan.
- **Fungsi metode tetap sama:** menentukan template RKS (Metode SPK / Tender) dan tahapan checklist yang dilewati (Surat Pesanan melewati HPE, UPB, RKS, Inisiasi SMART SCM, PR/RO).
- **Tampilan:** Metode Pengadaan tidak ditampilkan lagi di panel identitas detail, filter dan kolom daftar pengadaan, kolom laporan dan ekspor CSV, serta filter, kolom, dan isian "Dilewati Metode" di Item Checklist. Datanya tetap tersimpan (template RKS per metode, grafik dashboard per metode, dan menu Data Master Metode Pengadaan masih ada).
- **Pengecualian tahap:** semua pengecualian per metode dipindah ke **Dilewati Format Kontrak** (migrasi `move_method_exclusions_to_contract_formats`). Contohnya, SPPL kini melewati RAB, HPE, UPB, RKS, Inisiasi SMART SCM, dan PR/RO. Tabel pengecualian per metode dikosongkan agar tidak ada pengecualian tersembunyi; mekanismenya di backend masih ada untuk pemanggil lama.
- **Migrasi:** `add_procurement_method_to_contract_number_formats` hanya mengisi pengadaan yang metodenya kosong. Data lama sudah konsisten dengan pemetaan di atas.
- **Validasi:** `procurement_method_id` tidak lagi wajib. Nilainya masih diterima dari pemanggil lama, tetapi ditimpa metode format bila format punya metode.
- **Seeder:**
  - `MasterDataSeeder` mengisi metode tiap format.
  - `DemoProcurementSeeder` mengambil metode dari format, karena event model mati saat seeding.
  - `MasterDataSeeder` tidak lagi membuat ulang item checklist bawaan yang sudah dihapus pengguna.
- **Test:** `MethodFollowsFormatTest`.

### Bagian Calon Mitra

> **Revisi kode rekanan:** setiap calon mitra punya isian **Kode Rekanan** (opsional).
> - Saat mitra dipilih sebagai pelaksana (atau langsung untuk SPPL), kode rekanannya disalin ke pengadaan (`partner_vendor_code`).
> - Kode ini tercetak otomatis di SPK tabel (`{{kode_rekanan_mitra}}`) dan menjadi isian awal Kode Rekanan di setiap DO (bisa diubah per DO).
> - Migrasi: `add_vendor_code_to_partner_candidates`.
>
> **Revisi seeder demo:**
> - Dua dari enam pola kontrak demo kini **KHS** (PJ Barang dan SPK Barang). Pekerjaan yang masih berjalan dengan kontrak KHS mendapat Daftar Harga Satuan Kontrak (material MAK 8M) dan 3 DO: satu **Terbit** (dengan dokumen), satu **Di Laksdan** (bernomor), dan satu **Menunggu Persetujuan Asman**.
> - Sebagian pekerjaan non-KHS yang masih berjalan mendapat **Amandemen I Terbit** (dengan dokumen, pasal diambil dari kontrak) dan Amandemen II yang **Di Laksdan** atau **Menunggu Persetujuan TL**.
> - Semua calon mitra demo punya kode rekanan.

> **Revisi (calon mitra di checklist, wajib lengkap):**
> - Calon mitra tidak lagi tampil di bagian atas tab Perencanaan, tetapi **di dalam tahapan checklist "Penawaran"**.
> - Setiap calon wajib berisi: nama, direktur, alamat, nomor dan tanggal surat penawaran, **nilai total penawaran sebelum nego**, dan **unggahan berkas penawaran harga** (PDF/JPG/PNG, bisa diganti).
> - Tahapan Penawaran baru bisa dicentang bila minimal ada satu calon dan semua calon lengkap. Unggahan dokumen "Penawaran" yang terpisah dilepas dari tahapan ini.
> - Tahapan yang terlanjur dicentang tetapi calonnya belum lengkap tetap dihitung belum selesai, jadi perencanaan belum bisa diajukan.
> - Status otomatis "Menunggu Penawaran" mengikuti kelengkapan penawaran calon mitra.
> - **Nota Dinas ke Pengadaan** (dulu "Nota Dinas ke Manager"):
>   - Nomor dan tanggalnya kini diisi di tahapan checklist "Nota Dinas ke Pengadaan" bersama unggahan berkasnya, dan wajib sebelum tahapan dicentang.
>   - Inputannya dihapus dari form Edit Identitas dan dari panel Identitas.
>   - Data lama tetap tersimpan dan tetap dipakai dokumen.
> - **Test:** `PlanningInputsTest`.

> **Revisi (calon mitra lebih dari satu):** calon mitra tidak lagi diisi di form Ubah Data, tetapi di kartu **Calon Mitra** pada tab Perencanaan (tabel `procurement_partner_candidates`). Kartu ini diisi oleh PIC Perencana atau pemegang hak isi perencanaan.
> - **SPK/PJ:** calon mitra boleh lebih dari satu (tambah/ubah/hapus). Mendaftarkan calon belum berarti memilih pelaksana.
> - **SPPL (tunjuk langsung):** hanya boleh **satu** mitra, dan mitra itu langsung menjadi pelaksana.
> - Tahapan pelaksanaan **"Rekening Pelaksana" diganti menjadi "Pelaksana"**. Isinya: PIC Pelaksana memilih pelaksana dari daftar calon mitra, lalu mengisi nomor rekening, bank, dan pemilik rekening.
> - Data calon yang dipilih (nama, direktur, alamat, no./tgl. surat penawaran) disalin ke pengadaan (`partner_candidate_id`, `partner_name`, dst.), sehingga placeholder dokumen lama tetap berfungsi.
> - Calon yang sudah dipilih tidak bisa dihapus. Bila calon itu dikoreksi, data pengadaan ikut diperbarui.
> - Data mitra lama otomatis dipindah menjadi calon terpilih (migrasi `create_procurement_partner_candidates_table`).
> - **Test:** `PartnerCandidateTest`.
>
> Field calon mitra:

| No | Field | Tipe Input | Keterangan |
|----|-------|-----------|------------|
| 1 | Nama Calon Mitra | Text | Wajib per calon; nama mitra/penyedia (`partner_name` setelah dipilih) |
| 2 | No. Surat Penawaran | Text | Opsional; nomor surat penawaran dari penyedia (`quotation_number`) |
| 3 | Tgl. Surat Penawaran | Date | Opsional; tanggal surat penawaran (`quotation_date`) |
| 4 | Nama Direktur | Text | Opsional; nama direktur perusahaan mitra (`partner_director_name`) — dipanggil di template dengan `{{nama_direktur}}` |
| 5 | Alamat Perusahaan | Textarea | Opsional; alamat perusahaan mitra (`partner_address`) — dipanggil di template dengan `{{alamat_perusahaan}}` atau `{{alamat_mitra}}` (otomatis terpanggil di kolom KEPADA pada dokumen Surat Pesanan) |

### Bagian Usulan Pekerjaan

| No | Field | Tipe Input | Keterangan |
|----|-------|-----------|------------|
| 1 | No. PRK | Text | Opsional (Berpasangan dengan No. COA) |
| 2 | No. COA | Text | Opsional |
| 3 | No. PR | Text (input manual) | Opsional (Berpasangan dengan No. WO) |
| 4 | No. WO | Text | Opsional |
| 5 | No. Nodis Usulan & Tgl. Nodis Usulan | Text & Date | Nomor dan tanggal nota dinas usulan (`proposal_memo_date`) |
| 6 | No. Nodis Manager & Tgl. Nodis Manager | Text & Date | Nomor dan tanggal nota dinas ke manager (`icc_memo_date`, sebelumnya Nota Dinas ICC) |
| 7 | Nilai (Sebelum Nego) | Number (currency, Rupiah) | Nilai HPE / anggaran sebelum negosiasi |
| 8 | Nilai Setelah Nego | Number (currency, Rupiah) | Opsional; hasil kesepakatan negosiasi harga |

> **Catatan Penyempurnaan Form:**
> - Inputan nomor dan tanggal nota dinas disatukan di Edit Identitas Pengadaan.
> - Form "Nota Dinas ke Manager" yang terpisah telah dihapus seluruh inputannya, dan field Nota Dinas ICC diganti namanya menjadi **Nota Dinas ke Manager** lengkap dengan tanggal suratnya (`icc_memo_date`).
> - Field **Nama Mitra**, **No. Surat Penawaran**, **Tgl. Surat Penawaran**, **Nama Direktur**, dan **Alamat Perusahaan** dipindahkan ke bagian tersendiri bernama **Calon Mitra**.
> - Target Penyelesaian sudah dihapus dari form. Data lama yang sudah terisi tetap tersimpan.

### Status Progres Otomatis

**Status Progres tidak lagi diisi manual.** Sistem menetapkannya sendiri dari posisi pengadaan dan dokumennya (`App\Services\ProgressStatusResolver`). Status dihitung ulang setiap kali:
- pengadaan disimpan;
- dokumen digenerate atau dihapus;
- salinan bertanda tangan diunggah atau dihapus;
- checklist dicentang atau disinkronkan.

Status ditulis dengan query langsung, sehingga tidak memicu sinkronisasi ulang isi dokumen.

| Tahap | Status | Kapan berlaku |
|---|---|---|
| Usulan (pengusul) | Penyusunan TOR | TOR belum diunggah bertanda tangan (sudah/belum digenerate) |
| | Draft Nodis Usulan | TOR sudah diunggah, Nota Dinas Usulan belum |
| | Approval Usulan | menunggu persetujuan TL / Asman |
| | Revisi Usulan | usulan dikembalikan |
| Perencanaan SPK / PJ | Inisiasi Rendan | sudah di TL ICC, PIC Perencana belum ditunjuk |
| | Penyusunan RKS | RKS, UPB, HPE belum digenerate atau RAB belum diunggah |
| | Menunggu Penawaran | penawaran harga belum diunggah |
| | Draft Nodis ke Pengadaan | Nota Dinas ke Pengadaan belum diunggah / belum diajukan |
| Perencanaan SPPL | Inisiasi Rendan | idem |
| | Draft Nota Dinas ke Laksdan | PIC ditunjuk, dokumen rendan belum lengkap / belum diajukan |
| Persetujuan | Menunggu Approval ICC / Revisi Perencanaan | perencanaan diajukan / dikembalikan |
| Pelaksanaan SPK / PJ | Inisiasi Laksdan | sudah di TL Pengadaan, PIC Pelaksana belum ditunjuk |
| | Penyusunan HPS | Penyusunan HPS belum diunggah |
| | Proses SMART SCM | dokumen SMART SCM dan seluruh Berita Acara belum lengkap |
| | Penyusunan Kontrak | kontrak, jaminan, rentang waktu, masa pemeliharaan |
| Pelaksanaan SPPL | Inisiasi Laksdan / Penyusunan Surat Pesanan | BA Negosiasi, Surat Pesanan, dan tahapan SPPL lain |
| Persetujuan | Menunggu Approval TL Pengadaan / Revisi Pelaksanaan | pelaksanaan diajukan / dikembalikan |
| Keuangan | Terkontrak | disetujui TL Pengadaan; proses Penyerahan dan Pembayaran |
| | Selesai | pembayaran dicatat |
| Manual | Batal | dipilih TL / admin lewat **Batalkan Pengadaan** (alasan wajib). Tetap Batal sampai **Cabut Pembatalan**; kedua aksi tercatat di histori |

Aturan tambahan:
- **Format SPPL:** pengadaan berformat SPPL (atau bermetode Surat Pesanan) mengikuti jalur SPPL.
- **Tahap yang dilewati:** dokumen dari tahap yang dilewati format itu tidak ditunggu.
- **Data Master Status Progres:**
  - Setiap status punya `system_key` tetap, dan kolom **Diatur Otomatis** menjelaskan aturannya.
  - Nama, kategori, dan urutan status tetap bisa diubah di layar Data Master.
  - Status yang dinonaktifkan tidak dipakai sistem; pengadaan tetap pada status terakhirnya.
- **Status lama:** Pending, Kelengkapan Dokumen, Penawaran Harga, Disposisi AMS, dan Proses Validasi dinonaktifkan (tidak dihapus). Migrasi `automate_progress_statuses_and_merge_smart_scm_step` menghitung ulang status semua pengadaan, dan pengadaan yang Batal tetap Batal.
- **Form:** isian Status Progres dihapus dari form Buat/Ubah Pengadaan. Kartu Status Progres di detail kini hanya menampilkan status beserta alasannya, plus tombol Batalkan / Cabut Pembatalan untuk TL / admin.
- **Checklist pelaksanaan:** "Proses SMART SCM" dan "Berita Acara" digabung menjadi satu checklist, **Proses SMART SCM & Berita Acara**, berisi dokumen SMART SCM dan 6 Berita Acara.
  - Pada pengadaan yang masih berjalan, langkah gabungan dianggap selesai hanya bila kedua langkah lama sudah selesai.
  - Item "Berita Acara" lama dinonaktifkan.
- **Test:** `AutomaticProgressStatusTest`.

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

### Arsitektur Template Bawaan (Library)

- **Sumber kebenaran template = database** (`document_templates`), dikelola dari Data Master → Template Dokumen, berversi.
- **Teks template bawaan TIDAK ditulis di kode PHP** (seeder/migrasi). Teks bawaan disimpan sebagai file HTML biasa di `resources/document-templates/<grup>/<nama>.html`, dideskripsikan oleh `resources/document-templates/manifest.php` (kunci, grup, file, kode jenis dokumen, metode, format kontrak, kategori, nama).
- `App\Services\DocumentTemplateLibrary` memasang/menyegarkan template bawaan; setiap template mencatat asalnya di kolom `library_key` dan `library_checksum`.
- Aturan sinkron (`php artisan templates:sync [kunci…] [--group=…] [--force] [--dry-run]`):
  - Belum terpasang → dipasang & diaktifkan (kecuali admin sudah punya template sendiri untuk cakupan yang sama).
  - Terpasang & belum diubah admin → bila file berubah, dipasang sebagai **versi baru** (versi lama tetap ada untuk riwayat dokumen).
  - Sudah diubah admin / diganti template buatan admin → **tidak ditimpa** (kecuali `--force`, tetap sebagai versi baru).
- Daftar Template Dokumen menampilkan asal: **Bawaan**, **Bawaan · diubah**, atau **Kustom**.
- Seeder template (`DocumentTemplateSeeder`, `KontrakTemplateSeeder`, `SpplDocumentTemplateSeeder`, `PjTemplateSeeder`, dst.) hanya pembungkus tipis yang menyebut grup library; `DatabaseSeeder` memakai `DocumentTemplateLibrarySeeder` (semua grup).
- Migrasi lama yang dulu menulis teks template dari kode seeder dikosongkan (hasilnya sudah ada di database lama; instalasi baru mendapat teks terbaru dari library). Riwayatnya ada di git.
- **Mengubah teks bawaan**: edit file HTML di `resources/document-templates`, commit, lalu jalankan `php artisan templates:sync` di server. **Menambah template bawaan**: tambah file + entri di `manifest.php`, lalu `templates:sync`.

### Langkah Deploy Production

1. `git pull` (pastikan folder `resources/document-templates` ikut).
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build` (ada perubahan frontend).
4. `php artisan migrate --force` (kolom kategori barang/jasa, format kontrak pada template, asal library, langkah rekening/garansi SPK/PJ, dll.; migrasi library sekaligus memasang template bawaan yang belum ada).
5. `php artisan templates:sync --dry-run` lalu `php artisan templates:sync` — lihat laporan; template yang sudah disesuaikan admin dilewati.
6. `php artisan optimize:clear` lalu `php artisan optimize` (config/route/view cache), dan `php artisan queue:restart` bila memakai queue worker.

### Editor Template (gaya Word, tanpa HTML)

- Tambah/Ubah template membuka **halaman editor tersendiri** (`/master-data/document-templates/create` dan `/{id}/edit`), **bukan modal**. Setelah template baru disimpan, pengguna tetap berada di editornya.
- Editor berupa lembar kertas A4 (potret/lanskap) yang tampil persis seperti hasil generate (memakai stylesheet dokumen yang sama, `DocumentGenerator::documentStylesheet()`).
- Toolbar: urungkan/ulangi, gaya paragraf (Normal, Judul 1–4), ukuran huruf (pt), tebal/miring/garis bawah/coret, warna teks, hapus format, perataan (kiri/tengah/kanan/rata kiri-kanan), daftar berpoin/bernomor, indentasi, sisipkan tabel (pilih baris × kolom), atur tabel (tambah/hapus baris & kolom, tampil/sembunyikan garis, hapus tabel), sisipkan logo, blok tanda tangan, garis pemisah, pemisah halaman, dan orientasi kertas.
- Panel **Data Otomatis** di kanan: placeholder ditampilkan sebagai label (chip) yang bisa dicari dan disisipkan dengan sekali klik — pengguna tidak pernah menulis `{{...}}` atau HTML. Saat disimpan, chip dikembalikan menjadi `{{placeholder}}` sehingga format penyimpanan & proses generate tidak berubah.
- Tombol **Pratinjau** menampilkan template dengan contoh data pengadaan.

### Detail Pengadaan

- **Tab halaman detail pengadaan:** tab nama pengusul (dokumen usulan), **Perencanaan**, **Pelaksanaan**, **Penyerahan**, **Pembayaran**, dan **Riwayat Approval**.
  - Tab Dokumen dihapus: generate, preview, unduh, edit, dan unggah hasil TTD dilakukan langsung di baris checklist masing-masing.
- **Riwayat Approval** (dulu tab *Aktivitas*): tabel proses persetujuan dari usulan pengguna sampai pembayaran Keuangan, **yang terbaru di paling atas**.
  - Baris "Posisi saat ini" tampil paling atas, disusul approval terakhir, lalu mundur sampai "Pengadaan Dibuat" di paling bawah.
  - Nomor tetap mengikuti urutan kejadian, jadi approval terbaru bernomor paling besar.
  - Pembalikan urutan hanya di tampilan (`approval-history.tsx`); data dari server tidak berubah.
  - **Kolom:** No, Tahap (Usulan / Perencanaan / Pelaksanaan / Keuangan), Proses, Hasil (Dibuat / Diajukan / Disetujui / Dikembalikan / Ditunjuk / Selesai), Oleh (nama dan peran), Waktu, dan Keterangan (termasuk catatan pengembalian).
  - **Proses yang dicatat:** pengadaan dibuat, usulan diajukan, disetujui TL, disetujui Asman, atau dikembalikan, penunjukan PIC Perencana (tahap Perencanaan) dan PIC Pelaksana (tahap Pelaksanaan), perencanaan diajukan, disetujui, atau dikembalikan, diserahkan ke Keuangan, pembayaran selesai, dan pengadaan selesai.
  - **Posisi saat ini:** baris terakhir menunjukkan langkah yang sedang ditunggu, sampai pengadaan ditutup.
  - **Yang tidak ditampilkan:** perubahan data, status, checklist, dan dokumen tetap tercatat di database (`procurement_activities`), tetapi tidak ditampilkan di tabel ini.
  - **Tautan lama:** `?tab=aktivitas` otomatis membuka tab ini.
- Tombol Preview dokumen generate di checklist memakai gaya ringkas yang sama dengan tombol Preview berkas unggahan.

> **Revisi tanda tangan TOR:**
> - Di Lembar Pengesahan TOR, yang menyetujui di bagian paling bawah adalah **Manager**, namanya diambil dari Data Master → Manager lewat placeholder `{{nama_manager}}` yang sudah ada.
> - "Disusun oleh" dan "Diperiksa/Diketahui oleh" (Direksi Pekerjaan, Pengguna Barang/Jasa) dikosongkan (titik-titik) untuk diketik manual di editor dokumen.
> - Template terpasang sebagai versi baru lewat migrasi `update_tor_template_signed_by_manager`, kecuali template TOR sudah diubah sendiri oleh admin.
> - TOR yang sudah digenerate sebelumnya tidak berubah.
> - **Test:** `TorSignatureTest`.

### Output

- Dokumen hasil generate dapat diunduh dalam format standar (mis. Word/PDF sesuai jenis dokumen).
- Dokumen tersimpan otomatis pada Arsip Dokumen pengadaan terkait.

> **Revisi Arsip Dokumen (folder) & tombol berkas TTD:**
> - **Folder per jenis kontrak:** menu Arsip Dokumen menampilkan folder per format kontrak (**SPK, PJ, SPPL**, mengikuti data master Format Nomor Kontrak; format amandemen tidak dijadikan folder).
>   - Folder **Lainnya** muncul bila ada pengadaan yang jenis kontraknya belum ditentukan.
>   - Folder **Semua Dokumen** menampilkan seluruh arsip.
>   - Setiap folder menampilkan jumlah dokumen yang boleh dilihat akun itu, mengikuti filter pencarian dan jenis dokumen.
>   - Format nonaktif tetap punya folder selama masih berisi dokumen.
> - **Arsip hanya untuk melihat:** tombol **Edit** (edit teks dokumen) dihapus dari Arsip Dokumen; yang tersisa Pratinjau dan Unduh PDF. Edit dokumen tetap dari checklist di halaman detail pengadaan.
> - **Dikelompokkan per pekerjaan:**
>   - Di dalam folder jenis kontrak, setiap pekerjaan (pengadaan) tampil sebagai **folder kecil** dalam grid berisi nama, nomor, dan jumlah dokumen, sehingga tidak perlu banyak scroll.
>   - Klik folder pekerjaan membuka **halaman tersendiri** `documents/pekerjaan/{pengadaan}` (`documents.procurement`, `DocumentArchiveController::procurement`, hak `menu.documents` + boleh melihat pengadaan itu).
>     - Kiri: daftar dokumen pekerjaan itu, terbaru di atas.
>     - Kanan: **pratinjau PDF berukuran besar** untuk dokumen yang dipilih (default dokumen terbaru), dengan tombol Tab Baru dan Unduh PDF.
>     - Tautan "Buka detail pengadaan" dan tombol **Kembali ke Arsip** membawa kembali folder/pencarian/filter/halaman sebelumnya. Filter jenis dokumen ikut menyaring isi folder.
>   - Halaman arsip hanya memuat jumlah dokumen per pekerjaan (`document_count`), sehingga lebih ringan.
>   - Paginasi 30 pekerjaan per halaman. Bila halaman penuh, baris folder meregang sampai bawah layar dan paginasi menempel di bawah, jadi tidak ada ruang kosong. Pekerjaan dengan dokumen yang paling baru digenerate tampil paling depan.
>   - Pencarian juga mencocokkan nama pekerjaan.
>   - Filter jenis dokumen menyaring dokumen di dalam tiap kelompok.
>   - Prop halaman `groups` menggantikan `documents`.
> - **Pratinjau PDF:** tombol **Pratinjau PDF** membuka PDF di tab baru (`?preview=1`, inline), bukan lagi pratinjau HTML.
> - **Test:** `DocumentArchiveFolderTest`.
> - **Berkas TTD di checklist:**
>   - Tombol **Preview** di samping tombol hapus dihapus; pratinjau tetap dibuka dengan mengklik nama berkas.
>   - Tombol **Hapus** pindah ke kelompok tombol Preview PDF / Unduh PDF / Edit. Untuk satu berkas, langsung menghapus berkas itu dengan konfirmasi. Untuk beberapa berkas, membuka menu pilihan berkas atau "Hapus semua berkas".
>   - Dokumen yang hanya diunggah (tanpa template, mis. Nota Dinas Usulan) tidak punya kelompok tombol, jadi Hapus berada di samping tombol Tambah Berkas.
> - **Test:** `DocumentArchiveFolderTest`.

### Data Dokumen Selalu Terbaru (sinkron otomatis)

- Setiap data yang dipanggil template (placeholder) ditulis di dokumen sebagai **field hidup**. Saat data pengadaan berubah dari layar mana pun (form ubah pengadaan, isian checklist seperti rekening/tanggal/garansi, penunjukan PIC, status, nilai setelah nego, dll.), seluruh dokumen pengadaan itu langsung ikut diperbarui — tidak perlu "Muat Ulang dari Template".
- Dokumen yang belum pernah diedit disusun ulang penuh dari templatenya; dokumen yang sudah diedit hanya diperbarui pada field yang belum diubah manual — **ketikan manual tidak pernah ditimpa**.
- **Halaman Edit Dokumen** memeriksa data terbaru setiap ±4 detik. Perubahan data langsung tampil di editor tanpa keluar-masuk halaman (indikator "Sinkron otomatis" / "Data terbaru dimuat"); perubahan yang belum disimpan tetap utuh.
- Unduh/Pratinjau PDF dan Arsip Dokumen selalu memakai data terbaru (termasuk perubahan nama di data master).
- **Tanggal dokumen** (`{{tanggal_dokumen}}`) dan **tahun** tetap sesuai saat dokumen digenerate.

---

# Alur Bisnis Pengadaan

Setiap pengadaan mengikuti tahapan yang telah ditentukan. Setiap tahapan memiliki PIC, status, checklist dokumen, serta histori aktivitas yang dapat dipantau.

## 0. Tahap Usulan Pekerjaan (Bidang)

Pekerjaan dapat berawal dari bidang pengguna:

1. **Pengusul** (Staf/TL bidang, peran `pengusul`) membuka menu **Usulkan Pekerjaan** dan mengisi (semua wajib kecuali yang ditandai opsional):
   1. Nama Pengadaan
   2. Direksi Pekerjaan — otomatis mengikuti bidang akun pengusul, tidak dapat diganti
   3. Sumber Anggaran
   4. Unit Tujuan
   5. Nomor PRK (bisa lebih dari satu)
   6. Nomor PR (bisa lebih dari satu)
   7. Nomor COA (bisa lebih dari satu)
   8. Nomor SR — opsional (bisa lebih dari satu)
   9. Nomor WO — opsional (bisa lebih dari satu)
   10. Nomor Nota Dinas Usulan
   11. Tanggal Nota Dinas Usulan
   12. **Dokumen Usulan** — pengusul hanya mengunggah **dua dokumen**: **TOR** dan **Nota Dinas Usulan** (PDF/JPG/PNG, maks. 20 MB). TOR otomatis **digenerate** dari template saat usulan dikirim; pengusul melengkapinya lewat **Edit TOR**, mencetak, menandatangani, lalu mengunggah TOR bertanda tangan. Keduanya boleh diunggah di form atau sesudahnya di halaman usulan. **Usulan baru dapat disetujui TL/Asman setelah kedua dokumen terunggah.** Kedua dokumen ini menggantikan tahapan *TOR / KAK* dan *Nota Dinas Usulan* yang dihapus dari checklist perencanaan, dan tetap wajib terunggah sebelum perencanaan diajukan (juga untuk pengadaan yang dibuat langsung oleh TL Pengadaan).

   Nomor yang lebih dari satu diisi per baris (tombol *Tambah*) dan disimpan sebagai satu daftar dipisah koma (`{{nomor_prk}}`, `{{nomor_sr}}`, dst. di dokumen menampilkan seluruh daftar). Metode pengadaan, nilai, calon mitra, nota dinas ke manager, dan catatan diisi kemudian oleh pengadaan/perencana setelah usulan disetujui (inputan perencana akan disesuaikan lagi). Status usulan: **Menunggu Persetujuan**.

   > **Revisi (usulan wajib lengkap):** usulan **tidak masuk** ke persetujuan TL maupun Asman sebelum pengusul melengkapi semua item berikut:
   > - Data: nama pengadaan, direksi pekerjaan, sumber anggaran, unit tujuan, nomor PRK, nomor PR, nomor COA, nomor dan tanggal nota dinas usulan.
   > - Dokumen: TOR (generate lalu unggah yang sudah TTD) dan unggahan nota dinas usulan.
   > - SR dan WO tetap opsional.
   >
   > Cara kerjanya:
   > - Selama belum lengkap, usulan **tertahan di pengusul**. Statusnya "Belum Lengkap", tidak tampil di antrean dan dashboard TL/Asman, tidak dikirim notifikasi, dan tidak bisa disetujui.
   > - Halaman usulan menampilkan daftar yang masih kurang.
   > - Begitu lengkap, usulan **otomatis diteruskan** ke TL/Asman satu kali (riwayat "Usulan diajukan" dan notifikasi), dicatat di `proposal_completed_at`.
   > - Bila berkas dihapus saat menunggu persetujuan, usulan kembali tertahan.
   > - Usulan yang sudah terlanjur di antrean tetapi belum lengkap otomatis ditahan oleh migrasi `require_complete_proposals_and_planning_inputs`.
   > - Perencanaan juga tidak bisa diajukan bila data identitas di atas belum lengkap. Tab Perencanaan menampilkan peringatan "Data identitas pengadaan belum lengkap" untuk dilengkapi lewat Edit Identitas Pengadaan.
   > - **Test:** `ProposalCompletenessTest`.
2. **Persetujuan dua tingkat.** Usulan staf lebih dulu disetujui **TL atasan langsung** pengusul (status *Menunggu Persetujuan TL*), lalu **Direksi Pekerjaan / Asman** bidang (status *Menunggu Persetujuan Asman*). TL atasan diatur per akun di **Manajemen Pengguna → TL / Atasan Langsung** (`users.team_leader_id`); siapa pun yang menjadi atasan staf otomatis mendapat antrean persetujuan di menu Usulan Pekerjaan dan dashboard. Usulan dari TL sendiri, atau dari staf tanpa TL, langsung ke Asman. Jika TL atasan sekaligus Direksi Pekerjaan bidangnya (K3 KAM, Lingkungan), cukup satu kali persetujuan.
   **Direksi Pekerjaan** (Asman bidang, atau TL untuk K3 KAM dan Lingkungan, peran `direksi_pekerjaan`) menerima notifikasi setelah TL menyetujui dan melihat usulan pada menu **Usulan Pekerjaan** dan kartu dashboard. Halaman **Tinjau Usulan** menampilkan PDF TOR (dan lampiran lain bila ada) berukuran besar di kiri dan ringkasan data serta tombol **Setujui Usulan** / **Kembalikan ke Pengusul** di kanan. Mengembalikan wajib disertai catatan.
   > **Revisi (TL K3 KAM / Lingkungan = Direksi Pekerjaan):**
   > - Persetujuan TL menjadi **persetujuan akhir**, dan usulan langsung masuk perencanaan, bila TL itu Direksi Pekerjaan bidangnya **atau** bidang itu tidak memiliki akun Direksi Pekerjaan (Asman) lain (`ProcurementService::teamLeaderApprovalIsFinal()`). Dengan begitu usulan tidak pernah menunggu Asman yang tidak ada, meski data akun TL berubah (mis. perannya bukan Direksi Pekerjaan atau haknya dicabut).
   > - Usulan yang terlanjur menunggu di level Direksi Pekerjaan pada bidang tanpa akun Asman kini muncul di antrean TL pengusul dan dapat diputuskan olehnya. Di bidang yang punya Asman, TL tetap tidak memutuskan level Asman.
   > - Label status "Menunggu Persetujuan Asman" diganti **"Menunggu Persetujuan Direksi Pekerjaan"** (untuk K3/Lingkungan, penyetujunya TL).
   > - Panel keputusan memberi tahu TL bila persetujuannya langsung meneruskan usulan ke perencanaan.
   > - **Test:** `TeamLeaderApprovalTest` (K3 & Lingkungan, bidang tanpa akun Asman, usulan yang terlanjur menunggu).
3. Usulan yang **dikembalikan** (oleh TL maupun Asman) dapat diperbaiki pengusul (ubah data, tambah/hapus lampiran) lalu **Ajukan Ulang**; pengajuan ulang mulai lagi dari TL.
4. Usulan yang **disetujui Asman** langsung masuk perencanaan untuk **penunjukan PIC Perencana** oleh TL Pengadaan: muncul di Penunjukan PIC, Perencanaan, Monitoring Publik, dan TL Pengadaan/TL ICC mendapat notifikasi. Sebelum disetujui, usulan tidak tampil di perencanaan dan PIC tidak dapat ditunjuk.
5. Direksi Pekerjaan yang mengusulkan untuk bidangnya sendiri langsung berstatus disetujui. Direksi Pekerjaan tidak dapat menyetujui usulan bidang lain maupun usulannya sendiri.

Hak akses terkait (dapat diatur di menu Hak Akses): `procurement.propose` (Mengusulkan Pekerjaan), `procurement.approve-proposal` (Menyetujui Usulan), `menu.proposals` (menu Usulan Pekerjaan). Bidang setiap akun diatur pada **Manajemen Pengguna → Bidang (Direksi Pekerjaan)**, wajib untuk peran Pengusul dan Direksi Pekerjaan.

Akun bidang dibuat oleh `DivisionUserSeeder` (`php artisan db:seed --class=DivisionUserSeeder`), hanya membuat akun yang belum ada (password awal `password`, email `nama.lengkap@upkendari.go.id`). Akun Asman dibuat atas nama jabatan (`asman.operasi@`, `asman.engineering@`, `asman.pemeliharaan@`, `asman.bs@`) karena nama orangnya belum ditentukan, dan dapat diganti lewat Manajemen Pengguna. TL K3 KAM (Musriadi) dan TL Lingkungan (Sadri) berperan sebagai Direksi Pekerjaan bidangnya; Muhammad Amrullah sebagai Team Leader Pengadaan; Bastial tetap PIC Perencana.

Nomor usulan: usulan mendapat nomor sementara berawalan **USL/** (mis. `USL/2026/10/0001`) sampai pengadaan memberi nomor kontrak.

Setelah usulan disetujui, **TL Pengadaan** melengkapi data yang tidak diketahui bidang (Jenis Kontrak, nilai, calon mitra; metode pengadaan mengikuti format) lewat **Ubah Data**. Kategori dipilih PIC Perencana dan No Kontrak diisi PIC Pelaksana (poin 30). Selama Jenis Kontrak dan Kategori (untuk SPK/PJ) belum terisi, dokumen perencanaan belum bisa digenerate dan perencanaan belum bisa diajukan — tab Perencanaan menampilkan peringatan data apa yang kurang. *(Inputan lanjutan untuk perencana menunggu daftar dari pengguna.)*

Di setiap detail pengadaan ada tab bernama **pengusul / pembuat pengadaan** (di kiri tab Perencanaan) berisi kedua dokumen tersebut: pratinjau/edit draf TOR, berkas TOR bertanda tangan, dan scan Nota Dinas Usulan.

## 0b. Tahap Pembuatan Pengadaan

Team Leader Pengadaan mengisi **Form Input Awal Pembuatan Pengadaan** (lihat bagian di atas) untuk mendaftarkan pengadaan baru ke dalam sistem secara langsung (tanpa tahap usulan).

## 1. Tahap Perencanaan

### Penunjukan PIC Perencana

Sebelum proses perencanaan dimulai, **Team Leader Pengadaan** wajib menunjuk **1 orang PIC Perencana** yang akan bertanggung jawab menyusun seluruh dokumen perencanaan.

**Beban kerja PIC saat penunjukan.** Di setiap pilihan PIC (menu Penunjukan PIC, kartu Penunjukan PIC di detail pengadaan, dan form Buat Pengadaan) ditampilkan beban kerja kandidat agar penunjuk bisa membagi tugas secara merata:

- PIC Perencana: jumlah **perencanaan berjalan** (sebagai PIC Perencana, belum disetujui) dan **pengadaan aktif** total.
- PIC Pelaksana: jumlah **pelaksanaan berjalan** (sebagai PIC Pelaksana, perencanaan sudah disetujui) dan **pengadaan aktif** total.
- Pengadaan aktif = belum selesai dan tidak batal; satu pengadaan dengan PIC yang sama di dua peran dihitung sekali.
- Warna lencana: hijau (< 5 aktif), kuning (5–7), merah (≥ 8).

### Daftar PIC Perencana

- Himatullah
- Bastial
- Iklan Nano
- Putu Wisna

### Checklist Perencanaan

| Tahapan | Cara penyelesaian |
|---------|-------------------|
| Evaluasi Harga (dulu RAB) | Generate dari Rincian Item (harga per calon mitra), lalu unggah hasil tanda tangan — dilewati untuk format SPPL (lihat poin 19) |
| Penawaran | **Isian** calon mitra beserta penawarannya — untuk SPPL satu mitra tunjuk langsung, sekaligus mencakup Evaluasi Harga |
| CSMS (Sertifikat) — opsional | **Unggah saja** |
| Nota Dinas ke Pengadaan | **Unggah saja** |
| HPE (Harga Perkiraan Engineer) | Generate dari template, lalu unggah hasil tanda tangan |
| UPB | Generate dari template, lalu unggah hasil tanda tangan |
| RKS (Rencana Kerja dan Syarat) | Generate dari template, lalu unggah hasil tanda tangan |
| Inisiasi SMART SCM | Centang |
| PR / RO — opsional | Centang |

- Tahapan **unggah saja** tidak menampilkan tombol generate maupun edit: dokumen disiapkan di luar sistem, diunggah (boleh beberapa berkas, PDF/JPG/PNG), lalu tahapan dapat dicentang dan lanjut ke tahap berikutnya.
- Tahapan wajib yang memiliki dokumen tetap **wajib diunggah** sebelum dapat dicentang.
- Tahapan **Checklist Perencanaan**, **Nota Dinas Usulan**, dan **TOR / KAK** (perencanaan) serta **Evaluasi Dokumen** (pelaksanaan) dinonaktifkan; TOR dan Nota Dinas Usulan kini diunggah di tab dokumen pengguna. Tahapan tersebut masih ada di Data Master dan dapat diaktifkan kembali.
- Tahapan **opsional** (mis. CSMS, Amandemen) dapat langsung dicentang tanpa dokumen; dokumennya tetap bisa diunggah bila pekerjaan memerlukannya. Sifat opsional diatur di **Data Master → Item Checklist**.
- Sifat "unggah saja" diatur per jenis dokumen di **Data Master → Jenis Dokumen** (saklar *Hanya diunggah (tanpa generate)*).

### Susunan Tahapan per Format Kontrak & Metode

Susunan tahapan dapat disesuaikan tanpa mengubah kode, di **Data Master → Item Checklist**:

- **Dilewati oleh Format Kontrak** — tahapan tidak dipakai oleh format kontrak tertentu. Contoh bawaan: format **SPPL** melewati RAB, sehingga RAB dan Penawaran disatukan menjadi Penawaran.
- **Dilewati oleh Format Kontrak** — contoh bawaan: SPPL (metode Surat Pesanan) melewati RAB, RKS, Inisiasi SMART SCM, PR/RO, UPB, dan HPE. *(Isian "Dilewati oleh Metode Pengadaan" sudah dihapus; metode mengikuti format.)*
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

> **Revisi SPK tabel + Pasal:**
> - **SPK (format SPK)** kini berbentuk tabel seperti contoh SPK UP Kendari (template `kontrak/spk.html`, khusus format SPK):
>   - Nomor, tanggal, dan judul.
>   - Dokumen terkait: Permintaan Penawaran, Surat Penawaran, BA Klarifikasi/Negosiasi.
>   - Sumber dana, Kepada (mitra + alamat), kode rekanan, dan tempat penyerahan.
>   - Tabel barang/pekerjaan (jumlah, satuan, harga satuan, jumlah harga, batas waktu), jumlah/PPN/total/terbilang dari nilai setelah nego.
>   - Tanda tangan Direktur mitra dan Manager.
>   - Rincian item dan data yang belum ada di sistem (nomor PP, BA Klarifikasi, kode rekanan) diisi di editor dokumen.
> - **Pasal-pasal SPK** yang lama dipindah menjadi dokumen baru **"SPK - Syarat & Ketentuan (Pasal)"** (kode `spk-pasal`, template SPK Barang/Jasa sesuai kategori), tetap di tahapan checklist **Kontrak** tepat di bawah SPK.
> - Jenis dokumen kini bisa dibatasi untuk satu format kontrak (Data Master → Jenis Dokumen → "Hanya untuk Format Kontrak"). Dokumen Pasal hanya diminta untuk format SPK; PJ tetap satu dokumen Surat Perjanjian.
> - Kebutuhan Penyerahan/Pembayaran untuk SPK kini dibaca dari dokumen Pasal.
> - Dokumen SPK lama yang berisi pasal otomatis dipindah menjadi dokumen Pasal (migrasi `split_spk_into_table_and_articles`), sehingga tidak ada dokumen yang hilang; SPK tabelnya tinggal digenerate.
> - **Test:** `SpkContractDocumentsTest`.

| Tahapan | Cara penyelesaian |
|---------|-------------------|
| Penyusunan HPS | Unggah saja |
| Proses SMART SCM | Unggah saja |
| Berita Acara | **Generate** 6 dokumen (Aanwijzing, Lampiran BAPP, Evaluasi Teknis, Evaluasi Harga, Hasil Evaluasi, Klarifikasi) lalu unggah hasil TTD |
| Surat Pesanan | Centang |
| Jaminan Bank | Unggah saja |
| Kontrak | **Format SPK:** dua dokumen, masing-masing digenerate lalu diunggah hasil TTD-nya: (1) **SPK (Surat Perintah Kerja)** berbentuk tabel; (2) **SPK - Syarat & Ketentuan (Pasal)** berisi pasal-pasal (dulu isi SPK). **Format PJ:** satu dokumen Surat Perjanjian (sudah berisi pasal). Lampiran SPK dan BA Negosiasi tidak diminta |
| Rentang Waktu | Centang |
| Amandemen — opsional | Unggah saja |
| Masa Pemeliharaan | Unggah saja |

- **Generate dokumen hanya pada Berita Acara dan Kontrak**; tahapan lain cukup diunggah.
- **Penyusunan Kontrak dihapus** (dinonaktifkan) untuk semua format.

### Kategori Kontrak (SPK & PJ) dari Data Master Jenis Kontrak

- Untuk SPK/PJ, **Kategori** diambil dari **Data Master → Jenis Kontrak** (bawaan: KHS, Lumsum, Barang, Jasa; dapat ditambah/diubah). Kategori (Barang, Jasa, KHS, Barang dan Jasa) dipilih PIC Perencana pada panel identitas di detail pengadaan (poin 30), bukan lagi di form Buat/Ubah Pengadaan. Format lain (mis. SPPL) tidak meminta kategori.
- Setiap Jenis Kontrak memiliki **Format Dokumen** (Umum / Barang / Jasa) yang menentukan template SPK/PJ yang digenerate. Kategori Barang/Jasa pengadaan (`work_category`) tidak lagi dipilih manual, melainkan otomatis mengikuti Jenis Kontrak yang dipilih.
- Kategori tampil sebagai **Kategori Kontrak** pada panel identitas dan dapat diubah PIC Perencana sebelum perencanaan disetujui; mengubahnya ikut memperbarui format dokumen.
- Format mana yang meminta kategori diatur di **Data Master → Format No Kontrak** (sakelar "Pilih Barang / Jasa").
- Jenis kontrak tersedia sebagai placeholder `{{jenis_kontrak}}`, format dokumennya sebagai `{{kategori_pekerjaan}}`.
- **Template Dokumen** dapat dibatasi per **Metode**, per **Format No Kontrak** (SPK/PJ/SPPL), dan per **Kategori** (Barang/Jasa). Saat generate, template paling spesifik yang cocok dipakai (metode → format → kategori → versi terbaru); template tanpa batasan menjadi cadangan. Mengaktifkan template satu kategori/format tidak menonaktifkan template kategori/format lain.
- Template terpasang:
  - **SPK Jasa**: format perjanjian 20 pasal (lingkup pekerjaan).
  - **SPK Barang**: layout yang sama, pasal disesuaikan untuk barang (Jenis dan Jumlah Barang, Spesifikasi dan Mutu Barang, penyerahan di Gudang, Bon Penerimaan Barang, garansi barang). Redaksi masih standar — dapat disempurnakan di Template Dokumen.
  - **HPE PJ Barang**: "Harga Perkiraan Enjiniring" — kop logo + PT PLN Nusantara Power UP Kendari, Nomor `{{nomor_nota_dinas_usulan}}-HPE(A)`, Dasar Usulan, tabel No | Stock Code | Nama Barang dan Spesifikasi | Qty | Stn | Harga HPE (Rp.) | Keterangan, tanda tangan {{direksi_pekerjaan}} & TL Inventori Kontrol & Gudang, disahkan Manager UP Kendari.
  - **RKS PJ Jasa (Penunjukan Langsung)**: cover berbingkai (logo, PT PLN NUSANTARA POWER UP KENDARI, judul RKS, Nomor `{{nomor_rks}}`, Tanggal, "UNTUK {{nama_pengadaan}}", UP KENDARI + tahun), Daftar Isi, BAB I Umum (Dasar Hukum, Pengertian & Istilah, Undangan), BAB II Instruksi kepada Peserta (A–M, termasuk Syarat-Syarat Perjanjian 1–19), BAB III Kepatuhan Hukum & Anti Penyuapan, BAB IV Penutup + tanda tangan (TL Inventori Kontrol & Gudang, Officer Inventori Kontrol = {{pic_perencana}}, Manager UP Kendari, {{direksi_pekerjaan}}), Lampiran 1–13 (spesifikasi, contoh surat penawaran, rincian harga, surat pernyataan, pakta integritas, referensi pengalaman, ketentuan blacklist, surat minat, kesanggupan TOR, K3L, TOR, cara VICON). ±41 halaman.
  - **UPB PJ (Usulan Pengadaan Barang / Jasa)** — berlaku untuk PJ Barang maupun Jasa: kop logo + PT PLN Nusantara Power UP Kendari, judul bergaris bawah, Nomor `{{nomor_nota_dinas_usulan}}`, Sumber Dana `{{sumber_anggaran}}-{{tahun}}`, Peruntukan {{nama_pengadaan}}, Dasar Usulan "NOTA DINAS {{nomor_nota_dinas_icc}}", tabel No | Stock Code | Nama Barang dan Spesifikasi | No. Requisisi ({{nomor_pr_po}}) | No. WO ({{nomor_wo}}) | WO Description | Qty | Satuan | Tgl Dibutuhkan ({{target_penyelesaian}}) | Keterangan, tanda tangan TL Inventori Kontrol & Gudang, Officer Inventori Kontrol ({{pic_perencana}}), disetujui {{direksi_pekerjaan}}. 1 halaman A4 potret.
  - `{{nomor_rks}}` disusun otomatis dari No Kontrak: `KDD017.PJ/612/UPKD/2026` → `017.RKS-PJ/612/UPKD/2026`.
  - Klausul khusus paket (tahapan pembayaran, level CSMS, detail laporan) ditulis sebagai isian titik-titik/umum untuk disesuaikan di editor dokumen.
  - HPE PJ Jasa dan format PJ lainnya menyusul (sementara memakai template HPE umum).
- Urutan prioritas pemilihan template: **format kontrak → kategori → metode → versi terbaru**.
- **Dokumen yang belum pernah diedit** otomatis berpindah ke template yang paling cocok saat ini (mis. setelah template PJ/kategori baru dipasang atau kategori pengadaan diubah). Dokumen yang sudah diedit tetap memakai template saat dibuat; gunakan "Muat Ulang dari Template" bila ingin pindah.
- **HPE PJ** sementara berlaku untuk Barang maupun Jasa; template HPE khusus Jasa yang ditambahkan nanti otomatis lebih diutamakan.

### Performa Dokumen Panjang

- **Editor dokumen**: polling tiap ±4 detik hanya membawa sidik versi (`liveVersion`, 40 karakter); isi dokumen diunduh ulang hanya bila sidiknya berubah.
- **Penyegaran di server** di-cache per dokumen (sidik data pengadaan + versi template + versi dokumen): dokumen tidak disusun ulang selama tidak ada yang berubah.
- **Render browser**: bab, lampiran, dan cover yang berada di luar layar tidak di-layout/paint sampai digulir (`content-visibility: auto`), di editor dokumen maupun editor template.
- **Pratinjau PDF** draf disimpan di disk per dokumen (berdasarkan sidik isi); pratinjau ulang tanpa perubahan atau versi tersimpan tidak dirender ulang. Unduhan PDF memakai cache yang sudah ada.
- **Daftar Template Dokumen** tidak lagi mengirim isi seluruh template; isi diambil saat tombol Pratinjau diklik.
- Penghitung kata/karakter editor dihitung dengan `useDeferredValue` sehingga tidak memperlambat pengetikan.
- Tanggal terbit (`{{tanggal_dokumen}}`, `{{tahun}}`) tetap tanggal generate, juga saat dokumen yang belum diedit disusun ulang mengikuti data terbaru.

### Format Dokumen SPK (Surat Perintah Kerja)

SPK disusun sebagai perjanjian lengkap mengikuti format kontrak UP Kendari (±9–10 halaman A4 potret, font Arial):

- **Kepala**: logo PLN, judul SURAT PERINTAH KERJA, *Nomor : {{nomor_pengadaan}}*, "PT PLN NUSANTARA POWER / UP KENDARI / DAN / {{nama_mitra}} / Tentang / {{nama_pengadaan}}".
- **Para pihak**: (1) PT PLN Nusantara Power diwakili {{nama_manager}} selaku MANAGER PT PLN NP UP Kendari (nomor & tanggal SK Direksi diisi di editor), alamat Jl. Chairil Anwar No.2 Kendari — PIHAK KESATU; (2) {{nama_mitra}} berkantor di {{alamat_perusahaan}} diwakili {{nama_direktur}} selaku DIREKTUR — PIHAK KEDUA.
- **Berdasarkan** (tabel No | Uraian | Nomor Dokumen | Tanggal Dokumen): Permintaan Penawaran Harga, Surat Penawaran Harga ({{nomor_surat_penawaran}}, {{tanggal_surat_penawaran}}), Berita Acara Klarifikasi & Negosiasi.
- **Pasal 1–20**: Lingkup Pekerjaan (tabel No | Deskripsi | Volume | Satuan), Spesifikasi dan Mutu, Harga ({{nilai_setelah_nego}} + terbilang), Waktu dan Tempat Penyerahan ({{jangka_waktu_hari}} hari kalender), Cara Pembayaran (lampiran a–l + rekening {{nama_bank}}/{{nomor_rekening}}/{{nama_pemilik_rekening}}), Penyerahan Pekerjaan, Sanksi, Jaminan Produk, Garansi ({{masa_garansi_bulan}} bulan), Pembebasan Tuntutan, Pemenuhan Sistem Manajemen, Kepemilikan, Pemutusan Perjanjian, Force Majeure, Pajak dan Pungutan, Tuntutan Tambahan Biaya, Perselisihan Pendapat, Kepatuhan Hukum & Larangan KKN, Dokumen-Dokumen, Perubahan-Perubahan.
- **Penutup & tanda tangan**: dibuat di Kendari; PIHAK KEDUA ({{nama_mitra}}, DIREKTUR, {{nama_direktur}}) dan PIHAK KESATU (PT PLN NP UP KENDARI, MANAGER, {{nama_manager}}).
- **Footer setiap halaman (PDF)**: nomor SPK di kiri, paraf "PIHAK KESATU : ……" / "PIHAK KEDUA : ……" di kanan, nomor halaman di tengah.
- Data yang belum ada di sistem (nomor SK Direksi, nomor RFQ, nomor BA Klarifikasi, uraian lingkup) tampil sebagai titik-titik untuk dilengkapi di editor dokumen.

### Format Dokumen Surat Perjanjian (PJ)

- **Pemakaian:** pada format kontrak **PJ**, langkah checklist **Kontrak** menghasilkan **Surat Perjanjian**, bukan SPK. Format SPK tetap menghasilkan Surat Perintah Kerja.
- **Template:** `pj.surat-perjanjian`, file `resources/document-templates/pj/surat-perjanjian.html`.
  - Melayani jenis dokumen `spk` yang dibatasi format PJ, sehingga pengadaan PJ otomatis memakainya.
  - Nama dokumen dan judulnya tampil sebagai **"Surat Perjanjian (PJ)"** (lihat `DocumentType::nameFor()`).
- **Kepala:** logo PLN, lalu "SURAT PERJANJIAN / tentang / {{nama_pengadaan}} / UNTUK / PT PLN NUSANTARA POWER / UNIT PEMBANGKITAN KENDARI / NOMOR : {{nomor_pengadaan}}".
- **Pembuka:** "Pada hari ini … tanggal {{tanggal_dokumen}} di Kendari".
- **Para pihak:**
  - **PIHAK KESATU:** PT PLN Nusantara Power (data akta), diwakili {{nama_manager}} selaku MANAGER.
  - **PIHAK KEDUA:** {{nama_mitra}} berkantor di {{alamat_perusahaan}}, diwakili {{nama_direktur}} selaku DIREKTUR.
- **Pertimbangan:** undangan, RKS {{nomor_rks}}, Surat Penawaran {{nomor_surat_penawaran}}/{{tanggal_surat_penawaran}}, BA Penjelasan, BA Negosiasi {{nomor_ba_nego}}, BA Hasil, dan Surat Penunjukan.
- **Pasal 1–22** mengikuti narasi standar:
  - Definisi, Lingkup, Spesifikasi & Mutu.
  - Harga: {{nilai_setelah_nego}} beserta terbilangnya.
  - Waktu & Tempat Penyerahan: {{jangka_waktu_hari}} hari kalender, paling lambat {{tanggal_selesai_pelaksanaan}}, dengan dokumen penyerahan a–g.
  - Jaminan Produk, Jaminan Pelaksanaan 5%.
  - Masa Garansi: {{masa_garansi_bulan}} bulan.
  - Pemenuhan Sistem Manajemen.
  - Cara Pembayaran digital, dengan rekening {{nama_bank}}/{{nomor_rekening}}/{{nama_pemilik_rekening}}.
  - Sanksi Keterlambatan, Pembebasan Tuntutan, Pemutusan, Force Majeure, Penyelesaian Perselisihan (BANI), Pengalihan, Pajak, Tuntutan Biaya, Penggantian Barang, Anti Penyuapan, Perubahan, Administrasi & Surat Menyurat.
- **Penutup:** ditandatangani di Kendari oleh PIHAK KEDUA (DIREKTUR) dan PIHAK KESATU (MANAGER). Footer PDF sama dengan SPK.
- **Data yang belum ada di sistem** tampil sebagai titik-titik untuk dilengkapi di editor: nomor SK Direksi, nomor undangan/BA, nilai dan nomor bank garansi, telepon/faks.
- **Migration `2026_10_15_080010_add_surat_perjanjian_template_for_pj`:**
  - Memasang template ini di database yang sudah berjalan.
  - Mengganti judul dokumen SPK pengadaan PJ yang belum ditandatangani menjadi "Surat Perjanjian (PJ) - …".
  - Dokumen yang belum diedit otomatis mengikuti template baru.

### Checklist Pelaksanaan — format SPPL

| Tahapan | Cara penyelesaian |
|---------|-------------------|
| BA Negosiasi | **Generate** Berita Acara Negosiasi (format SPPL, Landscape) lalu unggah hasil TTD |
| Surat Pesanan | **Isian**: nomor kontrak (di Identitas Pengadaan); **Generate** Surat Pesanan lalu unggah hasil TTD |
| Pelaksana | **Isian**: mitra tunjuk langsung (satu-satunya calon mitra, otomatis terpilih), lalu nomor rekening, bank, dan pemilik rekening |
| Lampiran Surat Pesanan | Generate **Lampiran SP Barang dan Jasa** (satu dokumen untuk barang maupun jasa); unggah hasil TTD (nomor kontrak tidak lagi ditagih di sini) |
| Rentang Waktu Pelaksanaan | **Isian**: tanggal mulai + jumlah hari; **tanggal akhir dihitung otomatis** (tanggal mulai dihitung hari ke-1, mis. 1 Okt + 30 hari = 30 Okt) |
| Masa Garansi | **Isian**: jumlah bulan (mis. 2 atau 3 bulan) — menggantikan Masa Pemeliharaan |

- Tidak ada pada SPPL: Penyusunan HPS, Proses SMART SCM, Berita Acara (6 dokumen), Kontrak (SPK), Jaminan Bank, Rentang Waktu (centang biasa), Amandemen, Masa Pemeliharaan, Penyusunan Kontrak.
- **Surat Pesanan mengikuti BA Negosiasi**: template Surat Pesanan dan lampirannya otomatis memuat nilai hasil negosiasi (Nilai Setelah Nego) beserta terbilangnya, rekening pelaksana, jangka waktu dan tanggal mulai–akhir pelaksanaan, serta masa garansi.
- **Kop BA Negosiasi SPPL dan Surat Pesanan** (bukan lampiran): logo di kiri, dan di kanan header tercantum **PT. PLN Nusantara Power UP Kendari** beserta alamat *Jl. Chairil Anwar No.2, Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara 93117*. Lampiran SP Barang dan Jasa tetap memakai logo di tengah tanpa alamat.
- **Format BA Negosiasi SPPL (Harga Pembayaran Langsung)**: kop logo PLN Nusantara Power + UP KENDARI, di kanan nama & alamat perusahaan lalu Tanggal & Perusahaan; tabel NO | NAMA BARANG/JASA | VOLUME | SATUAN | HARGA SEBELUM NEGO (Harga Satuan, Jumlah Harga) | HARGA SETELAH NEGO (Harga Satuan, Jumlah Harga); baris **TOTAL HARGA**; **tanpa baris DPP 11/12, PPN 12%, dan Jumlah Total** — diganti keterangan *"\*Harga belum termasuk PPN"* di bawah tabel; tanda tangan {{nama_mitra}} / DIREKTUR dan PLN NP UP KENDARI / TL PELAKSANA PENGADAAN. Baris kelompok (mis. "A  Fabrikasi Material") dan baris kosong boleh ditambahkan; baris tanpa volume dan harga tidak dihitung sebagai item saat sinkron ke Surat Pesanan.
- **Lampiran SP Barang dan Jasa (Syarat Umum, 2 halaman)** — satu jenis dokumen saja (kode `lampiran-sp`), tidak lagi dipisah Barang dan Jasa; memakai narasi formulir UP Kendari: A. Umum, B. Penerimaan Barang / Pekerjaan, C. Pemeriksaan Barang / Pekerjaan, D. Pembayaran (daftar lampiran tagihan a–o dan rekening Bank/Nomor Rekening/Atas Nama dari isian Rekening Pelaksana), E. Sanksi Keterlambatan, F. Kepatuhan terhadap Peraturan dan Kebijakan Anti Penyuapan, G. Lain-lain. Narasinya berlaku untuk barang maupun jasa ("barang / pekerjaan"). Dokumen yang sebelumnya dibuat sebagai Lampiran SP Barang/Jasa otomatis dipindah ke jenis gabungan ini. Isinya mengalir tanpa paksaan pindah halaman, sehingga halaman 1 terisi penuh (A–D) dan sisanya (E–G) di halaman 2.
- Tahapan berisian tidak dapat dicentang sebelum isiannya lengkap; tahapan berdokumen tidak dapat dicentang sebelum dokumennya diunggah.

### Menyusun tahapan secara modular (Data Master → Item Checklist)

Seluruh susunan di atas adalah data, bukan kode, dan dapat diubah kapan saja:

| Pengaturan per tahapan | Fungsi |
|------------------------|--------|
| Dilewati oleh Format Kontrak | Tahapan tidak dipakai oleh format tertentu (SPK, PJ, SPPL, atau format baru). |
| Dilewati oleh Format Kontrak | Tahapan tidak dipakai oleh format kontrak tertentu (metode mengikuti format). |
| Dokumen yang Dihasilkan | Dokumen wajib — semuanya harus diunggah. |
| Dokumen Pilihan (cukup salah satu) | Dokumen alternatif — cukup satu yang diunggah (mis. Jaminan Bank atau Surat Garansi). |
| Isian Tahapan | Data wajib sebelum dicentang: Rentang waktu, Masa garansi, atau Rekening pelaksana. |

- Sifat **generate** atau **unggah saja** diatur per jenis dokumen di **Data Master → Jenis Dokumen**.
- Isi template (termasuk BA Negosiasi SPPL, Surat Pesanan, Lampiran SP Barang dan Jasa) diubah di **Data Master → Template Dokumen**.
- Placeholder baru untuk template: `{{nilai_setelah_nego_terbilang}}`, `{{tanggal_mulai_pelaksanaan}}`, `{{jangka_waktu_hari}}`, `{{tanggal_selesai_pelaksanaan}}`, `{{masa_garansi_bulan}}`, `{{nomor_rekening}}`, `{{nama_bank}}`, `{{nama_pemilik_rekening}}`.

### Output

- Kontrak / Surat Pesanan selesai.
- Masa pemeliharaan / masa garansi selesai.
- Arsip dokumen lengkap.
- Setelah seluruh tahapan **wajib** pelaksanaan selesai, PIC Pelaksana (atau TL) menekan **Serahkan ke Keuangan**. Sejak itu checklist pelaksanaan terkunci.

## 3. Tahap Keuangan

> **Revisi terbaru (27 Okt 2026):** Penyerahan dikerjakan PIC Pelaksana dan disetujui TL Pengadaan. Pembayaran diajukan staf Keuangan, lalu disetujui TL Keuangan dan Asman Business Support; persetujuan Asman mencatat lunas dan menutup pengadaan. Tombol "Pembayaran Lunas & Tutup Pengadaan" di bawah sudah diganti. Rincian lengkap ada di bagian *Alur Penyerahan & Pembayaran dengan Persetujuan*.

> **Revisi (Penyerahan & Pembayaran = unggah dokumen):**
> - Setiap item checklist Penyerahan dan Pembayaran kini **wajib unggah berkas** (PDF/JPG/PNG, bisa beberapa sekaligus), bukan sekadar centang.
> - Item baru bisa dicentang setelah ada berkas.
> - Menghapus semua berkas otomatis membatalkan centangnya.
> - Item yang terlanjur dicentang tanpa berkas tetap dihitung belum selesai, sehingga pembayaran belum bisa diselesaikan.
> - **Tampilan seragam dengan tab Perencanaan/Pelaksanaan:** berkas Penyerahan/Pembayaran (`ChecklistFileList`) kini tampil seperti dokumen di checklist perencanaan.
>   - Kotak berlabel "Dokumen {nama item}" dengan status **Terunggah · n berkas** / **Wajib diunggah**.
>   - Klik nama berkas membuka **pratinjau dalam jendela** (Tab Baru / Unduh / Tutup), sama dengan berkas TTD.
>   - Tombol **Unggah Dokumen / Tambah Berkas** dan **Hapus** berada dalam satu grup. Untuk beberapa berkas, Hapus membuka menu pilihan berkas.
>   - Daftar berkas dan jendela pratinjau memakai komponen bersama `UploadedFileRows` dan `UploadPreviewDialog` (di `signed-upload-list.tsx`).
>   - Tab user (dokumen usulan) dan dokumen generate di Penyerahan/Pembayaran sudah memakai `ChecklistDocumentActions` yang sama dengan perencanaan (Generate, Preview PDF, Unduh PDF, Edit, Hapus).
> - Item opsional ("apabila ada") tetap opsional.
> - Berkas disimpan per item (tabel `procurement_checklist_files`, migrasi `create_procurement_checklist_files_table`) dan ikut di ZIP "Unduh Semua Dokumen" pada folder `4. Keuangan/<nama item>`.
> - **Test:** `FinanceUploadTest`.

- Pengadaan yang diserahkan muncul di menu **Keuangan** (antrean *Menunggu Pembayaran*, total nilai tagihan, dan riwayat *Sudah Dibayar*) serta kartu dashboard **Tagihan Menunggu Pembayaran**, dan seluruh akun Keuangan mendapat notifikasi.
- Peran **Keuangan** (`keuangan`) mengerjakan dua tab di detail pengadaan, yaitu **Penyerahan** dan **Pembayaran** (dulu satu tab *Keuangan*). Tautan lama `?tab=keuangan` membuka tab Penyerahan.
- **Isi checklist** diambil dari pasal syarat penyerahan dan syarat pembayaran pada template kontrak masing-masing format:

| Kontrak (format / kategori) | Tab Penyerahan | Tab Pembayaran |
|---|---|---|
| SPK Barang (SPK / barang) | Pasal 6 ayat (7) huruf a–f | Pasal 5 ayat (1) huruf a–m |
| SPK Jasa (SPK / jasa) | Pasal 6 ayat (8) huruf a–g | Pasal 5 ayat (1) huruf a–l |
| Surat Perjanjian (PJ) | Pasal 5 ayat (5) huruf a–g | Pasal 10 ayat (1) huruf a–h |
| Lampiran Surat Pesanan (SPPL) | Bagian B (Penerimaan) butir 3–4 | Bagian D (Pembayaran) butir 1 huruf a–o |

- **Sifat item:**
  - Item bertanda "(apabila ada)" atau "(jika ada)" bersifat **opsional**.
  - Untuk SPPL, item yang khusus barang atau jasa juga opsional.
  - Keterangan setiap item menyebut sumbernya, misalnya "SPK Jasa Pasal 6 ayat (8) huruf f".
- **Penyaringan per pengadaan:**
  - Syarat hanya muncul setelah **format kontrak** diisi. Untuk format yang memakai kategori (SPK), kategori **barang/jasa** juga harus diisi.
  - Selama belum diisi, kedua tab menampilkan pemberitahuan agar data itu dilengkapi lewat Ubah Data.
  - Pengadaan tanpa format tidak lagi menampilkan syarat semua format sekaligus.
  - Format kontrak memakai pengecualian format yang sudah ada.
  - Kategori barang/jasa memakai kolom baru `checklist_items.work_category` (kosong berarti berlaku untuk keduanya).
  - Tab ditentukan kolom `checklist_items.section` (`penyerahan` / `pembayaran`, enum `FinanceSection`). Item Keuangan tanpa tab tampil di Pembayaran.
- **Dibaca dari kontrak pengadaan itu sendiri (opsi B):** `ContractRequirementReader` membaca dokumen kontrak milik pengadaan.
  - **Dokumen yang dibaca:** SPK atau Surat Perjanjian (jenis dokumen `spk`) untuk format SPK/PJ, dan Lampiran SP (`lampiran-sp`) untuk SPPL.
  - **Daftar yang diambil:** daftar berhuruf yang langsung mengikuti frasa penyerahan ("dilengkapi dokumen sebagai berikut" / "harus dilengkapi dengan") dan frasa pembayaran ("melampirkan" / "dilampiri"). Daftar harus berada di paragraf atau sel yang sama dengan frasanya.
  - **Hasil:** setiap butir menjadi item checklist milik pengadaan itu (kolom `checklist_items.procurement_id`), dengan keterangan pasal, misalnya "SPK (Surat Perintah Kerja) Pasal 6 ayat (8) huruf f". Penomoran diambil dari judul pasal dan kolom nomor ayat, bukan dari teks.
  - **Sifat opsional:** item opsional bila diakhiri "(apabila ada)", "(jika ada)", atau "(untuk …)".
  - **Panjang nama:** kolom nama diperlebar menjadi 500 karakter agar teks kontrak tersimpan utuh.
  - **Pembaruan:** daftar diperbarui setiap kali detail pengadaan dibuka, mengikuti isi kontrak terbaru termasuk hasil editan.
    - Butir dengan teks yang sama tetap item yang sama, jadi centangnya bertahan.
    - Butir yang dihapus dari kontrak dinonaktifkan. Centangnya tetap tersimpan sebagai riwayat.
    - Setelah pembayaran dicatat, daftar dibekukan.
  - **Fallback ke template:** bila dokumen kontrak belum dibuat atau daftarnya tidak terbaca, tab itu memakai syarat template dari Data Master. Contohnya syarat penyerahan SPPL, yang ditulis dalam kalimat.
  - **Penanda sumber:** tiap tab menampilkan sumbernya, yaitu judul dokumen kontrak atau "template standar".
  - **Pengaman:**
    - Kontrak yang gagal dibaca dicatat di log dan tidak membuat halaman error.
    - Penulisan dikunci per pengadaan, sehingga dua pengguna yang membuka bersamaan tidak membuat item ganda.
    - Item milik pengadaan tidak tampil di Data Master, papan monitoring, atau pengadaan lain, dan tidak bisa diubah atau dihapus lewat Data Master.
    - Item itu ikut terhapus bila pengadaannya dihapus.
  - Migration: `2026_10_15_150516_add_procurement_id_to_checklist_items`. Test: `ContractRequirementTest`.
- **Penjaga kecocokan:** `FinanceRequirementSourceTest` membaca file template dan memastikan daftar syarat sama kata per kata dengan pasalnya.
  - Pengecualian yang disengaja ada dua: "selama masa garansi" menggantikan placeholder bulan, dan "TTE" menggantikan "tanda tangan elektronik" agar muat 255 karakter.
  - Bila pasal penyerahan atau pembayaran di template diubah, test ini gagal sampai syaratnya disesuaikan.
- **Data Master → Item Checklist** sekarang punya isian **Tab Keuangan** dan **Kategori Pekerjaan**, sehingga semua item tetap bisa diubah atau ditambah.
- **Filter data Item Checklist** untuk melihat susunan proses:
  - **Jenis filter:** Cari (nama, keterangan, dokumen), Tahap (termasuk Keuangan — Penyerahan / Pembayaran), Berlaku untuk Format Kontrak, Kategori Pekerjaan, Sifat, Isian Tahapan, dan Status. Ada juga tombol Reset dan jumlah "Menampilkan X dari Y".
  - **Cara kerja filter Format dan Kategori:** menampilkan item yang dilalui pengadaan dengan kriteria itu. Item yang dilewati (dikecualikan) disembunyikan, sedangkan item yang berlaku untuk semua tetap ditampilkan.
  - **Penyimpanan filter:** tersimpan di alamat halaman (query string), jadi tetap terpasang setelah item disimpan atau halaman dimuat ulang, dan bisa dibagikan lewat tautan.
  - **Teknis:** penyaringan dilakukan di sisi tampilan, tanpa perubahan backend. `MasterDataPage` mendapat prop opsional `toolbar` dan `filtered`; halaman data master lain tidak berubah.
- **Seeder dan migration:**
  - Seeder: `FinanceRequirementSeeder`, ikut `DatabaseSeeder`.
  - Migration `2026_10_15_141204_split_keuangan_into_penyerahan_and_pembayaran`: menambah kedua kolom, memasang syarat, dan menonaktifkan 6 tahapan Keuangan generik lama (Penerimaan Tagihan, BAST, Faktur Pajak, Verifikasi, Pengajuan Pembayaran, Pembayaran ke Mitra).
  - Centang lama yang sudah selesai tetap tersimpan sebagai riwayat.
- **Kartu pembayaran:** status dan tombol **Pembayaran Lunas & Tutup Pengadaan** ada di tab Pembayaran.

- Setelah semua tahapan wajib selesai, Keuangan menekan **Pembayaran Lunas & Tutup Pengadaan**: pengadaan ditutup (`completed_at`), status progres otomatis menjadi **Selesai**, dan pengusul, TL, Asman, PIC Pelaksana, serta TL Pengadaan/ICC mendapat notifikasi.
- Pengadaan tidak lagi bisa ditutup manual sebelum dibayar (hak `procurement.complete` hanya berlaku untuk pengadaan yang sudah lunas).
- Hak akses: `procurement.process-payment` (Proses pembayaran, bawaan: Keuangan) dan `menu.finance` (Menu Keuangan, bawaan: Keuangan, TL Pengadaan, TL ICC). Keuangan juga dapat mengusulkan pekerjaan.

## Penyempurnaan Alur

- **Layout detail pengadaan:** kartu Identitas Pengadaan, Calon Mitra, dan Usulan Pekerjaan digabung menjadi satu panel ringkas (`components/procurement/identity-panel.tsx`), sehingga tab (Perencanaan, Pelaksanaan, dst.) menjadi bagian yang dominan.
  - Panel menampilkan 8 data utama dalam grid rapat (label di atas nilai): Direksi Pekerjaan, Unit Tujuan, Sumber Anggaran, Jenis Kontrak, Target Selesai, Calon Mitra, Nilai Sebelum Nego, dan Nilai Setelah Nego.
  - Rincian lainnya ada di tombol **Lihat detail lengkap**: data mitra, nomor PRK/COA/PR/SR/WO, nota dinas, dan catatan. Pilihan buka/tutup diingat per pengguna di browser.
- **Target Selesai** dipakai kolom target, kurva-S, dan penanda terlambat. Pengisinya:
  - Pemegang tombol **Edit Identitas Pengadaan** (TL Pengadaan, admin) mengisinya di form Edit, bagian Informasi Tambahan. Untuk mereka, panel identitas di halaman detail hanya-baca (Jenis Kontrak dan Target Selesai tidak bisa diubah di sana).
  - PIC Perencana, yang tidak punya form Edit, tetap mengisi Jenis Kontrak dan Target Selesai langsung di panel identitas detail sampai perencanaan disetujui.
  - Usulan dari bidang tidak pernah membawa Target Selesai.
- **Notifikasi tiap tahap** ke pengusul, TL atasan, dan Asman bidang: usulan disetujui TL/Asman, perencanaan disetujui, diserahkan ke Keuangan, dan pengadaan selesai.
- **PIC Pelaksana** yang ditunjuk sebelum perencanaan disetujui baru mendapat notifikasi saat perencanaan disetujui.
- Detail pengadaan otomatis terbuka di tab tahap yang sedang berjalan (Perencanaan / Pelaksanaan / Keuangan).

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

## Direksi Pekerjaan (`direksi_pekerjaan`)

- Asman bidang (atau TL K3 KAM / TL Lingkungan). Meninjau lampiran usulan pekerjaan bidangnya lalu menyetujui atau mengembalikan.
- Melihat seluruh pengadaan milik bidangnya (berdasarkan Direksi Pekerjaan pengadaan).

## Keuangan (`keuangan`)

- **Revisi (Penyerahan & Pembayaran dengan persetujuan):** staf Keuangan melengkapi berkas **Pembayaran** setelah TL Pengadaan menyetujui Penyerahan, lalu mengajukannya ke TL Keuangan (atasan staf) dan Asman Business Support. Persetujuan Asman mencatat lunas dan menutup pengadaan. Lihat bagian *Alur Penyerahan & Pembayaran dengan Persetujuan*.
- Melihat **seluruh pengadaan** (baca saja, hak "Lihat seluruh pengadaan"), tetapi tidak lagi mengusulkan pekerjaan maupun membuka menu Usulan.
- Akun bawaan: Nova Sang Jaya (TL Keuangan, sekaligus TL atasan staf keuangan), Muhammad Jaya Junaid, Inggrid Novela Pitaloka.

## Pengusul (`pengusul`)

- Staf/TL bidang. Hanya dapat mengusulkan pekerjaan untuk bidangnya sendiri, memantau status usulan, memperbaiki usulan yang dikembalikan, dan melihat pengadaan yang ia usulkan.

---

# Fitur Tambahan & Penyempurnaan Terkini

### 1. Fitur Preview Dokumen pada Checklist Perencanaan & Pelaksanaan
- Setiap dokumen yang diunggah pada tabel checklist perencanaan maupun pelaksanaan kini dilengkapi tombol **Preview** (ikon mata `Eye`) di sebelah tombol Unduh dan Hapus.
- Tombol Preview memunculkan modal dialog responsif yang menampilkan dokumen secara langsung di dalam browser (menggunakan `iframe` untuk berkas PDF dan tag `img` untuk berkas PNG/JPG), sehingga pengguna tidak perlu mengunduh file untuk memeriksa kelengkapan dokumen.

### 2. Standarisasi Logo Perusahaan pada Dokumen
- Seluruh template dokumen (Nota Dinas Usulan, TOR, UPB, RKS, SPK, BA Negosiasi, Surat Pesanan, Lampiran SP Barang dan Jasa) menggunakan logo korporat resmi PLN yang bersumber dari `public/logo/sidebar-logo.png`.
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

### 7. Integrasi Alamat Calon Mitra pada Dokumen Surat Pesanan
- Bagian **KEPADA** pada dokumen Surat Pesanan kini memanggil alamat calon mitra secara dinamis menggunakan tag `{{alamat_mitra}}` (atau `{{alamat_perusahaan}}`) yang bersumber dari inputan **Alamat Perusahaan** pada form Calon Mitra.
- Jika alamat belum diisi, sistem secara cerdas menampilkan teks default `"DI TEMPAT"`. Begitu alamat diisi pada Edit Identitas Pengadaan, dokumen Surat Pesanan yang telah digenerate otomatis tersinkronisasi tanpa harus menekan tombol muat ulang secara manual.

### 8. Lembar Halaman Terpisah di Editor Visual
- Dokumen yang lebih dari satu halaman tampil sebagai lembar-lembar terpisah di Editor Visual. Di antara lembar ada jeda abu-abu berlabel **Halaman N**. Dokumen satu halaman tidak berubah.
- Batas halaman tidak ditebak di browser karena font dan margin di layar berbeda dengan PDF. Editor menomori setiap elemen, lalu mengirim draf ke `POST procurements/{p}/documents/{d}/pages` (`documents.pages`). Mesin PDF (dompdf, `DocumentPdfRenderer::sheetStarts`) menata draf itu dan melaporkan elemen pertama di setiap halaman. Hasilnya di-cache 1 hari per isi dokumen. Permintaan ini dikirim ulang ±0,8 detik setelah penulis berhenti mengetik.
- Elemen awal lembar diberi atribut `data-sheet-start` sehingga editor membuka jeda di atasnya; aturan CSS ada di `app.css`. Atribut ini dibuang sebelum disimpan atau dipratinjau, jadi tidak pernah masuk ke dokumen maupun PDF. Baris tabel yang pindah halaman ikut dipisah. Teks yang terpotong di tengah sel atau paragraf hanya diberi garis putus-putus. Penanda manual **Batas Halaman** tetap memakai tampilannya sendiri.

### 9. Perbaikan Error "Attempt to read property "name" on null" (production)
- **Gejala:** halaman edit dokumen error bila akun PIC Perencana atau PIC Pelaksana pengadaan itu sudah dihapus. Akun pengguna memakai soft delete, sedangkan relasi `planner` / `executor` tidak membaca akun yang terhapus.
- **Perbaikan:**
  - Placeholder `pic_perencana` / `pic_pelaksana` di `DocumentGenerator::placeholderValues` menampilkan "-" bila akunnya tidak ada lagi.
  - Hal yang sama berlaku untuk kolom PIC di ekspor laporan (`ReportController`).
- **Cakupan:** tidak ada fungsi atau fitur baru.
- **Test:** `DocumentEditingTest::test_the_editor_opens_when_the_pic_accounts_were_deleted`.

### 10. Filter Format Kontrak di Halaman Perencanaan & Pelaksanaan
- **Filter:** halaman **Perencanaan** dan **Pelaksanaan** punya filter **Format Kontrak** (Semua / SPK / PJ / SPPL) di samping filter Sumber Anggaran.
- **Sumber pilihan:** master data Format Nomor Kontrak (`MasterDataOptions::contractFormats`, scope `forContracts`), jadi format Amandemen dan Delivery Order tidak ikut tampil. Format baru yang ditambahkan di master data otomatis muncul.
- **Penerapan:**
  - Parameter `contract_number_format_id` ditambahkan ke filter bersama `ProcurementFilters`.
  - Dropdown memakai `extraSelects` yang sudah ada di `ProcurementFilterBar`, sehingga filter lain dan tombol Reset tetap bekerja seperti sebelumnya.
- **Filter Jenis Kontrak:** di kedua halaman yang sama ada juga filter **Jenis Kontrak**.
  - Pilihannya dari master data **Jenis Kontrak** (saat ini KHS, Lumsum, Barang, Jasa) lewat `MasterDataOptions::contractTypes()` yang sudah ada, dengan parameter `contract_type_id` di `ProcurementFilters`.
  - Jenis baru seperti "Barang dan Jasa" cukup ditambahkan di Data Master → Jenis Kontrak, lalu otomatis muncul di filter.
- **Test:** `ContractFormatFilterTest`.

### 11. Tab Aktif Berwarna Biru
- **Komponen tab bawaan** (`components/ui/tabs.tsx`): tab aktif kini bergaris bawah dan berteks **biru (primary)** tebal; tab lain berubah biru saat disorot. Berlaku di detail pengadaan (Perencanaan, Pelaksanaan, Penyerahan, Pembayaran, Amandemen, DO, Riwayat Approval), editor dokumen, dan detail penilaian penyedia.
- **Halaman Approval:** tab antrean (Usulan/Perencanaan/Pelaksanaan) dan tab **Menunggu Keputusan / Riwayat Keputusan** yang aktif berlatar biru dengan teks putih (sebelumnya putih di atas abu-abu sehingga tidak terlihat). Lencana jumlah ikut menyesuaikan warnanya.
- **Pengaturan → Tampilan:** pilihan tema yang aktif juga berlatar biru.

### 12. Kolom No di Perencanaan, Pelaksanaan, dan Penunjukan PIC
- Ketiga daftar punya kolom **No** di paling kiri. Nomor berlanjut antarhalaman (halaman 2 mulai dari 16), dihitung dari `meta.from` paginasi.
- `ProcurementTable` mendapat opsi `numbered` (hanya dipakai di Perencanaan dan Pelaksanaan, jadi daftar lain tidak berubah). Tabel Penunjukan PIC menambah kolom yang sama memakai helper `firstRowNumber`.
- **Test:** `PaginationContractTest::test_numbered_lists_learn_where_their_page_starts`.

### 13. Rincian Item di Editor Dokumen (tabel item tidak lagi diketik per sel)
- **Lokasi:** editor dokumen yang berisi tabel item punya tab **Rincian Item** paling kiri dan langsung terbuka. Dokumennya: BA Negosiasi (SPPL), Surat Pesanan/PO, SPK (tabel), HPE, UPB, Penyusunan HPS, Penawaran, dan Lampiran SPK.
  - Tab hanya muncul bila tabel itemnya benar-benar ada (`ItemTableRenderer::hasTable`).
  - Dokumen pasal (SPK Pasal, Surat Perjanjian PJ, Lampiran SP) tetap memakai editor biasa tanpa perubahan.
- **Isian berbentuk grid**, dengan kolom Uraian, Part No., Volume, Satuan, Harga Satuan Sebelum Nego, Harga Satuan Setelah Nego, dan Jumlah (otomatis), serta total.
  - Baris **Kelompok** menjadi judul (A, B, …) bercetak tebal. Item di bawahnya bernomor 1, 2, … dan baris kelompok tidak dihitung sebagai item.
  - Tombol yang tersedia: **Tambah Item**, **Tambah Kelompok**, **+ Item** di baris kelompok (menambah di akhir kelompok itu, sehingga Kelompok A tetap bisa ditambah setelah Kelompok B dibuat), **+** di baris item (menyisipkan tepat di bawahnya), naik/turun, dan hapus.
  - Bisa **tempel dari Excel** di kolom Uraian, dengan urutan kolom Uraian, Part No, Volume, Satuan, Harga Sebelum, Harga Setelah. Baris yang hanya berisi uraian menjadi kelompok.
- **Realtime:** setiap perubahan isian (jeda 0,4 detik) menyusun ulang tabel di draf Editor Visual lewat server (`documents.items-preview`, renderer yang sama dengan cetakan), sehingga Editor Visual dan Pratinjau Cetak langsung menampilkan hasilnya.
- **Simpan:** isian ikut tersimpan dengan tombol **Simpan Perubahan** dokumen (`item_rows` di `documents.update`, hak `manageItems`).
  - Pihak yang boleh mengisi: perencana selama perencanaan, pelaksana setelah perencanaan disetujui. Isian terkunci setelah diserahkan ke Keuangan atau bila pengadaan Batal.
  - Setelah disimpan, semua tabel item dokumen pengadaan itu ikut tersusun ulang, dan **Nilai Setelah Nego** mengikuti total setelah nego.
- **Tanpa fitur ganda:** di Editor Visual dokumen bertabel item, tombol sisip tabel serta aksi tambah/hapus baris/kolom dan Hitung Ulang disembunyikan. Dokumen lain tidak berubah.
- **Output tidak berubah:** tabel disusun dengan markup dan gaya sel yang sama seperti template (`ItemTableRenderer`).
  - SPK: baris pekerjaan, baris "A. kelompok" dan "- item"; baris JUMLAH/PPN/TOTAL tetap.
  - HPE: kolom Stock Code berisi part number, Harga HPE berisi jumlah, lalu baris Jumlah.
  - HPS/Penawaran/Lampiran SPK: Sub Total, PPN 12%, Total, dan Terbilang.
  - Tabel yang kolom harganya belum terisi dibiarkan seperti template.
- **Data:** tabel `procurement_contract_items` (juga daftar harga DO KHS) ditambah `volume` dan `price_before`, dan `unit_price` boleh kosong (migrasi `extend_contract_items_into_procurement_items`).
  - Tabel BA Negosiasi yang sudah ada dibaca sekali menjadi Rincian Item.
  - Untuk BA Negosiasi tanpa Rincian Item, editor langsung menampilkan baris tabelnya sebagai isian awal.
  - Tabel yang masih diketik di editor BA Negosiasi juga dibaca menjadi Rincian Item saat disimpan.
- **Perbaikan bug:** baris kelompok yang terketik dengan volume 1 dan harga 0 (mis. "Material & Tools Tangki 50 KL") dulu terbaca sebagai item Rp 0 di Surat Pesanan. Sekarang baris tanpa harga dibaca sebagai judul kelompok, dan Surat Pesanan mencetaknya sebagai baris kelompok.
- **Test:** `ProcurementItemsTest`.

### 15. Kolom Isian Lebih Jelas
- **Seluruh aplikasi:** border kolom isian (token `--input`) dibuat lebih gelap, dari `oklch(0.88 …)` menjadi `oklch(0.72 …)` di mode terang dan dari `0.35` menjadi `0.48` di mode gelap. Input, Textarea, dan Select juga diberi latar `bg-background`, sehingga setiap kolom yang perlu diisi tampak jelas berbeda dari kartu di sekitarnya. Ini berlaku di semua form (usulan, perencanaan, checklist, master data, penilaian, login, dan lain-lain), begitu juga Checkbox dan Switch yang memakai token yang sama.
- **Grid Rincian Item:**
  - Sel isian berlatar abu-biru (`bg-muted`) dengan border gelap, lalu berubah putih saat diketik.
  - Kolom nama kelompok bernuansa biru.
  - Kepala tabel dibuat lebih tegas.

### 14. Nama Penanda Tangan Huruf Besar
- Di seluruh template, nama penanda tangan di blok tanda tangan dicetak **huruf besar** lewat `text-transform: uppercase` pada namanya saja. Ini berlaku untuk Manager, Direktur, Direksi Pekerjaan, PIC Perencana/Pelaksana, dan TL Pelaksana Pengadaan.
- Data tetap tersimpan apa adanya, dan nama yang sama di teks isi (mis. "arahan dari Direksi Pekerjaan" di TOR, uraian para pihak) tidak berubah.
- Sebanyak 26 template diperbarui dan disinkron lewat migrasi `uppercase_signatory_names_in_templates`. Template yang sudah diubah admin tidak ditimpa, dan dokumen lama tetap seperti saat digenerate.
- **Test:** `SignatoryNamesTest`.

### 16. Satu Template per Dokumen & HPE/UPB Format Terbaru
- **HPE dan UPB** sekarang hanya punya satu template, yaitu format terbaru UP Kendari (sebelumnya khusus PJ), dan berlaku untuk semua format kontrak (SPK, PJ, SPPL).
  - HPE berjudul "Harga Perkiraan Enjiniring" dengan kolom No., Stock Code, Nama Barang dan Spesifikasi, Qty, Stn, Harga HPE (Rp.), dan Keterangan, lalu baris Jumlah.
  - UPB berjudul "USULAN PENGADAAN BARANG / JASA" dengan kolom No., Stock Code, Nama Barang dan Spesifikasi, No. Requisisi, No. WO, WO Description, Qty, Satuan, Tgl Dibutuhkan, dan Keterangan.
  - File template lama `umum/hpe.html` dan `umum/upb.html` diganti dengan format terbaru, dan entri `pj.hpe`/`pj.upb` dihapus dari manifest.
- **Rincian Item di UPB:** UPB kini ikut memakai tab Rincian Item, seperti HPE.
  - Setiap item menjadi satu baris berisi nomor, Stock Code (part number), uraian (baris baru tetap menjadi baris baru, mis. spesifikasi), Qty, dan Satuan huruf besar.
  - No. Requisisi, No. WO, dan Tgl Dibutuhkan dari baris pekerjaan template diisikan ke setiap item.
  - UPB tidak memiliki harga, jadi tabel langsung tersusun begitu item diisi.
- **Baris kosong** pengisi tabel di template (HPE dan UPB) tetap dipertahankan setelah tabel disusun dari Rincian Item.
- **Satu versi per template:**
  - Sinkron template bawaan (`templates:sync`) kini memperbarui template yang sudah terpasang langsung, tanpa membuat versi 2, 3, dan seterusnya.
  - Migrasi `keep_a_single_template_per_document` menghapus versi lama di setiap cakupan template, menghapus HPE/UPB PJ dan template bawaan yang sudah digabung (Lampiran SP/Surat Pesanan Barang & Jasa) yang tidak terpakai, lalu menjadikan template yang tersisa versi 1.
  - Dokumen yang menunjuk template yang dihapus dialihkan ke template yang dipertahankan. Isi dokumennya tidak berubah, dan dokumen yang belum diedit otomatis tersusun ulang dari template terbaru saat dibuka.
  - Hasil di database lokal: 38 template, semuanya aktif dan versi 1.
- **Test:** `SingleTemplateVersionTest`, `ProcurementItemsTest` (tabel UPB), `DocumentTemplateLibraryTest`, `RksPjJasaTest`, `WorkCategoryTest`, `SignatoryNamesTest`.

### 17. Tidak Ada Template Versi 2
- Di Data Master → Template Dokumen, menambah template untuk dokumen (dengan metode, format kontrak, dan kategori yang sama) yang sudah punya template kini **ditolak** dengan pesan "Dokumen ini sudah punya template (…). Ubah template itu saja; satu dokumen hanya memakai satu template."
- Template baru selalu **versi 1**. Mengubah template memperbarui template itu sendiri, dan memindahkan template ke dokumen yang sudah punya template juga ditolak.
- Template yang sudah diarsipkan tidak dihitung, jadi dokumennya boleh diberi template baru (versi 1).
- **Test:** `MasterDataManagementTest` (`test_administrator_cannot_add_a_second_version_of_a_template`, `test_a_template_cannot_be_moved_onto_a_document_that_has_one`), `WorkCategoryTest`.

### 18. Semua Nama Penanda Tangan Huruf Besar (lanjutan)
- Melengkapi poin 14: seluruh blok tanda tangan di 31 template kini dijamin huruf besar lewat `<span style="text-transform: uppercase;">`, termasuk:
  - kolom nama yang dikosongkan untuk diketik nanti ("( Nama Jelas )" dan titik-titik), sehingga nama yang diketik di editor ikut tercetak huruf besar;
  - nama yang sebelumnya hanya mengandalkan class `.upper` milik template (Direktur di SPK Pasal, Surat Perjanjian, Amandemen; PIC Perencana di RKS PJ), karena class bisa hilang saat diedit di Editor Visual;
  - nama mitra di atas tanda tangannya (Surat Pesanan, SPK tabel, DO, BA Negosiasi SPPL) dan nama/jabatan penyedia di lampiran RKS PJ.
- Nama di teks isi tetap seperti data tersimpan. Disinkron lewat migrasi `uppercase_every_signatory_name_in_templates` (template yang diubah admin tidak ditimpa).
- **Test:** `SignatoryNamesTest::test_every_name_under_a_signature_in_every_template_prints_in_capitals`.

### 19. RAB menjadi Evaluasi Harga
- Langkah checklist perencanaan **RAB (Rencana Anggaran Biaya)** berganti nama menjadi **Evaluasi Harga**, begitu juga jenis dokumennya (kode tetap `rab`). Dokumen ini sekarang **digenerate** (tidak lagi hanya unggah), lalu tetap diunggah versi bertanda tangannya.
- **Format dokumen** sesuai contoh "EVALUASI HARGA PERKIRAAN ENJINIRING" (A4 lanskap):
  - Kolom No., Stock Code, Nama Barang dan Spesifikasi, Harga (No PO, Tgl PO, Supplier, Harga Lalu), satu kolom per calon mitra (nama mitra huruf besar), dan Harga HPE.
  - Di bawah tabel tercantum Informasi Tambahan dan RKAP Budget.
- **Harga per mitra diisi di Rincian Item:** editor Evaluasi Harga menampilkan satu kolom harga untuk setiap calon mitra, dan tabel dokumen tersusun realtime.
  - Disimpan di tabel baru `procurement_item_quotations` (item × calon mitra).
  - Harga HPE memakai harga HPE item; bila kosong, otomatis diisi harga mitra terendah.
  - Menyimpan Rincian Item dari editor dokumen lain tidak menghapus harga mitra.
  - Menambah, mengubah, atau menghapus calon mitra langsung menyusun ulang kolom mitra di dokumen.
- SPPL tetap melewati langkah ini. Teks "RAB" di halaman login, beranda, Item Checklist, dan status progres ikut diganti.
- Migrasi: `create_procurement_item_quotations_table`, `rename_rab_to_evaluasi_harga` (template `umum.rab` dipasang ulang dengan format baru).
- **Test:** `EvaluasiHargaTest`; `UploadOnlyStepTest` dan `ChecklistDocumentRequirementTest` disesuaikan.

### 20. Kolom Rincian Item Mengikuti Dokumennya
- Grid Rincian Item kini menampilkan kolom yang sama dengan tabel cetakan dokumen yang sedang diedit, dengan urutan dan istilahnya. Data item tetap satu, dan kolom yang tidak tampil tidak berubah:

| Dokumen | Kolom grid (setelah No & Uraian) |
|---|---|
| BA Negosiasi / BA Negosiasi SPPL | Volume, Satuan, Harga Sblm Nego, Harga Stlh Nego, Jumlah |
| Surat Pesanan | Volume, Satuan, Harga Satuan, Jumlah Harga |
| SPK, Lampiran SPK | Volume, Satuan, Harga Satuan, Jumlah |
| HPE | Stock Code, Qty, Stn, Harga Satuan, Harga HPE (jumlah) |
| Evaluasi Harga | Stock Code, No PO, Tgl PO, Supplier, Harga Lalu, satu kolom per calon mitra, Harga HPE |
| Penyusunan HPS / Penawaran | Volume, Satuan, Harga Satuan, Jumlah |
| UPB | Stock Code, Qty, Satuan |

- Part No. tidak lagi tampil di dokumen yang tidak mencetaknya (BA Nego, Surat Pesanan, SPK, HPS, Penawaran).
- **Pembelian terakhir (Evaluasi Harga):** item kini menyimpan No PO, Tgl PO, Supplier, dan Harga Lalu (migrasi `add_previous_purchase_to_procurement_contract_items`). Keempatnya tercetak di kolom "Harga"; Harga Lalu yang kosong tercetak 0,00 seperti contoh. Menyimpan dari editor dokumen lain tidak menghapusnya.
- Bila pengadaan belum punya calon mitra, editor Evaluasi Harga menampilkan pesan agar calon mitra ditambahkan dulu.
- **Isian awal:** HPE, UPB, dan Evaluasi Harga tanpa Rincian Item langsung membuka grid berisi baris pekerjaan seperti yang tercetak di template (nama pekerjaan, 1 LOT, nilai HPE). Baris ini baru tersimpan saat Simpan Perubahan.
- Tempel dari Excel mengikuti urutan kolom yang tampil.
- **Perbaikan urutan baris HPE:** item kini tercetak tepat di bawah kepala tabel, lalu baris kosong pengisi, lalu baris Jumlah. Sebelumnya baris kosong berada di atas item, sehingga data terdorong ke bawah tabel. Dokumen yang belum diedit tersusun ulang otomatis (`ItemTableRenderer::VERSION` ikut dalam sidik pembaruan dokumen).
- **Perbaikan:** dokumen yang belum diedit (mis. Surat Pesanan) kini ikut tersusun ulang saat Rincian Item berubah. Sebelumnya cache pembaruan dokumen tidak memperhitungkan Rincian Item, sehingga Surat Pesanan masih mencetak judul kelompok sebagai item "Lot Rp 0". Selain itu, `syncItemDocuments` tidak lagi mengubah dokumen yang sudah diunggah versi bertanda tangannya.
- **Test:** `ProcurementItemsTest::test_an_unedited_document_follows_items_changed_elsewhere`.

### 21. Keamanan Berkas Unggahan
- **Cara penyimpanan (sudah ada):** semua unggahan disimpan di disk privat `storage/app/private` (di luar folder publik, tanpa `public/storage`) dengan nama acak. Berkas hanya dibuka lewat rute aplikasi yang memeriksa hak `view` pengadaan dan kepemilikan berkasnya. Unggahan dibatasi PDF/JPG/PNG (lampiran usulan juga DOC/XLS), maksimal 20 MB.
- **Celah yang ditutup:** akhiran nama berkas dan jenisnya dulu memakai nama dan jenis kiriman browser. Akibatnya, berkas berisi PDF yang diberi nama `.html` (polyglot PDF/HTML berisi script) lolos validasi, tersimpan sebagai `.html`, dan saat dipratinjau bisa tampil sebagai halaman web di domain aplikasi (XSS).
- **Perbaikan:**
  - Kelas baru `App\Support\UploadedFileType` menentukan akhiran dan jenis berkas dari **isinya**. Kelas ini dipakai di semua tempat unggah: dokumen bertanda tangan (`SignedDocumentStore`), berkas checklist, penawaran calon mitra, berkas amandemen, permintaan DO, dan lampiran usulan.
  - Saat ditampilkan, berkas memakai jenis dari daftar putih (PDF, JPEG, PNG tampil di browser; selain itu hanya diunduh) ditambah header `X-Content-Type-Options: nosniff`. Berkas lama yang tercatat dengan jenis kiriman browser ikut aman, karena jenis di luar daftar putih selalu diunduh.
  - Rute bawaan `storage/{path}` (GET dan PUT) ditutup dengan `'serve' => false` pada disk `local` di `config/filesystems.php`, karena aplikasi tidak memakainya.
- **Belum ada (di luar aplikasi):** enkripsi berkas di disk, pemindaian virus, dan backup folder `storage/app/private` (perlu diatur di server).
- **Test:** `UploadSecurityTest`.

### 22. Halaman Error (403, 404, 419, 429, 500, 503)
- Halaman error bawaan Laravel diganti halaman milik aplikasi (`resources/js/pages/error.tsx`) dengan gaya korporat biru-putih yang sama (sesuai Panduan Tema):
  - header berlogo PLN "Management Pengadaan · UP Kendari";
  - kartu putih dengan garis aksen biru, ikon dan kode status besar berwarna biru, judul, penjelasan, dan saran langkah;
  - footer PT PLN Nusantara Power · UP Kendari.
- Isi per status:

| Status | Judul | Tombol |
|---|---|---|
| 403 | Anda tidak memiliki akses ke halaman ini (alasan khusus dari sistem, mis. saat Pindah Akun, ikut ditampilkan) | Ke Dashboard / Masuk, Kembali |
| 404 | Halaman tidak ditemukan | Ke Dashboard / Masuk, Kembali |
| 419 | Sesi halaman sudah berakhir | Muat Ulang, Ke Dashboard, Kembali |
| 429 | Terlalu banyak permintaan dalam waktu singkat, dengan hitung mundur "Coba lagi dalam N detik" dari header `Retry-After` | Muat Ulang (aktif setelah hitung mundur), Ke Dashboard, Kembali |
| 500 / 503 | Terjadi kesalahan pada sistem / Sistem sedang dalam pemeliharaan | Muat Ulang, Ke Dashboard, Kembali |

- Tombol Ke Dashboard tampil untuk pengguna yang sudah masuk; pengunjung mendapat tombol Masuk.
- **Teknis:**
  - `Inertia::handleExceptionsUsing` di `AppServiceProvider::configureErrorPages`.
  - Status 500/503 hanya memakai halaman ini saat `APP_DEBUG=false`, agar stack trace tetap terlihat saat pengembangan.
  - Permintaan JSON tetap mendapat jawaban JSON.
  - `Route::fallback` di `routes/web.php` membuat alamat yang tidak dikenal tetap melewati session, sehingga halaman 404 tahu siapa yang login.
- **Test:** `ErrorPagesTest`.

### 23. Persetujuan Pelaksanaan di Kolom Kanan
- Kartu **Persetujuan Pelaksanaan** (ajukan ke TL Pengadaan, catatan, Setujui & Teruskan ke Keuangan / Kembalikan) dipindah dari bawah checklist tab Pelaksanaan ke **kolom kanan** detail pengadaan, tepat di bawah kartu Persetujuan Perencanaan. Dengan begitu kolom kanan yang tadinya kosong terpakai, dan tombol persetujuan langsung terlihat tanpa menggulir checklist yang panjang.
- Gayanya disamakan dengan kartu Persetujuan Perencanaan: label bagian, lencana status, dan tombol selebar kartu. Kartu tetap hanya muncul setelah perencanaan disetujui. Setelah diserahkan ke Keuangan, kartu berganti menjadi keterangan "Pelaksanaan disetujui dan diserahkan ke Keuangan".
- Di layar sempit, kolom kanan tetap turun ke bawah konten seperti sebelumnya.
- **Lebih ringkas:** daftar "Tahapan wajib yang belum selesai" di kartu Persetujuan Pelaksanaan dan Persetujuan Perencanaan (kolom kanan) diganti satu baris, misalnya "8 tahapan wajib di checklist Pelaksanaan belum selesai", karena tahapannya sudah terlihat di checklist di sebelahnya. Daftar di tab Pembayaran tetap.

### 24. Amandemen & Delivery Order di Menu Approval
- **Sebelumnya:** menu Approval hanya berisi antrean Perencanaan (TL ICC) dan Pelaksanaan (TL Pengadaan). Persetujuan Amandemen dan DO hanya bisa ditemukan lewat notifikasi atau tab Amandemen/DO di detail pengadaan.
- **Sekarang** ada dua antrean baru, **Amandemen** dan **Delivery Order**, masing-masing dengan jumlah yang menunggu, tab Menunggu Keputusan / Riwayat Keputusan, dan tombol **Tinjau** yang membuka tab Amandemen/DO pengadaan itu (keputusan diambil di sana, dengan isi permohonan lengkap).
- **Siapa melihat apa** (aturan sama dengan policy, `App\Support\ContractRequestQueue`):

| Langkah | Amandemen | DO |
|---|---|---|
| Persetujuan TL | TL atasan pengusul | TL atasan pengusul |
| Persetujuan Asman | Direksi Pekerjaan bidang pengadaan | Direksi Pekerjaan bidang pengadaan |
| Rendan (Nota Dinas DO) | — | PIC Perencana pengadaan itu, atau TL (TL ICC/TL Pengadaan/Admin) |
| Laksdan (penerbitan) | PIC Pelaksana pengadaan itu, TL Pengadaan, Admin | PIC Pelaksana pengadaan itu, TL Pengadaan, Admin |

  Tidak ada yang memutuskan permohonannya sendiri. Riwayat Keputusan berisi permohonan yang pernah disetujui/dikembalikan, diteruskan, atau diterbitkan pengguna itu.
- **Akses menu:** menu Approval kini juga terbuka bagi yang terlibat (mis. Asman dan TL atasan yang tidak memegang hak Menu Approval), lewat gate `open-approvals`. Pengguna yang tidak terlibat dan tidak punya hak tetap tidak melihat menu ini.
- **Test:** `ContractApprovalQueueTest`; `ApprovalQueueTest` disesuaikan (TL ICC kini juga mendapat antrean DO, TL Pengadaan antrean Amandemen dan DO).

### 25. Notifikasi Realtime, Pop-up Browser, dan Panel Dashboard
- **Sebelumnya:** notifikasi tersimpan di database dan hanya tampil lewat ikon lonceng dan halaman Notifikasi. Angka di lonceng baru berubah saat berpindah halaman, tidak ada notifikasi browser, dan notifikasi hanya bisa ditandai dibaca sekaligus.
- **Realtime (tanpa library baru):**
  - Aplikasi yang terbuka memeriksa notifikasi baru setiap 15 detik dan setiap kali tab kembali aktif, lewat `GET notifications/feed`.
  - Angka di lonceng dan panel Dashboard langsung berubah tanpa memuat ulang halaman.
  - Notifikasi baru muncul sebagai pemberitahuan di dalam aplikasi (dengan tombol Buka).
- **Pop-up browser:**
  - Tombol **Aktifkan Notifikasi Browser** ada di panel Dashboard dan halaman Notifikasi; browser meminta izin sekali.
  - Setelah diizinkan, notifikasi baru tampil sebagai pop-up browser saat aplikasi tidak sedang di depan (tab lain, jendela lain, atau diperkecil). Mengklik pop-up membuka pengadaannya dan menandai notifikasi dibaca.
  - Satu notifikasi hanya diumumkan sekali walau aplikasi terbuka di beberapa tab.
  - Batasan: pop-up hanya muncul selama aplikasi terbuka di salah satu tab, dan memerlukan HTTPS di production (localhost dikecualikan). Pemberitahuan saat browser tertutup memerlukan Web Push atau WebSocket (Laravel Reverb), yang butuh paket dan proses server tambahan.
- **Panel "Notifikasi Belum Dibaca" di Dashboard:** tampil paling atas setiap kali aplikasi dibuka selama masih ada notifikasi yang belum dibaca (hingga 5 terbaru, dengan tautan Lihat semua). Tiap notifikasi punya tombol **Buka** dan **Tandai dibaca**, ditambah **Tandai semua dibaca**. Panel hilang sendiri saat semua sudah dibaca.
- **Tautan tepat sasaran:** notifikasi Amandemen dan DO kini membuka tab Amandemen/DO pengadaannya (`ProcurementMilestone` menyimpan `tab`).
- **Tandai dibaca per notifikasi:** `PUT notifications/{id}/read`, juga di halaman Notifikasi.
- **Teknis:** `App\Support\NotificationFeed`, `NotificationController::feed/read`, `resources/js/lib/notification-feed.ts`, `components/notification-watcher.tsx` (dipasang di layout aplikasi), `notification-panel.tsx`, `browser-notification-toggle.tsx`.
- **Test:** `NotificationFeedTest`.

### 26. Notifikasi Realtime dengan Laravel Reverb
- **Terpasang:** `laravel/reverb` (Composer), `laravel-echo`, `pusher-js`, dan `@laravel/echo-react` (npm), lewat `php artisan install:broadcasting --reverb`.
  - Berkas baru: `config/broadcasting.php`, `config/reverb.php`, dan `routes/channels.php` (kanal privat `App.Models.User.{id}`, hanya pemiliknya yang boleh mendengar). `bootstrap/app.php` kini mendaftarkan `channels`.
  - `.env`: `BROADCAST_CONNECTION=reverb`, serta `REVERB_*` dan `VITE_REVERB_*`.
- **Alur:**
  1. Setiap notifikasi yang tersimpan memicu listener `App\Listeners\BroadcastUnreadNotifications`. Listener ini berjalan **setelah transaksi database selesai**, jadi notifikasi yang batal (rollback) tidak pernah disiarkan.
  2. Listener menyiarkan event `App\Events\UnreadNotificationsChanged` (`notifications.changed`, `ShouldBroadcastNow`) ke kanal pemiliknya, berisi daftar notifikasi belum dibaca terbaru dan id notifikasi yang baru.
  3. Tab aplikasi yang terbuka langsung memperbarui lonceng dan panel Dashboard, lalu menampilkan pemberitahuan dalam aplikasi atau pop-up browser.
  4. Menandai dibaca (satu atau semua) juga disiarkan, sehingga tab dan perangkat lain ikut berubah.
- **Aman bila Reverb mati:** kegagalan siaran hanya dicatat di log (`rescue`). Batas waktu koneksi ke Reverb 2–3 detik (`client_options` di `config/broadcasting.php`), dan polling tetap berjalan sebagai cadangan setiap 60 detik serta saat tab kembali aktif.
- **Otorisasi kanal:** Echo dikonfigurasi di `resources/js/lib/echo.ts` dengan `channelAuthorization.customHandler` yang mengirim cookie `XSRF-TOKEN` setiap kali otorisasi. Halaman tidak memakai tag meta csrf-token, dan cara ini tetap berlaku setelah login atau Pindah Akun.
  - Bila `VITE_REVERB_APP_KEY` belum terbaca oleh Vite, Echo tidak dipasang dan aplikasi tetap memakai polling.
- **Menjalankan:**
  - `composer run dev` kini juga menjalankan `php artisan reverb:start`.
  - Di production, Reverb dijalankan sebagai proses tetap (mis. Supervisor), lalu `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` disesuaikan dengan domain HTTPS (biasanya lewat reverse proxy `wss`).
- **Asal koneksi:** `config/reverb.php` membaca `REVERB_ALLOWED_ORIGINS` (dipisah koma, bawaan `*`). Di production isi dengan domain aplikasi. Semua variabel Reverb kini tercantum di `.env.example`.
- **Panduan production:**
  1. Butuh server yang bisa menjalankan proses tetap dan reverse proxy WebSocket (VPS dengan Supervisor dan Nginx/Apache). Tanpa itu, aplikasi tetap berjalan dengan polling 60 detik.
  2. Backup database. Lalu `composer install --no-dev --optimize-autoloader`.
  3. Atur `.env` production:
     - **Server ke Reverb (jalur internal):** `BROADCAST_CONNECTION=reverb`, kunci `REVERB_APP_*` baru, `REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, `REVERB_SERVER_HOST=127.0.0.1`, `REVERB_SERVER_PORT=8080`, `REVERB_ALLOWED_ORIGINS=<domain>`.
     - **Browser ke Reverb (jalur publik):** `VITE_REVERB_HOST=<domain>`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https`.
  4. `npm ci && npm run build`, setelah `.env` diisi, karena nilai `VITE_REVERB_*` ikut dibangun ke aset.
  5. `php artisan migrate --force`, lalu `php artisan optimize`.
  6. Supervisor menjalankan `php artisan reverb:start --host=127.0.0.1 --port=8080`. Nginx meneruskan `location /app/` (dengan garis miring, agar `/approvals` tidak ikut tertangkap) ke `http://127.0.0.1:8080` dengan header `Upgrade`/`Connection "Upgrade"` (Apache: `mod_proxy_wstunnel`, `ProxyPass /app/ ws://127.0.0.1:8080/app/`). Port 8080 tidak perlu dibuka ke publik.
  7. Setiap deploy berikutnya: `php artisan reverb:restart`.
  8. Cek di DevTools → Network → WS: koneksi `wss://<domain>/app/...` berstatus 101.
- **Perbaikan ikut serta:**
  - Halaman Notifikasi sempat error `Cannot read properties of undefined (reading 'toString')` karena tab yang masih memakai kode lama menerima data format baru. Data notifikasi kini tetap menyertakan `procurement_id` untuk tab atau build lama.
  - Tombol Aktifkan Notifikasi Browser sempat menyebabkan *hydration mismatch* di Dashboard. Status izin kini dibaca lewat `useSyncExternalStore`, dengan snapshot server yang sama dengan render server.
- **Test:** `RealtimeNotificationTest` (siaran saat notifikasi baru dan saat dibaca, tidak ada siaran saat rollback, pekerjaan tetap jalan bila Reverb tak terjangkau, otorisasi kanal hanya untuk pemiliknya).

### 27. Tab DO dan Amandemen Lebih Ringkas
- **Masalah:** tab DO dan Amandemen menampilkan semuanya sekaligus: paragraf alur yang panjang, seluruh daftar harga kontrak, dan setiap permohonan sebagai kartu besar berisi semua rincian, termasuk DO/Amandemen yang sudah terbit. Pengguna sulit menemukan apa yang harus dikerjakan.
- **Susunan baru** (berlaku untuk kedua tab; data dan alur di server tidak berubah):
  - **Judul satu baris** menggantikan paragraf alur.
  - **Perlu tindakan Anda:** hanya permohonan yang menunggu tindakan pengguna (mengisi, menyetujui, Nota Dinas DO, atau menerbitkan) yang tampil sebagai kartu lengkap berbingkai biru. Kartu hanya memuat bagian milik pengguna:
    - pengusul mendapat isian dan unggahan;
    - penyetuju mendapat ringkasan, berkas, dan tombol Setujui/Kembalikan;
    - Rendan/Laksdan mendapat formnya, dengan rincian permohonan dilipat di balik tombol Detail.
  - **Sedang diproses** dan **DO terbit / Amandemen terbit:** satu baris per permohonan (nomor, tanggal, jumlah item atau perubahan pasal, total, status) dengan tombol **Preview PDF** dan **Detail**. Detail membuka tahapan, ringkasan, berkas, tombol dokumen (Preview PDF, Unduh PDF, Edit), dan riwayat persetujuan.
  - **Indikator tahapan** di setiap permohonan: DO = Pengusul › TL › Asman › Rendan › Laksdan › Terbit; Amandemen = Pengusul › TL › Asman › Laksdan › Terbit. Tahap selesai dicentang, tahap berjalan berwarna kuning (merah bila dikembalikan).
  - **Daftar Harga Satuan Kontrak** (DO) dilipat secara bawaan. Yang tampil hanya jumlah item dengan tombol Detail dan Ubah Daftar.
  - **Dokumen Permohonan** (Amandemen) hanya menampilkan berkas yang diunggah, kecuali saat pengusul mengisinya.
  - Tombol dokumen diseragamkan dengan checklist: Preview PDF, Unduh PDF, Edit.
- **Teknis:** komponen bersama `components/procurement/request-progress.tsx` (`RequestProgress`, `RequestDocumentActions`, `DetailToggle`), dipakai di `delivery-order-tab.tsx` dan `amendment-tab.tsx`.

### 28. Riwayat Status & Sisa Tahapan Menuju Terkontrak
- **Sebelumnya:** kartu Status Progres hanya menampilkan status saat ini. Status otomatis diubah diam-diam tanpa catatan, jadi tidak ada riwayat dan tidak terlihat berapa langkah lagi.
- **Riwayat status:** tabel baru `procurement_status_histories` mencatat setiap status yang dimasuki pengadaan, beserta nama status saat itu, waktu, dan pengguna yang tindakannya memicu perubahan.
  - Status otomatis dicatat di `ProgressStatusResolver::apply`. Status saat pengadaan dibuat atau diubah manual (Batal / cabut pembatalan) dicatat lewat event `saved` pada model.
  - **Pengadaan lama** disusun ulang riwayatnya oleh migrasi `create_procurement_status_histories_table` dari jejak aktivitas: usulan diajukan/disetujui/dikembalikan, perencanaan diajukan/disetujui/ditolak, pelaksanaan diajukan/disetujui/ditolak, diserahkan ke Keuangan, pembayaran, dan pembatalan. Riwayat ditutup dengan status saat ini.
  - Tahap yang tidak meninggalkan jejak aktivitas (mis. Penyusunan RKS) tetap tampil di urutan sebagai selesai, tetapi tanpa tanggal.
- **Urutan tahapan sesuai format** (`ProgressStatusResolver::path` dan `journey`):
  - **SPK/PJ:** (bila berawal dari usulan: Penyusunan TOR › Draft Nodis Usulan › Approval Usulan ›) Inisiasi Rendan › Penyusunan RKS › Menunggu Penawaran › Draft Nodis ke Pengadaan › Menunggu Approval ICC › Inisiasi Laksdan › Penyusunan HPS › Proses SMART SCM › Penyusunan Kontrak › Menunggu Approval TL Pengadaan › **Terkontrak** › Selesai.
  - **SPPL:** (usulan ›) Inisiasi Rendan › Draft Nota Dinas ke Laksdan › Menunggu Approval ICC › Inisiasi Laksdan › Penyusunan Surat Pesanan › Menunggu Approval TL Pengadaan › **Terkontrak** › Selesai.
  - Status Revisi Usulan/Perencanaan/Pelaksanaan menempati posisi tahap persetujuannya. Status yang dinonaktifkan di Data Master tidak dihitung.
- **Kartu Status Progres** kini menampilkan:
  - "Tahap X dari Y" dengan bilah kemajuan, dan **"N status lagi menuju Terkontrak"** (atau "Sudah terkontrak");
  - daftar tahapan: tahap selesai dicentang biru dengan tanggal masuk, tahap sekarang ditandai kuning "· sekarang", sisanya abu-abu;
  - tombol **Riwayat status (n)** yang membuka daftar perubahan status (terbaru di atas) dengan waktu dan pelakunya.
- **Test:** `StatusJourneyTest`.

### 29. Header TOR: Nomor Dokumen Kosong, Tanggal Terbit Sesuai Tanggal Generate
- Pada kop TOR (`umum/tor.html`), **Nomor Dokumen / Pengadaan** sekarang dikosongkan untuk diisi manual. Sebelumnya terisi nomor pengadaan.
- Baris **Halaman** juga dikosongkan. Sebelumnya tertulis "1 dari {status progres}", yang keliru.
- **Tanggal Terbit** tetap tanggal TOR digenerate (`{{tanggal_dokumen}}`, dibekukan).
- Migrasi `clear_the_document_number_of_the_tor_header` memperbarui template terpasang, juga TOR yang sudah ada (kecuali yang sudah punya scan bertanda tangan): nomor dan halaman dikosongkan, Tanggal Terbit diset ke tanggal generate-nya. Sebelumnya ada TOR yang menampilkan tanggal render ulang, bukan tanggal generate.
- **Test:** `TorHeaderTest`.

### 30. Jenis Kontrak di Form, Kategori oleh PIC Perencana, Nomor Kontrak oleh PIC Pelaksana
- **Form Buat/Ubah Pengadaan:**
  - Label "Jenis No Kontrak" menjadi **Jenis Kontrak** (pilihan SPK / PJ / SPPL tetap di sini, karena menentukan tahapan Perencanaan).
  - Kolom **No Kontrak** dan pilihan **Kategori** dihapus dari form.
- **Kategori Kontrak** (Barang, Jasa, KHS, Barang dan Jasa) dipilih **PIC Perencana** pada panel Identitas Pengadaan di detail pengadaan, selama perencanaan belum disetujui.
  - "Barang dan Jasa" ditambahkan ke Data Master → Jenis Kontrak (migrasi `add_barang_dan_jasa_contract_type`) dan mencetak format SPK/PJ umum.
  - Lumsum tetap ada di data master dan dapat dinonaktifkan bila tidak dipakai.
  - Untuk usulan dengan format SPK/PJ, dokumen perencanaan menunggu Jenis Kontrak dan Kategori terisi.
- **Pengadaan baru** terdaftar dengan nomor registrasi internal: `PGD/tahun/bulan/urut`, atau `USL/...` untuk usulan.
- **Checklist Pelaksanaan:** isian baru **Nomor kontrak** (`ChecklistInput::ContractNumber`), diisi PIC Pelaksana.
  - Dipasang pada tahapan **Kontrak** untuk SPK/PJ dan **Surat Pesanan** untuk SPPL (sebelumnya di Lampiran Surat Pesanan), sehingga tiap format cukup sekali (migrasi `ask_for_the_contract_number_in_execution`, `move_sppl_contract_number_input_to_surat_pesanan`, dan `MasterDataSeeder`). Lihat bagian *Nomor Kontrak SPPL Pindah ke Langkah Surat Pesanan*.
  - Isian menawarkan nomor otomatis berikutnya sesuai format (tombol "Pakai nomor otomatis").
  - Nomor wajib unik dan tidak boleh berupa nomor registrasi PGD/USL.
  - Tahapan belum bisa dicentang sebelum nomor terisi. Dokumen yang sudah digenerate ikut memakai nomor baru secara otomatis.
- **Test:** `ContractNumberInputTest`; `ContractNumberTest`, `WorkCategoryTest`, `PlanningIdentityTest`, `MethodFollowsFormatTest`, dan `EndToEndFlowTest` disesuaikan.

### 31. Pembagian Isian: User, Rendan, Laksdan
- **User** (pembuat pengadaan atau pengusul) memilih **Jenis Kontrak** (SPK / PJ / SPPL) di form Buat Pengadaan, kini juga di form Usulan Pekerjaan.
  - Di server tetap opsional, sehingga alur lama tidak rusak. Perencanaan menunggu isian ini lewat peringatan data kurang.
- **Rendan** (PIC Perencana) memilih **Metode Pengadaan** dan **Kategori Kontrak** di panel Identitas Pengadaan, sebelum perencanaan disetujui.
  - Pilihan metode: Penunjukan Langsung, Pengadaan Langsung, Lelang Terbuka 1 Tahap 1 Sampul, Lelang Terbuka 1 Tahap 2 Sampul.
  - Setiap perubahan tercatat di riwayat aktivitas.
- **Laksdan** (PIC Pelaksana) mengisi **No Kontrak** di checklist Pelaksanaan (poin 30).
- **Data Master → Metode Pengadaan** baru: tabel `selection_methods`, kolom `procurements.selection_method_id` (migrasi `create_selection_methods_table`), dapat ditambah/diubah/dinonaktifkan.
  - Placeholder `{{metode_pengadaan}}` mencetak metode ini. Bila belum dipilih, yang dicetak metode internal.
- Metode lama (Surat Pesanan / SPK / Tender) tetap dipakai sistem untuk memilih template dan tahapan sesuai format. Menunya diganti nama menjadi **Data Master → Metode Internal (Template)**.
- **Test:** `SelectionMethodTest`; batas jumlah query di `MethodSpecificTemplateTest` dinaikkan satu karena ada daftar metode pada opsi detail pengadaan.

### 32. Empat Penanda Tangan Lembar Pengesahan TOR
- Lembar Pengesahan TOR (`umum/tor.html`) kini ditandatangani empat pihak, dan namanya terisi otomatis dari data:
  1. **Disusun oleh: User**, yaitu pembuat usulan/pengadaan (`{{nama_penyusun}}`).
  2. **Diperiksa/Diketahui oleh**, kiri: **Asman bidang user** sesuai Direksi Pekerjaan pengadaan (jabatan `{{direksi_pekerjaan}}`, nama `{{nama_asman_pengguna}}`).
  3. **Diperiksa/Diketahui oleh**, kanan: **Asman Engineering** (`{{nama_asman_engineering}}`). Diambil dari Direksi Pekerjaan bernama "...Engineering" dan akun yang berhak menyetujui usulan bidang itu.
  4. **Disetujui oleh: Manager** (`{{nama_manager}}`), seperti sebelumnya.
- Bila akun Asman belum ada, nama diganti garis titik untuk diisi manual. Semua nama dicetak huruf besar.
- **SPPL hanya tiga penanda tangan:** Asman bidang user di kiri, user di kanan, dan Manager di bawahnya. Asman Engineering tidak ikut.
- Template TOR tetap satu. Blok tanda tangan disusun sistem sesuai format lewat placeholder `{{pengesahan_tor}}` (migrasi `sign_the_sppl_tor_by_three`).
- Migrasi `sign_the_tor_by_the_user_and_both_asman` memperbarui template terpasang. TOR yang belum diedit ikut menyesuaikan saat dibuka; TOR yang sudah diedit tetap seperti isinya.
- **Test:** `TorHeaderTest`.

### 33. Margin Halaman TOR di Editor Template
- Di Data Master → Template Dokumen, halaman TOR sebelumnya menempel ke tepi tanpa margin. TOR adalah satu-satunya template berbentuk dokumen HTML lengkap, dan marginnya hanya diatur `@page` (15 mm) yang berlaku di PDF.
- Editor (`resources/js/lib/template-document.ts`) kini menampilkan TOR di lembar A4 dengan margin 15 mm, sama dengan PDF. Pratinjau HTML-nya juga diberi margin yang sama (khusus layar).
- Pratinjau template punya contoh nilai untuk `{{pengesahan_tor}}` dan nama-nama penanda tangan.

### 34. Level Risiko CSMS oleh User
- Form Buat Pengadaan / Usulan Pekerjaan punya isian **Level Risiko CSMS**: Low, Moderat, High, Extreme (enum `CsmsRiskLevel`, kolom `procurements.csms_risk_level`).
- Ditampilkan di panel Identitas Pengadaan dan dicetak lewat placeholder `{{level_risiko_csms}}`.
- **Test:** `CsmsRiskLevelTest`.

### 35. TOR: Kerangka Poin, Kop Formulir, dan Tanggal Terbit dari User
- **Isi TOR hanya poin dan sub-poin.** Narasi diketik manual di editor dokumen, di bawah setiap poin ada baris kosong untuk mulai mengetik. Penomoran juga dirapikan: sebelumnya ada dua poin 9 dan sub-poin 10.x di bawah poin 9.
  - Poin: 1 Pendahuluan (1.1 Latar Belakang, 1.2 Maksud dan Tujuan dengan 1.2.1 Maksud dan 1.2.2 Tujuan, 1.3 Lokasi, 1.4 Nama Pengguna Barang/Jasa); 2 Pedoman Acuan Teknis (2.1 Standart Nasional & Internasional Terkait); 3 Lingkup Pekerjaan; 4 Performance Design; 5 Kualifikasi Calon Pelaksana; 6 Detail Uraian Pekerjaan; 7 Kelengkapan Pelaksanaan; 8 Aspek K3 dan Keamanan (8.1 Identifikasi Bahaya dan Risiko Kerja); 9 Laporan Hasil Pekerjaan (9.1 Laporan Hasil Pekerjaan, 9.2 Dokumen Penagihan); 10 Barang Sisa & Limbah; 11 Quality Acceptance; 12 Waktu Penyelesaian; 13 Garansi; 14 Lain-lain.
  - Sampul, identitas program, dan Lembar Pengesahan tidak berubah.
- **Kop mengikuti formulir resmi:** logo | PT PLN NUSANTARA POWER / INTEGRATED MANAGEMENT SYSTEM / TERM OF REFERENCE (TOR) | Nomor Dokumen, Revisi (00), Tanggal Terbit, Halaman.
  - **Nomor Dokumen** tetap sama di setiap TOR: `FMKP – 167 – 9.1.1.2.e.a.c - 001` (nomor formulir, statis). Migrasi `put_the_form_number_on_the_tor_kop` mengisinya di template dan TOR lama yang belum bertanda tangan.
  - **Halaman** dikosongkan untuk diisi manual.
- **Tanggal Terbit TOR** diisi user di form Buat Pengadaan / Usulan Pekerjaan (kolom `procurements.tor_issue_date`).
  - Dicetak di kop lewat `{{tanggal_terbit}}` dengan format `06 - 02 - 2023`.
  - Bila kosong, yang dicetak tanggal TOR digenerate.
- **TOR yang sudah ada** (migrasi `outline_the_tor_and_use_the_form_kop`):
  - TOR yang belum diedit dibangun ulang dari template baru.
  - TOR yang sudah diedit tetap berisi teks yang diketik, hanya kop-nya yang diganti.
  - TOR yang sudah punya scan bertanda tangan tidak diubah.
- **Test:** `TorHeaderTest` (kerangka poin, kop, tanggal terbit dari user, perbaikan TOR lama). `SignatoryNamesTest` disesuaikan karena narasi TOR dihapus.

### 36. Template TOR Dipaksa Kerangka Poin & Command Perbaikan TOR
- **Template TOR bawaan selalu dipasang**, meskipun sebelumnya diubah admin. Ini berlaku untuk seeder (`DocumentTemplateLibrarySeeder`, properti `$forced = ['umum.tor']` di `LibraryTemplateSeeder`) dan migrasi TOR (`sync(..., force: true)`).
  - Sebelumnya, template yang pernah disesuaikan admin dilewati, sehingga narasi lama bisa tetap tampil di production.
- **Command `php artisan tor:perbaiki`** memasang template TOR bawaan lalu memperbaiki TOR yang sudah dibuat:
  - TOR yang belum diedit dibangun ulang menjadi kerangka poin. Tanggal generate-nya tetap.
  - TOR yang sudah diketik hanya diganti kop-nya; teks yang diketik tetap ada.
  - TOR bertanda tangan dilewati.
  - `--termasuk-ttd`: TOR bertanda tangan ikut dibangun ulang. Scan-nya tidak berubah.
  - `--timpa-isian`: TOR yang sudah diketik juga dibangun ulang menjadi kerangka poin, dan isiannya hilang.
  - `--dry-run`: hanya menampilkan jumlah, tanpa mengubah apa pun.
- Pendukung: `DocumentGenerator::rebuild()` membangun ulang satu dokumen dari template yang berlaku sambil menjaga tanggal generate. `App\Support\TorKop` berisi kop formulir TOR.
- **Test:** `RepairTorDocumentsTest`.

### 37. Label "Penyusun" pada Lembar Pengesahan TOR
- Di Lembar Pengesahan TOR, tulisan "User" di atas nama pembuat usulan/pengadaan diganti **"Penyusun"**. Berlaku untuk SPK/PJ (di bawah "Disusun oleh:") dan SPPL (kolom kanan).
- TOR yang belum diedit ikut berubah saat dibuka. Untuk TOR yang sudah diedit, `php artisan tor:perbaiki` mengganti "User" menjadi "Penyusun" bersama kop-nya, tanpa menyentuh isian lain.
- `tor:perbaiki` mengenali label "User" meskipun selnya sudah diubah editor (ada `style`, atau dibungkus `<span>`/`<b>`). Command ini juga:
  - menampilkan semua template TOR aktif, dengan opsi `--nonaktifkan-template-lain` untuk mematikan template TOR selain bawaan;
  - menyediakan `--detail` untuk melihat tiap TOR: bertanda tangan, diedit, tindakan, dan apakah masih "User".
- TOR yang sudah punya scan bertanda tangan hanya diperbaiki dengan `--termasuk-ttd`.

### 38. Semua Tanggal TOR = Tanggal Terbit TOR, Lokasi Kendari
- Semua tanggal di TOR memakai **Tanggal Terbit TOR** yang diisi user, atau tanggal generate bila kosong:
  - TANGGAL di sampul, Tanggal di identitas program, dan tanggal di Lembar Pengesahan memakai `{{tanggal_terbit_tor}}` (contoh: 06 Februari 2023);
  - tahun di pita sampul memakai `{{tahun_terbit_tor}}`;
  - Tanggal Terbit di kop memakai `{{tanggal_terbit}}` (06 - 02 - 2023).
- Lokasi di Lembar Pengesahan statis **"Kendari, <tanggal>"**, tidak lagi menyebut unit tujuan.
- Template diperbarui paksa oleh migrasi `date_the_tor_by_its_issue_date_in_kendari`. Untuk TOR yang sudah diedit, `tor:perbaiki` menyesuaikan tanggal sampul, tanggal identitas, dan baris "Kendari, tanggal"; isian lain tetap.
- **Test:** `TorHeaderTest`, `RepairTorDocumentsTest`, `TorDocumentTemplateTest`.

### 39. Total Tabel Berita Acara Negosiasi
- **SPPL** (tabel "Harga Sebelum/Setelah Nego"): rumus sudah benar. TOTAL HARGA = Σ (Qty × Harga Satuan), sebelum dan sesudah nego, dihitung dari Rincian Item.
- **SPK/PJ** (lampiran "Rincian Hasil Negosiasi Harga"): sebelumnya tabel ini tidak dihitung sama sekali; Qty, harga, Sub Total, PPN, dan Total hanya titik-titik. Sekarang tabelnya digambar dari Rincian Item (`ItemTableRenderer::negoBreakdown`):
  - Jumlah per baris = Qty × Harga Negosiasi;
  - Sub Total = Σ Jumlah;
  - PPN = 12% × Sub Total;
  - Total Harga Akhir = Sub Total + PPN, beserta terbilangnya.
- `ItemTableRenderer::VERSION` naik ke 3, sehingga BA Negosiasi yang belum diedit digambar ulang saat dibuka.
- **Test:** `BaNegosiasiTotalTest`.

### 40. Lembar Pengesahan TOR: Huruf Besar, Tanpa Jarak Judul–Jabatan
- Baris kosong (`small-gap`) antara judul ("DIPERIKSA/DIKETAHUI OLEH:", "DISUSUN OLEH:", "DISETUJUI OLEH:") dan jabatan dihapus. Jarak yang tersisa hanya ruang tanda tangan di atas nama.
- Semua teks pada form tanda tangan ditulis huruf besar (judul, jabatan seperti ASMAN OPERASI / PENYUSUN / ASMAN ENGINEERING / MANAGER, dan nama). Huruf besar ditulis langsung, bukan lewat CSS, sehingga tetap bertahan saat diedit atau diunduh.
- `tor:perbaiki` kini menggambar ulang seluruh Lembar Pengesahan pada TOR yang sudah diedit (`DocumentGenerator::torApprovalPage`): judul, "Kendari, tanggal terbit", dan tanda tangan. Isian poin-poin tetap.
- **Test:** `TorHeaderTest`, `TorSignatureTest`, `RepairTorDocumentsTest`.

### 41. Baris Kelompok Tabel BA Negosiasi SPPL Di-merge
- Baris kelompok ("A | Material & Tools Tangki 50 KL") kini berisi huruf di kolom NO dan nama kelompok yang di-merge (`colspan="7"`) sampai kolom terakhir. Sebelumnya diikuti enam sel kosong.
- Parser BA Negosiasi (`parseNegotiationItems`) mengenali baris kelompok yang di-merge, sehingga kelompok tidak hilang saat dokumen disimpan dari editor.
- Dokumen yang sudah diedit kini ikut menggambar ulang tabel itemnya dari Rincian Item saat dibuka (teks lain tetap). `ItemTableRenderer::VERSION` naik ke 4, sehingga BA Negosiasi yang sudah ada langsung memakai tampilan baru. Dokumen bertanda tangan tidak diubah.
- **Test:** `BaNegosiasiTotalTest`.

### 42. Template Berita Acara Tahap "Proses SMART SCM & Berita Acara" Mengikuti Formulir Unit
- Enam template diganti sesuai formulir UP Kendari. Semuanya memakai logo `/logo/sidebar-logo.png`, kop "PT PLN Nusantara Power / UP KENDARI", dan nama penanda tangan huruf besar.
  1. **BA Pemberian Penjelasan (Aanwijzing)**: Nomor …/BAP/612/UPKD, bagian I–V (Penjelasan, Pertanyaan & Jawaban, Perubahan/Koreksi, Kesimpulan, Penutup). Tanda tangan: peserta (calon mitra) di kiri; UP Kendari di kanan (TL Pengadaan – Team Leader Pengadaan, PIC Pelaksana – Junior Officer Pengadaan).
  2. **Lampiran BAPP – Catatan Hasil Pembukaan Penawaran**: tabel kelengkapan (Dokumen Teknis a–l, Dokumen Kewajaran Harga, Kesimpulan Akhir, Paraf Peserta) dengan **satu kolom per calon mitra** (A/L), plus keterangan A/TA dan L/TL.
  3. **BA Evaluasi Teknis (Lampiran 3)**: Evaluasi Kualifikasi dan Pembuktian Kualifikasi (Teknis), dokumen a–l per calon mitra (M), Kesimpulan MEMENUHI, dan User Peminta.
  4. **BA Evaluasi Harga (Lampiran 4)**: Hasil Evaluasi Penawaran (Surat Penawaran Harga M, Kesimpulan LULUS, **Urutan Harga Penawaran** dari harga penawaran termurah).
  5. **BA Evaluasi Administrasi, Teknik dan Harga** (Hasil Evaluasi): pemasukan penawaran dan harganya, evaluasi administrasi/keuangan/teknis/penawaran, **urutan harga terkoreksi beserta % terhadap HPS**, HPS (+PPN 12%), keputusan; ditandatangani TL Pengadaan, TL ICC, PIC Perencana, dan PIC Pelaksana.
  6. **BA Klarifikasi Teknik**: Nomor …/BAK/612/UPKD, tabel peserta dengan nomor surat penawaran, butir 1–4, dan tanda tangan peserta serta UP Kendari.
- Data diisi otomatis dari calon mitra (nama, nomor surat penawaran, nilai penawaran), pejabat (TL Pengadaan = peninjau pelaksanaan atau TL Pengadaan aktif; TL ICC = peninjau perencanaan atau TL ICC aktif), PIC, nomor dan tanggal RKS, metode pengadaan (bawaan "Pengadaan Langsung"), serta hari/tanggal/bulan/tahun yang dieja dari tanggal dokumen (`App\Support\TenderDocuments`).
  - Placeholder baru: `{{hari_dokumen}}`, `{{tanggal_dokumen_terbilang}}`, `{{bulan_dokumen}}`, `{{tahun_dokumen_terbilang}}`, `{{tanggal_rks}}`, `{{metode_pemilihan}}`, `{{nama_pejabat_pelaksana}}`, `{{nama_pejabat_perencana}}`, `{{jumlah_peserta}}`, `{{daftar_peserta}}`, `{{daftar_peserta_ttd}}`, `{{tabel_peserta_penawaran}}`, `{{tabel_pembukaan_penawaran}}`, `{{tabel_evaluasi_teknis}}`, `{{tabel_evaluasi_harga}}`, `{{tabel_pemasukan_penawaran}}`, `{{tabel_hasil_administrasi}}`, `{{tabel_hasil_keuangan}}`, `{{tabel_hasil_teknis}}`, `{{tabel_hasil_penawaran}}`, `{{tabel_urutan_harga}}`.
  - Tanda evaluasi (A, L, M, LULUS, MEMENUHI) diisi sebagai hasil umum dan dapat dikoreksi di editor dokumen. Tanpa calon mitra, tabel menyediakan tiga baris kosong.
- Migrasi `put_the_tender_berita_acara_templates_in_place` mengganti keenam template terpasang (paksa). Dokumen yang belum diedit mengikuti saat dibuka; yang sudah diedit tetap, dan dapat diganti dengan "Muat Ulang dari Template".
- Semua template bawaan dan template aktif di database sudah memakai logo `sidebar-logo.png`.
- **Test:** `TenderBeritaAcaraTest`; `BeritaAcaraDocumentTest` disesuaikan dengan judul baru.
- **Test:** `TorHeaderTest`, `RepairTorDocumentsTest`.

### 43. Data Master Manager Unit: Pejabat Unit Layanan
- 27 pejabat Unit Layanan ditambahkan ke Data Master → Manager Unit lewat seeder `UnitLayananManagerSeeder` (juga dipanggil `MasterDataSeeder`). Nama di kolom Nama, jabatan di kolom Jabatan, dan unit di kolom Keterangan:
  - **ULPLTD WUA WUA** (5): Manager UL, TL Pemeliharaan, TL Operasi, TL OPHAR PLTD Langara, TL K3L.
  - **ULPLTD POASIA** (4): Manager UL, TL Pemeliharaan, TL Operasi, TL K3L.
  - **ULPLTDG KOLAKA** (8): Manager UL, TL Pemeliharaan, TL Operasi, TL K3L, TL OPHAR PLTM Sabilambo, PLTD Lanipa Nipa, PLTM Mikuasi, PLTG Kolaka.
  - **ULPLTD BAU BAU** (10): Manager UL, TL Operasi, TL Pemeliharaan, TL K3L, TL OPHAR PLTD Raha, PLTD Ereke, PLTD Wangi Wangi, PLTM Winning, PLTM Rongi, PLTD Pasarwajo.
- Urutannya ditaruh setelah data yang sudah ada (`max(sort_order) + 1` dst.), sehingga Manager UP Kendari yang menandatangani dokumen (`{{nama_manager}}`, yaitu manager aktif dengan urutan terkecil) tidak berubah.
- Hanya baris yang belum ada yang ditambahkan (dicocokkan dengan nama, termasuk yang sudah dihapus). Perubahan atau penghapusan oleh administrator tetap dipertahankan saat seeder dijalankan ulang.
- Ejaan yang dirapikan dari daftar: PPOASIA → POASIA, WIINNING → WINNING, PLM RONGI → PLTM RONGI.
- **Menjalankan:** `php artisan db:seed --class=UnitLayananManagerSeeder --force`.
- **Test:** `UnitLayananManagerSeederTest`.

### 44. Jabatan Manager Unit Dipilih dari Dropdown Bertingkat
- Isian Jabatan pada Tambah/Ubah Manager kini dropdown (`App\Enums\UnitManagerLevel`), urut dari yang tertinggi:
  1. **MANAGER**: jabatan tertinggi; tanpa unit.
  2. **MANAGER UL**: memegang beberapa unit (pilih banyak).
  3. **TL**: satu bidang di beberapa unit. Pilih **Bidang** (`App\Enums\TeamLeaderField`: Pemeliharaan, Operasi, K3L) dan beberapa unit.
  4. **TL OPHAR**: memegang 1 unit (pilih satu).
- Unit diambil dari Data Master **Unit Tujuan** (tabel pivot `target_unit_unit_manager`, berurutan sesuai pilihan). Isian Bidang/Unit hanya tampil bila jabatannya membutuhkan (`visible` pada `MasterField`).
- Kolom `position` kini ditulis otomatis dari pilihan: "MANAGER", "MANAGER UL", "TL PEMELIHARAAN", "TL OPHAR PLTD LANGARA" (nama unit dari Unit Tujuan). Tabel menampilkan kolom **Unit**.
- Validasi: TL wajib bidang dan minimal 1 unit; Manager UL wajib minimal 1 unit; TL OPHAR wajib tepat 1 unit. Pilihan yang tertinggal dari jabatan lain diabaikan, dan Manager tidak memegang unit.
- `{{nama_manager}}` di dokumen hanya mengambil pejabat berjabatan **MANAGER** (aktif, urutan terkecil), sehingga Manager UL/TL tidak pernah tercetak sebagai Manager UP Kendari.
- Migrasi `arrange_unit_managers_by_level` menambah kolom `level` dan `field`, membaca jabatan lama menjadi tingkat dan bidang, lalu menautkan unit pejabat Unit Layanan (`UnitLayananManagerSeeder::arrange()`):
  - **WUA WUA**: PLTD Wua Wua, PLTD Langara.
  - **POASIA**: PLTD Poasia, PLTD Poasia Containerized.
  - **KOLAKA**: PLTD Kolaka, PLTD Lanipa, PLTM Sabilambo, PLTM Mikuasi.
  - **BAU BAU**: PLTD Bau Bau, Raha, Ereke, Wangi Wangi, Winning, PLTM Rongi, PLTD Pasar Wajo.
  - TL OPHAR ditautkan ke unitnya, dan jabatannya mengikuti nama di Unit Tujuan (mis. TL OPHAR PLTD WINNING, TL OPHAR PLTD LANIPA, TL OPHAR PLTD PASAR WAJO).
  - **PLTG Kolaka** belum ada di Unit Tujuan, sehingga RIZAL JHON belum punya unit.
  - Pejabat yang sudah punya unit atau tingkatnya sudah diubah administrator tidak disentuh.
- **Test:** `UnitManagerLevelTest`, `UnitLayananManagerSeederTest`; `MasterDataManagementTest` disesuaikan.

### 45. Template Surat Mitra di Keuangan: Unduh (Word), lalu Unggah
- Langkah Keuangan yang dibuktikan dengan unggahan kini menawarkan tombol **Unduh Template (Word)** bila langkahnya adalah salah satu surat mitra. Surat tidak digenerate: mitra memasang kop perusahaannya, melengkapi, menandatangani, lalu berkasnya diunggah kembali di langkah yang sama (`App\Support\FinanceLetters`, rute `procurements.checklists.template`).
- Langkah dikenali dari namanya, termasuk langkah yang dibaca dari kontrak:

| Surat (jenis dokumen) | Langkah yang menawarkan |
|---|---|
| Surat Jalan (`surat-jalan`) | nama memuat "Surat Jalan" |
| Surat Pengantar Penyerahan Pekerjaan (`surat-pengantar-penyerahan`) | "Surat (Pengantar) Penyerahan Pekerjaan" |
| Berita Acara Serah Terima Pekerjaan / BASTP (`berita-acara-serah-terima`) | "Berita Acara Serah Terima" / "Serah Terima Pekerjaan", termasuk langkah yang mengunggah dokumen upload-only (BAST) |
| BA Pemeriksaan / Penyelesaian Pekerjaan (`berita-acara-pemeriksaan`) | "Berita Acara Pemeriksaan …" / "Berita Acara Penyelesaian …" |
| Surat Pernyataan Garansi Material (`surat-pernyataan-garansi`) | "Surat (Pernyataan) Garansi …", kecuali "Garansi Pekerjaan" |
| Surat Permohonan Pembayaran (`surat-permohonan-pembayaran`) | "Permohonan Pembayaran" |

- **Isi otomatis:**
  - **Kop:** kotak bertuliskan "[ KOP SURAT PERUSAHAAN ]".
  - **Nama direktur:** tertulis "( NAMA DIREKTUR )".
  - **Kontrak:** jenis kontrak (Surat Perintah Kerja (SPK) / Surat Perjanjian / Surat Pesanan), nomor kontrak, dan tanggal kontrak (tanggal dokumen kontrak digenerate).
  - **Data pengadaan:** nama pekerjaan (huruf besar), nilai termasuk PPN beserta terbilangnya, rekening, dan masa garansi.
  - **Surat Jalan:** tabel barang dari Rincian Item (5 baris kosong bila belum ada).
  - **Penerima (Data Master Manager Unit):** unit tujuan; "Up / Mengetahui" = Manager UL yang memegang unit itu; penerima = TL OPHAR unit itu, atau TL Pemeliharaan Unit Layanannya.
  - **Judul BA:** mengikuti langkahnya (Pemeriksaan Barang / Pemeriksaan Pekerjaan / Penyelesaian Pekerjaan).
  - **BASTP:** PIHAK PERTAMA = Manager UP Kendari (`{{nama_manager}}`), PIHAK KEDUA = Direktur mitra; kolom materai 10.000 di sisi pelaksana.
  - **Data yang belum ada:** tercetak garis titik-titik.
- Placeholder baru: `{{unit_penerima}}`, `{{up_penerima}}`, `{{nama_penerima}}`, `{{nama_manager_penerima}}`, `{{tabel_surat_jalan}}`, `{{judul_berita_acara}}`, `{{nama_berita_acara}}`; `{{nomor_kontrak}}`, `{{tanggal_kontrak}}`, `{{jenis_dokumen_kontrak_judul}}` kini juga terisi pada surat mitra.
- Template tersimpan di Data Master → Template Dokumen (grup `keuangan`, file `resources/document-templates/keuangan/*.html`) dan dapat diubah administrator. Kelima jenis dokumen tidak ditawarkan di panel Generate dan ditolak oleh permintaan generate.
- File Word dibuat dari HTML (`App\Support\WordDocument`): tampilan cetak A4, gaya template dipindah ke head, dan live field ditulis sebagai teks biasa.
- Tombol juga muncul pada langkah yang mengunggah dokumen upload-only (mis. BAST), di atas daftar dokumennya; langkah dengan dokumen yang digenerate tidak ditawari.
- **Migrasi:** `add_the_partner_letters_for_keuangan` (jenis dokumen + template) dan `add_the_handover_report_for_keuangan` (BASTP). **Test:** `FinanceLettersTest`.

### 46. TOR Dua Versi Tanda Tangan: SPPL Tiga Orang, SPK/PJ Empat Orang (Dipastikan)
- Satu template TOR (`umum.tor`); halaman Lembar Pengesahan (`{{pengesahan_tor}}`) mengikuti format kontrak:
  - **SPPL (3):** Diperiksa/Diketahui Asman bidang user (kiri), Disusun Penyusun/user (kanan), lalu Disetujui Manager di bawah.
  - **SPK dan PJ (4):** Disusun Penyusun/user, lalu Diperiksa/Diketahui Asman bidang user (kiri) dan Asman Engineering (kanan), lalu Disetujui Manager.
- **Perbaikan:** TOR yang narasinya sudah diketik (revisi > 0) kini juga menggambar ulang halaman Lembar Pengesahan dari data saat dibuka atau saat data pengadaan berubah. Bila Jenis Kontrak diganti (mis. SPK menjadi SPPL), jumlah penanda tangannya ikut berubah, dan narasi yang diketik tetap utuh (`DocumentGenerator::redrawTorApproval`). TOR yang sudah memiliki scan bertanda tangan tidak berubah.
- **Test:** `TorHeaderTest` (`test_an_spk_or_pj_tor_is_signed_by_four_and_an_sppl_tor_by_three`, `test_a_typed_tor_follows_a_change_of_contract_format`).

### 47. Editor Dokumen: Tab Seperti Word dan Toolbar Lebih Lengkap
- **Tab tidak lagi memindahkan fokus atau halaman** (sebelumnya browser memindahkan fokus ke elemen berikutnya, sehingga halaman melompat). Di editor dokumen (`visual-editor.tsx`), termasuk TOR:
  - Di sel tabel: Tab / Shift+Tab pindah ke sel berikut / sebelumnya, seperti sebelumnya.
  - Di butir daftar: Tab menjorokkan butir, Shift+Tab mengembalikannya.
  - Di teks biasa: Tab menyisipkan jarak satu tab (spasi tak terputus, sehingga tetap terlihat di PDF); Shift+Tab mengurangi indentasi.
- **Toolbar ditambah:** coret, superskrip, subskrip, ukuran huruf (8–24 pt), warna huruf, stabilo, hapus format, rata kiri / tengah / kanan / kiri-kanan, serta tambah / kurangi indentasi.
  - Ukuran dan warna ditulis sebagai style inline agar terbaca PDF.
  - Pilihan teks diingat, sehingga pemilih warna tetap mewarnai teks yang dipilih.

### 48. Isian Nomor RKS, HPE, dan UPB di Checklist Perencanaan
- Langkah **RKS**, **HPE**, dan **UPB** pada checklist perencanaan kini masing-masing punya isian **Nomor RKS / Nomor HPE / Nomor UPB** (jenis isian baru `rks_number`, `hpe_number`, `upb_number` pada `ChecklistInput`; kolom `procurements.rks_number`, `hpe_number`, `upb_number`).
- Sama seperti isian lain: langkah baru bisa dicentang dan perencanaan baru bisa diajukan setelah nomornya diisi. Langkah yang sudah tercentang tetapi nomornya kosong dihitung belum selesai.
- **Dokumen:**
  - `{{nomor_rks}}` memakai nomor yang diisi; bila kosong, tetap diturunkan dari No Kontrak seperti sebelumnya.
  - Placeholder baru `{{nomor_hpe}}` (bila kosong: nomor nota dinas usulan + "-HPE(A)") dan `{{nomor_upb}}` (bila kosong: nomor nota dinas usulan). Template HPE dan UPB kini memakainya pada baris Nomor.
- **Migrasi:** `add_document_numbers_to_planning_steps` (kolom, pemasangan isian pada ketiga langkah bila belum diberi isian lain oleh administrator, dan sinkron template HPE/UPB). **Test:** `PlanningDocumentNumbersTest`.

### 49. Monitoring dalam Folder per Mitra, Monitoring Kontrak, dan Filter Tahun/Bulan
- **Monitoring Pengadaan, Amandemen, dan Delivery Order** kini dibuka sebagai **folder per mitra** (nama mitra disamakan tanpa beda huruf besar/kecil dan spasi; mitra yang paling baru aktif di depan; folder **Belum Ada Mitra** di akhir). Tampilannya sama dengan folder Penilaian Penyedia.
  - **Isi folder:**
    - **Pengadaan:** jumlah pekerjaan dan berapa yang selesai.
    - **Amandemen / DO:** jumlah amandemen/DO, jumlah pekerjaan, dan berapa yang terbit.
  - **Klik folder** (`?mitra=`): Monitoring Pengadaan menampilkan daftar pekerjaan mitra itu (tabel yang sama seperti sebelumnya). Amandemen dan DO menampilkan tabel yang **dikelompokkan per pekerjaan** (baris judul No/nama pekerjaan, lalu Amandemen I, II, ... / DO ke-1, 2, ...).
  - **Filter tetap berlaku:** kartu status, pencarian, dan filter tetap ada, baik di tampilan folder maupun di dalam folder. Tombol **Semua Mitra** kembali ke folder dengan filter yang sama.
- **Monitoring Kontrak** (baru, menu Monitoring, `monitoring/kontrak`, hak `menu.monitoring`): folder per **mitra yang sedang terkontrak**, yaitu pengadaan yang sudah bernomor kontrak (bukan PGD/USL), punya mitra, dan status progresnya bukan Selesai/Batal.
  - Isi folder: No Kontrak, pekerjaan, direksi, jenis (SPK/PJ/SPPL), nilai, masa pelaksanaan beserta sisa/lewat hari, dan status.
  - Filter: cari, jenis kontrak, direksi pekerjaan, tahun/bulan.
- **Filter Tahun dan Bulan** di seluruh halaman daftar (`App\Support\FilterPeriod`, komponen `PeriodFilter`):
  - Daftar Pengadaan, Perencanaan, Pelaksanaan, Penunjukan PIC, Approval, Laporan, Monitoring Pengadaan, dan Monitoring Kontrak: tanggal pengadaan didaftarkan (`created_at`), lewat filter bersama `ProcurementFilters` / `ProcurementFilterBar`.
  - Monitoring Amandemen / DO: tanggal amandemen/DO dibuat.
  - Arsip Dokumen: tanggal dokumen digenerate.
  - Penilaian Penyedia: tanggal formulir dibuat.
  - Pilihan tahun dimulai dari data paling awal sampai tahun ini; bulan tanpa tahun berarti bulan itu di setiap tahun.
- Pencarian filter bersama kini juga mencocokkan **nama mitra**.
- **Test:** `ContractMonitoringTest` (disesuaikan dengan folder, ditambah folder pengadaan, Monitoring Kontrak, dan filter tahun/bulan).

### 50. Berita Acara SMART SCM per Metode Pengadaan (Pengadaan Langsung vs Penunjukan Langsung / Lelang)
- **Jenis dokumen kini bisa dibatasi per Metode Pengadaan** (pivot `document_type_selection_method`; Data Master → Jenis Dokumen, isian **Hanya untuk Metode Pengadaan**, kolom **Metode**). Kosong berarti semua metode. Pengadaan yang metodenya belum dipilih Rendan dianggap **Pengadaan Langsung** (`DocumentType::appliesTo`).
- **Langkah "Proses SMART SCM & Berita Acara"** memuat BA semua metode secara berurutan (`App\Support\TenderBeritaAcara::STEP`). Setiap pengadaan hanya diminta BA metodenya:

| Metode | BA |
|---|---|
| Pengadaan Langsung | BA Aanwijzing, Lampiran BAPP, BA Evaluasi Teknis (Lampiran 3), BA Evaluasi Harga (Lampiran 4), BA Evaluasi Administrasi, Teknik dan Harga, BA Klarifikasi (seperti sebelumnya) |
| Penunjukan Langsung, Lelang 1 Tahap 1 Sampul, Lelang 1 Tahap 2 Sampul | BA Pemberian Penjelasan (Aanwijzing) versi lelang, Lampiran BAPP, Evaluasi Kualifikasi Administrasi (Lampiran 1), Keuangan (Lampiran 2), Teknis (Lampiran 3) versi lelang, Lampiran 4 Harga, BA Evaluasi ATH, BA Klarifikasi, BA Negosiasi Penawaran, BA Hasil Pengadaan, Nota Dinas Usulan Calon Pemenang, BA Penetapan Pemenang, Pengumuman Pemenang, SPPBJ |
| Tambahan khusus Lelang 1 Tahap 2 Sampul | BA Pembukaan Penawaran Sampul 1, Pengumuman Hasil Evaluasi Sampul 1, BA Pembukaan Penawaran Sampul 2 |

- Lampiran BAPP, Lampiran 4 Harga, BA Evaluasi ATH, dan BA Klarifikasi sementara dipakai bersama semua metode, karena versi lelangnya belum dikirim (dianggap sama). Bila nanti ada versi lelangnya, batasi dokumen lama ke Pengadaan Langsung di Data Master atau lewat migrasi.
- **Template** baru di `resources/document-templates/lelang/*.html` (grup `lelang`), mengikuti formulir unit:
  - Semua memakai logo `sidebar-logo.png`.
  - Nomor surat titik-titik (mis. `..../BAPPS2/612/UPKD/{tahun}`).
  - Hari/tanggal dieja.
- **Data otomatis** (`App\Support\LelangDocuments`):
  - **Pemenang** = calon mitra yang dipilih sebagai pelaksana, atau penawaran termurah.
  - **Harga:** penawarannya (format 2.274.589.800,00), harga setelah negosiasi termasuk PPN beserta terbilang, dan persentase terhadap **HPS termasuk PPN**.
  - **Metode penyampaian:** Satu Tahap Satu/Dua Sampul.
  - **Tabel:** Lampiran 1–3 (dokumen a–g / a–d / a–h per calon mitra, tanda M, kesimpulan LULUS/MEMENUHI; tahun laporan keuangan = tahun lalu; kriteria CSMS = level risiko CSMS pengadaan), nilai penawaran, hasil evaluasi sampul 1, nominasi (pemenang = Nominator ke-1; NPWP titik-titik), negosiasi, negosiasi terhadap HPS, urutan harga terhadap HPS, dan pengumuman pemenang (kolom sampul 1/2 hanya untuk 2 sampul).
  - **Penanda tangan:** TL Pengadaan, PIC Pelaksana, Manager, user/penyusun (Tim Teknis / USER), dan direktur pemenang.
- **Migrasi:** `add_tender_berita_acara_per_selection_method` (pivot, 13 jenis dokumen baru, batasan metode, tautan ke langkah dengan urutan, dan pemasangan template grup `lelang`). Seeder `MasterDataSeeder` mengikuti `TenderBeritaAcara`.
- **Test:** `TenderMethodDocumentsTest`; `MethodSpecificTemplateTest` disesuaikan (hanya menghitung jenis dokumen buatannya sendiri).

### 51. Rapikan Tampilan Monitoring dan Filter Daftar
- **Folder mitra (Pengadaan, Amandemen, DO, Kontrak):** halaman terisi sampai bawah layar dan pagination selalu di bagian paling bawah, seperti di Penilaian Penyedia (`partner-folder-grid.tsx`).
- **Kartu status tanpa baris ganjil** (`resources/js/lib/layout.ts`):
  - `evenColumns()` memilih jumlah kolom (4 sampai 7) yang menyisakan tempat kosong paling sedikit di baris terakhir, misalnya 21 status menjadi 7 × 3.
  - `cardBasis()` membuat kartu baris terakhir tetap memenuhi lebar.
  - Dipakai di kartu status Monitoring Pengadaan dan ringkasan Amandemen/DO/Kontrak.
- **Menu Laporan dihapus dari grup Monitoring di sidebar.** Halaman, rute, dan hak aksesnya tetap ada.
- **Filter Pelaksanaan, Perencanaan, dan semua halaman yang memakai `ProcurementFilterBar`:**
  - Lebar setiap filter mengikuti panjang labelnya.
  - Isi pilihan tidak lagi melebarkan kolom (`w-0 min-w-full`).
  - Mulai layar `xl`, semua filter muat dalam satu baris; bila sempit, label turun ke baris kedua, bukan filternya.
  - Juga diterapkan pada Tahun/Bulan (`period-filter.tsx`) dan filter Monitoring Kontrak/Amandemen/DO.

### 52. Cover RKS Baru (Sesuai Contoh)
- **Semua RKS** (template `rks.spk`, `rks.tender`, `pj.rks-jasa`, `umum.rks`) memakai cover yang sama seperti contoh, dari atas ke bawah:
  - Garis biru diagonal di pojok kiri atas dan kanan bawah. Biru muda dan biru tua langsung bersambung, tanpa garis putih pembatas.
  - **PT PLN NUSANTARA POWER / RENCANA KERJA DAN SYARAT-SYARAT / (RKS)**.
  - **NOMOR :** `{{nomor_rks}}` (dari isian No RKS di checklist, atau diturunkan dari No Kontrak) dan **TANGGAL :** `{{tanggal_dokumen}}`, huruf kapital.
  - Logo PLN Nusantara Power.
  - Nama pekerjaan `{{nama_pengadaan}}` dalam huruf kapital.
  - Pita biru di bawah berisi **UNIT PEMBANGKITAN KENDARI** dan `{{tahun}}`; narasi "Satuan Supply Chain Management 2025" pada contoh sudah diganti.
- **Gambar latar:** `public/image/rks-cover.png` (A4, 150 dpi), ditarik sebesar margin halaman (atas 20 mm, kiri 15 mm, atau 22 mm untuk RKS PJ) sehingga PDF-nya penuh sampai tepi kertas.
- **Teks cover** tetap teks biasa sehingga bisa diedit di editor.
- **Halaman kosong setelah cover** di RKS SPK/Tender dihilangkan (`page-break-after: auto` karena bab berikutnya sudah memulai halaman baru).
- **Di editor dokumen dan editor template:** gambar cover tampil sampai tepi kertas dan dipotong di bawah cover supaya tidak menutupi Daftar Isi (`app.css`, `template-document.ts`).
- **Migrasi `put_the_new_cover_on_every_rks`:**
  - Memperbarui keempat template RKS.
  - Template RKS yang sudah diubah administrator tetap menyimpan perubahannya; hanya cover lamanya yang diganti.
  - Dokumen RKS yang sudah dibuat sebelumnya tidak berubah; buat ulang dokumennya untuk mendapatkan cover baru.
- **Test:** `RksCoverTest` (baru). `MethodSpecificTemplateTest` dan `RksPjJasaTest` disesuaikan dengan judul cover baru.

### 53. Editor Visual Seperti Word: Zoom, Pengaturan Halaman, dan Area Edit Lebih Luas
Berlaku di **Edit Dokumen** (pengadaan) dan **editor Template Dokumen** (Data Master).

- **Lembar kertas sesuai ukuran asli:**
  - Lebar, tinggi, dan margin mengikuti pengaturan halaman dokumen.
  - Batas halaman (dari mesin PDF) dan jarak antarlembar mengikuti margin.
  - "Batas Halaman" dan cover RKS juga mengikuti margin.
- **Zoom** di bilah status bawah:
  - Tombol − / +, pilihan 50–200%, dan **Sesuai lebar**.
  - Ctrl + gulir mouse juga mengubah zoom.
  - Pilihan zoom diingat di browser.
  - Bila belum pernah memilih, layar sempit otomatis memakai **Sesuai lebar**.
  - Zoom hanya tampilan; tidak pernah ikut tersimpan.
- **Pengaturan Halaman** (ikon di toolbar atau klik keterangan halaman di bilah status):
  - Ukuran kertas: A4, F4/Folio, Letter, Legal.
  - Orientasi: Potret / Lanskap.
  - Margin: preset (Standar dokumen 20/15 mm, Normal, Sempit, Sedang, Lebar) atau angka sendiri per sisi (mm), dengan sketsa lembar.
  - **Bawaan template** mengembalikan pengaturan asli.
  - Menggantikan menu "A4 Potret/Lanskap" lama di editor template.
- **Penyimpanan pengaturan halaman:**
  - Disimpan di dokumen/template sebagai satu blok `<style data-page-setup>@page { size; margin }</style>` (`resources/js/lib/page-setup.ts`).
  - Blok ini diletakkan paling akhir sehingga mengalahkan `@page` template.
  - PDF (unduh, pratinjau cetak, dan pembagian halaman di editor) dicetak dengan kertas dan margin itu.
  - Dokumen tanpa blok ini tidak berubah.
  - Dokumen yang dibuat dari template ber-blok ikut membawanya.
- **Footer PDF** (judul, nomor halaman, footer SPK, dan amandemen) kini diukur dari tepi bawah/kanan lembar. Pada A4 posisinya sama seperti sebelumnya; pada F4/Legal tetap di bawah.
- **Area edit lebih luas:**
  - Info dokumen dipadatkan menjadi satu baris.
  - Judul dokumen dibuat sebaris dengan labelnya.
  - Area kertas setinggi layar.
  - Tombol **Layar penuh** (Esc untuk keluar) di kedua editor.
- **Test:** `DocumentPageSetupTest` (baru):
  - Kertas A4/F4/Legal lanskap sampai ke PDF.
  - Blok pengaturan menang atas `@page` template.
  - Margin dan kertas mengubah jumlah halaman.
  - Posisi footer tetap di F4.

### 54. Garis Tabel Surat Pesanan yang Hilang di PDF
- **Masalah:** kolom **Batas Waktu Penyerahan** pada Surat Pesanan digabung (rowspan) untuk semua baris barang. Bila tabel menyambung ke halaman berikutnya, mesin PDF tidak menggambar garis sel gabungan itu (garis bawah di akhir halaman dan kolomnya di halaman berikut hilang).
- **Perbaikan** (`DocumentPdfRenderer::withRowSpansPerSheet`):
  - Saat PDF dibuat, dokumen dibaca halaman per halaman terlebih dahulu.
  - Sel gabungan yang melewati batas halaman dipotong menjadi satu sel gabungan per halaman, masing-masing dengan isi yang sama (tanggal tampil di setiap halaman), sehingga garis tabel lengkap di tiap halaman.
  - Berlaku untuk semua tabel dengan sel gabungan. Tabel yang muat di satu halaman tidak diubah.
  - Isi dokumen yang tersimpan dan editor tidak berubah.
- **Cache PDF:** `LAYOUT_VERSION` dinaikkan ke 4, sehingga PDF yang sudah ter-cache dibuat ulang.
- **Test:** `MergedCellPageBreakTest`.

### 55. Checklist Perencanaan sampai Pembayaran sebagai Tabel Langkah (UX Tahap 1)
Hanya tampilan. Database, rute, dan aturan server tidak diubah; semua aksi memakai endpoint yang sudah ada.

- **Tab Perencanaan, Pelaksanaan, Penyerahan, dan Pembayaran** memakai komponen `ChecklistSteps` (`resources/js/components/procurement/checklist-steps.tsx`).
  - `ChecklistPanel` lama tidak dipakai lagi di halaman pengadaan; `ChecklistDocumentActions` di dalamnya tetap dipakai.
  - Pada Penyerahan/Pembayaran, menu ⋯ juga berisi "Unduh template surat (Word)".
- **Pesan penutup banner per tahap:**
  - Perencanaan: siap diajukan ke TL ICC.
  - Pelaksanaan: siap diajukan ke TL Pengadaan.
- **Langkah dengan banyak dokumen** (mis. Proses SMART SCM & Berita Acara, lebih dari 3 dokumen):
  - Di tabel diringkas menjadi "x dari y dokumen lengkap" dengan bilah kemajuan dan nama dokumen berikutnya.
  - Di panel samping, bila kebutuhan lebih dari 6, hanya yang belum lengkap yang didaftar.
- **Isian ditulis dengan bahasa sederhana:** "Tanggal mulai & jumlah hari", "Pelaksana & rekening bank", "Masa garansi (bulan)", "Nomor & tanggal nota dinas", "Nomor kontrak".
- **Banner "Langkah berikutnya"**:
  - Menunjuk langkah wajib pertama yang belum selesai.
  - Menampilkan apa yang masih kurang dan satu tombol aksi.
  - Bila semua langkah wajib selesai, menampilkan pesan bahwa perencanaan siap diajukan.
- **Tabel langkah** dengan kolom No | Langkah | Yang dibutuhkan | Status | Aksi. Di ponsel berubah menjadi daftar bertumpuk.
- **Status dibaca dari data**:
  - Status: Selesai, Siap ditandai selesai, Perlu dilengkapi, Belum mulai, Opsional.
  - Sumber data: isian, berkas, dan dokumen bertanda tangan.
  - Tanda selesai tetap lewat tombol "Tandai selesai" dengan validasi server yang sama.
- **Isian nomor (RKS/HPE/UPB/kontrak) bisa diketik langsung di tabel**; tekan Enter atau ✓ untuk menyimpan.
- **Satu tombol utama per baris**:
  - Isi data / Generate / Unggah TTD / Tandai selesai / Lihat.
  - Aksi lain ada di menu ⋯: buka detail, pratinjau/unduh dokumen, tandai belum selesai.
- **Klik langkah membuka panel samping** berisi:
  - Daftar yang dibutuhkan, lalu bagian bernomor "Isi data", "Unggah berkas", dan "Dokumen" (memakai komponen form/unggah/dokumen yang sudah ada).
  - Tombol Tandai selesai, dinonaktifkan dengan keterangan bila belum lengkap.
  - Tombol "Langkah berikutnya".
- **Panel tetap terbuka setelah menyimpan** (halaman dimuat ulang), dan pesan penolakan dari server ditampilkan.

### 56. Amandemen & DO sebagai Tabel dengan Panel Samping
Hanya tampilan. Endpoint, aturan persetujuan, dan database tidak diubah.

- **Komponen bersama** di `resources/js/components/procurement/request-board.tsx`:
  - `useRememberedPanel`: panel yang terbuka diingat 60 detik agar muncul lagi setelah halaman dimuat ulang karena menyimpan.
  - `NextActionBanner`, `PanelSection`, dan `StageDots`.
- **Banner "Perlu tindakan Anda"**:
  - Menyebut amandemen/DO pertama yang menunggu pengguna, beserta tugasnya.
  - Contoh tugas: Lengkapi, Perbaiki, Tinjau, Proses (Rendan), Terbitkan; disertai yang masih kurang.
  - Tombolnya langsung membuka panel.
- **Tabel daftar** (terbaru di atas):
  - Amandemen: Amandemen | Nomor & tanggal | Perubahan (jumlah pasal) | Status (label + titik tahap "TL · 2/5") | Aksi.
  - DO: DO | Nomor & tanggal | Item & nilai | Status | Aksi.
  - Satu tombol sesuai tugas; menu ⋯ berisi pratinjau/unduh/edit dokumen.
- **Panel samping per amandemen/DO**:
  - Tahapan (Pengusul › TL › Asman › … › Terbit), "Tugas Anda", dan catatan pengembalian.
  - Bagian bernomor sesuai peran: isi permohonan, unggah dokumen, ajukan / periksa permohonan, keputusan / Nota Dinas DO, teruskan / data DO & pasal, terbitkan.
  - Diakhiri riwayat persetujuan.
  - Form yang dipakai tetap form yang sama seperti sebelumnya.
- **Tombol "Ajukan Amandemen" / "Ajukan DO"** langsung membuka panel draf baru setelah dibuat.
- **Daftar Harga Satuan Kontrak (DO)**:
  - Bila masih kosong, tampil paling atas dengan banner "Langkah pertama: Isi Daftar Harga Satuan Kontrak".
  - Bila sudah terisi, dipindah ke bawah tabel.
- **Tab yang dipilih di halaman pengadaan kini tersimpan di alamat (`?tab=`)**, sehingga setelah menyimpan apa pun halaman kembali ke tab yang sama. Sebelumnya halaman kembali ke tab tahap berjalan.

### 57. Stepper Tahap dan Hambatan Pengajuan (UX Tahap 2)
Hanya tampilan. Database, rute, aturan pengajuan/persetujuan, dan data yang dikirim server tidak diubah.

- **Stepper tahap menggantikan deretan 8 tab** (`resources/js/components/procurement/stage-stepper.tsx`):
  - Usulan › Perencanaan › Pelaksanaan › Penyerahan › Pembayaran, masing-masing bertanda ✔ selesai, nomor biru sedang berjalan, abu-abu belum, atau 🔒 terkunci.
  - Ringkasan per tahap, mis. "2/2 dokumen", "Disetujui TL ICC", "4/8 langkah", "Menunggu TL Pengadaan", "Dikembalikan", "Lunas", "Terkunci".
  - Tahap terkunci tetap bisa dibuka untuk dilihat.
  - Titik merah menandai tahap yang menunggu tindakan Anda (mengajukan/menyetujui, mencatat lunas).
- **Amandemen, Delivery Order, dan Riwayat Approval** menjadi tombol sekunder di bawah stepper, dengan jumlah dan titik merah bila ada yang menunggu Anda.
- **Kartu Persetujuan Perencanaan, Persetujuan Pelaksanaan, dan Proses Pembayaran** kini menyebut setiap hal yang belum lengkap, bukan hanya jumlahnya:
  - Klik nama langkah → pindah ke tab tahapnya dan langsung membuka panel langkah itu.
  - "(data identitas)" → ke Edit Identitas Pengadaan.
  - "(tab dokumen pengguna)" → ke tab Usulan.
  - Bila tidak ada hambatan, tampil catatan hijau "siap diajukan" di atas tombol Ajukan.
- **Banner selesai di tabel langkah** Perencanaan/Pelaksanaan menampilkan tombol "Ajukan Persetujuan" / "Ajukan ke TL Pengadaan" (endpoint yang sama dengan kartu) bagi yang berhak mengajukan.
- **Di ponsel:**
  - Stepper bisa digeser ke samping dan tahap yang dipilih otomatis terlihat.
  - Bila Anda bisa mengajukan/menyetujui, muncul pintasan di bawah stepper yang menggulir ke kartu persetujuan.

### 58. Panel Kanan, DO Bertahap, dan Perbandingan Pasal (UX Tahap 3)
Hanya tampilan. Database, rute, aturan, dan pesan server tidak diubah.

**A. Panel kanan halaman pengadaan**
- **Satu kartu "Persetujuan"** (`approval-panel.tsx`) berisi Perencanaan lalu Pelaksanaan:
  - Tahap yang sudah lewat cukup satu baris (mis. "✔ Disetujui 18 Agu · Bastial"), bisa dibuka untuk detail.
  - Tahap terkunci menyebut kapan terbuka.
  - Tahap yang sedang berjalan terbuka penuh.
- **Urutan kolom kanan:** Persetujuan → Status Progres → PIC. Di desktop kolom ini menempel saat halaman digulir.
- **Istilah diseragamkan:**
  - "Ajukan ke TL ICC" / "Ajukan ke TL Pengadaan".
  - "Kembalikan untuk Revisi" (sebelumnya "Tolak").
- **Keputusan dua langkah** di semua persetujuan (perencanaan, pelaksanaan, amandemen, DO; `decision-controls.tsx`):
  - "Setujui" tombol utama, dengan "Tambah catatan" opsional.
  - "Kembalikan untuk Revisi" membuka kotak "Yang harus diperbaiki" yang wajib diisi sebelum "Kirim Pengembalian".
- **Status Progres dipadatkan:** hanya status sebelumnya, sekarang, dan berikutnya, plus "Lihat semua status".
- **"Batalkan Pengadaan" dan "Arsipkan"** pindah ke menu ⋯ di judul halaman.
  - Pembatalan lewat dialog yang meminta alasan.
  - "Cabut Pembatalan" tetap di kartu Status.
- **Kartu PIC tampil sebagai nama**, dengan tombol "Ubah" bagi yang berhak. Bila PIC belum ditunjuk, pilihan langsung terbuka.
- "Tandai Pengadaan Selesai" pindah ke bawah kartu Persetujuan.

**D. Notifikasi:** semua aksi di halaman pengadaan ternyata sudah mengirim notifikasi dari server ("Isian tersimpan", "Checklist diperbarui", "Permohonan DO diajukan", dst.), jadi tidak ditambah agar tidak dobel.

**B. Delivery Order** (`delivery-order-items.tsx`)
- **Ringkasan nilai kontrak** di atas tabel DO: Nilai kontrak (nilai setelah nego), DO terbit, Sedang diproses/draf, dan Sisa, dengan bilah kemajuan dan peringatan bila terlampaui.
- **Pilih item berupa tabel daftar harga:**
  - Cari, filter "hanya yang dipilih", kelompok sebagai baris judul, input volume per item, jumlah per baris, dan total berjalan.
  - Peringatan (bukan blokir) bila total DO melebihi sisa nilai kontrak.
  - Dipakai di permohonan dan di penerbitan Laksdan.
  - Volume kosong atau 0 berarti tidak dipesan; item yang sama tidak terulang (sesuai aturan server).
- **Permohonan DO menjadi wizard 3 langkah:** ① Pilih item → ② Nota Dinas usulan (+ unggah) → ③ Periksa & ajukan.
  - Tiap langkah menyimpan sebelum lanjut.
  - Posisi langkah diingat setelah halaman dimuat ulang.
- **Daftar Harga Kontrak bisa "Tempel dari Excel":**
  - Kolom: Kelompok, Uraian, Part Number, Satuan, Harga (atau 4/3 kolom).
  - Baris judul dilewati; format "Rp 1.234.500,00" terbaca.
- Sisa per item belum ditampilkan karena daftar harga tidak menyimpan kuantitas kontrak; perlu kolom database baru bila diinginkan.

**C. Amandemen:** pasal yang diubah ditampilkan berdampingan **Semula | Menjadi** dengan perbandingan per kata.
- Kata yang dihapus dicoret merah, kata baru ditandai hijau.
- Tanda yang sama juga muncul langsung di editor pasal Laksdan saat mengetik.

**Tab Usulan** (`proposal-documents.tsx`, juga dipakai di halaman Usulan Pekerjaan) kini memakai pola yang sama:
- Banner "Langkah berikutnya" untuk dokumen yang belum terunggah, atau catatan hijau bila keduanya sudah terunggah.
- Tabel TOR dan Nota Dinas Usulan: Yang dibutuhkan, nama berkas, Status, satu tombol (Generate / Unggah TTD / Unggah / Lihat), dan menu ⋯ (pratinjau / unduh / edit).
- Panel samping berisi tombol dan berkas dokumen.
- Hambatan "(tab dokumen pengguna)" di kartu Persetujuan langsung membuka panel dokumennya.

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
(Usulan staf: disetujui TL, lalu Asman bidang → langsung masuk ke TL ICC)
            │
            ▼
Penunjukan PIC Perencana — oleh TL ICC
            │
            ▼
Tahap Perencanaan
│
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
PIC Perencana mengajukan → Approval TL ICC
            │
            ▼
Penunjukan PIC Pelaksana — oleh TL Pengadaan (hanya setelah perencanaan disetujui)
            │
            ▼
Tahap Pelaksanaan — SPK & PJ
│
├── Penyusunan HPS            (unggah)
├── Proses SMART SCM          (unggah)
├── Berita Acara              (generate 6 dokumen)
├── Surat Pesanan
├── Jaminan Bank              (unggah)
├── Kontrak                   (generate SPK saja)
├── Rentang Waktu
├── Amandemen                 (unggah, opsional)
└── Masa Pemeliharaan         (unggah)

Tahap Pelaksanaan — SPPL
│
├── BA Negosiasi              (generate, landscape)
├── Surat Pesanan             (generate)
├── Rekening Pelaksana        (isian: no rekening, bank, nama)
├── Lampiran Surat Pesanan    (Lampiran SP Barang dan Jasa)
├── Rentang Waktu Pelaksanaan (isian: tanggal mulai + hari → tanggal akhir otomatis)
└── Masa Garansi              (isian: bulan)
            │
            ▼
PIC Pelaksana mengajukan → Approval TL Pengadaan
(ditolak: kembali ke PIC Pelaksana dengan catatan, lalu diajukan ulang)
            │
            ▼
Penyerahan & Pembayaran (Keuangan) → Pengadaan Selesai
```

## Pembagian Peran Setelah Usulan Disetujui Asman

| Langkah | Pelaku (default) | Hak akses |
|---|---|---|
| Menunjuk PIC Perencana | TL ICC | `procurement.assign-planner` — Tunjuk PIC perencana |
| Menyetujui / menolak perencanaan | TL ICC | `procurement.review-planning` |
| Menunjuk PIC Pelaksana (setelah perencanaan disetujui) | TL Pengadaan | `procurement.assign-pic` — kini berarti Tunjuk PIC pelaksana (nilai lama dipertahankan agar peran yang sudah memilikinya tidak berubah) |
| Mengajukan pelaksanaan | PIC Pelaksana yang ditunjuk (bukan TL / admin) | otomatis dari penugasan, setelah semua tahapan wajib pelaksanaan dicentang |
| Menyetujui / mengembalikan pelaksanaan | TL Pengadaan | `procurement.review-execution` — Setujui / tolak pelaksanaan |

- **Notifikasi:** persetujuan Asman memberi tahu pemegang hak Tunjuk PIC perencana (TL ICC). Pengajuan perencanaan memberi tahu pemegang hak review perencanaan. Perencanaan yang disetujui tanpa PIC Pelaksana memberi tahu pemegang hak Tunjuk PIC pelaksana. Pengajuan pelaksanaan memberi tahu TL Pengadaan; keputusan dikirim ke PIC Pelaksana; persetujuan meneruskan ke Keuangan (notifikasi tagihan).
- **Penunjukan PIC:** layar Penunjukan PIC menampilkan kolom sesuai hak. TL ICC melihat usulan yang disetujui dan mengisi PIC Perencana; TL Pengadaan hanya melihat pengadaan yang perencanaannya disetujui dan mengisi PIC Pelaksana. Server memeriksa tiap PIC terhadap haknya sendiri. Kolom PIC Perencana di form Buat Pengadaan hanya muncul bagi pemegang hak Tunjuk PIC perencana.
- **Persetujuan pelaksanaan:** kolom baru `execution_approval_state` (belum_diajukan / menunggu_persetujuan / disetujui / ditolak), `execution_submitted_at`, `execution_reviewed_at`, `execution_reviewed_by`, `execution_review_note`, `execution_revision`. Checklist pelaksanaan terkunci selama ditinjau. Persetujuan mengisi `finance_submitted_at/by` (pengadaan masuk Penyerahan & Pembayaran). Penolakan wajib bercatatan. Pengadaan yang sudah di Keuangan sebelum migrasi ditandai disetujui. Rute: `procurements.execution-approval.store/update` (menggantikan `procurements.finance.submit`).
- **Tampilan:** kartu Persetujuan Pelaksanaan di tab Pelaksanaan, antrean Approval memuat bagian "Pelaksanaan · menunggu TL Pengadaan", dashboard punya daftar "Pelaksanaan Menunggu Persetujuan Anda", dan Riwayat Approval mencatat Pelaksanaan Diajukan / Disetujui / Ditolak.
- **Test:** `ExecutionApprovalTest`, `ProcurementWorkflowTest`, `FinanceStageTest`, `EndToEndFlowTest`, `DashboardTasksTest`, `PlannerOnCreateTest`.

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

## Folder per Mitra & Penanda Kelengkapan

- **Daftar = folder per mitra:** daftar Penilaian Penyedia tampil sebagai grid folder kecil, satu folder per mitra/penyedia, sama seperti Arsip Dokumen.
  - Nama yang sama dengan beda huruf besar/kecil atau spasi digabung (`LOWER(TRIM(vendor_name))`).
  - Tiap folder menampilkan jumlah penilaian dan penanda **Semua lengkap** (hijau) atau **x/y lengkap** (kuning).
  - Paginasi 30 folder. Bila halaman penuh, baris meregang sampai bawah layar. Mitra yang terakhir dinilai tampil paling depan.
  - Pencarian (pekerjaan/penyedia/nomor PO), Ekspor Excel, tombol Buat Formulir, dan panel Request Penilaian Vendor tetap ada.
- **Halaman folder mitra** `penilaian-penyedia/mitra?nama=` (`vendor-assessments.vendor`):
  - Kiri: daftar penilaian mitra itu (terbaru di atas) berisi pekerjaan, nomor kontrak/PO, tanggal, penanda **Lengkap** / **x/y aspek**, dan nilai akhir.
  - Kanan: **pratinjau PDF besar** dengan pilihan **Sertifikat (Akumulasi)** atau **Semua Lembar** (memakai rute cetak yang sudah ada), tombol Tab Baru, dan **Buka & Isi Nilai** ke halaman penilaian.
  - Judul memakai ejaan nama dari Data Master Mitra beserta kode rekanan dan direktur.
  - Tombol **Kembali ke Penilaian** membawa pencarian dan halaman sebelumnya.
- **Test:** `VendorAssessmentTest` (`test_the_list_tells_a_fully_scored_assessment_apart`, `test_assessments_are_filed_in_one_folder_per_vendor`, `test_an_unknown_vendor_folder_is_not_found`).

## Daftar Pengadaan per Mitra & Label Dokumen Tervalidasi

- **Daftar Pengadaan = folder per mitra:** halaman `procurements` kini dibuka dengan grid folder per mitra, sama seperti halaman Monitoring.
  - Tiap folder menampilkan jumlah pengadaan dan penanda **x/y selesai**. Pengadaan tanpa mitra masuk folder **Belum Ada Mitra** (`?mitra=tanpa-mitra`), yang selalu tampil paling akhir.
  - Klik folder (`?mitra=`) untuk membuka tabel pengadaan mitra itu, dengan tombol **Semua Mitra** untuk kembali. Filter tetap berlaku di dalam folder.
  - Saat mengetik pencarian (tanpa folder terbuka), hasilnya langsung tampil sebagai tabel lintas semua folder.
  - Hak lihat tidak berubah: PIC hanya melihat folder dari pengadaan miliknya.
  - Logika folder dipindah ke trait `App\Concerns\GroupsByPartner`, dipakai `MonitoringController` dan `ProcurementController` (konstanta `NO_PARTNER` tetap bisa diakses lewat kedua controller). Prop `procurements` tetap dikirim.
  - Test: `ProcurementPartnerFoldersTest`.
- **Label dokumen tervalidasi:** di checklist semua tahapan dan tab Usulan:
  - "Unggah TTD", "Unggah dokumen/berkas", "Unggah Hasil TTD", dan "Unggah Dokumen" diganti menjadi **Unggah Dokumen Tervalidasi**.
  - "… bertanda tangan" / "… diunggah" diganti menjadi **… tervalidasi**.
  - Kolom Aksi tabel langkah dilebarkan (16.5rem), dan tombolnya membungkus teks agar tidak menutupi kolom Status.
- **Request Penilaian Vendor:** request yang sudah dibuat penilaiannya keluar dari daftar request dan cukup dilihat di folder mitranya.

## Request Penilaian Vendor (tautan rincian tagihan)

Bagian **Request Penilaian Vendor** ada di halaman Penilaian Penyedia, di atas daftar formulir.

- **Tautan tetap:** tautannya satu dan tidak pernah berubah, yaitu `{APP_URL}/permintaan-penilaian`.
  - Tautan ini dicantumkan di kontrak lewat variabel template `{{tautan_penilaian_vendor}}`.
  - Di halaman Penilaian Penyedia tautan tampil beserta tombol **Salin Tautan** dan **Buka**.
  - **Salin Tautan** menyalin pesan siap kirim: "Semangat Pagi / Bapak/Ibu / Untuk request Penilaian Vendor mohon agar dibantu mengisi rincian tagihan pada link berikut :" diikuti tautannya, lalu "Terima Kasih".
- **Ikon situs:** `public/favicon.ico`, `favicon.svg`, dan `apple-touch-icon.png` (dulu logo Laravel, yang tampil di pratinjau tautan WhatsApp) diganti logo PLN dari `public/logo/icon.png`.
  - Vendor cukup mengklik tautan, tidak perlu akun.
  - Setiap kiriman menjadi satu request baru, dan tautan tetap bisa dipakai vendor berikutnya. Setelah mengirim, vendor melihat layar "terkirim" dengan tombol **Isi Request Lain**.
  - Batas kiriman 10 per menit per IP.
- **Pengisian oleh vendor / pelaksana:** semua isian wajib, kecuali No. HP/WhatsApp pengisi yang opsional:
  - Pekerjaan
  - Pelaksana
  - No Kontrak/DO
  - Tgl. Kontrak
  - Termin
  - Nilai tagihan
  - No BAPP/BASTP
  - Tgl BAPP/BASTP
  - Denda (Ada / Tidak ada)
  - Nama pengisi
- **Tampilan request:** rincian yang masuk tampil sebagai **Request baru**, lengkap dengan isiannya. Tombol **Buat Penilaian** membuka formulir penilaian yang sudah terisi:
  - Pekerjaan → Project
  - Pelaksana → Penyedia
  - No Kontrak/DO → No Kontrak
  - Tgl. Kontrak → Tanggal Kontrak
  - Tgl BAPP/BASTP → Tanggal BASTP
  - Denda → Denda
  - Termin, Nilai tagihan, No BAPP/BASTP dan nama pengisi → Catatan internal (tidak tercetak)
- **Setelah penilaian dibuat:** request otomatis keluar dari daftar Request Penilaian Vendor dan penilaiannya ada di folder mitranya (datanya tetap tersimpan, tertaut ke penilaian lewat `vendor_assessment_id`). Daftar hanya berisi request yang menunggu diproses. Request yang salah atau ganda bisa dihapus dari daftar.
- **Tanpa request:** pembuatan formulir penilaian berjalan seperti sebelumnya.
- **Data:** tabel `vendor_assessment_requests`, model `VendorAssessmentRequest` (`formUrl()` memberi tautan tetap). Controller admin `VendorAssessmentRequestController` (hapus), controller publik `AssessmentRequestFormController`. Halaman publik: `assessment-signing/request`.

## Header Tabel Tidak Diulang di Setiap Halaman

- **Masalah:** dompdf (dan fitur cetak browser) mengulang `<thead>` di atas setiap halaman yang dilewati tabel. Akibatnya header tabel barang Surat Pesanan tampil lagi di halaman 2 dan seterusnya.
- **Perbaikan:** `DocumentGenerator::withoutRepeatedTableHeaders()` mengubah `<thead>` menjadi `<tbody>` pada keluaran `printableHtml()`. Fungsi itu dipakai untuk PDF, pratinjau, dan cetak semua dokumen, sehingga header hanya tercetak sekali di awal tabel.
  - Gaya header tetap sama karena semua gayanya inline.
  - Template dan dokumen tersimpan tidak diubah, begitu pula editor dan ekspor Word.
- **Cache:** `DocumentPdfRenderer::LAYOUT_VERSION` dinaikkan ke `'5'` supaya PDF lama dibuat ulang.
- **Test:** `TableHeaderOncePerTableTest`. Test ini juga membuktikan perilaku lama: sebelum perbaikan, header tergambar sekali di setiap halaman.

## Sidebar "Portal Saya" dengan Pin & Rincian Item Gaya Excel

- **Sidebar tersembunyi di balik tab "Portal Saya"** (`components/portal-tab.tsx`, dipasang di `app-sidebar-layout.tsx`):
  - **Bentuk tab:** tab vertikal di tepi kiri tengah layar.
  - **Membuka:** didekati kursor (area hover diperlebar) atau diklik membuka sidebar sambil **menggeser konten**, tidak menutupinya. Di ponsel, tab membuka sheet sidebar.
  - **Tidak disematkan:** sidebar menutup sendiri 400ms setelah kursor keluar dari sidebar (`scheduleClose`/`cancelClose` di `ui/sidebar.tsx`).
  - **Disematkan:** tombol pin di pojok atas sidebar (`app-sidebar.tsx`). Sidebar tetap tampil, tidak menutup saat kursor keluar, dan pilihan ini disimpan di cookie baru `sidebar_docked`. Tombol yang sama melepas pin.
  - **Header & Ctrl+B:** tombol sidebar di header dan Ctrl+B ikut menyematkan atau menutup (`setOpen` = pin).
  - **Bawaan untuk semua pengguna:** tidak disematkan. `HandleInertiaRequests` membaca `sidebar_docked` (dulu `sidebar_state`), dan cookie ini dikecualikan dari enkripsi di `bootstrap/app.php`.
  - **Konteks sidebar:** `pinned`, `unpin()`, `openForAWhile()`, `scheduleClose()`, `cancelClose()`. Sidebar memakai `collapsible="offcanvas"` (dulu "icon"). Saat tertutup, konten tetap diberi jarak `ml-2`.
  - **Tablet & ponsel (< 1024px, `useIsCompact()` di `hooks/use-mobile.tsx`):** sidebar dibuka sebagai panel geser di atas halaman dengan latar gelap (Sheet), sehingga konten tidak terdorong dan tidak sesak. Ketuk di luar panel untuk menutup. Tombol pin tidak tampil di mode ini.
  - **Tablet landscape / layar sentuh ≥ 1024px:** mode desktop (menggeser konten). Sidebar yang dibuka tanpa pin juga tertutup saat diketuk atau diklik di luarnya, karena di layar sentuh tidak ada kursor yang "keluar".
  - **Diuji di 768/820px:** tab Portal Saya tidak menutupi isi halaman (ada di margin kiri) dan tidak ada geser halaman ke samping.
- **Rincian Item bergaya lembar Excel** (`item-list-card.tsx`). Hanya tampilan yang berubah; fungsi tidak berubah.
  - Garis sel di semua sisi (`border-collapse`).
  - Kotak isian tanpa bingkai yang mengisi penuh selnya (`h-px` pada sel + `h-full` pada isian).
  - Sel yang sedang diisi bergaris biru tebal (`focus:shadow-[inset_0_0_0_2px_var(--primary)]`), seperti sel aktif Excel.
  - Kolom No abu-abu seperti nomor baris Excel, dan baris yang sedang diedit tersorot.
  - Header dan baris total abu-abu.

## Zoom Touchpad & Daftar Template Berfilter

- **Pinch touchpad di editor template (dan Editor Visual dokumen):**
  - **Penyebab utama:** dropdown persen zoom (`ZoomControls`, Radix Select) mengirim nilai kosong saat zoom berada di antara pilihan bakunya. Nilai kosong itu dibaca sebagai 0 lalu dibulatkan ke batas minimum, sehingga pinch selalu jatuh ke 30%.
  - **Perbaikan dropdown:** nilai kosong atau tidak valid kini diabaikan.
  - **Perbaikan gerakan (`usePinchZoom` di `zoom-controls.tsx`, dipakai `useWheelZoom` dan editor template):**
    - Pinch mengubah zoom sebesar gerakannya (`exp(-deltaY × 0.01)`), dikumpulkan dari nilai terbaru agar tidak hilang karena pembulatan atau render.
    - Satu putaran roda mouse + Ctrl tetap 10%.
- **Daftar Template Dokumen** (`master-data/document-templates`) kini berhalaman, 20 per halaman (`DataPagination`), dengan pencarian dan filter di server (`DocumentTemplateController::filters()` / `filtered()`):
  - **Pencarian (`q`):** nama template atau nama jenis dokumen, dengan jeda 300ms.
  - **Jenis Dokumen (`jenis`).**
  - **Metode (`metode`), Format Kontrak (`format`), Kategori (`kategori`):** pilihan "semua" berarti template yang berlaku untuk semua (nilai kosong).
  - **Status (`status`):** aktif atau nonaktif.
  - **Asal (`asal`):** `bawaan` (`library_key` terisi) atau `buatan` (dibuat di sini).
  - Tombol **Reset**, dan keadaan kosong khusus ketika tidak ada yang cocok.
  - Prop baru: `filters`, `filterOptions`. `templates` kini berbentuk `Paginated`.
- **Test:** `DocumentTemplateListTest`.

## Geser Garis Kolom, Enter di Sel, & Tab Awal Editor

- **Geser garis kolom seperti Word** (`components/document-editor/column-resize.ts`, hook `useColumnResize`). Berlaku di Editor Visual dokumen (`visual-editor.tsx`) dan di editor template Data Master (`template-editor.tsx`, di dalam iframe).
  - **Cara pakai:** arahkan kursor ke garis antara dua kolom sampai berubah menjadi ↔, lalu tarik. Kolom kiri melebar dan kolom kanan menyempit, sedangkan lebar tabel tetap. Garis tepi luar tabel tidak bisa ditarik.
  - **Batas:** lebar minimum kolom 16px. Sel gabungan (`colspan`/`rowspan`) dipetakan dengan benar.
  - **Zoom:** skala dibaca dari rasio ukuran layar dan ukuran tata letak tabel, jadi tepat untuk zoom `transform` (dokumen) maupun CSS `zoom` (template).
  - **Penyimpanan lebar:** lebar ditulis sebagai persen di `style.width` setiap sel satu-kolom pada dua kolom itu (dibaca dompdf). Untuk tabel `table-layout: fixed`, lebar juga ditulis ke `<colgroup>` (dibaca browser).
  - **Setelah dilepas:** perubahan diteruskan ke editor (`emit`); di template dengan event `input`.
  - Sudah dicek: PDF (dompdf) mengikuti lebar baru walau ada `colgroup`.
  - Catatan: tabel Surat Pesanan menulis ulang `thead`-nya sendiri saat item berubah, sehingga lebar di baris judul tabel itu bisa kembali ke bawaan.
- **Enter di dalam sel tabel item kini bertahan:**
  - **Penyebab:** saat BA Negosiasi disimpan, tabel item dibaca menjadi Rincian Item dan `<br>` diubah menjadi spasi, lalu semua tabel item digambar ulang.
  - **Pembacaan:** `DocumentGenerator::cellLines()` menyimpan baris baru sebagai `
`. Pembungkusan teks dari sumber HTML tetap dianggap spasi.
  - **Penggambaran:** uraian digambar dengan `nl2br` di `ItemTableRenderer`, Surat Pesanan, DO, dan Surat Keuangan.
  - **Rincian Item:** kolom uraian kini `Textarea` yang membesar otomatis (Enter = baris baru; nama kelompok tetap satu baris).
  - Test `SpplExecutionTest::test_a_line_break_in_an_item_name_keeps_the_words_apart` disesuaikan: kini mengharapkan baris baru, bukan spasi.
- **Tab awal editor dokumen:** selalu **Editor Visual**, bukan Rincian Item (`document-editor.tsx`).
- **Test:** `ProcurementItemsTest::test_a_line_typed_with_enter_in_a_cell_stays_after_saving` dan `test_the_source_wrapping_of_a_cell_is_no_line_break`.

## Nomor Kontrak Diisi di Identitas Pengadaan

- **Pindah tempat:** nomor kontrak (`procurements.number`) tidak lagi diketik di langkah **Lampiran Surat Pesanan**, tetapi di kartu **Identitas Pengadaan**, pada field **Nomor Kontrak** (`components/procurement/contract-number-field.tsx`).
  - Ada tombol "Pakai nomor otomatis" dari format kontrak.
  - Cara simpan: Enter atau ✓. Esc membatalkan.
  - Untuk yang tidak berhak mengisi, field tampil sebagai teks biasa begitu nomor sudah ada.
- **Yang boleh mengisi:** PIC Pelaksana, TL Pengadaan, atau administrator (policy `updateContractNumber`: perencanaan sudah disetujui dan belum diserahkan ke Keuangan).
  - Rute: `procurements.contract-number.update` (`PUT procurements/{id}/nomor-kontrak`), lewat `ContractNumberController` dan `UpdateContractNumberRequest`.
  - Aturan validasi sama seperti sebelumnya (`numberRules()` dipakai bersama `UpdateChecklistInputRequest`): wajib, unik, dan bukan nomor registrasi PGD/USL.
  - Setiap perubahan tercatat di riwayat aktivitas.
  - Untuk yang berhak mengisi, field mengisi satu baris penuh kartu (maks. `max-w-md`) agar nomor lengkap terlihat saat diketik.
- **Langkah checklist tetap menagih nomor kontrak**, jadi alur tidak berubah:
  - Kebutuhannya kini tertulis "Nomor kontrak (di Identitas Pengadaan)".
  - Tombol **Isi Nomor Kontrak** (di baris langkah dan di banner "Langkah berikutnya") menutup panel, menggulir ke kartu Identitas, dan membuka field-nya (`focusContractNumber()`).
  - Panel langkah menampilkan nomor saat ini dan tombol **Isi/Ubah di Identitas Pengadaan**.
  - Panel samping bisa ditutup dari luar lewat `closeRememberedPanels()` (`request-board.tsx`).
- **Test:** `ContractNumberTest`.

## Nomor Kontrak SPPL Pindah ke Langkah Surat Pesanan

- **Perubahan:** pada SPPL, isian **Nomor kontrak** kini ditagih di langkah **Surat Pesanan**, bukan lagi di **Lampiran Surat Pesanan**. SPK/PJ tetap di langkah **Kontrak**.
- **Isian dibatasi per format kontrak:**
  - Kolom baru `checklist_items.input_contract_number_format_id`: isian sebuah langkah bisa diminta dari satu format kontrak saja. Kosong berarti semua format.
  - Langkah Surat Pesanan dipakai SPK, PJ, dan SPPL, tetapi nomor kontrak hanya diminta dari SPPL. SPK/PJ tetap melewati Surat Pesanan tanpa isian.
  - Lewat `ChecklistItem::inputFor($procurement)`, yang dipakai `ProcurementController::checklistPayload`, `ChecklistController` (isian dan centang), `UpdateChecklistInputRequest`, dan `Procurement::pendingRequiredPlanningChecklists`.
  - Di **Data Master → Item Checklist**, ada isian baru **Isian Hanya untuk Format Kontrak** (pilihan "Semua format" atau satu format).
- **Nilai lama tidak hilang:** nomor kontrak tersimpan di pengadaan (`procurements.number`), bukan di langkah. Jadi nomor yang dulu diisi di Lampiran langsung tampil terisi di langkah Surat Pesanan, dan pekerjaan yang sudah berjalan tidak perlu mengisi ulang.
- **Migrasi `move_sppl_contract_number_input_to_surat_pesanan`:**
  - Langkah Surat Pesanan mendapat isian nomor kontrak khusus SPPL, dan isian itu dilepas dari Lampiran Surat Pesanan.
  - Bila langkah Surat Pesanan sudah dihapus di Data Master, langkah itu dipulihkan **khusus untuk SPPL** beserta dokumen Surat Pesanannya. SPK/PJ diberi pengecualian supaya tidak berubah.
  - Bila langkah itu dinonaktifkan, dilewati SPPL, atau sudah diberi isian lain oleh administrator, pilihan administrator dihormati dan nomor tetap ditagih di Lampiran.
  - Pekerjaan SPPL yang berjalan mendapat langkah ini: sebagai langkah yang harus dikerjakan bila pelaksanaannya masih dikerjakan, atau langsung selesai bila sudah diajukan, disetujui, atau dibayar, supaya tidak ada yang tertahan.
  - `down()` mengembalikan isian ke Lampiran.
- **Seeder:** `MasterDataSeeder` memasang isian di Surat Pesanan (khusus SPPL). Bila langkah Surat Pesanan dihapus pengguna, isian tetap di Lampiran seperti sebelumnya.
- **Test:** `ContractNumberInputTest`.

## Huruf Kelompok (A/a) & Kelompok Tanpa Item

- **Huruf kelompok bisa besar atau kecil:** pilihannya disimpan per pengadaan di kolom baru `procurements.group_letters` (`upper` sebagai bawaan, atau `lower`) lewat migrasi `add_group_letters_to_procurements`.
  - Berlaku di semua tabel item pengadaan itu: BA Negosiasi, SPK, HPE, UPB, Evaluasi Harga, Lampiran, Surat Pesanan (`generateSuratPesananTbody`), dan Delivery Order.
  - Lewat `Procurement::groupLetter()` dan `ItemTableRenderer::apply(..., $lowerLetters)`.
  - **Cara mengubah di Rincian Item editor dokumen:** pilih **Huruf kelompok: A, B, C / a, b, c** di bawah tabel (muncul kalau ada kelompok), atau klik huruf di kolom NO.
  - Pratinjau langsung digambar ulang (`items-preview` menerima `group_letters` dan memakainya di memori saja). Pilihannya tersimpan bersama **Simpan Perubahan** (`group_letters` di `UpdateDocumentRequest`), lalu semua tabel digambar ulang.
- **Kelompok tanpa item kini tetap tampil:**
  - **Masalah sebelumnya:** nama kelompok hanya menempel pada item, sehingga kelompok yang belum punya item hilang dari Editor Visual dan dokumen.
  - **Penyimpanan:** kelompok kosong disimpan sebagai **baris judul**, yaitu baris `procurement_contract_items` tanpa uraian (`ProcurementContractItem::isHeading()`). Kalau kelompok itu kemudian diberi item, baris judulnya otomatis terhapus.
  - **Siapa yang membaca baris judul:** relasi baru `Procurement::itemRows()` memuat baris judul, dan hanya dipakai editor Rincian Item serta penggambar tabel. Relasi `contractItems()` tetap mengabaikannya, jadi DO, Keuangan, nilai setelah nego, dan jumlah item tidak berubah.
- **Draf Rincian Item tidak hilang saat pindah tab:** tab yang tidak aktif dilepas dari halaman. Sebelumnya, isian yang belum disimpan (misalnya kelompok baru) hilang dari tabel isian setelah membuka Editor Visual lalu kembali, padahal drafnya masih ada di belakang layar. Kini `ItemListCard` menerima `draft` (baris yang belum disimpan dari editor dokumen) dan membangun tabel dari draf itu (`rowsFromDraft`).
- **Test:** `ProcurementItemsTest`, yaitu `test_the_groups_can_be_lettered_in_lower_case_in_every_table`, `test_the_draft_table_tries_the_lettering_without_keeping_it`, `test_only_upper_or_lower_lettering_is_taken`, dan `test_a_group_without_items_yet_still_prints_its_heading`.

## Tabel Langkah Menyesuaikan Tablet

- **Masalah:** di tablet (misalnya iPad landscape) tabel langkah checklist berantakan. Kolom Langkah dan Yang Dibutuhkan terjepit dan teksnya saling menimpa. Penyebabnya: tata letak tabel aktif berdasarkan lebar layar (`md:`), padahal kolom utama menyempit karena ada panel kanan (Persetujuan/Status).
- **Perbaikan:** tabel kini memakai container query, yaitu menyesuaikan lebar kartunya sendiri.
  - Kartu tabel diberi `@container`.
  - Checklist semua tahapan dan tab Usulan (`checklist-steps.tsx`, `proposal-documents.tsx`) tampil sebagai tabel mulai lebar kartu `@4xl` (56rem).
  - Tabel Amandemen dan DO (`amendment-tab.tsx`, `delivery-order-tab.tsx`) mulai `@3xl` (48rem).
  - Di bawah lebar itu, tiap langkah tampil bertumpuk (nama → yang dibutuhkan → status → aksi), seperti di ponsel.
- **Hasil pengecekan:** di 1180px kartu selebar 513px tampil bertumpuk dan rapi. Di 1920px tetap tabel lima kolom.
- **Audit seluruh halaman di lebar tablet** (820/1024/1180px, plus 1440px untuk desktop): halaman dipindai otomatis untuk mencari teks yang saling menimpa, teks terpotong, dan halaman yang bisa digeser ke samping.
  - **Halaman bisa digeser ke samping** (sampai 241px) di Approval, Perencanaan, Pelaksanaan, Keuangan, Penunjukan PIC, Pengguna, Hak Akses, dan Laporan. Penyebabnya: `<main>` (`SidebarInset`) adalah flex item tanpa `min-w-0`, sehingga melebar mengikuti tabel lebar.
    - Perbaikan: `components/app-content.tsx` memberi `min-w-0`. Tabel lebar kini bergulir di kotaknya sendiri.
  - **Teks "Semua" terpotong** di filter. Lebar minimum dinaikkan:
    - Filter Tahun 6.5rem dan Bulan 8.5rem (`period-filter.tsx`).
    - Filter pilihan lain `min-w-28` (`procurement-filter-bar.tsx`, `contract-monitoring.tsx`, `monitoring/contracts.tsx`).
  - **Kolom identitas pengadaan** (`identity-panel.tsx`) kini menyesuaikan lebar kartu (2/3/4 kolom lewat `@container`), bukan layar, sehingga nilai panjang tidak terpotong di tablet.
  - **Baris daftar harga kontrak di tab DO** memakai grid 6 kolom mulai lebar kartu `@2xl`.

## Proses Request Penilaian Sekali Klik & Panel Kirim ke Penilai

- **Tombol Proses & Siapkan WhatsApp** ada di setiap request baru (panel Request Penilaian Vendor). Rutenya `vendor-assessments.requests.process` (`POST penilaian-penyedia/permintaan/{id}/proses`). Sekali klik:
  1. Membuat formulir penilaian dari data request, sama dengan isian otomatis form Buat Penilaian. Tgl Formulir memakai `VendorAssessmentRequestController::FORM_DATE` = 2024-06-10, seperti yang diisi form itu.
  2. Menautkan request ke penilaiannya, sehingga request pindah ke folder mitra.
  3. Membuat tautan tanda tangan untuk setiap lembar aktif lewat `AssessmentSigningService::issueForEverySheet()`. Penerimanya ditentukan `AssessmentForm::recipientFor()`:
     - **Lembar dengan pilihan nama:** nama pertama yang berlaku pada Tgl BASTP. Contohnya lembar Pengadaan: FIRMAN.
     - **Direksi Pekerjaan:** nama yang dipilih vendor di request, lengkap dengan nomornya. Kalau request tidak memilih, lembar ini dibuat tanpa penerima.
     - **Lembar tanpa daftar:** nama penilai tetap, tanpa nomor.
  4. Membuka halaman penilaian.
  - Request yang sudah diproses hanya diarahkan ke penilaiannya, tanpa membuat penilaian kedua.
  - Tombol **Buat Penilaian** (cek dan ubah isian dulu) tetap ada.
- **Panel Kirim ke Penilai** (`components/assessment-send-panel.tsx`) ada di halaman penilaian, di atas tab. Isinya satu daftar semua lembar: penerima dan nomornya, status, tombol **Kirim WA**, dan ✎ untuk membuka tab lembar itu (tempat mengganti penerima).
  - **Status:** Sudah ditandatangani / Sudah dinilai / Belum ada tautan / Tautan dibatalkan / Kedaluwarsa / Sudah dibuka penilai / Penerima belum dipilih / Nomor belum ada / Terkirim, belum dibuka / Siap dikirim.
  - **Kirim berikutnya:** membuka WhatsApp untuk lembar berikutnya yang belum dikirim dari browser ini. Tanda "terkirim" disimpan di localStorage `vendor-assessment-sent:{id}`, karena database tidak mencatat pengiriman lewat WhatsApp.
  - **Siapkan Tautan (n):** muncul kalau ada lembar tanpa tautan. Rutenya `vendor-assessments.links.store-all` (`POST penilaian-penyedia/{id}/tautan`). Hanya membuat tautan yang belum ada; tautan yang sudah ada dan lembar yang sudah dinilai penuh tidak disentuh.
  - Lembar yang sudah dinilai penuh dihitung selesai dan tidak dikirimi.
- **Pesan WhatsApp disatukan:** `VendorAssessmentInvitation::whatsappUrl()` kini memuat Tanggal Kontrak, Amandemen, Tanggal BASTP, Denda, dan Lembar. Panel per lembar memakai `whatsapp_url` dari server, bukan lagi menyusun pesan sendiri.
- **Database:** tidak ada perubahan.
- **Test:** `VendorAssessmentRequestProcessTest`.

## Direksi Pekerjaan pada Request Penilaian Vendor

- **Field baru di form request publik** (`/permintaan-penilaian`): vendor wajib memilih **Direksi Pekerjaan**, letaknya tepat di bawah Tgl BAPP/BASTP.
  - Pilihannya hanya nama (tanpa nomor HP), diambil dari daftar nama lembar **Direksi Pekerjaan** (Data Master → Lembar Penilai, kode `direksi-pekerjaan`).
  - Disimpan di `vendor_assessment_requests.work_director_name`.
  - Tampil di panel Request Penilaian Vendor.
  - Saat dibuat penilaian, nama ini masuk ke **Catatan internal** ("Direksi Pekerjaan: …"), seperti Termin dan Nilai Tagihan.
- **Nama disaring menurut Tgl BAPP/BASTP:** tiap nama di **Pilihan Nama Penilai** bisa diberi tanggal **Tidak muncul s.d. BASTP**.
  - Disimpan di JSON `assessment_forms.assessor_hidden_until`, dengan kunci nama tanpa nomor HP.
  - Nama tidak ditawarkan bila Tgl BASTP ≤ tanggal itu. Selama Tgl BAPP/BASTP belum diisi, pilihan Direksi Pekerjaan **terkunci** ("Isi Tgl BAPP/BASTP terlebih dahulu"), supaya nama yang dibatasi tanggal tidak sempat tampil.
  - Isi awal dari migrasi: WINANDO PANDIANGAN s.d. 1 Jul 2026, TAUHID ALI s.d. 1 Agu 2026, TANWIR s.d. 1 Sep 2026.
  - Saringan yang sama berlaku di dropdown Nama Penilai lembar Direksi Pekerjaan dan halaman tanda tangan WhatsApp.
  - Validasi `App\Rules\OfferedWorkDirector`.
- **Dihapus (salah tangkap maksud):** field Direksi Pekerjaan di form admin Buat/Ubah Penilaian, beserta sinkronisasinya ke lembar dan cetakan.
  - Kolom `vendor_assessments.work_director_name` dibuang lewat migrasi `drop_work_director_from_vendor_assessments`. Migrasi aman dijalankan, baik kolom itu sudah ada maupun belum.
- **Helper dan komponen:** `AssessmentForm::nameOf()`, `optionsFor()`, `namesWithHiding()`, `workDirectorSheet()`; `components/work-director-select.tsx`; field `list` `MasterDataPage` dengan `rowDate`.
- **Test:** `VendorAssessmentRequestTest`, `VendorAssessmentWorkDirectorTest`.

## Pratinjau PDF di Ponsel

- **Masalahnya:** Chrome di Android tidak punya penampil PDF di dalam halaman, dan browser di iOS hanya menampilkan halaman pertama. Desktop Chrome yang disetel "unduh PDF" juga tidak bisa menampilkannya.
- **Perilaku sekarang:** di kondisi tersebut, tab Pratinjau Cetak menggambar PDF memakai **pdf.js** (`pdfjs-dist`, komponen `pdf-canvas-viewer.tsx`).
  - Setiap halaman digambar ke canvas saat mendekati layar, agar ringan di HP.
  - Tombol **Buka PDF** dan **Unduh PDF** tetap tersedia di atas halaman.
  - Bila pdf.js tidak bisa berjalan, misalnya di browser yang sangat lama, yang tampil hanya kedua tombol itu.
  - Di laptop/desktop tetap memakai penampil PDF bawaan browser.
  - pdf.js dimuat terpisah, hanya saat dibutuhkan.

---

# Dashboard

## Dashboard per Peran

Setiap peran punya dashboard sendiri yang hanya menampilkan data yang berkaitan dengan perannya (`App\Support\RoleDashboard`).

- **Penentuan profil:** siapa pun yang punya hak **lihat semua pengadaan** (`procurement.view-all`) mendapat dashboard **manajemen** (Administrator, TL Pengadaan, TL ICC, atau peran lain yang diberi hak itu di Hak Akses). Peran lain mendapat dashboard sesuai perannya:

| Profil | Kartu | Daftar |
|---|---|---|
| Pengusul | Usulan Saya, Menunggu Persetujuan, Dikembalikan, Sedang Diproses, Selesai (+ Usulan Tim Menunggu Anda bila ia TL) | Usulan Saya |
| Direksi Pekerjaan | Usulan Menunggu Anda, Pengadaan Bidang Berjalan, Selesai, Nilai HPE Berjalan | Pengadaan Bidang Anda |
| PIC Perencana | Perlu Dilengkapi, Menunggu Approval ICC, Dikembalikan, Disetujui | Pengadaan yang Anda Rencanakan (yang dikembalikan di atas) |
| PIC Pelaksana | Pekerjaan Berjalan, Siap Diserahkan, Di Keuangan, Selesai | Pengadaan yang Anda Laksanakan (urut target) |
| Keuangan | Menunggu Pembayaran, Nilai Tagihan Menunggu, Dibayar Bulan Ini, Total Sudah Dibayar | Tagihan Menunggu Pembayaran |
| Lainnya | Pengadaan Saya, Berjalan, Selesai | Pengadaan Saya |

- **Bagian yang sama untuk semua profil:**
  - Kotak **Perlu Tindakan Anda**.
  - Diagram **Posisi Pengadaan** (Usulan → Perencanaan → Pelaksanaan → Keuangan → Selesai).
  - Daftar pengadaan dengan tahap, keterangan menunggu apa, status, dan target.
  - Tombol **Buat Usulan Pekerjaan** / **Buat Pengadaan** bagi yang berhak.
- **Sumber angka:** semua angka diambil dari pengadaan yang boleh dilihat pengguna (`visibleTo`). Rincian manajemen (per direksi, unit, metode, anggaran, PIC) hanya dihitung dan dikirim untuk profil manajemen.
- **Dashboard manajemen** tetap seperti sebelumnya, ditambah diagram Posisi Pengadaan.

## Dashboard Manajemen

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

### Perlu Tindakan Anda (daftar tugas per akun)

Di bagian atas dashboard tampil daftar tugas sesuai akun yang login. Setiap baris bisa diklik dan langsung menuju halaman tempat tugas itu dikerjakan:

| Kartu | Muncul untuk | Isi | Klik menuju |
|---|---|---|---|
| Menunggu Persetujuan Anda | Pemegang hak *Tinjau perencanaan* | Perencanaan berstatus Menunggu Persetujuan (terlama di atas) | Detail pengadaan (kartu Approval) |
| Perlu Penunjukan PIC | Pemegang hak *Tunjuk PIC* | Pengadaan aktif yang belum punya PIC Perencana/Pelaksana | Penunjukan PIC (terfilter ke pengadaan tsb.) |
| Tugas Perencanaan Anda | PIC Perencana yang ditunjuk | Perencanaan belum diajukan / dikembalikan untuk revisi (revisi di atas) | Detail pengadaan |
| Tugas Pelaksanaan Anda | PIC Pelaksana yang ditunjuk | Pengadaan yang perencanaannya disetujui, beserta jumlah tahapan pelaksanaan yang belum selesai | Detail pengadaan |

- Pengadaan selesai, batal, atau terhapus tidak dihitung. "Lihat semua" hanya tampil bila menu tujuannya diizinkan di Hak Akses.
- Bila tidak ada tugas, tampil pesan "Tidak ada tugas yang menunggu Anda".

---

# Standar Orientasi Kertas Template Dokumen

- **Berita Acara Negosiasi (SPPL dan Kontrak/SPK), HPE, dan UPB** (umum maupun PJ) menggunakan format kertas **Lanskap (A4 Landscape)** (`@page { size: A4 landscape; ... }`).
- **Seluruh dokumen lainnya** (TOR, RKS, Nota Dinas, Surat Pesanan, Lampiran Surat Pesanan, dsb.) secara konsisten menggunakan format kertas **Potret (A4 Portrait)** (`@page { size: A4 portrait; margin: 20mm 15mm; }`).
- Pengaturan cetak HTML dan PDF secara otomatis mendeteksi konfigurasi `@page` pada dokumen sehingga orientasi kertas (landscape vs portrait) dan footer tercetak dengan koordinat yang presisi pada browser maupun Dompdf.
- Dialog pratinjau template dokumen pada Data Master dan canvas editor visual menyesuaikan lebar bingkai secara dinamis (`max-w-[297mm]` untuk dokumen landscape dan `max-w-[210mm]` untuk dokumen portrait).

---

# Delivery Order (DO) — Kontrak KHS

- **Kapan tampil:** tab **DO Delivery Order** (di sebelah Amandemen) hanya muncul untuk format **SPK / PJ** dengan jenis kontrak **KHS**. DO baru bisa diajukan setelah dokumen kontrak (SPK / Surat Perjanjian) dibuat. Satu kontrak bisa punya banyak DO, dan beberapa bisa berjalan bersamaan.
- **Daftar Harga Satuan Kontrak** (diisi Laksdan: PIC Pelaksana / TL Pengadaan):
  - Berisi material/jasa dari lampiran kontrak: kelompok (mis. MATERIAL MAK 8M 453 C), uraian, part number, satuan, dan harga satuan.
  - Setiap DO menarik item dari daftar ini.
  - Uraian dan harga disalin ke DO, sehingga perubahan daftar di kemudian hari tidak mengubah DO yang sudah dibuat.
- **Alur:**
  1. **Pengusul → Rendan:** mengisi nomor dan tanggal nota dinas usulan, mengunggah dokumennya, dan memilih item + volume dari daftar harga.
  2. Disetujui **TL**, lalu **Asman** bidang. Bila dikembalikan, pengusul memperbaiki lalu mengajukan ulang.
  3. **Rendan → Laksdan:** PIC Perencana (atau TL ICC/TL) mengisi nomor dan tanggal **Nota Dinas DO**, lalu meneruskan ke Laksdan.
  4. **Laksdan:** nomor DO terisi **otomatis**, berurutan untuk seluruh DO kontrak KHS (format "DO" di Data Master Format Nomor Kontrak, contoh D26252.DO/612/UPKD/2026), dan tetap bisa dikoreksi.
     - Laksdan mengisi tanggal DO, kode rekanan, uraian pekerjaan, tempat penyerahan, dan waktu penyerahan (hari), dan bisa menyesuaikan item.
     - Klik **Terbitkan DO** → dokumen DO digenerate dan status menjadi "DO Terbit".
- **Dokumen DO** (template `kontrak/delivery-order.html`), mengikuti contoh DO:
  - Header: nomor & tanggal DO, nomor & tanggal kontrak, Kepada (mitra + alamat), kode rekanan, uraian pekerjaan.
  - Tabel item dikelompokkan A, B, … (No, Uraian, Part Number, Volume, Satuan, Harga Satuan, Jumlah Harga), PR, total, dan terbilang.
  - Ketentuan 1–6 (tempat dan waktu penyerahan, jaminan, acuan kontrak).
  - Tanda tangan Direktur mitra dan **Manager** (Data Master Manager).
  - Dokumen bisa dibuka di editor, dipratinjau, dan diunduh.
- **Hak:** TL ICC dan PIC Perencana tidak bisa mengurus daftar harga atau menerbitkan DO; PIC Pelaksana tidak bisa mengisi Nota Dinas DO.
- **Migrasi:** `create_procurement_delivery_orders_table` (tabel DO, item DO, daftar harga kontrak, format nomor DO, jenis dokumen `delivery-order`, template).
- **Test:** `DeliveryOrderTest`.

# Amandemen Kontrak

> **Revisi alur Amandemen (Pengusul → TL → Asman → Laksdan):**
> 1. **Pengusul → Pengadaan:** mengisi **nomor dan tanggal nota dinas usulan** dan tanggal notulen, lalu mengunggah **dokumen nota dinas usulan** dan **notulen** (wajib) serta **surat permohonan mitra** (opsional). Daftar hadir, nomor/tanggal surat permohonan, dan perubahan pasal tidak lagi diisi pengusul.
> 2. Disetujui **TL**, lalu **Asman** bidang. Bila dikembalikan, pengusul memperbaiki dan mengajukan ulang.
> 3. **Laksdan** (PIC Pelaksana / TL Pengadaan / Administrator), tanpa singgah di perencana:
>    - Nomor Amd terisi **otomatis** saat Asman menyetujui, berurutan dengan nomor amandemen kontrak lainnya (format Amd), dan tetap bisa dikoreksi.
>    - Laksdan mengisi tanggal amandemen dan memilih **pasal yang diubah dari dokumen kontrak**. Teks "Semula berbunyi" terisi otomatis dari pasal kontrak (SPK – Syarat & Ketentuan (Pasal) / Surat Perjanjian), lalu Laksdan mengisi "Menjadi".
>    - Klik **Terbitkan Amandemen** → dokumen Amandemen I/II/… digenerate dan status menjadi "Amandemen Terbit".
> - Persetujuan akhir TL Pengadaan dan hak "Setujui / tolak amandemen kontrak" dihapus. Amandemen yang tadinya menunggu TL Pengadaan kini menunggu Laksdan.
> - **Narasi template amandemen** disesuaikan dengan kontrak:
>   - Para pihak disebut **PIHAK KESATU / PIHAK KEDUA** dengan uraian seperti di kontrak.
>   - Dasar amandemen: kontrak beserta lampirannya, Nota Dinas Usulan, dan Notulen Rapat; Surat Permohonan Amandemen dari mitra hanya dicantumkan bila diunggah (placeholder `{{butir_surat_permohonan}}`).
> - **Migrasi:** `hand_amendments_to_laksdan`. **Test:** `AmendmentTest`.

> **Revisi format & isian perubahan pasal Amandemen:**
> - **Pilihan per ayat:** pasal kontrak ditawarkan per ayat (mis. "PASAL 8 MASA GARANSI Ayat 2"). Teks semula terisi lengkap dengan butir a./b./1./- sebagai baris menjorok (`AmendmentService::contractArticles`).
> - **Isian lebih sederhana:**
>   - Laksdan cukup memilih ayat; teks *Semula* tampil sebagai pratinjau baca-saja, dengan tombol "Koreksi teks semula" bila perlu.
>   - Kolom *Menjadi* sudah terisi teks semula, jadi tinggal diubah. Satu baris = satu butir; butir dimulai "a." / "1." / "-", dan spasi di depan membuatnya menjorok.
>   - Ada pratinjau "Tampilan di dokumen".
> - **Format dokumen rapi:**
>   - Pasal ditulis tebal kapital dan ayat ditambahkan.
>   - Teks Semula/Menjadi disusun sebagai tabel bernomor menjorok (`table.teks`), seperti di kontrak.
>   - PASAL 2 Penutup tidak terpotong halaman.
> - **Setiap halaman PDF** (`DocumentPdfRenderer::stampAmendmentPages`):
>   - logo di atas;
>   - footer miring "Amandemen {ke} {nomor} tanggal {tgl kontrak}" beserta nama pengadaan;
>   - kolom "Paraf Pihak 1 / Pihak 2".
> - `LAYOUT_VERSION` disertakan pada cache PDF agar PDF lama digambar ulang.
> - **Data demo:** kolom "Menjadi" kini berisi ayat yang sama dengan jangka waktu (hari kalender) diperpanjang 30 hari dan tanggal batasnya ikut diundur, bukan lagi satu kalimat yang tidak berhubungan dengan pasalnya (`DemoProcurementSeeder::extendedTerm`). Ringkasan amandemen di tab memakai tampilan bernomor yang sama dengan dokumen.
> - Template disinkron lewat migrasi `hand_amendments_to_laksdan`. Server yang sudah menjalankannya cukup menjalankan `php artisan templates:sync kontrak.amandemen`.

> **Menu Monitoring:** grup sidebar "Pengawasan" diganti menjadi **Monitoring**, berisi:
> - **Monitoring Pengadaan** (halaman lama);
> - **Monitoring Amandemen** (`/monitoring/amandemen`);
> - **Monitoring Delivery Order** (`/monitoring/delivery-order`).
>
> Keduanya memakai hak `menu.monitoring` dan cakupan data yang sama dengan Monitoring Pengadaan, dengan ringkasan status, filter, dan pencarian. **Test:** `ContractMonitoringTest`.

Amandemen punya **tab tersendiri** di detail pengadaan (tab *Amandemen*). Langkah checklist pelaksanaan "Amandemen" yang lama dinonaktifkan; centang lamanya tetap tersimpan sebagai histori.

**Siapa dan kapan**
- Diajukan oleh **pengusul / user**: pembuat usulan, pemegang hak Usulkan Pekerjaan di bidang yang sama, atau admin.
- Tombol **Ajukan Amandemen** muncul setelah dokumen kontrak (SPK, Surat Perjanjian, atau Surat Pesanan) dibuat, sampai pengadaan selesai. Tombol tidak muncul bila pengadaan Batal.
- Hanya satu amandemen boleh terbuka sekaligus. Amandemen bisa lebih dari sekali (Amandemen I, II, dst.).

**Isian pengusul**
| Dokumen (unggah PDF/JPG/PNG) | Isian |
|---|---|
| Notulen Rapat | tanggal notulen (dipakai di dokumen) |
| Daftar Hadir | — |
| Nota Dinas Amandemen | nomor dan tanggal nota dinas |
| Surat Permohonan Amandemen | nomor dan tanggal surat |

Isian lain:
- Nomor amandemen: otomatis dari format **Amd**, tetap bisa dikoreksi.
- Tanggal amandemen dan tanggal kontrak (default: tanggal kontrak dibuat).
- Daftar **perubahan pasal**: pasal/ayat, teks *Semula berbunyi*, dan teks *Menjadi*; bisa beberapa baris.

Amandemen baru bisa diajukan bila semua dokumen dan isian lengkap; yang masih kurang ditampilkan sebagai daftar.

**Alur persetujuan** (tidak lewat perencana / TL ICC)
1. Pengusul mengajukan. Saat itu dokumen keluaran dibuat otomatis, sehingga penyetuju membaca dokumen yang akan disepakati.
2. **TL** atasan pengusul menyetujui. Bila pengusul tidak punya TL, atau TL-nya Direksi Pekerjaan bidang itu, langkah ini dilewati.
3. **Asman** (Direksi Pekerjaan) bidang menyetujui.
4. **TL Pengadaan** memberi persetujuan akhir (hak baru `procurement.approve-amendment`, default TL Pengadaan) → status *Disetujui*.

Aturan tambahan:
- **Dikembalikan:** catatan wajib diisi. Pengusul memperbaiki lalu mengajukan ulang (revisi bertambah) dan dokumen dibuat ulang.
- **Penyetuju:** tidak ada yang menyetujui amandemennya sendiri, dan admin bisa mewakili.
- **Notifikasi:** dikirim ke penyetuju berikutnya; keputusan akhir dikirim ke pengusul dan PIC Pelaksana.
- **Riwayat Approval:** mencatat Amandemen Diajukan / Disetujui TL / Disetujui Asman / Disetujui TL Pengadaan / Dikembalikan.

**Dokumen keluaran**
- Template: `resources/document-templates/kontrak/amandemen.html` (manifest `kontrak.amandemen`, jenis dokumen `amandemen-kontrak`), mengikuti contoh Amandemen I Surat Perjanjian.
- Jenis kontrak menyesuaikan format: SURAT PERJANJIAN (PJ), SURAT PERINTAH KERJA (SPK), atau SURAT PESANAN (SPPL).
- Isi Pasal 1 *Perubahan Ketentuan* disusun dari baris perubahan. Teks pengguna di-escape.
- Dokumen tersimpan sebagai dokumen pengadaan (`procurement_documents.procurement_amendment_id`). Dokumen bisa dibuka di editor dan pratinjau yang sama, serta di-regenerate dengan data amandemennya.
- Dokumen tidak bisa dibuat dari tombol generate biasa.
- Placeholder baru tercantum di katalog template (awalan "Amandemen:").

**Penomoran**
- Format **Amd** (KDD054.Amd/612/UPKD/2026) ada di Data Master Format No Kontrak, dengan saklar *Untuk Nomor Amandemen*. Kode format amandemen tidak diubah menjadi huruf besar.
- Format amandemen tidak ditawarkan sebagai format kontrak pengadaan, dan validasi menolaknya.

**Teknis**
- Tabel `procurement_amendments`, `procurement_amendment_changes`, `procurement_amendment_files`. Berkas disimpan di disk privat `amendments/{id}` dan hanya bisa dibuka oleh yang berhak melihat pengadaan.
- Layanan `App\Services\AmendmentService`, controller `Procurements\AmendmentController`, rute `procurements.amendments.*`.
- Test: `AmendmentTest`.

---

# Pindah Akun (Administrator)

Tombol **Pindah Akun** di sudut kanan atas header, hanya untuk Administrator. Administrator memilih pengguna dari daftar yang bisa dicari, lalu bekerja sebagai pengguna itu tanpa kata sandinya. Selama mode ini, header menampilkan penanda "Mode Pindah Akun oleh …" dan tombol **Kembali ke Administrator**.

Pengamanan:

- **Pelaku:** hanya akun Administrator yang aktif dan sedang masuk sebagai dirinya sendiri. Ini bukan hak yang bisa diberikan lewat layar Hak Akses.
- **Target:** bukan Administrator lain, bukan akun nonaktif atau terhapus, bukan diri sendiri.
- **Konfirmasi kata sandi:** kata sandi Administrator diminta, lalu berlaku 15 menit untuk perpindahan berikutnya (`Impersonation::CONFIRMATION_TIMEOUT`).
- **Sesi dan CSRF:** ID sesi dan token CSRF diperbarui setiap kali pindah dan kembali.
- **Konfirmasi kata sandi tidak terbawa:** konfirmasi kata sandi sesi (`auth.password_confirmed_at`) dihapus, sehingga pengaturan keamanan akun yang dipakai tetap butuh kata sandi pemilik akun itu.
- **Tidak bisa berantai:** dari sesi pindah akun tidak bisa pindah akun lagi.
- **Diblokir selama mode pindah akun** (middleware `RestrictImpersonatedSession`): ubah profil, hapus akun, ganti kata sandi, halaman keamanan, 2FA, passkey, konfirmasi kata sandi, kelola pengguna, dan Hak Akses.
- **Catatan sesi tidak cocok:** jika catatan pindah akun di sesi tidak sesuai dengan akun yang sedang masuk, catatan itu dibuang.
- **Kembali dengan aman:** jika Administrator dinonaktifkan atau kehilangan peran selama sesi, sesi dikeluarkan sepenuhnya.
- **Audit:** setiap perpindahan tercatat di tabel `impersonation_logs` (admin, akun, IP, user agent, mulai, selesai). Keluar (logout) saat mode pindah akun juga menutup catatannya.
- **Throttle:** pindah 10 kali/menit, daftar pengguna 60 kali/menit.
- **Kode dan rute:** rute `impersonation.index` / `store` / `destroy`, logika di `App\Support\Impersonation`, test `ImpersonationTest`.

---

# Data Demo

`DemoProcurementSeeder` (tidak termasuk `DatabaseSeeder`) membuat **72 pengadaan dummy** yang menempuh alur sebenarnya dari usulan staf sampai pembayaran. Setiap langkah dicatat dengan waktu mundur di Riwayat Approval.

| Posisi | Jumlah |
|---|---|
| Usulan menunggu TL / menunggu Asman / dikembalikan | 5 / 5 / 3 |
| Usulan disetujui, perlu penunjukan PIC Perencana (TL ICC) | 4 |
| Penyusunan perencanaan / menunggu approval ICC / dikembalikan ICC | 8 / 6 / 4 |
| Perencanaan disetujui, perlu penunjukan PIC Pelaksana (TL Pengadaan) | 2 |
| Pelaksanaan / siap diajukan / menunggu approval TL Pengadaan / dikembalikan TL Pengadaan | 8 / 2 / 3 / 1 |
| Di Keuangan (Penyerahan & Pembayaran sebagian dicentang) / lunas & selesai | 8 / 10 |
| Batal | 3 |

- **Pengusul:** akun Pengusul dari `DivisionUserSeeder`, bergiliran. Usulan melewati TL timnya, lalu Asman bidangnya.
- **Penomoran:** usulan memakai nomor `USL/…`. Setelah format kontrak diisi, nomornya menjadi nomor kontrak (SPK, PJ, atau SPPL).
- **Format kontrak:** bergiliran antara SPK Barang, SPK Jasa, PJ Barang, SPPL, dan PJ Jasa, lengkap dengan metode, jenis kontrak, mitra, rekening, jangka waktu, garansi, dan nilai nego.
- **Dokumen:** TOR, Nota Dinas Usulan, RKS, HPE, UPB, serta dokumen kontrak (SPK, Surat Perjanjian, atau Lampiran SP) dibuat dari template. Karena kontraknya dibuat, syarat Penyerahan dan Pembayaran terbaca dari kontrak itu.
- **Penyetuju perencanaan:** TL ICC menunjuk PIC Perencana dan menyetujui perencanaan; TL Pengadaan menunjuk PIC Pelaksana dan menyetujui pelaksanaan. Bila akun salah satunya tidak ada, Administrator yang mengisinya.
- **Penanda:** semua data demo memakai catatan "Data demo (DemoProcurementSeeder)." Menjalankan ulang seeder menghapus data demo sebelumnya (hanya yang bertanda itu), lalu membuat ulang.
- **Menjalankan:** `php artisan db:seed --class=DemoProcurementSeeder`, setelah `php artisan migrate --seed`. Test: `DemoProcurementSeederTest`.

---

# Alur Penyerahan & Pembayaran dengan Persetujuan

> Menggantikan tombol lama "Pembayaran Lunas & Tutup Pengadaan" oleh Keuangan.

**Alur**

| Tahap | Dikerjakan | Persetujuan | Bila dikembalikan |
|---|---|---|---|
| Penyerahan | PIC Pelaksana (atau TL Pengadaan) mengunggah berkas tab Penyerahan, lalu PIC Pelaksana **Ajukan ke TL Pengadaan** | TL Pengadaan (hak baru *Setujui / kembalikan penyerahan*, bawaan TL Pengadaan) | Catatan wajib, kembali ke PIC Pelaksana (revisi bertambah) |
| Pembayaran | Staf Keuangan mengunggah berkas tab Pembayaran, lalu **Ajukan Pembayaran** | TL atasan pengaju (TL Keuangan), lalu Asman bidang TL tersebut (Asman Business Support) | Catatan wajib, kembali ke staf Keuangan |

- Persetujuan pelaksanaan oleh TL Pengadaan kini membuka **Penyerahan**. Keuangan belum diberi tahu pada saat itu; notifikasi "Tagihan menunggu pembayaran" dikirim setelah Penyerahan disetujui.
- **Persetujuan Asman** = Pembayaran Lunas. Pengadaan otomatis ditutup (status Selesai), dan notifikasi dikirim ke pengusul, TL, Asman bidang, PIC Pelaksana, serta TL Pengadaan / ICC.
- **Penentuan penyetuju pembayaran:**
  - TL dan bidang Asman ditentukan saat pengajuan dan disimpan di `payment_team_leader_id` / `payment_work_director_id`.
  - Pengaju tanpa TL, atau dengan TL yang juga Asman bidang itu, langsung ke Asman.
  - Akun tanpa bidang tidak bisa mengajukan; muncul pesan agar menghubungi Administrator.
  - Tidak ada yang memutuskan pengajuannya sendiri. Administrator dapat mewakili.
- **Checklist terkunci** selama ditinjau dan setelah disetujui. Berkas Penyerahan hanya bisa diubah pihak pelaksanaan; berkas Pembayaran hanya oleh Keuangan.
- **Siapa yang bisa mengunggah:**
  - Penyerahan: PIC Pelaksana pengadaan itu atau TL Pengadaan, selama belum diajukan atau saat dikembalikan.
  - Pembayaran: akun yang memegang hak *Proses pembayaran* (Keuangan).
  - Akun lain (mis. TL Pengadaan di tab Pembayaran) hanya melihat dan mengunduh template. Panel langkah kini menampilkan kotak "Tidak dapat mengunggah di sini" beserta alasannya, sehingga tombol unggah yang tidak ada tidak membingungkan.
- **Kolom kanan Persetujuan** kini berisi 5 tahap: Perencanaan, Pelaksanaan, Penyerahan, Pembayaran (dengan Ajukan / Setujui / Kembalikan), lalu Riwayat Approval. Riwayat mencatat Penyerahan / Pembayaran Diajukan, Disetujui, dan Dikembalikan.
- **Menu:**
  - **Penyerahan** (`/penyerahan`, hak *Menu Penyerahan*, bawaan TL Pengadaan, TL ICC, PIC Pelaksana).
  - **Pembayaran** (`/pembayaran`, dulu menu Keuangan; tautan `/keuangan` dialihkan).
  - Keduanya memakai papan yang sama (`components/finance-board.tsx`): 4 angka ringkas; tab *Perlu Diproses* / *Sudah Disetujui (Dibayar)*; daftar ringkas 15 per halaman dengan pencarian; status berwarna dan progres berkas per baris.
  - Baris memakai *container query*: di area sempit (tablet, sidebar disematkan) nama tampil penuh di baris pertama, di area lebar menjadi kolom tabel.
- **Menu Approval:** antrean baru **Penyerahan** (pemegang hak setujui penyerahan) dan **Pembayaran** (TL Keuangan / Asman yang terlibat, `App\Support\PaymentQueue`). Dashboard mendapat kartu "Penyerahan / Pembayaran Menunggu Persetujuan Anda".
- **Hak akses Keuangan:**
  - Tidak lagi *Usulkan pekerjaan* dan *Menu Usulan Pekerjaan*.
  - Mendapat *Lihat seluruh pengadaan* (baca saja; dashboard tetap dashboard Keuangan).
  - Semua tetap dapat diatur ulang di Hak Akses.
- **Penyerahan adalah tahap tersendiri**, bukan bagian Keuangan:
  - Terbuka saat status **Terkontrak** (pelaksanaan disetujui TL Pengadaan).
  - PIC Pelaksana mengunggah berkas, lalu mengajukan ke TL Pengadaan.
  - Persetujuan TL Pengadaan membuka tahap Pembayaran; sebelum itu Pembayaran **terkunci**.
  - Label tahap menjadi "Penyerahan" lalu "Pembayaran".
  - ZIP arsip memakai folder `4. Penyerahan/` dan `5. Pembayaran/`.
  - Papan Monitoring menganggap Penyerahan selesai hanya bila sudah disetujui TL Pengadaan.
- **Perbaikan data (migrasi `reopen_incomplete_handovers_of_running_jobs`):** pengadaan Terkontrak yang belum lunas, pembayarannya belum diajukan, dan berkas Penyerahan wajibnya belum lengkap dikembalikan ke Penyerahan *Belum Diajukan*. Yang berkasnya lengkap tetap disetujui. **Test:** `ReopenIncompleteHandoverTest`.
- **Data lama** (migrasi `add_handover_and_payment_approvals`):
  - Pengadaan yang sudah di Keuangan dianggap Penyerahan disetujui, sehingga staf tinggal mengajukan pembayaran.
  - Yang sudah lunas dianggap Pembayaran disetujui.
- **Test:**
  - `FinanceStageTest`: alur lengkap, pengembalian, penyetuju, langsung ke Asman, antrean Approval.
  - `FinanceUploadTest`: berkas Penyerahan milik pelaksana.
  - `EndToEndFlowTest`: usulan sampai lunas.
  - `ApprovalQueueTest`, `RoleDashboardTest`, `TeamLeaderApprovalTest`.

---

# Halaman Approval Disederhanakan

- **Tujuan:** halaman Approval hanya untuk meninjau lalu memutuskan, jadi tampilannya dibuat ringkas.
- **Header:** satu kalimat, "Pilih pengajuan, periksa berkasnya, lalu Setujui atau Kembalikan".
- **Antrean dan pencarian:**
  - Tab antrean (Perencanaan, Pelaksanaan, Penyerahan, Pembayaran, Amandemen, DO) beserta jumlah yang menunggu, dan tab Menunggu Keputusan / Riwayat Keputusan.
  - Filter lengkap diganti satu kotak pencarian (nomor atau nama).
- **Daftar kartu ringkas** (15 per halaman): nama pekerjaan; nomor dan bidang serta mitra; pengaju dan waktu; nilai; label "Revisi ke-n". Satu tombol **Tinjau & Putuskan** (atau **Lihat** bila bukan keputusan Anda).
- **Panel tinjauan** (panel samping, layar penuh di tablet / ponsel):
  1. Ringkasan dan catatan revisi sebelumnya.
  2. **Periksa berkas:** pratinjau PDF / gambar langsung di panel, beralih per berkas.
     - Perencanaan / Pelaksanaan: unggahan dokumen tervalidasi tahap itu, atau draf bila belum diunggah.
     - Penyerahan / Pembayaran: berkas checklist tab tersebut.
  - **Pratinjau leluasa:**
    - Panel selebar hampir seluruh layar (maks. 1400 px), dengan tombol **Layar penuh / Perkecil**.
    - Daftar berkas berada di kolom kiri (nama terbaca utuh), dan pratinjau mengisi seluruh tinggi panel (1440×900: ±1100×650 px).
    - Ada tombol **Buka di tab baru** untuk berkas yang sedang dilihat.
    - Di tablet / ponsel, daftar berkas menjadi deretan tombol di atas pratinjau.
  3. **Putuskan:** tombol utama Setujui (label sesuai tahap, mis. "Setujui & Teruskan ke Pembayaran", "Setujui: Lunas & Tutup Pengadaan") dan "Kembalikan untuk Revisi" (catatan wajib). Keputusan memakai rute yang sama dengan kartu Persetujuan di detail pengadaan.
- Amandemen dan DO tetap dibuka di tab masing-masing karena isiannya lebih panjang.
- **Teknis:** prop `reviews` (dibangun di `ApprovalQueueController::review()` / `papers()`); komponen `components/approval-review.tsx` (`ReviewList`, panel tinjauan). **Test:** `ApprovalQueueTest::test_each_submission_carries_what_the_reviewer_reads_before_deciding`.

---

# Logo & Nama Aplikasi SIM-SCM UP KENDARI

- Nama aplikasi: **SIM-SCM UP KENDARI**, kepanjangan **Sistem Informasi Monitoring Supply Chain Management**. Kepanjangan ini tampil sebagai judul halaman depan dan di bawah nama pada halaman masuk. Dipakai pada `APP_NAME` (judul tab browser), sidebar, header halaman depan, halaman masuk, halaman error, dan dashboard.
- **Logo** dari `public/logo/app-logo.png`. Berkas asli membawa latar kotak-kotak abu-putih sebagai piksel (tidak transparan), sehingga dibuat dua turunan berlatar transparan. Centang putih di perisai dipertahankan, dan berkas asli tidak diubah.
  - `public/logo/app-logo-clean.png`: logo lengkap, dipakai di konten halaman depan (`welcome.tsx`), tinggi 64 px di ponsel dan 80 px di desktop, rata kiri sejajar judul.
  - `public/logo/app-logo-mark.png`: ikon saja (dokumen, perisai, lengkung). Dipakai di kotak putih sidebar dan header halaman depan, di samping teks "SIM-SCM / UP KENDARI".
- Komponen: `components/app-mark.tsx` (`AppMark`, `AppLogoFull`) dan `components/app-logo.tsx`. Logo PLN (`AppLogoIcon`) tetap dipakai di dokumen dan halaman masuk.

---

# Tombol Persetujuan Terlihat di Layar Kecil

- **Masalah:** di layar di bawah 1024px (tablet, atau laptop dengan skala tampilan 125–150%), kolom kanan detail pengadaan turun ke bawah semua tab. Kartu Persetujuan beserta tombol Ajukan / Setujui / Kembalikan berada beberapa layar di bawah (mis. 900px: tombol Setujui di y≈2.530).
- **Perbaikan:** di bawah `lg`, kedua kolom dilebur menjadi satu daftar (`max-lg:contents`) dengan urutan:
  1. Identitas Pengadaan
  2. **Persetujuan**
  3. Tab tahapan
  4. Status Progres dan Penunjukan PIC
- Hasilnya, tombol persetujuan langsung terlihat setelah identitas (900px: y≈749; ponsel 375px: y≈952).
- **Desktop (≥ 1024px) tidak berubah:** kolom kanan tetap di samping dan tetap menempel saat digulir.
- **Teknis:** `pages/procurements/show.tsx` (urutan `max-lg:order-*`); `ApprovalPanel` menerima `className`.
- **Kartu tidak lagi terpotong di layar pendek (≥ 1024px):**
  - Kolom kanan yang menempel dibatasi setinggi layar. Sebelumnya kartu di dalamnya ikut menyusut, sehingga tahap Pembayaran dan tombolnya terpotong.
  - Kini setiap kartu memakai tinggi penuhnya (`[&>*]:shrink-0`), dan kolom kanan bisa digulir sendiri.
  - Diuji pada 1920×950, 1280×600, 1024×700, dan ponsel 375px: tidak ada bagian terpotong, dan tombol Setujui / Kembalikan terjangkau.

---

# Nama Direktur pada Surat Penyerahan & Pembayaran

- **Masalah:** keenam template surat mitra di tab Penyerahan/Pembayaran menulis "( NAMA DIREKTUR )" sebagai teks tetap, sehingga nama direktur mitra tidak pernah terisi. Surat yang terdampak:
  - Surat Jalan
  - Surat Pengantar Penyerahan
  - Berita Acara Pemeriksaan
  - Berita Acara Serah Terima
  - Surat Pernyataan Garansi
  - Surat Permohonan Pembayaran
- **Perbaikan:**
  - Kedelapan tempat nama direktur (tanda tangan, serta baris "Nama" di BAST dan Pernyataan Garansi) kini memakai `<span style="text-transform: uppercase;">{{nama_direktur}}</span>`, sehingga tercetak huruf besar seperti nama penanda tangan lain.
  - `FinanceLetters::values()` mengisi `nama_direktur` dari Nama Direktur calon mitra. Bila belum diisi, ditampilkan garis titik-titik untuk ditulis tangan (sama seperti data mitra lain), bukan "-".
- **Deploy:** jalankan `php artisan templates:sync --group=keuangan`. Template yang sudah diubah admin tidak ditimpa; untuk itu pakai `--force`, atau ganti teksnya manual di Data Master → Template Dokumen.
- **Test:** `FinanceLettersTest::test_every_letter_names_the_director_or_leaves_a_line_for_it`.

---

# Dokumen Pendukung Usulan

- Selain **TOR** dan **Nota Dinas Usulan** (wajib), pengusul dapat menambah **Dokumen Pendukung** (opsional): spesifikasi, foto kondisi, penawaran, dsb.
- **Format:** PDF, JPG, PNG, Word, Excel; maks. 20 MB per berkas, hingga 10 berkas sekali unggah.
- **Tempat input:**
  - Form usulan baru, bagian Dokumen Usulan (boleh lebih dari satu berkas, seret atau klik).
  - Halaman Tinjau Usulan: panel **Dokumen Pendukung** dengan tombol **Tambah Dokumen** dan hapus per berkas.
  - Tab pengusul di detail pengadaan menampilkan daftar yang sama.
- **Hak:** hanya pembuat usulan yang menambah atau menghapus, selama usulan belum disetujui (policy `reviseProposal`). Pihak lain (TL, Asman, PIC) hanya melihat dan membuka.
- Penyetuju juga melihatnya sebagai tab "Pendukung · nama berkas" di penampil dokumen.
- Tidak memengaruhi kelengkapan usulan: usulan tetap diteruskan begitu TOR dan Nota Dinas Usulan lengkap.
- **Teknis:**
  - Memakai tabel dan rute lampiran yang sudah ada (`procurement_attachments`, `proposals.attachments.*`); tanpa migrasi.
  - Payload bersama `ProposalDocuments::supporting()`; komponen `components/procurement/supporting-documents.tsx`.
- **Test:** `ProposalFlowTest` (`test_the_proposer_files_supporting_documents_beside_the_tor_and_nota_dinas`, `test_supporting_documents_are_optional_limited_and_closed_after_approval`).

---

# Posisi Teks di Dalam Sel Tabel (Editor Visual)

- Saat kursor berada di tabel, bar **Aksi Tabel** dan menu melayang di sel kini punya tiga tombol: **teks sel di atas / di tengah / di bawah** (perataan vertikal isi sel).
- Berlaku untuk sel tempat kursor berada, atau semua sel yang tersentuh seleksi bila beberapa sel dipilih sekaligus.
- Tombol yang menyala menunjukkan posisi sel saat ini (dibaca dari tampilan sel, termasuk gaya bawaan template).
- Disimpan sebagai `style="vertical-align: top|middle|bottom"` pada `<td>/<th>`, sehingga ikut ke Pratinjau Cetak, PDF, dan unduhan. Tanpa perubahan database.
- Kode: `CellAlignButtons`, `alignCells`, `selectedCells` di `components/document-editor/visual-editor.tsx`. **Test:** `DocumentEditingTest::test_the_position_of_text_in_table_cells_is_kept_through_to_the_print`.

---

# Presentasi Alur Aplikasi

- **Berkas:** `presentasi/Sistem-Pengadaan-Kendari.pptx` (dapat diedit) dan `presentasi/Sistem-Pengadaan-Kendari.pdf` (distribusi), 13 slide 16:9.
- **Isi:** Cover, Gambaran Umum, Alur Utama (5 tahap), Aktor & Tanggung Jawab, Usulan, Perencanaan, Pelaksanaan, Percabangan SPK/PJ vs SPPL, Penyerahan, Pembayaran, Amandemen dan DO (proses pendukung, bukan tahap 6–7), Ringkasan.
- **Header tiap slide:** logo vendor `public/logo/vendor.png` di kiri dan logo PLN `public/logo/logo.png` di kanan, keduanya dengan rasio asli (tinggi 0,5") di atas pita navy.
- **Dasar alur:** dokumen ini, dicocokkan dengan hak bawaan di kode (`Permission::defaultRoles`): penunjuk PIC Perencana bawaan = TL ICC; Pembayaran Lunas hanya bila semua item wajib Keuangan (Penyerahan dan Pembayaran) selesai (`ProcurementPolicy::completePayment`).
- **Ditandai "perlu dikonfirmasi":** Surat Pesanan sebagai tahapan checklist SPPL (belum menjadi item checklist aktif) dan penunjuk PIC Perencana (dokumen menyebut TL Pengadaan, kode TL ICC).
- **Cara membuat ulang:** skrip pptxgenjs; PDF diekspor lewat PowerPoint (Save As PDF).

---

# Teknologi

Sesuai dengan `claude.md`.
