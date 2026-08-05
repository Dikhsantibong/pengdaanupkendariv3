const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const compactCurrencyFormatter = new Intl.NumberFormat('id-ID', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const numberFormatter = new Intl.NumberFormat('id-ID');

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function formatCurrency(value: number | string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return currencyFormatter.format(Number(value));
}

export function formatCompactCurrency(value: number | string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return `Rp ${compactCurrencyFormatter.format(Number(value))}`;
}

export function formatNumber(value: number | string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return numberFormatter.format(Number(value));
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return dateFormatter.format(new Date(value.replace(' ', 'T')));
}

export function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return dateTimeFormatter.format(new Date(value.replace(' ', 'T')));
}
