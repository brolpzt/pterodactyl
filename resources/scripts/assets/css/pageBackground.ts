import { css } from 'styled-components/macro';

/** Fundo padrão — dashboard e servidores sem imagem. */
export const pageFallbackBackground = css`
    background-color: var(--hg-bg-fallback);
    background-image: linear-gradient(
        165deg,
        color-mix(in srgb, var(--hg-bg-fallback) 78%, #000000 22%) 0%,
        var(--hg-bg-fallback) 50%,
        color-mix(in srgb, var(--hg-bg-fallback) 82%, #000000 18%) 100%
    );
`;
