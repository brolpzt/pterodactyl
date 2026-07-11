import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';
import { glassSidebarShell } from '@/assets/css/glassPanel';
import { rowListText, rowListTextFiles } from '@/assets/css/cardTheme';

export default styled.div.attrs({ className: 'hg-glass-sidebar' })<{
    $hoverable?: boolean;
    $compact?: boolean;
}>`
    ${glassSidebarShell};
    ${tw`relative flex rounded no-underline items-center overflow-hidden transition-all duration-150`};
    ${rowListText};
    color: var(--hg-nav-text);
    box-shadow: inset 0 0 0 1px rgba(45, 45, 58, 0.28);

    /* Conteúdo acima do ::before (vidro); senão nomes ficam borrados atrás do blur. */
    & > * {
        position: relative;
        z-index: 1;
    }

    & p {
        color: inherit;
    }

    ${(props) =>
        props.$compact
            ? css`
                  ${rowListTextFiles};
                  ${tw`py-1 px-3`};
              `
            : tw`p-3`};

    ${(props) =>
        props.$hoverable !== false &&
        (props.$compact
            ? css`
                  &:hover {
                      color: color-mix(in srgb, var(--hg-nav-text) 65%, var(--color-white) 35%);
                      box-shadow: inset 0 0 0 1px rgba(45, 45, 58, 0.28);

                      &::before {
                          background: color-mix(
                              in srgb,
                              var(--hg-header-glass-bg) 82%,
                              var(--color-white) 18%
                          );
                      }
                  }
              `
            : css`
                  &:hover {
                      color: var(--color-white);
                      box-shadow: inset 0 0 0 1px var(--color-primary), var(--field-glow);
                  }
              `)};

    & .icon {
        ${tw`rounded-full w-16 flex items-center justify-center p-3`};
        background: color-mix(in srgb, var(--color-divider) 38%, transparent);
    }
`;
