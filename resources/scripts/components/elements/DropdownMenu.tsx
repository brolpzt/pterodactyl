import React, { createRef } from 'react';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import Fade from '@/components/elements/Fade';
import Portal from '@/components/elements/Portal';
import {
    dropdownMenuButtonRowStyles,
    dropdownMenuRowStyles,
    dropdownMenuShell,
} from '@/assets/css/cardTheme';

interface Props {
    children: React.ReactNode;
    renderToggle: (onClick: (e: React.MouseEvent<any, MouseEvent>) => void) => React.ReactChild;
}

export const DropdownButtonRow = styled.button<{ danger?: boolean }>`
    ${(props) => dropdownMenuButtonRowStyles(props.danger)};
`;

export const DropdownMenuRow = styled.div<{ $danger?: boolean; $active?: boolean }>`
    ${(props) => dropdownMenuRowStyles(props.$danger, props.$active)};
`;

const MENU_WIDTH = 176;

interface MenuPosition {
    top: number;
    left: number;
}

interface State {
    visible: boolean;
    position: MenuPosition;
}

class DropdownMenu extends React.PureComponent<Props, State> {
    menu = createRef<HTMLDivElement>();

    state: State = {
        visible: false,
        position: { top: 0, left: 0 },
    };

    componentWillUnmount() {
        this.removeListeners();
    }

    componentDidUpdate(prevProps: Readonly<Props>, prevState: Readonly<State>) {
        const menu = this.menu.current;

        if (this.state.visible && !prevState.visible && menu) {
            document.addEventListener('click', this.windowListener);
            document.addEventListener('contextmenu', this.contextMenuListener);
            document.addEventListener('scroll', this.scrollListener, true);
        }

        if (!this.state.visible && prevState.visible) {
            this.removeListeners();
        }
    }

    removeListeners = () => {
        document.removeEventListener('click', this.windowListener);
        document.removeEventListener('contextmenu', this.contextMenuListener);
        document.removeEventListener('scroll', this.scrollListener, true);
    };

    positionBelow = (rect: DOMRect): MenuPosition => ({
        top: rect.bottom + 4,
        left: Math.max(8, Math.min(rect.right - MENU_WIDTH, window.innerWidth - MENU_WIDTH - 8)),
    });

    positionAtCursor = (x: number, y: number): MenuPosition => ({
        top: y,
        left: Math.max(8, Math.min(x, window.innerWidth - MENU_WIDTH - 8)),
    });

    onClickHandler = (e: React.MouseEvent<any, MouseEvent>) => {
        e.preventDefault();
        e.stopPropagation();

        if (this.state.visible) {
            this.setState({ visible: false });
            return;
        }

        const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
        this.setState({
            visible: true,
            position: this.positionBelow(rect),
        });
    };

    contextMenuListener = () => this.setState({ visible: false });

    scrollListener = () => this.setState({ visible: false });

    windowListener = (e: MouseEvent) => {
        const menu = this.menu.current;

        if (e.button === 2 || !this.state.visible || !menu) {
            return;
        }

        if (e.target === menu || menu.contains(e.target as Node)) {
            return;
        }

        this.setState({ visible: false });
    };

    triggerMenu = (detail: number | { x: number; y: number }) => {
        if (typeof detail === 'object' && detail !== null && 'x' in detail) {
            this.setState({
                visible: true,
                position: this.positionAtCursor(detail.x, detail.y),
            });
            return;
        }

        this.setState({
            visible: true,
            position: this.positionAtCursor(detail, 0),
        });
    };

    render() {
        const { visible, position } = this.state;

        return (
            <div style={{ position: 'relative' }}>
                {this.props.renderToggle(this.onClickHandler)}
                <Portal>
                    <Fade timeout={150} in={visible} unmountOnExit>
                        <div
                            ref={this.menu}
                            className={'hg-dropdown-menu'}
                            onClick={(e) => {
                                e.stopPropagation();
                                this.setState({ visible: false });
                            }}
                            style={{
                                position: 'fixed',
                                width: `${MENU_WIDTH}px`,
                                top: position.top,
                                left: position.left,
                            }}
                            css={[dropdownMenuShell, tw`z-[9999]`]}
                        >
                            {this.props.children}
                        </div>
                    </Fade>
                </Portal>
            </div>
        );
    }
}

export default DropdownMenu;
