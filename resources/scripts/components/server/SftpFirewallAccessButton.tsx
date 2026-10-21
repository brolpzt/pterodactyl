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
    const [loading, setLoading] = useState<'allow' | string | null>(null);
    const [manualIp, setManualIp] = useState('');

    useEffect(() => {
        getSftpFirewallAccess(serverUuid)
            .then(setAccess)
            .catch(() => setAccess(null));
    }, [serverUuid]);

    if (!access?.enabled) {
        return null;
    }

    const currentAllowed = !!access.clientIp && !!access.expiresAt;
    const busy = loading !== null;

    const onRequest = () => {
        clearFlashes('server:sftp-firewall');
        setLoading('allow');
        const override = manualIp.trim();
        requestSftpFirewallAccess(serverUuid, override || undefined)
            .then((next) => {
                setAccess(next);
                setManualIp('');
            })
            .catch((error) => clearAndAddHttpError({ key: 'server:sftp-firewall', error }))
            .finally(() => setLoading(null));
    };

    const onRevoke = (ip: string) => {
        clearFlashes('server:sftp-firewall');
        setLoading(`revoke:${ip}`);
        revokeSftpFirewallAccess(serverUuid, ip)
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
            {access.detectedIpIsV6 && (
                <p css={tw`text-xs text-yellow-400 mb-2`}>{t('server_overview.sftp_firewall_ipv6_note')}</p>
            )}
            {access.grants.length > 0 && (
                <ul css={tw`text-xs text-green-400 mb-2 space-y-1`}>
                    {access.grants.map((grant) => (
                        <li key={grant.ip} css={tw`flex flex-wrap items-center gap-2`}>
                            <span>
                                {t('server_overview.sftp_firewall_active', {
                                    ip: grant.ip,
                                    expires: grant.expiresAt
                                        ? new Date(grant.expiresAt).toLocaleString()
                                        : '—',
                                })}
                                {grant.isCurrent ? ` (${t('server_overview.sftp_firewall_you')})` : ''}
                            </span>
                            <Button.Danger
                                size={Button.Sizes.Small}
                                disabled={busy}
                                onClick={() => onRevoke(grant.ip)}
                            >
                                {loading === `revoke:${grant.ip}`
                                    ? t('server_overview.sftp_firewall_revoking')
                                    : t('server_overview.sftp_firewall_revoke')}
                            </Button.Danger>
                        </li>
                    ))}
                </ul>
            )}
            <div css={tw`flex flex-col sm:flex-row flex-wrap gap-2 items-stretch sm:items-center`}>
                <input
                    type="text"
                    value={manualIp}
                    onChange={(e) => setManualIp(e.target.value)}
                    placeholder={t('server_overview.sftp_firewall_ip_placeholder')}
                    disabled={busy}
                    css={tw`flex-1 min-w-0 px-3 py-2 text-xs rounded bg-neutral-700 border border-neutral-600 text-neutral-100`}
                />
                <Button.Text variant={Button.Variants.Secondary} disabled={busy} onClick={onRequest}>
                    {loading === 'allow'
                        ? t('server_overview.sftp_firewall_working')
                        : currentAllowed && !manualIp.trim()
                          ? t('server_overview.sftp_firewall_renew')
                          : t('server_overview.sftp_firewall_allow')}
                </Button.Text>
            </div>
        </div>
    );
};
