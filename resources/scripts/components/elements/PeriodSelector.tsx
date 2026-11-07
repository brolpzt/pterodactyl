import React from 'react';
import tw from 'twin.macro';
import styled from 'styled-components/macro';

export type PeriodValue = '15m' | '1h' | '6h' | '24h' | '7d' | '30d' | '90d';

export interface PeriodOption {
    value: PeriodValue;
    label: string;
}

const Wrap = styled.div`
    ${tw`inline-flex flex-wrap items-center gap-1.5 p-1 rounded`};
    background: color-mix(in srgb, var(--hg-card-surface) 72%, transparent);
    box-shadow: inset 0 0 0 1px rgba(45, 45, 58, 0.28);
`;

const Chip = styled.button<{ $active?: boolean }>`
    ${tw`px-2.5 py-1 text-xs font-medium rounded transition`};
    color: ${(p) => (p.$active ? 'var(--color-white)' : 'var(--hg-nav-text)')};
    background: ${(p) =>
        p.$active
            ? 'var(--color-primary-500)'
            : 'color-mix(in srgb, var(--hg-card-surface) 92%, var(--color-white) 8%)'};
    box-shadow: ${(p) =>
        p.$active ? 'none' : 'inset 0 0 0 1px color-mix(in srgb, var(--hg-border) 80%, transparent)'};

    &:hover {
        color: var(--color-white);
        background: ${(p) =>
            p.$active
                ? 'var(--color-primary-500)'
                : 'color-mix(in srgb, var(--hg-card-header) 88%, var(--color-white) 12%)'};
    }
`;

interface Props {
    value: PeriodValue;
    options: PeriodOption[];
    onChange: (value: PeriodValue) => void;
}

export default ({ value, options, onChange }: Props) => (
    <Wrap role={'group'} aria-label={'Período'}>
        {options.map((option) => (
            <Chip
                key={option.value}
                type={'button'}
                $active={option.value === value}
                onClick={() => onChange(option.value)}
            >
                {option.label}
            </Chip>
        ))}
    </Wrap>
);
