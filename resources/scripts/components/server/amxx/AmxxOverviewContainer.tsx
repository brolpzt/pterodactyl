import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { cardLabelText, cardValueText, emptyStateText } from '@/assets/css/cardTheme';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import getOverview from '@/api/server/amxx/getOverview';
import { AmxxOverview } from '@/api/server/amxx/types';

const StatusBadge = ({ ok, label }: { ok: boolean; label: string }) => (
    <span
        css={[
            tw`inline-flex items-center px-2 py-1 rounded text-xs font-medium`,
            ok ? tw`bg-green-500/20 text-green-300` : tw`bg-yellow-500/20 text-yellow-300`,
        ]}
    >
        {label}: {ok ? 'Encontrado' : 'Não encontrado'}
    </span>
);

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [data, setData] = useState<AmxxOverview | null>(null);
    const [error, setError] = useState<any>(null);
    const [loading, setLoading] = useState(true);
    const { clearAndAddHttpError } = useFlash();

    useEffect(() => {
        setLoading(true);
        setError(null);
        getOverview(uuid)
            .then(setData)
            .catch((err) => {
                setError(err);
                clearAndAddHttpError({ key: 'amxx:overview', error: err });
            })
            .finally(() => setLoading(false));
    }, [uuid]);

    if (loading) {
        return (
            <ServerContentBlock title={'AMXX Web Admin'}>
                <Spinner size={Spinner.Size.LARGE} centered />
            </ServerContentBlock>
        );
    }

    if (error) {
        return (
            <ServerContentBlock title={'AMXX Web Admin'}>
                <ServerError title={'Erro ao carregar AMXX'} message={httpErrorToHuman(error)} />
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'AMXX Web Admin'}>
            <FlashMessageRender byKey={'amxx:overview'} css={tw`mb-4`} />

            <TitledGreyBox title={'Estado do AMXX'} css={tw`mb-6`}>
                <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                    <div>
                        <p css={cardLabelText}>Diretório do jogo</p>
                        <p css={[cardValueText, tw`font-mono text-sm`]}>{data?.game_directory || 'cstrike'}</p>
                    </div>
                    <div css={tw`flex flex-wrap gap-2`}>
                        <StatusBadge ok={!!data?.users_ini_exists} label={'users.ini'} />
                        <StatusBadge ok={!!data?.banned_cfg_exists} label={'banned.cfg'} />
                        <StatusBadge ok={!!data?.listip_cfg_exists} label={'listip.cfg'} />
                    </div>
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Funcionalidades disponíveis'}>
                <ul css={[cardValueText, tw`space-y-2 text-sm list-disc list-inside`]}>
                    <li>
                        <strong>Admins</strong> — criar e gerir administradores no <code>users.ini</code>
                    </li>
                    <li>
                        <strong>Bans</strong> — gerir bans SteamID e IP via <code>banned.cfg</code> e <code>listip.cfg</code>
                    </li>
                    <li>
                        <strong>Stats/Rank</strong> — previsto para a segunda fase
                    </li>
                </ul>
                {!data?.users_ini_exists && (
                    <p css={[emptyStateText, tw`mt-4 text-sm`]}>
                        O ficheiro users.ini ainda não existe. Instale o AMX Mod X pelo menu Addons ou crie o primeiro admin.
                    </p>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
