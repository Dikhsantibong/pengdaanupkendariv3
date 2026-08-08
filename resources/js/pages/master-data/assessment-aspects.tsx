import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type AssessmentAspect = MasterRecord & {
    code: string;
    name: string;
    preamble: string | null;
    indicators: string[];
    indicator_count: number;
    form_count: number;
    sort_order: number;
};

export default function AssessmentAspects({
    records,
}: {
    records: AssessmentAspect[];
}) {
    return (
        <MasterDataPage<AssessmentAspect>
            title="Aspek Penilaian"
            description="Aspek bernomor pada Formulir Penilaian Kinerja Penyedia Barang dan Jasa, beserta indikator a, b, c yang tercetak di bawahnya."
            addLabel="Tambah Aspek"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Aspek', className: 'font-medium' },
                { key: 'code', label: 'Kode', className: 'tabular' },
                {
                    key: 'indicator_count',
                    label: 'Indikator',
                    className: 'tabular',
                    render: (record) => `${record.indicator_count} butir`,
                },
                {
                    key: 'form_count',
                    label: 'Dipakai Lembar',
                    className: 'tabular',
                },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Aspek',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: ASPEK INTEGRITAS',
                    hint: 'Disimpan dalam huruf kapital seperti pada formulir resmi.',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: integritas',
                    hint: 'Disimpan dalam bentuk slug, dipakai sebagai kunci acuan.',
                },
                {
                    name: 'preamble',
                    label: 'Kalimat Pembuka',
                    type: 'textarea',
                    placeholder:
                        'Kalimat pengantar yang tercetak sebelum daftar indikator.',
                },
                {
                    name: 'indicators',
                    label: 'Indikator Penilaian',
                    type: 'list',
                    addLabel: 'Tambah Indikator',
                    placeholder: 'Tulis satu indikator.',
                    hint: 'Tercetak berurutan sebagai a, b, c pada formulir. Gunakan panah untuk mengubah urutan.',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya aspek aktif yang dapat dipilih pada lembar penilai dan muncul pada rekapitulasi.',
                },
            ]}
            defaults={{
                name: '',
                code: '',
                preamble: '',
                indicators: [],
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.assessmentAspects.store().url}
            updateUrl={(record) =>
                masterData.assessmentAspects.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.assessmentAspects.destroy(record.id).url
            }
        />
    );
}

AssessmentAspects.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Aspek Penilaian',
            href: masterData.assessmentAspects.index(),
        },
    ],
};
