import { useForm } from '@inertiajs/react';
import { Check, Pencil, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { closeRememberedPanels } from '@/components/procurement/request-board';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import procurements from '@/routes/procurements';

/** Where the identity's contract number field sits, for steps to point at. */
export const CONTRACT_NUMBER_FIELD_ID = 'identity-contract-number';

/**
 * Bring the identity's contract number field into view and focus it, from
 * a checklist step that asks for the number.
 */
export function focusContractNumber(): void {
    closeRememberedPanels();
    window.dispatchEvent(new CustomEvent('contract-number:edit'));
}

/**
 * The contract number in the identity of a procurement. The execution PIC
 * and TL Pengadaan type it here; everyone else reads it.
 */
export function ContractNumberField({
    procurementId,
    value,
    suggested,
    editable,
}: {
    procurementId: number;
    /** The number once given. */
    value: string | null;
    /** The running number the contract format offers. */
    suggested: string | null;
    editable: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ number: value ?? '' });

    // Asked for by a checklist step: open the field and bring it up.
    useEffect(() => {
        if (!editable) {
            return;
        }

        const open = () => {
            setEditing(true);
            window.setTimeout(() => {
                const field = document.getElementById(CONTRACT_NUMBER_FIELD_ID);
                field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field?.focus();
            }, 50);
        };

        window.addEventListener('contract-number:edit', open);

        return () => window.removeEventListener('contract-number:edit', open);
    }, [editable]);

    if (!editable) {
        return <span>{value ?? '—'}</span>;
    }

    if (!editing && value !== null) {
        return (
            <span className="flex min-w-0 items-center gap-1.5">
                <span className="truncate" title={value}>
                    {value}
                </span>
                <button
                    type="button"
                    onClick={() => {
                        form.setData('number', value);
                        setEditing(true);
                    }}
                    className="shrink-0 rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Ubah nomor kontrak"
                    title="Ubah nomor kontrak"
                >
                    <Pencil className="size-3.5" />
                </button>
            </span>
        );
    }

    const save = () =>
        form.put(procurements.contractNumber.update(procurementId).url, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });

    return (
        <span className="grid max-w-md gap-1">
            <span className="flex items-center gap-1">
                <Input
                    id={CONTRACT_NUMBER_FIELD_ID}
                    value={form.data.number}
                    onChange={(event) =>
                        form.setData('number', event.target.value)
                    }
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            save();
                        }

                        if (event.key === 'Escape' && value !== null) {
                            form.reset();
                            setEditing(false);
                        }
                    }}
                    placeholder={suggested ?? 'KDD001.SPK/612/UPKD/2026'}
                    aria-label="Nomor kontrak"
                    autoComplete="off"
                    className="tabular h-8 min-w-0 text-sm"
                />
                <Button
                    type="button"
                    size="icon"
                    className="size-8 shrink-0"
                    onClick={save}
                    disabled={form.processing || form.data.number.trim() === ''}
                    aria-label="Simpan nomor kontrak"
                    title="Simpan"
                >
                    <Check className="size-4" />
                </Button>
                {value !== null && (
                    <Button
                        type="button"
                        size="icon"
                        variant="ghost"
                        className="size-8 shrink-0"
                        onClick={() => {
                            form.reset();
                            form.clearErrors();
                            setEditing(false);
                        }}
                        aria-label="Batal"
                        title="Batal"
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </span>
            {suggested !== null && form.data.number !== suggested && (
                <button
                    type="button"
                    className="justify-self-start text-[11px] font-normal text-muted-foreground underline-offset-2 hover:underline"
                    onClick={() => form.setData('number', suggested)}
                >
                    Pakai nomor otomatis ({suggested})
                </button>
            )}
            <InputError message={form.errors.number} />
        </span>
    );
}
