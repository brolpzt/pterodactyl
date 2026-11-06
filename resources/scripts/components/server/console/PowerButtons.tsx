import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';

interface PowerButtonProps {
    className?: string;
}

type ConfirmAction = 'stop' | 'kill' | null;

export default ({ className }: PowerButtonProps) => {
    const [confirm, setConfirm] = useState<ConfirmAction>(null);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const isInstallRestricted = ServerContext.useStoreState((state) => state.server.isInstallRestricted);

    const killable = status === 'stopping';
    const actionsDisabled = isInstallRestricted;

    const sendPower = (action: PowerAction): void => {
        if (!instance) return;
        setConfirm(null);
        instance.send('set state', action);
    };

    const onButtonClick = (
        action: PowerAction,
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ): void => {
        e.preventDefault();
        if (action === 'stop') {
            setConfirm('stop');
            return;
        }
        if (action === 'kill') {
            setConfirm('kill');
            return;
        }
        sendPower(action);
    };

    useEffect(() => {
        if (status === 'offline') {
            setConfirm(null);
        }
    }, [status]);

    return (
        <div css={[tw`flex gap-2 overflow-visible`, className]}>
            <Dialog.Confirm
                open={confirm === 'stop'}
                hideCloseIcon
                onClose={() => setConfirm(null)}
                title={'Parar servidor'}
                confirm={'Parar'}
                onConfirmed={() => sendPower('stop')}
            >
                Tens a certeza que queres parar este servidor?
            </Dialog.Confirm>
            <Dialog.Confirm
                open={confirm === 'kill'}
                hideCloseIcon
                onClose={() => setConfirm(null)}
                title={'Forçar paragem'}
                confirm={'Continuar'}
                onConfirmed={() => sendPower('kill')}
            >
                Forçar a paragem pode corromper dados. Tens a certeza?
            </Dialog.Confirm>
            <Can action={'control.start'}>
                <Button
                    className={'flex-1'}
                    disabled={actionsDisabled || status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    Start
                </Button>
            </Can>
            <Can action={'control.restart'}>
                <Button
                    variant={Button.Variants.Secondary}
                    className={'flex-1'}
                    disabled={actionsDisabled || !status}
                    onClick={onButtonClick.bind(this, 'restart')}
                >
                    Restart
                </Button>
            </Can>
            <Can action={'control.stop'}>
                <Button.Danger
                    className={'flex-1'}
                    disabled={actionsDisabled || status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    {killable ? 'Kill' : 'Stop'}
                </Button.Danger>
            </Can>
        </div>
    );
};
