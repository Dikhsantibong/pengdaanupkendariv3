import { useForm } from '@inertiajs/react';
import { CheckCircle2, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import procurements from '@/routes/procurements';
import type { ChecklistInput } from '@/types';

/**
 * The last day of an execution period, counting the start date as day one,
 * so 30 days from 1 October ends on 30 October. Mirrors the server.
 */
function endDate(start: string, days: string): string | null {
    const count = Number(days);

    if (start === '' || !Number.isInteger(count) || count < 1) {
        return null;
    }

    const date = new Date(`${start}T00:00:00`);
    date.setDate(date.getDate() + count - 1);

    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/**
 * The data a checklist step asks for before it can be ticked.
 */
export function ChecklistInputForm({
    procurementId,
    checklistId,
    input,
    canManage,
}: {
    procurementId: number;
    checklistId: number;
    input: ChecklistInput;
    canManage: boolean;
}) {
    const { values } = input;

    const form = useForm({
        execution_start_date: values.execution_start_date ?? '',
        execution_duration_days:
            values.execution_duration_days === null
                ? ''
                : String(values.execution_duration_days),
        warranty_months:
            values.warranty_months === null
                ? ''
                : String(values.warranty_months),
        bank_account_number: values.bank_account_number ?? '',
        bank_name: values.bank_name ?? '',
        bank_account_holder: values.bank_account_holder ?? '',
    });

    const save = () =>
        form.put(
            procurements.checklists.input({
                procurement: procurementId,
                checklist: checklistId,
            }).url,
            { preserveScroll: true },
        );

    const end = endDate(
        form.data.execution_start_date,
        form.data.execution_duration_days,
    );

    return (
        <div className="space-y-2 rounded-sm border border-border bg-muted/30 p-2.5">
            <div className="flex items-center gap-1.5 text-xs font-medium">
                {input.is_filled && (
                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                )}
                Isian: {input.label}
            </div>

            <fieldset
                disabled={!canManage || form.processing}
                className="grid gap-2 sm:grid-cols-3"
            >
                {input.kind === 'contract_period' && (
                    <>
                        <Field
                            id={`start-${checklistId}`}
                            label="Tanggal Mulai"
                            error={form.errors.execution_start_date}
                        >
                            <Input
                                id={`start-${checklistId}`}
                                type="date"
                                className="tabular h-8"
                                value={form.data.execution_start_date}
                                onChange={(event) =>
                                    form.setData(
                                        'execution_start_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id={`days-${checklistId}`}
                            label="Jumlah Hari"
                            error={form.errors.execution_duration_days}
                        >
                            <Input
                                id={`days-${checklistId}`}
                                type="number"
                                min={1}
                                className="tabular h-8"
                                value={form.data.execution_duration_days}
                                onChange={(event) =>
                                    form.setData(
                                        'execution_duration_days',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field id={`end-${checklistId}`} label="Tanggal Akhir">
                            <div
                                id={`end-${checklistId}`}
                                className="tabular flex h-8 items-center rounded-md border border-input bg-muted px-3 text-sm"
                            >
                                {end === null ? '—' : formatDate(end)}
                            </div>
                            <p className="text-[11px] text-muted-foreground">
                                Otomatis; tanggal mulai dihitung hari ke-1.
                            </p>
                        </Field>
                    </>
                )}

                {input.kind === 'warranty' && (
                    <Field
                        id={`warranty-${checklistId}`}
                        label="Masa Garansi (bulan)"
                        error={form.errors.warranty_months}
                    >
                        <Input
                            id={`warranty-${checklistId}`}
                            type="number"
                            min={0}
                            className="tabular h-8"
                            value={form.data.warranty_months}
                            onChange={(event) =>
                                form.setData(
                                    'warranty_months',
                                    event.target.value,
                                )
                            }
                            placeholder="Contoh: 3"
                        />
                    </Field>
                )}

                {input.kind === 'bank_account' && (
                    <>
                        <Field
                            id={`account-${checklistId}`}
                            label="Nomor Rekening"
                            error={form.errors.bank_account_number}
                        >
                            <Input
                                id={`account-${checklistId}`}
                                inputMode="numeric"
                                className="tabular h-8"
                                value={form.data.bank_account_number}
                                onChange={(event) =>
                                    form.setData(
                                        'bank_account_number',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            id={`bank-${checklistId}`}
                            label="Bank"
                            error={form.errors.bank_name}
                        >
                            <Input
                                id={`bank-${checklistId}`}
                                className="h-8"
                                value={form.data.bank_name}
                                onChange={(event) =>
                                    form.setData(
                                        'bank_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Contoh: BRI"
                            />
                        </Field>
                        <Field
                            id={`holder-${checklistId}`}
                            label="Nama Pelaksana (Pemilik Rekening)"
                            error={form.errors.bank_account_holder}
                        >
                            <Input
                                id={`holder-${checklistId}`}
                                className="h-8"
                                value={form.data.bank_account_holder}
                                onChange={(event) =>
                                    form.setData(
                                        'bank_account_holder',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </>
                )}
            </fieldset>

            {canManage && (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={save}
                    disabled={form.processing || !form.isDirty}
                >
                    <Save className="size-3.5" />
                    Simpan Isian
                </Button>
            )}
        </div>
    );
}

function Field({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <Label htmlFor={id} className="text-[11px]">
                {label}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
