import { css } from 'styled-components/macro';
import tw from 'twin.macro';

/** Mesmo padrão tipográfico dos itens do sidebar (NavItem), em branco. */
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
    border-bottom: 1px solid rgba(45, 45, 58, 0.45);
`;

export const cardRowBorder = css`
    border-color: color-mix(in srgb, var(--color-divider) 45%, transparent);
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
    color: var(--color-white);
`;
