<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The documents the Kontrak step produces.
 *
 * The step yields three separate documents: the SPK itself, its annex, and the
 * negotiation berita acara together with its own annex in a single file.
 */
class KontrakTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the contract documents.
     */
    public function run(): void
    {
        // [document type, work category (null = any), name, body]: the SPK
        // comes as a services and a goods version, picked by the
        // procurement's category.
        $templates = [
            ['spk', 'jasa', 'SPK (Surat Perintah Kerja)', $this->spk('jasa')],
            ['spk', 'barang', 'SPK (Surat Perintah Kerja) Barang', $this->spk('barang')],
            ['lampiran-spk', null, 'Lampiran Surat Perintah Kerja', $this->lampiranSpk()],
            ['ba-negosiasi', null, 'Berita Acara Negosiasi dan Lampiran', $this->beritaAcaraNegosiasi()],
        ];

        $seeded = 0;

        foreach ($templates as [$code, $category, $name, $body]) {
            $documentType = DocumentType::query()->where('code', $code)->first();

            if ($documentType === null) {
                continue;
            }

            DocumentTemplate::query()->updateOrCreate(
                [
                    'document_type_id' => $documentType->id,
                    'procurement_method_id' => null,
                    'work_category' => $category,
                    'version' => 1,
                ],
                [
                    'name' => $name.' - Template Standar UP Kendari',
                    'body' => $body,
                    'placeholders' => $this->placeholdersIn($body),
                    'is_active' => true,
                ],
            );

            $seeded++;
        }

        $this->command?->info("Template kontrak terpasang untuk {$seeded} jenis dokumen.");
    }

    /**
     * Surat Perintah Kerja, following the UP Kendari contract layout:
     * the parties, the documents it rests on, then Pasal 1 to 20 and the
     * signatures. Procurement data fills in through placeholders; anything
     * the system does not hold yet is left as a dotted blank to complete in
     * the document editor.
     */
    public function spk(string $category = 'jasa'): string
    {
        $isBarang = $category === 'barang';
        $pasal = fn (string $number, string $title): string => '<div class="pasal"><div>'.$number.'</div><div>'.$title.'</div></div>';

        $lingkup = $this->ayat([
            'PIHAK KESATU setuju untuk memberi tugas kepada PIHAK KEDUA dan PIHAK KEDUA setuju menerima tugas untuk melaksanakan pekerjaan <b class="upper">{{nama_pengadaan}}</b> dengan spesifikasi sebagaimana tertera dalam Lampiran Surat Perintah Kerja ini.',
            'Lingkup Pekerjaan adalah namun tidak terbatas sebagai berikut :',
        ]);

        $spesifikasi = $this->ayat([
            '<b>Pekerjaan</b> sebagaimana dimaksud pada Pasal 2 Perjanjian ini mempunyai spesifikasi teknik sesuai Lampiran Surat Perintah Kerja No. {{nomor_pengadaan}} tanggal {{tanggal_dokumen}} terlampir.',
            'Hasil pekerjaan tersebut dalam Pasal 1 di atas harus dalam keadaan baik, bebas dari cacat, baik yang kelihatan maupun yang tersembunyi.',
        ]);

        $harga = $this->ayat([
            'PARA PIHAK sepakat bahwa harga barang sebagaimana dimaksud dalam Pasal 2 Perjanjian ini adalah sebesar <b>{{nilai_setelah_nego}} ({{nilai_setelah_nego_terbilang}}).</b>',
            'Harga yang dimaksud dalam ayat (1) Pasal ini adalah harga di dalam Gudang PT PLN NP UP Kendari (Franco Gudang), tidak termasuk Pajak Pertambahan Nilai (PPN) dimana besaran Pajak Pertambahan Nilai (PPN) mengikuti regulasi yang berlaku, biaya pengemasan sebagaimana dimaksud dalam Pasal 4, biaya pengangkutan, biaya handling barang dari tempat asal barang sampai di tempat penyerahan barang, biaya persiapan, biaya pekerjaan jasa, perapihan lahan pekerjaan serta seluruh pajak-pajak yang berlaku.',
            'Jumlah harga sebagaimana dimaksud dalam ayat (1) Pasal ini tetap atau tidak berubah (fixed price) sampai dengan PARA PIHAK selesai melaksanakan hak dan kewajibannya berdasarkan Perjanjian ini.',
            'PIHAK KEDUA tidak dapat menuntut perubahan harga sebagaimana dimaksud dalam ayat (1) Pasal ini dan/atau tambahan biaya apapun juga walaupun terjadi kenaikan harga yang berhubungan dengan pelaksanaan Perjanjian ini kecuali atas persetujuan/penetapan Pemerintah.',
        ]);

        $waktu = $this->ayat([
            'Pekerjaan sebagaimana dimaksud dalam Pasal 2 Perjanjian ini harus diserahkan seluruhnya oleh PIHAK KEDUA kepada PIHAK KESATU di site PT PLN NP UP Kendari.',
            'Penyerahan pekerjaan dan Laporan Hasil Pekerjaan ke Tim Pemeriksa Pekerjaan sebagaimana dimaksud dalam ayat (1) Pasal ini harus dilakukan selambat lambatnya <b>{{jangka_waktu_hari}} ({{jangka_waktu_hari_terbilang}}) Hari Kalender Sejak Tanggal Waktu Mulai Pelaksanaan Pekerjaan dalam Working Permit.</b>',
        ]);

        $pembayaran = $this->ayat([
            'Pembayaran akan dilakukan setelah PIHAK KEDUA menyelesaikan pengiriman barang. Permintaan pembayaran diajukan kepada PIHAK KESATU, dengan melampirkan:'
                .$this->huruf([
                    'Permohonan Pembayaran',
                    'Kwitansi tagihan dalam rangkap 3 (tiga) lembar pertama bermaterai 10.000',
                    'Asli Berita Acara Penyelesaian Pekerjaan',
                    'Asli atau Salinan Surat Penyerahan Pekerjaan',
                    'Salinan Surat Perintah Kerja',
                    'Asli Safety Clearence',
                    'Salinan Surat Keputusan Pengukuhan Pengusaha Kena Pajak (PKP)',
                    'Salinan Nomor Pokok Wajib Pajak (NPWP)',
                    'E-Faktur Pajak 4 (empat) rangkap',
                    'E-Faktur Pajak Pengganti/Revisi 4 (empat) rangkap (apabila ada)',
                    'Surat Keterangan Bebas Potong PPh 21, PPh 22, PPh 23 dan PPH Pasal 4 ayat 2 (apabila ada)',
                    'Salinan Addendum Pengadaan Barang/Jasa (apabila ada)',
                ]),
            'PIHAK KESATU akan membayar kepada PIHAK KEDUA harga kontrak sebagaimana dimaksud dalam Pasal 3 Surat Perintah Kerja ini dengan cara ditransfer ke:'
                .'<table class="plain-rows rekening">'
                .'<tr><td>Bank</td><td>: {{nama_bank}}</td></tr>'
                .'<tr><td>Nomor Rekening</td><td>: {{nomor_rekening}}</td></tr>'
                .'<tr><td>Atas Nama</td><td>: {{nama_pemilik_rekening}}</td></tr>'
                .'</table>',
            'Pembayaran Harga Kontrak sebagaimana dimaksud dalam Pasal 4 ayat 1 akan diperhitungkan dengan denda yang dikenakan pada PIHAK KEDUA.',
            'Biaya-biaya yang timbul pada Bank PIHAK KEDUA sehubungan dengan transaksi pembayaran yang dilakukan PIHAK KESATU kepada PIHAK KEDUA berdasarkan PERJANJIAN ini, akan menjadi beban dan tanggung jawab sepenuhnya PIHAK KEDUA.',
        ]);

        $penyerahan = $this->ayat([
            'Pekerjaan sebagaimana dimaksud dalam Perjanjian ini yang diserahkan oleh PIHAK KEDUA kepada PIHAK KESATU harus sesuai dengan ketentuan sebagaimana dimaksud dalam Pasal 2 Perjanjian ini.',
            'Penyerahan pekerjaan harus dilakukan dengan Berita Acara Penyerahan Pekerjaan yang ditandatangani oleh PIHAK KESATU atau wakil yang ditunjuk dan PIHAK KEDUA atau wakil yang ditunjuk. Berita Acara yang telah ditandatangani oleh PIHAK KESATU wajib diserahkan kepada Tim Pemeriksa Kualitas Jasa pada hari dan jam kerja (Senin s/d Jum&rsquo;at Pukul 08.30 &ndash; 15.30 WITA). Atas penerimaan pekerjaan tersebut di atas akan dibuat Berita Acara Penyelesaian Pekerjaan.',
            'Apabila pekerjaan yang diserahkan oleh PIHAK KEDUA tidak sesuai dengan ketentuan sebagaimana dimaksud dalam Pasal 2 Perjanjian ini maka PIHAK KESATU berhak menolak pekerjaan yang tidak sesuai tersebut dan PIHAK KEDUA harus mengganti dengan pekerjaan yang sesuai dengan syarat-syarat yang telah ditentukan dalam Pasal 1 Perjanjian ini.',
            'Apabila penggantian pekerjaan sebagaimana dimaksud dalam ayat (3) Pasal ini melampaui batas waktu yang telah ditetapkan sebagaimana dimaksud dalam Pasal 4 tentang Waktu dan Tempat Penyerahan ayat (2) Perjanjian ini, maka penggantian pekerjaan tersebut dinyatakan terlambat penyerahannya.',
            'Semua kehilangan atau kerusakan yang terjadi dalam waktu pengangkutan dari tempat PIHAK KEDUA sampai ditempat penyerahan pekerjaan sebagaimana dimaksud dalam Pasal 6 ayat (2) Perjanjian ini, menjadi beban dan tanggung jawab PIHAK KEDUA.',
            'Semua biaya yang dikeluarkan atau diperlukan sebagai akibat dari kejadian sebagaimana dimaksud dalam ayat (3) Pasal ini menjadi beban dan tanggung jawab PIHAK KEDUA.',
            'PIHAK KEDUA sanggup mengerjakan pekerjaan dengan sebaik-baiknya dengan menggunakan tenaga, bahan dan peralatan yang memenuhi persyaratan teknis, yang dapat dipertanggungjawabkan sebagaimana tersebut dalam Pasal 2 Perjanjian.',
            'Penyerahan pekerjaan sebagaimana diatur dalam ayat (2) pasal ini wajib diserahkan kepada Tim Pemeriksa Kualitas Jasa PT PLN NP UP Kendari paling lambat sesuai waktu yang ditentukan dalam Pasal 6 ayat (2) Perjanjian ini dengan dilengkapi dokumen sebagai berikut:'
                .$this->huruf([
                    '<b>Asli Surat Penyerahan Pekerjaan</b>',
                    '<b>Laporan Pekerjaan</b>',
                    '<b>Dokumentasi Foto Pelaksanaan Pekerjaan</b>',
                    '<b>Copy <i>Safety Clearance</i> atau <i>Working Permit</i></b>',
                    '<b>Surat Pernyataan Garansi Pekerjaan</b>',
                    '<b>Sertifikat CSMS dengan klasifikasi minimal Moderat</b>',
                    '<b>Salinan Surat Perintah Kerja</b>',
                ]),
            'PIHAK KEDUA wajib menyerahkan dokumen penagihan sesuai dengan Pasal 5 Ayat 1 maksimal 5 hari kerja sejak tanggal pengambilan BA dari Gudang.',
        ]);

        $sanksi = $this->ayat([
            'Dalam hal terjadi keterlambatan penyerahan barang sesuai waktu yang telah ditentukan dalam Perjanjian ini maka PIHAK KEDUA dikenakan denda keterlambatan sebesar 1&permil; (satu perseribu) per-hari kalender dari nilai Perjanjian dengan batas maksimum denda keterlambatan sebesar 5% dari nilai Perjanjian, kecuali keterlambatan penyerahan barang dimaksud disebabkan adanya Force Majeure.',
            'Bilamana terjadi keterlambatan penyerahan barang, PIHAK KEDUA harus memberitahukan kepada PIHAK KESATU selambat-lambatnya 7 (tujuh) hari kalender terhitung sejak berakhirnya batas waktu penyerahan barang dan mengajukan perpanjangan waktu penyerahan barang secara tertulis disertai alasan yang wajar.',
        ]);

        $jaminan = $this->ayat([
            'PIHAK KEDUA menjamin bahwa semua barang yang diserahkan berdasarkan PERJANJIAN ini adalah benar-benar baru dan belum pernah dipakai serta bebas dari pelanggaran hak paten, merek terdaftar, desain, hak cipta atau hak milik intelektual lainnya.',
            'Apabila PIHAK KEDUA tidak memenuhi ketentuan dalam ayat (1) Pasal ini, maka PIHAK KEDUA akan membebaskan PIHAK KESATU dari kerugian dan melindungi PIHAK KESATU dari gugatan pihak ketiga atas biaya PIHAK KEDUA.',
        ]);

        $garansi = $this->ayat([
            'PIHAK KEDUA memberikan garansi teknis selama masa pemeliharaan atas pekerjaan yang telah diselesaikan.',
            'PIHAK KEDUA wajib memelihara hasil pekerjaan selama masa pemeliharaan sehingga kondisi tetap baik.',
            'Masa pemeliharaan pekerjaan selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan terhitung sejak tanggal Berita Acara Penyelesaian Pekerjaan.',
        ]);

        $tuntutan = $this->ayat([
            'PIHAK KEDUA menjamin PIHAK KESATU baik sekarang maupun di kemudian hari tidak akan mendapat tuntutan dari pihak lain yang menyatakan mempunyai hak atas barang sebagaimana dimaksud dalam Pasal 1 PERJANJIAN ini yang telah diserahkan PIHAK KEDUA kepada PIHAK KESATU.',
        ]);

        $sistemManajemen = $this->ayat([
            'Sistem Manajemen Lingkungan (ISO 14001 : 2015)'
                .$this->angka([
                    'Dalam melaksanakan Pekerjaan/Pengadaan Barang atau Jasa, PIHAK KEDUA wajib selalu mengadakan pengawasan tentang kebersihan, keamanan dan keselamatan di tempat pekerjaan.',
                    'Kelebihan material dan sisa-sisa bahan lainnya yang bersifat mencemari lingkungan wajib selalu dibersihkan untuk menghindari terjadinya pencemaran lingkungan.',
                    'Kendaraan operasional yang digunakan untuk memasuki area PT PLN NP UP Kendari wajib lulus Uji Emisi.',
                ]),
            'Sistem Manajemen Keselamatan dan Kesehatan Kerja (OHSAS 45001 : 2018)'
                .$this->angka([
                    'Sebelum pekerjaan dilaksanakan :<br>PIHAK KEDUA wajib mengurus <i>Safety</i> dan <i>Working Permit</i> ke Bidang K3 PT PLN NP UP Kendari dengan melampirkan persyaratan pendukung, yaitu :'
                        .'<ul class="dash">'
                        .'<li>Daftar peralatan kerja dan Alat Pelindung Diri (APD) yang digunakan.</li>'
                        .'<li>Daftar Pelaksana Tenaga Kerja yang melaksanakan pekerjaan, dibuktikan dengan foto kopi indentitas.</li>'
                        .'<li>Penanggung jawab dan atau pengawas pekerjaan.</li>'
                        .'<li><i>Copy</i> bukti Asuransi Tenaga Kerja</li>'
                        .'<li><i>Job Safety Analysis</i> untuk pekerjaan berisiko tinggi</li>'
                        .'</ul>',
                    'Saat pelaksanaan pekerjaan :'
                        .$this->huruf([
                            'PIHAK KEDUA wajib menyiapkan sarana bantu kerja yang diperlukan dan dilarang keras bertumpu langsung ke peralatan unit yang dinilai akan membahayakan baik saat unit mati maupun beroperasi.',
                            'PIHAK KEDUA wajib menggunakan Alat Pelindung Diri (APD) untuk tenaga kerjanya yang diperlukan sesuai jenis dan resiko pekerjaannya, seperti : <i>Safety Helmet, Safety Belt, Safety Shoes</i>, Sarung Tangan, Masker/Tutup Hidung dan lain-lain.',
                            'PIHAK KEDUA wajib melaporkan segala kondisi potensi bahaya di area kerja kepada Pengawas/Direksi pekerjaan.',
                            'PIHAK KEDUA wajib untuk selalu menjaga kebersihan, keamanan dan keselamatan di tempat pekerjaan.',
                            'PIHAK KEDUA wajib bertanggung jawab apabila terjadi kecelakaan kerja dan pencemaran lingkungan akibat kelalaian dalam pelaksanaan pekerjaan dan semua biaya yang timbul menjadi tanggung jawab PIHAK KEDUA, baik kerugian terhadap tenaga kerja maupun kerusakan peralatan/lingkungan dan adanya kecelakaan kerja ini harus tidak mempengaruhi jadwal pelaksanaan pekerjaan.',
                            'Selama melaksanakan pekerjaan PIHAK KEDUA tidak diperkenankan merokok di seluruh area PT PLN NUSANTARA POWER UP Kendari.',
                        ]),
                ]),
            'Sistem Manajemen Mutu (ISO 9001: 2015)'
                .$this->angka([
                    'PIHAK KEDUA wajib melaporkan kegiatan pekerjaan baik sebelum dan sesudah pelaksanaan pekerjaan kepada Direksi/Pengawas pekerjaan.',
                    'Semua peralatan yang digunakan harus sudah maupun dalam masa kalibrasi.',
                ]),
            'Sistem Manajemen Energi'
                .$this->angka([
                    'Menggunakan peralatan hemat energi.',
                    'Melakukan penghematan air dan listrik selama di Area PT PLN NP UP Kendari.',
                    'PIHAK KEDUA dalam menggunakan sumber energi listrik, sumber udara bertekanan, air dan bahan bakar harus dapat menggunakannya sehemat mungkin.',
                ]),
        ]);

        $pemutusan = $this->ayat([
            'PIHAK KESATU berhak untuk memutus PERJANJIAN secara sepihak <u>bilamana :</u>'
                .$this->huruf([
                    'PIHAK KEDUA mengalami keterlambatan pengiriman barang sesuai tersebut dalam pasal 2 ayat 1 sebanyak 3 (tiga) kali.',
                    'PIHAK KEDUA terlambat mengirimkan barang lebih dari 50 hari kalender sejak penerbitan Surat Pesanan.',
                ]),
            'Apabila terjadi pemutusan PERJANJIAN sebagaimana dimaksud dalam ayat (1) Pasal ini, PIHAK KESATU berhak mencairkan jaminan pelaksanaan dan jaminan pelaksanaan tersebut akan sepenuhnya menjadi hak milik PIHAK KESATU serta PIHAK KEDUA dikenakan sanksi tidak diperbolehkan untuk mengikuti pengadaan barang di wilayah kerja PT PLN Nusantara Power selama minimum 1 (satu) tahun sejak tanggal pemutusan PERJANJIAN.',
            'Dalam hal terjadi pemutusan PERJANJIAN secara sepihak, maka PARA PIHAK sepakat untuk tidak memberlakukan ketentuan-ketentuan Pasal 1266 dan Pasal 1267 Kitab Undang-Undang Hukum Perdata.',
        ]);

        $forceMajeure = $this->ayat([
            'Kejadian Kahar adalah setiap keadaan yang berada di luar kontrol yang wajar, langsung ataupun tidak langsung dari pihak yang terkena (termasuk tetapi tidak terbatas pada kerusuhan, perang, bencana alam, <i>blackout</i> pada Sistem Ketenagalistrikan), tetapi jika hanya dan sejauh bahwa :'
                .$this->huruf([
                    'situasi tersebut, walaupun telah dilakukan upaya keras yang pantas, tidak dapat dicegah, dihindari atau dipindahkan oleh pihak tersebut;',
                    'kejadian tersebut mempengaruhi secara materiil kemampuan pihak yang terkena untuk melaksanakan kewajiban dalam PERJANJIAN ini dan pihak yang terkena telah melakukan seluruh tindakan pencegahan yang pantas, kehati-hatian dan tindakan alternatif yang pantas untuk menghindari akibat dari kejadian tersebut terhadap kemampuan pihak yang terkena tersebut untuk melaksanakan kewajiban dalam PERJANJIAN ini dan untuk mengurangi konsekuensi-konsekuensinya;',
                    'kejadian tersebut bukan akibat langsung atau tidak langsung kegagalan salah satu pihak untuk melaksanakan setiap kewajiban-kewajibannya dalam PERJANJIAN ini; dan',
                    'pihak yang terkena tersebut telah mengirim kepada pihak lainnya pemberitahuan seketika yang menjelaskan kejadian tersebut dan akibat yang terjadi dan tindakan-tindakan yang telah dilakukan untuk memenuhi ketentuan ini dengan ketentuan Kejadian Kahar tidak termasuk pemogokan, penutupan atau tindakan industri lainnya oleh personel dari pihak yang terkena atau agen-agennya.',
                ]),
            'Dalam hal terjadi Force Majeure, maka PIHAK KEDUA wajib memberitahukan secara tertulis kepada PIHAK KESATU dalam waktu selambat-lambatnya 7 (tujuh) hari kalender terhitung sejak terjadinya peristiwa sebagaimana dimaksud dalam ayat (1) Pasal ini.',
            'Apabila dalam jangka waktu sebagaimana dimaksud dalam ayat (2) Pasal ini PIHAK KEDUA tidak memberitahukan kejadian Force Majeure tersebut kepada PIHAK KESATU, maka keterlambatan penyerahan barang sebagaimana dimaksud Pasal 2 PERJANJIAN ini dianggap bukan sebagai akibat dari Force Majeure.',
            'Dalam pemberitahuan mengenai Force Majeure sebagaimana dimaksud dalam ayat (2) Pasal ini harus disertai dengan keterangan dari pihak yang berwenang mengenai peristiwa tersebut dan PIHAK KEDUA dapat sekaligus mengajukan permohonan perpanjangan waktu penyerahan barang kepada PIHAK KESATU.',
            'PIHAK KESATU dalam waktu 14 (empat belas) hari kalender terhitung sejak diterimanya permohonan perpanjangan waktu sebagaimana dimaksud dalam ayat (4) Pasal ini akan memberikan jawaban secara tertulis mengenai permohonan dimaksud kepada PIHAK KEDUA.',
            'Apabila dalam jangka waktu sebagaimana dimaksud dalam ayat (5) Pasal ini PIHAK KESATU tidak memberikan jawaban terhadap permohonan perpanjangan waktu penyerahan barang dari PIHAK KEDUA, maka PIHAK KESATU dianggap telah memberikan persetujuan terhadap permohonan dimaksud.',
            'PIHAK KEDUA tidak dapat dikenakan sanksi atas keterlambatan penyerahan barang sebagaimana dimaksud dalam Pasal 1 PERJANJIAN ini yang diakibatkan oleh Force Majeure.',
        ]);

        $pajak = $this->ayat([
            'Selain dengan yang telah ditentukan dalam PERJANJIAN ini biaya angkutan dan biaya lainnya yang dikeluarkan atau yang diperlukan untuk pengiriman dan penyerahan barang-barang dari tempat penyimpanan barang atau tempat PIHAK KEDUA hingga ditempat yang telah ditentukan oleh PIHAK KESATU ditanggung dan dibebankan oleh PIHAK KEDUA.',
            'Kehilangan atau kerusakan atas barang-barang yang terjadi dalam perjalanan atau pengangkutan dari tempat PIHAK KEDUA sampai ditempat yang telah ditentukan oleh PIHAK KESATU menjadi resiko dan tanggung jawab PIHAK KEDUA.',
        ]);

        $perselisihan = $this->ayat([
            'Jika dalam pelaksanaan PERJANJIAN ini terdapat perbedaan yang menimbulkan perselisihan, maka PARA PIHAK akan berusaha sebaik mungkin untuk menyelesaikan perselisihan tersebut secara musyawarah.',
            'Apabila perselisihan tidak dapat diselesaikan, maka para pihak dengan ini sepakat untuk menyelesaikan perselisihan tersebut melalui arbitrase berdasarkan ketentuan-ketentuan dari Badan Arbitrasi Nasional Indonesia (BANI).',
            'Para pihak dengan ini sepakat memilih tempat kedudukan hukum di Kantor BANI.',
            'Selama proses diselenggarakannya usaha penyelesaian perselisihan berlangsung tidak dapat dijadikan alasan oleh PIHAK KEDUA untuk tidak melaksanakan kewajibannya berdasarkan PERJANJIAN ini sesuai dengan jadual yang telah ditetapkan.',
        ]);

        $kkn = $this->ayat([
            'PARA PIHAK menyepakati bahwa pada saat melaksanakan Perjanjian ini berdasarkan pada prinsip itikad baik, tidak saling mempengaruhi baik langsung maupun tidak langsung, menerima serta bertanggungjawab atas segala keputusan yang ditetapkan sesuai dengan kesepakatan PARA PIHAK, menghindari serta mencegah terjadinya pertentangan kepentingan (<i>conflict of interest</i>), menghindari serta mencegah penyalahgunaan wewenang dan/atau kolusi dengan tujuan untuk keuntungan pribadi-golongan-atau pihak lain, dan tidak menerima, tidak menawarkan atau tidak menjanjikan untuk memberi atau menerima hadiah, imbalan berupa apa saja kepada siapapun yang diketahui atau patut diduga berkaitan dengan pelaksanaan Perjanjian ini serta tidak akan melakukan tindakan-tindakan yang mengakibatkan terjadinya ketidakwajaran dalam pelaksanaan Perjanjian ini termasuk namun tidak terbatas pada tindakan Penipuan, Persekongkolan, Penyuapan, Korupsi, Kecurangan, Pemalsuan dan tindakan lain yang bertentangan dengan peraturan yang berlaku dan tidak sesuai dengan etika bisnis yang baik.',
            'PARA PIHAK menyepakati bahwa dalam pelaksanaan Perjanjian ini wajib patuh dan selalu mengambil tindakan yang cukup untuk memastikan subkontraktor, agen, atau pihak lain yang menjadi subjek kendali agar patuh terhadap setiap hukum Indonesia yang berlaku, tidak terbatas pada Undang-Undang Nomor 31 Tahun 1999 <i>Juncto</i> Undang-Undang Nomor 20 Tahun 2001 tentang Pemberantasan Tindak Pidana Korupsi dan Undang-Undang Nomor 8 Tahun 2010 Tentang Pencegahan dan Pemberantasan Tindak Pidana Pencucian Uang serta bersedia dikenakan sanksi berdasarkan ketentuan peraturan perundang-undangan apabila terbukti terlibat Korupsi, Kolusi, Nepotisme (KKN), penyuapan dan lain sebagainya.',
            'PARA PIHAK dengan ini menjamin untuk tidak akan membayar, menawarkan, menjanjikan atau setuju untuk membayar, menyebabkan dibayarnya, ditawarkannya, dijanjikannya, persetujuan untuk membayar, secara langsung atau tidak langsung, sehubungan dengan pelaksanaan Perjanjian ini atau setiap potensi proyek, setiap kontribusi, biaya, komisi politik, atau pembayaran yang tidak semestinya atau manfaat lain kepada pegawai atau perwakilan pemerintah atau perseorangan swasta yang mengakibatkan timbulnya pembayaran yang tidak semestinya.',
            'PARA PIHAK selanjutnya menjamin untuk tidak, secara langsung atau tidak langsung, sehubungan dengan Perjanjian ini dan bisnis yang timbul karenanya, menawarkan, membayar, berjanji untuk membayar, atau mengizinkan pemberian uang atau hal lainnya yang bernilai kepada pegawai atau perwakilan pemerintah atau pihak manapun, dengan mengetahui atau sadar bahwa uang atau hal lainnya yang bernilai tersebut akan ditawarkan, diberikan atau dijanjikan, secara langsung atau tidak langsung, kepada pegawai atau perwakilan pemerintah, dengan tujuan:'
                .$this->huruf([
                    'Mempengaruhi tindakan atau keputusan dari pegawai tersebut dalam kapasitasnya, termasuk keputusan untuk tidak melaksanakan tugas kedinasannya;',
                    'Mendorong pegawai tersebut untuk menggunakan pengaruhnya kepada institusi pemerintah untuk mempengaruhi tindakan atau keputusan apapun dari institusi pemerintah tersebut, untuk membantu PIHAK manapun untuk mendapatkan bisnis atau mempertahankan bisnis atau mengarahkan suatu bisnis kepada pihak ketiga.',
                ]),
            'PARA PIHAK menyepakati bahwa tidak akan melakukan tindakan-tindakan yang mengakibatkan terjadinya ketidakwajaran dalam pelaksanaan Perjanjian ini termasuk namun tidak terbatas pada tindakan Penipuan, Persekongkolan, Kolusi Penyuapan, Korupsi, Kecurangan, Pemalsuan dan tindakan lain yang bertentangan dengan peraturan yang berlaku dan tidak sesuai dengan etika bisnis yang baik.',
            'Apabila PIHAK KEDUA terbukti tidak memenuhi ketentuan sebagaimana dimaksud dalam Pasal ini, maka PIHAK KESATU berhak memutus Perjanjian ini secara sepihak. Jaminan Pelaksanaan atau Jaminan Garansi berupa Bank Garansi (apabila terjadi saat masa Garansi) akan dicairkan dan sepenuhnya menjadi milik PIHAK KESATU, serta PIHAK KEDUA dikenakan sanksi Blacklist selama minimum 24 (dua puluh empat) bulan sejak tanggal penjatuhan sanksi Blacklist oleh PIHAK KESATU.',
        ]);

        $perubahan = $this->ayat([
            'PARA PIHAK sepakat bahwa setiap perubahan dalam PERJANJIAN ini hanya dapat dilakukan atas persetujuan bersama.',
            'Usulan perubahan sebagaimana dimaksud dalam ayat (1) Pasal ini harus diajukan secara tertulis oleh pihak yang berkepentingan kepada pihak lainnya.',
            'Perubahan sebagaimana dimaksud dalam ayat (2) Pasal ini setelah disepakati dan ditanda tangani oleh PARA PIHAK sebagai addendum dan merupakan bagian yang tidak terpisahkan dari PERJANJIAN ini.',
        ]);

        $itemHeader = 'Deskripsi';

        // The goods SPK: same layout, with the clauses about delivering,
        // inspecting and guaranteeing goods instead of carrying out work.
        if ($isBarang) {
            $itemHeader = 'Nama Barang / Spesifikasi';

            $lingkup = $this->ayat([
                'PIHAK KESATU setuju untuk membeli dari PIHAK KEDUA dan PIHAK KEDUA setuju untuk menjual dan menyerahkan kepada PIHAK KESATU barang untuk pengadaan <b class="upper">{{nama_pengadaan}}</b> dengan spesifikasi sebagaimana tertera dalam Lampiran Surat Perintah Kerja ini.',
                'Jenis dan jumlah barang adalah sebagai berikut :',
            ]);

            $spesifikasi = $this->ayat([
                '<b>Barang</b> sebagaimana dimaksud pada Pasal 1 Perjanjian ini mempunyai spesifikasi teknik sesuai Lampiran Surat Perintah Kerja No. {{nomor_pengadaan}} tanggal {{tanggal_dokumen}} terlampir.',
                'Barang yang diserahkan harus dalam keadaan baru, belum pernah dipakai, baik, lengkap dan bebas dari cacat, baik yang kelihatan maupun yang tersembunyi, serta dilengkapi dokumen keaslian barang (COO / COM / COC / COA) sesuai jenis barangnya.',
            ]);

            $harga = $this->ayat([
                'PARA PIHAK sepakat bahwa harga barang sebagaimana dimaksud dalam Pasal 1 Perjanjian ini adalah sebesar <b>{{nilai_setelah_nego}} ({{nilai_setelah_nego_terbilang}}).</b>',
                'Harga yang dimaksud dalam ayat (1) Pasal ini adalah harga barang sampai di dalam Gudang PT PLN NP UP Kendari (Franco Gudang), tidak termasuk Pajak Pertambahan Nilai (PPN) dimana besaran Pajak Pertambahan Nilai (PPN) mengikuti regulasi yang berlaku, termasuk biaya pengemasan, biaya pengangkutan, asuransi pengiriman, biaya bongkar muat (handling) barang dari tempat asal barang sampai di tempat penyerahan barang serta seluruh pajak-pajak yang berlaku.',
                'Jumlah harga sebagaimana dimaksud dalam ayat (1) Pasal ini tetap atau tidak berubah (fixed price) sampai dengan PARA PIHAK selesai melaksanakan hak dan kewajibannya berdasarkan Perjanjian ini.',
                'PIHAK KEDUA tidak dapat menuntut perubahan harga sebagaimana dimaksud dalam ayat (1) Pasal ini dan/atau tambahan biaya apapun juga walaupun terjadi kenaikan harga yang berhubungan dengan pelaksanaan Perjanjian ini kecuali atas persetujuan/penetapan Pemerintah.',
            ]);

            $waktu = $this->ayat([
                'Barang sebagaimana dimaksud dalam Pasal 1 Perjanjian ini harus diserahkan seluruhnya oleh PIHAK KEDUA kepada PIHAK KESATU di Gudang PT PLN NP UP Kendari.',
                'Penyerahan barang sebagaimana dimaksud dalam ayat (1) Pasal ini harus dilakukan selambat lambatnya <b>{{jangka_waktu_hari}} ({{jangka_waktu_hari_terbilang}}) Hari Kalender sejak tanggal Surat Perintah Kerja ini ditandatangani.</b>',
            ]);

            $pembayaran = $this->ayat([
                'Pembayaran akan dilakukan setelah PIHAK KEDUA menyelesaikan penyerahan barang dan barang dinyatakan diterima. Permintaan pembayaran diajukan kepada PIHAK KESATU, dengan melampirkan:'
                    .$this->huruf([
                        'Permohonan Pembayaran',
                        'Kwitansi tagihan dalam rangkap 3 (tiga) lembar pertama bermaterai 10.000',
                        'Asli Bon Penerimaan Barang',
                        'Asli Berita Acara Pemeriksaan Barang',
                        'Asli atau Salinan Surat Jalan / Delivery Order',
                        'Salinan Surat Perintah Kerja',
                        'Salinan Surat Keputusan Pengukuhan Pengusaha Kena Pajak (PKP)',
                        'Salinan Nomor Pokok Wajib Pajak (NPWP)',
                        'E-Faktur Pajak 4 (empat) rangkap',
                        'E-Faktur Pajak Pengganti/Revisi 4 (empat) rangkap (apabila ada)',
                        'Surat Keterangan Bebas Potong PPh 21, PPh 22, PPh 23 dan PPH Pasal 4 ayat 2 (apabila ada)',
                        'Surat Pernyataan Garansi Barang',
                        'Salinan Addendum Pengadaan Barang/Jasa (apabila ada)',
                    ]),
                'PIHAK KESATU akan membayar kepada PIHAK KEDUA harga kontrak sebagaimana dimaksud dalam Pasal 3 Surat Perintah Kerja ini dengan cara ditransfer ke:'
                    .'<table class="plain-rows rekening">'
                    .'<tr><td>Bank</td><td>: {{nama_bank}}</td></tr>'
                    .'<tr><td>Nomor Rekening</td><td>: {{nomor_rekening}}</td></tr>'
                    .'<tr><td>Atas Nama</td><td>: {{nama_pemilik_rekening}}</td></tr>'
                    .'</table>',
                'Pembayaran Harga Kontrak sebagaimana dimaksud dalam Pasal 4 ayat 1 akan diperhitungkan dengan denda yang dikenakan pada PIHAK KEDUA.',
                'Biaya-biaya yang timbul pada Bank PIHAK KEDUA sehubungan dengan transaksi pembayaran yang dilakukan PIHAK KESATU kepada PIHAK KEDUA berdasarkan PERJANJIAN ini, akan menjadi beban dan tanggung jawab sepenuhnya PIHAK KEDUA.',
            ]);

            $penyerahan = $this->ayat([
                'Barang yang diserahkan oleh PIHAK KEDUA kepada PIHAK KESATU harus sesuai dengan jenis, jumlah dan spesifikasi sebagaimana dimaksud dalam Pasal 1 dan Pasal 2 Perjanjian ini.',
                'Penyerahan barang dilakukan di Gudang PT PLN NP UP Kendari pada hari dan jam kerja (Senin s/d Jum&rsquo;at Pukul 08.30 &ndash; 15.30 WITA) dengan disertai Surat Jalan / Delivery Order dan diperiksa oleh Tim Pemeriksa Mutu Barang PT PLN NP UP Kendari. Atas barang yang diterima dengan baik akan dibuat Berita Acara Pemeriksaan Barang dan Bon Penerimaan Barang.',
                'Apabila barang yang diserahkan oleh PIHAK KEDUA tidak sesuai dengan ketentuan sebagaimana dimaksud dalam Pasal 2 Perjanjian ini maka PIHAK KESATU berhak menolak barang yang tidak sesuai tersebut dan PIHAK KEDUA harus mengambil kembali serta mengganti dengan barang yang sesuai dengan syarat-syarat yang telah ditentukan dalam Perjanjian ini atas biaya PIHAK KEDUA.',
                'Apabila penggantian barang sebagaimana dimaksud dalam ayat (3) Pasal ini melampaui batas waktu yang telah ditetapkan dalam Pasal 4 ayat (2) Perjanjian ini, maka penggantian barang tersebut dinyatakan terlambat penyerahannya.',
                'Semua kehilangan atau kerusakan yang terjadi dalam waktu pengangkutan dari tempat PIHAK KEDUA sampai ditempat penyerahan barang sebagaimana dimaksud dalam Pasal 4 ayat (1) Perjanjian ini, menjadi beban dan tanggung jawab PIHAK KEDUA.',
                'Semua biaya yang dikeluarkan atau diperlukan sebagai akibat dari kejadian sebagaimana dimaksud dalam ayat (3) Pasal ini menjadi beban dan tanggung jawab PIHAK KEDUA.',
                'Penyerahan barang sebagaimana diatur dalam ayat (2) pasal ini wajib dilengkapi dokumen sebagai berikut:'
                    .$this->huruf([
                        '<b>Surat Jalan / Delivery Order</b>',
                        '<b>Packing List</b>',
                        '<b>Dokumen keaslian barang (COO / COM / COC / COA)</b>',
                        '<b>Buku manual / petunjuk penggunaan (apabila ada)</b>',
                        '<b>Surat Pernyataan Garansi Barang</b>',
                        '<b>Salinan Surat Perintah Kerja</b>',
                    ]),
                'PIHAK KEDUA wajib menyerahkan dokumen penagihan sesuai dengan Pasal 5 Ayat 1 maksimal 5 hari kerja sejak tanggal penerbitan Bon Penerimaan Barang.',
            ]);

            $garansi = $this->ayat([
                'PIHAK KEDUA memberikan garansi atas barang yang diserahkan selama {{masa_garansi_bulan}} ({{masa_garansi_bulan_terbilang}}) bulan terhitung sejak tanggal Bon Penerimaan Barang.',
                'Selama masa garansi, setiap kerusakan atau cacat pada barang yang bukan disebabkan kesalahan pemakaian wajib diperbaiki atau diganti oleh PIHAK KEDUA tanpa biaya tambahan bagi PIHAK KESATU.',
                'Perbaikan atau penggantian barang sebagaimana dimaksud dalam ayat (2) Pasal ini dilakukan paling lambat 14 (empat belas) hari kalender sejak pemberitahuan tertulis dari PIHAK KESATU.',
            ]);
        }

        $h = array_map(fn (array $pair): string => $pasal(...$pair), [
            ['Pasal 1', $isBarang ? 'JENIS DAN JUMLAH BARANG' : 'LINGKUP PEKERJAAN'],
            ['PASAL 2', $isBarang ? 'SPESIFIKASI DAN MUTU BARANG' : 'SPESIFIKASI DAN MUTU PEKERJAAN'],
            ['Pasal 3', $isBarang ? '<u>HARGA BARANG</u>' : '<u>HARGA PEKERJAAN</u>'],
            ['Pasal 4', $isBarang ? 'WAKTU DAN TEMPAT PENYERAHAN BARANG' : 'WAKTU DAN TEMPAT PENYERAHAN'],
            ['Pasal 5', 'CARA PEMBAYARAN'],
            ['Pasal 6', $isBarang ? 'PENYERAHAN DAN PEMERIKSAAN BARANG' : 'PENYERAHAN PEKERJAAN'],
            ['PASAL 7', 'SANKSI'],
            ['Pasal 8', 'JAMINAN PRODUK'],
            ['Pasal 9', 'GARANSI'],
            ['Pasal 10', 'PEMBEBASAN TUNTUTAN'],
            ['Pasal 11', 'PEMENUHAN SISTEM MANAJEMEN'],
            ['Pasal 12', 'KEPEMILIKAN'],
            ['Pasal 13', 'PEMUTUSAN PERJANJIAN'],
            ['Pasal 14', 'FORCE MAJEURE'],
            ['Pasal 15', 'PAJAK DAN PUNGUTAN'],
            ['Pasal 16', 'TUNTUTAN TAMBAHAN BIAYA'],
            ['Pasal 17', 'PERSELISIHAN PENDAPAT'],
            ['Pasal 18', 'KEPATUHAN TERHADAP HUKUM DAN LARANGAN PENYUAPAN, KORUPSI, KOLUSI, NEPOTISME (KKN)'],
            ['Pasal 19', 'DOKUMEN-DOKUMEN'],
            ['Pasal 20', 'PERUBAHAN-PERUBAHAN'],
        ]);

        return <<<HTML
        <section class="spk">
            <style>
                @page { size: A4 portrait; margin: 18mm 20mm 30mm 22mm; }
                .spk { font-family: Arial, Helvetica, sans-serif; font-size: 9.5pt; line-height: 1.4; color: #000; text-align: justify; }
                .spk .title { text-align: center; font-weight: bold; font-size: 10.5pt; line-height: 1.6; margin-bottom: 14px; }
                .spk .title .gap { margin-top: 10px; }
                .spk .upper { text-transform: uppercase; }
                .spk p { margin: 6px 0; }
                .spk .pasal { text-align: center; font-weight: bold; margin: 18px 0 6px; page-break-after: avoid; }
                .spk .pasal div + div { margin-top: 3px; }
                .spk table { border-collapse: collapse; width: 100%; margin: 4px 0; font-size: 9.5pt; }
                .spk table.ayat td { border: none; padding: 1.5px 0; vertical-align: top; text-align: justify; }
                .spk table.ayat td.no { width: 30px; }
                .spk table.plain-rows td { border: none; padding: 1px 0; }
                .spk table.rekening { width: auto; margin: 6px 0 2px; }
                .spk table.rekening td:first-child { width: 190px; }
                .spk table.grid th, .spk table.grid td { border: 1px solid #000; padding: 3px 6px; vertical-align: middle; }
                .spk table.grid th { background: #fff; text-align: center; font-weight: bold; }
                .spk table.lingkup th, .spk table.lingkup td { border: 1px solid #8eaadb; padding: 3px 6px; vertical-align: top; }
                .spk table.lingkup th { background: #dbe5f1; text-align: center; }
                .spk ol.huruf { list-style-type: lower-alpha; margin: 3px 0 3px 18px; padding: 0; }
                .spk ol.angka { list-style-type: decimal; margin: 3px 0 3px 18px; padding: 0; }
                .spk ol li { margin: 1.5px 0; padding-left: 2px; }
                .spk ul.dash { list-style-type: none; margin: 2px 0 2px 4px; padding: 0; }
                .spk ul.dash li:before { content: "- "; }
                .spk .party td { border: none; padding: 2px 0; vertical-align: top; text-align: justify; }
                .spk .party td.no { width: 30px; }
                .spk .sign td { border: none; text-align: center; vertical-align: top; font-weight: bold; width: 50%; padding: 0 8px; }
            </style>

            <div style="margin-bottom: 6px;"><img src="/logo/sidebar-logo.png" alt="PT PLN Nusantara Power" style="height: 34px; width: auto;"></div>

            <div class="title">
                <div>SURAT PERINTAH KERJA</div>
                <div><u>Nomor :</u>&nbsp;&nbsp;{{nomor_pengadaan}}</div>
                <div class="gap">PT PLN NUSANTARA POWER</div>
                <div>UP KENDARI</div>
                <div>DAN</div>
                <div class="gap upper">{{nama_mitra}}</div>
                <div class="gap">Tentang</div>
                <div class="gap upper">{{nama_pengadaan}}</div>
            </div>

            <p>Perjanjian ini dibuat pada tanggal &nbsp;<b>{{tanggal_dokumen}}</b> <u>antara :</u></p>

            <table class="party">
                <tr>
                    <td class="no">1</td>
                    <td><b>PT PLN NUSANTARA POWER</b>, suatu perseroan terbatas yang didirikan berdasarkan Hukum Indonesia berkantor pusat di Jalan Ketintang Baru No. 11, Surabaya 60231, Indonesia, yang dalam hal ini diwakili oleh <b>{{nama_manager}}</b> dalam kedudukannya selaku <b>MANAGER</b> dari <b>PT PLN NP UP Kendari</b> berdasarkan Surat Keputusan Direksi PT PLN Nusantara Power Nomor <span class="fill">....................</span> tanggal <span class="fill">....................</span> dari dan oleh karenanya bertindak untuk dan atas nama <b>PT PLN Nusantara Power UP Kendari</b> berkedudukan di Jl. Chairil Anwar No.2, Mataiwoi, Kec. Wua-Wua, Kota Kendari, Sulawesi Tenggara 93117, selanjutnya di sebut <b>PIHAK KESATU.</b></td>
                </tr>
                <tr><td colspan="2" style="text-align: center; padding: 8px 0;">Dan</td></tr>
                <tr>
                    <td class="no">2</td>
                    <td><b>{{nama_mitra}}</b>, suatu perseroan terbatas yang didirikan berdasarkan Hukum Indonesia, berkantor di {{alamat_perusahaan}}, yang dalam hal ini diwakili oleh <b>{{nama_direktur}}</b>, dalam kedudukannya selaku <b>DIREKTUR</b> dari <b>{{nama_mitra}}</b>, dan oleh karenanya sah bertindak untuk dan atas nama <b>{{nama_mitra}}</b>, selanjutnya disebut <b>PIHAK KEDUA.</b></td>
                </tr>
            </table>

            <p>PIHAK KESATU dan PIHAK KEDUA secara bersama-sama selanjutnya disebut PARA PIHAK.</p>

            <p style="margin-top: 12px;"><b><u>Berdasarkan :</u></b></p>
            <table class="grid">
                <tr><th style="width: 7%;">No</th><th style="width: 37%;">Uraian</th><th style="width: 34%;">Nomor Dokumen</th><th style="width: 22%;">Tanggal Dokumen</th></tr>
                <tr><td style="text-align: center;">1</td><td>Permintaan Penawaran Harga</td><td><span class="fill">....................</span></td><td style="text-align: center;"><span class="fill">....................</span></td></tr>
                <tr><td style="text-align: center;">2</td><td>Surat Penawaran Harga</td><td>{{nomor_surat_penawaran}}</td><td style="text-align: center;">{{tanggal_surat_penawaran}}</td></tr>
                <tr><td style="text-align: center;">3</td><td>Berita Acara Klarifikasi &amp; Negosiasi</td><td><span class="fill">....................</span></td><td style="text-align: center;"><span class="fill">....................</span></td></tr>
            </table>

            <p style="margin-top: 12px;">PARA PIHAK sepakat untuk mengadakan PERJANJIAN dengan syarat-syarat dan ketentuan-ketentuan tersebut di bawah ini:</p>

            {$h[0]}
            {$lingkup}
            <table class="lingkup">
                <tr><th style="width: 8%;">No.</th><th>{$itemHeader}</th><th style="width: 13%;">Volume</th><th style="width: 13%;">Satuan</th></tr>
                <tr><td></td><td><b class="upper">{{nama_pengadaan}}</b></td><td style="text-align: center;">1</td><td style="text-align: center;">LOT</td></tr>
                <tr><td style="text-align: center;">1.</td><td><span class="fill">....................</span></td><td></td><td></td></tr>
                <tr><td style="text-align: center;">2.</td><td><span class="fill">....................</span></td><td></td><td></td></tr>
                <tr><td style="text-align: center;">3.</td><td><span class="fill">....................</span></td><td></td><td></td></tr>
            </table>

            {$h[1]}
            {$spesifikasi}

            {$h[2]}
            {$harga}

            {$h[3]}
            {$waktu}

            {$h[4]}
            {$pembayaran}

            {$h[5]}
            {$penyerahan}

            {$h[6]}
            {$sanksi}

            {$h[7]}
            {$jaminan}

            {$h[8]}
            {$garansi}

            {$h[9]}
            {$tuntutan}

            {$h[10]}
            {$sistemManajemen}

            {$h[11]}
            <p>Pengalihan risiko, baik risiko kehilangan maupun risiko kerusakan, terjadi pada saat PIHAK KESATU menerima barang sebagaimana dimaksud dalam Pasal 2 PERJANJIAN ini bersama-sama dengan dokumen pengiriman dari PIHAK KEDUA.</p>

            {$h[12]}
            {$pemutusan}

            {$h[13]}
            {$forceMajeure}

            {$h[14]}
            {$pajak}

            {$h[15]}
            <p>PIHAK KEDUA tidak dibenarkan menuntut tambahan biaya apapun juga <i>(claim)</i> walaupun ada kenaikan harga barang-barang atau jasa yang bersangkutan dengan PERJANJIAN ini.</p>

            {$h[16]}
            {$perselisihan}

            {$h[17]}
            {$kkn}

            {$h[18]}
            <p>Dokumen-dokumen yang berhubungan dengan pelaksanaan PERJANJIAN ini merupakan bagian yang tidak terpisahkan dari PERJANJIAN ini dan mengikat PARA PIHAK</p>

            {$h[19]}
            {$perubahan}

            <p style="margin-top: 16px;">PERJANJIAN ini dibuat dan ditandatangani di Kendari, pada hari, tanggal, bulan dan tahun tersebut diatas dalam rangkap dua, yang masing-masing bermeterai Rp10.000,- (sepuluh ribu rupiah) dan mempunyai kekuatan hukum yang sama.</p>

            <table class="sign" style="margin-top: 18px; page-break-inside: avoid;">
                <tr>
                    <td>PIHAK KEDUA<br><span class="upper">{{nama_mitra}}</span><br>DIREKTUR,</td>
                    <td>PIHAK KESATU<br>PT PLN NP UP KENDARI<br>MANAGER,</td>
                </tr>
                <tr><td style="height: 80px;"></td><td></td></tr>
                <tr>
                    <td class="upper">{{nama_direktur}}</td>
                    <td class="upper">{{nama_manager}}</td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * Numbered clauses "(1)", "(2)" ... with a hanging indent.
     *
     * @param  array<int, string>  $items
     */
    protected function ayat(array $items): string
    {
        $rows = '';

        foreach (array_values($items) as $index => $text) {
            $rows .= '<tr><td class="no">('.($index + 1).')</td><td>'.$text.'</td></tr>';
        }

        return '<table class="ayat">'.$rows.'</table>';
    }

    /**
     * A lettered list "a.", "b." ... inside a clause.
     *
     * @param  array<int, string>  $items
     */
    protected function huruf(array $items): string
    {
        return '<ol class="huruf"><li>'.implode('</li><li>', $items).'</li></ol>';
    }

    /**
     * A numbered list "1.", "2." ... inside a clause.
     *
     * @param  array<int, string>  $items
     */
    protected function angka(array $items): string
    {
        return '<ol class="angka"><li>'.implode('</li><li>', $items).'</li></ol>';
    }

    /**
     * Lampiran Surat Perintah Kerja.
     */
    protected function lampiranSpk(): string
    {
        return <<<'HTML'
        <section>
            <table class="lampiran-head" style="width: 100%; margin-bottom: 14px; border: none; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: middle; border: none; padding: 0;">
                        <img src="/logo/sidebar-logo.png" alt="PT PLN Nusantara Power" style="height: 38px; width: auto; margin-bottom: 4px;"><br>
                        <b>LAMPIRAN SURAT PERINTAH KERJA</b>
                    </td>
                    <td style="text-align:right; vertical-align: middle; border: none; padding: 0;">SPK No. : <span class="fill">............ .SPK/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>LAMPIRAN SURAT PERINTAH KERJA</h1>
            <p style="text-align:center"><b>{{nama_pengadaan}}</b><br>{{unit_tujuan}}</p>

            <h3>A. Rincian Lingkup dan Harga Pekerjaan</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian Barang/Jasa</th><th style="width:9%">Qty</th><th style="width:9%">Satuan</th><th style="width:16%">Harga Satuan (Rp)</th><th style="width:16%">Jumlah (Rp)</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Sub Total</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>PPN</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="5"><b>Total Nilai SPK</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Terbilang:</b> <span class="fill">.................................................................</span></td></tr>
            </table>

            <h3>B. Spesifikasi Teknis</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Uraian</th><th>Spesifikasi yang Dipersyaratkan</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>C. Jadwal Pelaksanaan</h3>
            <table>
                <tr><th style="width:5%">No</th><th>Tahapan Pekerjaan</th><th style="width:18%">Mulai</th><th style="width:18%">Selesai</th><th style="width:14%">Bobot (%)</th></tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>D. Ketentuan Serah Terima</h3>
            <ol>
                <li>Penyerahan pekerjaan disertai Berita Acara Serah Terima Pekerjaan (BASTP).</li>
                <li>Pemeriksaan dilakukan oleh Tim Pemeriksa Kualitas Barang/Jasa paling lambat 14 (empat belas) hari kerja setelah penyerahan.</li>
                <li>Masa garansi/pemeliharaan selama <span class="fill">......</span> bulan sejak Berita Acara Pemeriksaan diterbitkan.</li>
            </ol>

            <table class="signature">
                <tr>
                    <td class="role">PIHAK KESATU<br>PT PLN Nusantara Power UP Kendari</td>
                    <td class="role">PIHAK KEDUA<br>Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>

            <p class="note">Template standar. Perbarui redaksi sesuai dokumen resmi melalui menu Data Master &rarr; Template Dokumen.</p>
        </section>
        HTML;
    }

    /**
     * Berita Acara Negosiasi together with its annex, in one file.
     */
    protected function beritaAcaraNegosiasi(): string
    {
        return <<<'HTML'
        <section>
            <style>
                @page { size: A4 landscape; margin: 15mm 20mm; }
            </style>
            <table class="lampiran-head" style="width: 100%; margin-bottom: 14px; border: none; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: middle; border: none; padding: 0;">
                        <img src="/logo/sidebar-logo.png" alt="PT PLN Nusantara Power" style="height: 38px; width: auto; margin-bottom: 4px;"><br>
                        <b>PT PLN NUSANTARA POWER<br>UP KENDARI</b>
                    </td>
                    <td style="text-align:right; vertical-align: middle; border: none; padding: 0;">Nomor : <span class="fill">............ /BAN/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>BERITA ACARA NEGOSIASI</h1>

            <p>Pada hari ini <span class="fill">..................</span> tanggal <span class="fill">..................</span> bulan <span class="fill">..................</span> tahun {{tahun}}, telah dilaksanakan negosiasi harga atas pekerjaan <b>{{nama_pengadaan}}</b> antara:</p>

            <table>
                <tr><td style="width:32%">Pejabat Pelaksana Pengadaan</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Penyedia Barang/Jasa</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Diwakili oleh</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Jabatan</td><td class="fill">&nbsp;</td></tr>
            </table>

            <h3>A. Dasar Negosiasi</h3>
            <ol>
                <li>Berita Acara Hasil Evaluasi pekerjaan di atas.</li>
                <li>Harga Perkiraan Sendiri/HPE sebesar {{nilai_hpe}} <i>({{nilai_hpe_terbilang}})</i>.</li>
                <li>Dokumen penawaran harga yang disampaikan Penyedia Barang/Jasa.</li>
            </ol>

            <h3>B. Hasil Negosiasi</h3>
            <table>
                <tr><td style="width:40%">Nilai HPE</td><td>{{nilai_hpe}}</td></tr>
                <tr><td>Harga Penawaran Awal</td><td class="fill">&nbsp;</td></tr>
                <tr><td><b>Harga Akhir Hasil Negosiasi</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td>Terbilang</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Selisih terhadap HPE</td><td class="fill">&nbsp;</td></tr>
                <tr><td>Persentase terhadap HPE</td><td class="fill">&nbsp;</td></tr>
            </table>
            <p>Harga Akhir sebagaimana tersebut di atas disepakati kedua belah pihak dan menjadi Harga Kontrak.</p>

            <h3>C. Kesepakatan Lain</h3>
            <ol>
                <li>Jangka waktu pelaksanaan: <span class="fill">..................</span></li>
                <li>Cara pembayaran: <span class="fill">..................</span></li>
                <li>Masa garansi/pemeliharaan: <span class="fill">..................</span></li>
                <li><span class="fill">..............................................................................</span></li>
            </ol>

            <p>Demikian Berita Acara ini dibuat dengan sebenarnya untuk dipergunakan sebagaimana mestinya.</p>

            <table class="signature">
                <tr>
                    <td class="role">Pejabat Pelaksana Pengadaan</td>
                    <td class="role">Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill">( Nama Jelas )</td>
                </tr>
            </table>
        </section>

        <section class="lampiran">
            <style>
                @page { size: A4 landscape; margin: 15mm 20mm; }
            </style>
            <table class="lampiran-head" style="width: 100%; margin-bottom: 14px; border: none; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: middle; border: none; padding: 0;">
                        <img src="/logo/sidebar-logo.png" alt="PT PLN Nusantara Power" style="height: 38px; width: auto; margin-bottom: 4px;"><br>
                        <b>LAMPIRAN BERITA ACARA NEGOSIASI</b>
                    </td>
                    <td style="text-align:right; vertical-align: middle; border: none; padding: 0;">Nomor : <span class="fill">............ /BAN/612/UPKD/{{tahun}}</span><br>Tanggal : {{tanggal_dokumen}}</td>
                </tr>
            </table>

            <h1>RINCIAN HASIL NEGOSIASI HARGA</h1>
            <p style="text-align:center"><b>{{nama_pengadaan}}</b></p>

            <table style="width: 100%; max-width: 100%; table-layout: fixed; word-break: break-word;">
                <tr>
                    <th style="width:5%">No</th>
                    <th style="width:29%">Uraian Barang/Jasa</th>
                    <th style="width:8%">Qty</th>
                    <th style="width:8%">Sat.</th>
                    <th style="width:16%">Harga Penawaran (Rp)</th>
                    <th style="width:16%">Harga Negosiasi (Rp)</th>
                    <th style="width:18%">Jumlah (Rp)</th>
                </tr>
                <tr><td>1</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>2</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>3</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td>4</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Sub Total</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>PPN</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="6"><b>Total Harga Akhir</b></td><td class="fill">&nbsp;</td></tr>
                <tr><td colspan="7"><b>Terbilang:</b> <span class="fill">.................................................................</span></td></tr>
            </table>

            <p class="note">Lampiran ini merupakan bagian yang tidak terpisahkan dari Berita Acara Negosiasi di atas dan dicetak dalam satu berkas yang sama.</p>

            <table class="signature">
                <tr>
                    <td class="role">Pejabat Pelaksana Pengadaan</td>
                    <td class="role">Penyedia Barang/Jasa</td>
                </tr>
                <tr><td class="space"></td><td class="space"></td></tr>
                <tr>
                    <td class="name fill">( Nama Jelas )</td>
                    <td class="name fill"><b>{{nama_direktur}}</b></td>
                </tr>
            </table>
        </section>
        HTML;
    }

    /**
     * Read the placeholders referenced by a template body.
     *
     * @return array<int, string>
     */
    protected function placeholdersIn(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }
}
