import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption } from '@/types';

type UnitManager = MasterRecord & {
    name: string;
    position: string;
    level: string;
    field: string | null;
    target_unit_ids: number[];
    target_unit_id: string | null;
    unit_names: string[];
    description: string | null;
    sort_order: number;
};

/** Ranks that hold several units, and the one that holds a single unit. */
const SEVERAL_UNITS = ['manager_ul', 'tl'];
const ONE_UNIT = 'tl_ophar';

export default function UnitManagers({
    records,
    levelOptions,
    fieldOptions,
    unitOptions,
}: {
    records: UnitManager[];
    levelOptions: EnumOption[];
    fieldOptions: EnumOption[];
    unitOptions: EnumOption[];
}) {
    return (
        <MasterDataPage<UnitManager>
            title="Manager Unit"
            description="Pejabat PT PLN Nusantara Power UP Kendari dan Unit Layanannya. Manager (jabatan tertinggi) bertanda tangan pada dokumen pengadaan (seperti Surat Pesanan / PO)."
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
                {
                    key: 'unit_names',
                    label: 'Unit',
                    render: (record) =>
                        record.unit_names.length === 0
                            ? '—'
                            : record.unit_names.join(', '),
                },
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
                    name: 'level',
                    label: 'Jabatan',
                    type: 'select',
                    options: levelOptions,
                    placeholder: 'Pilih jabatan',
                },
                {
                    name: 'field',
                    label: 'Bidang',
                    type: 'select',
                    options: fieldOptions,
                    placeholder: 'Pilih bidang',
                    hint: 'Jabatan tertulis "TL" diikuti bidangnya, mis. TL PEMELIHARAAN.',
                    visible: (values) => values.level === 'tl',
                },
                {
                    name: 'target_unit_ids',
                    label: 'Unit yang Dipegang',
                    type: 'multiselect',
                    options: unitOptions,
                    hint: 'Diambil dari Data Master Unit Tujuan.',
                    visible: (values) =>
                        SEVERAL_UNITS.includes(String(values.level)),
                },
                {
                    name: 'target_unit_id',
                    label: 'Unit yang Dipegang',
                    type: 'select',
                    options: unitOptions,
                    placeholder: 'Pilih satu unit',
                    hint: 'Jabatan tertulis "TL OPHAR" diikuti nama unitnya.',
                    visible: (values) => values.level === ONE_UNIT,
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Contoh: ULPLTD WUA WUA',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Manager aktif dengan urutan terkecil digunakan sebagai nama manager saat mencetak template dokumen.',
                },
            ]}
            defaults={{
                name: '',
                level: 'manager',
                field: null,
                target_unit_ids: [],
                target_unit_id: null,
                description: '',
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
