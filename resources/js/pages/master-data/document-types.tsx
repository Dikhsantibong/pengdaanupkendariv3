import { MasterDataPage } from '@/components/master-data-page';
import type { MasterRecord } from '@/components/master-data-page';
import { dashboard } from '@/routes';
import masterData from '@/routes/master-data';
import type { EnumOption, ProcurementStage } from '@/types';

type DocumentType = MasterRecord & {
    code: string;
    name: string;
    stage: ProcurementStage;
    stage_label: string;
    upload_only: boolean;
    contract_number_format_id: string;
    contract_number_format: string | null;
    description: string | null;
    sort_order: number;
    template_count: number;
    active_template: { id: number; name: string; version: number } | null;
};

export default function DocumentTypes({
    records,
    stages,
    contractNumberFormats,
}: {
    records: DocumentType[];
    stages: EnumOption[];
    contractNumberFormats: EnumOption[];
}) {
    return (
        <MasterDataPage<DocumentType>
            title="Jenis Dokumen"
            description="Jenis dokumen yang dapat digenerate. Setiap jenis mereferensikan satu template aktif yang dapat diganti secara independen."
            addLabel="Tambah Jenis Dokumen"
            records={records}
            nameKey="name"
            columns={[
                {
                    key: 'name',
                    label: 'Jenis Dokumen',
                    className: 'font-medium',
                },
                { key: 'code', label: 'Kode', className: 'tabular' },
                { key: 'stage_label', label: 'Tahap' },
                {
                    key: 'contract_number_format',
                    label: 'Format',
                    render: (record) =>
                        record.contract_number_format ?? (
                            <span className="text-muted-foreground">Semua</span>
                        ),
                },
                {
                    key: 'active_template',
                    label: 'Template Aktif',
                    render: (record) =>
                        record.upload_only ? (
                            <span className="text-muted-foreground">
                                Hanya diunggah
                            </span>
                        ) : record.active_template === null ? (
                            <span className="text-status-batal">Belum ada</span>
                        ) : (
                            `${record.active_template.name} (v${record.active_template.version})`
                        ),
                },
                {
                    key: 'template_count',
                    label: 'Versi',
                    className: 'tabular',
                },
            ]}
            fields={[
                {
                    name: 'name',
                    label: 'Nama Dokumen',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: RKS (Rencana Kerja dan Syarat)',
                },
                {
                    name: 'code',
                    label: 'Kode',
                    type: 'text',
                    required: true,
                    placeholder: 'Contoh: rks',
                    hint: 'Dipakai sebagai bagian nama berkas hasil generate.',
                },
                {
                    name: 'stage',
                    label: 'Tahap',
                    type: 'select',
                    options: stages,
                    placeholder: 'Pilih tahap',
                },
                {
                    name: 'description',
                    label: 'Keterangan',
                    type: 'text',
                    placeholder: 'Opsional',
                },
                {
                    name: 'upload_only',
                    label: 'Hanya diunggah (tanpa generate)',
                    type: 'switch',
                    hint: 'Dokumen disiapkan di luar sistem lalu diunggah pada tahapannya. Tombol generate dan edit tidak ditampilkan.',
                },
                {
                    name: 'contract_number_format_id',
                    label: 'Hanya untuk Format Kontrak',
                    type: 'select',
                    options: contractNumberFormats,
                    hint: 'Bila dipilih, tahapan checklist hanya meminta dokumen ini pada pengadaan dengan format tersebut (mis. SPK - Syarat & Ketentuan (Pasal) hanya untuk SPK, karena PJ sudah memuat pasal di Surat Perjanjian).',
                },
                { name: 'sort_order', label: 'Urutan Tampil', type: 'number' },
                { name: 'is_active', label: 'Aktif', type: 'switch' },
            ]}
            defaults={{
                name: '',
                code: '',
                stage: 'perencanaan',
                upload_only: false,
                contract_number_format_id: 'none',
                description: '',
                sort_order: 0,
                is_active: true,
            }}
            storeUrl={masterData.documentTypes.store().url}
            updateUrl={(record) =>
                masterData.documentTypes.update(record.id).url
            }
            destroyUrl={(record) =>
                masterData.documentTypes.destroy(record.id).url
            }
        />
    );
}

DocumentTypes.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jenis Dokumen', href: masterData.documentTypes.index() },
    ],
};
