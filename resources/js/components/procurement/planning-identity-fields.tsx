import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import procurements from '@/routes/procurements';
import type { Option } from '@/types';

/** Sentinel for "not yet decided": a Select cannot carry an empty value. */
const UNSET = 'none';

/**
 * Save one identity field on its own.
 *
 * Each control posts only its own key, so the request never overwrites a field
 * somebody else is editing at the same time.
 */
function save(
    procurementId: number,
    payload: Record<string, string | number | null>,
) {
    router.put(
        procurements.planningIdentity.update(procurementId).url,
        payload,
        { preserveScroll: true },
    );
}

/**
 * Pick the kind of contract from the identity panel.
 */
export function ContractTypePicker({
    procurementId,
    value,
    options,
}: {
    procurementId: number;
    value: number | null;
    options: Option[];
}) {
    return (
        <Select
            value={value === null ? UNSET : String(value)}
            onValueChange={(next) =>
                save(procurementId, {
                    contract_type_id: next === UNSET ? null : Number(next),
                })
            }
        >
            <SelectTrigger
                className="ml-auto h-8 w-44 text-sm"
                aria-label="Jenis kontrak"
            >
                <SelectValue placeholder="Belum ditentukan" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={UNSET}>Belum ditentukan</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={String(option.value)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Type in the manager's memo number that hands the work to procurement.
 *
 * Saved on blur or Enter rather than on every keystroke, so a half-typed
 * number never reaches the server.
 */
export function ManagerMemoNumberField({
    procurementId,
    value,
}: {
    procurementId: number;
    value: string | null;
}) {
    const current = value ?? '';

    return (
        <Input
            // Uncontrolled, keyed on the server value: typing never re-renders
            // the field, and a value that changes server side remounts it with
            // the new text. No effect is needed to keep the two in step.
            key={current}
            defaultValue={current}
            onBlur={(event) => {
                const next = event.target.value.trim();

                if (next === current) {
                    return;
                }

                save(procurementId, {
                    manager_memo_number: next === '' ? null : next,
                });
            }}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.currentTarget.blur();
                }

                if (event.key === 'Escape') {
                    event.currentTarget.value = current;
                    event.currentTarget.blur();
                }
            }}
            placeholder="Belum ada"
            autoComplete="off"
            aria-label="Nomor nota dinas manager ke pengadaan"
            className="ml-auto h-8 w-56 text-right text-sm"
        />
    );
}
