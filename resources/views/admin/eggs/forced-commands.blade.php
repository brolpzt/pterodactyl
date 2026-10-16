@extends('layouts.admin')

@section('title')
    Egg &rarr; {{ $egg->name }} &rarr; Forced Commands
@endsection

@section('content-header')
    <h1>{{ $egg->name }}<small>Forçar comandos de consola a partir de ENV</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nests') }}">Nests</a></li>
        <li><a href="{{ route('admin.nests.view', $egg->nest->id) }}">{{ $egg->nest->name }}</a></li>
        <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">{{ $egg->name }}</a></li>
        <li class="active">Forced Commands</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nests.egg.variables', $egg->id) }}">Variables</a></li>
                <li class="active"><a href="{{ route('admin.nests.egg.forced-commands', $egg->id) }}">Forced Commands</a></li>
                <li><a href="{{ route('admin.nests.egg.scripts', $egg->id) }}">Install Script</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-body">
                <p class="no-margin">
                    Liga uma variável de ambiente (ex.: <code>TICKRATE</code>) a um comando de consola
                    (ex.: <code>sys_ticrate @{{VALUE}}</code>). O painel reaplica o comando periodicamente
                    com o valor do ENV, para o cliente não conseguir aumentar o valor pago.
                </p>
                <p class="text-muted small no-margin-top" style="margin-top:8px;">
                    Use <code>@{{VALUE}}</code> ou <code>@{{NOME_DA_ENV}}</code> no template.
                    Os comandos forçados são sempre enviados por <strong>stdin</strong> da consola (não usam RCON),
                    para o cliente não conseguir desativar. O jogo pode ainda ecoar o comando na consola.
                    Intervalo mínimo: 15 segundos.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @forelse($forcedCommands as $command)
        <div class="col-sm-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <code>{{ $command->env_variable }}</code>
                        →
                        <code>{{ $command->command_template }}</code>
                    </h3>
                </div>
                <form action="{{ route('admin.nests.egg.forced-commands.update', ['egg' => $egg->id, 'forcedCommand' => $command->id]) }}" method="POST">
                    <div class="box-body">
                        <div class="form-group">
                            <label class="form-label">Environment Variable</label>
                            <select name="env_variable" class="form-control" required>
                                @foreach($egg->variables as $variable)
                                    <option value="{{ $variable->env_variable }}" @if($variable->env_variable === $command->env_variable) selected @endif>
                                        {{ $variable->name }} ({{ $variable->env_variable }})
                                    </option>
                                @endforeach
                                @if($egg->variables->where('env_variable', $command->env_variable)->isEmpty())
                                    <option value="{{ $command->env_variable }}" selected>{{ $command->env_variable }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Command Template</label>
                            <input type="text" name="command_template" class="form-control" value="{{ $command->command_template }}" required />
                            <p class="text-muted small">Exemplo: <code>sys_ticrate @{{VALUE}}</code></p>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Interval (seconds)</label>
                            <input type="number" name="interval_seconds" class="form-control" min="15" max="3600" value="{{ $command->interval_seconds }}" required />
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="enabled_{{ $command->id }}" name="enabled" value="1" @if($command->enabled) checked @endif />
                            <label for="enabled_{{ $command->id }}">Enabled</label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="hide_{{ $command->id }}" name="hide_output" value="1" @if($command->hide_output) checked @endif />
                            <label for="hide_{{ $command->id }}">Hide output from console</label>
                        </div>
                    </div>
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button class="btn btn-sm btn-primary pull-right" name="_method" value="PATCH" type="submit">Save</button>
                        <button class="btn btn-sm btn-danger pull-left" name="_method" value="DELETE" type="submit" onclick="return confirm('Remover este comando forçado?')">
                            <i class="fa fa-trash-o"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="col-xs-12">
            <div class="callout callout-default">
                <p>Ainda não há comandos forçados neste egg.</p>
            </div>
        </div>
    @endforelse
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Criar comando forçado</h3>
            </div>
            <form action="{{ route('admin.nests.egg.forced-commands', $egg->id) }}" method="POST">
                <div class="box-body">
                    @if($egg->variables->isEmpty())
                        <div class="callout callout-warning">
                            <p>Crie primeiro uma variável de ambiente na aba Variables (ex.: <code>TICKRATE</code>).</p>
                        </div>
                    @else
                        <div class="row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Environment Variable</label>
                                <select name="env_variable" class="form-control" required>
                                    @foreach($egg->variables as $variable)
                                        <option value="{{ $variable->env_variable }}">{{ $variable->name }} ({{ $variable->env_variable }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Command Template</label>
                                <input type="text" name="command_template" class="form-control" placeholder="sys_ticrate @{{VALUE}}" value="{{ old('command_template') }}" required />
                            </div>
                            <div class="form-group col-md-4">
                                <label class="control-label">Interval (seconds)</label>
                                <input type="number" name="interval_seconds" class="form-control" min="15" max="3600" value="{{ old('interval_seconds', 60) }}" required />
                            </div>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="new_enabled" name="enabled" value="1" checked />
                            <label for="new_enabled">Enabled</label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" id="new_hide" name="hide_output" value="1" checked />
                            <label for="new_hide">Hide output from console</label>
                        </div>
                    @endif
                </div>
                @if(!$egg->variables->isEmpty())
                    <div class="box-footer">
                        {!! csrf_field() !!}
                        <button type="submit" class="btn btn-success pull-right">Create</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection
