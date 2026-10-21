import { router } from '@inertiajs/react';
import { Link2, MessageCircle, Pencil, Send } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import vendorAssessments from '@/routes/vendor-assessments';

/** The signing link of one sheet, as the assessment page carries it. */
type SheetLink = {
    url: string;
    whatsapp_url: string | null;
    recipient_name: string | null;
    recipient_phone: string | null;
    opened_at: string | null;
    submitted_at: string | null;
    revoked_at: string | null;
    is_open: boolean;
};

export type SendableSheet = {
    id: number;
    code: string;
    name: string;
    aspects: { level: number | null }[];
    invitation: SheetLink | null;
};

type Status = { label: string; tone: string };

const TONES = {
    done: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    opened: 'bg-sky-500/10 text-sky-700 dark:text-sky-400',
    sent: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
    ready: 'bg-primary/10 text-primary',
    missing: 'bg-muted text-muted-foreground',
    closed: 'bg-red-500/10 text-red-700 dark:text-red-400',
};

/** The links already handed to WhatsApp from this browser, per assessment. */
function sentKey(assessmentId: number): string {
    return `vendor-assessment-sent:${assessmentId}`;
}

function readSent(assessmentId: number): string[] {
    try {
        const stored = JSON.parse(
            window.localStorage.getItem(sentKey(assessmentId)) ?? '[]',
        );

        return Array.isArray(stored) ? stored.map(String) : [];
    } catch {
        return [];
    }
}

/** Every aspect of the sheet has a level, however it was filled in. */
function isScored(sheet: SendableSheet): boolean {
    return (
        sheet.aspects.length > 0 &&
        sheet.aspects.every((aspect) => aspect.level !== null)
    );
}

/** A sheet finished: signed through its link, or scored on the page. */
function isFinished(sheet: SendableSheet): boolean {
    return Boolean(sheet.invitation?.submitted_at) || isScored(sheet);
}

/**
 * A link that can still be sent: open, unsigned, with a number to send to,
 * on a sheet not yet scored.
 */
function isSendable(sheet: SendableSheet): boolean {
    const invitation = sheet.invitation;

    return (
        !isScored(sheet) &&
        invitation !== null &&
        invitation.is_open &&
        invitation.submitted_at === null &&
        invitation.whatsapp_url !== null
    );
}

function statusOf(sheet: SendableSheet, sent: boolean): Status {
    const invitation = sheet.invitation;

    if (invitation?.submitted_at) {
        return { label: 'Sudah ditandatangani', tone: TONES.done };
    }

    if (isScored(sheet)) {
        return { label: 'Sudah dinilai', tone: TONES.done };
    }

    if (invitation === null) {
        return { label: 'Belum ada tautan', tone: TONES.missing };
    }

    if (invitation.revoked_at !== null) {
        return { label: 'Tautan dibatalkan', tone: TONES.closed };
    }

    if (!invitation.is_open) {
        return { label: 'Tautan kedaluwarsa', tone: TONES.closed };
    }

    if (invitation.opened_at !== null) {
        return { label: 'Sudah dibuka penilai', tone: TONES.opened };
    }

    if (invitation.whatsapp_url === null) {
        return {
            label: invitation.recipient_name
                ? 'Nomor WhatsApp belum ada'
                : 'Penerima belum dipilih',
            tone: TONES.sent,
        };
    }

    return sent
        ? { label: 'Terkirim, belum dibuka', tone: TONES.sent }
        : { label: 'Siap dikirim', tone: TONES.ready };
}

/**
 * Every sheet's assessor in one list: who it goes to, where it stands, and
 * one click to open WhatsApp with the message ready. "Kirim berikutnya"
 * walks through the ones not yet sent from this browser.
 */
