import React, { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faServer, faKey } from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import { useStoreState } from 'easy-peasy';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Can from '@/components/elements/Can';
import { ip } from '@/lib/formatters';
import UptimeDuration from '@/components/server/UptimeDuration';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { capitalize } from '@/lib/strings';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';

type Stats = { uptime: number };

const InfoRow = ({
    label,
    value,
    copyValue,
}: {
    label: string;
    value: React.ReactNode;
    copyValue?: string;
}) => (
    <div css={tw`flex items-center justify-between text-sm py-2 border-b border-neutral-600 last:border-0`}>
        <p css={tw`text-neutral-400`}>{label}</p>
        {copyValue !== undefined ? (
            <CopyOnClick text={copyValue}>
                <code css={tw`font-mono bg-neutral-900 rounded py-1 px-2 cursor-pointer hover:bg-neutral-800`}>
                    {value}
                </code>
            </CopyOnClick>
        ) : (
            <span css={tw`text-neutral-200 font-medium`}>{value}</span>
        )}
    </div>
);

const ServerOverviewContainer = () => {
    const [stats, setStats] = useState<Stats>({ uptime: 0 });
    const { t } = useTranslation('strings');

    const status = ServerContext.useStoreState((state) => state.status.value);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);

    const server = ServerContext.useStoreState((state) => state.server.data);
    const username = useStoreState((state) => state.user.data!.username);

    const allocation = server?.allocations?.find((a) => a.isDefault);
    const address = allocation ? `${allocation.alias || ip(allocation.ip)}:${allocation.port}` : '—';

    const passwordVariable = server?.variables?.find(
        (v) =>
            v.serverValue &&
            /(password|pass|rcon|server_password)/i.test(v.envVariable)
    );
    const rconVariable = server?.variables?.find(
        (v) => v.serverValue && /rcon/i.test(v.envVariable)
    ) || passwordVariable;
    const connectionString = address !== '—'
        ? passwordVariable?.serverValue
            ? `connect ${address}; password ${passwordVariable.serverValue}`
            : `connect ${address}`
        : '—';

    useEffect(() => {
        if (!connected || !instance) return;
        instance.send(SocketRequest.SEND_STATS);
    }, [instance, connected]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        try {
            const parsed = JSON.parse(data);
            setStats({ uptime: parsed.uptime || 0 });
        } catch {
            // ignore
        }
    });

    if (!server) return null;

    const uptimeDisplay =
        status === null
            ? t('common.offline')
            : stats.uptime > 0
                ? <UptimeDuration uptime={stats.uptime / 1000} />
                : capitalize(status || '—');

    return (
        <ServerContentBlock title={t('server_overview.title')}>
            <div css={tw`grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8`}>
                <TitledGreyBox title={t('server_overview.server_info')} icon={faServer}>
                    <div css={tw`space-y-0`}>
                        <InfoRow label={t('server_overview.egg_name')} value={server.egg || '—'} />
                        <InfoRow label={t('server_overview.server_id')} value={server.id} copyValue={server.id} />
                        <InfoRow label={t('server_overview.server_ip_port')} value={address} copyValue={address} />
                        <InfoRow
                            label={t('server_overview.databases_contracted')}
                            value={server.featureLimits?.databases ?? '—'}
                        />
                        <InfoRow
                            label={t('server_overview.backups_contracted')}
                            value={server.featureLimits?.backups ?? '—'}
                        />
                        <InfoRow label={t('server_overview.uptime')} value={uptimeDisplay} />
                    </div>
                </TitledGreyBox>

                <Can action="file.sftp">
                    <TitledGreyBox title={t('server_overview.sftp_access')} icon={faKey}>
                        <div>
                            <Label>{t('server_overview.server_address')}</Label>
                            <CopyOnClick text={`sftp://${ip(server.sftpDetails.ip)}:${server.sftpDetails.port}`}>
                                <Input
                                    type="text"
                                    value={`sftp://${ip(server.sftpDetails.ip)}:${server.sftpDetails.port}`}
                                    readOnly
                                    css={tw`mt-1`}
                                />
                            </CopyOnClick>
                        </div>
                        <div css={tw`mt-4`}>
                            <Label>{t('server_overview.username')}</Label>
                            <CopyOnClick text={`${username}.${server.id}`}>
                                <Input
                                    type="text"
                                    value={`${username}.${server.id}`}
                                    readOnly
                                    css={tw`mt-1`}
                                />
                            </CopyOnClick>
                        </div>
                        <div css={tw`border-l-4 border-cyan-500 p-3 mt-4`}>
                            <p css={tw`text-xs text-neutral-200`}>
                                {t('server_overview.sftp_password_note')}
                            </p>
                        </div>
                    </TitledGreyBox>
                </Can>
            </div>

            <TitledGreyBox title={t('server_overview.connection_string')} icon={faServer}>
                <p css={tw`text-neutral-400 text-sm mb-2`}>
                    {t('server_overview.connection_string_help')}
                </p>
                <CopyOnClick text={connectionString}>
                    <Input
                        type="text"
                        value={connectionString}
                        readOnly
                        css={tw`font-mono`}
                    />
                </CopyOnClick>
            </TitledGreyBox>

            <TitledGreyBox title={t('server_overview.rcon_password')} icon={faKey} className="mt-6">
                <p css={tw`text-neutral-400 text-sm mb-2`}>
                    {t('server_overview.rcon_password_help')}
                </p>
                <CopyOnClick text={rconVariable?.serverValue || ''}>
                    <Input
                        type="text"
                        value={rconVariable?.serverValue || '—'}
                        readOnly
                        css={tw`font-mono`}
                    />
                </CopyOnClick>
            </TitledGreyBox>
        </ServerContentBlock>
    );
};

export default ServerOverviewContainer;
