import styled, { css } from 'styled-components/macro';
import tw from 'twin.macro';
import {
    fieldControl,
    fieldControlError,
    fieldControlLight,
    fieldHintText,
    fieldTextarea,
} from '@/assets/css/formTheme';

export interface Props {
    isLight?: boolean;
    hasError?: boolean;
}

const fieldInteraction = css<Props>`
    border-color: ${(props) => (props.hasError ? 'var(--color-danger)' : 'var(--color-primary)')} !important;
    box-shadow: ${(props) =>
        props.hasError
            ? '0 0 0 3px color-mix(in srgb, var(--color-danger) 24%, transparent)'
            : 'var(--field-glow)'} !important;
    outline: none;
`;

const light = css<Props>`
    ${fieldControlLight};
`;

const checkboxStyle = css<Props>`
    ${tw`cursor-pointer appearance-none inline-block align-middle select-none flex-shrink-0 w-4 h-4 text-primary-500 rounded-sm`};
    border: 1px solid var(--field-border);
    background-color: var(--field-bg);
    color-adjust: exact;
    background-origin: border-box;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);

    &:checked {
        ${tw`border-transparent bg-no-repeat bg-center`};
        background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 16 16' fill='white' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M5.707 7.293a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4a1 1 0 0 0-1.414-1.414L7 8.586 5.707 7.293z'/%3e%3c/svg%3e");
        background-color: currentColor;
        background-size: 100% 100%;
    }

    &:focus-visible {
        ${fieldInteraction};
    }
`;

const inputStyle = css<Props>`
    ${fieldControl};

    & + .input-help {
        ${tw`mt-2 text-sm font-semibold`};
        ${(props) => (props.hasError ? tw`text-red-400` : fieldHintText)};
    }

    &:required,
    &:invalid {
        box-shadow: none;
    }

    ${(props) => props.isLight && light};
    ${(props) => props.hasError && fieldControlError};
`;

const Input = styled.input.attrs<Props>(({ type }) => ({
    className: type === 'checkbox' || type === 'radio' ? undefined : 'input-base',
}))<Props>`
    &:not([type='checkbox']):not([type='radio']) {
        ${inputStyle};
    }

    &[type='checkbox'],
    &[type='radio'] {
        ${checkboxStyle};

        &[type='radio'] {
            ${tw`rounded-full`};
        }
    }
`;

const Textarea = styled.textarea.attrs({ className: 'textarea-base' })<Props>`
    ${fieldTextarea};
    ${(props) => props.isLight && light};
    ${(props) => props.hasError && fieldControlError};

    & + .input-help {
        ${tw`mt-2 text-sm font-semibold`};
        ${(props) => (props.hasError ? tw`text-red-400` : fieldHintText)};
    }
`;

export { Textarea };
export default Input;
