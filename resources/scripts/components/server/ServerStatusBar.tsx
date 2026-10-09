import React, { useEffect, useRef, useState } from 'react';
import tw, { css } from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCopy } from '@fortawesome/free-solid-svg-icons';
import ContentContainer from '@/components/elements/ContentContainer';
import PowerButtons from '@/components/server/console/PowerButtons';
import Fade from '@/components/elements/Fade';
import FlagIcon from '@/components/elements/FlagIcon';
import { motionDurations } from '@/assets/css/motionTheme';
import { glassHeaderInner, glassStickyBarShell } from '@/assets/css/glassPanel';
import { cardLabelText, navText } from '@/assets/css/cardTheme';
import { hideNativeScrollbar } from '@/assets/css/scrollTheme';
import { ServerContext } from '@/state/server';
import useGameQuery from '@/api/swr/getGameQuery';
import { supportsGameQuery } from '@/lib/supportsGameQuery';
import { stripGameHostnameColors } from '@/lib/stripGameHostnameColors';

interface Props {
    name?: string;
    id?: string;
    allocation?: { ip: string; port: number; alias?: string | null };
    locationName?: string | null;
    onCopyText: (text: string, label: string) => void;
    formatIp: (value: string) => string;
}

const stickyShellStyles = css`
    ${glassStickyBarShell};
    ${tw`sticky top-[3.5rem] z-30 w-full`};

    &::before {
        opacity: 1;
    }
`;

const barInnerStyles = (scrolled: boolean) => css`
    ${glassHeaderInner};
    overflow: hidden;
    transition: padding ${motionDurations.layout}ms ease;
    padding-top: ${scrolled ? '0.5rem' : '0.625rem'};
    padding-bottom: ${scrolled ? '0.5rem' : '0.625rem'};

    @media (min-width: 640px) {
        padding-top: ${scrolled ? '0.625rem' : '1rem'};
        padding-bottom: ${scrolled ? '0.625rem' : '1rem'};
    }
`;

/** Limita a largura do nome; o fade/glow só activa quando há overflow. */
const titleClampOuterStyles = css`
    ${tw`relative min-w-0 max-w-full`};
    width: fit-content;
    max-width: min(100%, 16rem);

    @media (min-width: 640px) {
        max-width: min(100%, 22rem);
    }

    @media (min-width: 1280px) {
        max-width: min(100%, 28rem);
    }
`;

