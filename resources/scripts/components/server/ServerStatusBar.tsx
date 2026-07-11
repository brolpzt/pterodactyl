import React, { useEffect, useState } from 'react';
import tw, { css } from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCopy } from '@fortawesome/free-solid-svg-icons';
import ContentContainer from '@/components/elements/ContentContainer';
import PowerButtons from '@/components/server/console/PowerButtons';
import Fade from '@/components/elements/Fade';
import FlagIcon from '@/components/elements/FlagIcon';
import { glassHeaderInner, glassStickyBarShell } from '@/assets/css/glassPanel';
import { cardLabelText, navText } from '@/assets/css/cardTheme';

interface Props {
    name?: string;
    id?: string;
    allocation?: { ip: string; port: number; alias?: string | null };
    locationName?: string | null;
    onCopyText: (text: string, label: string) => void;
    formatIp: (value: string) => string;
}

const stickyShellStyles = (scrolled: boolean) => css`
    ${glassStickyBarShell};
    ${tw`sticky top-[3.5rem] z-30 w-full transition-[box-shadow] duration-300`};
    box-shadow: ${scrolled ? 'var(--hg-subheader-shadow-scrolled)' : 'var(--hg-subheader-shadow)'};

    &::before {
        opacity: 1;
    }
`;

const barInnerStyles = (scrolled: boolean) => css`
    ${glassHeaderInner};
    overflow: visible;
    transition: padding 300ms ease;
    padding-top: ${scrolled ? '0.5rem' : '0.75rem'};
    padding-bottom: ${scrolled ? '0.5rem' : '0.75rem'};

    @media (min-width: 640px) {
        padding-top: ${scrolled ? '0.625rem' : '1rem'};
        padding-bottom: ${scrolled ? '0.625rem' : '1rem'};
    }
`;

const titleStyles = (scrolled: boolean) => css`
    ${tw`text-white font-header font-semibold uppercase whitespace-nowrap truncate m-0 transition-all duration-300 max-w-[140px] sm:max-w-[280px]`};
    font-size: ${scrolled ? '1rem' : '1.125rem'};

    @media (min-width: 640px) {
        font-size: ${scrolled ? '1.125rem' : '1.25rem'};
    }
`;

const metaStyles = (scrolled: boolean) => css`
    ${navText};
    ${tw`flex items-center whitespace-nowrap m-0 transition-all duration-300`};
    font-size: ${scrolled ? '12px' : '13px'};
    margin-top: ${scrolled ? '0.125rem' : '0.125rem'};
`;

const ServerStatusBar = ({
    name,
    id,
    allocation,
    locationName,
    onCopyText,
    formatIp,
}: Props) => {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 12);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const address = allocation ? `${allocation.alias || formatIp(allocation.ip)}:${allocation.port}` : 'n/a';

    return (
        <div className="hg-glass-server-bar" css={stickyShellStyles(scrolled)}>
            <Fade timeout={150} in appear>
                <div css={barInnerStyles(scrolled)}>
                    <ContentContainer css={tw`w-full`}>
                        <div tw="flex w-full flex-nowrap items-center justify-start overflow-visible">
                            <div tw="flex items-center flex-shrink-0 mr-4 sm:mr-6 pr-4 sm:pr-6 border-r border-neutral-700/40 min-w-0">
                                <div tw="min-w-0">
                                    <h1 css={titleStyles(scrolled)}>
                                        {name}
                                    </h1>
                                    <p css={metaStyles(scrolled)}>
                                        <span
                                            css={tw`font-mono bg-[var(--hg-primary)] text-white px-1.5 py-0.5 rounded mr-2 text-[12px] flex-shrink-0 font-semibold`}
                                        >
                                            {id}
                                        </span>
                                        <span
                                            css={[navText, tw`cursor-pointer hover:text-white transition-colors duration-150 flex items-center flex-shrink-0`]}
                                            onClick={() => onCopyText(address, 'IP')}
                                            title="Clique para copiar"
                                        >
                                            {address}
                                            <FontAwesomeIcon icon={faCopy} css={[navText, tw`ml-1.5 text-[10px] opacity-60`]} />
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div tw="hidden md:flex items-center gap-6 lg:gap-8 flex-shrink-0 overflow-hidden">
                                <div tw="flex flex-col flex-shrink-0">
                                    <span css={[cardLabelText, tw`text-[10px] uppercase font-bold tracking-wider whitespace-nowrap`]}>
                                        Localização
                                    </span>
                                    <span css={[navText, tw`text-[13px] flex items-center whitespace-nowrap`]}>
                                        <FlagIcon
                                            code={locationName?.split(' ')[0]}
                                            alt={locationName || 'Location'}
                                            size="sm"
                                            className="mr-1.5"
                                        />
                                        <span>{locationName || 'n/a'}</span>
                                    </span>
                                </div>
                            </div>

                            <div tw="flex items-center ml-auto flex-shrink-0 pl-4 overflow-visible py-1">
                                <PowerButtons />
                            </div>
                        </div>
                    </ContentContainer>
                </div>
            </Fade>
        </div>
    );
};

export default ServerStatusBar;
