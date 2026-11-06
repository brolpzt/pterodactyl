import { format, parseISO } from 'date-fns';

export const formatBps = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return '—';
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
    let n = value;
    let i = 0;
    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i++;
    }
    return `${n.toFixed(n >= 100 ? 0 : 1)} ${units[i]}`;
};

export const formatPps = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return '—';
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} Mpps`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(1)} Kpps`;
    return `${Math.round(value)} pps`;
};

export const formatWhen = (value: string | null | undefined): string => {
    if (!value) return '—';
    try {
        return format(parseISO(value), 'dd/MM/yyyy HH:mm');
    } catch {
        return value;
    }
};

/** Convert bps to a chart-friendly unit (Mbps). */
export const bpsToMbps = (value: number | null | undefined): number => {
    if (value === null || value === undefined || Number.isNaN(value)) return 0;
    return value / 1_000_000;
};
