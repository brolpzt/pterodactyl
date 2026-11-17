@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Firewall XDP
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Firewall XDP de jogos (game_firewall) gerido pelo painel.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Firewall XDP</li>
    </ol>
@endsection

@section('content')
@php
    $changes = [
        'added' => ['Entra', 'success', '+'],
        'updated' => ['Muda', 'warning', '~'],
        'removed' => ['Sai', 'danger', '−'],
    ];
    $tz = config('app.stats_timezone', 'America/Sao_Paulo');
    $triggerColors = ['manual' => 'primary', 'automatic' => 'info', 'reconcile' => 'default'];
    $pending = $preview ? count($preview['added']) + count($preview['updated']) + count($preview['removed']) : 0;
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.nftables', $node->id) }}">Firewall Nftables</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.xdp', $node->id) }}">Firewall XDP</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-{{ $node->xdp_enabled ? 'success' : 'default' }}">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shield"></i> Firewall XDP neste node</h3>
                <div class="box-tools">
                    @if($node->xdp_enabled)
                        <form action="{{ route('admin.nodes.view.xdp.sync', $node->id) }}" method="POST" style="display:inline">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-box-tool btn-sm" style="color:#fff;background:#00a65a;border-radius:3px;padding:3px 10px;margin-top:3px">
                                <i class="fa fa-refresh"></i> Sincronizar agora
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="box-body">
                <p class="text-muted" style="margin-top:0">
                    O painel é dono do mapa de portas do XDP: o sync deixa o node exatamente com as portas dos servidores
                    abaixo, e <strong>portas que não estão aqui são removidas</strong>. O perfil vem do port slot do egg
                    (campo XDP) ou, em automático, do jogo do egg. Slots só TCP ficam de fora (o XDP filtra só UDP).
                    Convive com a Gcore.
                </p>
                <form action="{{ route('admin.nodes.view.xdp.setEnabled', $node->id) }}" method="POST" class="form-inline" style="margin-bottom:12px">
                    {!! csrf_field() !!}
                    <label class="control-label" style="margin-right:12px">Sincronizar XDP neste node</label>
                    <div class="radio radio-success radio-inline">
                        <input type="radio" id="pXdpFalse" value="0" name="xdp_enabled" {{ !$node->xdp_enabled ? 'checked' : '' }}>
                        <label for="pXdpFalse"> Não</label>
                    </div>
                    <div class="radio radio-info radio-inline">
                        <input type="radio" id="pXdpTrue" value="1" name="xdp_enabled" {{ $node->xdp_enabled ? 'checked' : '' }}>
                        <label for="pXdpTrue"> Sim</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-left:12px">Salvar</button>
                </form>
                @if($lastSync)
                    <p style="margin-bottom:0">
                        <strong>Última sync:</strong>
                        <span class="label label-{{ !empty($lastSync['ok']) ? 'success' : 'danger' }}">{{ !empty($lastSync['ok']) ? 'OK' : 'Falha' }}</span>
                        <span class="text-muted">{{ $lastSync['at'] ?? '' }} - {{ $lastSync['message'] ?? '' }}</span>
                    </p>
                @else
                    <p class="text-muted" style="margin-bottom:0">Nenhuma sync registrada ainda.</p>
                @endif
                @if($node->xdp_enabled)
                    @php($reconcileAge = $lastReconcile ? $lastReconcile->created_at->diffInMinutes(now()) : null)
                    <p style="margin:6px 0 0">
                        <strong>Reconciliação automática:</strong>
                        @if(!$lastReconcile)
                            <span class="label label-default">ainda não rodou</span>
                            <span class="text-muted small">roda a cada 10 min pelo agendador do painel</span>
                        @elseif(!$lastReconcile->ok)
                            <span class="label label-danger">falhou</span>
                            <span class="text-muted">{{ $lastReconcile->created_at->timezone($tz)->format('d/m H:i') }} - {{ $lastReconcile->error }}</span>
                        @elseif($reconcileAge > 25)
                            <span class="label label-warning">atrasada</span>
                            <span class="text-muted">última há {{ $reconcileAge }} min; confira o cron/agendador do painel</span>
                        @else
                            <span class="label label-success">em dia</span>
                            <span class="text-muted">última {{ $lastReconcile->created_at->timezone($tz)->format('d/m H:i') }} (há {{ $reconcileAge }} min)</span>
                        @endif
                    </p>
                @endif
            </div>
        </div>

        <div class="box box-{{ $previewError ? 'danger' : ($pending ? 'warning' : 'success') }}">
            <div class="box-header with-border">
                <h3 class="box-title">Prévia (dry-run): o que o próximo sync muda no node</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                @if($previewError)
                    <div style="padding:15px"><p class="text-danger" style="margin:0">{{ $previewError }}</p></div>
                @elseif(!$pending)
                    <div style="padding:15px"><p class="text-success" style="margin:0"><i class="fa fa-check"></i> O node já está igual ao painel ({{ $preview['unchanged'] }} porta(s)).</p></div>
                @else
                    <table class="table table-hover" style="margin-bottom:0">
                        <thead><tr><th>Mudança</th><th>Porta</th><th>Perfil no node</th><th>Perfil do painel</th></tr></thead>
                        <tbody>
                        @foreach($changes as $key => [$label, $color, $sign])
                            @foreach($preview[$key] as $change)
                                <tr>
                                    <td><span class="label label-{{ $color }}">{{ $sign }} {{ $label }}</span></td>
                                    <td>{{ $change['port'] ?? '' }}</td>
                                    <td>{{ $change['from'] ?? '—' }}</td>
                                    <td>{{ $change['to'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                    <div class="box-footer text-muted small">{{ $preview['unchanged'] }} porta(s) já estão iguais. Nada é aplicado até clicar em "Sincronizar agora".</div>
                @endif
            </div>
        </div>

        <div class="box box-default" id="history">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> Histórico de sincronizações</h3>
                <div class="box-tools" style="margin-top:4px">
                    @if($showAll)
                        <a href="{{ route('admin.nodes.view.xdp', $node->id) }}#history" class="btn btn-xs btn-default">Só mudanças e falhas</a>
                    @else
                        <a href="{{ route('admin.nodes.view.xdp', ['node' => $node->id, 'all' => 1]) }}#history" class="btn btn-xs btn-default">Mostrar todas</a>
                    @endif
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                @if($history->isEmpty())
                    <div style="padding:15px"><p class="text-muted" style="margin:0">
                        {{ $showAll ? 'Nenhuma sincronização registrada ainda.' : 'Nenhuma sincronização com mudança ou falha. Use "Mostrar todas" para ver as reconciliações sem alteração.' }}
                    </p></div>
                @else
                    <table class="table table-hover" style="margin-bottom:0">
                        <thead><tr><th>Quando</th><th>Origem</th><th>Resultado</th><th>Portas</th><th>Mudanças</th></tr></thead>
                        <tbody>
                        @foreach($history as $log)
                            <tr>
                                <td style="white-space:nowrap" title="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->timezone($tz)->format('d/m H:i:s') }}</td>
                                <td>
                                    <span class="label label-{{ $triggerColors[$log->trigger] ?? 'default' }}">{{ \Pterodactyl\Models\XdpSyncLog::TRIGGER_LABELS[$log->trigger] ?? $log->trigger }}</span>
                                    @if($log->reason)<div class="small text-muted">{{ $log->reason }}</div>@endif
                                </td>
                                <td>
                                    @if($log->ok)
                                        <span class="label label-success">OK</span>
                                    @else
                                        <span class="label label-danger">Falha</span>
                                        <div class="small text-danger">{{ \Illuminate\Support\Str::limit($log->error, 160) }}</div>
                                    @endif
                                </td>
                                <td>{{ $log->ports }}</td>
                                <td class="small">
                                    @if(!$log->changedPorts())
                                        <span class="text-muted">sem alteração</span>
                                    @else
                                        @foreach($changes as $key => [$label, $color, $sign])
                                            @foreach($log->changes[$key] ?? [] as $change)
                                                <span class="label label-{{ $color }}" style="display:inline-block;margin:1px 0" title="{{ $label }}">{{ $sign }} {{ $change['port'] ?? '' }} {{ trim(($change['from'] ?? '') . ' → ' . ($change['to'] ?? ''), ' →') }}</span>
                                            @endforeach
                                        @endforeach
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @if($history->hasPages())
                <div class="box-footer">{{ $history->fragment('history')->links() }}</div>
            @endif
        </div>

        @if(!empty($conflicts))
            <div class="box box-warning">
                <div class="box-header with-border"><h3 class="box-title">Conflitos de porta</h3></div>
                <div class="box-body">
                    <p class="text-muted">O XDP usa só a porta (sem IP). Estas portas aparecem em mais de um servidor com perfis diferentes; vale o primeiro.</p>
                    <ul style="margin:0">
                        @foreach($conflicts as $c)
                            <li>Porta {{ $c['port'] }}: <a href="{{ route('admin.servers.view', $c['server_id']) }}">{{ $c['server_name'] }}</a> pede <code>{{ $c['profile'] }}</code>, ficou <code>{{ $c['kept'] }}</code></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Portas desejadas ({{ count($rows) }})</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                @if(empty($rows))
                    <div style="padding:15px"><p class="text-muted" style="margin:0">Nenhuma porta de servidor neste node.</p></div>
                @else
                    <table class="table table-hover" style="margin-bottom:0">
                        <thead><tr><th>Porta</th><th>Perfil</th><th>Slot</th><th>Servidor</th></tr></thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr>
                                <td><code>{{ $row['ip'] }}:{{ $row['port'] }}</code></td>
                                <td><span class="label label-default" title="{{ $labels[$row['profile']] ?? '' }}">{{ $row['profile'] }}</span></td>
                                <td>{{ $row['slot'] ?? '—' }}</td>
                                <td><a href="{{ route('admin.servers.view', $row['server_id']) }}">{{ $row['server_name'] }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="box box-info">
            <div class="box-header with-border"><h3 class="box-title">Como funciona</h3></div>
            <div class="box-body">
                <ol style="padding-left:18px;margin:0">
                    <li>Confira a prévia: portas colocadas à mão que não pertencem a servidores aparecem como <strong>Sai</strong>.</li>
                    <li>Habilite e clique em <strong>Sincronizar agora</strong>.</li>
                    <li>Depois disso o painel sincroniza sozinho ao criar, alterar ou apagar servidores, e a cada 10 minutos. Tudo fica no <a href="#history">histórico</a> por {{ \Pterodactyl\Models\XdpSyncLog::RETENTION_DAYS }} dias.</li>
                    <li>O perfil de cada porta é ajustável no egg (port slots → XDP).</li>
                </ol>
                <p class="small" style="margin-top:12px;margin-bottom:4px"><strong>Perfis</strong></p>
                <ul class="small text-muted" style="padding-left:18px;margin:0">
                    @foreach($labels as $profile => $label)
                        <li><code>{{ $profile }}</code> {{ $label }}</li>
                    @endforeach
                </ul>
                <p class="text-muted small" style="margin-top:12px;margin-bottom:0">
                    O Wings chama <code>game_firewall</code> no node. Sem ele instalado, a prévia mostra erro.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
