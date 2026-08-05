import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption, ProcurementStage } from '@/types';

type ChecklistItem = MasterRecord & {
    stage: ProcurementStage;
    stage_label: string;
    name: string;
    description: string | null;
    is_optional: boolean;
    sort_order: number;
    excluded_procurement_method_ids: number[];
};

export default function ChecklistItems({
    records,
    stages,
    procurementMethods,
}: {
    records: ChecklistItem[];
    stages: EnumOption[];
    procurementMethods: EnumOption[];
}) {
    const methodLabel = (id: number) =>
        procurementMethods.find((method) => Number(method.value) === id)?.label;

    return (
        <MasterDataPage<ChecklistItem>
            title="Item Checklist"
            description="Daftar dokumen dan tahapan yang harus dilengkapi pada tahap perencanaan maupun pelaksanaan."
            addLabel="Tambah Item"
            records={records}
            nameKey="name"
            columns={[
                { key: 'stage_label', label: 'Tahap' },
                { key: 'name', label: 'Item', className: 'font-medium' },
                { key: 'description', label: 'Keterangan' },
                {
                    key: 'is_optional',
                    label: 'Sifat',
                    render: (record) =>
                        record.is_optional ? 'Opsional' : 'Wajib',
                },
                {
                    key: 'excluded_procurement_method_ids',
                    label: 'Dilewati Metode',
                    render: (record) =>
                        record.excluded_procurement_method_ids.length === 0
                            ? '—'
                            : record.excluded_procurement_method_ids
                                  .map(methodLabel)
                                  .filter(Boolean)
                                  .join(', '),
                },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'stage',
                    label: 'Tahap',
                    type: 'select',
                    options: stages,
                    placeholder: 'Pilih tahap',
                },
                {
                    name: 'name',
                    label: 'Nama Item',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Nota Dinas Usulan',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Opsional',
                },
                {
                    name: 'is_optional',
                    label: 'Opsional',
                    type: 'switch',
                    hint: 'Item opsional tidak wajib dicentang sebelum pengajuan persetujuan.',
                },
                {
                    name: 'excluded_procurement_method_ids',
                    label: 'Dilewati oleh Metode Pengadaan',
                    type: 'multiselect',
                    options: procurementMethods,
                    hint: 'Centang metode yang tidak melalui tahapan ini. Metode yang tidak dicentang tetap memakai item ini.',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                { name: 'is_active', label: 'Aktif', type: 'switch' },
            ]}
            defaults={{
                stage: 'perencanaan',
                name: '',
                description: '',
                is_optional: false,
                excluded_procurement_method_ids: [],
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.checklistItems.store().url}
            updateUrl={(record) =>
                masterData.checklistItems.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.checklistItems.destroy(record.id).url
            }
        />
    );
}

ChecklistItems.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Item Checklist', href: masterData.checklistItems.index() },
    ],
};
