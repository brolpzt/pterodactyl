{{-- Server Boost numbers, charts and recent connections; re-rendered by admin.boost.live. --}}
@php
    $max = max(1, max(array_column($stats['series'], 'count') ?: [0]));
    $topMax = max(1, max(array_column($stats['top'], 'count') ?: [0]));
    $every = count($stats['series']) > 12 ? (int) ceil(count($stats['series']) / 12) : 1;
    $rangeLabel = ['24h' => 'últimas 24 horas', '7d' => 'últimos 7 dias', '30d' => 'últimos 30 dias'][$range];
@endphp
<div class="bst-kpis">
    <div class="bst-kpi"><span>Conexões</span><strong>{{ number_format($stats['total'], 0, ',', '.') }}</strong><em>{{ $rangeLabel }}</em></div>
    <div class="bst-kpi"><span>Jogadores únicos</span><strong>{{ number_format($stats['players'], 0, ',', '.') }}</strong><em>por SteamID, IP ou nome</em></div>
    <div class="bst-kpi"><span>Servidores que receberam</span><strong>{{ $stats['servers'] }}</strong><em>{{ $rangeLabel }}</em></div>
    <div class="bst-kpi"><span>Última conexão</span><strong>{{ $stats['last'] ? $stats['last']->diffForHumans(null, true) : '—' }}</strong><em>{{ $stats['last'] ? $stats['last']->format('d/m H:i:s') : 'nenhuma no período' }}</em></div>
</div>

<div class="bst-charts">
    <div class="panel">
        <div class="panel-head">Conexões por {{ $range === '24h' ? 'hora' : 'dia' }} · {{ $rangeLabel }}</div>
        <div class="panel-body">
            <div class="bst-cols" role="img" aria-label="Conexões por {{ $range === '24h' ? 'hora' : 'dia' }}">
                <span class="bst-ymax">{{ $max }}</span>
                @foreach($stats['series'] as $i => $p)
                    <div class="bst-col" data-tip="{{ $p['title'] }}: {{ $p['count'] }} {{ $p['count'] === 1 ? 'conexão' : 'conexões' }}">
                        <div class="bst-bar" style="height: {{ $p['count'] ? max(2, round($p['count'] / $max * 100, 1)) : 0 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="bst-xaxis">
                @foreach($stats['series'] as $i => $p)
                    <span>{{ $i % $every === 0 ? $p['label'] : '' }}</span>
                @endforeach
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head">Servidores que mais receberam · {{ $rangeLabel }}</div>
        <div class="panel-body">
            @forelse($stats['top'] as $t)
                <div class="bst-hrow" data-tip="{{ $t['name'] }} ({{ $t['address'] }}): {{ $t['count'] }} {{ $t['count'] === 1 ? 'conexão' : 'conexões' }}">
                    <span class="bst-hname" title="{{ $t['address'] }}">{{ $t['name'] }}</span>
                    <span class="bst-htrack"><span class="bst-hbar" style="width: {{ max(1, round($t['count'] / $topMax * 100, 1)) }}%"></span></span>
                    <span class="bst-hval">{{ number_format($t['count'], 0, ',', '.') }}</span>
                </div>
            @empty
                <p class="ipl-muted" style="margin:6px 0">Nenhuma conexão no período.</p>
            @endforelse
        </div>
    </div>
</div>

<h1 class="page-title" style="margin-top:14px">Jogadores conectados via boost</h1>
<p class="meta-row">Últimas {{ $connections->count() }} conexões{{ $serverId || $search ? ' com o filtro atual' : '' }} · horário de {{ $tz }} · atualiza sozinho a cada 15s</p>
<div class="acl-wrap" style="max-height:520px;min-height:0">
    <table class="data">
        <thead>
        <tr><th>Quando</th><th>Jogador</th><th>SteamID</th><th>IP do jogador</th><th>Servidor</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($connections as $c)
            @php
                $at = $c->created_at->copy()->setTimezone($tz)->locale('pt_BR');
                $blocked = \Pterodactyl\Services\Boost\BoostBlacklistService::matchConnection($blacklist, $c);
                $marks = array_filter([
                    'steam_id' => $c->steam_id ? ['SteamID', $c->steam_id] : null,
                    'ip' => $c->player_ip ? ['IP', $c->player_ip] : null,
                    'name' => $c->player_name ? ['Nome', $c->player_name] : null,
                ]);
            @endphp
            <tr class="{{ $blocked ? 'bst-blocked' : '' }}">
                <td class="ipl-mono" title="{{ $at->format('d/m/Y H:i:s') }}">{{ $at->format('d/m H:i:s') }} <span class="ipl-muted">· {{ $at->diffForHumans(null, true) }}</span></td>
                <td>{{ $c->player_name ?? '—' }}@if($blocked) <span class="ipl-st error" title="{{ \Pterodactyl\Models\BoostBlacklistEntry::TYPES[$blocked->type] }} {{ $blocked->value }}{{ $blocked->reason ? ' · ' . $blocked->reason : '' }}">blacklist</span>@endif</td>
                <td class="ipl-mono">{{ $c->steam_id ?? '—' }}</td>
                <td class="ipl-mono">{{ $c->player_ip ?? '—' }}</td>
                <td>
                    @if($c->server)
                        <a href="{{ route('admin.servers.view', $c->server_id) }}">{{ $c->server->name }}</a>
                    @else
                        <span class="ipl-muted">servidor excluído</span>
                    @endif
                    <span class="ipl-mono ipl-muted">{{ $c->address }}</span>
                </td>
                <td style="text-align:right;white-space:nowrap">
                    @if($marks !== [])
                        <span class="bst-bl" title="Colocar na blacklist">
                            <i class="fa fa-ban"></i>
                            @foreach($marks as $type => [$label, $value])
                                <form method="post" action="{{ route('admin.boost.blacklist.store') }}" onsubmit="return confirm('Blacklist por {{ $label }}: ' + {{ json_encode($value) }} + '?')">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $type }}">
                                    <input type="hidden" name="value" value="{{ $value }}">
                                    <input type="hidden" name="back" value="{{ $backUrl }}">
                                    <button type="submit" class="btn" title="Blacklist por {{ $label }}: {{ $value }}">{{ $label }}</button>
                                </form>
                            @endforeach
                        </span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="ipl-muted">Nenhum jogador conectado via boost{{ $serverId || $search ? ' com este filtro' : ' ainda' }}.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
