import React, { useEffect, useState } from 'react';
import useSWR from 'swr';
import { format, parseISO } from 'date-fns';
import tw from 'twin.macro';
import { faSlidersH } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Select from '@/components/elements/Select';
import Can from '@/components/elements/Can';
import { Alert } from '@/components/elements/alert';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import {
    DdosProfileAlert,
    DdosProfileState,
    getDdosProfile,
    updateDdosProfile,
} from '@/api/server/antiDdos/ddosProfile';
import { cardLabelText, cardValueText } from '@/assets/css/cardTheme';

const ProfileAlert = ({ alert, className }: { alert: DdosProfileAlert; className?: string }) => (
    <Alert
        type={alert.type}
        className={`text-sm items-start [&>svg]:mt-0.5 [&>svg]:shrink-0 [&>svg]:w-5 [&>svg]:h-5 ${className ?? ''}`}
    >
        <div className={'min-w-0 flex-1 break-words'}>
            {alert.title && <p className={'m-0 font-medium text-neutral-100'}>{alert.title}</p>}
            {alert.html && (
                <p
                    className={`m-0 text-neutral-200 leading-relaxed [&_a]:underline [&_a]:font-medium ${
                        alert.title ? 'mt-1.5' : ''
                    }`}
                    // Rendered by EggDdosProfile::alertPayload(), which escapes HTML like panel alerts.
                    dangerouslySetInnerHTML={{ __html: alert.html }}
                />
            )}
        </div>
    </Alert>
);

const SyncStatus = ({ sync }: { sync: NonNullable<DdosProfileState['sync']> }) => {
    if (sync.status === 'pending') {
        return (
            <p css={tw`flex items-center gap-2 text-sm text-yellow-300 m-0 mt-2`}>
                <span css={tw`inline-block h-2 w-2 rounded-full bg-yellow-400 animate-pulse`} />
                Aplicando na proteção… o servidor pode ficar indisponível por alguns segundos durante a mudança.
            </p>
        );
    }
    if (sync.status === 'applied') {
        return (
            <p css={tw`flex items-center gap-2 text-sm text-green-400 m-0 mt-2`}>
                <span css={tw`inline-block h-2 w-2 rounded-full bg-green-400`} />
                Aplicado na proteção
                {sync.finished_at ? ` em ${format(parseISO(sync.finished_at), 'dd/MM HH:mm')}` : ''}.
            </p>
        );
    }

    return (
        <Alert type={'danger'} className={'mt-3 text-sm'}>
            Não foi possível aplicar a última mudança na proteção agora. Ela será aplicada na próxima sincronização;
            você já pode tentar mudar novamente ou abrir um ticket.
        </Alert>
    );
};

