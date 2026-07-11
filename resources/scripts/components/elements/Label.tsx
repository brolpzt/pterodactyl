import styled from 'styled-components/macro';
import { fieldLabel, fieldLabelLight } from '@/assets/css/formTheme';

const Label = styled.label.attrs({ className: 'field-label' })<{ isLight?: boolean }>`
    ${fieldLabel};
    ${(props) => props.isLight && fieldLabelLight};
`;

export default Label;
