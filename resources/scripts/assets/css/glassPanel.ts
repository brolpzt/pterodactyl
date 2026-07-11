import { css } from 'styled-components/macro';

/**
 * Vidro / backdrop-filter — espelha .site-header do hostgamer.net.
 * - Blur fixo em ::before; nunca animar backdrop-filter.
 * - Entrada/saída só com opacity.
 * - Fallback global em GlobalStylesheet (.hg-glass-*).
 */
export const glassHeaderBeforeLayer = css`
    &::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        border: 0;
        background: var(--hg-header-glass-bg);
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
        transform: translateZ(0);
        transition: opacity 220ms ease;
    }
`;

export const glassCardBeforeLayer = css`
    &::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        border: 0;
        background: var(--hg-card-glass-bg);
        -webkit-backdrop-filter: blur(4px);
        backdrop-filter: blur(4px);
        transform: translateZ(0);
        transition: opacity 220ms ease;
    }
`;

/** @deprecated Use glassHeaderBeforeLayer ou glassCardBeforeLayer. */
export const glassBeforeLayer = glassHeaderBeforeLayer;

export const glassServerBarBeforeLayer = css`
    &::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        border: 0;
        background: var(--hg-server-bar-glass-bg);
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
        transform: translateZ(0);
        transition: opacity 220ms ease, backdrop-filter 220ms ease;
    }
`;

export const glassHeaderShell = css`
    position: relative;
    background: transparent;
    overflow-x: clip;
    box-shadow: none;

    ${glassHeaderBeforeLayer};

    &::before {
        opacity: 1;
    }
`;

export const glassSidebarShell = css`
    position: relative;
    background: transparent;
    overflow: hidden;
    box-shadow: none;

    ${glassHeaderBeforeLayer};

    &::before {
        opacity: 1;
    }
`;

export const glassCardShell = css`
    position: relative;
    background: transparent;
    overflow: hidden;
    box-shadow: 0 12px 32px -18px rgba(0, 0, 0, 0.82),
        inset 0 0 0 1px rgba(45, 45, 58, 0.28);

    ${glassCardBeforeLayer};

    &::before {
        opacity: 1;
    }
`;

/** Barra sticky do servidor (subheader) — vidro transparente + sombra. */
export const glassStickyBarShell = css`
    position: relative;
    background: transparent;
    overflow: visible;
    box-shadow: var(--hg-subheader-shadow);

    ${glassServerBarBeforeLayer};

    &::before {
        opacity: 1;
        transition: opacity 220ms ease;
    }
`;

export const glassContentLayer = css`
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
`;

/** Camada de conteúdo horizontal (header). */
export const glassHeaderInner = css`
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: row;
    align-items: center;
    width: 100%;
    min-height: 0;
`;
