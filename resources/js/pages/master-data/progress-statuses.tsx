import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { StatusBadge } from '@/components/status-badge';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption, StatusCategory } from '@/types';

type ProgressStatus = MasterRecord & {
    name: string;
    slug: string;
    category: StatusCategory;
    sort_order: number;
    is_default: boolean;
};

export default function ProgressStatuses({
    records,
    categories,
}: {
    records: ProgressStatus[];
    categories: EnumOption[];
}) {
    return (
        <MasterDataPage<ProgressStatus>
            title="Status Progres"
            description="Pilihan status progres pengadaan beserta kategori siklus hidupnya yang menentukan warna badge di seluruh aplikasi."
            addLabel="Tambah Status"
            records={records}
            nameKey="name"
            columns={[
                {
                    key: 'name',
                    label: 'Status',
                    render: (record) => (
                        <StatusBadge
                            label={record.name}
                            category={record.category}
                        />
                    ),
                },
                {
                    key: 'category',
                    label: 'Kategori',
                    render: (record) =>
                        categories.find(
                            (category) => category.value === record.category,
                        )?.label ?? record.category,
                },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
                {
                    key: 'is_default',
                    label: 'Default',
                    render: (record) => (record.is_default ? 'Ya' : '—'),
                },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Status',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Penyusunan RKS',
                },
                {
                    name: 'category',
                    label: 'Kategori Siklus',
                    type: 'select',
                    options: categories,
                    placeholder: 'Pilih kategori',
                    hint: 'Menentukan warna badge: pending (abu), batal (merah gelap), berjalan (amber), selesai (hijau tua).',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_default',
                    label: 'Status Awal',
                    type: 'switch',
                    hint: 'Status yang otomatis dipakai saat pengadaan baru dibuat.',
                },
                { name: 'is_active', label: 'Aktif', type: 'switch' },
            ]}
            defaults={{
                name: '',
                category: 'berjalan',
                sort_order: 0,
                is_default: false,
                is_active: true,
            }}
            storeUrl={masterData.progressStatuses.store().url}
            updateUrl={(record) =>
                masterData.progressStatuses.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.progressStatuses.destroy(record.id).url
            }
        />
    );
}

ProgressStatuses.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Status Progres', href: masterData.progressStatuses.index() },
    ],
};
