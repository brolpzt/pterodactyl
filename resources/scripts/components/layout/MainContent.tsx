import styled from 'styled-components/macro';
import tw from 'twin.macro';
import { sidebarMainMargin } from '@/lib/sidebarLayout';

/** Área principal com margem responsiva para o sidebar fixo. */
export const MainContent = styled.div<{ $collapsed: boolean }>`
    ${tw`flex-1 min-w-0 transition-[margin] duration-300`};
    ${(props) => sidebarMainMargin(props.$collapsed)};
`;
