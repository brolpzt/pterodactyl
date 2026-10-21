import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import useFlash from '@/plugins/useFlash';
import { useTranslation } from 'react-i18next';
import getSftpFirewallAccess, { SftpFirewallAccess } from '@/api/server/sftp/getSftpFirewallAccess';
import requestSftpFirewallAccess from '@/api/server/sftp/requestSftpFirewallAccess';
import revokeSftpFirewallAccess from '@/api/server/sftp/revokeSftpFirewallAccess';

export default ({ serverUuid }: { serverUuid: string }) => {
    const { t } = useTranslation('strings');
    const { clearAndAddHttpError, clearFlashes } = useFlash();
    const [access, setAccess] = useState<SftpFirewallAccess | null>(null);
    const [loading, setLoading] = useState<'allow' | 'revoke' | null>(null);

    useEffect(() => {
        getSftpFirewallAccess(serverUuid)
            .then(setAccess)
            .catch(() => setAccess(null));
    }, [serverUuid]);

    if (!access?.enabled) {
        return null;
    }

    const active = !!access.clientIp && !!access.expiresAt;
    const busy = loading !== null;

    const onRequest = () => {
        clearFlashes('server:sftp-firewall');
        setLoading('allow');
        requestSftpFirewallAccess(serverUuid)
            .then(setAccess)
            .catch((error) => clearAndAddHttpError({ key: 'server:sftp-firewall', error }))
            .finally(() => setLoading(null));
    };

    const onRevoke = () => {
        clearFlashes('server:sftp-firewall');
        setLoading('revoke');
        revokeSftpFirewallAccess(serverUuid)
            .then(setAccess)
            .catch((error) => clearAndAddHttpError({ key: 'server:sftp-firewall', error }))
            .finally(() => setLoading(null));
    };

    return (
        <div css={tw`mt-6`}>
            <p css={tw`text-xs text-neutral-300 mb-2`}>
                {t('server_overview.sftp_firewall_help', {
                    minutes: access.ttlMinutes,
                    ip: access.detectedIp || '—',
                })}
            </p>
            {active && (
                <p css={tw`text-xs text-green-400 mb-2`}>
                    {t('server_overview.sftp_firewall_active', {
                        ip: access.clientIp,
                        expires: new Date(access.expiresAt as string).toLocaleString(),
                    })}
                </p>
            )}
            <div css={tw`flex flex-wrap gap-2`}>
                <Button.Text variant={Button.Variants.Secondary} disabled={busy} onClick={onRequest}>
                    {loading === 'allow'
                        ? t('server_overview.sftp_firewall_working')
                        : active
                          ? t('server_overview.sftp_firewall_renew')
                          : t('server_overview.sftp_firewall_allow')}
                </Button.Text>
                {active && (
                    <Button.Danger disabled={busy} onClick={onRevoke}>
                        {loading === 'revoke'
                            ? t('server_overview.sftp_firewall_revoking')
                            : t('server_overview.sftp_firewall_revoke')}
                    </Button.Danger>
                )}
            </div>
        </div>
    );
};
