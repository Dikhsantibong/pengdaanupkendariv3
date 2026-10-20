import { Check, MessageSquarePlus, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * An approver's decision, in two steps so it is not made by a slip: Setujui
 * is the main button (a note may be added); Kembalikan untuk Revisi first
 * asks what has to be corrected, and only then sends it back.
 */
export function DecisionControls({
    id,
    note,
    onNoteChange,
    error,
    processing,
    onDecide,
    approveLabel = 'Setujui',
    returnLabel = 'Kembalikan untuk Revisi',
}: {
    /** Names the note field. */
    id: string;
    note: string;
    onNoteChange: (note: string) => void;
    error?: string;
    processing: boolean;
    onDecide: (approved: boolean) => void;
    approveLabel?: string;
    returnLabel?: string;
}) {
    // A refused return (no note) keeps the form open on the note.
    const [mode, setMode] = useState<'idle' | 'approve' | 'return'>(
        error ? 'return' : 'idle',
    );

    const cancel = () => {
        onNoteChange('');
        setMode('idle');
    };

    if (mode === 'return') {
        return (
            <div className="space-y-2 rounded-sm border border-destructive/30 bg-destructive/5 p-2.5">
                <Label htmlFor={id} className="text-xs">
                    Yang harus diperbaiki
                </Label>
                <Textarea
                    id={id}
                    rows={3}
                    autoFocus
                    value={note}
                    onChange={(event) => onNoteChange(event.target.value)}
                    placeholder="Tuliskan apa yang perlu diperbaiki pengusul"
                    className="bg-background"
                />
                <InputError message={error} />
                <div className="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="flex-1"
                        onClick={cancel}
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        className="flex-1"
                        disabled={processing || note.trim() === ''}
                        onClick={() => onDecide(false)}
                    >
                        <RotateCcw className="size-3.5" />
                        Kirim Pengembalian
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-2">
            {mode === 'approve' && (
                <div className="grid gap-1.5">
                    <Label htmlFor={id} className="text-xs">
                        Catatan persetujuan (opsional)
                    </Label>
                    <Textarea
                        id={id}
                        rows={2}
                        autoFocus
                        value={note}
                        onChange={(event) => onNoteChange(event.target.value)}
                    />
                    <InputError message={error} />
                </div>
            )}
            <Button
                type="button"
                size="sm"
                className="w-full"
                disabled={processing}
                onClick={() => onDecide(true)}
            >
                <Check className="size-3.5" />
                {approveLabel}
            </Button>
            <div className="flex items-center justify-between gap-2">
                {mode === 'approve' ? (
                    <button
                        type="button"
                        onClick={cancel}
                        className="text-xs text-muted-foreground underline-offset-2 hover:underline"
                    >
                        Tanpa catatan
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => setMode('approve')}
                        className="inline-flex items-center gap-1 text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                    >
                        <MessageSquarePlus className="size-3.5" />
                        Tambah catatan
                    </button>
                )}
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    disabled={processing}
                    onClick={() => {
                        onNoteChange('');
                        setMode('return');
                    }}
                >
                    <RotateCcw className="size-3.5" />
                    {returnLabel}
                </Button>
            </div>
        </div>
    );
}
