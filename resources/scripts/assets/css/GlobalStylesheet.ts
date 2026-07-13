import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
import { hostgamerColors } from '@/lib/hostgamerTheme';
import { globalScrollbarStyles } from '@/assets/css/scrollTheme';
import { revealThemeStyles } from '@/assets/css/revealTheme';

export default createGlobalStyle`
    :root {
        --hg-bg: ${hostgamerColors.bg};
        --hg-bg-fallback: ${hostgamerColors.serverBgFallback};
        --hg-surface: ${hostgamerColors.surface};
        --hg-text: ${hostgamerColors.text};
        --hg-primary: ${hostgamerColors.primary};
        --hg-primary-strong: ${hostgamerColors.primaryStrong};
        --hg-primary-soft: ${hostgamerColors.primarySoft};
        --hg-divider: ${hostgamerColors.divider};
        --hg-header-glass-bg: ${hostgamerColors.headerGlassBg};
        --hg-server-bar-glass-bg: ${hostgamerColors.serverBarGlassBg};
        --hg-subheader-shadow: ${hostgamerColors.subheaderShadow};
        --hg-subheader-shadow-scrolled: ${hostgamerColors.subheaderShadowScrolled};
        --hg-card-glass-bg: ${hostgamerColors.cardGlassBg};
        --hg-card-header: ${hostgamerColors.cardHeaderBg};
        --hg-focus-ring: ${hostgamerColors.focusRing};
        --hg-btn-glow: ${hostgamerColors.btnGlow};
        --hg-btn-danger-glow: ${hostgamerColors.btnDangerGlow};
        --btn-glow: var(--hg-btn-glow);
        --btn-danger-glow: var(--hg-btn-danger-glow);
        --font-size-btn: ${hostgamerColors.button.fontSize};
        --line-height-btn: ${hostgamerColors.button.lineHeight};
        --btn-gap: ${hostgamerColors.button.gap};
        --btn-min-height: ${hostgamerColors.button.minHeight};
        --btn-padding: ${hostgamerColors.button.padding};
        --btn-min-height-md: ${hostgamerColors.button.minHeightMd};
        --btn-padding-md: ${hostgamerColors.button.paddingMd};
        --btn-min-height-sm: ${hostgamerColors.button.minHeightSm};
        --btn-padding-sm: ${hostgamerColors.button.paddingSm};
        --font-size-btn-sm: ${hostgamerColors.button.fontSizeSm};
        --hg-field-glow: ${hostgamerColors.fieldGlow};
        --field-glow: ${hostgamerColors.fieldGlow};
        --transition-base: ${hostgamerColors.transitionBase};
        --color-danger: #dc2626;
        --radius-sm: 0.375rem;
        --radius-ui: var(--radius-sm);
        --hg-border: color-mix(in srgb, var(--color-divider) 55%, transparent);
        --hg-card-header-text: color-mix(in srgb, var(--color-text-muted) 88%, var(--color-white) 12%);
        --font-family-heading: 'Oxanium', Arial, Helvetica, sans-serif;
        --font-family-mono: ui-monospace, 'Cascadia Code', 'Courier New', Courier, monospace;
        --font-size-mono: ${hostgamerColors.mono.fontSize};
        --line-height-mono: ${hostgamerColors.mono.lineHeight};
        --font-size-mono-console: ${hostgamerColors.mono.consoleFontSize};

        /* Aliases hostgamer.net */
        --color-bg: var(--hg-bg);
        --color-surface: var(--hg-surface);
        --color-secondary: var(--hg-bg);
        --color-text: ${hostgamerColors.text};
        --color-primary: var(--hg-primary);
        --color-text-muted: ${hostgamerColors.textMuted};
        --color-white: #fff;
        --color-label: ${hostgamerColors.labelColor};
        --color-input-placeholder: ${hostgamerColors.inputPlaceholder};
        --field-bg: var(--color-bg);
        --field-border: var(--color-divider);
        --color-border: var(--color-divider);
        --header-glass-bg: var(--hg-header-glass-bg);
        --color-divider: ${hostgamerColors.divider};
        --hg-nav-text: color-mix(in srgb, var(--color-white) 58%, var(--color-text-muted));
        --hg-field-hint: color-mix(in srgb, var(--color-text-muted) 88%, transparent);
        --hg-card-surface: transparent;
        --hg-card-label: var(--hg-card-header-text);
    }

    .hg-layout-sidebar {
        border-right: 1px solid var(--hg-border);
        box-shadow: none;
    }

    .hg-glass-card {
        border-radius: var(--radius-sm);
        border: 1px solid var(--hg-border);
    }

    .hg-card-header {
        color: var(--hg-card-header-text);
    }

    .field-label {
        display: inline-block;
        margin-bottom: 0.375rem;
        font-family: var(--font-family-heading);
        font-size: 0.875rem;
        font-weight: 600;
        letter-spacing: normal;
        text-transform: uppercase;
        color: var(--hg-nav-text);
    }

    .hg-glass-card .field-label {
        color: var(--hg-nav-text);
    }

    .font-mono {
        font-family: var(--font-family-mono);
        font-size: var(--font-size-mono) !important;
        line-height: var(--line-height-mono);
    }

    .console-mono,
    .console-mono.font-mono {
        font-size: var(--font-size-mono-console) !important;
        line-height: 1.35;
    }

    .mono-panel {
        font-family: var(--font-family-mono);
        font-size: var(--font-size-mono);
        line-height: var(--line-height-mono);
        color: var(--color-white);
        background: var(--field-bg);
        border: 1px solid var(--field-border);
        border-radius: var(--radius-ui);
        padding: 0.65rem 0.85rem;
        word-break: break-word;
        white-space: pre-wrap;
    }

    .input-base,
    .select-base,
    .textarea-base {
        border: 1px solid var(--field-border);
        border-radius: var(--radius-ui);
        background-color: var(--field-bg);
        width: 100%;
        color: var(--color-white);
        transition: border-color var(--transition-base), box-shadow var(--transition-base),
            background-color var(--transition-base);
        padding: 0.58rem 0.75rem;
        font-family: inherit;
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.35;
        box-shadow: none;
        outline: none;
    }

    .textarea-base {
        resize: vertical;
        min-height: 5.6rem;
    }

    .input-base::placeholder,
    .textarea-base::placeholder {
        color: var(--color-input-placeholder);
    }

    .input-base:-webkit-autofill,
    .input-base:-webkit-autofill:hover,
    .textarea-base:-webkit-autofill,
    .textarea-base:-webkit-autofill:hover {
        -webkit-text-fill-color: var(--color-white);
        caret-color: var(--color-white);
        -webkit-box-shadow: 0 0 0 1000px var(--field-bg) inset;
        transition: background-color 99999s ease-out;
    }

    .input-base:-webkit-autofill:focus-visible,
    .textarea-base:-webkit-autofill:focus-visible {
        -webkit-text-fill-color: var(--color-white);
        caret-color: var(--color-white);
        -webkit-box-shadow: 0 0 0 1000px var(--field-bg) inset;
        transition: background-color 99999s ease-out;
    }

    .input-base:hover:not(:disabled):not(:read-only),
    .input-base:focus-visible:not(:disabled):not(:read-only),
    .select-base:hover:not(:disabled),
    .select-base:focus-visible:not(:disabled),
    .textarea-base:hover:not(:disabled):not(:read-only),
    .textarea-base:focus-visible:not(:disabled):not(:read-only) {
        border-color: var(--color-primary) !important;
        box-shadow: var(--field-glow) !important;
        outline: none;
    }

    .select-base {
        appearance: none;
        padding-right: 2rem;
        background-image: linear-gradient(45deg, transparent 50%, var(--color-primary) 50%),
            linear-gradient(135deg, var(--color-primary) 50%, transparent 50%);
        background-position: calc(100% - 18px) calc(50% - 2px), calc(100% - 12px) calc(50% - 2px);
        background-repeat: no-repeat;
        background-size: 6px 6px, 6px 6px;
    }

    .input-base[aria-invalid='true'],
    .select-base[aria-invalid='true'],
    .textarea-base[aria-invalid='true'] {
        border-color: var(--color-danger);
        box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-danger) 32%, transparent);
    }

    .input-base[aria-invalid='true']:hover:not(:disabled):not(:read-only),
    .input-base[aria-invalid='true']:focus-visible:not(:disabled):not(:read-only),
    .select-base[aria-invalid='true']:hover:not(:disabled),
    .select-base[aria-invalid='true']:focus-visible:not(:disabled),
    .textarea-base[aria-invalid='true']:hover:not(:disabled):not(:read-only),
    .textarea-base[aria-invalid='true']:focus-visible:not(:disabled):not(:read-only) {
        border-color: var(--color-danger) !important;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-danger) 24%, transparent) !important;
    }

    .input-help,
    .field-hint {
        margin-top: 0.45rem;
        font-size: 0.84rem;
        font-weight: 600;
        line-height: 1.45;
        color: var(--hg-field-hint);
    }

    .input-help.error,
    .field-error {
        color: var(--color-danger);
    }

    /**
     * Vidro / backdrop-filter — compatibilidade (Chrome Windows).
     * Blur fixo em camada separada; fallback opaco quando blur não é suportado.
     */
    .hg-glass-header::before,
    .hg-glass-sidebar::before,
    .hg-glass-card::before,
    .hg-glass-server-bar::before,
    .hg-dropdown-menu {
        transform: translateZ(0);
    }

    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
        .hg-glass-header::before,
        .hg-glass-sidebar::before {
            background: var(--header-glass-bg);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .hg-glass-server-bar::before {
            background: color-mix(in srgb, var(--color-bg) 22%, transparent);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .hg-glass-card::before {
            background: var(--hg-card-glass-bg);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }

        .hg-dropdown-menu {
            background: var(--hg-surface);
            -webkit-backdrop-filter: none;
            backdrop-filter: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .hg-glass-header::before,
        .hg-glass-sidebar::before,
        .hg-glass-card::before,
        .hg-glass-server-bar::before {
            animation-duration: 1ms !important;
            animation-delay: 0ms !important;
            transition-duration: 1ms !important;
        }
    }

    .hg-card-header,
    .hg-card-header p,
    .hg-card-header > div,
    .hg-card-header > div p {
        color: var(--hg-card-header-text);
        font-family: 'Oxanium', Arial, Helvetica, sans-serif;
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        line-height: 1.25rem;
    }

    .hg-card-header svg {
        color: currentColor;
    }

    .flag-icon {
        object-fit: cover;
        width: 1rem;
        height: 1rem;
        border-radius: 0.2rem;
        flex-shrink: 0;
        display: inline-block;
    }

    .flag-icon--header {
        width: 0.95rem;
        height: 0.95rem;
    }

    @font-face {
        font-family: 'Manrope';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('/fonts/manrope-600.woff2') format('woff2');
    }

    @font-face {
        font-family: 'Oxanium';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('/fonts/oxanium-600.woff2') format('woff2');
    }

    @font-face {
        font-family: 'Oxanium';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        ascent-override: 88%;
        descent-override: 22%;
        line-gap-override: 0%;
        size-adjust: 105%;
        src: url('/fonts/oxanium-700.woff2') format('woff2');
    }

    *,
    *::before,
    *::after {
        box-sizing: border-box;
        border: 0 solid;
        margin: 0;
        padding: 0;
    }

    html {
        -webkit-text-size-adjust: 100%;
        tab-size: 4;
        line-height: 1.5;
        color-scheme: dark;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    ::selection {
        background: var(--hg-primary);
        color: #fff;
    }

    ${globalScrollbarStyles}

    body {
        background-color: var(--color-bg) !important;
        color: var(--color-text-muted);
        font-family: 'Manrope', Arial, Helvetica, sans-serif;
        font-size: 1.125rem;
        font-weight: 600;
        line-height: 1.6;
        margin: 0;
        overflow-x: clip;
    }

    #app,
    #root {
        background: transparent;
        min-height: 100%;
    }

    h1, h2, h3, h4, h5, h6 {
        font-size: inherit;
        font-weight: inherit;
        font-family: 'Oxanium', Arial, Helvetica, sans-serif;
        font-weight: 600;
        letter-spacing: -0.02em;
        line-height: 1.1;
    }

    p {
        ${tw`text-neutral-400`};
        font-family: inherit;
        line-height: 1.6;
    }

    form {
        margin: 0;
    }

    textarea, select, input, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    textarea, select, input {
        font-family: inherit;
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    code, pre, kbd, samp {
        font-family: ui-monospace, 'Cascadia Code', 'Courier New', Courier, monospace;
    }

    ${revealThemeStyles}
`;
