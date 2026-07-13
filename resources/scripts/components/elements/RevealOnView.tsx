import React, { useEffect, useRef, useState } from 'react';

interface Props {
    children: React.ReactNode;
    /** Atraso da animação (ms), via --reveal-delay */
    delay?: number;
    /** Sem animação — exibe imediatamente */
    immediate?: boolean;
    className?: string;
}

const RevealOnView = ({ children, delay = 0, immediate = false, className }: Props) => {
    const ref = useRef<HTMLDivElement>(null);
    const [visible, setVisible] = useState(immediate);
    const [settled, setSettled] = useState(immediate);

    useEffect(() => {
        if (immediate) {
            setVisible(true);
            setSettled(true);
            return;
        }

        setVisible(false);
        setSettled(false);

        const node = ref.current;
        if (!node) {
            return;
        }

        if (typeof IntersectionObserver === 'undefined') {
            setVisible(true);
            setSettled(true);
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setVisible(true);
                        observer.disconnect();
                    }
                });
            },
            { threshold: 0.01, rootMargin: '0px 0px -4% 0px' }
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [immediate]);

    const classes = [
        'reveal-on-view',
        immediate ? 'reveal-on-view--immediate' : '',
        visible ? 'is-visible' : '',
        settled ? 'reveal-on-view--settled' : '',
        className,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div
            ref={ref}
            className={classes}
            style={{ '--reveal-delay': `${delay}ms` } as React.CSSProperties}
            onAnimationEnd={(event) => {
                if (event.animationName === 'reveal-on-view-up') {
                    setSettled(true);
                }
            }}
        >
            {children}
        </div>
    );
};

export default RevealOnView;
