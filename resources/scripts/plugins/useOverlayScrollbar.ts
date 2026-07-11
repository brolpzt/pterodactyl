import { useCallback, useEffect, useRef, useState } from 'react';

export type OverlayScrollTarget = HTMLElement | 'document' | null;

export interface OverlayScrollbarMetrics {
    visible: boolean;
    thumbHeight: number;
    thumbTop: number;
    trackHeight: number;
}

const MIN_THUMB_HEIGHT = 28;

const getScrollElement = (target: OverlayScrollTarget): HTMLElement | null => {
    if (!target || target === 'document') {
        return document.documentElement;
    }

    return target;
};

const getScrollTop = (target: OverlayScrollTarget) =>
    target === 'document' ? window.scrollY : target?.scrollTop ?? 0;

const setScrollTop = (target: OverlayScrollTarget, value: number) => {
    if (target === 'document') {
        window.scrollTo({ top: value });
        return;
    }

    if (target) {
        target.scrollTop = value;
    }
};

export interface UseOverlayScrollbarOptions {
    /** Deslocamento superior da área visível do trilho (ex.: altura do header fixo). */
    viewportTop?: number;
}

const measure = (
    target: OverlayScrollTarget,
    viewportTop = 0
): OverlayScrollbarMetrics => {
    const el = getScrollElement(target);

    if (!el || !target) {
        return { visible: false, thumbHeight: 0, thumbTop: 0, trackHeight: 0 };
    }

    const scrollHeight = el.scrollHeight;
    const trackHeight =
        target === 'document' ? window.innerHeight - viewportTop : el.clientHeight;
    const scrollTop = getScrollTop(target);

    if (scrollHeight <= trackHeight + 1) {
        return { visible: false, thumbHeight: 0, thumbTop: 0, trackHeight };
    }

    const scrollable = scrollHeight - trackHeight;
    const thumbHeight = Math.max((trackHeight / scrollHeight) * trackHeight, MIN_THUMB_HEIGHT);
    const maxThumbTop = trackHeight - thumbHeight;
    const thumbTop = scrollable > 0 ? (scrollTop / scrollable) * maxThumbTop : 0;

    return { visible: true, thumbHeight, thumbTop, trackHeight };
};

export default (target: OverlayScrollTarget, options: UseOverlayScrollbarOptions = {}) => {
    const viewportTop = options.viewportTop ?? 0;
    const [metrics, setMetrics] = useState<OverlayScrollbarMetrics>(() => measure(target, viewportTop));
    const dragging = useRef(false);
    const dragState = useRef({ startY: 0, startScrollTop: 0 });

    const update = useCallback(() => {
        setMetrics(measure(target, viewportTop));
    }, [target, viewportTop]);

    useEffect(() => {
        update();

        const scrollEl = target === 'document' ? window : target;
        if (!scrollEl) {
            return;
        }

        scrollEl.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        let observer: ResizeObserver | undefined;
        const observed = getScrollElement(target);

        if (observed && typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(update);
            observer.observe(observed);
            if (target !== 'document' && target) {
                observer.observe(target);
            }
        }

        return () => {
            scrollEl.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
            observer?.disconnect();
        };
    }, [target, viewportTop, update]);

    const scrollToThumbPosition = useCallback(
        (thumbTop: number) => {
            if (!target || !metrics.visible) {
                return;
            }

            const el = getScrollElement(target);
            if (!el) {
                return;
            }

            const trackHeight = metrics.trackHeight;
            const maxThumbTop = trackHeight - metrics.thumbHeight;
            const ratio = maxThumbTop > 0 ? thumbTop / maxThumbTop : 0;
            const scrollable = el.scrollHeight - trackHeight;

            setScrollTop(target, ratio * scrollable);
            update();
        },
        [metrics.thumbHeight, metrics.trackHeight, metrics.visible, target, update]
    );

    const onThumbMouseDown = useCallback(
        (event: React.MouseEvent) => {
            if (!target || !metrics.visible) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            dragging.current = true;
            dragState.current = {
                startY: event.clientY,
                startScrollTop: getScrollTop(target),
            };
        },
        [metrics.visible, target]
    );

    const onTrackMouseDown = useCallback(
        (event: React.MouseEvent, trackHeight: number) => {
            if (!target || !metrics.visible || event.target !== event.currentTarget) {
                return;
            }

            const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
            const clickY = event.clientY - rect.top;
            const nextTop = Math.min(
                Math.max(clickY - metrics.thumbHeight / 2, 0),
                trackHeight - metrics.thumbHeight
            );

            scrollToThumbPosition(nextTop);
        },
        [metrics.thumbHeight, metrics.visible, scrollToThumbPosition, target]
    );

    useEffect(() => {
        const onMouseMove = (event: MouseEvent) => {
            if (!dragging.current || !target || !metrics.visible) {
                return;
            }

            const el = getScrollElement(target);
            if (!el) {
                return;
            }

            const deltaY = event.clientY - dragState.current.startY;
            const trackHeight = metrics.trackHeight;
            const maxThumbTop = trackHeight - metrics.thumbHeight;
            const scrollable = el.scrollHeight - trackHeight;
            const scrollDelta = maxThumbTop > 0 ? (deltaY / maxThumbTop) * scrollable : 0;

            setScrollTop(target, dragState.current.startScrollTop + scrollDelta);
            update();
        };

        const onMouseUp = () => {
            dragging.current = false;
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);

        return () => {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };
    }, [metrics.thumbHeight, metrics.trackHeight, metrics.visible, target, update]);

    return {
        metrics,
        onThumbMouseDown,
        onTrackMouseDown,
    };
};
