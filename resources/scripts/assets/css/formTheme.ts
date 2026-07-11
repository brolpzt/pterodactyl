import { css } from 'styled-components/macro';

/** Espelha hostgamer.net/login — .field-group / .field-label / .input-base */
export const fieldGroup = css`
    display: grid;
    gap: 0;
`;

export const fieldLabel = css`
    display: inline-block;
    margin-bottom: 0.4rem;
    font-family: var(--font-family-heading, 'Oxanium', Arial, Helvetica, sans-serif);
    font-size: 0.86rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-label);
`;

export const fieldLabelLight = css`
    color: var(--color-text-muted);
`;

const fieldInteraction = css`
    border-color: var(--color-primary) !important;
    box-shadow: var(--field-glow) !important;
    outline: none;
`;

const fieldInteractionError = css`
    border-color: var(--color-danger) !important;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-danger) 24%, transparent) !important;
    outline: none;
`;

export const fieldControlBase = css`
    appearance: none;
    width: 100%;
    min-width: 0;
    border: 1px solid var(--field-border);
    border-radius: var(--radius-ui);
    background-color: var(--field-bg);
    color: var(--color-white);
    font-family: inherit;
    font-size: 0.92rem;
    font-weight: 600;
    line-height: 1.35;
    padding: 0.58rem 0.75rem;
    box-shadow: none;
    transition: border-color var(--transition-base), box-shadow var(--transition-base),
        background-color var(--transition-base);

    &::placeholder {
        color: var(--color-input-placeholder);
    }

    &:-webkit-autofill,
    &:-webkit-autofill:hover {
        -webkit-text-fill-color: var(--color-white);
        caret-color: var(--color-white);
        -webkit-box-shadow: 0 0 0 1000px var(--field-bg) inset;
        transition: background-color 99999s ease-out;
    }

    &:-webkit-autofill:focus-visible {
        -webkit-text-fill-color: var(--color-white);
        caret-color: var(--color-white);
        -webkit-box-shadow: 0 0 0 1000px var(--field-bg) inset;
        transition: background-color 99999s ease-out;
    }

    &:hover:not(:disabled):not(:read-only) {
        ${fieldInteraction};
    }

    &:focus-visible:not(:disabled):not(:read-only) {
        ${fieldInteraction};
    }

    &:disabled {
        opacity: 0.75;
        cursor: not-allowed;
    }

    &:read-only {
        cursor: default;
    }
`;

export const fieldControl = css`
    ${fieldControlBase};
    outline: none;
    resize: none;
`;

export const fieldTextarea = css`
    ${fieldControlBase};
    outline: none;
    resize: vertical;
    min-height: 5.6rem;
`;

export const fieldSelectArrow = css`
    padding-right: 2rem;
    background-image: linear-gradient(45deg, transparent 50%, var(--color-primary) 50%),
        linear-gradient(135deg, var(--color-primary) 50%, transparent 50%);
    background-position: calc(100% - 18px) calc(50% - 2px), calc(100% - 12px) calc(50% - 2px);
    background-repeat: no-repeat;
    background-size: 6px 6px, 6px 6px;
`;

export const fieldControlLight = css`
    background-color: #fff;
    border-color: color-mix(in srgb, var(--color-divider) 55%, #fff);
    color: var(--color-bg);

    &::placeholder {
        color: color-mix(in srgb, var(--color-text-muted) 72%, transparent);
    }

    &:hover:not(:disabled):not(:read-only),
    &:focus-visible:not(:disabled):not(:read-only) {
        border-color: var(--color-primary) !important;
        box-shadow: var(--field-glow) !important;
    }
`;

export const fieldControlError = css`
    border-color: var(--color-danger) !important;
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--color-danger) 32%, transparent) !important;

    &:hover:not(:disabled):not(:read-only),
    &:focus-visible:not(:disabled):not(:read-only) {
        ${fieldInteractionError};
    }
`;

export const fieldHintText = css`
    color: color-mix(in srgb, var(--color-text-muted) 88%, transparent);
`;

export const fieldHint = css`
    margin-top: 0.45rem;
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1.45;
    letter-spacing: 0;
    ${fieldHintText};
`;

export const fieldErrorText = css`
    margin-top: 0.45rem;
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1.45;
    color: var(--color-danger);
`;
