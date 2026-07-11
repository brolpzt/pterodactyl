import styled from 'styled-components/macro';
import { pageFallbackBackground } from '@/assets/css/pageBackground';

const PageFallbackBackground = styled.div`
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    ${pageFallbackBackground};
`;

export default PageFallbackBackground;
