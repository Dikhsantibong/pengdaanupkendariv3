import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type WorkDirector = MasterRecord & {
    name: string;
    description: string | null;
    sort_order: number;
};

export default function WorkDirectors({
    records,
}: {
    records: WorkDirector[];
}) {
    return (
        <MasterDataPage<WorkDirector>
            title="Direksi Pekerjaan"
            description="Pejabat yang dapat dipilih sebagai penanggung jawab pekerjaan pada form pembuatan pengadaan."
            addLabel="Tambah Direksi"
            records={records}
            nameKey="name"
            columns={[
                {
                    key: 'name',
                    label: 'Nama Jabatan',
                    className: 'font-medium',
                },
                { key: 'description', label: 'Keterangan' },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Jabatan',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Asman Pemeliharaan',
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
                    hint: 'Hanya data aktif yang muncul pada dropdown pengadaan.',
                },
            ]}
            defaults={{
                name: '',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.workDirectors.store().url}
            updateUrl={(record) =>
                masterData.workDirectors.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.workDirectors.destroy(record.id).url
            }
        />
    );
}

WorkDirectors.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Direksi Pekerjaan', href: masterData.workDirectors.index() },
    ],
};
