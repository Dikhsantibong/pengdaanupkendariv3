import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { FilterOptions, Option, ProcurementFilters } from '@/types';

const ALL = 'all';

type ExtraSelect = {
    name: string;
    label: string;
    options: Option[];
    value: number | null;
};

export function ProcurementFilterBar({
    url,
    filters,
    options,
    extraSelects = [],
}: {
    url: string;
    filters: ProcurementFilters;
    options: FilterOptions;
    extraSelects?: ExtraSelect[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    const visit = (overrides: Record<string, string | number | null>) => {
        const query: Record<string, string> = {};
        const merged = {
            search: filters.search,
            progress_status_id: filters.progress_status_id,
            work_director_id: filters.work_director_id,
            target_unit_id: filters.target_unit_id,
            procurement_method_id: filters.procurement_method_id,
            budget_source_id: filters.budget_source_id,
            planner_id: filters.planner_id,
            executor_id: filters.executor_id,
            ...Object.fromEntries(
                extraSelects.map((select) => [select.name, select.value]),
            ),
            ...overrides,
        };

        Object.entries(merged).forEach(([key, value]) => {
            if (value !== null && value !== '') {
                query[key] = String(value);
            }
        });

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            visit({ search: search || null });
        }, 350);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasActiveFilters =
        Boolean(filters.search) ||
        Boolean(filters.progress_status_id) ||
        Boolean(filters.work_director_id) ||
        Boolean(filters.target_unit_id) ||
        Boolean(filters.procurement_method_id) ||
        Boolean(filters.budget_source_id) ||
        extraSelects.some((select) => Boolean(select.value));

    return (
        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
            <div className="min-w-64 flex-1 space-y-1.5">
                <Label htmlFor="procurement-search" className="section-label">
                    Cari
                </Label>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        id="procurement-search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Nomor pengadaan, nama pekerjaan, atau nomor PRK"
                        className="pl-9"
                    />
                </div>
            </div>

            <FilterSelect
                label="Status Progres"
                value={filters.progress_status_id}
                options={options.progressStatuses}
                onChange={(value) => visit({ progress_status_id: value })}
            />

            <FilterSelect
                label="Direksi Pekerjaan"
                value={filters.work_director_id}
                options={options.workDirectors}
                onChange={(value) => visit({ work_director_id: value })}
            />

            <FilterSelect
                label="Unit Tujuan"
                value={filters.target_unit_id}
                options={options.targetUnits}
                onChange={(value) => visit({ target_unit_id: value })}
            />

            <FilterSelect
                label="Metode Pengadaan"
                value={filters.procurement_method_id}
                options={options.procurementMethods}
                onChange={(value) => visit({ procurement_method_id: value })}
            />

            <FilterSelect
                label="Sumber Anggaran"
                value={filters.budget_source_id}
                options={options.budgetSources}
                onChange={(value) => visit({ budget_source_id: value })}
            />

            {extraSelects.map((select) => (
                <FilterSelect
                    key={select.name}
                    label={select.label}
                    value={select.value}
                    options={select.options}
                    onChange={(value) => visit({ [select.name]: value })}
                />
            ))}

            {hasActiveFilters && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setSearch('');
                        router.get(
                            url,
                            {},
                            { preserveScroll: true, replace: true },
                        );
                    }}
                >
                    <X className="size-4" />
                    Reset
                </Button>
            )}
        </div>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: number | null;
    options: Option[];
    onChange: (value: number | null) => void;
}) {
    return (
        <div className="w-full space-y-1.5 sm:w-48">
            <Label className="section-label">{label}</Label>
            <Select
                value={value === null ? ALL : String(value)}
                onValueChange={(next) =>
                    onChange(next === ALL ? null : Number(next))
                }
            >
                <SelectTrigger className="w-full">
                    <SelectValue placeholder="Semua" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Semua</SelectItem>
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={String(option.value)}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
