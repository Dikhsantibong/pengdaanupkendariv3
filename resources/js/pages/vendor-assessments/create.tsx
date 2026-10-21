import { Head, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PartnerNameInput } from '@/components/partner-name-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { WorkDirectorSelect } from '@/components/work-director-select';
import type { WorkDirector } from '@/components/work-director-select';
import { dashboard } from '@/routes';
import vendorAssessments from '@/routes/vendor-assessments';
import type { Option } from '@/types';

/** Sentinel for "no linked procurement": a Select cannot carry an empty value. */
const NONE = 'none';

type Defaults = {
    procurement_id: number | null;
    project: string;
    po_number: string | null;
    po_date: string | null;
    bastp_date: string | null;
    vendor_name: string;
    work_director_name: string;
    has_penalty: boolean;
    notes: string;
    vendor_assessment_request_id: number | null;
    form_number: string;
    revision_number: string;
    form_date: string | null;
    place: string;
};

export default function CreateVendorAssessment({
    defaults,
    procurements,
    workDirectors,
}: {
    defaults: Defaults;
    procurements: Option[];
    /** The names of the Direksi Pekerjaan sheet, phone numbers left off. */
    workDirectors: WorkDirector[];
}) {
    const form = useForm({
        procurement_id: defaults.procurement_id,
        project: defaults.project,
        po_number: defaults.po_number ?? '',
        po_date: defaults.po_date ?? '',
        bastp_date: defaults.bastp_date ?? '',
        vendor_name: defaults.vendor_name,
        work_director_name: defaults.work_director_name ?? '',
        has_penalty: defaults.has_penalty,
        form_number: defaults.form_number,
        revision_number: defaults.revision_number,
        form_date: '2024-06-10',
        place: defaults.place,
        notes: defaults.notes,
        vendor_assessment_request_id: defaults.vendor_assessment_request_id,
    });

    return (
        <>
            <Head title="Buat Formulir Penilaian" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    eyebrow="SMT-FM-DAN-02.02"
                    title="Buat Formulir Penilaian Kinerja"
                    description="Isi kepala formulir. Lembar penilaian tiap fungsi disiapkan otomatis setelah formulir dibuat."
                    actions={
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(vendorAssessments.index().url)
                            }
                        >
                            Kembali
                        </Button>
                    }
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(vendorAssessments.store().url);
                    }}
                    className="space-y-6"
                >
                    {defaults.vendor_assessment_request_id !== null && (
                        <p className="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200">
                            Data diisi dari rincian tagihan yang dikirim lewat
                            tautan permintaan. Termin, nilai tagihan dan No
                            BAPP/BASTP disimpan pada Catatan.
                        </p>
                    )}

                    <section className="space-y-4 rounded-md border border-border bg-card p-5">
                        <p className="section-label">Identitas Penilaian</p>

                        <div className="grid gap-2">
                            <Label htmlFor="procurement_id">
                                Pengadaan Terkait
                            </Label>
                            <Select
                                value={
                                    form.data.procurement_id === null
                                        ? NONE
                                        : String(form.data.procurement_id)
                                }
                                onValueChange={(value) => {
                                    const id =
                                        value === NONE ? null : Number(value);

                                    form.setData('procurement_id', id);

                                    const picked = procurements.find(
                                        (option) => option.value === id,
                                    );

                                    // Fill the project from the procurement, but
                                    // leave anything already typed alone.
                                    if (picked && form.data.project === '') {
                                        const parts = picked.label.split(' — ');

                                        form.setData(
                                            'project',
                                            parts[1] ?? picked.label,
                                        );
                                    }
                                }}
                            >
                                <SelectTrigger
                                    id="procurement_id"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Tanpa kaitan pengadaan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE}>
                                        Tanpa kaitan pengadaan
                                    </SelectItem>
                                    {procurements.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={String(option.value)}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.procurement_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="project">Project / Pekerjaan</Label>
                            <Input
                                id="project"
                                value={form.data.project}
                                onChange={(event) =>
                                    form.setData('project', event.target.value)
                                }
                                required
                                autoComplete="off"
                            />
                            <InputError message={form.errors.project} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="vendor_name">
                                Penyedia Barang/Jasa
                            </Label>
                            <PartnerNameInput
                                id="vendor_name"
                                value={form.data.vendor_name}
                                onChange={(name) =>
                                    form.setData('vendor_name', name)
                                }
                                placeholder="Contoh: PT. Surveyor Indonesia"
                                required
                            />
                            <InputError message={form.errors.vendor_name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="has_penalty">Denda</Label>
                            <Select
                                value={form.data.has_penalty ? 'ada' : 'tidak'}
                                onValueChange={(v) =>
                                    form.setData('has_penalty', v === 'ada')
                                }
                            >
                                <SelectTrigger
                                    id="has_penalty"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Pilih denda" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="ada">
                                        Ada Denda
                                    </SelectItem>
                                    <SelectItem value="tidak">
                                        Tidak Ada Denda
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.has_penalty} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="po_number">No Kontrak</Label>
                                <Input
                                    id="po_number"
                                    value={form.data.po_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'po_number',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                />
                                <InputError message={form.errors.po_number} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="po_date">Tanggal Kontrak</Label>
                                <Input
                                    id="po_date"
                                    type="date"
                                    value={form.data.po_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'po_date',
                                            event.target.value,
                                        )
                                    }
                                    className="tabular"
                                />
                                <InputError message={form.errors.po_date} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="bastp_date">Tanggal BASTP</Label>
                            <Input
                                id="bastp_date"
                                type="date"
                                value={form.data.bastp_date}
                                onChange={(event) =>
                                    form.setData(
                                        'bastp_date',
                                        event.target.value,
                                    )
                                }
                                className="tabular"
                            />
                            <InputError message={form.errors.bastp_date} />
                        </div>

                        <WorkDirectorSelect
                            names={workDirectors}
                            bastpDate={form.data.bastp_date}
                            value={form.data.work_director_name}
                            onChange={(name) =>
                                form.setData('work_director_name', name)
                            }
                            error={form.errors.work_director_name}
                        />
                    </section>

                    <section className="space-y-4 rounded-md border border-border bg-card p-5">
                        <p className="section-label">Kepala Formulir</p>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="form_number">
                                    Nomor Formulir
                                </Label>
                                <Input
                                    id="form_number"
                                    value={form.data.form_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'form_number',
                                            event.target.value,
                                        )
                                    }
                                    className="tabular"
                                />
                                <InputError message={form.errors.form_number} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="revision_number">
                                    Nomor Revisi
                                </Label>
                                <Input
                                    id="revision_number"
                                    value={form.data.revision_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'revision_number',
                                            event.target.value,
                                        )
                                    }
                                    className="tabular"
                                />
                                <InputError
                                    message={form.errors.revision_number}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="form_date">
                                    Tanggal Formulir
                                </Label>
                                <Input
                                    id="form_date"
                                    type="date"
                                    value={form.data.form_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'form_date',
                                            event.target.value,
                                        )
                                    }
                                    className="tabular"
                                />
                                <InputError message={form.errors.form_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="place">
                                    Tempat Penandatanganan
                                </Label>
                                <Input
                                    id="place"
                                    value={form.data.place}
                                    onChange={(event) =>
                                        form.setData(
                                            'place',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.place} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="notes">Catatan</Label>
                            <Textarea
                                id="notes"
                                rows={3}
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                placeholder="Catatan internal, tidak tercetak pada formulir."
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                    </section>

                    <div className="flex items-center gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Buat Formulir
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit(vendorAssessments.index().url)
                            }
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateVendorAssessment.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penilaian Penyedia', href: vendorAssessments.index() },
        { title: 'Buat Formulir', href: vendorAssessments.create() },
    ],
};
