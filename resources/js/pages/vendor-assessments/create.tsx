import { Head, router, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
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
    vendor_name: string;
    form_number: string;
    revision_number: string;
    form_date: string | null;
    place: string;
};

export default function CreateVendorAssessment({
    defaults,
    procurements,
}: {
    defaults: Defaults;
    procurements: Option[];
}) {
    const form = useForm({
        procurement_id: defaults.procurement_id,
        project: defaults.project,
        po_number: defaults.po_number ?? '',
        po_date: defaults.po_date ?? '',
        vendor_name: defaults.vendor_name,
        form_number: defaults.form_number,
        revision_number: defaults.revision_number,
        form_date: defaults.form_date ?? '',
        place: defaults.place,
        notes: '',
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
                            <Input
                                id="vendor_name"
                                value={form.data.vendor_name}
                                onChange={(event) =>
                                    form.setData(
                                        'vendor_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Contoh: PT. Surveyor Indonesia"
                                required
                                autoComplete="off"
                            />
                            <InputError message={form.errors.vendor_name} />
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
