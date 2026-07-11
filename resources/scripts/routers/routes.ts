import React, { lazy } from 'react';
import ServerOverviewContainer from '@/components/server/ServerOverviewContainer';
import ServerConsole from '@/components/server/console/ServerConsoleContainer';
import DatabasesContainer from '@/components/server/databases/DatabasesContainer';
import ScheduleContainer from '@/components/server/schedules/ScheduleContainer';
import UsersContainer from '@/components/server/users/UsersContainer';
import BackupContainer from '@/components/server/backups/BackupContainer';
import NetworkContainer from '@/components/server/network/NetworkContainer';
import FileManagerContainer from '@/components/server/files/FileManagerContainer';
import SettingsContainer from '@/components/server/settings/SettingsContainer';
import AccountOverviewContainer from '@/components/dashboard/AccountOverviewContainer';
import AccountApiContainer from '@/components/dashboard/AccountApiContainer';
import AccountSSHContainer from '@/components/dashboard/ssh/AccountSSHContainer';
import ActivityLogContainer from '@/components/dashboard/activity/ActivityLogContainer';
import ServerActivityLogContainer from '@/components/server/ServerActivityLogContainer';

// Each of the router files is already code split out appropriately — so
// all of the items above will only be loaded in when that router is loaded.
//
// These specific lazy loaded routes are to avoid loading in heavy screens
// for the server dashboard when they're only needed for specific instances.
const FileEditContainer = lazy(() => import('@/components/server/files/FileEditContainer'));
const ScheduleEditContainer = lazy(() => import('@/components/server/schedules/ScheduleEditContainer'));
const Ts3OverviewContainer = lazy(() => import('@/components/server/ts3/Ts3OverviewContainer'));
const Ts3SnapshotsContainer = lazy(() => import('@/components/server/ts3/Ts3SnapshotsContainer'));
const Ts3BansContainer = lazy(() => import('@/components/server/ts3/Ts3BansContainer'));
const Ts3TokensContainer = lazy(() => import('@/components/server/ts3/Ts3TokensContainer'));
const Ts3LogsContainer = lazy(() => import('@/components/server/ts3/Ts3LogsContainer'));
const Ts3HtmlViewerContainer = lazy(() => import('@/components/server/ts3/Ts3HtmlViewerContainer'));
const Ts3QueryTerminalContainer = lazy(() => import('@/components/server/ts3/Ts3QueryTerminalContainer'));
const AmxxAdminsContainer = lazy(() => import('@/components/server/amxx/AmxxAdminsContainer'));
const AmxxBansContainer = lazy(() => import('@/components/server/amxx/AmxxBansContainer'));
const AmxxWebContainer = lazy(() => import('@/components/server/amxx/AmxxWebContainer'));

interface RouteDefinition {
    path: string;
    // If undefined is passed this route is still rendered into the router itself
    // but no navigation link is displayed in the sub-navigation menu.
    name: string | undefined;
    component: React.ComponentType;
    exact?: boolean;
    adminOnly?: boolean;
}

interface ServerRouteDefinition extends RouteDefinition {
    permission: string | string[] | null;
    nameKey?: string;
}

interface Routes {
    // All of the routes available under "/account"
    account: RouteDefinition[];
    // All of the routes available under "/server/:id"
    server: ServerRouteDefinition[];
}

