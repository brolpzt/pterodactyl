import React, { useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { emptyStateText } from '@/assets/css/cardTheme';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import { httpErrorToHuman } from '@/api/http';
import { ServerError } from '@/components/elements/ScreenBlock';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import getAdmins from '@/api/server/amxx/getAdmins';
import createAdmin from '@/api/server/amxx/createAdmin';
import updateAdmin from '@/api/server/amxx/updateAdmin';
import deleteAdmin from '@/api/server/amxx/deleteAdmin';
import {
    AMXX_ACCESS_FLAGS,
    AMXX_PRESET_FLAGS,
    AmxxAdmin,
    AmxxAuthType,
    AmxxPreset,
} from '@/api/server/amxx/types';

const PRESET_OPTIONS: { value: AmxxPreset; label: string }[] = [
    { value: 'owner', label: 'Owner (todas as permissões)' },
    { value: 'admin', label: 'Admin' },
    { value: 'mod', label: 'Moderador' },
    { value: 'custom', label: 'Personalizado' },
];

const AUTH_OPTIONS: { value: AmxxAuthType; label: string }[] = [
    { value: 'steamid', label: 'SteamID' },
    { value: 'ip', label: 'IP' },
    { value: 'nickname', label: 'Nickname + Senha' },
];

const flagsFromPreset = (preset: AmxxPreset, customFlags: string): string => {
    if (preset === 'custom') return customFlags;
    return AMXX_PRESET_FLAGS[preset];
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [admins, setAdmins] = useState<AmxxAdmin[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [busyId, setBusyId] = useState<string | number | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);

    const [authType, setAuthType] = useState<AmxxAuthType>('steamid');
    const [auth, setAuth] = useState('');
    const [password, setPassword] = useState('');
    const [nickname, setNickname] = useState('');
    const [preset, setPreset] = useState<AmxxPreset>('admin');
    const [customFlags, setCustomFlags] = useState(AMXX_PRESET_FLAGS.admin);
    const [selectedFlags, setSelectedFlags] = useState<Set<string>>(new Set(AMXX_PRESET_FLAGS.admin.split('')));

    const { clearAndAddHttpError, clearFlashes, addFlash } = useFlash();

    const accessFlags = useMemo(() => {
        if (preset !== 'custom') {
            return flagsFromPreset(preset, customFlags);
        }
        return Array.from(selectedFlags).sort().join('');
    }, [preset, customFlags, selectedFlags]);

    const load = async () => {
        setLoading(true);
        setLoadError(null);
        try {
            setAdmins(await getAdmins(uuid));
        } catch (err) {
            setLoadError(httpErrorToHuman(err));
            clearAndAddHttpError({ key: 'amxx:admins', error: err });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
    }, [uuid]);

    useEffect(() => {
        if (preset === 'custom') return;
        const flags = flagsFromPreset(preset, customFlags);
        setSelectedFlags(new Set(flags.split('')));
    }, [preset]);

    const resetForm = () => {
        setEditingId(null);
        setAuthType('steamid');
        setAuth('');
        setPassword('');
        setNickname('');
        setPreset('admin');
        setCustomFlags(AMXX_PRESET_FLAGS.admin);
        setSelectedFlags(new Set(AMXX_PRESET_FLAGS.admin.split('')));
    };

    const onPresetChange = (value: AmxxPreset) => {
        setPreset(value);
        if (value !== 'custom') {
            setCustomFlags(AMXX_PRESET_FLAGS[value]);
        }
    };

    const toggleFlag = (flag: string) => {
        setPreset('custom');
        setSelectedFlags((current) => {
            const next = new Set(current);
            if (next.has(flag)) {
                next.delete(flag);
            } else {
                next.add(flag);
            }
            setCustomFlags(Array.from(next).sort().join(''));
            return next;
        });
    };

    const onEdit = (admin: AmxxAdmin) => {
        setEditingId(admin.id);
        setAuthType(admin.auth_type);
        setAuth(admin.auth);
        setPassword(admin.password);
        setNickname(admin.nickname || '');
        setPreset('custom');
        setCustomFlags(admin.access_flags);
        setSelectedFlags(new Set(admin.access_flags.split('')));
    };

    const onSubmit = async () => {
        if (!auth.trim()) return;
        clearFlashes('amxx:admins');
        setBusyId(editingId ?? 'create');

        try {
            const payload = {
                auth_type: authType,
                auth: auth.trim(),
                password: authType === 'nickname' ? password : '',
                access_flags: accessFlags,
                nickname: nickname.trim() || undefined,
                preset: preset !== 'custom' ? preset : undefined,
            };

            let commandSent = false;
            if (editingId !== null) {
                const result = await updateAdmin(uuid, editingId, { ...payload, enabled: true });
                commandSent = result.command_sent;
            } else {
                const result = await createAdmin(uuid, payload);
                commandSent = result.command_sent;
            }

            addFlash({
                key: 'amxx:admins',
                type: 'success',
                message: editingId !== null
                    ? `Admin atualizado.${commandSent ? '' : ' Servidor offline — execute amx_reloadadmins quando estiver online.'}`
                    : `Admin criado.${commandSent ? '' : ' Servidor offline — execute amx_reloadadmins quando estiver online.'}`,
            });

            resetForm();
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:admins', error: err });
        } finally {
            setBusyId(null);
        }
    };

    const onDelete = async (adminId: number) => {
        clearFlashes('amxx:admins');
        setBusyId(adminId);
        try {
            await deleteAdmin(uuid, adminId);
            addFlash({ key: 'amxx:admins', type: 'success', message: 'Admin removido com sucesso.' });
            if (editingId === adminId) resetForm();
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:admins', error: err });
        } finally {
            setBusyId(null);
        }
    };

    const onToggleEnabled = async (admin: AmxxAdmin) => {
        clearFlashes('amxx:admins');
        setBusyId(`toggle-${admin.id}`);
        try {
            await updateAdmin(uuid, admin.id, {
                auth_type: admin.auth_type,
                auth: admin.auth,
                password: admin.password,
                access_flags: admin.access_flags,
                nickname: admin.nickname || undefined,
                enabled: !admin.enabled,
            });
            addFlash({
                key: 'amxx:admins',
                type: 'success',
                message: admin.enabled ? 'Admin desativado.' : 'Admin reativado.',
            });
            await load();
        } catch (err) {
            clearAndAddHttpError({ key: 'amxx:admins', error: err });
        } finally {
            setBusyId(null);
        }
    };

    return (
        <ServerContentBlock title={'AMXX Admins'}>
            <FlashMessageRender byKey={'amxx:admins'} css={tw`mb-4`} />

            <TitledGreyBox title={editingId !== null ? 'Editar admin' : 'Adicionar admin'} css={tw`mb-6`}>
                <div css={tw`grid grid-cols-1 md:grid-cols-2 gap-4`}>
                    <div>
                        <Label>Tipo de autenticação</Label>
                        <Select value={authType} onChange={(e) => setAuthType(e.currentTarget.value as AmxxAuthType)}>
                            {AUTH_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <Label>Perfil rápido</Label>
                        <Select value={preset} onChange={(e) => onPresetChange(e.currentTarget.value as AmxxPreset)}>
                            {PRESET_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <Label>{authType === 'steamid' ? 'SteamID' : authType === 'ip' ? 'IP' : 'Nickname'}</Label>
                        <Input
                            value={auth}
                            onChange={(e) => setAuth(e.currentTarget.value)}
                            placeholder={
                                authType === 'steamid'
                                    ? 'STEAM_0:1:123456'
                                    : authType === 'ip'
                                    ? '192.168.1.10'
                                    : 'Nome do admin'
                            }
                        />
                    </div>
                    {authType === 'nickname' && (
                        <div>
                            <Label>Senha</Label>
                            <Input
                                type={'password'}
                                value={password}
                                onChange={(e) => setPassword(e.currentTarget.value)}
                                placeholder={'Senha do admin'}
                            />
                        </div>
                    )}
                    <div>
                        <Label>Nickname (referência)</Label>
                        <Input
                            value={nickname}
                            onChange={(e) => setNickname(e.currentTarget.value)}
                            placeholder={'Opcional — aparece como comentário no users.ini'}
                        />
                    </div>
                </div>

                <div css={tw`mt-4`}>
                    <Label>Permissões ({accessFlags || 'nenhuma'})</Label>
                    <div css={tw`grid grid-cols-2 md:grid-cols-4 gap-2 mt-2`}>
                        {Object.entries(AMXX_ACCESS_FLAGS).map(([flag, label]) => (
                            <label key={flag} css={tw`flex items-center gap-2 text-sm cursor-pointer`}>
                                <Input
                                    type={'checkbox'}
                                    checked={selectedFlags.has(flag)}
                                    onChange={() => toggleFlag(flag)}
                                />
                                <span>
                                    <strong>{flag}</strong> — {label}
                                </span>
                            </label>
                        ))}
                    </div>
                </div>

                <div css={tw`flex gap-2 mt-4`}>
                    <Button onClick={onSubmit} disabled={busyId !== null || !auth.trim()}>
                        {busyId === 'create' || (typeof busyId === 'number' && busyId === editingId) ? (
                            <Spinner size={Spinner.Size.SMALL} />
                        ) : editingId !== null ? (
                            'Salvar alterações'
                        ) : (
                            'Criar admin'
                        )}
                    </Button>
                    {editingId !== null && (
                        <Button.Text onClick={resetForm} disabled={busyId !== null}>
                            Cancelar
                        </Button.Text>
                    )}
                </div>
            </TitledGreyBox>

            <TitledGreyBox title={'Lista de admins'}>
                {loading ? (
                    <Spinner size={Spinner.Size.LARGE} centered />
                ) : loadError ? (
                    <ServerError title={'Erro ao carregar admins'} message={loadError} />
                ) : admins.length === 0 ? (
                    <p css={[emptyStateText, tw`py-4`]}>Nenhum admin cadastrado.</p>
                ) : (
                    <table css={tw`w-full text-sm`}>
                        <thead>
                            <tr css={tw`text-neutral-400 border-b border-neutral-600`}>
                                <th css={tw`text-left py-2`}>Auth</th>
                                <th css={tw`text-left py-2`}>Tipo</th>
                                <th css={tw`text-left py-2`}>Flags</th>
                                <th css={tw`text-left py-2`}>Status</th>
                                <th css={tw`text-right py-2`}>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {admins.map((admin) => (
                                <tr key={admin.id} css={tw`border-b border-neutral-700`}>
                                    <td css={tw`py-2`}>
                                        <div css={tw`font-mono text-xs`}>{admin.auth}</div>
                                        {admin.nickname && <div css={tw`text-neutral-400 text-xs`}>{admin.nickname}</div>}
                                    </td>
                                    <td css={tw`py-2 capitalize`}>{admin.auth_type}</td>
                                    <td css={tw`py-2 font-mono text-xs`}>{admin.access_flags}</td>
                                    <td css={tw`py-2`}>
                                        <span css={admin.enabled ? tw`text-green-400` : tw`text-yellow-400`}>
                                            {admin.enabled ? 'Ativo' : 'Desativado'}
                                        </span>
                                    </td>
                                    <td css={tw`py-2 text-right`}>
                                        <div css={tw`flex justify-end gap-2`}>
                                            <Button.Text
                                                disabled={busyId !== null}
                                                onClick={() => onEdit(admin)}
                                            >
                                                Editar
                                            </Button.Text>
                                            <Button.Text
                                                disabled={busyId !== null}
                                                onClick={() => onToggleEnabled(admin)}
                                            >
                                                {busyId === `toggle-${admin.id}` ? (
                                                    <Spinner size={Spinner.Size.SMALL} />
                                                ) : admin.enabled ? (
                                                    'Desativar'
                                                ) : (
                                                    'Ativar'
                                                )}
                                            </Button.Text>
                                            <Button.Danger disabled={busyId !== null} onClick={() => onDelete(admin.id)}>
                                                {busyId === admin.id ? <Spinner size={Spinner.Size.SMALL} /> : 'Remover'}
                                            </Button.Danger>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </TitledGreyBox>
        </ServerContentBlock>
    );
};
