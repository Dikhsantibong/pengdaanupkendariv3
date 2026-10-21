import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';

/** One name of the Direksi Pekerjaan sheet, without its phone number. */
export type WorkDirector = {
    name: string;
    /** The last BASTP date the name stays hidden on, for one who took office later. */
    hidden_until: string | null;
};

/** Sentinel for "none chosen": a Select cannot carry an empty value. */
const NONE = '__none__';

/** The names offered on work handed over on the given date. */
export function offeredWorkDirectors(
    names: WorkDirector[],
    bastpDate: string | null | undefined,
): WorkDirector[] {
    return names.filter(
        (director) =>
            !bastpDate ||
            director.hidden_until === null ||
            bastpDate > director.hidden_until,
    );
}

/**
 * The assessment's Direksi Pekerjaan, picked by name from the Direksi
 * Pekerjaan sheet; a name not yet in office by the BASTP date is left out.
 * Without a list on that sheet the name is typed.
 */
export function WorkDirectorSelect({
    names,
    bastpDate,
    value,
    onChange,
    error,
}: {
    names: WorkDirector[];
    bastpDate: string | null | undefined;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const offered = offeredWorkDirectors(names, bastpDate);
    const isOffered = offered.some(
        (director) => director.name.toUpperCase() === value.toUpperCase(),
    );
    const hiddenCount = names.length - offered.length;

    return (
        <div className="grid gap-2">
            <Label htmlFor="work_director_name">Direksi Pekerjaan</Label>
            {names.length === 0 ? (
                <Input
                    id="work_director_name"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder="Nama direksi pekerjaan"
                    autoComplete="off"
                />
            ) : (
                <Select
                    value={value === '' ? NONE : value}
                    onValueChange={(next) =>
                        onChange(next === NONE ? '' : next)
                    }
                >
                    <SelectTrigger id="work_director_name" className="w-full">
                        <SelectValue placeholder="Pilih direksi pekerjaan" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>Belum dipilih</SelectItem>
                        {offered.map((director) => (
                            <SelectItem
                                key={director.name}
                                value={director.name}
                            >
                                {director.name}
                            </SelectItem>
                        ))}
                        {/* Kept visible so a name no longer offered can be seen and changed. */}
                        {value !== '' && !isOffered && (
                            <SelectItem value={value}>{value}</SelectItem>
                        )}
                    </SelectContent>
                </Select>
            )}
            {value !== '' && names.length > 0 && !isOffered ? (
                <p className="text-xs text-amber-700 dark:text-amber-400">
                    {value} belum menjabat pada Tgl BASTP{' '}
                    {bastpDate ? formatDate(bastpDate) : ''}. Pilih nama lain.
                </p>
            ) : (
                hiddenCount > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {hiddenCount} nama tidak ditampilkan karena belum
                        menjabat pada Tgl BASTP ini.
                    </p>
                )
            )}
            <InputError message={error} />
        </div>
    );
}
