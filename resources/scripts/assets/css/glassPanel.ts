import { css } from 'styled-components/macro';
import { motionDurations } from '@/assets/css/motionTheme';
import { hgBorderBottom } from '@/assets/css/borderTheme';

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
        transition: opacity ${motionDurations.glass}ms ease;
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
        transition: opacity ${motionDurations.glass}ms ease;
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
        transition: opacity ${motionDurations.glass}ms ease, backdrop-filter ${motionDurations.glass}ms ease;
    }
`;

export const glassHeaderShell = css`
    position: relative;
    background: transparent;
    overflow-x: clip;
    box-shadow: none;

    ${glassHeaderBeforeLayer};
    ${hgBorderBottom};

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
    border-radius: var(--radius-sm);
    border: 1px solid var(--hg-border);
    box-shadow: none;

    ${glassCardBeforeLayer};

    &::before {
        opacity: 1;
    }
`;

/** Barra sticky do servidor (subheader) — vidro transparente + borda inferior. */
export const glassStickyBarShell = css`
    position: relative;
    background: transparent;
    overflow: visible;
    box-shadow: none;

    ${glassServerBarBeforeLayer};
    ${hgBorderBottom};

    &::before {
        opacity: 1;
        transition: opacity ${motionDurations.glass}ms ease;
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
