import React from 'react';
import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';
import Spinner from '@/components/elements/Spinner';

interface Props {
    isLoading?: boolean;
    size?: 'xsmall' | 'small' | 'large' | 'xlarge';
    color?: 'green' | 'red' | 'primary' | 'grey';
    isSecondary?: boolean;
}

const hostgamerButtonBase = css`
    position: relative;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    white-space: nowrap;
    gap: var(--btn-gap);
    min-height: var(--btn-min-height);
    padding: var(--btn-padding);
    border-radius: var(--radius-ui);
    font-family: var(--font-family-heading);
    font-size: var(--font-size-btn);
    font-weight: 700;
    line-height: 1;
    letter-spacing: 0;
    text-transform: uppercase;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    transition: transform var(--transition-base), box-shadow var(--transition-base),
        background var(--transition-base), border-color var(--transition-base), color var(--transition-base);
    cursor: pointer;

    &:focus-visible {
        outline: none;
    }
`;

const ButtonStyle = styled.button<Omit<Props, 'isLoading'>>`
    ${hostgamerButtonBase};

    ${(props) =>
        ((!props.isSecondary && !props.color) || props.color === 'primary') &&
        css<Props>`
            ${(props) =>
                !props.isSecondary
                    ? css`
                          background: var(--color-primary);
                          border: 1px solid var(--color-primary);
                          color: var(--color-white);

                          &:hover:not(:disabled),
                          &:focus-visible:not(:disabled) {
                              background: var(--color-primary);
                              border-color: var(--color-primary);
                              color: var(--color-white);
                              box-shadow: var(--hg-btn-glow);
                          }

                          &:focus-visible:not(:disabled) {
                              box-shadow: var(--hg-focus-ring), var(--hg-btn-glow);
                          }
                      `
                    : css`
                          background: transparent;
                          border: 2px solid var(--color-primary);
                          color: var(--color-white);

                          &:hover:not(:disabled),
                          &:focus-visible:not(:disabled) {
                              background: var(--color-primary);
                              border-color: var(--color-primary);
                              color: var(--color-white);
                              box-shadow: var(--hg-btn-glow);
                          }

                          &:focus-visible:not(:disabled) {
                              box-shadow: var(--hg-focus-ring);
                          }
                      `};
        `};

    ${(props) =>
        props.color === 'grey' &&
        css`
            border: 1px solid var(--color-divider);
            background: transparent;
            color: var(--color-white);

            &:hover:not(:disabled) {
                background: color-mix(in srgb, var(--color-divider) 55%, transparent);
                border-color: var(--color-divider);
            }
        `};

    ${(props) =>
        props.color === 'green' &&
        css<Props>`
            border: 1px solid #059669;
            background: #059669;
            color: var(--color-white);

            &:hover:not(:disabled) {
                border-color: #047857;
                background: #047857;
            }
        `};

    ${(props) =>
        props.color === 'red' &&
        css<Props>`
            ${(props) =>
                props.isSecondary
                    ? css`
                          background: transparent;
                          border: 2px solid var(--color-danger);
                          color: var(--color-white);

                          &:hover:not(:disabled),
                          &:focus-visible:not(:disabled) {
                              background: var(--color-danger);
                              border-color: var(--color-danger);
                              color: var(--color-white);
                              box-shadow: var(--hg-btn-danger-glow);
                          }

                          &:focus-visible:not(:disabled) {
                              box-shadow: var(--hg-focus-ring);
                          }
                      `
                    : css`
                          border: 1px solid var(--color-danger);
                          background: var(--color-danger);
                          color: var(--color-white);

                          &:hover:not(:disabled),
                          &:focus-visible:not(:disabled) {
                              border-color: var(--color-danger);
                              background: var(--color-danger);
                              color: var(--color-white);
                              box-shadow: var(--hg-btn-danger-glow);
                          }

                          &:focus-visible:not(:disabled) {
                              box-shadow: var(--hg-focus-ring), var(--hg-btn-danger-glow);
                          }
                      `};
        `};

    ${(props) =>
        props.size === 'xsmall' &&
        css`
            min-height: var(--btn-min-height-sm);
            padding: var(--btn-padding-sm);
            font-size: var(--font-size-btn-sm);
        `};

    ${(props) =>
        props.size === 'large' &&
        css`
            min-height: var(--btn-min-height-md);
            padding: var(--btn-padding-md);
        `};

    ${(props) =>
        props.size === 'xlarge' &&
        css`
            width: 100%;
        `};

    ${(props) =>
        props.isSecondary &&
        props.color === 'grey' &&
        css`
            border: 2px solid var(--color-divider);
            background: transparent;
            color: var(--color-white);

            &:hover:not(:disabled) {
                background: color-mix(in srgb, var(--color-divider) 55%, transparent);
                border-color: var(--color-divider);
            }
        `};

    &:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
`;

type ComponentProps = Omit<JSX.IntrinsicElements['button'], 'ref' | keyof Props> & Props;

const Button: React.FC<ComponentProps> = ({ children, isLoading, ...props }) => (
    <ButtonStyle {...props}>
        {isLoading && (
            <div css={tw`absolute inset-0 flex items-center justify-center bg-black/25 rounded-sm`}>
                <Spinner size={'small'} />
            </div>
        )}
        <span css={isLoading ? tw`opacity-0` : undefined}>{children}</span>
    </ButtonStyle>
);

type ButtonComponent = typeof Button & {
    Sizes: { Small: 'small'; Large: 'large' };
};

export default Button as ButtonComponent;
