<?php

namespace Database\Seeders;

use App\Models\AssessmentAspect;
use App\Models\AssessmentForm;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The official Formulir Penilaian Kinerja Penyedia Barang dan Jasa.
 *
 * Nine aspects, five assessor sheets, and the mapping of which sheet scores
 * which aspect. All of it is ordinary reference data, so the unit revises the
 * form from the Data Master screens rather than waiting on a developer.
 */
class VendorAssessmentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The Asman who may sign the Direksi Pekerjaan sheet.
     *
     * @var array<int, string>
     */
    protected const DIREKSI_ASSESSORS = [
        'MUSRIYADI',
        'SADRI',
        'EKO YULI WIDIYATMOKO',
        'AGUS SALIM',
        'ROBY FIRMANSYAH',
    ];

    /**
     * Which aspects each assessor sheet scores.
     *
     * @var array<string, array<int, string>>
     */
    protected const FORM_ASPECTS = [
        'pengadaan' => ['integritas', 'kerja-sama', 'mutu', 'waktu', 'harga'],
        'icc-gudang' => [
            'integritas',
            'kerja-sama',
            'waktu',
            'manajemen-k3',
            'lingkungan',
            'keamanan',
        ],
        'direksi-pekerjaan' => ['mutu', 'manajemen-energi'],
        'lingkungan' => ['lingkungan'],
        'k3-keamanan' => ['manajemen-k3', 'keamanan'],
    ];

    /**
     * Seed the aspects, the assessor sheets and the mapping between them.
     */
    public function run(): void
    {
        $this->seedAspects();
        $this->seedForms();
        $this->linkFormAspects();

        $this->command->info('Formulir Penilaian Kinerja Penyedia berhasil dipasang.');
    }

    /**
     * The nine numbered aspects of the official form.
     */
    protected function seedAspects(): void
    {
        $aspects = [
            [
                'integritas',
                'ASPEK INTEGRITAS',
                null,
                [
                    'Prilaku Kejujuran',
                    'Kepatuhan dan Ketaatan terhadap peraturan dan etika yang berlaku',
                    'Prilaku kriminal dan bahaya Narkoba',
                    'Prilaku KKN',
                ],
            ],
            [
                'kerja-sama',
                'ASPEK KERJA SAMA',
                null,
                [
                    'Keaktifan',
                    'Keterlibatan langsung pemilik dalam bertransaksi',
                    'Fleksibilitas dalam berkomunikasi',
                    'Layanan Purna Jual',
                ],
            ],
            [
                'mutu',
                'ASPEK MUTU',
                null,
                [
                    'Kualitas barang atau pekerjaan secara fisik',
                    'Kualitas barang atau pekerjaan secara fungsi',
                    'Pemenuhan dan keaslian terhadap dokumen yang disyaratkan',
                ],
            ],
            [
                'waktu',
                'ASPEK WAKTU',
                null,
                [
                    'Ketepatan waktu kedatangan barang atau penyelesaian pekerjaan',
                    'Kelengkapan jumlah barang yang dikirim atau volume pekerjaan',
                    'Kelengkapan dokumen saat pengiriman barang atau jasa',
                ],
            ],
            [
                'harga',
                'ASPEK HARGA',
                'Kewajaran Harga Penawaran dan atau Harga PO terhadap:',
                [
                    'Harga Perkiraan Sendiri (HPS)',
                    'Harga Pengadaan sebelumnya',
                    'Harga Dipasaran',
                ],
            ],
            [
                'manajemen-k3',
                'ASPEK MANAJEMEN K3',
                'Kepatuhan terhadap peraturan dan perundangan yang berlaku dalam manajemen K3 yang meliputi:',
                [
                    'Alat Perlindungan Diri (APD)',
                    'Rambu - Rambu K3',
                    'Pemakaian safety tools',
                    'Safety permit',
                ],
            ],
            [
                'lingkungan',
                'ASPEK LINGKUNGAN',
                'Kepatuhan terhadap peraturan dan perundangan yang berlaku dalam manajemen lingkungan yang meliputi:',
                [
                    'Kemasan dan pengaman yang disaratkan',
                    'Petunjuk penanganan, SOP yang disyaratkan',
                    'Pemenuhan dokumen yang disyaratkan (Misal : MSDS dll)',
                    'Pemenuhan terhadap penanganan limbah',
                ],
            ],
            [
                'keamanan',
                'ASPEK KEAMANAN',
                'Kepatuhan terhadap peraturan dan perundangan yang berlaku dalam manajemen pengamanan yang meliputi:',
                [
                    'Ketertiban dalam bertamu',
                    'Tertib dalam pembuatan working permit',
                    'Tidak memasuki zona terlarang tanpa seijin resmi dari',
                    'Ijin pengiriman dan Pengeluaran barang (dari keamanan unit)',
                    'Koordinasi loading material',
                ],
            ],
            [
                'manajemen-energi',
                'ASPEK MANAJEMEN ENERGI',
                'Kepatuhan terhadap peraturan dan perundangan yang berlaku dalam manajemen energi yang meliputi:',
                [
                    'Hemat dalam penggunaan sumber energi (Listrik, Air, Bahan Bakar, Tenaga, Waktu).',
                    'Menggunakan peralatan kerja yang hemat energi ketika melaksanakan',
                    'Perilaku hemat energi dalam penggunaan alat kerja atau',
                    'Menawarkan produk-produk hemat energy (mengacu pada produk-produk yang sudah didaftarkan kementrian energi)',
                ],
            ],
        ];

        foreach ($aspects as $index => [$code, $name, $preamble, $indicators]) {
            AssessmentAspect::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'preamble' => $preamble,
                    'indicators' => $indicators,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * The five sheets, each signed by its own assessor.
     */
    protected function seedForms(): void
    {
        $forms = [
            [
                'pengadaan',
                'Pengadaan',
                'PENGADAAN',
                null,
                null,
                'Dinilai oleh fungsi pengadaan.',
            ],
            [
                'icc-gudang',
                'ICC & Gudang',
                'TL INVENTORY CONTROL & GUDANG',
                'BASTIAL',
                null,
                'Dinilai oleh Inventory Control dan Gudang.',
            ],
            [
                'direksi-pekerjaan',
                'Direksi Pekerjaan',
                'Direksi Pekerjaan',
                null,
                self::DIREKSI_ASSESSORS,
                'Diwakili satu Asman per bidang. Nama penilai dipilih saat mengisi formulir.',
            ],
            [
                'lingkungan',
                'Lingkungan',
                'TL LINGKUNGAN',
                'SADRI',
                null,
                'Dinilai oleh fungsi lingkungan.',
            ],
            [
                'k3-keamanan',
                'K3 & Keamanan',
                'TL K3 DAN KEAMANAN',
                'MUSRIYADI',
                null,
                'Dinilai oleh fungsi K3 dan keamanan.',
            ],
        ];

        foreach ($forms as $index => [$code, $name, $title, $assessor, $options, $description]) {
            AssessmentForm::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'assessor_title' => $title,
                    'assessor_name' => $assessor,
                    'assessor_options' => $options,
                    'description' => $description,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Attach the aspects each sheet scores.
     */
    protected function linkFormAspects(): void
    {
        $aspectIds = AssessmentAspect::query()->pluck('id', 'code');

        foreach (self::FORM_ASPECTS as $formCode => $aspectCodes) {
            $form = AssessmentForm::query()->where('code', $formCode)->first();

            if ($form === null) {
                continue;
            }

            $links = [];

            foreach ($aspectCodes as $index => $aspectCode) {
                if (isset($aspectIds[$aspectCode])) {
                    $links[$aspectIds[$aspectCode]] = ['sort_order' => $index + 1];
                }
            }

            $form->aspects()->sync($links);
        }
    }
}
