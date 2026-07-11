import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ServerContext } from '@/state/server';
import getServerDnsRecords, { DnsRecord } from '@/api/swr/getServerDnsRecords';
import createDnsRecord from '@/api/server/createDnsRecord';
import deleteDnsRecord from '@/api/server/deleteDnsRecord';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import { fieldControl } from '@/assets/css/formTheme';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTrash, faPlus, faGlobe } from '@fortawesome/free-solid-svg-icons';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';

const DATE_LOCALES: Record<string, string> = {
    en: 'en-US',
    pt: 'pt-BR',
    es: 'es-AR',
};

const displayDnsName = (record: DnsRecord): string =>
    record.domain ? `${record.subdomain}.${record.domain}` : record.name;

const DnsRecordRow = ({ record, onDelete }: { record: DnsRecord; onDelete: (record: DnsRecord) => void }) => {
    const { t, i18n } = useTranslation('strings');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const dnsName = displayDnsName(record);
    const dateLocale = DATE_LOCALES[i18n.language?.split('-')[0] || 'en'] || 'en-US';

    return (
        <React.Fragment>
            <Dialog.Confirm
                open={confirmOpen}
                title={t('server_dns.delete_title')}
                confirm={t('server_dns.delete_confirm')}
                onClose={() => setConfirmOpen(false)}
                onConfirmed={() => {
                    setConfirmOpen(false);
                    onDelete(record);
                }}
            >
                {t('server_dns.delete_message', { name: dnsName })}
            </Dialog.Confirm>

            <tr css={tw`border-b border-neutral-600 last:border-b-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                <td css={tw`px-3 py-3`}>
                    <span css={tw`font-mono text-sm text-neutral-100`}>{dnsName}</span>
                </td>
                <td css={tw`px-3 py-3 text-xs text-neutral-500`}>
                    {new Date(record.createdAt).toLocaleString(dateLocale)}
                </td>
                <td css={tw`px-3 py-3 text-right`}>
                    <Button.Danger onClick={() => setConfirmOpen(true)}>
                        <FontAwesomeIcon icon={faTrash} css={tw`mr-1`} />
                        {t('server_dns.remove')}
                    </Button.Danger>
                </td>
            </tr>
        </React.Fragment>
    );
};

const CreateDnsForm = ({
    onCreate,
    recordType,
    zones,
    canCreate,
    initialSubdomain,
}: {
    onCreate: (data: { zoneId: number; subdomain: string; content?: string; proxied?: boolean }) => Promise<void>;
    recordType: string;
    zones: { id: number; label: string; domain: string }[];
    canCreate: boolean;
    initialSubdomain?: string;
}) => {
    const { t } = useTranslation('strings');
    const [zoneId, setZoneId] = useState(zones[0]?.id?.toString() || '');
    const [subdomain, setSubdomain] = useState(initialSubdomain || '');
    const [content, setContent] = useState('');
    const [proxied, setProxied] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (zones.length && !zoneId) {
            setZoneId(zones[0].id.toString());
        }
    }, [zones, zoneId]);

    useEffect(() => {
        if (initialSubdomain) {
            setSubdomain(initialSubdomain);
        }
    }, [initialSubdomain]);

    const selectedZone = zones.find((zone) => zone.id.toString() === zoneId);
    const domainSuffix = selectedZone ? `.${selectedZone.domain}` : '';

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!zoneId || !subdomain.trim() || !canCreate) return;
        setLoading(true);
        try {
            await onCreate({
                zoneId: parseInt(zoneId, 10),
                subdomain: subdomain.trim().toLowerCase(),
                content: recordType === 'CNAME' ? content.trim() : undefined,
                proxied: recordType === 'CNAME' ? proxied : false,
            });
            setSubdomain('');
            setContent('');
            setProxied(false);
        } finally {
            setLoading(false);
        }
    };

    if (!canCreate) {
        return <p css={[emptyStateText, tw`py-2`]}>{t('server_dns.limit_reached')}</p>;
    }

    if (zones.length === 0) {
        return <p css={[emptyStateText, tw`py-2`]}>{t('server_dns.no_domains')}</p>;
    }

    return (
        <form onSubmit={handleSubmit} css={tw`space-y-4`}>
            <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                <div>
                    <Label htmlFor={'dns-zone'} css={tw`text-xs mb-1`}>
                        {t('server_dns.domain')}
                    </Label>
                    <Select id={'dns-zone'} value={zoneId} onChange={(e) => setZoneId(e.target.value)}>
                        {zones.map((zone) => (
                            <option key={zone.id} value={zone.id}>
                                {zone.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label htmlFor={'dns-subdomain'} css={tw`text-xs mb-1`}>
                        {t('server_dns.subdomain')}
                    </Label>
                    <div css={[fieldControl, tw`relative p-0 overflow-hidden`]}>
                        <div
                            aria-hidden
                            css={tw`pointer-events-none absolute inset-y-0 left-0 flex max-w-full items-center overflow-hidden whitespace-nowrap px-3 font-mono text-sm`}
                        >
                            <span css={tw`text-transparent select-none`}>{subdomain || '\u00a0'}</span>
                            {subdomain && domainSuffix ? (
                                <span css={tw`text-neutral-500`}>{domainSuffix}</span>
                            ) : null}
                        </div>
                        <Input
                            id={'dns-subdomain'}
                            type={'text'}
                            placeholder={t('server_dns.subdomain_placeholder')}
                            value={subdomain}
                            onChange={(e) => setSubdomain(e.target.value.toLowerCase())}
                            required
                            css={tw`relative w-full !border-0 !bg-transparent font-mono text-sm !shadow-none hover:!shadow-none focus:!shadow-none`}
                        />
                    </div>
                </div>
            </div>

            {recordType === 'CNAME' && (
                <div>
                    <Label htmlFor={'dns-content'} css={tw`text-xs mb-1`}>
                        {t('server_dns.target_hostname')}
                    </Label>
                    <Input
                        id={'dns-content'}
                        type={'text'}
                        placeholder={t('server_dns.target_placeholder')}
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        required
                        css={tw`font-mono text-sm`}
                    />
                </div>
            )}

            {recordType === 'CNAME' && (
                <label css={tw`flex items-center gap-2 text-sm text-neutral-300`}>
                    <input type={'checkbox'} checked={proxied} onChange={(e) => setProxied(e.target.checked)} />
                    {t('server_dns.enable_proxy')}
                </label>
            )}

            <Button className={'w-full md:w-auto'} type={'submit'} disabled={loading || !subdomain.trim()}>
                {loading ? (
                    <Spinner size={Spinner.Size.SMALL} />
                ) : (
                    <>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        {t('server_dns.create_button')}
                    </>
                )}
            </Button>
        </form>
    );
};

export default () => {
    const { t } = useTranslation('strings');
    const location = useLocation();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures);
    const dnsEnabled = ServerContext.useStoreState((state) => state.server.data!.dnsEnabled);
    const { data, error, isValidating, mutate } = getServerDnsRecords(uuid);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();

    const query = new URLSearchParams(location.search);
    const initialSubdomain = query.get('subdomain') || undefined;

    const hasDnsFeature = dnsEnabled || eggFeatures.includes('dns');

    if (!hasDnsFeature) {
        return (
            <ServerContentBlock title={t('server_dns.title')}>
                <p css={emptyStateText}>{t('server_dns.unavailable')}</p>
            </ServerContentBlock>
        );
    }

    if (!data) {
        if (!error || (error && isValidating)) {
            return <Spinner centered size={Spinner.Size.LARGE} />;
        }
        return <ServerError title={'Oops!'} message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    const handleCreate = async (formData: {
        zoneId: number;
        subdomain: string;
        content?: string;
        proxied?: boolean;
    }) => {
        clearFlashes('server:dns');
        try {
            await createDnsRecord(uuid, formData);
            await mutate();
            addFlash({
                key: 'server:dns',
                type: 'success',
                message: t('server_dns.create_success'),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'server:dns', error: err });
        }
    };

    const handleDelete = async (record: DnsRecord) => {
        clearFlashes('server:dns');
        try {
            await deleteDnsRecord(uuid, record.id);
            await mutate();
            addFlash({
                key: 'server:dns',
                type: 'success',
                message: t('server_dns.delete_success', { name: displayDnsName(record) }),
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'server:dns', error: err });
        }
    };

    return (
        <ServerContentBlock title={t('server_dns.title')}>
            <FlashMessageRender byKey={'server:dns'} css={tw`mb-4`} />

            <TitledGreyBox
                title={
                    <div css={tw`flex items-center`}>
                        <FontAwesomeIcon icon={faGlobe} css={tw`mr-2`} />
                        <span css={tw`text-sm uppercase`}>{t('server_dns.create_title')}</span>
                    </div>
                }
                css={tw`mb-6`}
            >
                <CreateDnsForm
                    onCreate={handleCreate}
                    recordType={data.meta.defaultType}
                    zones={data.meta.zones}
                    canCreate={data.meta.canCreate}
                    initialSubdomain={initialSubdomain}
                />
            </TitledGreyBox>

            <TitledGreyBox
                title={
                    <div css={tw`flex items-center justify-between w-full`}>
                        <span css={tw`text-sm uppercase`}>{t('server_dns.list_title')}</span>
                        <span css={tw`text-xs bg-neutral-900 px-2 py-0.5 rounded-full text-neutral-400`}>
                            {data.records.length} / {data.meta.maxRecords}
                        </span>
                    </div>
                }
            >
                {data.records.length === 0 ? (
                    <p css={[emptyStateText, tw`py-6`]}>{t('server_dns.empty_list')}</p>
                ) : (
                    <table css={tw`w-full text-left`}>
                        <thead>
                            <tr css={tw`text-xs text-neutral-400 uppercase border-b border-neutral-600`}>
                                <th css={tw`px-3 pb-2 font-semibold`}>{t('server_dns.col_record')}</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>{t('server_dns.col_created')}</th>
                                <th css={tw`px-3 pb-2 font-semibold text-right`}>{t('server_dns.col_actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.records.map((record) => (
                                <DnsRecordRow key={record.id} record={record} onDelete={handleDelete} />
                            ))}
                        </tbody>
                    </table>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
