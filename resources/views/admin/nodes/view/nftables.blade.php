@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Firewall Nftables
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Chain nftables gerida pelo painel (inet ptero / ptero_ports).</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Firewall Nftables</li>
    </ol>
@endsection

@section('content')
@php
    $rules = $rules ?? [];
    $lastSync = $lastSync ?? null;
@endphp
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.nftables', $node->id) }}">Firewall Nftables</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-{{ $node->nftables_enabled ? 'success' : 'default' }}">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-fire"></i> Nftables neste node</h3>
                <div class="box-tools">
                    @if($node->nftables_enabled)
                        <form action="{{ route('admin.nodes.view.nftables.sync', $node->id) }}" method="POST" style="display:inline">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-box-tool btn-sm" style="color:#fff;background:#00a65a;border-radius:3px;padding:3px 10px;margin-top:3px">
                                <i class="fa fa-refresh"></i> Sync agora
                            </button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="box-body">
                <p class="text-muted" style="margin-top:0">
                    O painel é dono da chain <code>inet ptero / ptero_ports</code>. O sync envia o estado completo ao Wings
                    (flush + rewrite). Protocolos vêm dos <strong>port_slots</strong> do egg (mesmo modelo da Gcore).
                    Allocations com nftables <strong>não</strong> usam Gcore na mesma porta.
                </p>
                <form action="{{ route('admin.nodes.view.nftables.setEnabled', $node->id) }}" method="POST" class="form-inline" style="margin-bottom:12px">
                    {!! csrf_field() !!}
                    <label class="control-label" style="margin-right:12px">Nftables ativo neste node</label>
                    <div class="radio radio-success radio-inline">
                        <input type="radio" id="pNftFalse" value="0" name="nftables_enabled" {{ !$node->nftables_enabled ? 'checked' : '' }}>
                        <label for="pNftFalse"> Não</label>
                    </div>
                    <div class="radio radio-info radio-inline">
                        <input type="radio" id="pNftTrue" value="1" name="nftables_enabled" {{ $node->nftables_enabled ? 'checked' : '' }}>
                        <label for="pNftTrue"> Sim</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-left:12px">Salvar</button>
                </form>
                @if($lastSync)
                    <p style="margin-bottom:0">
                        <strong>Última sync:</strong>
                        <span class="label label-{{ !empty($lastSync['ok']) ? 'success' : 'danger' }}">
                            {{ !empty($lastSync['ok']) ? 'OK' : 'Falha' }}
                        </span>
                        <span class="text-muted">
                            {{ $lastSync['at'] ?? '' }}
                            - {{ $lastSync['message'] ?? '' }}
                            @if(isset($lastSync['rules']))
                                ({{ $lastSync['rules'] }} regra(s))
                            @endif
                        </span>
                    </p>
                @else
                    <p class="text-muted" style="margin-bottom:0">Nenhuma sync registada ainda.</p>
                @endif
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Regras desejadas (allocations nftables + servidor)</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                @if(empty($rules))
                    <div style="padding:15px">
                        <p class="text-muted" style="margin:0">
                            Nenhuma regra. Marque allocations em <a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a>
                            com "Gerenciar via nftables" e associe a um servidor cujo egg tenha port_slots com proto/policy.
                        </p>
                    </div>
                @else
                    <table class="table table-hover" style="margin-bottom:0">
                        <thead>
                        <tr>
                            <th>IP</th>
                            <th>Porta</th>
                            <th>Proto</th>
                            <th>Servidor</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rules as $rule)
                            <tr>
                                <td><code>{{ $rule['ip'] }}</code></td>
                                <td>{{ $rule['port'] }}</td>
                                <td><span class="label label-default">{{ strtoupper($rule['proto']) }}</span></td>
                                <td>
                                    @if(!empty($rule['server_id']))
                                        <a href="{{ route('admin.servers.view', $rule['server_id']) }}">{{ $rule['server_name'] }}</a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
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
            <div class="box-header with-border">
                <h3 class="box-title">Como usar</h3>
            </div>
            <div class="box-body">
                <ol style="padding-left:18px;margin:0">
                    <li>Ative nftables neste node.</li>
                    <li>Em Allocation, marque <strong>Gerenciar via nftables</strong> (não use Gcore na mesma porta).</li>
                    <li>Ao criar/apagar servidor o sync é automático; use <strong>Sync agora</strong> para forçar.</li>
                </ol>
                <p class="text-muted small" style="margin-top:12px;margin-bottom:0">
                    Wings precisa de <code>CAP_NET_ADMIN</code> e do binário <code>nft</code>. Chain: <code>table inet ptero / chain ptero_ports</code>.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
