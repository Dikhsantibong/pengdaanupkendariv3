import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Send } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import assessmentRequests from '@/routes/assessment-requests';
import { SigningShell } from './shell';

type DetailsForm = {
    project: string;
    vendor_name: string;
    contract_number: string;
    contract_date: string;
    term: string;
    invoice_amount: string;
    bastp_number: string;
    bastp_date: string;
    has_penalty: boolean | null;
    submitter_name: string;
    submitter_phone: string;
};

/** Digits only, shown back with thousands separators. */
function groupThousands(value: string): string {
    const digits = value.replace(/\D+/g, '');

    return digits === '' ? '' : Number(digits).toLocaleString('id-ID');
}

type Submitted = {
    project: string;
    vendor_name: string;
    submitted_at: string | null;
};

/**
 * The invoice details a vendor fills in from the fixed link printed in the
 * contract, so their assessment can be prepared.
 */
export default function AssessmentRequestForm({
    submitted,
}: {
    submitted: Submitted | null;
}) {
    const [showForm, setShowForm] = useState(submitted === null);
    const { data, setData, post, processing, errors, transform, reset } =
        useForm<DetailsForm>({
            project: '',
            vendor_name: '',
            contract_number: '',
            contract_date: '',
            term: '',
            invoice_amount: '',
            bastp_number: '',
            bastp_date: '',
            has_penalty: null,
            submitter_name: '',
            submitter_phone: '',
        });

    transform((values) => ({
        ...values,
        invoice_amount: values.invoice_amount.replace(/\D+/g, ''),
    }));

    const field = (
        key: keyof Omit<DetailsForm, 'has_penalty'>,
        label: string,
        props: React.ComponentProps<typeof Input> = {},
    ) => (
        <div className="grid gap-1.5">
            <Label htmlFor={key}>{label}</Label>
            <Input
                id={key}
                value={data[key]}
                onChange={(event) => setData(key, event.target.value)}
                required
                autoComplete="off"
                {...props}
            />
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <SigningShell
            title="Request Penilaian Vendor"
            subtitle="PT PLN Nusantara Power UP Kendari"
        >
            <Head title="Request Penilaian Vendor" />

            {!showForm && submitted !== null ? (
                <section className="rounded-lg border border-border bg-card p-6 text-center">
                    <CheckCircle2 className="mx-auto size-10 text-emerald-600 dark:text-emerald-400" />
                    <h1 className="mt-3 text-lg font-semibold">
                        Rincian tagihan sudah terkirim
                    </h1>
                    <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                        Terima kasih. Request penilaian vendor Anda sudah
                        diterima dan akan diproses oleh tim pengadaan.
                    </p>
                    <dl className="mx-auto mt-5 grid max-w-md gap-2 text-left text-sm">
                        <SummaryRow
                            label="Pekerjaan"
                            value={submitted.project}
                        />
                        <SummaryRow
                            label="Pelaksana"
                            value={submitted.vendor_name}
                        />
                        {submitted.submitted_at && (
                            <SummaryRow
                                label="Dikirim"
                                value={formatDateTime(submitted.submitted_at)}
                            />
                        )}
                    </dl>
                    <Button
                        variant="outline"
                        className="mt-6"
                        onClick={() => setShowForm(true)}
                    >
                        Isi Request Lain
                    </Button>
                </section>
            ) : (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(assessmentRequests.store().url, {
                            onSuccess: () => {
                                reset();
                                setShowForm(false);
                            },
                        });
                    }}
                    className="space-y-4 rounded-lg border border-border bg-card p-5"
                >
                    <div>
                        <h1 className="text-base font-semibold">
                            Rincian Tagihan
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Untuk request Penilaian Vendor mohon agar dibantu
                            mengisi rincian tagihan berikut.
                        </p>
                    </div>

                    {field('project', 'Pekerjaan')}
                    {field('vendor_name', 'Pelaksana', {
                        placeholder: 'Contoh: PT. Sinar Teknik',
                    })}

                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('contract_number', 'No Kontrak/DO')}
                        {field('contract_date', 'Tgl. Kontrak', {
                            type: 'date',
                        })}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('term', 'Termin', {
                            placeholder: 'Contoh: Termin 1 (100%)',
                        })}
                        <div className="grid gap-1.5">
                            <Label htmlFor="invoice_amount">
                                Nilai Tagihan
                            </Label>
                            <div className="relative">
                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground">
                                    Rp
                                </span>
                                <Input
                                    id="invoice_amount"
                                    value={data.invoice_amount}
                                    onChange={(event) =>
                                        setData(
                                            'invoice_amount',
                                            groupThousands(event.target.value),
                                        )
                                    }
                                    inputMode="numeric"
                                    className="pl-9"
                                    required
                                    autoComplete="off"
                                />
                            </div>
                            <InputError message={errors.invoice_amount} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('bastp_number', 'No BAPP/BASTP')}
                        {field('bastp_date', 'Tgl BAPP/BASTP', {
                            type: 'date',
                        })}
                    </div>

                    <fieldset className="grid gap-1.5">
                        <legend className="mb-1.5 text-sm font-medium">
                            Denda
                        </legend>
                        <div className="flex gap-2">
                            {[
                                { value: true, label: 'Ada' },
                                { value: false, label: 'Tidak ada' },
                            ].map((option) => (
                                <Button
                                    key={option.label}
                                    type="button"
                                    variant={
                                        data.has_penalty === option.value
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={
                                        data.has_penalty === option.value
                                    }
                                    onClick={() =>
                                        setData('has_penalty', option.value)
                                    }
                                >
                                    {option.label}
                                </Button>
                            ))}
                        </div>
                        <InputError message={errors.has_penalty} />
                    </fieldset>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {field('submitter_name', 'Nama Pengisi')}
                        <div className="grid gap-1.5">
                            <Label htmlFor="submitter_phone">
                                No. HP / WhatsApp (opsional)
                            </Label>
                            <Input
                                id="submitter_phone"
                                value={data.submitter_phone}
                                onChange={(event) =>
                                    setData(
                                        'submitter_phone',
                                        event.target.value,
                                    )
                                }
                                inputMode="tel"
                                placeholder="08xxxxxxxxxx"
                                autoComplete="off"
                            />
                            <InputError message={errors.submitter_phone} />
                        </div>
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        <Send className="size-4" />
                        Kirim Rincian Tagihan
                    </Button>
                </form>
            )}
        </SigningShell>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-border/60 pb-1.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}
