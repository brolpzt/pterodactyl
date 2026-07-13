import React, { useEffect, useMemo } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import RevealOnView from '@/components/elements/RevealOnView';
import { useLocation } from 'react-router-dom';

import { motionDurations } from '@/assets/css/motionTheme';

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    showFlashKey?: string;
    /** Margem compacta nas páginas de servidor (abaixo do subheader). */
    dense?: boolean;
}

const STAGGER_MS = motionDurations.revealStagger;

const PageContentBlock: React.FC<PageContentBlockProps> = ({ title, showFlashKey, className, dense, children }) => {
    const { pathname } = useLocation();

    useEffect(() => {
        if (title) {
            document.title = title;
        }
    }, [title]);

    const contentItems = useMemo(() => React.Children.toArray(children).filter(Boolean), [children]);
    const revealKey = pathname;

    return (
        <ContentContainer
            css={dense ? tw`mt-6 sm:mt-8 mb-4 sm:mb-10` : tw`my-4 sm:my-10`}
            className={className}
        >
            {showFlashKey && (
                <RevealOnView key={`${revealKey}-flash`} delay={0}>
                    <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />
                </RevealOnView>
            )}
            {contentItems.length <= 1 ? (
                <RevealOnView key={revealKey} delay={showFlashKey ? STAGGER_MS : 0}>
                    {children}
                </RevealOnView>
            ) : (
                contentItems.map((child, index) => (
                    <RevealOnView
                        key={`${revealKey}-${index}`}
                        delay={(showFlashKey ? index + 1 : index) * STAGGER_MS}
                    >
                        {child}
                    </RevealOnView>
                ))
            )}
        </ContentContainer>
    );
};

export default PageContentBlock;
