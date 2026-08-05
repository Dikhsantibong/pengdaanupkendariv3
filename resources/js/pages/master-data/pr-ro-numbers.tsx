import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type PrRoNumber = MasterRecord & {
    number: string;
    description: string | null;
    source: string;
};

export default function PrRoNumbers({ records }: { records: PrRoNumber[] }) {
    return (
        <MasterDataPage<PrRoNumber>
            title="Nomor PR/RO"
            description="Daftar nomor PR/RO yang tersedia dari sistem Smart SCM dan dapat dipilih pada form pengadaan."
            addLabel="Tambah Nomor"
            records={records}
            nameKey="number"
            columns={[
                {
                    key: 'number',
                    label: 'Nomor PR/RO',
                    className: 'tabular font-medium',
                },
                { key: 'description', label: 'Keterangan' },
                { key: 'source', label: 'Sumber' },
            ]}
            fields={[
                {
                    name: 'number',
                    label: 'Nomor PR/RO',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: PR-1000123456',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Opsional',
                },
                {
                    name: 'source',
                    label: 'Sumber Data',
                    type: 'text',
                    required: true,
                    hint: 'Sistem asal nomor, misalnya Smart SCM.',
                },
                { name: 'is_active', label: 'Aktif', type: 'switch' },
            ]}
            defaults={{
                number: '',
                description: '',
                source: 'Smart SCM',
                is_active: true,
            }}
            storeUrl={masterData.prRoNumbers.store().url}
            updateUrl={(record) => masterData.prRoNumbers.update(record.id).url}
            destroyUrl={(record) =>
                masterData.prRoNumbers.destroy(record.id).url
            }
        />
    );
}

PrRoNumbers.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Nomor PR/RO', href: masterData.prRoNumbers.index() },
    ],
};
