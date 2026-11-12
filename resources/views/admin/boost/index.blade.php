@extends('layouts.admin')

@section('title')
    Server Boost
@endsection

@section('content-header')
    <h1>Server Boost<small>Jogadores enviados pelo sistema de boost de CS 1.6</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Server Boost</li>
    </ol>
@endsection

@section('content')
@include('admin.gcore.ip-lists._styles')
<style>
    .bst-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
    .bst-toolbar .chip { text-decoration: none; }
    .bst-toolbar .chip.on { border-color: var(--accent); background: rgba(59,130,246,.18); }
    .bst-toolbar select, .bst-toolbar input { background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 4px; height: 26px; padding: 0 8px; font-size: 12px; }
    .bst-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 10px; }
    @media (max-width: 900px) { .bst-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .bst-kpi { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; }
    .bst-kpi span { color: var(--muted); font-size: 11px; }
    .bst-kpi strong { font-size: 22px; font-weight: 600; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .bst-kpi em { color: var(--muted); font-size: 10px; font-style: normal; }
    .bst-charts { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 10px; }
    @media (max-width: 1100px) { .bst-charts { grid-template-columns: minmax(0, 1fr); } }
    .bst-cols { position: relative; height: 150px; display: flex; align-items: flex-end; gap: 2px; border-bottom: 1px solid var(--border); padding-top: 14px; }
    .bst-ymax { position: absolute; top: 0; left: 0; font-size: 10px; color: var(--muted); font-family: var(--mono); }
    .bst-col { flex: 1 1 0; height: 100%; display: flex; align-items: flex-end; cursor: default; }
    .bst-col:hover { background: rgba(59,130,246,.08); }
    .bst-bar { width: 100%; background: var(--accent); border-radius: 4px 4px 0 0; }
    .bst-col:hover .bst-bar { background: #60a5fa; }
    .bst-xaxis { display: flex; gap: 2px; margin-top: 4px; }
    .bst-xaxis span { flex: 1 1 0; font-size: 10px; color: var(--muted); font-family: var(--mono); white-space: nowrap; overflow: visible; }
    .bst-hrow { display: grid; grid-template-columns: minmax(0, 150px) minmax(0, 1fr) 48px; gap: 8px; align-items: center; padding: 4px 0; }
    .bst-hrow:hover { background: rgba(59,130,246,.06); }
    .bst-hname { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bst-htrack { height: 12px; }
    .bst-hbar { display: block; height: 100%; background: var(--accent); border-radius: 0 4px 4px 0; }
    .bst-hval { text-align: right; font-family: var(--mono); font-variant-numeric: tabular-nums; }
    .bst-tip { position: fixed; z-index: 1000; pointer-events: none; background: var(--surface2); border: 1px solid var(--border); color: var(--text); padding: 4px 8px; border-radius: 4px; font-size: 11px; white-space: nowrap; display: none; }
    .bst-url { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
    .bst-url input { flex: 1; min-width: 0; font-family: var(--mono); font-size: 11px; background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 4px; height: 26px; padding: 0 6px; }
    .bst-doc { font-family: var(--mono); font-size: 10.5px; line-height: 1.5; background: var(--surface2); border: 1px solid var(--border); border-radius: 4px; padding: 6px 8px; white-space: pre-wrap; word-break: break-all; margin: 6px 0; }
    .bst-eggs { max-height: 220px; overflow: auto; }
    .bst-bl { display: inline-flex; gap: 3px; align-items: center; color: var(--muted); }
    .bst-bl form { display: inline; margin: 0; }
    .bst-bl .btn { height: 20px; padding: 0 6px; font-size: 10px; }
    .bst-bl .btn:hover { border-color: rgba(239,68,68,.7); color: #fca5a5; }
    tr.bst-blocked td { background: rgba(239,68,68,.06); }
    .bst-eggs label { display: flex; gap: 6px; align-items: center; font-weight: normal; margin: 2px 0; }
</style>
<div class="gcore-ddos-wrap">
    <h1 class="page-title">Server Boost</h1>
    <p class="meta-row">Cada linha é um jogador que o sistema de boost mandou para um servidor (informado pelo endpoint <code>connect</code>). Guardamos {{ \Pterodactyl\Services\Boost\ServerBoostService::RETENTION_DAYS }} dias. · <a href="{{ route('admin.boost.blacklist') }}">Blacklist →</a></p>

    <form method="get" action="{{ route('admin.boost') }}" class="bst-toolbar" id="boost-filter">
        @foreach(['24h' => '24 horas', '7d' => '7 dias', '30d' => '30 dias'] as $key => $label)
            <a class="chip {{ $range === $key ? 'on' : '' }}" href="{{ route('admin.boost', array_filter(['range' => $key, 'server' => $serverId, 'q' => $search])) }}">{{ $label }}</a>
        @endforeach
        <input type="hidden" name="range" value="{{ $range }}">
        <select name="server" onchange="this.form.submit()">
            <option value="">Todos os servidores</option>
            @foreach($servers as $s)
                <option value="{{ $s->id }}" @selected($serverId === $s->id)>{{ $s->name }} · {{ \Pterodactyl\Services\Boost\ServerBoostService::address($s) }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Jogador, SteamID ou IP" style="width:200px">
        <button type="submit" class="btn">Filtrar</button>
        @if($serverId || $search)<a class="btn btn-ghost" href="{{ route('admin.boost', ['range' => $range]) }}">Limpar</a>@endif
    </form>

    <div id="boost-dash" data-url="{{ route('admin.boost.live', array_filter(['range' => $range, 'server' => $serverId, 'q' => $search])) }}">
        @include('admin.boost._dashboard')
    </div>
    <div class="bst-tip" id="boost-tip"></div>

    <h1 class="page-title" style="margin-top:18px">Integração</h1>
    <div class="ipl-grid" style="grid-template-columns:minmax(0,1fr) 320px">
        <div class="panel">
            <div class="panel-head">API do sistema de boost</div>
            <div class="panel-body">
                @if($token === null)
                    <p>Nenhum token gerado ainda: a API está desligada. Gere um token para obter as URLs.</p>
                @else
                    <label class="ipl-muted">Lista de servidores (GET · JSON, ou <code>?format=txt</code> para um ip:porta por linha)</label>
                    <div class="bst-url"><input type="text" readonly value="{{ $serversUrl }}" onclick="this.select()"><button type="button" class="btn" data-copy="{{ $serversUrl }}">Copiar</button></div>
                    <label class="ipl-muted">Jogador conectou (POST, ou GET com os mesmos parâmetros)</label>
                    <div class="bst-url"><input type="text" readonly value="{{ $connectUrl }}" onclick="this.select()"><button type="button" class="btn" data-copy="{{ $connectUrl }}">Copiar</button></div>
                    <details>
                        <summary class="ipl-muted" style="cursor:pointer">Formato das chamadas</summary>
                        <div class="bst-doc">GET …/servers
{"count": 2, "servers": [
  {"id": 325, "uuid": "…", "name": "…", "address": "89.39.161.172:27015",
   "ip": "89.39.161.172", "port": 27015, "node": "…"}, …]}

POST …/connect   (form ou JSON)
  server_id=325            id da lista, ou
  server=89.39.161.172:27015
  player=Nome do jogador   (opcional)
  player_ip=200.1.2.3      (opcional)
  steam_id=STEAM_0:1:123   (opcional)
→ 201 {"ok": true, "id": 1, "server_id": 325, "address": "…", "blacklisted": false}
→ 404 servidor fora da lista dos eggs do boost · 422 parâmetro inválido
GET …/blacklist   lista de bloqueio (veja a página Blacklist)
Token errado → 404. Limite: 600 chamadas por minuto por IP.</div>
                    </details>
                    <p class="ipl-muted" style="margin:6px 0 0">Fora da lista: servidores ocultos (abaixo ou em Manage do servidor), suspensos ou instalando.</p>
                @endif
                <form method="post" action="{{ route('admin.boost.token') }}" style="margin-top:8px" @if($token) onsubmit="return confirm('Gerar um novo token? As URLs atuais param de funcionar.')" @endif>
                    @csrf
                    <button type="submit" class="btn {{ $token ? '' : 'btn-primary' }}">{{ $token ? 'Gerar novo token' : 'Gerar token' }}</button>
                </form>
            </div>
        </div>
        <div>
        <div class="panel" style="margin-bottom:12px">
            <div class="panel-head">Painel do cliente</div>
            <div class="panel-body">
                <p style="margin:0 0 8px">
                    Aba <strong>Server Boost</strong> nos servidores dos eggs abaixo, com os jogadores que o boost mandou
                    para aquele servidor (nome, SteamID e horário; sem o IP do jogador).
                </p>
                <p style="margin:0 0 8px">
                    @if($clientTab)
                        <span class="ipl-st ok">donos e admins veem</span>
                    @else
                        <span class="ipl-st pending">só admins veem</span>
                    @endif
                </p>
                <form method="post" action="{{ route('admin.boost.client-tab') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="{{ $clientTab ? 0 : 1 }}">
                    <button type="submit" class="btn">{{ $clientTab ? 'Mostrar só para admins' : 'Mostrar para os donos' }}</button>
                </form>
            </div>
        </div>
        <div class="panel">
            <div class="panel-head">Eggs do boost</div>
            <div class="panel-body">
                <form method="post" action="{{ route('admin.boost.eggs') }}">
                    @csrf
                    <div class="bst-eggs">
                        @foreach($eggs as $egg)
                            <label><input type="checkbox" name="eggs[]" value="{{ $egg->id }}" @checked(in_array($egg->id, $eggIds, true))> {{ $egg->name }}</label>
                        @endforeach
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top:8px">Salvar</button>
                </form>
            </div>
        </div>
        </div>
    </div>

    <h1 class="page-title" style="margin-top:18px">Servidores ({{ $servers->count() }})</h1>
    <p class="meta-row">Servidores dos eggs marcados. Oculte os que não devem receber jogadores do boost.</p>
    <div class="acl-wrap" style="max-height:none;min-height:0">
        <table class="data">
            <thead><tr><th>Servidor</th><th>Endereço</th><th>Node</th><th>Na lista</th><th></th></tr></thead>
            <tbody>
            @forelse($servers as $s)
                @php $available = \Pterodactyl\Services\Boost\ServerBoostService::isAvailable($s); @endphp
                <tr>
                    <td><a href="{{ route('admin.servers.view', $s->id) }}">{{ $s->name }}</a></td>
                    <td class="ipl-mono">{{ \Pterodactyl\Services\Boost\ServerBoostService::address($s) }}</td>
                    <td>{{ $s->node?->name }}</td>
                    <td>
                        @if($s->boost_hidden)
                            <span class="ipl-st pending">oculto</span>
                        @elseif(!$available)
                            <span class="ipl-st pending" title="Volta para a lista sozinho quando deixar de estar {{ $s->status }}">{{ $s->status }}</span>
                        @else
                            <span class="ipl-st ok">sim</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <form method="post" action="{{ route('admin.boost.server', $s->id) }}" style="display:inline">
                            @csrf
                            <input type="hidden" name="hidden" value="{{ $s->boost_hidden ? 0 : 1 }}">
                            <input type="hidden" name="range" value="{{ $range }}">
                            <input type="hidden" name="server" value="{{ $serverId }}">
                            <button type="submit" class="btn">{{ $s->boost_hidden ? 'Mostrar na lista' : 'Ocultar da lista' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ipl-muted">Nenhum servidor nos eggs marcados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    const dash = document.getElementById('boost-dash');
    const tip = document.getElementById('boost-tip');

    document.addEventListener('mousemove', function (e) {
        const el = e.target.closest && e.target.closest('#boost-dash [data-tip]');
        if (!el) { tip.style.display = 'none'; return; }
        tip.textContent = el.dataset.tip;
        tip.style.display = 'block';
        const x = Math.min(e.clientX + 12, window.innerWidth - tip.offsetWidth - 8);
        tip.style.left = x + 'px';
        tip.style.top = (e.clientY - tip.offsetHeight - 10) + 'px';
    });

    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(btn.dataset.copy).then(function () {
                btn.textContent = 'Copiado';
                setTimeout(function () { btn.textContent = 'Copiar'; }, 1500);
            });
        });
    });

    setInterval(function () {
        if (document.hidden) return;
        fetch(dash.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (j) { dash.innerHTML = j.html; })
            .catch(function () {});
    }, 15000);
})();
</script>
@endsection
