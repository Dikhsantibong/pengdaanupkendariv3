import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type Partner = MasterRecord & {
    name: string;
    vendor_code: string | null;
    director_name: string | null;
    address: string | null;
    sort_order: number;
};

export default function Partners({ records }: { records: Partner[] }) {
    return (
        <MasterDataPage<Partner>
            title="Mitra"
            description="Daftar mitra (penyedia barang/jasa) yang pernah diinput di perencanaan dan penilaian penyedia. Saat mengetik nama calon mitra, sistem menyarankan mitra dari daftar ini dan mengisi kode rekanan, direktur, serta alamatnya. Mitra baru yang diinput otomatis ditambahkan ke sini."
            addLabel="Tambah Mitra"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Nama Mitra', className: 'font-medium' },
                {
                    key: 'vendor_code',
                    label: 'Kode Rekanan',
                    className: 'tabular',
                },
                { key: 'director_name', label: 'Nama Direktur' },
                { key: 'address', label: 'Alamat' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Mitra',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: CV. AKASHA NUSA ENGINEERING',
                },
                {
                    name: 'vendor_code',
                    label: 'Kode Rekanan',
                    type: 'text',
                    placeholder: 'Contoh: V07452',
                },
                {
                    name: 'director_name',
                    label: 'Nama Direktur',
                    type: 'text',
                },
                {
                    name: 'address',
                    label: 'Alamat Perusahaan',
                    type: 'textarea',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya mitra aktif yang disarankan saat mengetik nama calon mitra.',
                },
            ]}
            defaults={{
                name: '',
                vendor_code: '',
                director_name: '',
                address: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.partners.store().url}
            updateUrl={(record) => masterData.partners.update(record.id).url}
            destroyUrl={(record) => masterData.partners.destroy(record.id).url}
        />
    );
}

Partners.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Mitra', href: masterData.partners.index() },
    ],
};
