import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type UnitManager = MasterRecord & {
    name: string;
    position: string;
    description: string | null;
    sort_order: number;
};

export default function UnitManagers({
    records,
}: {
    records: UnitManager[];
}) {
    return (
        <MasterDataPage<UnitManager>
            title="Manager Unit"
            description="Pejabat Manager PT PLN Nusantara Power UP Kendari yang bertanda tangan pada dokumen pengadaan (seperti Surat Pesanan / PO)."
            addLabel="Tambah Manager"
            records={records}
            nameKey="name"
            columns={[
                {
                    key: 'name',
                    label: 'Nama Manager',
                    className: 'font-medium',
                },
                { key: 'position', label: 'Jabatan' },
                { key: 'description', label: 'Keterangan' },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Lengkap & Gelar',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: MUHAMMAD RUSLI',
                },
                {
                    name: 'position',
                    label: 'Jabatan',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: MANAGER',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Contoh: Manager PT PLN Nusantara Power UP Kendari',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Data aktif akan digunakan sebagai nama manager saat mencetak template dokumen.',
                },
            ]}
            defaults={{
                name: '',
                position: 'MANAGER',
                description: 'Manager PT PLN Nusantara Power UP Kendari',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.unitManagers.store().url}
            updateUrl={(record) =>
                masterData.unitManagers.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.unitManagers.destroy(record.id).url
            }
        />
    );
}

UnitManagers.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Manager Unit', href: masterData.unitManagers.index() },
    ],
};