const minutesUntil = (iso: string | null, now: number): number => {
    if (!iso) return 0;
    const diff = new Date(iso).getTime() - now;

    return diff > 0 ? Math.ceil(diff / 60_000) : 0;
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { data, mutate, revalidate } = useSWR<DdosProfileState>(
        ['server:anti-ddos:profile', uuid],
        () => getDdosProfile(uuid),
        {
            revalidateOnFocus: false,
            errorRetryCount: 1,
        }
    );
    const [selected, setSelected] = useState<number | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 30_000);

        return () => clearInterval(timer);
    }, []);

    useEffect(() => {
        setSelected(data?.current?.id ?? null);
    }, [data?.current?.id]);

    // While Gcore applies the change, check every few seconds.
    const pending = data?.sync?.status === 'pending';
    useEffect(() => {
        if (!pending) return;
        const timer = setInterval(() => revalidate(), 5_000);

        return () => clearInterval(timer);
    }, [pending]);

    if (!data || !data.enabled || !data.current) {
        return null;
    }

    const wait = minutesUntil(data.next_change_at, now);
    const blocked = data.locked || data.custom;
    const chosen = data.options.find((o) => o.id === selected) ?? null;
    const changed = chosen !== null && chosen.id !== data.current.id;

    const submit = () => {
        if (!chosen) return;
        setSaving(true);
        setError(null);
        updateDdosProfile(uuid, chosen.id)
            .then((state) => {
                mutate(state, false);
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => {
                setSaving(false);
                setConfirming(false);
            });
    };

    return (
        <TitledGreyBox title={'Perfil de proteção'} icon={faSlidersH} className={'mb-4'}>
            <p css={[cardLabelText, tw`text-xs uppercase tracking-wide m-0`]}>Perfil ativo</p>
            <p css={[cardValueText, tw`text-lg font-medium m-0 mt-1`]}>{data.current.label}</p>
            {data.sync && <SyncStatus sync={data.sync} />}
            {data.current.alert && <ProfileAlert alert={data.current.alert} className={'mt-3'} />}

            {blocked
                ? // Locked by the admin: the picker is simply not shown (no notice about it).
                  data.custom && (
                      <p css={[cardLabelText, tw`text-sm mt-4 mb-0`]}>
                          Este servidor usa uma configuração definida pelo suporte. Abra um ticket para alterá-la.
                      </p>
                  )
                : data.options.length > 1 && (
                      <Can action={'antiddos.profile'}>
                          <div css={tw`mt-4 border-t border-neutral-700 pt-4`}>
                              <label css={[cardLabelText, tw`block text-xs uppercase tracking-wide mb-2`]}>
                                  Mudar perfil
                              </label>
                              <div css={tw`flex flex-col sm:flex-row gap-2`}>
                                  <Select
                                      value={selected ?? ''}
                                      onChange={(e) => {
                                          setSelected(Number(e.currentTarget.value));
                                          setError(null);
                                      }}
                                      disabled={saving}
                                  >
                                      {data.options.map((option) => (
                                          <option key={option.id} value={option.id}>
                                              {option.label}
                                              {option.id === data.current!.id ? ' (ativo)' : ''}
                                          </option>
                                      ))}
                                  </Select>
                                  <Button
                                      disabled={!changed || saving || wait > 0}
                                      onClick={() => setConfirming(true)}
                                      className={'sm:w-auto whitespace-nowrap'}
                                  >
                                      Aplicar perfil
                                  </Button>
                              </div>
                              {changed && chosen?.alert && <ProfileAlert alert={chosen.alert} className={'mt-3'} />}
                              <p css={[cardLabelText, tw`text-xs mt-3 mb-0`]}>
                                  A mudança pode deixar o servidor indisponível por alguns segundos.{' '}
                                  {wait > 0
                                      ? `Você poderá mudar novamente em ${wait} min.`
                                      : data.cooldown_minutes > 0
                                      ? `Depois de mudar, uma nova mudança só é liberada após ${data.cooldown_minutes} min.`
                                      : null}
                              </p>
                              {error && (
                                  <Alert type={'danger'} className={'mt-3 text-sm'}>
                                      {error}
                                  </Alert>
                              )}
                          </div>
                      </Can>
                  )}

            <Dialog
                open={confirming}
                onClose={() => !saving && setConfirming(false)}
                title={'Mudar perfil de proteção?'}
            >
                <p css={tw`text-sm text-neutral-300 m-0`}>
                    O perfil passará de <strong>{data.current.label}</strong> para <strong>{chosen?.label}</strong>.
                    {data.cooldown_minutes > 0 &&
                        ` Depois disso, você só poderá mudar de novo em ${data.cooldown_minutes} minutos.`}
                </p>
                <Alert type={'warning'} className={'mt-3 text-sm'}>
                    Enquanto o novo filtro é aplicado, o servidor pode ficar indisponível por alguns segundos e
                    jogadores conectados podem cair.
                </Alert>
                {chosen?.alert && <ProfileAlert alert={chosen.alert} className={'mt-3'} />}
                <Dialog.Footer>
                    <Button.Text onClick={() => setConfirming(false)} disabled={saving}>
                        Cancelar
                    </Button.Text>
                    <Button onClick={submit} disabled={saving}>
                        Confirmar mudança
                    </Button>
                </Dialog.Footer>
            </Dialog>
        </TitledGreyBox>
    );
};
