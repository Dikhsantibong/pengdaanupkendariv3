import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption } from '@/types';

type AssessmentForm = MasterRecord & {
    code: string;
    name: string;
    assessor_title: string;
    assessor_name: string | null;
    assessor_options: string[];
    description: string | null;
    aspect_ids: number[];
    aspect_names: string[];
    sort_order: number;
};

export default function AssessmentForms({
    records,
    aspectOptions,
}: {
    records: AssessmentForm[];
    aspectOptions: EnumOption[];
}) {
    return (
        <MasterDataPage<AssessmentForm>
            title="Lembar Penilai"
            description="Lembar penilaian yang diisi tiap penilai. Setiap lembar membawa aspeknya sendiri, dan rekapitulasi merata-ratakan aspek yang sama antar penilai."
            addLabel="Tambah Lembar"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Lembar', className: 'font-medium' },
                { key: 'assessor_title', label: 'Kedudukan Penilai' },
                {
                    key: 'aspect_names',
                    label: 'Aspek Dinilai',
                    render: (record) =>
                        record.aspect_names.length === 0
                            ? '—'
                            : `${record.aspect_names.length} aspek`,
                },
                {
                    key: 'assessor_options',
                    label: 'Pilihan Nama',
                    render: (record) =>
                        record.assessor_options.length === 0
                            ? 'Diketik bebas'
                            : `${record.assessor_options.length} nama`,
                },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Lembar',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Direksi Pekerjaan',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: direksi-pekerjaan',
                    hint: 'Disimpan dalam bentuk slug, dipakai sebagai nama berkas saat unduh semua dokumen.',
                },
                {
                    name: 'assessor_title',
                    label: 'Kedudukan Penilai',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Asman Pemeliharaan',
                    hint: 'Tercetak di atas garis tanda tangan.',
                },
                {
                    name: 'aspect_ids',
                    label: 'Aspek yang Dinilai',
                    type: 'multiselect',
                    options: aspectOptions,
                    hint: 'Aspek yang dilepas tidak menghapus nilai yang sudah pernah diberikan pada penilaian lama.',
                },
                {
                    name: 'assessor_options',
                    label: 'Pilihan Nama Penilai',
                    type: 'list',
                    addLabel: 'Tambah Nama',
                    placeholder: 'Contoh: MUSRIYADI',
                    hint: 'Kosongkan bila nama penilai diketik bebas. Bila diisi, penilai wajib memilih dari daftar ini, termasuk pada halaman tanda tangan WhatsApp.',
                },
                {
                    name: 'assessor_name',
                    label: 'Nama Penilai Terakhir',
                    type: 'text',
                    placeholder:
                        'Terisi otomatis setelah lembar ditandatangani',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Catatan singkat mengenai lembar ini.',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya lembar aktif yang dibuatkan baris nilai pada penilaian baru dan ikut terunduh.',
                },
            ]}
            defaults={{
                name: '',
                code: '',
                assessor_title: '',
                assessor_name: '',
                assessor_options: [],
                description: '',
                aspect_ids: [],
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.assessmentForms.store().url}
            updateUrl={(record) =>
                masterData.assessmentForms.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.assessmentForms.destroy(record.id).url
            }
        />
    );
}

AssessmentForms.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lembar Penilai', href: masterData.assessmentForms.index() },
    ],
};
