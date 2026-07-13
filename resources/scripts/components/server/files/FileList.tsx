import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { cardSurface } from '@/assets/css/cardTheme';
import { hgBorder, hgRadiusSm } from '@/assets/css/borderTheme';
import { glassContentLayer, glassSidebarShell } from '@/assets/css/glassPanel';

const ListShell = styled.div.attrs({ className: 'hg-glass-sidebar' })`
    ${glassSidebarShell};
    ${hgRadiusSm};
    ${hgBorder};
    ${tw`overflow-hidden`};
    box-shadow: none;
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

export default ({ children, className }: Props) => (
    <ListShell className={className}>
        <ListInner>{children}</ListInner>
    </ListShell>
);
