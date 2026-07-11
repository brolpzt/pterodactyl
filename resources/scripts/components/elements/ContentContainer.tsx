import styled from 'styled-components/macro';
import tw from 'twin.macro';

const ContentContainer = styled.div`
    width: 100%;
    max-width: 1200px;
    ${tw`mx-auto px-4 sm:px-6 lg:px-8`};
`;
ContentContainer.displayName = 'ContentContainer';

export default ContentContainer;
