import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ALL = 'all';

/** The months, January first. */
export const MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

/**
 * The year and month filter every list of data offers. Picking a month
 * without a year keeps that month of every year.
 */
export function PeriodFilter({
    years,
    year,
    month,
    onChange,
}: {
    years: number[];
    year: number | null | undefined;
    month: number | null | undefined;
    onChange: (next: { year?: number | null; month?: number | null }) => void;
}) {
    return (
        <>
            <div className="w-full space-y-1.5 sm:w-32">
                <Label className="section-label">Tahun</Label>
                <Select
                    value={year ? String(year) : ALL}
                    onValueChange={(next) =>
                        onChange({ year: next === ALL ? null : Number(next) })
                    }
                >
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Semua" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Semua</SelectItem>
                        {years.map((option) => (
                            <SelectItem key={option} value={String(option)}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="w-full space-y-1.5 sm:w-36">
                <Label className="section-label">Bulan</Label>
                <Select
                    value={month ? String(month) : ALL}
                    onValueChange={(next) =>
                        onChange({ month: next === ALL ? null : Number(next) })
                    }
                >
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Semua" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Semua</SelectItem>
                        {MONTHS.map((name, index) => (
                            <SelectItem key={name} value={String(index + 1)}>
                                {name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </>
    );
}