export default {
    account: [
        {
            path: '/',
            name: 'Account',
            component: AccountOverviewContainer,
            exact: true,
        },
        {
            path: '/api',
            name: 'API Credentials',
            component: AccountApiContainer,
        },
        {
            path: '/ssh',
            name: 'SSH Keys',
            component: AccountSSHContainer,
        },
        {
            path: '/activity',
            name: 'Activity',
            component: ActivityLogContainer,
        },
    ],
    server: [
        {
            path: '/',
            permission: null,
            name: 'Início',
            nameKey: 'server.home',
            component: ServerOverviewContainer,
            exact: true,
        },
        {
            path: '/console',
            permission: null,
            name: 'Console',
            nameKey: 'server.console',
            component: ServerConsole,
            exact: true,
        },
        {
            path: '/files',
            permission: 'file.*',
            name: 'Files',
            nameKey: 'server.files',
            component: FileManagerContainer,
        },
        {
            path: '/files/:action(edit|new)',
            permission: 'file.*',
            name: undefined,
            component: FileEditContainer,
        },
        {
            path: '/databases',
            permission: 'database.*',
            name: 'Databases',
            nameKey: 'server.databases',
            component: DatabasesContainer,
        },
        {
            path: '/schedules',
            permission: 'schedule.*',
            name: 'Schedules',
            nameKey: 'server.schedules',
            component: ScheduleContainer,
        },
        {
            path: '/schedules/:id',
            permission: 'schedule.*',
            name: undefined,
            component: ScheduleEditContainer,
        },
        {
            path: '/users',
            permission: 'user.*',
            name: 'Users',
            nameKey: 'server.users',
            component: UsersContainer,
        },
        {
            path: '/backups',
            permission: 'backup.*',
            name: 'Backups',
            nameKey: 'server.backups',
            component: BackupContainer,
        },
        {
            path: '/network',
            permission: 'allocation.*',
            name: 'Network',
            nameKey: 'server.network',
            component: NetworkContainer,
        },
        {
            path: '/addons',
            permission: 'addon.*',
            name: 'Addons',
            nameKey: 'server.addons',
            component: lazy(() => import('@/components/server/addons/AddonsContainer')),
        },
        {
            path: '/firewall',
            permission: 'firewall.*',
            name: 'Firewall',
            nameKey: 'server.firewall',
            component: lazy(() => import('@/components/server/firewall/FirewallContainer')),
        },
        {
            path: '/activity',
            permission: 'activity.*',
            name: 'Activity',
            nameKey: 'server.activity',
            component: ServerActivityLogContainer,
        },
        {
            path: '/settings',
            permission: ['settings.*', 'file.sftp'],
            name: 'Settings',
            nameKey: 'server.settings',
            component: SettingsContainer,
        },
        {
            path: '/ts3',
            permission: 'activity.*',
            name: undefined,
            component: Ts3OverviewContainer,
            exact: true,
        },
        {
            path: '/ts3/snapshots',
            permission: 'backup.*',
            name: 'Snapshots',
            nameKey: 'server.ts3.snapshots',
            component: Ts3SnapshotsContainer,
        },
        {
            path: '/ts3/bans',
            permission: 'firewall.*',
            name: 'Bans',
            nameKey: 'server.ts3.bans',
            component: Ts3BansContainer,
        },
        {
            path: '/ts3/tokens',
            permission: 'control.console',
            name: 'Tokens',
            nameKey: 'server.ts3.tokens',
            component: Ts3TokensContainer,
        },
        {
            path: '/ts3/logs',
            permission: 'activity.*',
            name: 'Logs',
            nameKey: 'server.ts3.logs',
            component: Ts3LogsContainer,
        },
        {
            path: '/ts3/html-viewer',
            permission: 'activity.*',
            name: 'TS3 Viewer',
            nameKey: 'server.ts3.viewer',
            component: Ts3HtmlViewerContainer,
        },
        {
            path: '/ts3/query',
            permission: 'activity.*',
            name: 'Query Terminal',
            nameKey: 'server.ts3.query',
            component: Ts3QueryTerminalContainer,
        },
        {
            path: '/amxx',
            permission: 'control.console',
            name: 'AMXX Web',
            nameKey: 'server.amxx.web',
            component: AmxxWebContainer,
        },
        {
            path: '/amxx/admins',
            permission: ['file.read-content', 'file.update'],
            name: 'AMXX Admins',
            nameKey: 'server.amxx.admins',
            component: AmxxAdminsContainer,
        },
        {
            path: '/amxx/bans',
            permission: 'firewall.*',
            name: 'AMXX Bans',
            nameKey: 'server.amxx.bans',
            component: AmxxBansContainer,
        },
    ],
} as Routes;
