import { css } from 'styled-components/macro';

/** Borda padrão hostgamer.net — divider 55% transparente. */
export const hgBorderColor = 'color-mix(in srgb, var(--color-divider) 55%, transparent)';

export const hgBorderBottom = css`
    border-bottom: 1px solid ${hgBorderColor};
`;

export const hgBorderRight = css`
    border-right: 1px solid ${hgBorderColor};
`;

export const hgBorderTop = css`
    border-top: 1px solid ${hgBorderColor};
`;

export const hgBorder = css`
    border: 1px solid ${hgBorderColor};
`;

export const hgRadiusSm = css`
    border-radius: var(--radius-sm);
`;
