import { Building2 } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import partners from '@/routes/partners';

export type PartnerSuggestion = {
    id: number;
    name: string;
    vendor_code: string | null;
    director_name: string | null;
    address: string | null;
};

/**
 * A partner's name with the Mitra master data offered as it is typed: from
 * the first letters on, the matching partners drop down, and picking one
 * hands its details back so the rest of the form fills in. A name not in
 * the list is simply typed in full; it joins the master data once saved.
 */
export function PartnerNameInput({
    id,
    value,
    onChange,
    onPick,
    placeholder,
    required,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    onPick?: (partner: PartnerSuggestion) => void;
    placeholder?: string;
    required?: boolean;
}) {
    const listId = useId();
    const [suggestions, setSuggestions] = useState<PartnerSuggestion[]>([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    // Only what the user typed asks the server, not a name just picked.
    const typed = useRef(false);

    useEffect(() => {
        if (!typed.current) {
            return;
        }

        const search = value.trim();

        // An emptied field hides the list (see showList); nothing to ask.
        if (search === '') {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(partners.suggest({ query: { search } }).url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<{
                              partners: PartnerSuggestion[];
                          }>)
                        : { partners: [] },
                )
                .then((data) => {
                    setSuggestions(data.partners);
                    setActive(-1);
                })
                .catch(() => {
                    // Aborted or offline: the name can still be typed in full.
                });
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [value]);

    const pick = (partner: PartnerSuggestion) => {
        typed.current = false;
        onChange(partner.name);
        onPick?.(partner);
        setOpen(false);
        setSuggestions([]);
    };

    const showList = open && value.trim() !== '' && suggestions.length > 0;

    return (
        <div className="relative">
            <Input
                id={id}
                value={value}
                required={required}
                placeholder={
                    placeholder ?? 'Ketik nama mitra, mis. CV. atau PT. …'
                }
                autoComplete="off"
                role="combobox"
                aria-expanded={showList}
                aria-controls={listId}
                aria-autocomplete="list"
                onChange={(event) => {
                    typed.current = true;
                    onChange(event.target.value);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                // Late enough for a click on a suggestion to land first.
                onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                onKeyDown={(event) => {
                    if (!showList) {
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        setActive((at) => (at + 1) % suggestions.length);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        setActive(
                            (at) =>
                                (at - 1 + suggestions.length) %
                                suggestions.length,
                        );
                    } else if (event.key === 'Enter' && active >= 0) {
                        event.preventDefault();
                        pick(suggestions[active]);
                    } else if (event.key === 'Escape') {
                        setOpen(false);
                    }
                }}
            />
            {showList && (
                <ul
                    id={listId}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-md"
                >
                    {suggestions.map((partner, at) => (
                        <li
                            key={partner.id}
                            role="option"
                            aria-selected={at === active}
                            className={cn(
                                'flex cursor-pointer items-start gap-2 rounded-sm px-2 py-1.5 text-sm',
                                at === active
                                    ? 'bg-accent text-accent-foreground'
                                    : 'hover:bg-accent/60',
                            )}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                pick(partner);
                            }}
                            onMouseEnter={() => setActive(at)}
                        >
                            <Building2 className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                            <span className="min-w-0">
                                <span className="block truncate font-medium">
                                    {partner.name}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {[
                                        partner.vendor_code &&
                                            `Kode ${partner.vendor_code}`,
                                        partner.director_name,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || 'Data mitra tersimpan'}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
