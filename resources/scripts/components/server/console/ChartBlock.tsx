import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { cardLabelText } from '@/assets/css/cardTheme';
import { glassContentLayer, glassHeaderShell } from '@/assets/css/glassPanel';

interface ChartBlockProps {
    title: string;
    legend?: React.ReactNode;
    children: React.ReactNode;
}

const ChartShell = styled.div.attrs({ className: 'hg-glass-header' })`
    ${glassHeaderShell};
    ${tw`rounded overflow-hidden`};
    box-shadow: inset 0 0 0 1px rgba(45, 45, 58, 0.28);
`;

const ChartInner = styled.div`
    ${glassContentLayer};
`;

const ChartHeader = styled.div`
    ${tw`relative z-10 flex items-center justify-between px-4 py-2`};
`;

const ChartTitle = styled.h3`
    ${cardLabelText};
    ${tw`font-header font-medium m-0 transition-colors duration-100`};
`;

const ChartBody = styled.div`
    ${tw`relative z-10 ml-2`};
`;

export default ({ title, legend, children }: ChartBlockProps) => (
    <ChartShell className={'group'}>
        <ChartInner>
            <ChartHeader>
                <ChartTitle>{title}</ChartTitle>
                {legend && <p css={tw`text-sm flex items-center m-0`}>{legend}</p>}
            </ChartHeader>
            <ChartBody>{children}</ChartBody>
        </ChartInner>
    </ChartShell>
);
