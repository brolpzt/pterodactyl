import { css } from 'styled-components/macro';

export const HEADER_HEIGHT = '3.5rem';
export const HEADER_HEIGHT_FALLBACK_PX = 56;

/** Altura em px do header fixo (`.hg-glass-header`). */
export const getFixedHeaderHeight = (): number => {
    if (typeof document === 'undefined') {
        return HEADER_HEIGHT_FALLBACK_PX;
    }

    const header = document.querySelector('.hg-glass-header');
    return header ? header.getBoundingClientRect().height : HEADER_HEIGHT_FALLBACK_PX;
};
export const SIDEBAR_WIDTH_EXPANDED = 240;
export const SIDEBAR_WIDTH_COLLAPSED = 70;

/** Offset horizontal do header / conteúdo principal conforme largura do sidebar. */
export const sidebarLayoutOffset = (collapsed: boolean) => css`
    left: ${collapsed ? `${SIDEBAR_WIDTH_COLLAPSED}px` : `${SIDEBAR_WIDTH_EXPANDED}px`};

    @media (max-width: 767px) {
        left: ${collapsed ? '0' : 'min(240px, 85vw)'};
    }
`;

export const sidebarMainMargin = (collapsed: boolean) => css`
    margin-left: 0;

    @media (min-width: 768px) {
        margin-left: ${collapsed ? `${SIDEBAR_WIDTH_COLLAPSED}px` : `${SIDEBAR_WIDTH_EXPANDED}px`};
    }
`;

export const sidebarWidthRule = (collapsed: boolean) => css`
    width: ${collapsed ? `${SIDEBAR_WIDTH_COLLAPSED}px` : `${SIDEBAR_WIDTH_EXPANDED}px`};

    @media (max-width: 767px) {
        width: ${collapsed ? '0px' : 'min(240px, 85vw)'};
    }
`;
