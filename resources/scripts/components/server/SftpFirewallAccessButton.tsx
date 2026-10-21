import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import { useTranslation } from 'react-i18next';
import getSftpFirewallAccess, { SftpFirewallAccess } from '@/api/server/sftp/getSftpFirewallAccess';
import requestSftpFirewallAccess from '@/api/server/sftp/requestSftpFirewallAccess';

export default ({ serverUuid }: { serverUuid: string }) => {
    const { t } = useTranslation('strings');
    const { clearAndAddHttpError, clearFlashes } = useFlash();
    const [access, setAccess] = useState<SftpFirewallAccess | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        getSftpFirewallAccess(serverUuid)
            .then(setAccess)
            .catch(() => setAccess(null));
    }, [serverUuid]);

    if (!access?.enabled) {
        return null;
    }

    const active = !!access.clientIp && !!access.expiresAt;

    const onRequest = () => {
        clearFlashes('server:sftp-firewall');
        setLoading(true);
        requestSftpFirewallAccess(serverUuid)
            .then(setAccess)
            .catch((error) => clearAndAddHttpError({ key: 'server:sftp-firewall', error }))
            .finally(() => setLoading(false));
    };

    return (
        <div css={tw`mt-6`}>
            <p css={tw`text-xs text-neutral-300 mb-2`}>
                {t('server.sftp_firewall_help', {
                    minutes: access.ttlMinutes,
                    ip: access.detectedIp || '—',
                })}
            </p>
            {active && (
                <p css={tw`text-xs text-green-400 mb-2`}>
                    {t('server.sftp_firewall_active', {
                        ip: access.clientIp,
                        expires: new Date(access.expiresAt as string).toLocaleString(),
                    })}
                </p>
            )}
            <Button.Text variant={Button.Variants.Secondary} disabled={loading} onClick={onRequest}>
                {loading
                    ? t('server.sftp_firewall_working')
                    : active
                      ? t('server.sftp_firewall_renew')
                      : t('server.sftp_firewall_allow')}
            </Button.Text>
        </div>
    );
};
