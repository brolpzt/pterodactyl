import React, { useEffect } from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import FlashMessageRender from '@/components/FlashMessageRender';

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    showFlashKey?: string;
    /** Menos margem superior — páginas de servidor com breadcrumb acima. */
    dense?: boolean;
}

const PageContentBlock: React.FC<PageContentBlockProps> = ({ title, showFlashKey, className, dense, children }) => {
    useEffect(() => {
        if (title) {
            document.title = title;
        }
    }, [title]);

    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                <ContentContainer
                    css={dense ? tw`mt-0 mb-4 sm:mb-10` : tw`my-4 sm:my-10`}
                    className={className}
                >
                    {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
                    {children}
                </ContentContainer>
            </>
        </CSSTransition>
    );
};

export default PageContentBlock;
