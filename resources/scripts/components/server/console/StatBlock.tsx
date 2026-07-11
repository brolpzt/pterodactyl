import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';
import useFitText from 'use-fit-text';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { cardLabelText, cardValueText } from '@/assets/css/cardTheme';
import { glassContentLayer, glassSidebarShell } from '@/assets/css/glassPanel';

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    color?: string | undefined;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
}

const StatShell = styled.div.attrs({ className: 'hg-glass-sidebar' })`
    ${glassSidebarShell};
    ${tw`rounded col-span-3 md:col-span-2 lg:col-span-6`};
    box-shadow: inset 0 0 0 1px rgba(45, 45, 58, 0.28);
`;

const StatInner = styled.div`
    ${glassContentLayer};
    ${tw`relative z-10 flex-row items-center px-3 py-2 md:p-3 lg:p-4`};
`;

const StatusBar = styled.div`
    ${tw`w-1 h-full absolute left-0 top-0 rounded-l sm:hidden`};
`;

const IconBox = styled.div<{ $hasAccent?: boolean }>`
    ${tw`hidden flex-shrink-0 items-center justify-center rounded-lg w-12 h-12 sm:flex sm:mr-4 transition-colors duration-500`};
    ${(props) =>
        !props.$hasAccent &&
        css`
            background: transparent;
            border: 1px solid rgba(45, 45, 58, 0.45);
        `};
`;

const StatTitle = styled.p`
    ${cardLabelText};
    ${tw`font-header font-medium leading-tight text-xs md:text-sm m-0`};
`;

const StatValue = styled.div`
    ${cardValueText};
    ${tw`h-[1.75rem] w-full truncate`};
`;

export default ({ title, copyOnClick, icon, color, className, children }: StatBlockProps) => {
    const { fontSize, ref } = useFitText({ minFontSize: 8, maxFontSize: 500 });

    return (
        <CopyOnClick text={copyOnClick}>
            <StatShell className={className}>
                <StatInner>
                    <StatusBar className={classNames(color || 'bg-primary-500')} />
                    <IconBox className={classNames(color)} $hasAccent={!!color}>
                        <Icon
                            icon={icon}
                            className={classNames('w-6 h-6 m-auto', {
                                'text-gray-100': !color || color === 'bg-gray-700',
                                'text-gray-50': color && color !== 'bg-gray-700',
                            })}
                        />
                    </IconBox>
                    <div className={'flex flex-col justify-center overflow-hidden w-full'}>
                        <StatTitle>{title}</StatTitle>
                        <StatValue ref={ref} style={{ fontSize }}>
                            {children}
                        </StatValue>
                    </div>
                </StatInner>
            </StatShell>
        </CopyOnClick>
    );
};
