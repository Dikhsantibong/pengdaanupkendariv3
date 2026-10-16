import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption } from '@/types';

type ContractNumberFormat = MasterRecord & {
    code: string;
    name: string;
    prefix: string;
    unit_segment: string;
    sequence_length: number;
    starting_sequence: number;
    uses_work_category: boolean;
    for_amendment: boolean;
    procurement_method_id: string;
    procurement_method: string | null;
    description: string | null;
    sample: string;
    sort_order: number;
};

export default function ContractNumberFormats({
    records,
    procurementMethods,
}: {
    records: ContractNumberFormat[];
    procurementMethods: EnumOption[];
}) {
    return (
        <MasterDataPage<ContractNumberFormat>
            title="Format No Kontrak"
            description="Bentuk penomoran kontrak beserta urutan berjalannya. Setiap format menghitung nomornya sendiri per tahun, contoh KDD075.SPK/612/UPKD/2026."
            addLabel="Tambah Format"
            records={records}
            nameKey="code"
            columns={[
                { key: 'code', label: 'Kode', className: 'font-medium' },
                { key: 'name', label: 'Keterangan' },
                {
                    key: 'sample',
                    label: 'Nomor Berikutnya',
                    className: 'tabular',
                },
                {
                    key: 'procurement_method',
                    label: 'Metode Pengadaan',
                    render: (record) => record.procurement_method ?? '—',
                },
                { key: 'sort_order', label: 'Urutan', className: 'tabular' },
            ]}
            fields={[
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: SPK',
                    hint: 'Tercetak di tengah nomor, tepat setelah angka urut.',
                },
                {
                    name: 'name',
                    label: 'Keterangan',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: Surat Perintah Kerja',
                },
                {
                    name: 'prefix',
                    label: 'Awalan',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: KDD',
                },
                {
                    name: 'unit_segment',
                    label: 'Ruas Unit',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: 612/UPKD',
                    hint: 'Bagian setelah kode, sebelum tahun.',
                },
                {
                    name: 'sequence_length',
                    label: 'Jumlah Digit Urut',
                    type: 'number',
                    hint: '3 digit menghasilkan 075, 076, dan seterusnya.',
                },
                {
                    name: 'starting_sequence',
                    label: 'Nomor Urut Awal',
                    type: 'number',
                    hint: 'Nomor pertama yang dikeluarkan sistem. Isi dengan nomor lanjutan dari kontrak terakhir yang sudah terbit, misalnya 75 untuk SPK dan 20 untuk PJ. Setel ulang tiap awal tahun.',
                },
                {
                    name: 'uses_work_category',
                    label: 'Pilih Barang / Jasa',
                    type: 'switch',
                    hint: 'Saat format ini dipilih, form pengadaan meminta kategori Barang atau Jasa. Kategori menentukan template dokumen yang dipakai (mis. SPK Barang / SPK Jasa).',
                },
                {
                    name: 'for_amendment',
                    label: 'Untuk Nomor Amandemen',
                    type: 'switch',
                    hint: 'Format ini dipakai untuk menomori amandemen kontrak (contoh KDD054.Amd/612/UPKD/2026), tidak ditawarkan sebagai format kontrak pengadaan.',
                },
                {
                    name: 'procurement_method_id',
                    label: 'Metode Pengadaan',
                    type: 'select',
                    options: procurementMethods,
                    hint: 'Metode pengadaan tidak lagi diisi di form pengadaan, tetapi mengikuti format ini. Metode menentukan template RKS dan tahapan checklist yang dilewati (mis. Surat Pesanan melewati HPE, UPB, dan RKS).',
                },
                {
                    name: 'description',
                    label: 'Catatan',
                    type: 'text',
                    placeholder: 'Catatan internal, tidak ikut tercetak.',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                {
                    name: 'is_active',
                    label: 'Aktif',
                    type: 'switch',
                    hint: 'Hanya format aktif yang muncul saat membuat perencanaan pengadaan.',
                },
            ]}
            defaults={{
                code: '',
                name: '',
                prefix: 'KDD',
                unit_segment: '612/UPKD',
                sequence_length: 3,
                starting_sequence: 1,
                uses_work_category: false,
                for_amendment: false,
                procurement_method_id: 'none',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.contractNumberFormats.store().url}
            updateUrl={(record) =>
                masterData.contractNumberFormats.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.contractNumberFormats.destroy(record.id).url
            }
        />
    );
}

ContractNumberFormats.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        {
            title: 'Format No Kontrak',
            href: masterData.contractNumberFormats.index(),
        },
    ],
};
