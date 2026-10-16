@extends('layouts.admin')

@section('title')
    Server — {{ $server->name }} — Forced Commands
@endsection

@section('content-header')
    <h1>{{ $server->name }}<small>Overrides de comandos forçados</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.servers') }}">Servers</a></li>
        <li><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></li>
        <li class="active">Forced Commands</li>
    </ol>
@endsection

@section('content')
@include('admin.servers.partials.navigation')

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-body">
                <p class="no-margin">
                    Comandos definidos no egg
                    <a href="{{ route('admin.nests.egg.forced-commands', $server->egg_id) }}">{{ $server->egg->name }}</a>.
                    Pode desativar por servidor, alterar intervalo ou forçar agora.
                </p>
            </div>
        </div>
    </div>
</div>

@forelse($rows as $row)
    @php
        /** @var \Pterodactyl\Models\EggForcedCommand $forced */
        $forced = $row['forced'];
        $override = $row['override'];
        $effective = $row['effective'];
        $enabledValue = is_null($override) || is_null($override->getAttributes()['enabled'] ?? null)
            ? ''
            : ((int) $override->enabled === 1 ? '1' : '0');
        $hideValue = is_null($override) || is_null($override->getAttributes()['hide_output'] ?? null)
            ? ''
            : ((int) $override->hide_output === 1 ? '1' : '0');
    @endphp
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <code>{{ $forced->env_variable }}</code>
                        →
                        <code>{{ $forced->command_template }}</code>
                        @if($effective['enabled'])
                            <span class="label label-success">Ativo</span>
                        @else
                            <span class="label label-default">Desativado</span>
                        @endif
                    </h3>
                </div>
                <form action="{{ route('admin.servers.view.forced-commands.update', ['server' => $server->id, 'forcedCommand' => $forced->id]) }}" method="POST">
                    <div class="box-body">
                        <div class="row">
                            <div class="form-group col-md-3">
                                <label class="control-label">Enabled</label>
                                <select name="enabled" class="form-control">
                                    <option value="" @if($enabledValue === '') selected @endif>Padrão do egg ({{ $forced->enabled ? 'On' : 'Off' }})</option>
                                    <option value="1" @if($enabledValue === '1') selected @endif>Ativar</option>
                                    <option value="0" @if($enabledValue === '0') selected @endif>Desativar</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="control-label">Hide output</label>
                                <select name="hide_output" class="form-control">
                                    <option value="" @if($hideValue === '') selected @endif>Padrão do egg ({{ $forced->hide_output ? 'On' : 'Off' }})</option>
                                    <option value="1" @if($hideValue === '1') selected @endif>Esconder</option>
                                    <option value="0" @if($hideValue === '0') selected @endif>Mostrar</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="control-label">Interval (seconds)</label>
                                <input type="number" name="interval_seconds" class="form-control" min="15" max="3600"
                                       placeholder="Padrão: {{ $forced->interval_seconds }}"
                                       value="{{ $override->interval_seconds ?? '' }}" />
                                <p class="text-muted small">Vazio = padrão do egg ({{ $forced->interval_seconds }}s). Efetivo: {{ $effective['interval_seconds'] }}s</p>
                            </div>
                            <div class="form-group col-md-3">
                                <label class="control-label">Last forced</label>
                                <p class="form-control-static">
                                    {{ optional($override?->last_forced_at)->toDateTimeString() ?? 'Nunca' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-sm btn-primary">Save override</button>
                        <button type="submit" class="btn btn-sm btn-warning"
                                formaction="{{ route('admin.servers.view.forced-commands.force', ['server' => $server->id, 'forcedCommand' => $forced->id]) }}"
                                formmethod="POST">
                            Force now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@empty
    <div class="row">
        <div class="col-xs-12">
            <div class="callout callout-default">
                <p>
                    Este egg não tem comandos forçados.
                    Configure em
                    <a href="{{ route('admin.nests.egg.forced-commands', $server->egg_id) }}">Egg → Forced Commands</a>.
                </p>
            </div>
        </div>
    </div>
@endforelse
@endsection
