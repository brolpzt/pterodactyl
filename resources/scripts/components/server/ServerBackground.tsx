import React, { useLayoutEffect, useState } from 'react';
import tw, { css } from 'twin.macro';
import styled from 'styled-components/macro';
import { getServerBackgroundUrl } from '@/lib/serverBackgrounds';
import { pageFallbackBackground } from '@/assets/css/pageBackground';

interface Props {
    gamedig?: string | null;
    eggName?: string | null;
    children?: React.ReactNode;
    /** Camada fixa em toda a viewport (atrás do sidebar/header). */
    viewport?: boolean;
    className?: string;
    style?: React.CSSProperties;
}

const Wrapper = styled.div<{ $viewport?: boolean }>`
    ${tw`relative min-h-full`};
    background-color: transparent;

    ${(props) =>
        props.$viewport &&
        css`
            position: fixed;
            inset: 0;
            min-height: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        `}
`;

const FallbackLayer = styled.div`
    position: absolute;
    inset: 0;
    ${pageFallbackBackground}
`;

const BackgroundLayer = styled.div<{ $imageUrl: string; $ready: boolean }>`
    position: absolute;
    inset: 0;
    background-image: linear-gradient(to bottom, rgba(9, 9, 17, 0.78), rgba(9, 9, 17, 0.92)),
        url('${(props) => props.$imageUrl}');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    opacity: ${(props) => (props.$ready ? 1 : 0)};
    transition: opacity 0.3s ease-out;
`;

const VignetteLayer = styled.div`
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at center, transparent 0%, rgba(0, 0, 0, 0.28) 100%);
    pointer-events: none;
`;

const ContentLayer = styled.div`
    ${tw`relative z-10 min-h-full`};
`;

const preloadBackgroundImage = (url: string): Promise<void> =>
    new Promise((resolve, reject) => {
        const image = new Image();

        const finish = () => {
            image.onload = null;
            image.onerror = null;
            resolve();
        };

        const fail = () => {
            image.onload = null;
            image.onerror = null;
            reject(new Error('background load failed'));
        };

        image.onload = finish;
        image.onerror = fail;
        image.decoding = 'async';
        image.src = url;

        if (image.complete && image.naturalWidth > 0) {
            finish();
        }
    });

export default ({ gamedig, eggName, children, viewport = false, className, style }: Props) => {
    const [backgroundUrl] = useState(() => getServerBackgroundUrl(gamedig, eggName));
    const [imageReady, setImageReady] = useState(false);
    const [imageError, setImageError] = useState(false);

    useLayoutEffect(() => {
        setImageReady(false);
        setImageError(false);

        if (!backgroundUrl) {
            return;
        }

        let cancelled = false;

        preloadBackgroundImage(backgroundUrl)
            .then(() => {
                if (!cancelled) {
                    setImageReady(true);
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setImageError(true);
                }
            });

        return () => {
            cancelled = true;
        };
    }, [backgroundUrl]);

    const showImage = Boolean(backgroundUrl) && !imageError;
    const layers = (
        <>
            <FallbackLayer />
            {showImage ? <BackgroundLayer $imageUrl={backgroundUrl!} $ready={imageReady} /> : null}
            <VignetteLayer />
        </>
    );

    if (viewport && !children) {
        return (
            <Wrapper className={className} style={style} $viewport>
                {layers}
            </Wrapper>
        );
    }

    return (
        <Wrapper className={className} style={style} $viewport={viewport}>
            {layers}
            {children ? <ContentLayer>{children}</ContentLayer> : null}
        </Wrapper>
    );
};
