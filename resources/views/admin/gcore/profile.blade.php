@extends('layouts.admin')

@section('title')
    Gcore · {{ $profile['ip_address'] ?? $profile['id'] }}
@endsection

@section('content-header')
    <h1>Editar perfil<small>{{ $profile['ip_address'] ?? '' }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.gcore') }}">Gcore DDoS</a></li>
        <li class="active">{{ $profile['id'] }}</li>
    </ol>
@endsection

@section('content')
<link rel="stylesheet" href="/themes/pterodactyl/css/gcore-ddos.css">
<style>
    .col-dports { min-width: 260px; }
    .port-chips { display: flex; flex-wrap: wrap; gap: 3px; align-items: center; min-height: 26px; padding: 2px 4px; border: 1px solid transparent; border-radius: 3px; cursor: pointer; }
    .port-chips:hover, .port-chips:focus { border-color: var(--accent); outline: none; background: var(--bg); }
    .port-chips .empty { color: var(--muted); font-style: italic; }
    .pchip { display: inline-flex; align-items: center; gap: 3px; height: 18px; padding: 0 6px; border-radius: 9px; font-family: var(--mono); font-size: 10px; border: 1px solid var(--border); background: var(--surface2); color: var(--text); white-space: nowrap; }
    .pchip.st-ok { border-color: rgba(16,185,129,.55); }
    .pchip.st-manual { color: var(--muted); border-style: dashed; }
    .pchip.st-warn { border-color: rgba(245,158,11,.7); color: #fcd34d; }
    .pchip.st-err { border-color: rgba(239,68,68,.7); color: #fca5a5; }
    .pchip.more { background: transparent; color: var(--muted); }
    .pchip-edit { margin-left: auto; color: var(--muted); font-size: 11px; padding: 0 2px; }
    .warn-total { color: #fcd34d; margin-left: 6px; }
    .pm-backdrop { position: fixed; inset: 0; z-index: 1050; background: rgba(0,0,0,.55); display: flex; align-items: flex-start; justify-content: center; padding: 6vh 16px 16px; }
    .pm-backdrop[hidden] { display: none; }
    .pm-dialog { width: min(820px, 100%); max-height: 86vh; display: flex; flex-direction: column; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-family: var(--font); font-size: 12px; box-shadow: 0 20px 50px rgba(0,0,0,.5); }
    .pm-head { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-bottom: 1px solid var(--border); background: var(--surface2); border-radius: 8px 8px 0 0; }
    .pm-head h4 { margin: 0; font-size: 13px; font-weight: 600; }
    .pm-head .sub { color: var(--muted); font-family: var(--mono); font-size: 11px; }
    .pm-head .x { margin-left: auto; }
    .pm-add { display: flex; gap: 6px; padding: 10px 12px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
    .pm-add input { flex: 1; min-width: 200px; height: 28px; padding: 0 8px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-family: var(--mono); font-size: 11px; }
    .pm-add .hint { width: 100%; color: var(--muted); font-size: 10px; }
    .pm-msg { padding: 0 12px; color: #fcd34d; font-size: 11px; min-height: 0; }
    .pm-msg:not(:empty) { padding: 6px 12px 0; }
    .pm-list { overflow: auto; flex: 1; min-height: 120px; }
    .pm-list table { width: 100%; border-collapse: collapse; }
    .pm-list th { position: sticky; top: 0; background: var(--surface2); color: var(--muted); font-weight: 600; text-align: left; padding: 5px 8px; font-size: 11px; border-bottom: 1px solid var(--border); }
    .pm-list td { padding: 4px 8px; border-bottom: 1px solid rgba(47,61,79,.6); vertical-align: top; }
    .pm-list td.port { font-family: var(--mono); width: 64px; }
    .pm-list td.del { width: 30px; }
    .pm-list .owner a { font-weight: 600; }
    .pm-list .owner .meta { color: var(--muted); font-size: 11px; }
    .pm-list .flags span { display: block; font-size: 11px; }
    .pm-list .flags .f-warn { color: #fcd34d; }
    .pm-list .flags .f-err { color: #fca5a5; }
    .pm-list .flags .f-ok { color: #6ee7b7; }
    .pm-list .flags .f-muted { color: var(--muted); }
    .pm-foot { display: flex; gap: 6px; flex-wrap: wrap; padding: 10px 12px; border-top: 1px solid var(--border); }
    .pm-foot .spacer { flex: 1; }
</style>
@php
    $id = (int) $profile['id'];
    $aclJson = json_encode($form['acl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $ruleCount = count($form['acl']);
    $st = (string) ($profile['status']['status'] ?? '-');
    $stClass = str_contains(strtolower($st), 'pending') ? 'status-pending' : 'status-updated';
@endphp
<div class="gcore-ddos-wrap">
    <div class="meta-row">
        <a href="{{ route('admin.gcore') }}">← Perfis</a>
        <span class="chip"><strong>ID</strong> {{ $id }}</span>
        <span class="chip"><strong>IP</strong> {{ $profile['ip_address'] ?? '' }}</span>
        <span class="chip"><strong>POP</strong> {{ $profile['site'] ?? '' }}</span>
        <span class="chip"><strong>Tmpl</strong> {{ $profile['profile_template']['name'] ?? '' }}</span>
        <span class="chip"><strong>Plano</strong> {{ $profile['plan'] ?? '' }}</span>
        <span class="chip {{ $stClass }}">{{ $st }}</span>
    </div>

    @if($error)
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">{{ $error }}</div>
    @endif

    <form method="post" action="{{ route('admin.gcore.profile.update', $id) }}" id="profile-form">
        @csrf
        <div class="toolbar">
            <button type="submit" class="btn btn-primary">Salvar na Gcore</button>
            <button type="button" class="btn" id="add-rule">+ Regra</button>
            <button type="button" class="btn btn-ghost" id="preset-theisle">Preset The Isle</button>
            <button type="button" class="btn btn-ghost" id="preset-geo">Preset GEO block</button>
            <span class="toolbar-spacer"></span>
            <span class="toolbar-hint"><strong id="rule-count">{{ $ruleCount }}</strong> regras · ordem = prioridade · <code>dport</code> = porta no servidor · clique nas portas para editar<span class="warn-total" id="warn-total"></span></span>
        </div>

        <div class="layout-edit">
            <aside>
                <div class="panel" style="margin-bottom:10px">
                    <div class="panel-head">Rate limiters (kpps)</div>
                    <div class="panel-body field-grid">
                        <div class="field">
                            <label>Low 1–50</label>
                            <input type="number" name="rate_low" min="1" max="50" value="{{ (int) $form['rate']['low'] }}">
                        </div>
                        <div class="field">
                            <label>Med 50–150</label>
                            <input type="number" name="rate_medium" min="50" max="150" value="{{ (int) $form['rate']['medium'] }}">
                        </div>
                        <div class="field">
                            <label>High 150–300</label>
                            <input type="number" name="rate_high" min="150" max="300" value="{{ (int) $form['rate']['high'] }}">
                        </div>
                        <div class="field">
                            <label>Geo 1–300</label>
                            <input type="number" name="rate_geo" min="1" max="300" value="{{ (int) $form['rate']['geo'] }}">
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head">GEOIP block</div>
                    <div class="panel-body">
                        <div class="field field-full">
                            <label>ISO (CN, US…)</label>
                            <textarea name="geoip_list" placeholder="vazio = off">{{ implode(', ', $form['geoip']) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="panel json-panel" style="margin-top:10px">
                    <div class="panel-head">JSON ACL (preview)</div>
                    <pre><code id="json-preview">{{ $aclJson }}</code></pre>
                </div>
            </aside>

            <div class="acl-wrap">
                <table class="data" id="acl-table">
                    <thead>
                    <tr>
                        <th class="col-idx">#</th>
                        <th class="col-ops">Ordem</th>
                        <th class="col-policy">Política</th>
                        <th class="col-proto">Proto</th>
                        <th class="col-dports">dport</th>
                        <th class="col-ports">sport</th>
                        <th class="col-sip">sip</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="acl-body">
                    @foreach($form['acl'] as $i => $rule)
                        <tr class="acl-row">
                            <td class="col-idx row-idx">{{ $i + 1 }}</td>
                            <td class="col-ops">
                                <button type="button" class="btn btn-icon btn-ghost move-up" title="Subir">↑</button>
                                <button type="button" class="btn btn-icon btn-ghost move-down" title="Descer">↓</button>
                            </td>
                            <td class="col-policy">
                                <select name="acl[{{ $i }}][policy]" required>
                                    @foreach($policies as $p)
                                        <option value="{{ $p }}" @selected(($rule['policy'] ?? '') === $p)>{{ $p }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="acl[{{ $i }}][proto_list]" value="{{ implode(',', $rule['proto_list'] ?? []) }}" placeholder="tcp,udp"></td>
                            <td class="col-dports">
                                <input type="hidden" name="acl[{{ $i }}][dport_list]" value="{{ implode(',', $rule['dport_list'] ?? []) }}">
                                <div class="port-chips" tabindex="0" title="Editar portas"></div>
                            </td>
                            <td><input name="acl[{{ $i }}][sport_list]" value="{{ implode(',', $rule['sport_list'] ?? []) }}" placeholder="53"></td>
                            <td><input name="acl[{{ $i }}][sip_list]" value="{{ implode(',', $rule['sip_list'] ?? []) }}" placeholder="IP/CIDR"></td>
                            <td><button type="button" class="btn btn-icon del-rule" title="Remover">×</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <template id="rule-row-template">
        <tr class="acl-row">
            <td class="col-idx row-idx">0</td>
            <td class="col-ops">
                <button type="button" class="btn btn-icon btn-ghost move-up">↑</button>
                <button type="button" class="btn btn-icon btn-ghost move-down">↓</button>
            </td>
            <td class="col-policy">
                <select name="acl[__IDX__][policy]" required>
                    @foreach($policies as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </td>
            <td><input name="acl[__IDX__][proto_list]" placeholder="tcp,udp"></td>
            <td class="col-dports">
                <input type="hidden" name="acl[__IDX__][dport_list]" value="">
                <div class="port-chips" tabindex="0" title="Editar portas"></div>
            </td>
            <td><input name="acl[__IDX__][sport_list]" placeholder=""></td>
            <td><input name="acl[__IDX__][sip_list]" placeholder=""></td>
            <td><button type="button" class="btn btn-icon del-rule">×</button></td>
        </tr>
    </template>

    <div class="pm-backdrop" id="pm" hidden>
        <div class="pm-dialog" role="dialog" aria-modal="true" aria-labelledby="pm-title">
            <div class="pm-head">
                <h4 id="pm-title">Portas</h4>
                <span class="sub" id="pm-sub"></span>
                <button type="button" class="btn btn-icon btn-ghost x" id="pm-close" title="Fechar">×</button>
            </div>
            <div class="pm-add">
                <input type="text" id="pm-input" placeholder="27015, 27020-27025, 7777" autocomplete="off">
                <button type="button" class="btn btn-primary" id="pm-add">Adicionar</button>
                <span class="hint">Aceita lista separada por vírgula/espaço/linha e faixas <code>a-b</code> (máx. 1000 portas por faixa). Repetidas são ignoradas.</span>
            </div>
            <div class="pm-msg" id="pm-msg"></div>
            <div class="pm-list">
                <table>
                    <thead><tr><th>Porta</th><th>Servidor / slot</th><th>Status</th><th></th></tr></thead>
                    <tbody id="pm-body"></tbody>
                </table>
            </div>
            <div class="pm-foot">
                <button type="button" class="btn" id="pm-sort">Ordenar</button>
                <button type="button" class="btn" id="pm-rm-free">Remover livres</button>
                <button type="button" class="btn" id="pm-rm-dup">Remover repetidas</button>
                <span class="spacer"></span>
                <span class="toolbar-hint">As alterações só vão para a Gcore ao clicar em “Salvar na Gcore”.</span>
                <button type="button" class="btn btn-primary" id="pm-done">Concluir</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const PORT_MAP = @json((object) $portMap);
    const MAX_CHIPS = 10;
    const body = document.getElementById('acl-body');
    const tpl = document.getElementById('rule-row-template').innerHTML;
    const countEl = document.getElementById('rule-count');
    const jsonEl = document.getElementById('json-preview');

    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const splitList = s => s ? String(s).split(/[\s,;]+/).filter(Boolean) : [];
    const parsePorts = s => splitList(s).map(Number).filter(n => Number.isInteger(n) && n >= 1 && n <= 65535);
    const dportInput = row => row.querySelector('[name$="[dport_list]"]');

    function ruleInfo(row) {
        const g = n => row.querySelector('[name$="[' + n + ']"]')?.value?.trim() || '';
        const protos = splitList(g('proto_list').toLowerCase());
        return {
            row,
            policy: g('policy'),
            protos: protos.length === 0 || protos.includes('any') ? ['any'] : protos,
            dports: parsePorts(g('dport_list')),
            sip: splitList(g('sip_list')),
            sport: parsePorts(g('sport_list')),
        };
    }

    // Only tcp/udp carry ports; "any" = both. icmp/gre/esp rules never match a dport.
    const portProtos = list => list.includes('any') ? ['tcp', 'udp'] : list.filter(p => p === 'tcp' || p === 'udp');
    const protosOverlap = (a, b) => { const x = portProtos(a), y = portProtos(b); return x.some(p => y.includes(p)); };

    // Per-row, per-port status: owner (server/slot), and warnings derived from rule order.
    function analyze() {
        const rules = [...body.querySelectorAll('.acl-row')].map(ruleInfo);
        const result = new Map();
        let warnings = 0;

        rules.forEach((rule, idx) => {
            const ports = [];
            rule.dports.forEach(port => {
                const entries = PORT_MAP[port] || [];
                const owners = entries.filter(e => e.server);
                const flags = [];
                let level = 'ok';
                const bump = l => { const order = { ok: 0, manual: 0, warn: 1, err: 2 }; if (order[l] > order[level]) level = l; };

                if (entries.length === 0) {
                    level = 'manual';
                    flags.push({ cls: 'f-muted', text: 'Manual (sem allocation no painel)' });
                } else if (owners.length === 0) {
                    bump('err');
                    flags.push({ cls: 'f-err', text: 'Livre: allocation sem servidor, porta aberta à toa' });
                } else {
                    owners.forEach(e => {
                        const s = e.server;
                        if (!s.policy) {
                            bump('warn');
                            flags.push({ cls: 'f-warn', text: 'Slot sem perfil Gcore no egg (o sync remove)' });
                        } else if (s.policy !== rule.policy || (s.proto && !protosOverlap([s.proto], rule.protos))) {
                            bump('err');
                            flags.push({ cls: 'f-err', text: 'Egg espera ' + s.policy + '/' + (s.proto || 'any') + ' (o sync move)' });
                        } else {
                            flags.push({ cls: 'f-ok', text: 'Confere com o egg (' + s.policy + '/' + (s.proto || 'any') + ')' });
                        }
                        if (!e.protected) {
                            bump('warn');
                            flags.push({ cls: 'f-warn', text: 'Allocation não marcada como protegida Gcore' });
                        }
                    });
                }

                // Earlier rule that already matches this port (first match wins).
                if (rule.sip.length === 0 && rule.sport.length === 0) {
                    for (let j = 0; j < idx; j++) {
                        const prev = rules[j];
                        if (prev.sip.length || prev.sport.length || !protosOverlap(prev.protos, rule.protos)) continue;
                        if (prev.dports.length === 0 || prev.dports.includes(port)) {
                            bump('warn');
                            flags.push({
                                cls: 'f-warn',
                                dup: prev.policy === rule.policy && prev.dports.includes(port),
                                text: prev.policy === rule.policy && prev.dports.includes(port)
                                    ? 'Repetida: já está na linha ' + (j + 1)
                                    : 'Sem efeito: a linha ' + (j + 1) + ' (' + prev.policy + ') captura antes',
                            });
                            break;
                        }
                    }
                }

                if (level === 'warn' || level === 'err') warnings++;
                ports.push({ port, owners, entries, flags, level });
            });
            result.set(rule.row, ports);
        });

        return { result, warnings };
    }

    function ownerLabel(p) {
        if (p.owners.length === 0) return p.entries.length ? 'livre' : 'manual';
        return p.owners.map(e => '#' + e.server.id + ' ' + e.server.name + (e.server.slot ? ' · ' + e.server.slot : '')).join(' | ');
    }

    function renderChips(row, ports) {
        const box = row.querySelector('.port-chips');
        if (!box) return;
        if (ports.length === 0) {
            box.innerHTML = '<span class="empty">sem portas</span><span class="pchip-edit">✎</span>';
            return;
        }
        const shown = ports.slice(0, MAX_CHIPS).map(p =>
            '<span class="pchip st-' + p.level + '" title="' + esc(p.port + ' — ' + ownerLabel(p) + (p.flags.length ? '\n' + p.flags.map(f => f.text).join('\n') : '')) + '">' + p.port + '</span>'
        ).join('');
        const more = ports.length > MAX_CHIPS ? '<span class="pchip more">+' + (ports.length - MAX_CHIPS) + '</span>' : '';
        const warn = ports.filter(p => p.level === 'warn' || p.level === 'err').length;
        box.innerHTML = shown + more + '<span class="pchip-edit">' + ports.length + (warn ? ' · ⚠' + warn : '') + ' ✎</span>';
    }

    let modalRow = null;

    function refreshMeta() {
        const { result, warnings } = analyze();
        result.forEach((ports, row) => renderChips(row, ports));
        const wt = document.getElementById('warn-total');
        if (wt) wt.textContent = warnings ? '⚠ ' + warnings + ' porta(s) com aviso' : '';
        if (modalRow) renderModal(result.get(modalRow) || []);

        const rows = body.querySelectorAll('.acl-row');
        rows.forEach((row, idx) => {
            const el = row.querySelector('.row-idx');
            if (el) el.textContent = String(idx + 1);
        });
        if (countEl) countEl.textContent = String(rows.length);
        if (jsonEl) {
            const rules = [];
            rows.forEach(row => {
                const g = n => row.querySelector('[name$="[' + n + ']"]')?.value?.trim() || '';
                const split = s => s ? s.split(/[\s,;]+/).filter(Boolean) : [];
                const policy = g('policy');
                if (!policy) return;
                rules.push({
                    policy,
                    sip_list: split(g('sip_list')),
                    dport_list: split(g('dport_list')).map(Number).filter(n => n >= 1 && n <= 65535),
                    proto_list: split(g('proto_list')),
                    sport_list: split(g('sport_list')).map(Number).filter(n => n >= 1 && n <= 65535),
                });
            });
            jsonEl.textContent = JSON.stringify(rules, null, 2);
        }
    }

    function reindex() {
        body.querySelectorAll('.acl-row').forEach((row, idx) => {
            row.querySelectorAll('[name^="acl["]').forEach(el => {
                el.name = el.name.replace(/acl\[\d+\]/, 'acl[' + idx + ']');
            });
        });
        refreshMeta();
    }

    function bindRow(row) {
        row.querySelector('.del-rule')?.addEventListener('click', () => { row.remove(); reindex(); });
        row.querySelector('.move-up')?.addEventListener('click', () => {
            const prev = row.previousElementSibling;
            if (prev) { body.insertBefore(row, prev); reindex(); }
        });
        row.querySelector('.move-down')?.addEventListener('click', () => {
            const next = row.nextElementSibling;
            if (next) { body.insertBefore(next, row); reindex(); }
        });
        row.querySelectorAll('input,select').forEach(el => el.addEventListener('input', refreshMeta));
        const chips = row.querySelector('.port-chips');
        chips?.addEventListener('click', () => openModal(row));
        chips?.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openModal(row); } });
    }

    // ---- Port editor modal ----
    const pm = document.getElementById('pm');
    const pmBody = document.getElementById('pm-body');
    const pmInput = document.getElementById('pm-input');
    const pmMsg = document.getElementById('pm-msg');

    function setRowPorts(row, ports) {
        dportInput(row).value = ports.join(',');
        refreshMeta();
    }

    function openModal(row) {
        modalRow = row;
        pmMsg.textContent = '';
        pmInput.value = '';
        pm.hidden = false;
        refreshMeta();
        pmInput.focus();
    }

    function closeModal() {
        modalRow = null;
        pm.hidden = true;
    }

    function renderModal(ports) {
        if (!modalRow) return;
        const info = ruleInfo(modalRow);
        const idx = [...body.querySelectorAll('.acl-row')].indexOf(modalRow) + 1;
        document.getElementById('pm-title').textContent = 'Linha ' + idx + ' · ' + (info.policy || '—');
        document.getElementById('pm-sub').textContent = info.protos.join(',') + ' · ' + ports.length + ' porta(s)';
        if (ports.length === 0) {
            pmBody.innerHTML = '<tr><td colspan="4" style="color:var(--muted);padding:14px 8px">Nenhuma porta nesta regra.</td></tr>';
            return;
        }
        pmBody.innerHTML = ports.map(p => {
            const owner = p.owners.length
                ? p.owners.map(e => {
                    const s = e.server;
                    return '<div><a href="' + esc(s.url) + '" target="_blank" rel="noopener">#' + esc(s.id) + ' ' + esc(s.name) + '</a>'
                        + '<div class="meta">' + esc([s.nest, s.egg].filter(Boolean).join(' / ')) + ' · '
                        + esc(s.slot ? (s.slot + (s.primary ? ' (primária)' : '')) : 'sem slot') + (e.node ? ' · ' + esc(e.node) : '') + '</div></div>';
                }).join('')
                : '<span class="meta">' + (p.entries.length ? 'Allocation livre' + (p.entries[0].node ? ' · ' + esc(p.entries[0].node) : '') : 'Fora do painel (manual)') + '</span>';
            const flags = p.flags.map(f => '<span class="' + f.cls + '">' + esc(f.text) + '</span>').join('');
            return '<tr><td class="port"><span class="pchip st-' + p.level + '">' + p.port + '</span></td>'
                + '<td class="owner">' + owner + '</td><td class="flags">' + flags + '</td>'
                + '<td class="del"><button type="button" class="btn btn-icon pm-del" data-port="' + p.port + '" title="Remover porta">×</button></td></tr>';
        }).join('');
    }

    function parseInputPorts(text) {
        const out = [];
        const bad = [];
        splitList(text).forEach(tok => {
            const m = tok.match(/^(\d+)-(\d+)$/);
            if (m) {
                let a = Number(m[1]), b = Number(m[2]);
                if (a > b) [a, b] = [b, a];
                if (a < 1 || b > 65535 || b - a >= 1000) { bad.push(tok); return; }
                for (let p = a; p <= b; p++) out.push(p);
                return;
            }
            const n = Number(tok);
            if (Number.isInteger(n) && n >= 1 && n <= 65535) out.push(n); else bad.push(tok);
        });
        return { out, bad };
    }

    function addFromInput() {
        if (!modalRow) return;
        const { out, bad } = parseInputPorts(pmInput.value);
        const current = parsePorts(dportInput(modalRow).value);
        const set = new Set(current);
        const added = out.filter(p => !set.has(p) && set.add(p));
        pmMsg.textContent = (bad.length ? 'Ignorado (inválido): ' + bad.join(', ') + '. ' : '')
            + (out.length && !added.length ? 'Todas já estavam na regra.' : '');
        pmInput.value = '';
        setRowPorts(modalRow, current.concat(added));
    }

    document.getElementById('pm-add').addEventListener('click', addFromInput);
    pmInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addFromInput(); } });
    pmInput.addEventListener('paste', () => setTimeout(() => { if (/[\s,;]/.test(pmInput.value.trim())) addFromInput(); }, 0));
    pmBody.addEventListener('click', e => {
        const btn = e.target.closest('.pm-del');
        if (!btn || !modalRow) return;
        const port = Number(btn.dataset.port);
        setRowPorts(modalRow, parsePorts(dportInput(modalRow).value).filter(p => p !== port));
    });
    document.getElementById('pm-sort').addEventListener('click', () => {
        if (modalRow) setRowPorts(modalRow, [...new Set(parsePorts(dportInput(modalRow).value))].sort((a, b) => a - b));
    });
    document.getElementById('pm-rm-free').addEventListener('click', () => {
        if (!modalRow) return;
        const free = p => (PORT_MAP[p] || []).length > 0 && !(PORT_MAP[p] || []).some(e => e.server);
        const ports = parsePorts(dportInput(modalRow).value);
        const keep = ports.filter(p => !free(p));
        pmMsg.textContent = ports.length === keep.length ? 'Nenhuma porta livre nesta regra.' : 'Removidas ' + (ports.length - keep.length) + ' porta(s) livre(s).';
        setRowPorts(modalRow, keep);
    });
    document.getElementById('pm-rm-dup').addEventListener('click', () => {
        if (!modalRow) return;
        const ports = analyze().result.get(modalRow) || [];
        const dup = new Set(ports.filter(p => p.flags.some(f => f.dup)).map(p => p.port));
        const list = parsePorts(dportInput(modalRow).value);
        const keep = list.filter((p, i) => !dup.has(p) && list.indexOf(p) === i);
        pmMsg.textContent = list.length === keep.length ? 'Nenhuma porta repetida em linhas anteriores.' : 'Removidas ' + (list.length - keep.length) + ' porta(s) repetida(s).';
        setRowPorts(modalRow, keep);
    });
    document.getElementById('pm-done').addEventListener('click', closeModal);
    document.getElementById('pm-close').addEventListener('click', closeModal);
    pm.addEventListener('click', e => { if (e.target === pm) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !pm.hidden) closeModal(); });

    body.querySelectorAll('.acl-row').forEach(bindRow);
    refreshMeta();

    document.getElementById('add-rule').addEventListener('click', () => {
        const idx = body.querySelectorAll('.acl-row').length;
        const html = tpl.replace(/__IDX__/g, String(idx));
        body.insertAdjacentHTML('beforeend', html);
        bindRow(body.lastElementChild);
        reindex();
        body.closest('.acl-wrap')?.scrollTo({ top: 1e6, behavior: 'smooth' });
    });

    const GEO_ISO = 'RU,CN,TR,BD,PK,IN,NP,RO,AF,IS,EE,SY,IR,SD,CU,NG,IQ';

    function applyPreset(preset, geoField) {
        body.innerHTML = '';
        preset.forEach((r, idx) => {
            const html = tpl.replace(/__IDX__/g, String(idx));
            body.insertAdjacentHTML('beforeend', html);
            const row = body.lastElementChild;
            row.querySelector('[name$="[policy]"]').value = r.policy;
            row.querySelector('[name$="[proto_list]"]').value = r.proto_list;
            row.querySelector('[name$="[dport_list]"]').value = r.dport_list;
            row.querySelector('[name$="[sport_list]"]').value = r.sport_list;
            row.querySelector('[name$="[sip_list]"]').value = r.sip_list;
            bindRow(row);
        });
        if (geoField) {
            const geo = document.querySelector('[name="geoip_list"]');
            if (geo) geo.value = GEO_ISO;
        }
        reindex();
    }

    document.getElementById('preset-theisle')?.addEventListener('click', () => {
        if (!confirm('Substituir ACL + GEOIP pelo preset The Isle (com GEO block)?')) return;
        applyPreset([
            { policy: 'allowlist', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '187.94.104.229' },
            { policy: 'geo', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '' },
            { policy: 'tcp-server', proto_list: 'tcp', dport_list: '2022', sport_list: '', sip_list: '' },
            { policy: 'tcp-server', proto_list: 'tcp', dport_list: '8080', sport_list: '', sip_list: '' },
            { policy: 'ratelimiter-medium', proto_list: 'udp', dport_list: '7707,7777,7778,7779', sport_list: '', sip_list: '' },
            { policy: 'ratelimiter-medium', proto_list: 'tcp', dport_list: '7707,7777,7778,7779,17707', sport_list: '', sip_list: '' },
            { policy: 'DROP', proto_list: 'icmp', dport_list: '', sport_list: '', sip_list: '' },
            { policy: 'DROP', proto_list: 'tcp', dport_list: '22', sport_list: '', sip_list: '' },
            { policy: 'dns-trust', proto_list: 'udp,tcp', dport_list: '', sport_list: '53', sip_list: '' },
            { policy: 'ratelimiter-high', proto_list: 'udp', dport_list: '', sport_list: '', sip_list: '' },
            { policy: 'ratelimiter-high', proto_list: 'tcp', dport_list: '', sport_list: '', sip_list: '' },
        ], true);
    });

    document.getElementById('preset-geo')?.addEventListener('click', () => {
        if (!confirm('Inserir allowlist + geo no topo e preencher GEOIP? (demais regras mantidas)')) return;
        document.querySelector('[name="geoip_list"]').value = GEO_ISO;
        const rows = [...body.querySelectorAll('.acl-row')];
        const filtered = rows.filter(row => row.querySelector('[name$="[policy]"]')?.value !== 'geo');
        body.innerHTML = '';
        [
            { policy: 'allowlist', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '187.94.104.229' },
            { policy: 'geo', proto_list: 'any', dport_list: '', sport_list: '', sip_list: '' },
        ].forEach((r, idx) => {
            const html = tpl.replace(/__IDX__/g, String(idx));
            body.insertAdjacentHTML('beforeend', html);
            const row = body.lastElementChild;
            row.querySelector('[name$="[policy]"]').value = r.policy;
            row.querySelector('[name$="[proto_list]"]').value = r.proto_list;
            row.querySelector('[name$="[dport_list]"]').value = r.dport_list;
            row.querySelector('[name$="[sport_list]"]').value = r.sport_list;
            row.querySelector('[name$="[sip_list]"]').value = r.sip_list;
            bindRow(row);
        });
        filtered.forEach((row, i) => {
            body.appendChild(row);
            row.querySelectorAll('[name^="acl["]').forEach(el => {
                el.name = el.name.replace(/acl\[\d+\]/, 'acl[' + (i + 2) + ']');
            });
            bindRow(row);
        });
        reindex();
    });
})();
</script>
@endsection
