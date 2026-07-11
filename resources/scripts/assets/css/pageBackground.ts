import { css } from 'styled-components/macro';

/** Fundo claro padrão — dashboard e servidores sem imagem. */
export const pageFallbackBackground = css`
    background-color: var(--hg-bg-fallback);
    background-image: linear-gradient(
        165deg,
        color-mix(in srgb, var(--hg-bg-fallback) 80%, #ffffff 20%) 0%,
        var(--hg-bg-fallback) 45%,
        color-mix(in srgb, var(--hg-bg-fallback) 90%, #000000 10%) 100%
    );
`;
