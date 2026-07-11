import React, { useEffect, useState } from 'react';
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
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faTrash, faPlus, faGlobe } from '@fortawesome/free-solid-svg-icons';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';

const DnsRecordRow = ({ record, onDelete }: { record: DnsRecord; onDelete: (record: DnsRecord) => void }) => {
    const [confirmOpen, setConfirmOpen] = useState(false);

    return (
        <React.Fragment>
            <Dialog.Confirm
                open={confirmOpen}
                title={'Remover DNS'}
                confirm={'Sim, remover'}
                onClose={() => setConfirmOpen(false)}
                onConfirmed={() => {
                    setConfirmOpen(false);
                    onDelete(record);
                }}
            >
                Tem certeza que deseja remover o registro <strong>{record.name}</strong>? O registro será
                excluído da Cloudflare imediatamente.
            </Dialog.Confirm>

            <tr css={tw`border-b border-neutral-600 last:border-b-0 hover:bg-neutral-600/20 transition-colors duration-100`}>
                <td css={tw`px-3 py-3`}>
                    <span css={tw`font-mono text-sm text-neutral-100`}>{record.name}</span>
                </td>
                <td css={tw`px-3 py-3`}>
                    <span css={tw`text-xs uppercase text-neutral-400`}>{record.type}</span>
                </td>
                <td css={tw`px-3 py-3`}>
                    <span css={tw`font-mono text-xs text-neutral-300`}>{record.content}</span>
                </td>
                <td css={tw`px-3 py-3 text-xs text-neutral-500`}>
                    {record.proxied ? 'Proxied' : 'DNS only'}
                </td>
                <td css={tw`px-3 py-3 text-xs text-neutral-500`}>
                    {new Date(record.createdAt).toLocaleString('pt-BR')}
                </td>
                <td css={tw`px-3 py-3 text-right`}>
                    <Button.Danger onClick={() => setConfirmOpen(true)}>
                        <FontAwesomeIcon icon={faTrash} css={tw`mr-1`} />
                        Remover
                    </Button.Danger>
                </td>
            </tr>
        </React.Fragment>
    );
};

