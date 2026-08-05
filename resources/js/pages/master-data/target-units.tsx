import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type TargetUnit = MasterRecord & {
    name: string;
    system_name: string | null;
    sort_order: number;
};

export default function TargetUnits({ records }: { records: TargetUnit[] }) {
    return (
        <MasterDataPage<TargetUnit>
            title="Unit Tujuan"
            description="Daftar unit pembangkit yang dapat dipilih sebagai tujuan pengadaan."
            addLabel="Tambah Unit"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Nama Unit', className: 'font-medium' },
                { key: 'system_name', label: 'Sistem' },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Unit',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: PLTD Poasia',
                },
                {
                    name: 'system_name',
                    label: 'Nama Sistem',
                    type: 'text',
                    placeholder: 'Contoh: Sistem Isolated Ereke',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya data aktif yang muncul pada dropdown pengadaan.',
                },
            ]}
            defaults={{
                name: '',
                system_name: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.targetUnits.store().url}
            updateUrl={(record) => masterData.targetUnits.update(record.id).url}
            destroyUrl={(record) =>
                masterData.targetUnits.destroy(record.id).url
            }
        />
    );
}

TargetUnits.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Unit Tujuan', href: masterData.targetUnits.index() },
    ],
};
