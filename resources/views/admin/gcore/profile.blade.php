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
    .port-chips, .sip-chips { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 3px; height: 26px; padding: 0 4px; border: 1px solid transparent; border-radius: 3px; cursor: pointer; overflow: hidden; }
    .chips-track { display: flex; flex-wrap: nowrap; gap: 3px; min-width: 0; overflow: hidden; }
    .pchip[hidden] { display: none; }
    .port-chips:hover, .port-chips:focus { border-color: var(--accent); outline: none; background: var(--bg); }
    .port-chips .empty, .sip-chips .empty { color: var(--muted); font-style: italic; white-space: nowrap; overflow: hidden; }
    .pchip { display: inline-flex; align-items: center; gap: 3px; height: 18px; padding: 0 6px; border-radius: 9px; font-family: var(--mono); font-size: 10px; border: 1px solid var(--border); background: var(--surface2); color: var(--text); white-space: nowrap; }
    .pchip.st-ok { border-color: rgba(16,185,129,.55); }
    .pchip.st-manual { color: var(--muted); border-style: dashed; }
    .pchip.st-warn { border-color: rgba(245,158,11,.7); color: #fcd34d; }
    .pchip.st-err { border-color: rgba(239,68,68,.7); color: #fca5a5; }
    .pchip.pinned::before { content: '📌'; font-size: 9px; }
    .pm-list .flags .act { display: inline-block; margin-top: 3px; height: 22px; padding: 0 8px; font-size: 11px; }
    .col-sip { min-width: 180px; }
    .col-ports { min-width: 120px; }
    .sip-chips:hover, .sip-chips:focus { border-color: var(--accent); outline: none; background: var(--bg); }
    .sip-chips .empty { color: var(--muted); font-style: italic; }
    .pchip.more { background: transparent; color: var(--muted); }
    .pchip-edit { color: var(--muted); font-size: 11px; padding: 0 2px; white-space: nowrap; }
    .col-dports { width: 40%; }
    .col-sip { width: 18%; }
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
    .layout-stack { display: flex; flex-direction: column; gap: 10px; }
    .bottom-panels { display: grid; grid-template-columns: 220px 260px minmax(0, 1fr); gap: 10px; align-items: start; }
    @media (max-width: 900px) { .bottom-panels { grid-template-columns: 1fr; } }
    .bottom-panels .json-panel pre { max-height: 260px; }
    .col-desc { width: 16%; min-width: 140px; }
    table.data input.desc { font-family: var(--font); font-size: 11px; }
    .pchip.iplist { border-color: rgba(59,130,246,.6); color: #93c5fd; }
    .pchip.iplist.bad { border-color: rgba(239,68,68,.7); color: #fca5a5; }
    .sm-lists { padding: 8px 12px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 6px 14px; align-items: center; }
    .sm-lists[hidden] { display: none; }
    .sm-lists .ttl { width: 100%; color: var(--muted); font-size: 11px; }
    .sm-lists label { display: inline-flex; gap: 5px; align-items: center; font-weight: normal; margin: 0; cursor: pointer; }
    .sm-lists label.off { opacity: .5; cursor: not-allowed; }
    .sm-lists .cnt { color: var(--muted); font-family: var(--mono); font-size: 10px; }
</style>
@php
    $id = (int) $profile['id'];
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
        <span class="chip {{ $stClass }}" id="profile-status">{{ $st }}</span>
        <span id="profile-sync" data-url="{{ route('admin.gcore.live.profile', $id) }}" data-age="{{ (int) max(0, $fetchedAt->diffInSeconds(now())) }}">
            @if($fetchedAt->diffInSeconds(now()) < 20)
                atualizado agora
            @else
                cópia de {{ $fetchedAt->diffForHumans() }} · <span class="sync-state">conferindo com a Gcore…</span>
            @endif
        </span>
    </div>
    <div class="flash flash-err" id="profile-changed" style="border-radius:6px;margin-bottom:8px" hidden></div>

    @if($error)
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">{{ $error }}</div>
    @endif
    @if($lostNotes > 0)
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">
            {{ $lostNotes }} descrição(ões) não puderam ser associadas a nenhuma regra (política, proto, sport ou sip mudaram fora do painel) e serão descartadas ao salvar.
        </div>
    @endif
    @php
        $listFailed = $listChecks->filter(fn ($b) => ($b->verify_missing ?? 0) > 0);
        $listOldest = $listChecks->pluck('verified_at')->filter()->sort()->first();
    @endphp
    @foreach($listFailed as $b)
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">
            Regra {{ $b->policy }} ({{ implode(',', $b->proto_list ?? []) ?: 'any' }}): última conferência na Gcore, {{ $b->verified_at?->diffForHumans() }}, encontrou faixas faltando. {{ $b->verify_message }}
            O painel reenvia automaticamente a cada 15 min.
        </div>
    @endforeach
    @if($listChecks->isNotEmpty() && $listFailed->isEmpty())
        <div class="meta-row" style="margin-bottom:8px">
            Listas de IP: {{ $listChecks->count() }} regra(s), {{ $listChecks->sum('verify_checked') }} faixa(s)
            @if($listChecks->contains(fn ($b) => $b->verified_at === null || $b->verify_missing === null))
                <span style="color:#fcd34d">⚠ conferência pendente</span>
            @else
                <span style="color:#6ee7b7">✓ conferidas na Gcore</span>
            @endif
            @if($listOldest)<span title="{{ $listOldest }}">{{ $listOldest->diffForHumans() }}</span>@endif
        </div>
    @endif
    @if($orphanBindings > 0)
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">
            {{ $orphanBindings }} regra(s) ligada(s) a listas de IP não foram encontradas neste perfil (alteradas fora do painel?).
            A ligação será descartada ao salvar; marque as listas de novo na regra certa.
        </div>
    @endif

    <form method="post" action="{{ route('admin.gcore.profile.update', $id) }}" id="profile-form">
        @csrf
        <input type="hidden" name="port_overrides" id="port-overrides" value="">
        <input type="hidden" name="form_hash" value="{{ $formHash }}">
        <div class="toolbar">
            <button type="submit" class="btn btn-primary">Salvar na Gcore</button>
            <button type="button" class="btn" id="add-rule">+ Regra</button>
            <button type="button" class="btn btn-ghost" id="preset-theisle">Preset The Isle</button>
            <button type="button" class="btn btn-ghost" id="preset-geo">Preset GEO block</button>
            <a class="btn btn-ghost" href="{{ route('admin.gcore.ip-lists') }}">Listas de IP</a>
            <span class="toolbar-spacer"></span>
            <span class="toolbar-hint"><strong id="rule-count">{{ $ruleCount }}</strong> regras · ordem = prioridade · <code>dport</code> = porta no servidor · clique nas portas para editar<span class="warn-total" id="warn-total"></span></span>
        </div>

        <div class="layout-stack">

            <div class="acl-wrap">
                <table class="data" id="acl-table">
                    <thead>
                    <tr>
                        <th class="col-idx">#</th>
                        <th class="col-ops">Ordem</th>
                        <th class="col-desc">Descrição</th>
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
                            <td class="col-desc"><input class="desc" name="acl[{{ $i }}][description]" value="{{ $rule['description'] ?? '' }}" maxlength="160" placeholder="ex.: Liberar Cloudflare"></td>
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
                            <td class="col-ports">
                                <input type="hidden" name="acl[{{ $i }}][sport_list]" value="{{ implode(',', $rule['sport_list'] ?? []) }}">
                                <div class="sip-chips sport-chips" tabindex="0" title="Editar portas de origem"></div>
                            </td>
                            <td class="col-sip">
                                <input type="hidden" name="acl[{{ $i }}][sip_list]" value="{{ implode(',', $rule['sip_list'] ?? []) }}">
                                <input type="hidden" name="acl[{{ $i }}][ip_lists]" value="{{ implode(',', $rule['ip_lists'] ?? []) }}">
                                <div class="sip-chips" tabindex="0" title="Editar IPs de origem"></div>
                            </td>
                            <td><button type="button" class="btn btn-icon del-rule" title="Remover">×</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="bottom-panels">
                <div class="panel">
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
                <details class="panel json-panel" id="json-panel">
                    <summary class="panel-head" style="cursor:pointer">JSON ACL (preview: clique para gerar)</summary>
                    <pre><code id="json-preview"></code></pre>
                </details>
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
            <td class="col-desc"><input class="desc" name="acl[__IDX__][description]" value="" maxlength="160" placeholder="ex.: Liberar Cloudflare"></td>
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
            <td class="col-ports">
                <input type="hidden" name="acl[__IDX__][sport_list]" value="">
                <div class="sip-chips sport-chips" tabindex="0" title="Editar portas de origem"></div>
            </td>
            <td class="col-sip">
                <input type="hidden" name="acl[__IDX__][sip_list]" value="">
                <input type="hidden" name="acl[__IDX__][ip_lists]" value="">
                <div class="sip-chips" tabindex="0" title="Editar IPs de origem"></div>
            </td>
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

    <div class="pm-backdrop" id="sm" hidden>
        <div class="pm-dialog" role="dialog" aria-modal="true" aria-labelledby="sm-title">
            <div class="pm-head">
                <h4 id="sm-title">IPs de origem</h4>
                <span class="sub" id="sm-sub"></span>
                <button type="button" class="btn btn-icon btn-ghost x" id="sm-close" title="Fechar">×</button>
            </div>
            <div class="pm-add">
                <input type="text" id="sm-input" placeholder="187.94.104.229, 10.0.0.0/24, 2001:db8::/32" autocomplete="off">
                <button type="button" class="btn btn-primary" id="sm-add">Adicionar</button>
                <span class="hint" id="sm-hint"></span>
            </div>
            <div class="sm-lists" id="sm-lists" hidden></div>
            <div class="pm-msg" id="sm-msg"></div>
            <div class="pm-list">
                <table>
                    <thead><tr><th id="sm-col">IP / CIDR</th><th>Descrição</th><th>Status</th><th></th></tr></thead>
                    <tbody id="sm-body"></tbody>
                </table>
            </div>
            <div class="pm-foot">
                <button type="button" class="btn" id="sm-sort">Ordenar</button>
                <button type="button" class="btn" id="sm-rm-bad">Remover inválidos/repetidos</button>
                <span class="spacer"></span>
                <span class="toolbar-hint">As alterações só vão para a Gcore ao clicar em “Salvar na Gcore”.</span>
                <button type="button" class="btn btn-primary" id="sm-done">Concluir</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const PORT_MAP = @json((object) $portMap);
    // Remote IP lists (admin → Gcore → Listas de IP): id => {id, name, count, entries}.
    // entries arrive later (loadListEntries): they can be thousands of CIDRs.
    const IP_LISTS = Object.fromEntries(@json($ipLists).map(l => [String(l.id), l]));
    let listEntriesLoaded = null;
    function loadListEntries() {
        listEntriesLoaded ??= fetch(@json(route('admin.gcore.live.ip-list-entries')), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('HTTP ' + r.status)))
            .then(j => { Object.entries(j.lists || {}).forEach(([id, entries]) => { if (IP_LISTS[id]) IP_LISTS[id].entries = entries; }); })
            .catch(e => { listEntriesLoaded = null; throw e; });
        return listEntriesLoaded;
    }
    const MAX_CHIPS = 60; // rendered per cell; fitChips() hides what does not fit the width

    // One line per cell: chips that do not fit are hidden and counted in "+N".
    function setChipCell(box, chipsHtml, extra, label) {
        box.innerHTML = '<span class="chips-track">' + chipsHtml + '</span><span class="pchip more" hidden></span><span class="pchip-edit">' + label + '</span>';
        box.dataset.extra = String(extra);
        fitChips(box);
    }

    function fitChips(box) {
        const track = box.querySelector('.chips-track');
        const more = box.querySelector('.pchip.more');
        if (!track || !more) return;
        const chips = [...track.children];
        chips.forEach(c => { c.hidden = false; });
        let hiddenCount = Number(box.dataset.extra || 0);
        more.textContent = '+' + hiddenCount;
        more.hidden = hiddenCount === 0;
        if (track.clientWidth === 0) return; // not laid out (hidden tab / tests)
        for (let i = chips.length - 1; i > 0 && track.scrollWidth > track.clientWidth; i--) {
            chips[i].hidden = true;
            hiddenCount++;
            more.textContent = '+' + hiddenCount;
            more.hidden = false;
        }
    }

    let fitTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(fitTimer);
        fitTimer = setTimeout(() => document.querySelectorAll('.port-chips, .sip-chips').forEach(fitChips), 120);
    });
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
            // Only "has any source IP" matters here; avoid expanding the lists on every keystroke.
            sip: splitList(row.querySelector('[name$="[sip_list]"]')?.value || '').concat(ruleListIds(row)),
            sport: parsePorts(g('sport_list')),
        };
    }

    const ruleListIds = row => splitList(row.querySelector('[name$="[ip_lists]"]')?.value || '');

    // What Gcore receives: manual IPs + CIDRs of every selected list.
    function effectiveSips(row) {
        const manual = splitList(row.querySelector('[name$="[sip_list]"]')?.value || '');
        const out = new Set(manual);
        ruleListIds(row).forEach(id => (IP_LISTS[id]?.entries || []).forEach(c => out.add(c)));
        return [...out];
    }

    // Only tcp/udp carry ports; "any" = both. icmp/gre/esp rules never match a dport.
    const portProtos = list => list.includes('any') ? ['tcp', 'udp'] : list.filter(p => p === 'tcp' || p === 'udp');
    const protosOverlap = (a, b) => { const x = portProtos(a), y = portProtos(b); return x.some(p => y.includes(p)); };

    // Per-port overrides edited in this session: port => {policy, proto} | {clear: true}.
    const pendingOverrides = {};
    const overridesInput = document.getElementById('port-overrides');

    function expectedFor(server, port) {
        const pend = pendingOverrides[port];
        if (pend && pend.clear) return { policy: server.egg_policy, proto: server.egg_proto, source: 'pending-clear' };
        if (pend) return { policy: pend.policy, proto: pend.proto, source: 'pending' };
        return { policy: server.policy, proto: server.proto, source: server.override ? 'override' : 'egg' };
    }

    function syncOverridesInput() {
        overridesInput.value = JSON.stringify(Object.entries(pendingOverrides).map(([port, o]) =>
            o.clear ? { port: Number(port), clear: true } : { port: Number(port), policy: o.policy, proto: o.proto }));
    }

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
                let pinned = false;
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
                        const exp = expectedFor(s, port);
                        const eggTxt = (s.egg_policy || 'sem perfil') + '/' + (s.egg_proto || 'any');
                        const expTxt = exp.policy + '/' + (exp.proto || 'any');
                        if (exp.source === 'pending-clear') {
                            flags.push({ cls: 'f-muted', text: 'Exceção será removida ao salvar (volta para o egg: ' + eggTxt + ')' });
                        }
                        if (!exp.policy) {
                            bump('warn');
                            flags.push({ cls: 'f-warn', text: 'Slot sem perfil Gcore no egg (o sync remove)', pin: true });
                        } else if (exp.policy !== rule.policy || (exp.proto && !protosOverlap([exp.proto], rule.protos))) {
                            bump('err');
                            flags.push({
                                cls: 'f-err',
                                text: (exp.source === 'egg' || exp.source === 'pending-clear' ? 'Egg espera ' : 'Exceção fixada em ') + expTxt + ' (o sync move)',
                                pin: true,
                                unpin: exp.source === 'override' || exp.source === 'pending',
                            });
                        } else if (exp.source === 'override') {
                            pinned = true;
                            flags.push({ cls: 'f-ok', text: 'Fixada manualmente nesta regra (egg: ' + eggTxt + ')', unpin: true });
                        } else if (exp.source === 'pending') {
                            pinned = true;
                            flags.push({ cls: 'f-ok', text: 'Será fixada nesta regra ao salvar (egg: ' + eggTxt + ')', unpin: true });
                        } else {
                            flags.push({ cls: 'f-ok', text: 'Confere com o egg (' + expTxt + ')' });
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
                ports.push({ port, owners, entries, flags, level, pinned });
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
            box.innerHTML = '<span class="empty">sem portas</span><span></span><span class="pchip-edit">✎</span>';
            return;
        }
        const shown = ports.slice(0, MAX_CHIPS).map(p =>
            '<span class="pchip st-' + p.level + (p.pinned ? ' pinned' : '') + '" title="' + esc(p.port + ' — ' + ownerLabel(p) + (p.flags.length ? '\n' + p.flags.map(f => f.text).join('\n') : '')) + '">' + p.port + '</span>'
        ).join('');
        const warn = ports.filter(p => p.level === 'warn' || p.level === 'err').length;
        setChipCell(box, shown, Math.max(0, ports.length - MAX_CHIPS), ports.length + (warn ? ' · ⚠' + warn : '') + ' ✎');
    }

    let modalRow = null;

    function refreshMeta() {
        const { result, warnings } = analyze();
        result.forEach((ports, row) => { renderChips(row, ports); renderSipChips(row); });
        if (typeof sipRow !== 'undefined' && sipRow) renderSipModal();
        const wt = document.getElementById('warn-total');
        if (wt) wt.textContent = warnings ? '⚠ ' + warnings + ' porta(s) com aviso' : '';
        if (modalRow) renderModal(result.get(modalRow) || []);

        const rows = body.querySelectorAll('.acl-row');
        rows.forEach((row, idx) => {
            const el = row.querySelector('.row-idx');
            if (el) el.textContent = String(idx + 1);
        });
        if (countEl) countEl.textContent = String(rows.length);
        renderJson();
    }

    const jsonPanel = document.getElementById('json-panel');
    function renderJson() {
        if (!jsonEl || !jsonPanel?.open) return;
        const rows = body.querySelectorAll('.acl-row');
        const usesLists = [...rows].some(row => ruleListIds(row).some(id => IP_LISTS[id] && !IP_LISTS[id].entries));
        if (usesLists) {
            jsonEl.textContent = 'Carregando as entradas das listas de IP…';
            loadListEntries().then(renderJson, e => { jsonEl.textContent = 'Erro ao carregar as listas de IP: ' + e.message; });
            return;
        }
        {
            const rules = [];
            rows.forEach(row => {
                const g = n => row.querySelector('[name$="[' + n + ']"]')?.value?.trim() || '';
                const split = s => s ? s.split(/[\s,;]+/).filter(Boolean) : [];
                const policy = g('policy');
                if (!policy) return;
                rules.push({
                    policy,
                    sip_list: effectiveSips(row),
                    dport_list: split(g('dport_list')).map(Number).filter(n => n >= 1 && n <= 65535),
                    proto_list: split(g('proto_list')),
                    sport_list: split(g('sport_list')).map(Number).filter(n => n >= 1 && n <= 65535),
                });
            });
            jsonEl.textContent = JSON.stringify(rules, null, 2);
        }
    }
    jsonPanel?.addEventListener('toggle', renderJson);

    // Typing (descriptions, proto) re-analyses every row: coalesce bursts of input events.
    let metaTimer = null;
    function refreshMetaSoon() {
        clearTimeout(metaTimer);
        metaTimer = setTimeout(refreshMeta, 150);
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
        row.querySelectorAll('input,select').forEach(el => el.addEventListener('input', refreshMetaSoon));
        const chips = row.querySelector('.port-chips');
        chips?.addEventListener('click', () => openModal(row));
        chips?.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openModal(row); } });
        [['.sip-chips:not(.sport-chips)', 'sip'], ['.sport-chips', 'sport']].forEach(([sel, kind]) => {
            const el = row.querySelector(sel);
            el?.addEventListener('click', () => openSip(row, kind));
            el?.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openSip(row, kind); } });
        });
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
            let flags = p.flags.map(f => '<span class="' + f.cls + '">' + esc(f.text) + '</span>').join('');
            if (p.owners.length && p.flags.some(f => f.pin)) {
                flags += '<button type="button" class="btn act pm-pin" data-port="' + p.port + '" title="Grava uma exceção: o sync passa a manter esta porta nesta regra">📌 Fixar nesta regra</button> ';
            }
            if (p.flags.some(f => f.unpin)) {
                flags += '<button type="button" class="btn btn-ghost act pm-unpin" data-port="' + p.port + '" title="Volta a seguir o perfil do egg">Desfazer exceção</button>';
            }
            return '<tr><td class="port"><span class="pchip st-' + p.level + (p.pinned ? ' pinned' : '') + '">' + p.port + '</span></td>'
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
        const pin = e.target.closest('.pm-pin');
        const unpin = e.target.closest('.pm-unpin');
        if ((pin || unpin) && modalRow) {
            const port = Number((pin || unpin).dataset.port);
            if (pin) {
                const info = ruleInfo(modalRow);
                const pp = portProtos(info.protos);
                pendingOverrides[port] = { policy: info.policy, proto: pp.length === 1 ? pp[0] : null };
                pmMsg.textContent = 'Porta ' + port + ' será fixada em ' + info.policy + '/' + (pp.length === 1 ? pp[0] : 'any') + ' ao salvar.';
            } else {
                const owners = (PORT_MAP[port] || []).filter(x => x.server);
                const saved = owners.some(x => x.server.override);
                if (saved) pendingOverrides[port] = { clear: true }; else delete pendingOverrides[port];
                pmMsg.textContent = saved ? 'A exceção da porta ' + port + ' será removida ao salvar.' : 'Exceção da porta ' + port + ' descartada.';
            }
            syncOverridesInput();
            refreshMeta();
            return;
        }
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
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (!pm.hidden) closeModal();
        if (!sm.hidden) closeSip();
    });

    // ---- List editor for sip (source IP/CIDR) and sport (source port) ----
    const sm = document.getElementById('sm');
    const smBody = document.getElementById('sm-body');
    const smInput = document.getElementById('sm-input');
    const smMsg = document.getElementById('sm-msg');
    let sipRow = null;
    let listKind = 'sip';

    function parseCidr(raw) {
        const v = String(raw).trim();
        const [addr, mask, extra] = v.split('/');
        if (extra !== undefined || !addr) return null;
        const v4 = addr.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
        if (v4) {
            if (v4.slice(1).some(o => Number(o) > 255)) return null;
            if (mask !== undefined && !/^\d{1,2}$/.test(mask)) return null;
            const m = mask === undefined ? 32 : Number(mask);
            if (m > 32) return null;
            return { family: 4, addr, mask: m, key: addr + '/' + m };
        }
        if (addr.includes(':')) {
            try { new URL('http://[' + addr + ']'); } catch (e) { return null; }
            if (mask !== undefined && !/^\d{1,3}$/.test(mask)) return null;
            const m = mask === undefined ? 128 : Number(mask);
            if (m > 128) return null;
            return { family: 6, addr: addr.toLowerCase(), mask: m, key: addr.toLowerCase() + '/' + m };
        }
        return null;
    }

    const WELL_KNOWN = { 20: 'FTP data', 21: 'FTP', 22: 'SSH', 25: 'SMTP', 53: 'DNS', 67: 'DHCP', 68: 'DHCP', 80: 'HTTP', 123: 'NTP', 443: 'HTTPS', 3478: 'STUN', 27015: 'Steam/Source' };

    const LIST_KINDS = {
        sip: {
            field: 'sip_list',
            title: 'IPs de origem',
            col: 'IP / CIDR',
            placeholder: '187.94.104.229, 10.0.0.0/24, 2001:db8::/32',
            hint: 'IPv4 ou IPv6, com ou sem /máscara. Lista separada por vírgula/espaço/linha. Vazio = qualquer origem.',
            empty: 'Sem IPs: a regra vale para qualquer origem.',
            chipEmpty: 'qualquer',
            maxChips: 4,
            parse: v => { const c = parseCidr(v); return c ? { key: c.key, info: c } : null; },
            describe: c => {
                if (c.mask === 0) return 'Qualquer IPv' + c.family;
                if ((c.family === 4 && c.mask === 32) || (c.family === 6 && c.mask === 128)) return '1 host (IPv' + c.family + ')';
                if (c.family === 4) return (2 ** (32 - c.mask)).toLocaleString('pt-BR') + ' endereços (/' + c.mask + ')';
                return 'Rede IPv6 /' + c.mask;
            },
            extraFlags: c => c.mask === 0 ? [{ cls: 'f-warn', text: 'Libera qualquer origem (regra fixa: o sync nunca mexe)', warn: true }] : [],
            sortKey: c => [c.family, c.family === 4 ? c.addr.split('.').map(o => o.padStart(3, '0')).join('.') : c.addr, c.mask],
            invalid: 'Inválido (não é IP/CIDR)',
        },
        sport: {
            field: 'sport_list',
            title: 'Portas de origem (sport)',
            col: 'Porta',
            placeholder: '53, 80, 443, 27009-27014',
            hint: 'Porta de ORIGEM do pacote (ex.: respostas de DNS vêm da 53). Aceita faixas a-b (máx. 1000). Vazio = qualquer.',
            empty: 'Sem sport: a regra não filtra a porta de origem.',
            chipEmpty: '—',
            maxChips: 6,
            expand: tok => {
                const m = String(tok).match(/^(\d+)-(\d+)$/);
                if (!m) return [tok];
                let a = Number(m[1]), b = Number(m[2]);
                if (a > b) [a, b] = [b, a];
                if (a < 1 || b > 65535 || b - a >= 1000) return [tok];
                const out = [];
                for (let p = a; p <= b; p++) out.push(String(p));
                return out;
            },
            parse: v => { const n = Number(v); return /^\d+$/.test(String(v)) && n >= 1 && n <= 65535 ? { key: String(n), info: n } : null; },
            describe: n => WELL_KNOWN[n] ? WELL_KNOWN[n] : (n < 1024 ? 'Porta de sistema' : 'Porta'),
            extraFlags: () => [],
            sortKey: n => [n, 0, 0],
            invalid: 'Inválido (não é porta 1–65535)',
        },
    };

    const listInput = (row, kind) => row.querySelector('[name$="[' + LIST_KINDS[kind].field + ']"]');

    function analyzeList(row, kind) {
        const K = LIST_KINDS[kind];
        const seen = new Set();
        return splitList(listInput(row, kind).value).map(v => {
            const parsed = K.parse(v);
            const flags = [];
            let level = 'ok';
            if (!parsed) { level = 'err'; flags.push({ cls: 'f-err', text: K.invalid }); }
            else {
                if (seen.has(parsed.key)) { level = 'warn'; flags.push({ cls: 'f-warn', text: 'Repetido nesta regra', dup: true }); }
                seen.add(parsed.key);
                const extra = K.extraFlags(parsed.info);
                if (extra.length) { if (level === 'ok') level = 'warn'; flags.push(...extra); }
                if (flags.length === 0) flags.push({ cls: 'f-ok', text: 'OK' });
            }
            return { value: v, parsed, flags, level };
        });
    }

    function listChips(row) {
        return ruleListIds(row).map(id => {
            const l = IP_LISTS[id];
            if (!l) return '<span class="pchip iplist bad" title="Lista excluída">lista #' + esc(id) + '</span>';
            return '<span class="pchip iplist' + (l.count ? '' : ' bad') + '" title="' + esc('Lista de IP: ' + l.name + ' (' + l.count + ' entradas, atualizada automaticamente)') + '">📋 ' + esc(l.name) + ' (' + l.count + ')</span>';
        });
    }

    function renderListChips(row, kind, box) {
        if (!box) return;
        const K = LIST_KINDS[kind];
        const items = analyzeList(row, kind);
        const lists = kind === 'sip' ? listChips(row) : [];
        if (items.length === 0 && lists.length === 0) {
            box.innerHTML = '<span class="empty">' + K.chipEmpty + '</span><span></span><span class="pchip-edit">✎</span>';
            return;
        }
        const shown = lists.join('') + items.slice(0, MAX_CHIPS).map(i => '<span class="pchip st-' + i.level + '" title="' + esc(i.value + ' — ' + (i.parsed ? K.describe(i.parsed.info) : K.invalid)) + '">' + esc(i.value) + '</span>').join('');
        const bad = items.filter(i => i.level !== 'ok').length;
        const label = (lists.length ? lists.length + ' lista(s)' + (items.length ? ' + ' : '') : '') + (items.length || !lists.length ? items.length : '');
        setChipCell(box, shown, Math.max(0, items.length - MAX_CHIPS), label + (bad ? ' · ⚠' + bad : '') + ' ✎');
    }

    function renderSipChips(row) {
        renderListChips(row, 'sip', row.querySelector('.sip-chips:not(.sport-chips)'));
        renderListChips(row, 'sport', row.querySelector('.sport-chips'));
    }

    function renderSipModal() {
        if (!sipRow) return;
        const K = LIST_KINDS[listKind];
        const info = ruleInfo(sipRow);
        const idx = [...body.querySelectorAll('.acl-row')].indexOf(sipRow) + 1;
        const items = analyzeList(sipRow, listKind);
        document.getElementById('sm-title').textContent = 'Linha ' + idx + ' · ' + (info.policy || '—') + ' · ' + K.title;
        document.getElementById('sm-sub').textContent = info.protos.join(',') + (info.dports.length ? ' · dport ' + info.dports.slice(0, 5).join(',') + (info.dports.length > 5 ? '…' : '') : ' · todas as portas') + ' · ' + items.length + ' item(s)';
        document.getElementById('sm-col').textContent = K.col;
        renderListPicker();
        const hasLists = listKind === 'sip' && ruleListIds(sipRow).length > 0;
        smBody.innerHTML = items.length === 0
            ? '<tr><td colspan="4" style="color:var(--muted);padding:14px 8px">' + esc(hasLists ? 'Sem IPs manuais: só as listas selecionadas acima.' : K.empty) + '</td></tr>'
            : items.map((i, n) => '<tr><td class="port"><span class="pchip st-' + i.level + '">' + esc(i.value) + '</span></td>'
                + '<td class="owner"><span class="meta">' + esc(i.parsed ? K.describe(i.parsed.info) : '—') + '</span></td>'
                + '<td class="flags">' + i.flags.map(f => '<span class="' + f.cls + '">' + esc(f.text) + '</span>').join('') + '</td>'
                + '<td class="del"><button type="button" class="btn btn-icon sm-del" data-idx="' + n + '" title="Remover">×</button></td></tr>').join('');
    }

    const smLists = document.getElementById('sm-lists');

    function renderListPicker() {
        const all = Object.values(IP_LISTS);
        smLists.hidden = listKind !== 'sip';
        if (smLists.hidden) return;
        if (all.length === 0) {
            smLists.innerHTML = '<span class="ttl">Nenhuma lista de IP cadastrada. <a href="{{ route('admin.gcore.ip-lists') }}" target="_blank">Cadastrar listas →</a></span>';
            return;
        }
        const selected = new Set(ruleListIds(sipRow));
        smLists.innerHTML = '<span class="ttl">Listas de IP (atualizadas automaticamente; somadas aos IPs manuais abaixo)</span>'
            + all.map(l => {
                const off = !l.count && !selected.has(String(l.id));
                const sample = l.entries ? l.entries.slice(0, 20).join(', ') + (l.entries.length > 20 ? '…' : '') : l.count + ' entradas';
                return '<label class="' + (off ? 'off' : '') + '" title="' + esc(off ? 'Lista ainda sem entradas' : sample) + '">'
                    + '<input type="checkbox" class="sm-list" value="' + esc(l.id) + '"' + (selected.has(String(l.id)) ? ' checked' : '') + (off ? ' disabled' : '') + '> '
                    + esc(l.name) + ' <span class="cnt">' + l.count + '</span></label>';
            }).join('');
    }

    smLists.addEventListener('change', e => {
        if (!e.target.classList.contains('sm-list') || !sipRow) return;
        const ids = [...smLists.querySelectorAll('.sm-list:checked')].map(c => c.value);
        sipRow.querySelector('[name$="[ip_lists]"]').value = ids.join(',');
        refreshMeta();
    });

    function setList(row, list) {
        listInput(row, listKind).value = list.join(',');
        refreshMeta();
    }

    function openSip(row, kind) {
        sipRow = row;
        listKind = kind || 'sip';
        const K = LIST_KINDS[listKind];
        smMsg.textContent = '';
        smInput.value = '';
        smInput.placeholder = K.placeholder;
        document.getElementById('sm-hint').textContent = K.hint;
        sm.hidden = false;
        renderSipModal();
        if (listKind === 'sip') loadListEntries().then(() => { if (sipRow === row) renderListPicker(); }, () => {});
        smInput.focus();
    }

    function closeSip() {
        sipRow = null;
        sm.hidden = true;
    }

    function addSipFromInput() {
        if (!sipRow) return;
        const K = LIST_KINDS[listKind];
        const current = splitList(listInput(sipRow, listKind).value);
        const keys = new Set(current.map(v => K.parse(v)?.key || v));
        const bad = [];
        let dup = 0;
        const added = [];
        splitList(smInput.value).flatMap(t => K.expand ? K.expand(t) : [t]).forEach(v => {
            const parsed = K.parse(v);
            if (!parsed) { bad.push(v); return; }
            if (keys.has(parsed.key)) { dup++; return; }
            keys.add(parsed.key);
            added.push(v);
        });
        smMsg.textContent = (bad.length ? 'Ignorado (inválido): ' + bad.join(', ') + '. ' : '') + (dup ? dup + ' já estava(m) na regra.' : '');
        smInput.value = '';
        setList(sipRow, current.concat(added));
    }

    document.getElementById('sm-add').addEventListener('click', addSipFromInput);
    smInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addSipFromInput(); } });
    smInput.addEventListener('paste', () => setTimeout(() => { if (/[\s,;]/.test(smInput.value.trim())) addSipFromInput(); }, 0));
    smBody.addEventListener('click', e => {
        const btn = e.target.closest('.sm-del');
        if (!btn || !sipRow) return;
        const list = splitList(listInput(sipRow, listKind).value);
        list.splice(Number(btn.dataset.idx), 1);
        setList(sipRow, list);
    });
    document.getElementById('sm-sort').addEventListener('click', () => {
        if (!sipRow) return;
        const K = LIST_KINDS[listKind];
        const list = splitList(listInput(sipRow, listKind).value);
        const k = v => { const p = K.parse(v); return p ? K.sortKey(p.info) : [Infinity, v, 0]; };
        list.sort((a, b) => { const x = k(a), y = k(b); for (let i = 0; i < 3; i++) { if (x[i] < y[i]) return -1; if (x[i] > y[i]) return 1; } return 0; });
        setList(sipRow, list);
    });
    document.getElementById('sm-rm-bad').addEventListener('click', () => {
        if (!sipRow) return;
        const items = analyzeList(sipRow, listKind);
        const keep = items.filter(i => i.parsed && !i.flags.some(f => f.dup)).map(i => i.value);
        smMsg.textContent = keep.length === items.length ? 'Nada para remover.' : 'Removido(s) ' + (items.length - keep.length) + '.';
        setList(sipRow, keep);
    });
    document.getElementById('sm-done').addEventListener('click', closeSip);
    document.getElementById('sm-close').addEventListener('click', closeSip);
    sm.addEventListener('click', e => { if (e.target === sm) closeSip(); });

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

    // ---- Background check against Gcore (the page may come from the cached copy) ----
    const form = document.getElementById('profile-form');
    const formSnapshot = () => new URLSearchParams(new FormData(form)).toString();
    const initialForm = formSnapshot();
    const syncEl = document.getElementById('profile-sync');
    const changedEl = document.getElementById('profile-changed');
    const loadedHash = form.querySelector('[name="form_hash"]').value;
    const syncState = t => { const el = syncEl.querySelector('.sync-state'); if (el) el.innerHTML = t; };
    if (Number(syncEl.dataset.age) >= 20) {
        fetch(syncEl.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(r => r.json().then(j => ({ ok: r.ok, j })))
            .then(({ ok, j }) => {
                if (!ok || j.error) throw new Error(j.error || 'erro ao consultar a Gcore');
                const status = document.getElementById('profile-status');
                status.textContent = j.status;
                status.className = 'chip ' + (j.pending ? 'status-pending' : 'status-updated');
                if (j.hash === loadedHash) {
                    syncState('<span style="color:#6ee7b7">✓ igual à Gcore</span>');
                    return;
                }
                if (formSnapshot() === initialForm) {
                    syncState('mudou na Gcore, recarregando…');
                    location.reload();
                    return;
                }
                syncState('<span style="color:#fca5a5">desatualizada</span>');
                changedEl.innerHTML = 'Este perfil mudou na Gcore depois que esta cópia foi lida. Salvar agora será recusado para não sobrescrever a mudança. '
                    + '<a href="' + location.pathname + '">Recarregar</a> (as alterações desta página serão perdidas).';
                changedEl.hidden = false;
            })
            .catch(e => syncState('<span style="color:#fcd34d">não foi possível conferir: ' + esc(e.message) + '</span>'));
    }
})();
</script>
@endsection