const titleClampInnerStyles = (fading: boolean) => css`
    ${tw`min-w-0 max-w-full overflow-hidden`};

    ${fading &&
    css`
        -webkit-mask-image: linear-gradient(90deg, #000 0%, #000 calc(100% - 2.75rem), transparent 100%);
        mask-image: linear-gradient(90deg, #000 0%, #000 calc(100% - 2.75rem), transparent 100%);
    `};
`;

const titleFadeGlowStyles = css`
    pointer-events: none;
    position: absolute;
    top: -0.15rem;
    right: -0.35rem;
    bottom: -0.15rem;
    width: 3rem;
    background: linear-gradient(
        90deg,
        transparent 0%,
        color-mix(in srgb, var(--hg-primary) 22%, transparent) 45%,
        color-mix(in srgb, var(--hg-primary) 55%, transparent) 100%
    );
    filter: blur(4px);
    opacity: 0.9;
`;

const titleStyles = (scrolled: boolean, fading: boolean) => css`
    ${tw`text-white font-header font-semibold uppercase m-0 transition-all duration-200 min-w-0 whitespace-nowrap`};
    font-size: ${scrolled ? '0.9375rem' : '1rem'};
    ${fading &&
    css`
        text-shadow: 0 0 14px color-mix(in srgb, var(--hg-primary) 45%, transparent),
            0 0 28px color-mix(in srgb, var(--hg-primary) 22%, transparent);
    `};

    @media (min-width: 640px) {
        font-size: ${scrolled ? '1.0625rem' : '1.125rem'};
    }

    @media (min-width: 1280px) {
        font-size: ${scrolled ? '1.125rem' : '1.25rem'};
    }
`;

const metaStyles = (scrolled: boolean) => css`
    ${navText};
    ${tw`flex flex-wrap items-center gap-x-2 gap-y-1 m-0 transition-all duration-200 min-w-0`};
    font-size: ${scrolled ? '12px' : '13px'};
`;

const mobilePowerButtons = css`
    & .button {
        min-height: 2.25rem;
        padding: 0 0.65rem;
        font-size: 0.75rem;
    }

    @media (min-width: 640px) {
        & .button {
            min-height: var(--btn-min-height);
            padding: var(--btn-padding);
            font-size: var(--font-size-btn);
        }
    }
`;

const queryStatLabel = css`
    ${cardLabelText};
    ${tw`text-[10px] uppercase font-bold tracking-wider whitespace-nowrap`};
`;

const queryStatValue = css`
    ${navText};
    ${tw`text-[13px] whitespace-nowrap truncate max-w-[8rem] sm:max-w-[10rem] xl:max-w-[12rem]`};
`;

const statsRowStyles = css`
    ${tw`flex flex-wrap items-start gap-x-5 gap-y-2 min-w-0 w-full`};

    @media (min-width: 768px) and (max-width: 1279px) {
        ${tw`flex-nowrap overflow-x-auto pb-0.5`};
        ${hideNativeScrollbar};
    }
`;

const QueryStat = ({ label, value, title }: { label: string; value: string; title?: string }) => (
    <div tw="flex flex-col flex-shrink-0 min-w-0">
        <span css={queryStatLabel}>{label}</span>
        <span css={queryStatValue} title={title || value}>
            {value}
        </span>
    </div>
);

const ServerStatusBar = ({
    name,
    id,
    allocation,
    locationName,
    onCopyText,
    formatIp,
}: Props) => {
    const [scrolled, setScrolled] = useState(false);
    const [nameFading, setNameFading] = useState(false);
    const titleClampRef = useRef<HTMLDivElement>(null);
    const gamedig = ServerContext.useStoreState((state) => state.server.data?.gamedig);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const queryEnabled = supportsGameQuery(gamedig, eggId);
    const { data: query } = useGameQuery(queryEnabled);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 12);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    useEffect(() => {
        const el = titleClampRef.current;
        if (!el) {
            return;
        }

        const checkOverflow = () => {
            setNameFading(el.scrollWidth > el.clientWidth + 1);
        };

        checkOverflow();
        const observer = new ResizeObserver(checkOverflow);
        observer.observe(el);
        return () => observer.disconnect();
    }, [name, scrolled]);

    const address = allocation ? `${allocation.alias || formatIp(allocation.ip)}:${allocation.port}` : 'n/a';
    const rawQueryHostname = query?.online ? query.hostname : null;
    const queryHostname = query?.online
        ? stripGameHostnameColors(rawQueryHostname) || '—'
        : query
          ? 'Offline'
          : '—';
    const queryMap = query?.online ? query.map || '—' : '—';
    const queryPlayers =
        query && query.online ? `${query.players}/${query.max_players || '?'}` : query ? '0/0' : '—';

    const showStatsRow = Boolean(locationName) || queryEnabled;

    return (
        <div className="hg-glass-server-bar" css={stickyShellStyles}>
            <Fade in appear>
                <div css={barInnerStyles(scrolled)}>
                    <ContentContainer css={tw`w-full min-w-0`}>
                        <div tw="flex w-full min-w-0 flex-col gap-3 xl:flex-row xl:items-center xl:gap-6">
                            <div tw="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:justify-between xl:contents">
                                <div tw="min-w-0 xl:max-w-[min(28rem,36%)] xl:flex-shrink xl:pr-6 xl:border-r xl:border-neutral-700/40">
                                    <div css={titleClampOuterStyles} title={name || undefined}>
                                        <div ref={titleClampRef} css={titleClampInnerStyles(nameFading)}>
                                            <h1 css={titleStyles(scrolled, nameFading)}>{name}</h1>
                                        </div>
                                        {nameFading && <span css={titleFadeGlowStyles} aria-hidden />}
                                    </div>
                                    <p css={metaStyles(scrolled)}>
                                        <span
                                            css={tw`font-mono bg-[var(--hg-primary)] text-white px-1.5 py-0.5 rounded text-[11px] sm:text-[12px] flex-shrink-0 font-semibold`}
                                        >
                                            {id}
                                        </span>
                                        <span
                                            css={[
                                                navText,
                                                tw`cursor-pointer hover:text-white transition-colors duration-150 flex items-center min-w-0 truncate max-w-full sm:max-w-[14rem] xl:max-w-none`,
                                            ]}
                                            onClick={() => onCopyText(address, 'IP')}
                                            title="Clique para copiar"
                                        >
                                            {address}
                                            <FontAwesomeIcon
                                                icon={faCopy}
                                                css={[navText, tw`ml-1.5 text-[10px] opacity-60 flex-shrink-0`]}
                                            />
                                        </span>
                                    </p>
                                </div>

                                <div tw="flex flex-shrink-0 items-center w-full sm:w-auto xl:hidden overflow-visible py-0.5">
                                    <PowerButtons css={[tw`w-full sm:w-auto`, mobilePowerButtons]} />
                                </div>
                            </div>

                            {showStatsRow && (
                                <div css={[statsRowStyles, tw`xl:flex-1 xl:min-w-0`]}>
                                    {locationName && (
                                        <div tw="flex flex-col flex-shrink-0 min-w-0">
                                            <span
                                                css={[
                                                    cardLabelText,
                                                    tw`text-[10px] uppercase font-bold tracking-wider whitespace-nowrap`,
                                                ]}
                                            >
                                                Localização
                                            </span>
                                            <span css={[navText, tw`text-[13px] flex items-center whitespace-nowrap`]}>
                                                <FlagIcon
                                                    code={locationName.split(' ')[0]}
                                                    alt={locationName}
                                                    size="sm"
                                                    className="mr-1.5"
                                                />
                                                <span tw="truncate max-w-[8rem] sm:max-w-none">{locationName}</span>
                                            </span>
                                        </div>
                                    )}

                                    {queryEnabled && (
                                        <>
                                            <QueryStat
                                                label="Hostname"
                                                value={queryHostname}
                                                title={queryHostname !== '—' ? queryHostname : undefined}
                                            />
                                            <QueryStat label="Mapa" value={queryMap} title={query?.map || undefined} />
                                            <QueryStat label="Jogadores" value={queryPlayers} />
                                        </>
                                    )}
                                </div>
                            )}

                            <div tw="hidden xl:flex flex-shrink-0 items-center overflow-visible py-0.5">
                                <PowerButtons css={mobilePowerButtons} />
                            </div>
                        </div>
                    </ContentContainer>
                </div>
            </Fade>
        </div>
    );
};

export default ServerStatusBar;
