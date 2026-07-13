import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { motionDurations } from '@/assets/css/motionTheme';
import { sidebarMainMargin } from '@/lib/sidebarLayout';

/** Área principal com margem responsiva para o sidebar fixo. */
export const MainContent = styled.div<{ $collapsed: boolean }>`
    ${tw`flex-1 min-w-0 transition-[margin]`};
    transition-duration: ${motionDurations.layout}ms;
    ${(props) => sidebarMainMargin(props.$collapsed)};
`;
