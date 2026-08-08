import { Head } from '@inertiajs/react';
import { CheckCircle2, Clock, Ban } from 'lucide-react';
import { formatDateTime } from '@/lib/format';
import { SigningShell } from './shell';

type State = 'submitted' | 'revoked' | 'expired';

const ICONS = {
    submitted: CheckCircle2,
    revoked: Ban,
    expired: Clock,
} as const;

const TONES = {
    submitted: 'text-emerald-600 dark:text-emerald-400',
    revoked: 'text-destructive',
    expired: 'text-amber-600 dark:text-amber-400',
} as const;

const HEADINGS = {
    submitted: 'Penilaian sudah terkirim',
    revoked: 'Tautan dibatalkan',
    expired: 'Tautan kedaluwarsa',
} as const;

export default function ClosedAssessmentLink({
    state,
    reason,
    form,
    project,
    vendorName,
    assessorName,
    submittedAt,
}: {
    state: State;
    reason: string | null;
    form: string;
    project: string;
    vendorName: string;
    assessorName: string | null;
    submittedAt: string | null;
}) {
    const Icon = ICONS[state];

    return (
        <SigningShell
            title={form}
            subtitle="Formulir Penilaian Kinerja Penyedia Barang dan Jasa"
        >
            <Head title={HEADINGS[state]} />

            <section className="rounded-lg border border-border bg-card p-6 text-center">
                <Icon className={`mx-auto size-10 ${TONES[state]}`} />

                <h1 className="mt-3 text-lg font-semibold">
                    {HEADINGS[state]}
                </h1>

                {reason && (
                    <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                        {reason}
                    </p>
                )}

                <dl className="mx-auto mt-5 grid max-w-md gap-2 text-left text-sm">
                    <Row label="Pekerjaan" value={project} />
                    <Row label="Penyedia" value={vendorName} />
                    {assessorName && (
                        <Row label="Penilai" value={assessorName} />
                    )}
                    {submittedAt && (
                        <Row
                            label="Dikirim"
                            value={formatDateTime(submittedAt)}
                        />
                    )}
                </dl>

                <p className="mt-6 text-xs text-muted-foreground">
                    PT PLN Nusantara Power UP Kendari
                </p>
            </section>
        </SigningShell>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-b border-border/60 pb-1.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}
