/**
 * How many cards a row of a summary board holds: between `min` and `max`, the
 * count that leaves the fewest empty places on the last row (the most cards
 * per row when several divide evenly), so 21 statuses lay out 7 × 3.
 */
export function evenColumns(count: number, min = 4, max = 7): number {
    if (count <= max) {
        return Math.max(1, count);
    }

    let best = max;
    let leastEmpty = Number.POSITIVE_INFINITY;

    for (let columns = max; columns >= min; columns--) {
        const empty = (columns - (count % columns)) % columns;

        if (empty < leastEmpty) {
            best = columns;
            leastEmpty = empty;
        }
    }

    return best;
}

/**
 * The flex basis of a card in a row of `columns`, the gap taken off; the
 * cards grow, so a last row that is not full still fills the width.
 */
export function cardBasis(columns: number, gapRem = 0.75): string {
    return `calc(${100 / columns}% - ${gapRem}rem)`;
}
