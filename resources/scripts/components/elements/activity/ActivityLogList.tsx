import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { cardLabelText, cardSurface } from '@/assets/css/cardTheme';
import { glassContentLayer, glassSidebarShell } from '@/assets/css/glassPanel';

const ListShell = styled.div.attrs({ className: 'hg-glass-sidebar' })`
    ${glassSidebarShell};
    ${tw`rounded overflow-hidden`};
    box-shadow: inset 0 0 0 1px rgba(45, 45, 58, 0.28);
`;

const ListInner = styled.div`
    ${glassContentLayer};
    ${cardSurface};
    ${tw`relative z-10`};
`;

interface Props {
    children: React.ReactNode;
    className?: string;
}

/** Lista de activity — mesmo vidro dos cards / sidebar. */
export default ({ children, className }: Props) => (
    <ListShell className={className}>
        <ListInner>{children}</ListInner>
    </ListShell>
);
