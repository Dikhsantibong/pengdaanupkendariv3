import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type ProcurementMethod = MasterRecord & {
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
};

export default function ProcurementMethods({
    records,
}: {
    records: ProcurementMethod[];
}) {
    return (
        <MasterDataPage<ProcurementMethod>
            title="Metode Pengadaan"
            description="Cara pengadaan dilaksanakan, dipilih pada form pembuatan pengadaan dan tercetak pada dokumen."
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
                    placeholder: 'Contoh: Surat Pesanan',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: surat-pesanan',
                    hint: 'Dipakai sebagai referensi internal dan pada dokumen.',
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
                    hint: 'Hanya metode aktif yang muncul pada dropdown pengadaan.',
                },
            ]}
            defaults={{
                name: '',
                code: '',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.procurementMethods.store().url}
            updateUrl={(record) =>
                masterData.procurementMethods.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.procurementMethods.destroy(record.id).url
            }
        />
    );
}

ProcurementMethods.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Metode Pengadaan',
            href: masterData.procurementMethods.index(),
        },
    ],
};
