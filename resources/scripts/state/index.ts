import { createStore, action, Action } from 'easy-peasy';
import flashes, { FlashStore } from '@/state/flashes';
import user, { UserStore } from '@/state/user';
import permissions, { GloablPermissionsStore } from '@/state/permissions';
import settings, { SettingsStore } from '@/state/settings';
import progress, { ProgressStore } from '@/state/progress';

export interface ApplicationStore {
    permissions: GloablPermissionsStore;
    flashes: FlashStore;
    user: UserStore;
    settings: SettingsStore;
    progress: ProgressStore;
    sidebarCollapsed: boolean;
    toggleSidebar: Action<ApplicationStore>;
    setSidebarCollapsed: Action<ApplicationStore, boolean>;
}

const state: ApplicationStore = {
    permissions,
    flashes,
    user,
    settings,
    progress,
    sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
    toggleSidebar: action((state) => {
        state.sidebarCollapsed = !state.sidebarCollapsed;
        localStorage.setItem('sidebar_collapsed', state.sidebarCollapsed ? 'true' : 'false');
    }),
    setSidebarCollapsed: action((state, collapsed) => {
        state.sidebarCollapsed = collapsed;
        localStorage.setItem('sidebar_collapsed', collapsed ? 'true' : 'false');
    }),
};

export const store = createStore(state);