export function AssessmentSendPanel({
    assessmentId,
    sheets,
    onEdit,
}: {
    assessmentId: number;
    sheets: SendableSheet[];
    /** Open the sheet's own tab, where the recipient can be changed. */
    onEdit: (code: string) => void;
}) {
    const [sent, setSent] = useState<string[]>(() => readSent(assessmentId));
    const [preparing, setPreparing] = useState(false);

    const markSent = (url: string) => {
        const next = sent.includes(url) ? sent : [...sent, url];
        setSent(next);

        try {
            window.localStorage.setItem(
                sentKey(assessmentId),
                JSON.stringify(next),
            );
        } catch {
            // Only a reminder for this browser; the list works without it.
        }
    };

    const finished = sheets.filter(isFinished).length;
    const withoutLink = sheets.filter(
        (sheet) => sheet.invitation === null && !isScored(sheet),
    ).length;
    const nextToSend = sheets.find(
        (sheet) =>
            isSendable(sheet) &&
            sheet.invitation !== null &&
            sheet.invitation.opened_at === null &&
            !sent.includes(sheet.invitation.url),
    );

    const prepare = () =>
        router.post(
            vendorAssessments.links.storeAll(assessmentId).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setPreparing(true),
                onFinish: () => setPreparing(false),
            },
        );

    return (
        <section className="rounded-md border border-border bg-card">
            <header className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-0.5">
                    <p className="section-label">Kirim ke Penilai</p>
                    <p className="text-xs text-muted-foreground">
                        {finished}/{sheets.length} lembar selesai dinilai. Klik
                        Kirim WA untuk membuka WhatsApp dengan pesan siap kirim.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    {withoutLink > 0 && (
                        <Button
                            variant="outline"
                            onClick={prepare}
                            disabled={preparing}
                        >
                            <Link2 className="size-4" />
                            Siapkan Tautan ({withoutLink})
                        </Button>
                    )}
                    {nextToSend?.invitation?.whatsapp_url ? (
                        <Button
                            asChild
                            className="bg-green-600 text-white hover:bg-green-700"
                        >
                            <a
                                href={nextToSend.invitation.whatsapp_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={() =>
                                    markSent(nextToSend.invitation!.url)
                                }
                            >
                                <Send className="size-4" />
                                Kirim berikutnya: {nextToSend.name}
                            </a>
                        </Button>
                    ) : (
                        <Button disabled className="bg-green-600/50 text-white">
                            <Send className="size-4" />
                            {finished === sheets.length
                                ? 'Semua lembar selesai'
                                : 'Tidak ada yang siap dikirim'}
                        </Button>
                    )}
                </div>
            </header>

            <ul className="divide-y divide-border">
                {sheets.map((sheet) => {
                    const invitation = sheet.invitation;
                    const wasSent =
                        invitation !== null && sent.includes(invitation.url);
                    const status = statusOf(sheet, wasSent);

                    return (
                        <li
                            key={sheet.id}
                            className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-4 py-3 sm:grid-cols-[10rem_minmax(0,1fr)_11rem_auto]"
                        >
                            <p className="text-sm font-medium">{sheet.name}</p>
                            <p className="col-start-1 min-w-0 truncate text-xs text-muted-foreground sm:col-start-auto sm:text-sm">
                                {invitation?.recipient_name ?? '—'}
                                {invitation?.recipient_phone
                                    ? ` · ${invitation.recipient_phone}`
                                    : ''}
                            </p>
                            <span
                                className={cn(
                                    'col-start-1 w-fit rounded-full px-2 py-0.5 text-[11px] font-medium sm:col-start-auto',
                                    status.tone,
                                )}
                            >
                                {status.label}
                            </span>
                            <div className="col-start-2 row-span-3 row-start-1 flex items-center justify-end gap-1 sm:col-start-auto sm:row-span-1 sm:row-start-auto">
                                {isSendable(sheet) &&
                                    invitation?.whatsapp_url && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant={
                                                wasSent || invitation.opened_at
                                                    ? 'outline'
                                                    : 'default'
                                            }
                                        >
                                            <a
                                                href={invitation.whatsapp_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                onClick={() =>
                                                    markSent(invitation.url)
                                                }
                                            >
                                                <MessageCircle className="size-3.5" />
                                                {wasSent || invitation.opened_at
                                                    ? 'Kirim Ulang'
                                                    : 'Kirim WA'}
                                            </a>
                                        </Button>
                                    )}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => onEdit(sheet.code)}
                                    aria-label={`Ubah penerima ${sheet.name}`}
                                    title="Ubah penerima / buka lembar"
                                >
                                    <Pencil className="size-3.5" />
                                </Button>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
