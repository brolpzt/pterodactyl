import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import isEqual from 'react-fast-compare';
import { cardHeaderBorder, cardHeaderSurface, cardSurface, cardTitleText } from '@/assets/css/cardTheme';
import { glassCardShell, glassContentLayer } from '@/assets/css/glassPanel';
import { hgRadiusSm } from '@/assets/css/borderTheme';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const CardShell = styled.div.attrs({ className: 'hg-glass-card' })`
    ${glassCardShell};
    ${hgRadiusSm};
`;

const CardInner = styled.div`
    ${glassContentLayer};
`;

const CardHeader = styled.div.attrs({ className: 'hg-card-header' })`
    ${cardHeaderSurface};
    ${cardHeaderBorder};
    ${tw`relative z-10 py-2.5 px-5`};
`;

const CardBody = styled.div`
    ${cardSurface};
    ${tw`relative z-10 p-3`};
`;

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <CardShell className={className}>
        <CardInner>
            <CardHeader>
                {typeof title === 'string' ? (
                    <p css={[cardTitleText, tw`flex items-center m-0`]}>
                        {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2`} />}
                        {title}
                    </p>
                ) : (
                    <div css={[cardTitleText, tw`flex items-center`]}>{title}</div>
                )}
            </CardHeader>
            <CardBody>{children}</CardBody>
        </CardInner>
    </CardShell>
);

export default memo(TitledGreyBox, isEqual);
