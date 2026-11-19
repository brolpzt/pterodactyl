@extends('layouts.admin')

@section('title')
    Egg &rarr; {{ $egg->name }} &rarr; Anti-DDoS
@endsection

@section('content-header')
    <h1>{{ $egg->name }}<small>Perfis de proteção que o cliente pode escolher</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nests') }}">Nests</a></li>
        <li><a href="{{ route('admin.nests.view', $egg->nest->id) }}">{{ $egg->nest->name }}</a></li>
        <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">{{ $egg->name }}</a></li>
        <li class="active">Anti-DDoS</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nests.egg.view', $egg->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nests.egg.variables', $egg->id) }}">Variables</a></li>
                <li><a href="{{ route('admin.nests.egg.forced-commands', $egg->id) }}">Forced Commands</a></li>
                <li class="active"><a href="{{ route('admin.nests.egg.ddos', $egg->id) }}">Anti-DDoS</a></li>
                <li><a href="{{ route('admin.nests.egg.scripts', $egg->id) }}">Install Script</a></li>
            </ul>
        </div>
    </div>
</div>

@php
    $oldOptions = old('options');
    $optionRows = is_array($oldOptions)
        ? array_values(array_filter($oldOptions, fn ($o) => !empty($o['id']) || trim(($o['policy'] ?? '') . ($o['label'] ?? '')) !== ''))
        : $profiles->map(fn ($p) => $p->only(['id', 'policy', 'label', 'alert_type', 'alert_title', 'alert_message']))->all();
    $defaultCovered = $defaultPolicy !== null && collect($optionRows)->contains(fn ($o) => ($o['policy'] ?? null) === $defaultPolicy);
@endphp

<form action="{{ route('admin.nests.egg.ddos', $egg->id) }}" method="POST" id="ddosForm">
    {!! csrf_field() !!}
    {!! method_field('PATCH') !!}

    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Mudança pelo cliente</h3>
                </div>
                <div class="box-body">
                    <p>
                        Na aba <strong>Anti-DDoS</strong> do painel, o cliente escolhe uma das opções abaixo para a
                        <strong>porta principal</strong> do servidor. As outras portas mantêm o perfil do slot do egg.
                        O cliente só vê o nome e o quadro de cada opção, nunca o nome do perfil da Gcore.
                    </p>
                    <p>
                        O seletor começa <strong>escondido</strong> em cada servidor: libere em Admin → servidor → aba Anti-DDoS.
                        Enquanto escondido, o cliente vê só o perfil ativo e o quadro dele.
                    </p>
                    <p class="text-muted small">
                        Perfil padrão da porta principal neste egg:
                        @if($defaultPolicy)
                            <code>{{ $defaultPolicy }}</code>
                        @else
                            <span class="text-danger">nenhum (defina o perfil da Gcore no slot <code>SERVER_PORT</code> do egg; sem ele a mudança não aparece).</span>
                        @endif
                    </p>
                    @if($defaultPolicy && !$defaultCovered && count($optionRows) > 0)
                        <div class="alert alert-warning" style="margin-bottom: 10px;">
                            Nenhuma opção usa o perfil padrão (<code>{{ $defaultPolicy }}</code>). Quem mudar não conseguirá voltar para ele sozinho.
                        </div>
                    @endif
                    <div class="row">
                        <div class="form-group col-sm-4">
                            <label class="control-label">Habilitado</label>
                            <div class="checkbox checkbox-primary no-margin-bottom">
                                <input type="checkbox" id="pEnabled" name="enabled" value="1" {{ old('enabled', $settings->enabled) ? 'checked' : '' }}>
                                <label for="pEnabled">Cliente pode mudar o perfil de proteção</label>
                            </div>
                            <p class="text-muted small">Ao desabilitar, os servidores que mudaram voltam ao perfil padrão do egg.</p>
                        </div>
                        <div class="form-group col-sm-4">
                            <label for="pCooldown" class="control-label">Cooldown (minutos)</label>
                            <input type="number" min="0" max="1440" id="pCooldown" name="cooldown_minutes" class="form-control" value="{{ old('cooldown_minutes', $settings->cooldown_minutes) }}">
                            <p class="text-muted small">Tempo mínimo entre duas mudanças do cliente. O admin não tem cooldown.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="ddosOptions">
        @foreach($optionRows as $i => $row)
            @include('admin.eggs.partials.ddos-option', ['i' => $i, 'row' => $row, 'count' => isset($row['id']) ? ($inUse[$row['id']] ?? 0) : 0])
        @endforeach
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-footer">
                    <button type="button" class="btn btn-sm btn-default" id="addOption"><i class="fa fa-plus"></i> Adicionar opção</button>
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="ddosOptionTemplate">
    @include('admin.eggs.partials.ddos-option', ['i' => '__INDEX__', 'row' => ['alert_type' => 'info'], 'count' => 0])
</template>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var container = document.getElementById('ddosOptions');
            var next = container.querySelectorAll('.ddos-option').length;
            document.getElementById('addOption').addEventListener('click', function () {
                var html = document.getElementById('ddosOptionTemplate').innerHTML.replace(/__INDEX__/g, String(next++));
                container.insertAdjacentHTML('beforeend', html);
            });
            container.addEventListener('click', function (event) {
                var button = event.target.closest('[data-action="remove"]');
                if (!button) return;
                var box = button.closest('.ddos-option');
                var flag = box.querySelector('input[data-role="delete"]');
                if (!box.querySelector('input[data-role="id"]').value) {
                    box.remove();
                    return;
                }
                var removing = flag.value !== '1';
                flag.value = removing ? '1' : '0';
                box.classList.toggle('box-danger', removing);
                box.style.opacity = removing ? '0.55' : '';
                button.innerHTML = removing ? '<i class="fa fa-undo"></i> Desfazer' : '<i class="fa fa-trash-o"></i> Remover';
            });
        })();
    </script>
@endsection
