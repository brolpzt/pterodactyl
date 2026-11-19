@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }} — Anti-DDoS
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Perfil anti-DDoS da porta principal</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Anti-DDoS</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')

@php
    /** @var \Pterodactyl\Models\ServerDdosProfileState $state */
    $state = $context['state'];
    $settings = $context['settings'];
    $current = $context['current'];
    $allocation = $context['allocation'];
    $selected = $current ? 'profile:' . $current->id : (($context['policy'] ?? null) === ($context['default']['policy'] ?? null) ? 'default' : 'policy:' . $context['policy']);
@endphp

@if(!$context['available'])
    <div class="row">
        <div class="col-xs-12">
            <div class="alert alert-warning">{{ $context['reason'] }}</div>
        </div>
    </div>
@else
<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Perfil atual</h3>
            </div>
            <div class="box-body">
                <dl class="dl-horizontal" style="margin-bottom: 0;">
                    <dt>Porta</dt>
                    <dd><code>{{ $allocation->ip }}:{{ $allocation->port }}</code></dd>
                    <dt>Perfil da Gcore</dt>
                    <dd><code>{{ $context['policy'] }}</code></dd>
                    <dt>O cliente vê</dt>
                    <dd>
                        @if($current)
                            {{ $current->label }}
                        @elseif($context['custom'])
                            Configuração personalizada pelo suporte <span class="text-muted">(mudança bloqueada para o cliente)</span>
                        @else
                            Proteção padrão
                        @endif
                    </dd>
                    <dt>Padrão do egg</dt>
                    <dd><code>{{ $context['default']['policy'] }}</code></dd>
                    @if($sync)
                        <dt>Última mudança na Gcore</dt>
                        <dd>
                            @if($sync['status'] === 'pending')
                                <span class="label label-warning">na fila / aplicando</span>
                            @elseif($sync['status'] === 'applied')
                                <span class="label label-success">aplicada</span>
                            @else
                                <span class="label label-danger">falhou</span>
                            @endif
                            <span class="text-muted small">sincronização #{{ $sync['run_id'] }}</span>
                            @if($sync['status'] === 'failed' && $sync['summary'])
                                <br><span class="text-danger small">{{ \Illuminate\Support\Str::limit($sync['summary'], 300) }}</span>
                            @endif
                        </dd>
                    @endif
                </dl>
            </div>
        </div>
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Mudar perfil</h3>
            </div>
            <form action="{{ route('admin.servers.view.ddos', $server->id) }}" method="POST">
                {!! csrf_field() !!}
                <div class="box-body">
                    <select name="choice" class="form-control">
                        <option value="default" {{ $selected === 'default' ? 'selected' : '' }}>Padrão do egg ({{ $context['default']['policy'] }})</option>
                        @if($context['profiles']->isNotEmpty())
                            <optgroup label="Opções do egg (o cliente vê o nome)">
                                @foreach($context['profiles'] as $profile)
                                    <option value="profile:{{ $profile->id }}" {{ $selected === 'profile:' . $profile->id ? 'selected' : '' }}>{{ $profile->label }} — {{ $profile->policy }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        <optgroup label="Qualquer perfil da Gcore (cliente vê &quot;personalizado&quot;)">
                            @foreach($policies as $policy)
                                <option value="policy:{{ $policy }}" {{ $selected === 'policy:' . $policy ? 'selected' : '' }}>{{ $policy }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                    <p class="text-muted small" style="margin-top: 8px;">
                        A mudança pode deixar o servidor indisponível por alguns segundos enquanto o filtro é aplicado na Gcore.
                        Sem cooldown para o admin. Um perfil fora das opções do egg trava a mudança pelo cliente até voltar a uma opção ou ao padrão.
                    </p>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Aplicar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Mudança pelo cliente</h3>
            </div>
            <form action="{{ route('admin.servers.view.ddos.settings', $server->id) }}" method="POST">
                {!! csrf_field() !!}
                <div class="box-body">
                    <p>
                        No egg <a href="{{ route('admin.nests.egg.ddos', $server->egg_id) }}">{{ $server->egg->name }}</a>:
                        @if($settings->enabled && $context['profiles']->isNotEmpty())
                            <span class="label label-success">habilitada</span>, {{ $context['profiles']->count() }} opção(ões), cooldown de {{ $settings->cooldown_minutes }} min.
                        @else
                            <span class="label label-default">desabilitada</span>
                        @endif
                    </p>
                    <p>
                        Última mudança do cliente:
                        {{ $state->client_changed_at ? $state->client_changed_at->timezone(config('app.stats_timezone', config('app.timezone')))->format('d/m/Y H:i') : 'nunca' }}
                        @if($nextClientChange)
                            <br><span class="text-muted">Pode mudar de novo às {{ $nextClientChange->timezone(config('app.stats_timezone', config('app.timezone')))->format('H:i') }}.</span>
                        @endif
                    </p>
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" id="pClientVisible" name="client_visible" value="1" {{ $state->client_locked ? '' : 'checked' }}>
                        <label for="pClientVisible">Mostrar o seletor de perfil ao cliente neste servidor</label>
                        <p class="text-muted small" style="margin: 4px 0 0;">Desmarcado (padrão): o cliente vê só o perfil ativo e o quadro dele, sem seletor e sem aviso.</p>
                    </div>
                    @if($nextClientChange)
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="pResetCooldown" name="reset_cooldown" value="1">
                            <label for="pResetCooldown">Zerar o cooldown (cliente pode mudar agora)</label>
                        </div>
                    @endif
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-sm btn-default pull-right">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
