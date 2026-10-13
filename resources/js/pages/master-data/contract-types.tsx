import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';

type ContractType = MasterRecord & {
    code: string;
    name: string;
    description: string | null;
    work_category: string;
    work_category_label: string;
    sort_order: number;
};

export default function ContractTypes({
    records,
}: {
    records: ContractType[];
}) {
    return (
        <MasterDataPage<ContractType>
            title="Jenis Kontrak"
            description="Pilihan kategori yang muncul saat memilih jenis no kontrak SPK atau PJ pada form pengadaan. Format dokumen menentukan template SPK/PJ yang dipakai (Barang atau Jasa)."
            addLabel="Tambah Jenis Kontrak"
            records={records}
            nameKey="name"
            columns={[
                { key: 'name', label: 'Jenis', className: 'font-medium' },
                { key: 'code', label: 'Kode', className: 'tabular' },
                {
                    key: 'work_category_label',
                    label: 'Format Dokumen',
                },
                { key: 'description', label: 'Keterangan' },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Jenis Kontrak',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: KHS',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: khs',
                    hint: 'Disimpan dalam bentuk slug, dipakai sebagai kunci acuan.',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Contoh: Kontrak Harga Satuan',
                },
                {
                    name: 'work_category',
                    label: 'Format Dokumen',
                    type: 'select',
                    options: [
                        { value: 'umum', label: 'Umum' },
                        { value: 'barang', label: 'Barang' },
                        { value: 'jasa', label: 'Jasa' },
                    ],
                    hint: 'Template SPK/PJ yang digenerate untuk pengadaan dengan jenis kontrak ini.',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya jenis aktif yang muncul pada dropdown pengadaan.',
                },
            ]}
            defaults={{
                name: '',
                code: '',
                description: '',
                work_category: 'umum',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.contractTypes.store().url}
            updateUrl={(record) =>
                masterData.contractTypes.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.contractTypes.destroy(record.id).url
            }
        />
    );
}

ContractTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jenis Kontrak', href: masterData.contractTypes.index() },
    ],
};