const CreateDnsForm = ({
    onCreate,
    allowedTypes,
    defaultType,
    zones,
    primaryIp,
    canCreate,
}: {
    onCreate: (data: { zoneId: number; subdomain: string; type: string; content?: string; proxied?: boolean }) => Promise<void>;
    allowedTypes: string[];
    defaultType: string;
    zones: { id: number; domain: string }[];
    primaryIp: string | null;
    canCreate: boolean;
}) => {
    const [zoneId, setZoneId] = useState(zones[0]?.id?.toString() || '');
    const [subdomain, setSubdomain] = useState('');
    const [type, setType] = useState(defaultType);
    const [content, setContent] = useState('');
    const [proxied, setProxied] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (zones.length && !zoneId) {
            setZoneId(zones[0].id.toString());
        }
    }, [zones, zoneId]);

    useEffect(() => {
        setType(defaultType);
    }, [defaultType]);

    const selectedZone = zones.find((zone) => zone.id.toString() === zoneId);
    const previewName = subdomain && selectedZone ? `${subdomain}.${selectedZone.domain}` : '';

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!zoneId || !subdomain.trim() || !canCreate) return;
        setLoading(true);
        try {
            await onCreate({
                zoneId: parseInt(zoneId, 10),
                subdomain: subdomain.trim().toLowerCase(),
                type,
                content: type === 'CNAME' ? content.trim() : undefined,
                proxied: type === 'CNAME' ? proxied : false,
            });
            setSubdomain('');
            setContent('');
            setProxied(false);
        } finally {
            setLoading(false);
        }
    };

    if (!canCreate) {
        return (
            <p css={[emptyStateText, tw`py-2`]}>
                Este servidor atingiu o limite de registros DNS ou não há domínios disponíveis.
            </p>
        );
    }

    if (zones.length === 0) {
        return (
            <p css={[emptyStateText, tw`py-2`]}>
                Nenhum domínio está disponível para criação de DNS. Contacte o administrador.
            </p>
        );
    }

    return (
        <form onSubmit={handleSubmit} css={tw`space-y-4`}>
            <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                <div>
                    <Label htmlFor={'dns-zone'} css={tw`text-xs mb-1`}>
                        Domínio
                    </Label>
                    <Select id={'dns-zone'} value={zoneId} onChange={(e) => setZoneId(e.target.value)}>
                        {zones.map((zone) => (
                            <option key={zone.id} value={zone.id}>
                                {zone.domain}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label htmlFor={'dns-subdomain'} css={tw`text-xs mb-1`}>
                        Subdomínio
                    </Label>
                    <Input
                        id={'dns-subdomain'}
                        type={'text'}
                        placeholder={'meuserver'}
                        value={subdomain}
                        onChange={(e) => setSubdomain(e.target.value.toLowerCase())}
                        required
                        css={tw`font-mono text-sm`}
                    />
                </div>
            </div>

            <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                <div>
                    <Label htmlFor={'dns-type'} css={tw`text-xs mb-1`}>
                        Tipo
                    </Label>
                    <Select id={'dns-type'} value={type} onChange={(e) => setType(e.target.value)}>
                        {allowedTypes.map((recordType) => (
                            <option key={recordType} value={recordType}>
                                {recordType}
                            </option>
                        ))}
                    </Select>
                </div>
                {type === 'CNAME' ? (
                    <div>
                        <Label htmlFor={'dns-content'} css={tw`text-xs mb-1`}>
                            Destino (hostname)
                        </Label>
                        <Input
                            id={'dns-content'}
                            type={'text'}
                            placeholder={'destino.exemplo.com'}
                            value={content}
                            onChange={(e) => setContent(e.target.value)}
                            required
                            css={tw`font-mono text-sm`}
                        />
                    </div>
                ) : (
                    <div>
                        <Label css={tw`text-xs mb-1`}>IP (alocação primária)</Label>
                        <Input type={'text'} value={primaryIp || '—'} readOnly css={tw`font-mono text-sm opacity-75`} />
                    </div>
                )}
            </div>

            {type === 'CNAME' && (
                <label css={tw`flex items-center gap-2 text-sm text-neutral-300`}>
                    <input type={'checkbox'} checked={proxied} onChange={(e) => setProxied(e.target.checked)} />
                    Ativar proxy Cloudflare (orange cloud)
                </label>
            )}

            {previewName && (
                <p css={tw`text-xs text-neutral-400`}>
                    Preview: <span css={tw`font-mono text-neutral-200`}>{previewName}</span>
                    {type === 'A' && primaryIp ? (
                        <span> → <span css={tw`font-mono`}>{primaryIp}</span></span>
                    ) : null}
                    {type === 'CNAME' && content ? (
                        <span> → <span css={tw`font-mono`}>{content}</span></span>
                    ) : null}
                </p>
            )}

            <Button className={'w-full md:w-auto'} type={'submit'} disabled={loading || !subdomain.trim()}>
                {loading ? (
                    <Spinner size={Spinner.Size.SMALL} />
                ) : (
                    <>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                        Criar registro DNS
                    </>
                )}
            </Button>
        </form>
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures);
    const { data, error, isValidating, mutate } = getServerDnsRecords(uuid);
    const { clearFlashes, addFlash, clearAndAddHttpError } = useFlash();

    const hasDnsFeature = eggFeatures.includes('dns');

    if (!hasDnsFeature) {
        return (
            <ServerContentBlock title={'DNS'}>
                <p css={emptyStateText}>DNS não está disponível para este tipo de servidor.</p>
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
        type: string;
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
                message: 'Registro DNS criado com sucesso na Cloudflare.',
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
                message: `Registro ${record.name} removido com sucesso.`,
            });
        } catch (err) {
            clearAndAddHttpError({ key: 'server:dns', error: err });
        }
    };

    return (
        <ServerContentBlock title={'DNS'}>
            <FlashMessageRender byKey={'server:dns'} css={tw`mb-4`} />

            <TitledGreyBox
                title={
                    <div css={tw`flex items-center`}>
                        <FontAwesomeIcon icon={faGlobe} css={tw`mr-2`} />
                        <span css={tw`text-sm uppercase`}>Criar registro DNS</span>
                    </div>
                }
                css={tw`mb-6`}
            >
                <CreateDnsForm
                    onCreate={handleCreate}
                    allowedTypes={data.meta.allowedTypes}
                    defaultType={data.meta.defaultType}
                    zones={data.meta.zones}
                    primaryIp={data.meta.primaryIp}
                    canCreate={data.meta.canCreate}
                />
            </TitledGreyBox>

            <TitledGreyBox
                title={
                    <div css={tw`flex items-center justify-between w-full`}>
                        <span css={tw`text-sm uppercase`}>Registros DNS</span>
                        <span css={tw`text-xs bg-neutral-900 px-2 py-0.5 rounded-full text-neutral-400`}>
                            {data.records.length} / {data.meta.maxRecords}
                        </span>
                    </div>
                }
            >
                {data.records.length === 0 ? (
                    <p css={[emptyStateText, tw`py-6`]}>
                        Nenhum registro DNS criado para este servidor.
                    </p>
                ) : (
                    <table css={tw`w-full text-left`}>
                        <thead>
                            <tr css={tw`text-xs text-neutral-400 uppercase border-b border-neutral-600`}>
                                <th css={tw`px-3 pb-2 font-semibold`}>Nome</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>Tipo</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>Conteúdo</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>Proxy</th>
                                <th css={tw`px-3 pb-2 font-semibold`}>Criado em</th>
                                <th css={tw`px-3 pb-2 font-semibold text-right`}>Ações</th>
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
