import React, { useEffect, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEye, faSync } from '@fortawesome/free-solid-svg-icons';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Button from '@/components/elements/Button';
import getSftpPasswordStatus from '@/api/server/sftp/getSftpPasswordStatus';
import revealSftpPassword from '@/api/server/sftp/revealSftpPassword';
import rotateSftpPassword from '@/api/server/sftp/rotateSftpPassword';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';

interface Props {
    serverUuid: string;
}

export default ({ serverUuid }: Props) => {
    const { t } = useTranslation('strings');
    const { addError, clearFlashes } = useFlash();

    const [hasPassword, setHasPassword] = useState<boolean | null>(null);
    const [password, setPassword] = useState<string | null>(null);
    const [loadingReveal, setLoadingReveal] = useState(false);
    const [loadingRotate, setLoadingRotate] = useState(false);

    useEffect(() => {
        clearFlashes('server:sftp-password');
        getSftpPasswordStatus(serverUuid)
            .then(({ hasPassword: configured }) => setHasPassword(configured))
            .catch((error) => {
                console.error(error);
                addError({ key: 'server:sftp-password', message: httpErrorToHuman(error) });
            });
    }, [serverUuid]);

    const reveal = () => {
        setLoadingReveal(true);
        clearFlashes('server:sftp-password');

        revealSftpPassword(serverUuid)
            .then((value) => {
                setPassword(value);
                setHasPassword(true);
            })
            .catch((error) => {
                console.error(error);
                addError({ key: 'server:sftp-password', message: httpErrorToHuman(error) });
            })
            .then(() => setLoadingReveal(false));
    };

    const rotate = () => {
        setLoadingRotate(true);
        clearFlashes('server:sftp-password');

        rotateSftpPassword(serverUuid)
            .then((value) => {
                setPassword(value);
                setHasPassword(true);
            })
            .catch((error) => {
                console.error(error);
                addError({ key: 'server:sftp-password', message: httpErrorToHuman(error) });
            })
            .then(() => setLoadingRotate(false));
    };

    const displayValue =
        password !== null
            ? password
            : hasPassword
                ? '••••••••••••••••'
                : t('server_overview.sftp_password_not_set');

    return (
        <div css={tw`mt-4`}>
            <Label>{t('server_overview.sftp_password')}</Label>
            <div css={tw`flex items-center mt-1 space-x-2`}>
                <div css={tw`flex-1 min-w-0`}>
                    <CopyOnClick text={password || ''} showInNotification={false}>
                        <Input type={'text'} value={displayValue} readOnly />
                    </CopyOnClick>
                </div>
                {hasPassword && password === null && (
                    <Button
                        isSecondary
                        onClick={reveal}
                        isLoading={loadingReveal}
                        disabled={loadingRotate}
                        title={t('server_overview.sftp_password_reveal')}
                    >
                        <FontAwesomeIcon icon={faEye} fixedWidth />
                    </Button>
                )}
                <Button
                    isSecondary
                    color={'primary'}
                    onClick={rotate}
                    isLoading={loadingRotate}
                    disabled={loadingReveal}
                    title={
                        hasPassword
                            ? t('server_overview.sftp_password_rotate')
                            : t('server_overview.sftp_password_generate')
                    }
                >
                    <FontAwesomeIcon icon={faSync} fixedWidth />
                </Button>
            </div>
        </div>
    );
};
