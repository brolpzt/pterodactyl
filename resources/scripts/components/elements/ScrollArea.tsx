import React, { forwardRef, HTMLAttributes, useCallback, useState } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { overlayScrollBehavior, ScrollbarVariant } from '@/assets/css/scrollTheme';
import OverlayScrollbar from '@/components/elements/OverlayScrollbar';

type ScrollAxis = 'y' | 'x' | 'both';

interface ScrollAreaProps extends HTMLAttributes<HTMLDivElement> {
    variant?: ScrollbarVariant;
    axis?: ScrollAxis;
}

const Root = styled.div<{ $axis: ScrollAxis }>`
    position: relative;
    min-height: 0;
    ${(p) => p.$axis === 'y' && tw`overflow-x-hidden`}
    ${(p) => p.$axis === 'x' && tw`overflow-y-hidden overflow-x-auto`}
    ${(p) => p.$axis === 'both' && tw`overflow-auto`}
    ${(p) => (p.$axis === 'y' || p.$axis === 'both') && overlayScrollBehavior};
`;

const ScrollArea = forwardRef<HTMLDivElement, ScrollAreaProps>(
    ({ variant = 'default', axis = 'y', children, ...props }, ref) => {
        const [element, setElement] = useState<HTMLDivElement | null>(null);

        const setRefs = useCallback(
            (node: HTMLDivElement | null) => {
                setElement(node);

                if (typeof ref === 'function') {
                    ref(node);
                } else if (ref) {
                    ref.current = node;
                }
            },
            [ref]
        );

        return (
            <Root ref={setRefs} $axis={axis} {...props}>
                {children}
                {(axis === 'y' || axis === 'both') && (
                    <OverlayScrollbar target={element} variant={variant} anchored />
                )}
            </Root>
        );
    }
);

ScrollArea.displayName = 'ScrollArea';

export default ScrollArea;
