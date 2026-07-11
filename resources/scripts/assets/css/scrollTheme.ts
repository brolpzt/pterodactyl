import { css } from 'styled-components/macro';
import { hostgamerColors } from '@/lib/hostgamerTheme';

export type ScrollbarVariant = 'default' | 'thin' | 'console';

const thumbBase = hostgamerColors.primaryStrong;
const thumbHover = hostgamerColors.primary;

export const scrollbarThumbColor = thumbBase;
export const scrollbarThumbHoverColor = thumbHover;

export const scrollbarTrackWidth = (variant: ScrollbarVariant = 'default') => {
    switch (variant) {
        case 'thin':
            return 6;
        case 'console':
            return 8;
        default:
            return 8;
    }
};

/** Esconde o scrollbar nativo — o scroll continua funcionando (mouse/trackpad). */
export const hideNativeScrollbar = css`
    scrollbar-width: none;
    -ms-overflow-style: none;

    &::-webkit-scrollbar {
        display: none;
        width: 0;
        height: 0;
        background: transparent;
    }
`;

export const overlayScrollBehavior = css`
    overflow-y: auto;
    ${hideNativeScrollbar};
`;

/** Estilos globais — página sem gutter de scrollbar nativo. */
export const globalScrollbarStyles = css`
    html {
        overflow-y: auto;
        ${hideNativeScrollbar};
    }

    body {
        ${hideNativeScrollbar};
    }
`;
