import React, { useEffect, useState } from 'react';
import styled from 'styled-components/macro';
import {
    scrollbarThumbColor,
    scrollbarThumbHoverColor,
    scrollbarTrackWidth,
    ScrollbarVariant,
} from '@/assets/css/scrollTheme';
import useOverlayScrollbar, { OverlayScrollTarget } from '@/plugins/useOverlayScrollbar';

interface Props {
    target: OverlayScrollTarget;
    variant?: ScrollbarVariant;
    fixed?: boolean;
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

const OverlayScrollbar = ({ target, variant = 'default', fixed = false, className }: Props) => {
    const [hovered, setHovered] = useState(false);
    const { metrics, onThumbMouseDown, onTrackMouseDown } = useOverlayScrollbar(target);
    const trackWidth = scrollbarTrackWidth(variant);

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

    return (
        <Track
            className={className}
            $width={trackWidth}
            $fixed={fixed}
            style={fixed ? undefined : { height: metrics.trackHeight }}
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

export const DocumentOverlayScrollbar = () => <OverlayScrollbar target={'document'} fixed variant={'default'} />;

export default OverlayScrollbar;
