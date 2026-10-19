import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type SelectionMethod = MasterRecord & {
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
};

export default function SelectionMethods({
    records,
}: {
    records: SelectionMethod[];
}) {
    return (
        <MasterDataPage<SelectionMethod>
            title="Metode Pengadaan"
            description="Cara mitra dipilih (Penunjukan Langsung, Pengadaan Langsung, Lelang Terbuka). Dipilih PIC Perencana di detail pengadaan dan tercetak pada dokumen."
            addLabel="Tambah Metode"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Metode', className: 'font-medium' },
                { key: 'code', label: 'Kode', className: 'tabular' },
                { key: 'description', label: 'Keterangan' },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Metode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Penunjukan Langsung',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: penunjukan-langsung',
                    hint: 'Disimpan sebagai slug untuk referensi internal.',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Opsional',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya metode aktif yang dapat dipilih PIC Perencana.',
                },
            ]}
            defaults={{
                name: '',
                code: '',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.selectionMethods.store().url}
            updateUrl={(record) =>
                masterData.selectionMethods.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.selectionMethods.destroy(record.id).url
            }
        />
    );
}

SelectionMethods.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Metode Pengadaan',
            href: masterData.selectionMethods.index(),
        },
    ],
};
