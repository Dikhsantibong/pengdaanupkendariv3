import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import procurements from '@/routes/procurements';
import type { Option, UserRef } from '@/types';

const NONE = 'none';

export function PicAssignmentCard({
    procurementId,
    planner,
    executor,
    planners,
    executors,
    editable,
}: {
    procurementId: number;
    planner: UserRef | null;
    executor: UserRef | null;
    planners: Option[];
    executors: Option[];
    editable: boolean;
}) {
    const form = useForm<{
        planner_id: number | null;
        executor_id: number | null;
    }>({
        planner_id: planner?.id ?? null,
        executor_id: executor?.id ?? null,
    });

    if (!editable) {
        return (
            <section className="space-y-3 rounded-md border border-border bg-card p-4">
                <p className="section-label">Penunjukan PIC</p>
                <dl className="space-y-2 text-sm">
                    <div className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">PIC Perencana</dt>
                        <dd className="text-right font-medium">
                            {planner?.name ?? 'Belum ditunjuk'}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-3">
                        <dt className="text-muted-foreground">PIC Pelaksana</dt>
                        <dd className="text-right font-medium">
                            {executor?.name ?? 'Belum ditunjuk'}
                        </dd>
                    </div>
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

            <div className="grid gap-2">
                <Label htmlFor="planner_id">PIC Perencana</Label>
                <Select
                    value={
                        form.data.planner_id === null
                            ? NONE
                            : String(form.data.planner_id)
                    }
                    onValueChange={(value) =>
                        form.setData(
                            'planner_id',
                            value === NONE ? null : Number(value),
                        )
                    }
                >
                    <SelectTrigger id="planner_id" className="w-full">
                        <SelectValue placeholder="Belum ditunjuk" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Belum ditunjuk</SelectItem>
                        {planners.map((option) => (
                            <SelectItem
                                key={option.value}
                                value={String(option.value)}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.planner_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="executor_id">PIC Pelaksana</Label>
                <Select
                    value={
                        form.data.executor_id === null
                            ? NONE
                            : String(form.data.executor_id)
                    }
                    onValueChange={(value) =>
                        form.setData(
                            'executor_id',
                            value === NONE ? null : Number(value),
                        )
                    }
                >
                    <SelectTrigger id="executor_id" className="w-full">
                        <SelectValue placeholder="Belum ditunjuk" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Belum ditunjuk</SelectItem>
                        {executors.map((option) => (
                            <SelectItem
                                key={option.value}
                                value={String(option.value)}
                            >
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.executor_id} />
            </div>

            <Button
                type="submit"
                size="sm"
                className="w-full"
                disabled={form.processing}
            >
                Simpan Penunjukan
            </Button>
        </form>
    );
}
