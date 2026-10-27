import { FinanceBoard } from '@/components/finance-board';
import type { BoardProps } from '@/components/finance-board';
import { dashboard } from '@/routes';
import finance from '@/routes/finance';

/** The Pembayaran board (the menu once called Keuangan). */
export default function FinanceIndex(props: BoardProps) {
    return <FinanceBoard {...props} />;
}

FinanceIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Pembayaran', href: finance.index() },
    ],
};
