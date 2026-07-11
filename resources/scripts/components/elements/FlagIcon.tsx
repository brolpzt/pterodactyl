import React from 'react';
import { flagAssetUrl, resolveFlagSlug } from '@/lib/flags';

export type FlagIconSize = 'sm' | 'header';

const SIZE_PX: Record<FlagIconSize, number> = {
    sm: 16,
    header: 18,
};

interface Props {
    code?: string | null;
    alt?: string;
    size?: FlagIconSize;
    className?: string;
}

const FlagIcon = ({ code, alt, size = 'sm', className }: Props) => {
    const slug = resolveFlagSlug(code);
    const dimension = SIZE_PX[size];

    return (
        <img
            src={flagAssetUrl(slug)}
            alt={alt || code || 'Flag'}
            width={dimension}
            height={dimension}
            loading="lazy"
            decoding="async"
            className={['flag-icon', size === 'header' ? 'flag-icon--header' : '', className].filter(Boolean).join(' ')}
        />
    );
};

export default FlagIcon;
