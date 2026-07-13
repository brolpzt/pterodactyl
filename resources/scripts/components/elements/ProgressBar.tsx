import React, { useEffect, useRef, useState } from 'react';
import styled from 'styled-components/macro';
import { useStoreActions, useStoreState } from 'easy-peasy';
import { randomInt } from '@/helpers';
import { motionDurations } from '@/assets/css/motionTheme';
import { CSSTransition } from 'react-transition-group';

const BarTrack = styled.div`
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    z-index: 60;
    pointer-events: none;
`;

const BarFill = styled.div`
    height: 100%;
    background-color: #2258ff;
    transition: ${motionDurations.progressBar}ms ease-in-out;
    box-shadow: 0 0 10px 1px rgba(34, 88, 255, 0.45);
`;

type Timer = ReturnType<typeof setTimeout>;

export default () => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const timeout = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [visible, setVisible] = useState(false);
    const progress = useStoreState((state) => state.progress.progress);
    const continuous = useStoreState((state) => state.progress.continuous);
    const setProgress = useStoreActions((actions) => actions.progress.setProgress);

    useEffect(() => {
        return () => {
            timeout.current && clearTimeout(timeout.current);
            interval.current && clearInterval(interval.current);
        };
    }, []);

    useEffect(() => {
        setVisible((progress || 0) > 0);

        if (progress === 100) {
            timeout.current = setTimeout(() => setProgress(undefined), 500);
        }
    }, [progress]);

    useEffect(() => {
        if (!continuous) {
            interval.current && clearInterval(interval.current);
            return;
        }

        if (!progress || progress === 0) {
            setProgress(randomInt(20, 30));
        }
    }, [continuous]);

    useEffect(() => {
        if (continuous) {
            interval.current && clearInterval(interval.current);
            if ((progress || 0) >= 90) {
                setProgress(90);
            } else {
                interval.current = setTimeout(() => setProgress((progress || 0) + randomInt(1, 5)), 500);
            }
        }
    }, [progress, continuous]);

    return (
        <BarTrack>
            <CSSTransition timeout={motionDurations.fade} appear in={visible} unmountOnExit classNames={'fade'}>
                <BarFill style={{ width: progress === undefined ? '100%' : `${progress}%` }} />
            </CSSTransition>
        </BarTrack>
    );
};
