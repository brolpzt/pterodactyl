import { css } from 'styled-components/macro';
import tw from 'twin.macro';

/** Tipografia dos títulos de card — alinhada ao header do site. */
export const cardTitleFont = css`
    ${tw`text-sm font-header font-semibold uppercase`};
`;

export const cardSurface = css`
    background: var(--hg-card-surface);
`;

/** Cabeçalho do card — mesmo tom claro do sidebar (vidro + blur). */
export const cardHeaderSurface = css`
    background: var(--hg-card-header);
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
`;

export const cardHeaderBorder = css`
    border-bottom: 1px solid var(--hg-border);
`;

export const cardRowBorder = css`
    border-color: var(--hg-border);
`;

export const cardLabelText = css`
    color: color-mix(in srgb, var(--color-text-muted) 88%, var(--color-white) 12%);
`;

/** Mesma cor de texto do sidebar / header. */
export const navText = css`
    color: var(--hg-nav-text);
`;

/** Tipografia e cor dos itens do sidebar — reutilizada em labels de formulário. */
export const sidebarItemText = css`
    ${navText};
    ${cardTitleFont};
`;

/** Tipografia padrão das listas (backups, users, schedules, etc.). */
export const rowListText = css`
    ${tw`text-sm leading-snug`};
`;

/** Lista compacta (arquivos) — um passo abaixo de text-base, alinhada a text-sm. */
export const rowListTextFiles = css`
    ${tw`text-sm leading-snug`};
`;

/** Texto dos itens de menu dropdown (⋯) — mesma escala da lista de arquivos. */
export const dropdownMenuItemText = css`
    ${rowListTextFiles};
    ${tw`font-semibold`};
    color: color-mix(in srgb, var(--color-white) 94%, var(--hg-nav-text) 6%);
`;

/** Painel flutuante dos menus dropdown — vidro opaco para legibilidade. */
export const dropdownMenuShell = css`
    ${dropdownMenuItemText};
    ${tw`rounded p-1`};
    position: relative;
    isolation: isolate;
    transform: translateZ(0);
    background: color-mix(in srgb, var(--hg-surface) 97%, transparent);
    -webkit-backdrop-filter: blur(10px);
    backdrop-filter: blur(10px);
    border: 1px solid var(--hg-border);
    box-shadow: 0 16px 40px -14px rgba(0, 0, 0, 0.94), inset 0 0 0 1px rgba(255, 255, 255, 0.04);
`;

const dropdownMenuItemHover = (danger?: boolean) => css`
    &:hover {
        ${danger
            ? css`
                  background: color-mix(in srgb, var(--color-danger) 22%, var(--hg-surface) 78%);
                  color: #fca5a5;
              `
            : css`
                  color: var(--color-white);
                  background: color-mix(in srgb, var(--color-divider) 72%, var(--hg-surface) 28%);
              `};
    }
`;

/** Item clicável (div) dentro de dropdown. */
export const dropdownMenuRowStyles = (danger?: boolean, active?: boolean) => css`
    ${dropdownMenuItemText};
    ${tw`px-2 py-1.5 flex items-center rounded cursor-pointer select-none transition-colors duration-150`};

    ${active &&
    css`
        color: var(--color-white);
        font-weight: 700;
        background: color-mix(in srgb, var(--color-primary) 28%, var(--hg-surface) 72%);
    `};

    ${dropdownMenuItemHover(danger)};
`;

/** Item clicável (button) dentro de dropdown. */
export const dropdownMenuButtonRowStyles = (danger?: boolean) => css`
    ${dropdownMenuItemText};
    ${tw`px-2 py-1.5 flex items-center rounded w-full text-left border-0 bg-transparent cursor-pointer transition-colors duration-150`};
    font: inherit;

    ${dropdownMenuItemHover(danger)};
`;

/** Ícone dentro de item de dropdown — proporcional ao text-sm. */
export const dropdownMenuIcon = css`
    ${tw`text-xs flex-shrink-0`};
    width: 0.875rem;
`;

/** Mensagens vazias e informativas nas páginas do servidor. */
export const emptyStateText = css`
    ${navText};
    ${tw`text-sm text-center`};
`;

/** Página atual no breadcrumb. */
export const breadcrumbCurrentText = css`
    color: var(--color-white);
    ${tw`font-semibold`};
`;

export const cardValueText = css`
    ${tw`font-header font-semibold`};
    color: var(--color-white);
`;

export const cardTitleText = css`
    ${cardTitleFont};
    color: color-mix(in srgb, var(--color-text-muted) 88%, var(--color-white) 12%);
`;
