@extends('layouts.admin')

@section('title')
    Game Monitor
@endsection

@section('content-header')
    <h1>Game Monitor<small>Query ao vivo de todos os servidores de jogo</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Game Monitor</li>
    </ol>
@endsection

@section('content')
@include('admin.gcore.ip-lists._styles')
<style>
    .gm-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 10px; }
    @media (max-width: 900px) { .gm-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .gm-kpi { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; }
    .gm-kpi span { color: var(--muted); font-size: 11px; }
    .gm-kpi strong { font-size: 22px; font-weight: 600; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .gm-kpi em { color: var(--muted); font-size: 10px; font-style: normal; }
    .gm-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
    .gm-toolbar input, .gm-toolbar select { background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 4px; height: 26px; padding: 0 8px; font-size: 12px; }
    .gm-toolbar label { display: inline-flex; gap: 5px; align-items: center; font-size: 12px; color: var(--muted); margin: 0; font-weight: normal; cursor: pointer; }
    .gm-toolbar .chip { cursor: pointer; user-select: none; }
    .gm-toolbar .chip.on { border-color: var(--accent); background: rgba(59,130,246,.18); }
    .gm-spacer { flex: 1; }
    table.gm { width: 100%; }
    table.gm thead th { cursor: pointer; user-select: none; white-space: nowrap; }
    table.gm thead th.sorted::after { content: ' ▾'; color: var(--accent); }
    table.gm thead th.sorted.asc::after { content: ' ▴'; }
    table.gm tbody tr.gm-row { cursor: pointer; }
    table.gm tbody tr.gm-row.open td { background: rgba(59,130,246,.08); }
    table.gm td { vertical-align: middle; }
    .gm-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: var(--muted); }
    .gm-dot.on { background: var(--ok); box-shadow: 0 0 0 3px rgba(16,185,129,.18); }
    .gm-dot.off { background: var(--err); }
    .gm-dot.wait { animation: gm-pulse 1s ease-in-out infinite; }
    .gm-dot.wait:not(.on):not(.off) { background: var(--warn); }
    @keyframes gm-pulse { 50% { opacity: .35; } }
    @media (prefers-reduced-motion: reduce) { .gm-dot.wait { animation: none; } }
    .gm-host { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gm-sub { display: block; color: var(--muted); font-size: 10.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 420px; }
    .gm-players { display: flex; align-items: center; gap: 6px; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .gm-track { width: 60px; height: 5px; border-radius: 3px; background: var(--surface2); overflow: hidden; }
    .gm-fill { height: 100%; background: var(--accent); border-radius: 3px; }
    .gm-fill.full { background: var(--warn); }
    .gm-num { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
    .gm-detail > td { background: var(--surface2) !important; padding: 12px 14px !important; }
    .gm-detail-grid { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 14px; }
    @media (max-width: 1100px) { .gm-detail-grid { grid-template-columns: minmax(0, 1fr); } }
    .gm-info { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 3px 12px; font-size: 12px; }
    .gm-info dt { color: var(--muted); font-weight: normal; }
    .gm-info dd { margin: 0; overflow-wrap: anywhere; }
    table.gm-plist { width: 100%; font-size: 12px; }
    table.data table.gm-plist th { color: var(--muted); font-weight: 500; font-size: 11px; text-align: left; padding: 3px 8px; border-bottom: 1px solid var(--border); background: none; position: static; }
    table.data table.gm-plist td { padding: 3px 8px; border-bottom: 1px solid rgba(47,61,79,.5); background: none; }
    table.data table.gm-plist tr:hover td { background: rgba(255,255,255,.03); }
    .gm-actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .gm-err { color: #fca5a5; font-size: 11px; }
    .gm-warn { color: #fcd34d; }
    .gm-copy.done { color: var(--ok); border-color: var(--ok); }
</style>

<div class="gcore-ddos-wrap">
    <div class="gm-kpis" aria-live="polite">
        <div class="gm-kpi"><span>Servidores online</span><strong id="k-online">—</strong><em id="k-online-sub">consultando…</em></div>
        <div class="gm-kpi"><span>Jogadores agora</span><strong id="k-players">—</strong><em id="k-players-sub">&nbsp;</em></div>
        <div class="gm-kpi"><span>Servidores com jogadores</span><strong id="k-busy">—</strong><em>dos que estão online</em></div>
        <div class="gm-kpi"><span>Última atualização</span><strong id="k-last">—</strong><em id="k-last-sub">&nbsp;</em></div>
    </div>

    <div class="gm-toolbar">
        <input type="search" id="f-search" placeholder="Buscar nome, hostname, mapa, IP, jogador" style="width:280px" aria-label="Buscar">
        <span id="f-types"></span>
        <label><input type="checkbox" id="f-online"> só online</label>
        <label><input type="checkbox" id="f-busy"> só com jogadores</label>
        <span class="gm-spacer"></span>
        <label for="f-every">Atualizar</label>
        <select id="f-every">
            <option value="0">manual</option>
            <option value="15">a cada 15s</option>
            <option value="30" selected>a cada 30s</option>
            <option value="60">a cada 60s</option>
        </select>
        <button type="button" class="btn btn-primary" id="refresh">Atualizar agora</button>
    </div>

    <div class="acl-wrap" style="max-height:none;min-height:0">
        <table class="data gm">
            <thead>
            <tr>
                <th data-sort="status" style="width:24px" title="Status"></th>
                <th data-sort="hostname">Servidor</th>
                <th data-sort="address">Endereço</th>
                <th data-sort="type">Jogo</th>
                <th data-sort="map">Mapa</th>
                <th data-sort="players">Jogadores</th>
                <th data-sort="took" class="gm-num" title="Tempo da query (painel → Wings → servidor)">Query</th>
                <th data-sort="node">Node</th>
            </tr>
            </thead>
            <tbody id="rows"></tbody>
        </table>
    </div>
    <p class="meta-row" style="margin-top:8px">A query sai do node de cada servidor (via Wings), então funciona mesmo com o Anti-DDoS filtrando o IP do painel. Clique numa linha para ver a lista de jogadores. Plutonium vem da lista pública do plutonium.pw. TeamSpeak é consultado pelo painel (ServerQuery TCP), com o login TS3_QUERY_USER/PASS do servidor ou, sem ele, como convidado.</p>
</div>

<script>
(function () {
    'use strict';

    var SERVERS = @json($servers);
    var CONCURRENCY = 6;
    var TYPE_LABEL = { cs16: 'CS 1.6', cscz: 'CS:CZ', cod: 'CoD', coduo: 'CoD UO', cod2: 'CoD2', cod4: 'CoD4', codmw3: 'MW3', plutonium: 'Plutonium', samp: 'SA-MP', tie: 'The Isle', mohaa: 'MoHAA', ts3: 'TeamSpeak 3' };
    var STEAM_CONNECT = { cs16: 1, cscz: 1 };
    var Q3_COLORS = { 0: '#7c8796', 1: '#f87171', 2: '#4ade80', 3: '#facc15', 4: '#60a5fa', 5: '#22d3ee', 6: '#e879f9', 7: '#e7edf4', 8: '#fb923c', 9: '#9ca3af' };

    var state = {}; // id -> { loading, data, error }
    var open = {};
    var sort = { key: 'players', dir: -1 };
    var types = {};
    var timer = null, running = false, lastRun = null;

    function store(k, v) { try { if (v === undefined) return localStorage.getItem('gm:' + k); localStorage.setItem('gm:' + k, v); } catch (e) { return null; } }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    function strip(s) { return String(s == null ? '' : s).replace(/\^[0-9]/g, ''); }
    // CoD / Plutonium colour codes (^0-^9) as coloured spans.
    function colorize(s) {
        if (s == null || s === '') return '';
        var out = '', parts = String(s).split(/(\^[0-9])/), inSpan = false;
        parts.forEach(function (p) {
            var m = /^\^([0-9])$/.exec(p);
            if (m) { if (inSpan) out += '</span>'; out += '<span style="color:' + Q3_COLORS[m[1]] + '">'; inSpan = true; }
            else out += esc(p);
        });
        return out + (inSpan ? '</span>' : '');
    }
    function fmtTime(sec) {
        if (sec == null) return '';
        sec = Math.max(0, Math.floor(sec));
        var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
        return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    }
    function fmtUptime(sec) {
        var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
        return (d ? d + 'd ' : '') + (d || h ? h + 'h ' : '') + m + 'min';
    }
    function typeLabel(t) { return TYPE_LABEL[t] || t; }

    SERVERS.forEach(function (s) { types[s.type] = (types[s.type] || 0) + 1; });
    var activeTypes = {};
    try { activeTypes = JSON.parse(store('types') || '{}') || {}; } catch (e) { activeTypes = {}; }

    // ---- toolbar
    var $types = document.getElementById('f-types');
    Object.keys(types).sort().forEach(function (t) {
        var chip = document.createElement('span');
        chip.className = 'chip' + (activeTypes[t] ? ' on' : '');
        chip.setAttribute('role', 'button');
        chip.tabIndex = 0;
        chip.innerHTML = esc(typeLabel(t)) + ' <strong>' + types[t] + '</strong>';
        function toggle() { activeTypes[t] = !activeTypes[t]; chip.classList.toggle('on', activeTypes[t]); store('types', JSON.stringify(activeTypes)); render(); }
        chip.addEventListener('click', toggle);
        chip.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
        $types.appendChild(chip);
        $types.appendChild(document.createTextNode(' '));
    });

    var $search = document.getElementById('f-search'), $online = document.getElementById('f-online'), $busy = document.getElementById('f-busy'), $every = document.getElementById('f-every');
    $online.checked = store('online') === '1';
    $busy.checked = store('busy') === '1';
    if (store('every') !== null && $every.querySelector('option[value="' + store('every') + '"]')) $every.value = store('every');
    $search.addEventListener('input', render);
    $online.addEventListener('change', function () { store('online', $online.checked ? '1' : '0'); render(); });
    $busy.addEventListener('change', function () { store('busy', $busy.checked ? '1' : '0'); render(); });
    $every.addEventListener('change', function () { store('every', $every.value); schedule(); });
    document.getElementById('refresh').addEventListener('click', function () { refreshAll(); });

    document.querySelectorAll('table.gm thead th[data-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var k = th.getAttribute('data-sort');
            sort = { key: k, dir: sort.key === k ? -sort.dir : (k === 'players' || k === 'status' ? -1 : 1) };
            render();
        });
    });

    // ---- data
    function value(s, key) {
        var st = state[s.id] || {}, d = st.data || {};
        switch (key) {
            case 'status': return d.online ? 2 : (st.loading && !st.data ? 1 : 0);
            case 'hostname': return strip(d.hostname || s.name).toLowerCase();
            case 'address': return s.address || '';
            case 'type': return typeLabel(s.type);
            case 'map': return (d.map || '').toLowerCase();
            case 'players': return d.online ? (d.players || 0) * 1000 + (d.max_players || 0) / 1000 : -1;
            case 'took': return d.took_ms == null ? Infinity : d.took_ms;
            case 'node': return s.node || '';
        }
        return '';
    }

    function visible(s) {
        var d = (state[s.id] || {}).data || {};
        var anyType = Object.keys(activeTypes).some(function (t) { return activeTypes[t]; });
        if (anyType && !activeTypes[s.type]) return false;
        if ($online.checked && !d.online) return false;
        if ($busy.checked && !(d.online && d.players > 0)) return false;
        var q = $search.value.trim().toLowerCase();
        if (!q) return true;
        var hay = [s.name, strip(d.hostname), d.map, s.address, s.node, s.owner, s.egg, typeLabel(s.type)]
            .concat((d.player_list || []).map(function (p) { return strip(p.name); })).join(' ').toLowerCase();
        return hay.indexOf(q) !== -1;
    }

    function rowHtml(s) {
        var st = state[s.id] || {}, d = st.data;
        var dot = d ? (d.online ? 'on' : 'off') : 'wait';
        if (st.loading && d) dot += ' wait';
        var title = d ? (d.online ? 'Online' : (d.error || 'Offline')) : 'Consultando…';
        var host = d && d.online && d.hostname ? colorize(d.hostname) : esc(s.name);
        var sub = d && d.error ? '<span class="gm-err">' + esc(d.error) + '</span>'
            : (d && d.limited ? '<span class="gm-warn">sem permissão para ler os dados</span>' : (d && !d.online ? 'sem resposta' : ''));
        var players = '';
        if (d && d.online && !d.limited) {
            var max = d.max_players || 0, n = d.players || 0, pct = max ? Math.min(100, n / max * 100) : 0;
            players = '<div class="gm-players"><span><strong>' + n + '</strong>/' + max + '</span>'
                + (d.bots ? '<span class="ipl-muted">+' + d.bots + ' bots</span>' : '')
                + '<span class="gm-track"><span class="gm-fill' + (max && n >= max ? ' full' : '') + '" style="width:' + pct.toFixed(1) + '%"></span></span>'
                + (d.password_protected ? ' <i class="fa fa-lock ipl-muted" title="Com senha"></i>' : '')
                + (d.slots && d.slots.show_warning ? ' <i class="fa fa-exclamation-triangle gm-warn" title="O jogo anuncia ' + d.slots.reported + ' slots e o painel permite ' + d.slots.configured + '"></i>' : '')
                + '</div>';
        }
        return '<tr class="gm-row' + (open[s.id] ? ' open' : '') + '" data-id="' + s.id + '" tabindex="0" aria-expanded="' + (open[s.id] ? 'true' : 'false') + '">'
            + '<td><span class="gm-dot ' + dot + '" title="' + esc(title) + '"></span></td>'
            + '<td><div class="gm-host" title="' + esc('Painel: ' + s.name) + '">' + host + '</div>' + (sub ? '<span class="gm-sub">' + sub + '</span>' : '') + '</td>'
            + '<td class="ipl-mono">' + esc(s.address || '—') + '</td>'
            + '<td>' + esc(typeLabel(s.type)) + (d && d.gametype ? ' <span class="ipl-muted">· ' + esc(d.gametype) + '</span>' : '') + '</td>'
            + '<td class="ipl-mono">' + esc(d && d.online ? (d.map || (d.channels != null ? d.channels + ' canais' : '—')) : '') + '</td>'
            + '<td>' + players + '</td>'
            + '<td class="gm-num ipl-mono ipl-muted">' + (d && d.took_ms != null ? d.took_ms + ' ms' : '') + '</td>'
            + '<td class="ipl-muted">' + esc(s.node || '') + '</td>'
            + '</tr>'
            + (open[s.id] ? detailHtml(s) : '');
    }

    function detailHtml(s) {
        var st = state[s.id] || {}, d = st.data || {};
        var list = (d.player_list || []).slice();
        var has = { channel: false, score: false, time: false, idle: false, ping: false };
        list.forEach(function (p) { ['channel', 'score', 'time', 'idle', 'ping'].forEach(function (k) { if (p[k] != null) has[k] = true; }); });
        list.sort(function (a, b) { return (b.score || 0) - (a.score || 0) || (b.time || 0) - (a.time || 0); });

        var players;
        if (d.limited) players = '<p class="gm-warn" style="margin:0">' + esc(d.notice) + '</p>';
        else if (!d.online) players = '<p class="ipl-muted" style="margin:0">' + esc(d.error || (st.data ? 'Servidor não respondeu à query.' : 'Consultando…')) + '</p>';
        else if (!list.length) players = '<p class="ipl-muted" style="margin:0">' + (d.players ? d.players + ' jogadores, mas o servidor não enviou a lista.' : 'Nenhum jogador conectado.') + '</p>';
        else players = '<table class="gm-plist"><thead><tr><th>#</th><th>Jogador</th>'
            + (has.channel ? '<th>Canal</th>' : '') + (has.score ? '<th class="gm-num">Score</th>' : '') + (has.time ? '<th class="gm-num">Tempo</th>' : '')
            + (has.idle ? '<th class="gm-num">Inativo</th>' : '') + (has.ping ? '<th class="gm-num">Ping</th>' : '')
            + '</tr></thead><tbody>'
            + list.map(function (p, i) {
                return '<tr><td class="ipl-muted">' + (i + 1) + '</td><td>' + (p.name ? colorize(p.name) : '<span class="ipl-muted">(conectando)</span>') + (p.away ? ' <span class="ipl-muted">(ausente)</span>' : '') + '</td>'
                    + (has.channel ? '<td class="ipl-muted">' + esc(p.channel || '') + '</td>' : '')
                    + (has.score ? '<td class="gm-num">' + (p.score != null ? p.score : '') + '</td>' : '')
                    + (has.time ? '<td class="gm-num ipl-mono">' + fmtTime(p.time) + '</td>' : '')
                    + (has.idle ? '<td class="gm-num ipl-mono">' + fmtTime(p.idle) + '</td>' : '')
                    + (has.ping ? '<td class="gm-num ipl-mono">' + (p.ping != null ? p.ping + ' ms' : '') + '</td>' : '')
                    + '</tr>';
            }).join('') + '</tbody></table>';

        function row(k, v) { return v == null || v === '' ? '' : '<dt>' + k + '</dt><dd>' + v + '</dd>'; }
        var slots = d.slots && d.slots.configured != null
            ? d.slots.configured + (d.slots.show_warning ? ' <span class="gm-warn">(o jogo anuncia ' + d.slots.reported + ')</span>' : '') : null;
        var info = '<dl class="gm-info">'
            + row('Nome no painel', esc(s.name))
            + row('Hostname', d.hostname ? colorize(d.hostname) : null)
            + row('Jogo', esc(d.game || typeLabel(s.type)))
            + row('Modo', d.gametype ? esc(d.gametype) : null)
            + row('Mapa', d.map ? '<span class="ipl-mono">' + esc(d.map) + '</span>' : null)
            + row('Jogadores', d.online ? (d.players || 0) + ' / ' + (d.max_players || 0) + (d.bots ? ' (+' + d.bots + ' bots)' : '') : null)
            + row('Slots no painel', slots)
            + row('Senha', d.online ? (d.password_protected ? 'sim' : 'não') : null)
            + row('Versão', d.version ? esc(d.version) + (d.platform ? ' · ' + esc(d.platform) : '') : null)
            + row('Canais', d.channels != null ? d.channels : null)
            + row('Uptime', d.uptime != null ? fmtUptime(d.uptime) : null)
            + row('Query', s.type === 'ts3' && d.online ? (d.authenticated ? 'com login' : 'convidado (sem login)') : null)
            + row('Endereço', '<span class="ipl-mono">' + esc(s.address || '—') + '</span>' + (d.query_port && d.query_port !== d.port ? ' <span class="ipl-muted">(query ' + d.query_port + ')</span>' : ''))
            + row('Egg', esc(s.egg))
            + row('Node', esc(s.node))
            + row('Dono', esc(s.owner))
            + row('Consultado', d.queried_at ? esc(new Date(d.queried_at).toLocaleTimeString('pt-BR')) + (d.took_ms != null ? ' <span class="ipl-muted">· ' + d.took_ms + ' ms</span>' : '') : null)
            + '</dl>';

        var actions = '<div class="gm-actions">'
            + '<a class="btn" href="' + esc(s.manage_url) + '">Admin do servidor</a>'
            + '<a class="btn" href="' + esc(s.client_url) + '" target="_blank" rel="noopener">Painel do cliente</a>'
            + (s.address ? '<button type="button" class="btn gm-copy" data-copy="' + esc(s.address) + '">Copiar IP</button>' : '')
            + (s.address && STEAM_CONNECT[s.type] ? '<a class="btn" href="steam://connect/' + esc(s.address) + '">Conectar (Steam)</a>' : '')
            + (s.address && s.type === 'ts3' ? '<a class="btn" href="ts3server://' + esc(s.address.replace(/:(\d+)$/, '?port=$1')) + '">Conectar (TS3)</a>' : '')
            + '<button type="button" class="btn gm-requery" data-id="' + s.id + '">Consultar de novo</button>'
            + '</div>';

        return '<tr class="gm-detail"><td colspan="8"><div class="gm-detail-grid"><div>'
            + '<div class="panel-head" style="padding:0 0 6px;border:0;background:none">Jogadores' + (list.length ? ' (' + list.length + ')' : '') + '</div>' + players
            + '</div><div>' + info + actions + '</div></div></td></tr>';
    }

    var $rows = document.getElementById('rows');
    function render() {
        document.querySelectorAll('table.gm thead th[data-sort]').forEach(function (th) {
            var on = th.getAttribute('data-sort') === sort.key;
            th.classList.toggle('sorted', on);
            th.classList.toggle('asc', on && sort.dir === 1);
            th.setAttribute('aria-sort', on ? (sort.dir === 1 ? 'ascending' : 'descending') : 'none');
        });

        var list = SERVERS.filter(visible).sort(function (a, b) {
            var va = value(a, sort.key), vb = value(b, sort.key);
            if (va < vb) return -sort.dir;
            if (va > vb) return sort.dir;
            return a.name.localeCompare(b.name);
        });
        $rows.innerHTML = list.length ? list.map(rowHtml).join('')
            : '<tr><td colspan="8" class="ipl-muted">' + (SERVERS.length ? 'Nenhum servidor com esse filtro.' : 'Nenhum servidor com egg de jogo consultável.') + '</td></tr>';
        kpis();
    }

    function kpis() {
        var done = 0, online = 0, players = 0, slots = 0, busy = 0, pending = 0;
        SERVERS.forEach(function (s) {
            var st = state[s.id] || {}, d = st.data;
            if (st.loading) pending++;
            if (!d) return;
            done++;
            if (d.online) { online++; players += d.players || 0; slots += d.max_players || 0; if (d.players > 0) busy++; }
        });
        document.getElementById('k-online').textContent = done ? online + ' / ' + SERVERS.length : '—';
        document.getElementById('k-online-sub').textContent = pending ? 'consultando ' + pending + '…' : (SERVERS.length - online) + ' offline ou sem resposta';
        document.getElementById('k-players').textContent = done ? players : '—';
        document.getElementById('k-players-sub').textContent = slots ? 'de ' + slots + ' slots (' + Math.round(players / slots * 100) + '% ocupado)' : ' ';
        document.getElementById('k-busy').textContent = done ? busy : '—';
        document.getElementById('k-last').textContent = lastRun ? lastRun.toLocaleTimeString('pt-BR') : '—';
        var every = +$every.value;
        document.getElementById('k-last-sub').textContent = every ? 'atualiza a cada ' + every + 's' : 'atualização manual';
    }

    // ---- querying
    function queryOne(s) {
        state[s.id] = state[s.id] || {};
        state[s.id].loading = true;
        return fetch(s.query_url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) {
                if (r.status === 401 || r.status === 419) throw new Error('Sessão expirada: recarregue a página.');
                return r.json().catch(function () { throw new Error('Resposta inválida (HTTP ' + r.status + ').'); });
            })
            .then(function (d) { state[s.id].data = d; })
            .catch(function (e) { state[s.id].data = { online: false, error: e.message || 'Falha na consulta.' }; })
            .then(function () { state[s.id].loading = false; render(); });
    }

    function refreshAll() {
        if (running) return;
        running = true;
        clearTimeout(timer);
        var queue = SERVERS.slice(), pending = queue.length;
        queue.forEach(function (s) { state[s.id] = state[s.id] || {}; state[s.id].loading = true; });
        render();
        function next() {
            var s = queue.shift();
            if (!s) return Promise.resolve();
            return queryOne(s).then(next);
        }
        var workers = [];
        for (var i = 0; i < Math.min(CONCURRENCY, pending); i++) workers.push(next());
        Promise.all(workers).then(function () { running = false; lastRun = new Date(); kpis(); schedule(); });
    }

    function schedule() {
        clearTimeout(timer);
        var every = +$every.value;
        kpis();
        if (every && !running) timer = setTimeout(function () { if (document.hidden) { schedule(); return; } refreshAll(); }, every * 1000);
    }

    // ---- row interaction
    function toggleRow(id) { open[id] = !open[id]; render(); }
    $rows.addEventListener('click', function (e) {
        var copy = e.target.closest('.gm-copy');
        if (copy) {
            var text = copy.getAttribute('data-copy');
            try { navigator.clipboard.writeText(text).then(function () { copy.textContent = 'Copiado'; copy.classList.add('done'); setTimeout(function () { copy.textContent = 'Copiar IP'; copy.classList.remove('done'); }, 1500); }); } catch (err) { window.prompt('Copie o endereço:', text); }
            return;
        }
        var re = e.target.closest('.gm-requery');
        if (re) {
            var s = SERVERS.find(function (x) { return String(x.id) === re.getAttribute('data-id'); });
            if (s) { render(); queryOne(s); }
            return;
        }
        if (e.target.closest('a, button')) return;
        var tr = e.target.closest('tr.gm-row');
        if (tr) toggleRow(tr.getAttribute('data-id'));
    });
    $rows.addEventListener('keydown', function (e) {
        var tr = e.target.closest('tr.gm-row');
        if (tr && (e.key === 'Enter' || e.key === ' ') && e.target === tr) { e.preventDefault(); toggleRow(tr.getAttribute('data-id')); }
    });

    render();
    refreshAll();
})();
</script>
@endsection
