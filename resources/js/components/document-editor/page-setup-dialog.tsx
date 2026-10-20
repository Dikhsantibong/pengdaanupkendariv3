import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { MARGIN_PRESETS, PAPER_SIZES, paperDimensions } from '@/lib/page-setup';
import type { PageMargins, PageSetup, PaperSize } from '@/lib/page-setup';

const SIDES: { key: keyof PageMargins; label: string }[] = [
    { key: 'top', label: 'Atas' },
    { key: 'bottom', label: 'Bawah' },
    { key: 'left', label: 'Kiri' },
    { key: 'right', label: 'Kanan' },
];

const CUSTOM = 'custom';

/**
 * The page setup, as Word's: the paper, its orientation and the four
 * margins, with a sketch of the sheet. Applying it reflows the document and
 * the printed PDF alike.
 */
export function PageSetupDialog({
    open,
    onOpenChange,
    setup,
    isCustom,
    onApply,
    onReset,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The setup the document prints with now. */
    setup: PageSetup;
    /** Whether that setup was chosen in an editor (rather than the template's). */
    isCustom: boolean;
    onApply: (setup: PageSetup) => void;
    /** Back to the setup of the template. */
    onReset: () => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                {open && (
                    <PageSetupForm
                        setup={setup}
                        isCustom={isCustom}
                        onCancel={() => onOpenChange(false)}
                        onApply={(next) => {
                            onApply(next);
                            onOpenChange(false);
                        }}
                        onReset={() => {
                            onReset();
                            onOpenChange(false);
                        }}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function PageSetupForm({
    setup,
    isCustom,
    onCancel,
    onApply,
    onReset,
}: {
    setup: PageSetup;
    isCustom: boolean;
    onCancel: () => void;
    onApply: (setup: PageSetup) => void;
    onReset: () => void;
}) {
    const [paper, setPaper] = useState<PaperSize>(setup.paper);
    const [landscape, setLandscape] = useState(setup.landscape);
    const [margins, setMargins] = useState<Record<keyof PageMargins, string>>(
        () => ({
            top: String(setup.margins.top),
            right: String(setup.margins.right),
            bottom: String(setup.margins.bottom),
            left: String(setup.margins.left),
        }),
    );

    const numbers: PageMargins = {
        top: Number(margins.top.replace(',', '.')),
        right: Number(margins.right.replace(',', '.')),
        bottom: Number(margins.bottom.replace(',', '.')),
        left: Number(margins.left.replace(',', '.')),
    };
    const draft: PageSetup = { paper, landscape, margins: numbers };
    const { width, height } = paperDimensions(draft);

    const errors: Partial<Record<keyof PageMargins, string>> = {};

    SIDES.forEach(({ key }) => {
        const value = numbers[key];

        if (!Number.isFinite(value) || value < 0) {
            errors[key] = 'Isi angka 0 atau lebih.';
        }
    });

    if (
        !errors.left &&
        !errors.right &&
        numbers.left + numbers.right > width - 50
    ) {
        errors.right = 'Margin kiri + kanan terlalu lebar untuk kertas ini.';
    }

    if (
        !errors.top &&
        !errors.bottom &&
        numbers.top + numbers.bottom > height - 50
    ) {
        errors.bottom = 'Margin atas + bawah terlalu tinggi untuk kertas ini.';
    }

    const valid = Object.keys(errors).length === 0;

    const preset =
        MARGIN_PRESETS.find(({ margins: candidate }) =>
            SIDES.every(
                ({ key }) => Math.abs(candidate[key] - numbers[key]) < 0.05,
            ),
        )?.label ?? CUSTOM;

    // The sketch: the sheet at a fixed height, its margins drawn to scale.
    const sketchHeight = 168;
    const scale = sketchHeight / Math.max(width, height);
    const sketch = {
        width: width * scale,
        height: height * scale,
    };

    return (
        <>
            <DialogHeader>
                <DialogTitle>Pengaturan Halaman</DialogTitle>
                <DialogDescription>
                    Ukuran kertas, orientasi, dan margin dipakai di editor
                    maupun PDF dokumen ini.
                </DialogDescription>
            </DialogHeader>

            <div className="grid gap-5 sm:grid-cols-[1fr_auto]">
                <div className="grid content-start gap-4">
                    <div className="grid gap-1.5">
                        <Label htmlFor="page-paper">Ukuran kertas</Label>
                        <Select
                            value={paper}
                            onValueChange={(next) =>
                                setPaper(next as PaperSize)
                            }
                        >
                            <SelectTrigger id="page-paper" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(Object.keys(PAPER_SIZES) as PaperSize[]).map(
                                    (size) => (
                                        <SelectItem key={size} value={size}>
                                            {PAPER_SIZES[size].label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-1.5">
                        <Label>Orientasi</Label>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={landscape ? 'landscape' : 'portrait'}
                            onValueChange={(next) => {
                                if (next !== '') {
                                    setLandscape(next === 'landscape');
                                }
                            }}
                            className="w-full"
                        >
                            <ToggleGroupItem
                                value="portrait"
                                className="flex-1"
                            >
                                Potret
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="landscape"
                                className="flex-1"
                            >
                                Lanskap
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    <div className="grid gap-1.5">
                        <Label htmlFor="page-margin-preset">Margin</Label>
                        <Select
                            value={preset}
                            onValueChange={(next) => {
                                const chosen = MARGIN_PRESETS.find(
                                    ({ label }) => label === next,
                                );

                                if (chosen) {
                                    setMargins({
                                        top: String(chosen.margins.top),
                                        right: String(chosen.margins.right),
                                        bottom: String(chosen.margins.bottom),
                                        left: String(chosen.margins.left),
                                    });
                                }
                            }}
                        >
                            <SelectTrigger
                                id="page-margin-preset"
                                className="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MARGIN_PRESETS.map(({ label }) => (
                                    <SelectItem key={label} value={label}>
                                        {label}
                                    </SelectItem>
                                ))}
                                <SelectItem value={CUSTOM} disabled>
                                    Kustom
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div className="mt-1 grid grid-cols-2 gap-3">
                            {SIDES.map(({ key, label }) => (
                                <div key={key} className="grid gap-1">
                                    <Label
                                        htmlFor={`page-margin-${key}`}
                                        className="text-xs text-muted-foreground"
                                    >
                                        {label} (mm)
                                    </Label>
                                    <Input
                                        id={`page-margin-${key}`}
                                        inputMode="decimal"
                                        value={margins[key]}
                                        onChange={(event) =>
                                            setMargins((current) => ({
                                                ...current,
                                                [key]: event.target.value,
                                            }))
                                        }
                                        aria-invalid={
                                            errors[key] ? true : undefined
                                        }
                                        className="h-8"
                                    />
                                    {errors[key] && (
                                        <p className="text-xs text-destructive">
                                            {errors[key]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="flex flex-col items-center justify-center gap-2 rounded-md bg-muted/50 p-4">
                    <div
                        className="relative bg-white shadow-md ring-1 ring-black/10"
                        style={{
                            width: sketch.width,
                            height: sketch.height,
                        }}
                        aria-hidden="true"
                    >
                        <div
                            className="absolute border border-dashed border-sky-500/80 bg-sky-50"
                            style={{
                                top: Math.max(0, numbers.top || 0) * scale,
                                bottom:
                                    Math.max(0, numbers.bottom || 0) * scale,
                                left: Math.max(0, numbers.left || 0) * scale,
                                right: Math.max(0, numbers.right || 0) * scale,
                            }}
                        >
                            {Array.from({ length: 7 }, (_, line) => (
                                <span
                                    key={line}
                                    className="mx-1 mt-1.5 block h-0.5 rounded bg-slate-300"
                                />
                            ))}
                        </div>
                    </div>
                    <span className="tabular text-xs text-muted-foreground">
                        {Math.round(width * 10) / 10} ×{' '}
                        {Math.round(height * 10) / 10} mm
                    </span>
                </div>
            </div>

            <DialogFooter className="gap-2 sm:justify-between">
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onReset}
                    disabled={!isCustom}
                    title="Kembali ke pengaturan halaman bawaan template"
                >
                    <RotateCcw className="size-4" />
                    Bawaan template
                </Button>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Batal
                    </Button>
                    <Button
                        type="button"
                        disabled={!valid}
                        onClick={() => onApply(draft)}
                    >
                        Terapkan
                    </Button>
                </div>
            </DialogFooter>
        </>
    );
}
