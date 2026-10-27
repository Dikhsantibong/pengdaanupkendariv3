import { FinanceBoard } from '@/components/finance-board';
import type { BoardProps } from '@/components/finance-board';
import { dashboard } from '@/routes';
import handover from '@/routes/handover';

/** The Penyerahan board. */
export default function HandoverIndex(props: BoardProps) {
    return <FinanceBoard {...props} />;
}

HandoverIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Penyerahan', href: handover.index() },
    ],
};
