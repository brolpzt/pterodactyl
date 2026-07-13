import React, { useEffect, useState } from 'react';
import styled from 'styled-components/macro';
import {
    scrollbarThumbColor,
    scrollbarThumbHoverColor,
    scrollbarTrackWidth,
    ScrollbarVariant,
} from '@/assets/css/scrollTheme';
import { getFixedHeaderHeight } from '@/lib/sidebarLayout';
import useOverlayScrollbar, { OverlayScrollTarget } from '@/plugins/useOverlayScrollbar';

interface Props {
    target: OverlayScrollTarget;
    variant?: ScrollbarVariant;
    fixed?: boolean;
    /** Fixa o trilho na borda visível do alvo (sidebar, terminal, etc.). */
    anchored?: boolean;
    /** Inicia o trilho abaixo do header fixo (scroll da página). */
    belowHeader?: boolean;
    className?: string;
}

const Track = styled.div<{ $width: number, $fixed: boolean }>`
    position: ${(p) => (p.$fixed ? 'fixed' : 'absolute')};
    top: 0;
    right: 0;
    width: ${(p) => p.$width}px;
    height: ${(p) => (p.$fixed ? '100vh' : '100%')};
    background: transparent;
    z-index: 40;
    pointer-events: none;
`;

const Thumb = styled.div<{ $hovered: boolean }>`
    position: absolute;
    right: 1px;
    width: 4px;
    border-radius: 999px;
    pointer-events: auto;
    cursor: pointer;
    transition: background-color 0.15s ease, opacity 0.15s ease;
    background-color: ${(p) =>
        p.$hovered
            ? `color-mix(in srgb, ${scrollbarThumbHoverColor} 90%, transparent)`
            : `color-mix(in srgb, ${scrollbarThumbColor} 65%, transparent)`};
    opacity: ${(p) => (p.$hovered ? 1 : 0.75)};

    &:hover {
        background-color: color-mix(in srgb, ${scrollbarThumbHoverColor} 90%, transparent);
        opacity: 1;
    }
`;

const OverlayScrollbar = ({
    target,
    variant = 'default',
    fixed = false,
    anchored = false,
    belowHeader = false,
    className,
}: Props) => {
    const [hovered, setHovered] = useState(false);
    const [headerOffset, setHeaderOffset] = useState(() => (belowHeader ? getFixedHeaderHeight() : 0));
    const { metrics, onThumbMouseDown, onTrackMouseDown } = useOverlayScrollbar(target, {
        viewportTop: belowHeader ? headerOffset : 0,
    });
    const trackWidth = scrollbarTrackWidth(variant);
    const useAnchoredTrack = anchored && target && target !== 'document';

    useEffect(() => {
        if (!belowHeader) {
            return;
        }

        const updateHeaderOffset = () => setHeaderOffset(getFixedHeaderHeight());

        updateHeaderOffset();
        window.addEventListener('resize', updateHeaderOffset);

        const header = document.querySelector('.hg-glass-header');
        const observer = typeof ResizeObserver !== 'undefined' && header
            ? new ResizeObserver(updateHeaderOffset)
            : undefined;
        if (header) {
            observer?.observe(header);
        }

        return () => {
            window.removeEventListener('resize', updateHeaderOffset);
            observer?.disconnect();
        };
    }, [belowHeader]);

    useEffect(() => {
        if (!hovered) {
            return;
        }

        const onLeave = () => setHovered(false);
        document.addEventListener('mouseleave', onLeave);

        return () => document.removeEventListener('mouseleave', onLeave);
    }, [hovered]);

    if (!metrics.visible) {
        return null;
    }

    const trackStyle = useAnchoredTrack
        ? {
              top: 0,
              right: 0,
              height: '100%',
          }
        : belowHeader && fixed
            ? {
                  top: headerOffset,
                  height: `calc(100vh - ${headerOffset}px)`,
              }
            : fixed
                ? undefined
                : { height: metrics.trackHeight };

    return (
        <Track
            className={className}
            $width={trackWidth}
            $fixed={fixed && !useAnchoredTrack}
            style={trackStyle}
            onMouseDown={(event) => onTrackMouseDown(event, metrics.trackHeight)}
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
        >
            <Thumb
                $hovered={hovered}
                style={{
                    height: metrics.thumbHeight,
                    top: metrics.thumbTop,
                }}
                onMouseDown={(event) => {
                    event.stopPropagation();
                    onThumbMouseDown(event);
                }}
            />
        </Track>
    );
};

export const DocumentOverlayScrollbar = () => (
    <OverlayScrollbar target={'document'} fixed belowHeader variant={'default'} />
);

export default OverlayScrollbar;
