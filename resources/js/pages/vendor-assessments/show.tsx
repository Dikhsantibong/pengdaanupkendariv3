import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Ban,
    Check,
    Copy,
    Download,
    Link2,
    MessageCircle,
    Printer,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatDateTime } from '@/lib/format';
import { dashboard } from '@/routes';
import vendorAssessments from '@/routes/vendor-assessments';

/** The scale printed on the official form. */
const LEVELS = [1, 2, 3, 4, 5];
const UNSCORED = 'none';

type AspectRow = {
    aspect_id: number;
    name: string;
    preamble: string | null;
    indicators: string[];
    sort_order: number;
    level: number | null;
    note: string | null;
    scored_by: string | null;
    scored_at: string | null;
};

type Invitation = {
    url: string;
    whatsapp_url: string | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    expires_at: string | null;
    opened_at: string | null;
    submitted_at: string | null;
    revoked_at: string | null;
    is_open: boolean;
    has_signature: boolean;
};

type FormSheet = {
    id: number;
    code: string;
    name: string;
    assessor_title: string;
    assessor_name: string | null;
    assessor_options: string[];
    description: string | null;
    aspects: AspectRow[];
    invitation: Invitation | null;
};

type RecapAspect = {
    aspect_id: number;
    name: string;
    preamble: string | null;
    indicators: string[];
    average: number | null;
    contributors: Array<{ form: string; level: number }>;
    pending: number;
};

type Assessment = {
    id: number;
    procurement_number: string | null;
    project: string;
    po_number: string | null;
    po_date: string | null;
    vendor_name: string;
    form_number: string;
    revision_number: string;
    form_date: string | null;
    place: string;
    notes: string | null;
    created_by: string | null;
};

/** Render a level the way the form does, with a comma decimal mark. */
function level(value: number | null): string {
    if (value === null) {
        return '—';
    }

    return value
        .toFixed(2)
        .replace(/\.?0+$/, '')
        .replace('.', ',');
}

