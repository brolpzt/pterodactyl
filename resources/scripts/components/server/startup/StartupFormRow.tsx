import React from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { cardLabelText, cardRowBorder } from '@/assets/css/cardTheme';
import { fieldLabel } from '@/assets/css/formTheme';

interface Props {
    label: React.ReactNode;
    description?: string;
    htmlFor?: string;
    className?: string;
    children: React.ReactNode;
}

const Row = styled.div`
    ${tw`grid gap-x-6 gap-y-2 py-4 items-start`};
    grid-template-columns: minmax(9rem, 13rem) minmax(0, 1fr);
    ${cardRowBorder};
    border-bottom-width: 1px;

    &:last-child {
        border-bottom-width: 0;
    }

    @media (max-width: 639px) {
        grid-template-columns: minmax(0, 1fr);
    }
`;

const LabelColumn = styled.div`
    ${tw`pt-2`};
`;

const LabelText = styled.div`
    ${fieldLabel};
    ${tw`mb-0`};
`;

const FieldColumn = styled.div`
    ${tw`min-w-0 w-full`};
`;

const Description = styled.p`
    ${cardLabelText};
    ${tw`mt-2 text-xs leading-relaxed m-0`};
`;

export default ({ label, description, htmlFor, className, children }: Props) => (
    <Row className={className}>
        <LabelColumn>
            {htmlFor ? (
                <LabelText as={'label'} htmlFor={htmlFor}>
                    {label}
                </LabelText>
            ) : (
                <LabelText>{label}</LabelText>
            )}
        </LabelColumn>
        <FieldColumn>
            {children}
            {description ? <Description>{description}</Description> : null}
        </FieldColumn>
    </Row>
);
