import { Link, router } from '@inertiajs/react';
import {
    Check,
    ClipboardPlus,
    Copy,
    ExternalLink,
    MessageCircle,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatCurrency, formatDate, formatDateTime } from '@/lib/format';
import vendorAssessments from '@/routes/vendor-assessments';

export type AssessmentRequestRow = {
    id: number;
    status: 'submitted' | 'processed';
    project: string;
    vendor_name: string;
    work_director_name: string | null;
    contract_number: string;
    contract_date: string;
    term: string;
    invoice_amount: number;
    bastp_number: string;
    bastp_date: string;
    has_penalty: boolean;
    submitter_name: string;
    submitter_phone: string | null;
    submitted_at: string | null;
    vendor_assessment_id: number | null;
};

const STATUS = {
    submitted: {
        label: 'Request baru',
        className: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
    },
    processed: {
        label: 'Sudah dibuat penilaian',
        className:
            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    },
} as const;

/**
 * The fixed link vendors fill their invoice details in at, and the requests
 * they sent, ready to be opened as an assessment.
 */
export function AssessmentRequestsPanel({
    requests,
    formUrl,
}: {
    requests: AssessmentRequestRow[];
    formUrl: string;
}) {
    const [copied, setCopied] = useState(false);
    const waiting = requests.filter((row) => row.status === 'submitted').length;

    // Copied with the greeting it is sent to the vendor with.
    const message = [
        'Semangat Pagi',
        'Bapak/Ibu',
        '',
        'Untuk request Penilaian Vendor mohon agar dibantu mengisi rincian tagihan pada link berikut :',
        '',
        formUrl,
        '',
        'Terima Kasih',
    ].join('\n');

    const copy = () =>
        void navigator.clipboard.writeText(message).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });

    return (
        <section className="rounded-md border border-border bg-card">
            <div className="flex flex-col gap-1 border-b border-border p-4">
                <div className="flex items-center gap-2">
                    <p className="section-label">Request Penilaian Vendor</p>
                    {waiting > 0 && (
                        <Badge className="bg-sky-600 text-white">
                            {waiting} request baru
                        </Badge>
                    )}
                </div>
                <p className="text-xs text-muted-foreground">
                    Tautan di bawah tetap dan tidak berubah, sehingga bisa
                    dicantumkan di kontrak (variabel template{' '}
                    <code className="rounded bg-muted px-1">
                        {'{{tautan_penilaian_vendor}}'}
                    </code>
                    ). Vendor cukup membuka tautan dan mengisi rincian
                    tagihannya tanpa akun; setiap kiriman muncul di bawah dan
                    bisa langsung dibuat menjadi formulir penilaian. Request
                    yang sudah dibuat penilaiannya pindah ke folder mitranya.
                </p>
            </div>

            <div className="flex flex-col gap-2 border-b border-border bg-muted/30 p-4 sm:flex-row sm:items-center">
                <Input
                    value={formUrl}
                    readOnly
                    onFocus={(event) => event.currentTarget.select()}
                    aria-label="Tautan request penilaian vendor"
                    className="h-9 font-mono text-xs sm:flex-1"
                />
                <div className="flex gap-2">
                    <Button variant="outline" onClick={copy}>
                        {copied ? (
                            <Check className="size-4" />
                        ) : (
                            <Copy className="size-4" />
                        )}
                        {copied ? 'Tersalin' : 'Salin Tautan'}
                    </Button>
                    <Button asChild variant="outline">
                        <a href={formUrl} target="_blank" rel="noopener">
                            <ExternalLink className="size-4" />
                            Buka
                        </a>
                    </Button>
                </div>
            </div>

            {requests.length === 0 ? (
                <p className="p-4 text-sm text-muted-foreground">
                    Tidak ada request yang menunggu diproses.
                </p>
            ) : (
                <ul className="divide-y divide-border">
                    {requests.map((row) => (
                        <RequestItem key={row.id} row={row} />
                    ))}
                </ul>
            )}
        </section>
    );
}

function RequestItem({ row }: { row: AssessmentRequestRow }) {
    const status = STATUS[row.status];
    const [processing, setProcessing] = useState(false);

    // One click: the assessment and a WhatsApp link for every sheet.
    const process = () =>
        router.post(
            vendorAssessments.requests.process(row.id).url,
            {},
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );

    const remove = () => {
        if (window.confirm('Hapus request ini dari daftar?')) {
            router.delete(vendorAssessments.requests.destroy(row.id).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <li className="flex flex-col gap-3 p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span
                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${status.className}`}
                >
                    {status.label}
                </span>
                <span className="text-sm font-medium">{row.project}</span>
                <span className="text-xs text-muted-foreground">
                    dikirim {formatDateTime(row.submitted_at)} oleh{' '}
                    {row.submitter_name}
                    {row.submitter_phone ? ` (${row.submitter_phone})` : ''}
                </span>
            </div>

            <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2 lg:grid-cols-3">
                <Detail label="Pekerjaan" value={row.project} />
                <Detail label="Pelaksana" value={row.vendor_name} />
                <Detail
                    label="Direksi Pekerjaan"
                    value={row.work_director_name ?? ''}
                />
                <Detail label="No Kontrak/DO" value={row.contract_number} />
                <Detail
                    label="Tgl. Kontrak"
                    value={formatDate(row.contract_date)}
                />
                <Detail label="Termin" value={row.term} />
                <Detail
                    label="Nilai Tagihan"
                    value={formatCurrency(row.invoice_amount)}
                />
                <Detail label="No BAPP/BASTP" value={row.bastp_number} />
                <Detail
                    label="Tgl BAPP/BASTP"
                    value={formatDate(row.bastp_date)}
                />
                <Detail
                    label="Denda"
                    value={row.has_penalty ? 'Ada' : 'Tidak ada'}
                />
            </dl>

            <div className="flex flex-wrap gap-2">
                {row.vendor_assessment_id === null ? (
                    <>
                        <Button
                            size="sm"
                            onClick={process}
                            disabled={processing}
                            className="bg-green-600 text-white hover:bg-green-700"
                        >
                            <MessageCircle className="size-4" />
                            {processing
                                ? 'Memproses…'
                                : 'Proses & Siapkan WhatsApp'}
                        </Button>
                        <Button asChild size="sm" variant="outline">
                            <Link
                                href={vendorAssessments.create({
                                    query: { request_id: row.id },
                                })}
                            >
                                <ClipboardPlus className="size-4" />
                                Buat Penilaian
                            </Link>
                        </Button>
                    </>
                ) : (
                    <Button asChild size="sm" variant="outline">
                        <Link
                            href={vendorAssessments.show(
                                row.vendor_assessment_id,
                            )}
                        >
                            Buka Penilaian
                        </Link>
                    </Button>
                )}
                <Button size="sm" variant="ghost" onClick={remove}>
                    <Trash2 className="size-4" />
                    Hapus
                </Button>
            </div>
        </li>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex gap-2">
            <dt className="w-32 shrink-0 text-xs text-muted-foreground">
                {label}
            </dt>
            <dd className="font-medium">{value || '—'}</dd>
        </div>
    );
}
