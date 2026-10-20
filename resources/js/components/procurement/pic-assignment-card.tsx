import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { PicSelect } from '@/components/procurement/pic-select';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import procurements from '@/routes/procurements';
import type { PicOption, UserRef } from '@/types';

/** A PIC shown without a picker. */
function PicValue({
    label,
    person,
    hint,
}: {
    label: string;
    person: UserRef | null;
    hint?: string;
}) {
    return (
        <div className="flex justify-between gap-3 text-sm">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="text-right font-medium">
                {person?.name ?? 'Belum ditunjuk'}
                {hint && person === null && (
                    <span className="block text-xs font-normal text-muted-foreground">
                        {hint}
                    </span>
                )}
            </dd>
        </div>
    );
}

/**
 * The two PICs of a procurement. TL ICC appoints the PIC Perencana once the
 * Asman approved the proposal; TL Pengadaan the PIC Pelaksana once ICC
 * approved the planning. The card reads as names; whoever may change a PIC
 * opens the pickers with Ubah, and an appointment still to be made opens
 * them at once.
 */
export function PicAssignmentCard({
    procurementId,
    planner,
    executor,
    planners,
    executors,
    canAssignPlanner,
    canAssignExecutor,
}: {
    procurementId: number;
    planner: UserRef | null;
    executor: UserRef | null;
    planners: PicOption[];
    executors: PicOption[];
    canAssignPlanner: boolean;
    canAssignExecutor: boolean;
}) {
    const form = useForm<{
        planner_id: number | null;
        executor_id: number | null;
    }>({
        planner_id: planner?.id ?? null,
        executor_id: executor?.id ?? null,
    });

    const executorHint = 'Ditunjuk TL Pengadaan setelah perencanaan disetujui';
    const canAssign = canAssignPlanner || canAssignExecutor;
    const [editing, setEditing] = useState(
        (canAssignPlanner && planner === null) ||
            (canAssignExecutor && executor === null),
    );

    if (!canAssign || !editing) {
        return (
            <section className="space-y-3 rounded-md border border-border bg-card p-4">
                <div className="flex items-center justify-between gap-2">
                    <p className="section-label">Penunjukan PIC</p>
                    {canAssign && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="h-7 px-2 text-xs"
                            onClick={() => setEditing(true)}
                        >
                            <Pencil className="size-3.5" />
                            Ubah
                        </Button>
                    )}
                </div>
                <dl className="space-y-2">
                    <PicValue
                        label="PIC Perencana"
                        person={planner}
                        hint="Ditunjuk TL ICC"
                    />
                    <PicValue
                        label="PIC Pelaksana"
                        person={executor}
                        hint={executorHint}
                    />
                </dl>
            </section>
        );
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(procurements.pic.update(procurementId).url, {
                    preserveScroll: true,
                });
            }}
            className="space-y-4 rounded-md border border-border bg-card p-4"
        >
            <p className="section-label">Penunjukan PIC</p>

            {canAssignPlanner ? (
                <div className="grid gap-2">
                    <Label htmlFor="planner_id">PIC Perencana</Label>
                    <PicSelect
                        id="planner_id"
                        value={form.data.planner_id}
                        options={planners}
                        role="planner"
                        onChange={(value) => form.setData('planner_id', value)}
                    />
                    <InputError message={form.errors.planner_id} />
                </div>
            ) : (
                <dl>
                    <PicValue
                        label="PIC Perencana"
                        person={planner}
                        hint="Ditunjuk TL ICC"
                    />
                </dl>
            )}

            {canAssignExecutor ? (
                <div className="grid gap-2">
                    <Label htmlFor="executor_id">PIC Pelaksana</Label>
                    <PicSelect
                        id="executor_id"
                        value={form.data.executor_id}
                        options={executors}
                        role="executor"
                        onChange={(value) => form.setData('executor_id', value)}
                    />
                    <InputError message={form.errors.executor_id} />
                </div>
            ) : (
                <dl>
                    <PicValue
                        label="PIC Pelaksana"
                        person={executor}
                        hint={executorHint}
                    />
                </dl>
            )}

            <div className="flex gap-2">
                {(planner !== null || !canAssignPlanner) &&
                    (executor !== null || !canAssignExecutor) && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="flex-1"
                            onClick={() => {
                                form.reset();
                                setEditing(false);
                            }}
                        >
                            Batal
                        </Button>
                    )}
                <Button
                    type="submit"
                    size="sm"
                    className="flex-1"
                    disabled={form.processing || !form.isDirty}
                >
                    Simpan Penunjukan
                </Button>
            </div>
        </form>
    );
}
