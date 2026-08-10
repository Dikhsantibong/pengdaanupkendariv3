import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, Send } from 'lucide-react';
import { SignaturePad } from '@/components/signature-pad';
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
import { formatDate, formatDateTime } from '@/lib/format';
import assessmentSigning from '@/routes/assessment-signing';
import { SigningShell } from './shell';

/** The scale printed on the official form. */
const LEVELS = [1, 2, 3, 4, 5];

type Aspect = {
    aspect_id: number;
    name: string;
    preamble: string | null;
    indicators: string[];
    sort_order: number;
    level: number | null;
};

type SigningForm = {
    assessor_name: string;
    scores: Array<{ aspect_id: number; level: number | null }>;
    signature: string | null;
};

export default function SignAssessment({
    token,
    assessment,
    form,
    assessorName,
    expiresAt,
    aspects,
}: {
    token: string;
    assessment: {
        project: string;
        vendor_name: string;
        po_number: string | null;
        po_date: string | null;
        form_number: string;
        revision_number: string;
        place: string;
    };
    form: {
        name: string;
        assessor_title: string;
        assessor_options: string[];
    };
    assessorName: string | null;
    expiresAt: string | null;
    aspects: Aspect[];
}) {
    const { data, setData, post, processing, errors } = useForm<SigningForm>({
        assessor_name: assessorName ?? '',
        scores: aspects.map((aspect) => ({
            aspect_id: aspect.aspect_id,
            level: aspect.level,
        })),
        signature: null,
    });

    const setLevel = (aspectId: number, value: number) =>
        setData(
            'scores',
            data.scores.map((score) =>
                score.aspect_id === aspectId
                    ? { ...score, level: value }
                    : score,
            ),
        );

    const remaining = data.scores.filter(
        (score) => score.level === null,
    ).length;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post(assessmentSigning.store(token).url, { preserveScroll: true });
    };

    return (
        <SigningShell
            title={form.name}
            subtitle="Formulir Penilaian Kinerja Penyedia Barang dan Jasa"
        >
            <Head title={`Penilaian ${form.name}`} />

            <form onSubmit={submit} className="space-y-4">
                <section className="rounded-lg border border-border bg-card p-4">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <Field label="Pekerjaan" value={assessment.project} />
                        <Field
                            label="Penyedia"
                            value={assessment.vendor_name}
                        />
                        <Field
                            label="No Kontrak"
                            value={
                                assessment.po_number === null
                                    ? '—'
                                    : `${assessment.po_number}${
                                          assessment.po_date
                                              ? ` · ${formatDate(assessment.po_date)}`
                                              : ''
                                      }`
                            }
                        />
                        <Field
                            label="Kedudukan Penilai"
                            value={form.assessor_title}
                        />
                    </dl>

                    <div className="mt-4 grid gap-1.5 sm:max-w-sm">
                        <Label htmlFor="assessor_name">Nama Penilai</Label>
                        {form.assessor_options.length > 0 ? (
                            <Select
                                value={data.assessor_name}
                                onValueChange={(value) =>
                                    setData('assessor_name', value)
                                }
                            >
                                <SelectTrigger id="assessor_name">
                                    <SelectValue placeholder="Pilih nama Anda" />
                                </SelectTrigger>
                                <SelectContent>
                                    {form.assessor_options.map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                id="assessor_name"
                                value={data.assessor_name}
                                onChange={(event) =>
                                    setData('assessor_name', event.target.value)
                                }
                                placeholder="Nama lengkap"
                                autoComplete="name"
                            />
                        )}
                        {errors.assessor_name && (
                            <p className="text-xs text-destructive">
                                {errors.assessor_name}
                            </p>
                        )}
                    </div>
                </section>

                <section className="space-y-3">
                    {aspects.map((aspect) => {
                        const current =
                            data.scores.find(
                                (score) => score.aspect_id === aspect.aspect_id,
                            )?.level ?? null;

                        return (
                            <article
                                key={aspect.aspect_id}
                                className="rounded-lg border border-border bg-card p-4"
                            >
                                <h2 className="text-sm font-semibold">
                                    {aspect.sort_order}. {aspect.name}
                                </h2>
                                {aspect.preamble && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {aspect.preamble}
                                    </p>
                                )}
                                <ol className="mt-1.5 ml-4 list-[lower-alpha] space-y-0.5 text-xs text-muted-foreground">
                                    {aspect.indicators.map((indicator) => (
                                        <li key={indicator}>{indicator}</li>
                                    ))}
                                </ol>

                                <div
                                    className="mt-3 flex flex-wrap gap-2"
                                    role="group"
                                    aria-label={`Nilai ${aspect.name}`}
                                >
                                    {LEVELS.map((value) => (
                                        <button
                                            key={value}
                                            type="button"
                                            aria-pressed={current === value}
                                            onClick={() =>
                                                setLevel(
                                                    aspect.aspect_id,
                                                    value,
                                                )
                                            }
                                            className={`h-11 min-w-11 flex-1 rounded-md border text-sm font-semibold transition-colors sm:flex-none sm:px-6 ${
                                                current === value
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-background hover:bg-accent'
                                            }`}
                                        >
                                            {value}
                                        </button>
                                    ))}
                                </div>
                            </article>
                        );
                    })}
                </section>

                <section className="rounded-lg border border-border bg-card p-4">
                    <h2 className="text-sm font-semibold">Tanda Tangan</h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {assessment.place}
                        {expiresAt
                            ? ` · Tautan berlaku sampai ${formatDateTime(expiresAt)}`
                            : ''}
                    </p>

                    <SignaturePad
                        className="mt-3"
                        onChange={(value) => setData('signature', value)}
                    />

                    {errors.signature && (
                        <p className="text-xs text-destructive">
                            {errors.signature}
                        </p>
                    )}
                </section>

                {(remaining > 0 || errors['scores.0.level']) && (
                    <p className="flex items-center gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400">
                        <AlertCircle className="size-4 shrink-0" />
                        {remaining > 0
                            ? `Masih ada ${remaining} aspek yang belum dinilai.`
                            : errors['scores.0.level']}
                    </p>
                )}

                <Button
                    type="submit"
                    className="h-12 w-full text-base"
                    disabled={processing}
                >
                    <Send className="size-4" />
                    Kirim Penilaian & Tanda Tangan
                </Button>

                <p className="pb-4 text-center text-xs text-muted-foreground">
                    Penilaian yang sudah dikirim tidak dapat diubah kembali.
                </p>
            </form>
        </SigningShell>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}
