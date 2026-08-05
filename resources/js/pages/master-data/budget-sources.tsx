import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type BudgetSource = MasterRecord & {
    code: string;
    name: string;
    description: string | null;
    sort_order: number;
};

export default function BudgetSources({
    records,
}: {
    records: BudgetSource[];
}) {
    return (
        <MasterDataPage<BudgetSource>
            title="Sumber Anggaran"
            description="Sumber pendanaan pengadaan, dipilih pada form pembuatan pengadaan dan tercetak pada dokumen."
            addLabel="Tambah Sumber Anggaran"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Sumber', className: 'font-medium' },
                { key: 'code', label: 'Kode', className: 'tabular' },
                { key: 'description', label: 'Keterangan' },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Sumber',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: AO',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: AO',
                    hint: 'Disimpan dalam huruf kapital.',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Contoh: Anggaran Operasi',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya sumber aktif yang muncul pada dropdown pengadaan.',
                },
            ]}
            defaults={{
                name: '',
                code: '',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.budgetSources.store().url}
            updateUrl={(record) =>
                masterData.budgetSources.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.budgetSources.destroy(record.id).url
            }
        />
    );
}

BudgetSources.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sumber Anggaran', href: masterData.budgetSources.index() },
    ],
};
