import React, { useEffect, useState } from 'react';
import tw, { css } from 'twin.macro';
import styled, { keyframes } from 'styled-components/macro';
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

const backgroundReveal = keyframes`
    from {
        opacity: 0;
        transform: scale(1.05);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
`;

const Wrapper = styled.div<{ $viewport?: boolean; $fallback?: boolean }>`
    ${tw`relative min-h-full`};
    background-color: ${(props) => (props.$fallback ? 'var(--hg-bg-fallback)' : 'var(--color-bg)')};

    ${(props) =>
        props.$fallback &&
        pageFallbackBackground}

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

const BackgroundLayer = styled.div<{ $imageUrl: string; $ready: boolean }>`
    position: absolute;
    inset: 0;
    background-image: linear-gradient(to bottom, rgba(9, 9, 17, 0.55), rgba(9, 9, 17, 0.72)),
        url('${(props) => props.$imageUrl}');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    opacity: 0;
    transform: scale(1.05);
    will-change: opacity, transform;

    ${(props) =>
        props.$ready &&
        css`
            animation: ${backgroundReveal} 1s ease-out forwards;
        `}
`;

const ContentLayer = styled.div`
    ${tw`relative z-10 min-h-full`};
`;

const ServerBackground = ({ gamedig, eggName, children, viewport = false, className, style }: Props) => {
    const [backgroundUrl] = useState(() => getServerBackgroundUrl(gamedig, eggName));
    const [imageReady, setImageReady] = useState(false);
    const [imageError, setImageError] = useState(false);

    useEffect(() => {
        setImageReady(false);
        setImageError(false);

        if (!backgroundUrl) {
            return;
        }

        const image = new Image();
        image.onload = () => setImageReady(true);
        image.onerror = () => setImageError(true);
        image.src = backgroundUrl;

        return () => {
            image.onload = null;
            image.onerror = null;
        };
    }, [backgroundUrl]);

    const showImage = Boolean(backgroundUrl) && !imageError;
    const useFallbackBackground = !showImage;

    const layers = showImage ? <BackgroundLayer $imageUrl={backgroundUrl!} $ready={imageReady} /> : null;

    if (viewport && !children) {
        return (
            <Wrapper className={className} style={style} $viewport $fallback={useFallbackBackground}>
                {layers}
            </Wrapper>
        );
    }

    return (
        <Wrapper className={className} style={style} $viewport={viewport} $fallback={useFallbackBackground}>
            {layers}
            {children ? <ContentLayer>{children}</ContentLayer> : null}
        </Wrapper>
    );
};

export default ServerBackground;
