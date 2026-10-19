import { router } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import procurements from '@/routes/procurements';

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
 * Set the date the job should be finished by.
 *
 * It drives the target column, the S-curve and the overdue warnings, so it
 * is saved as soon as a date is picked.
 */
export function TargetDateField({
    procurementId,
    value,
}: {
    procurementId: number;
    value: string | null;
}) {
    const current = value ?? '';

    return (
        <Input
            key={current}
            type="date"
            defaultValue={current}
            onChange={(event) => {
                const next = event.target.value;

                if (next === current) {
                    return;
                }

                save(procurementId, {
                    target_completion_date: next === '' ? null : next,
                });
            }}
            aria-label="Target selesai"
            className="tabular ml-auto h-8 w-44 text-sm"
        />
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
