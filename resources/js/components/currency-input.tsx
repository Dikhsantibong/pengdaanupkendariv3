import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const groupFormatter = new Intl.NumberFormat('id-ID');

/**
 * Rupiah input that shows thousand separators while reporting a plain number.
 * Pass `name` to also emit a hidden field for uncontrolled `<Form>` submissions.
 */
export function CurrencyInput({
    value,
    onValueChange,
    name,
    className,
    ...props
}: Omit<ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: number;
    onValueChange: (value: number) => void;
}) {
    const display = value === 0 ? '' : groupFormatter.format(value);

    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-muted-foreground">
                Rp
            </span>

            <Input
                inputMode="numeric"
                autoComplete="off"
                placeholder="0"
                className={cn('tabular pl-9 text-right', className)}
                value={display}
                onChange={(event) => {
                    const digits = event.target.value.replace(/\D/g, '');

                    onValueChange(digits === '' ? 0 : Number(digits));
                }}
                {...props}
            />

            {name && <input type="hidden" name={name} value={value} />}
        </div>
    );
}
