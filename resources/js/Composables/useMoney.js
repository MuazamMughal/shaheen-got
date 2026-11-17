const formatter = new Intl.NumberFormat('en-PK', { maximumFractionDigits: 0 });

/** "PKR 182,000" — returns '—' for null/undefined (e.g. hidden prices). */
export function formatPKR(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `PKR ${formatter.format(Number(value))}`;
}

export function formatNumber(value) {
    return formatter.format(Number(value ?? 0));
}
