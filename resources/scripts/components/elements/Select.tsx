import styled, { css } from 'styled-components/macro';
import { fieldControl, fieldControlError, fieldSelectArrow } from '@/assets/css/formTheme';

interface Props {
    hideDropdownArrow?: boolean;
    hasError?: boolean;
}

const Select = styled.select.attrs({ className: 'select-base' })<Props>`
    ${fieldControl};
    ${(props) => !props.hideDropdownArrow && fieldSelectArrow};
    ${(props) => props.hasError && fieldControlError};

    -webkit-appearance: none;
    -moz-appearance: none;

    &::-ms-expand {
        display: none;
    }
`;

export default Select;
