const HEX = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i;

/** Writes the brand colors from shared settings into :root CSS variables. */
export function applyTheme(theme) {
    if (!theme || typeof document === 'undefined') return;

    const root = document.documentElement.style;
    for (const key of ['primary', 'secondary', 'tertiary']) {
        if (HEX.test(theme[key] ?? '')) {
            root.setProperty(`--brand-${key}`, theme[key]);
        }
    }
}