export default function ShowVendorAssessment({
    assessment,
    forms,
    recap,
}: {
    assessment: Assessment;
    forms: FormSheet[];
    recap: {
        aspects: RecapAspect[];
        overall_average: number | null;
        scored: number;
        total: number;
    };
}) {
    const printUrl = (formId?: number) =>
        vendorAssessments.print(
            formId === undefined
                ? { assessment: assessment.id }
                : { assessment: assessment.id, form: formId },
        ).url;

    return (
        <>
            <Head title={`Penilaian ${assessment.vendor_name}`} />

            <div className="flex flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    eyebrow={`${assessment.form_number} · Rev ${assessment.revision_number}`}
                    title="Penilaian Kinerja Penyedia Barang dan Jasa"
                    description={`${assessment.vendor_name} · ${assessment.project}`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.visit(vendorAssessments.index().url)
                                }
                            >
                                <ArrowLeft className="size-4" />
                                Kembali
                            </Button>
                            <Button asChild variant="outline">
                                <a href={printUrl()}>
                                    <Printer className="size-4" />
                                    Unduh Rekapitulasi
                                </a>
                            </Button>
                            <Button asChild>
                                <a
                                    href={
                                        vendorAssessments.downloadAll(
                                            assessment.id,
                                        ).url
                                    }
                                >
                                    <Download className="size-4" />
                                    Unduh Semua Dokumen
                                </a>
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    if (
                                        window.confirm(
                                            'Arsipkan formulir penilaian ini?',
                                        )
                                    ) {
                                        router.delete(
                                            vendorAssessments.destroy(
                                                assessment.id,
                                            ).url,
                                        );
                                    }
                                }}
                            >
                                <Trash2 className="size-4" />
                                Arsipkan
                            </Button>
                        </div>
                    }
                />

                <dl className="grid gap-3 rounded-md border border-border bg-card p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Pekerjaan" value={assessment.project} />
                    <Field label="Penyedia" value={assessment.vendor_name} />
                    <Field
                        label="No PO"
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
                        label="Nilai Akhir"
                        value={`${level(recap.overall_average)} (${recap.scored}/${recap.total} aspek dinilai)`}
                    />
                </dl>

                <Tabs defaultValue="rekap">
                    <TabsList className="flex-wrap">
                        <TabsTrigger value="rekap">Rekapitulasi</TabsTrigger>
                        {forms.map((sheet) => (
                            <TabsTrigger key={sheet.id} value={sheet.code}>
                                {sheet.name}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="rekap" className="mt-4">
                        <RecapPanel recap={recap} printUrl={printUrl()} />
                    </TabsContent>

                    {forms.map((sheet) => (
                        <TabsContent
                            key={sheet.id}
                            value={sheet.code}
                            className="mt-4"
                        >
                            <SheetPanel
                                assessmentId={assessment.id}
                                sheet={sheet}
                                printUrl={printUrl(sheet.id)}
                            />
                        </TabsContent>
                    ))}
                </Tabs>
            </div>
        </>
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

/**
 * The WhatsApp handoff for one sheet.
 *
 * Nothing is sent from the server. Issuing mints the link, and the button opens
 * WhatsApp with the message already written so the administrator presses send
 * from their own account.
 */
function SigningLinkPanel({
    assessmentId,
    sheet,
}: {
    assessmentId: number;
    sheet: FormSheet;
}) {
    const invitation = sheet.invitation;
    const [name, setName] = useState(invitation?.recipient_name ?? '');
    const [phone, setPhone] = useState(invitation?.recipient_phone ?? '');
    const [copied, setCopied] = useState(false);

    const issue = () =>
        router.post(
            vendorAssessments.links.store({
                assessment: assessmentId,
                form: sheet.id,
            }).url,
            { recipient_name: name || null, recipient_phone: phone || null },
            { preserveScroll: true },
        );

    const revoke = () =>
        router.delete(
            vendorAssessments.links.destroy({
                assessment: assessmentId,
                form: sheet.id,
            }).url,
            { preserveScroll: true },
        );

    const copy = () => {
        if (invitation === null) {
            return;
        }

        void navigator.clipboard.writeText(invitation.url).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        });
    };

    const status = (() => {
        if (invitation === null) {
            return null;
        }

        if (invitation.submitted_at !== null) {
            return `Sudah ditandatangani ${formatDateTime(invitation.submitted_at)}`;
        }

        if (invitation.revoked_at !== null) {
            return 'Tautan dibatalkan';
        }

        if (!invitation.is_open) {
            return 'Tautan kedaluwarsa';
        }

        if (invitation.opened_at !== null) {
            return `Dibuka ${formatDateTime(invitation.opened_at)}, belum dikirim`;
        }

        return `Terkirim, belum dibuka · berlaku sampai ${formatDateTime(invitation.expires_at)}`;
    })();

    return (
        <div className="border-b border-border bg-muted/30 p-4">
            <div className="flex flex-wrap items-end gap-2">
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`recipient-name-${sheet.id}`}
                        className="text-xs"
                    >
                        Nama Penerima
                    </Label>
                    <Input
                        id={`recipient-name-${sheet.id}`}
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder="Nama penandatangan"
                        className="h-9 w-56"
                        autoComplete="off"
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label
                        htmlFor={`recipient-phone-${sheet.id}`}
                        className="text-xs"
                    >
                        Nomor WhatsApp
                    </Label>
                    <Input
                        id={`recipient-phone-${sheet.id}`}
                        value={phone}
                        onChange={(event) => setPhone(event.target.value)}
                        placeholder="08xxxxxxxxxx"
                        className="h-9 w-44"
                        inputMode="tel"
                        autoComplete="off"
                    />
                </div>

                <Button variant="outline" onClick={issue}>
                    <Link2 className="size-4" />
                    {invitation === null ? 'Buat Tautan' : 'Buat Tautan Baru'}
                </Button>

                {invitation !== null && (
                    <>
                        <Button variant="outline" onClick={copy}>
                            {copied ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                            {copied ? 'Tersalin' : 'Salin Tautan'}
                        </Button>

                        {invitation.whatsapp_url !== null && (
                            <Button asChild>
                                <a
                                    href={invitation.whatsapp_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <MessageCircle className="size-4" />
                                    Kirim via WhatsApp
                                </a>
                            </Button>
                        )}

                        {invitation.is_open && (
                            <Button variant="ghost" onClick={revoke}>
                                <Ban className="size-4" />
                                Batalkan
                            </Button>
                        )}
                    </>
                )}
            </div>

            {status !== null && (
                <p className="mt-2 text-xs text-muted-foreground">
                    {status}
                    {invitation?.has_signature
                        ? ' · tanda tangan tersimpan'
                        : ''}
                </p>
            )}

            {invitation === null && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Buat tautan untuk mengirim lembar ini ke penilai. Penilai
                    cukup membuka tautan, memberi nilai, dan tanda tangan tanpa
                    perlu akun.
                </p>
            )}
        </div>
    );
}

/**
 * One assessor sheet: the aspects it scores, and the level given to each.
 */
function SheetPanel({
    assessmentId,
    sheet,
    printUrl,
}: {
    assessmentId: number;
    sheet: FormSheet;
    printUrl: string;
}) {
    const [assessorName, setAssessorName] = useState(sheet.assessor_name ?? '');
    const [levels, setLevels] = useState<Record<number, number | null>>(
        Object.fromEntries(
            sheet.aspects.map((aspect) => [aspect.aspect_id, aspect.level]),
        ),
    );

    const form = useForm({});

    const submit = () => {
        router.put(
            vendorAssessments.scores.update({
                assessment: assessmentId,
                form: sheet.id,
            }).url,
            {
                assessor_name: assessorName || null,
                scores: sheet.aspects.map((aspect) => ({
                    aspect_id: aspect.aspect_id,
                    level: levels[aspect.aspect_id] ?? null,
                    note: aspect.note,
                })),
            },
            { preserveScroll: true },
        );
    };

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-end lg:justify-between">
                <div className="space-y-1">
                    <h2 className="text-sm font-semibold">
                        Lembar Penilaian — {sheet.name}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Penilai: {sheet.assessor_title}
                        {sheet.description ? ` · ${sheet.description}` : ''}
                    </p>
                </div>

                <div className="flex flex-wrap items-end gap-2">
                    <div className="grid gap-1.5">
                        <Label
                            htmlFor={`assessor-${sheet.id}`}
                            className="text-xs"
                        >
                            Nama Penilai
                        </Label>
                        {sheet.assessor_options.length > 0 ? (
                            <Select
                                value={
                                    assessorName === ''
                                        ? UNSCORED
                                        : assessorName
                                }
                                onValueChange={(value) =>
                                    setAssessorName(
                                        value === UNSCORED ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id={`assessor-${sheet.id}`}
                                    className="h-9 w-56"
                                >
                                    <SelectValue placeholder="Pilih penilai" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={UNSCORED}>
                                        Belum dipilih
                                    </SelectItem>
                                    {sheet.assessor_options.map((name) => (
                                        <SelectItem key={name} value={name}>
                                            {name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : (
                            <Input
                                id={`assessor-${sheet.id}`}
                                value={assessorName}
                                onChange={(event) =>
                                    setAssessorName(event.target.value)
                                }
                                placeholder="Nama yang menandatangani"
                                className="h-9 w-56"
                                autoComplete="off"
                            />
                        )}
                    </div>
                    <Button asChild variant="outline">
                        <a href={printUrl}>
                            <Printer className="size-4" />
                            Cetak Lembar
                        </a>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Save className="size-4" />
                        Simpan Penilaian
                    </Button>
                </div>
            </header>

            <SigningLinkPanel assessmentId={assessmentId} sheet={sheet} />

            <Table>
                <TableHeader className="bg-muted/60">
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="w-14 text-center">No.</TableHead>
                        <TableHead>Indikator Penilaian</TableHead>
                        <TableHead className="w-44 text-center">
                            Level (Nilai 1-5)
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sheet.aspects.map((aspect) => (
                        <TableRow key={aspect.aspect_id}>
                            <TableCell className="tabular text-center align-top font-semibold">
                                {aspect.sort_order}
                            </TableCell>
                            <TableCell className="align-top">
                                <p className="text-sm font-semibold italic">
                                    {aspect.name} :
                                </p>
                                {aspect.preamble && (
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {aspect.preamble}
                                    </p>
                                )}
                                <ol className="mt-1 ml-4 list-[lower-alpha] space-y-0.5 text-xs text-muted-foreground">
                                    {aspect.indicators.map((indicator) => (
                                        <li key={indicator}>{indicator}</li>
                                    ))}
                                </ol>
                                {aspect.scored_by && (
                                    <p className="mt-1 text-[11px] text-muted-foreground">
                                        Dinilai oleh {aspect.scored_by}
                                    </p>
                                )}
                            </TableCell>
                            <TableCell className="align-top">
                                <Select
                                    value={
                                        levels[aspect.aspect_id] == null
                                            ? UNSCORED
                                            : String(levels[aspect.aspect_id])
                                    }
                                    onValueChange={(value) =>
                                        setLevels((current) => ({
                                            ...current,
                                            [aspect.aspect_id]:
                                                value === UNSCORED
                                                    ? null
                                                    : Number(value),
                                        }))
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Belum dinilai" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={UNSCORED}>
                                            Belum dinilai
                                        </SelectItem>
                                        {LEVELS.map((value) => (
                                            <SelectItem
                                                key={value}
                                                value={String(value)}
                                            >
                                                {value}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </section>
    );
}

/**
 * The master sheet: every aspect averaged across the assessors who scored it.
 */
function RecapPanel({
    recap,
    printUrl,
}: {
    recap: {
        aspects: RecapAspect[];
        overall_average: number | null;
        scored: number;
        total: number;
    };
    printUrl: string;
}) {
    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-col gap-3 border-b border-border p-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="space-y-1">
                    <h2 className="text-sm font-semibold">
                        Rekapitulasi Hasil Penilaian
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        Nilai tiap aspek adalah rata-rata dari seluruh penilai
                        yang mengisinya. Aspek yang belum dinilai tidak
                        dihitung.
                    </p>
                </div>
                <Button asChild variant="outline">
                    <a href={printUrl}>
                        <Printer className="size-4" />
                        Cetak Rekapitulasi
                    </a>
                </Button>
            </header>

            <Table>
                <TableHeader className="bg-muted/60">
                    <TableRow className="hover:bg-transparent">
                        <TableHead>Aspek Penilaian</TableHead>
                        <TableHead>Penilai</TableHead>
                        <TableHead className="w-28 text-center">
                            Nilai
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {recap.aspects.map((aspect) => (
                        <TableRow key={aspect.aspect_id}>
                            <TableCell className="align-top font-medium">
                                {aspect.name}
                            </TableCell>
                            <TableCell className="align-top text-xs text-muted-foreground">
                                {aspect.contributors.length === 0 ? (
                                    <span className="text-amber-700 dark:text-amber-400">
                                        Belum ada yang menilai
                                    </span>
                                ) : (
                                    aspect.contributors
                                        .map(
                                            (row) =>
                                                `${row.form}: ${row.level}`,
                                        )
                                        .join(' · ')
                                )}
                                {aspect.pending > 0 &&
                                    aspect.contributors.length > 0 && (
                                        <span className="ml-1 text-amber-700 dark:text-amber-400">
                                            · {aspect.pending} penilai belum
                                            mengisi
                                        </span>
                                    )}
                            </TableCell>
                            <TableCell className="tabular text-center align-top font-semibold">
                                {level(aspect.average)}
                            </TableCell>
                        </TableRow>
                    ))}
                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                        <TableCell className="font-semibold" colSpan={2}>
                            Nilai Akhir
                        </TableCell>
                        <TableCell className="tabular text-center font-semibold">
                            {level(recap.overall_average)}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    );
}

ShowVendorAssessment.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penilaian Penyedia', href: vendorAssessments.index() },
    ],
};
