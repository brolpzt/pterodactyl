import { motionDurations, motionRevealOffsetPx } from '@/assets/css/motionTheme';

/** Fade + subida ao revelar blocos — alinhado ao hostgamer.net */
export const revealThemeStyles = `
    @keyframes reveal-on-view-up {
        from {
            opacity: 0;
            transform: translateY(${motionRevealOffsetPx}px);
        }
        to {
            opacity: 1;
            transform: none;
        }
    }

    .reveal-on-view {
        --reveal-delay: 0ms;
    }

    .reveal-on-view--immediate,
    .reveal-on-view--immediate.is-visible {
        opacity: 1;
        transform: none;
        animation: none;
        will-change: auto;
    }

    @media (prefers-reduced-motion: no-preference) {
        .reveal-on-view {
            opacity: 0;
            transform: translateY(${motionRevealOffsetPx}px);
            will-change: opacity, transform;
        }

        .reveal-on-view.is-visible {
            animation: reveal-on-view-up ${motionDurations.reveal}ms ease-out var(--reveal-delay) both;
        }

        .reveal-on-view.is-visible.reveal-on-view--settled {
            transform: none;
            will-change: auto;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .reveal-on-view,
        .reveal-on-view.is-visible {
            opacity: 1;
            transform: none;
            animation: none;
        }
    }

    @keyframes order-fade-in {
        from {
            opacity: 0.35;
            transform: translateY(6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (prefers-reduced-motion: no-preference) {
        .order-fade-in {
            animation: order-fade-in ${motionDurations.orderFade}ms ease-out both;
        }
    }
`;
