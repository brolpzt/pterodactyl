@extends('layouts.admin')

@php
    $model = \Pterodactyl\Models\PanelAlert::class;
    $selected = fn (string $field) => array_map('strval', old($field, $alert->{$field} ?? []));
    $serverPages = $selected('server_pages');
    $selectedPlacements = $selected('placements');
    $nestIds = $selected('nest_ids');
    $eggIds = $selected('egg_ids');
    $nodeIds = $selected('node_ids');
    $locationIds = $selected('location_ids');
@endphp

@section('title')
    Alertas - {{ $alert->exists ? ($alert->title ?: '#' . $alert->id) : 'Novo' }}
@endsection

@section('content-header')
    <h1>{{ $alert->exists ? ($alert->title ?: 'Alerta #' . $alert->id) : 'Novo alerta' }}<small>{{ $alert->exists ? $alert->status() : 'Aviso exibido no painel' }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.alerts') }}">Alertas</a></li>
        <li class="active">{{ $alert->exists ? '#' . $alert->id : 'Novo' }}</li>
    </ol>
@endsection

@section('content')
    <form action="{{ $alert->exists ? route('admin.alerts.view', $alert->id) : route('admin.alerts.new') }}" method="POST">
        {!! csrf_field() !!}
        @if($alert->exists)
            {!! method_field('PATCH') !!}
        @endif

        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Mensagem</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Tipo</label>
                            <select name="type" class="form-control">
                                @foreach($model::TYPES as $value => $label)
                                    <option value="{{ $value }}" {{ old('type', $alert->type) === $value ? 'selected' : '' }}>{{ $label }} ({{ $value }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Título <span class="field-optional"></span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $alert->title) }}" maxlength="191">
                        </div>
                        <div class="form-group">
                            <label class="control-label">Mensagem</label>
                            <textarea name="message" class="form-control" rows="4" maxlength="2000" required>{{ old('message', $alert->message) }}</textarea>
                            <p class="text-muted small">Aceita Markdown simples: <code>**negrito**</code>, <code>*itálico*</code> e <code>[texto](https://link)</code>. HTML não é interpretado.</p>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" id="alertEnabled" name="enabled" value="1" {{ old('enabled', $alert->enabled) ? 'checked' : '' }}>
                            <label for="alertEnabled">Ativo</label>
                        </div>
                        <div class="checkbox checkbox-primary">
                            <input type="hidden" name="dismissible" value="0">
                            <input type="checkbox" id="alertDismissible" name="dismissible" value="1" {{ old('dismissible', $alert->dismissible) ? 'checked' : '' }}>
                            <label for="alertDismissible">O usuário pode fechar este alerta</label>
                            <p class="text-muted small">Fica fechado naquele navegador. Se você editar o alerta, ele aparece de novo.</p>
                        </div>
                        <div class="row">
                            <div class="form-group col-sm-6">
                                <label class="control-label">Início <span class="field-optional"></span></label>
                                <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $alert->starts_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                            <div class="form-group col-sm-6">
                                <label class="control-label">Fim <span class="field-optional"></span></label>
                                <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $alert->ends_at?->format('Y-m-d\TH:i')) }}">
                            </div>
                        </div>
                        <p class="text-muted small">Horários em {{ config('app.timezone') }}.</p>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                        @if($alert->exists)
                            <button type="button" class="btn btn-danger btn-sm pull-right" id="deleteAlert">Excluir</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Onde aparece</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            @foreach($model::PLACEMENTS as $value => $label)
                                <div class="checkbox checkbox-primary" style="margin:6px 0">
                                    <input type="checkbox" id="alertPlacement-{{ $value }}" name="placements[]" value="{{ $value }}" {{ in_array($value, $selectedPlacements, true) ? 'checked' : '' }}>
                                    <label for="alertPlacement-{{ $value }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-group" id="serverPagesGroup">
                            <label class="control-label">Abas do servidor</label>
                            <select name="server_pages[]" class="form-control alert-select" multiple data-placeholder="Todas as abas">
                                @foreach($model::SERVER_PAGES as $value => $label)
                                    <option value="{{ $value }}" {{ in_array($value, $serverPages, true) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Público</label>
                            <select name="audience" class="form-control">
                                @foreach($model::AUDIENCES as $value => $label)
                                    <option value="{{ $value }}" {{ old('audience', $alert->audience) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Filtrar servidores</h3></div>
                    <div class="box-body">
                        <p class="text-muted small">Deixe vazio para valer em todos. Nests e eggs se somam; os demais filtros restringem o resultado. Na lista de servidores e em Minha conta, o alerta aparece para quem tem acesso a pelo menos um servidor compatível. No painel admin, estes filtros não se aplicam.</p>
                        <div class="form-group">
                            <label class="control-label">Condição do servidor</label>
                            <select name="server_condition" class="form-control">
                                <option value="">Nenhuma</option>
                                @foreach($model::CONDITIONS as $value => $label)
                                    <option value="{{ $value }}" {{ old('server_condition', $alert->server_condition) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted small">Com uma condição, o alerta só aparece nas páginas do servidor.</p>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Nests</label>
                            <select name="nest_ids[]" class="form-control alert-select" multiple data-placeholder="Todos os nests">
                                @foreach($nests as $nest)
                                    <option value="{{ $nest->id }}" {{ in_array((string) $nest->id, $nestIds, true) ? 'selected' : '' }}>{{ $nest->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Eggs</label>
                            <select name="egg_ids[]" class="form-control alert-select" multiple data-placeholder="Todos os eggs">
                                @foreach($nests as $nest)
                                    @if($nest->eggs->isNotEmpty())
                                        <optgroup label="{{ $nest->name }}">
                                            @foreach($nest->eggs as $egg)
                                                <option value="{{ $egg->id }}" {{ in_array((string) $egg->id, $eggIds, true) ? 'selected' : '' }}>{{ $egg->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Nodes</label>
                            <select name="node_ids[]" class="form-control alert-select" multiple data-placeholder="Todos os nodes">
                                @foreach($nodes as $node)
                                    <option value="{{ $node->id }}" {{ in_array((string) $node->id, $nodeIds, true) ? 'selected' : '' }}>{{ $node->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Locations</label>
                            <select name="location_ids[]" class="form-control alert-select" multiple data-placeholder="Todas as locations">
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ in_array((string) $location->id, $locationIds, true) ? 'selected' : '' }}>{{ $location->short }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Servidores específicos</label>
                            <select name="server_ids[]" id="alertServers" class="form-control" multiple data-placeholder="Todos os servidores">
                                @foreach($selectedServers as $server)
                                    <option value="{{ $server->id }}" selected>{{ $server->name }} (#{{ $server->id }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if($alert->exists)
        <form action="{{ route('admin.alerts.delete', $alert->id) }}" method="POST" id="deleteAlertForm">
            {!! csrf_field() !!}
            {!! method_field('DELETE') !!}
        </form>
    @endif
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            $('.alert-select').select2({ width: '100%' });
            $('#alertServers').select2({
                width: '100%',
                minimumInputLength: 1,
                ajax: {
                    url: @json(route('admin.alerts.servers')),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { return { q: params.term }; },
                    processResults: function (data) { return { results: data }; },
                },
            });

            const serverPlacement = document.querySelector('input[name="placements[]"][value="server"]');
            const serverPages = document.getElementById('serverPagesGroup');
            function toggleServerPages() {
                serverPages.style.display = serverPlacement.checked ? '' : 'none';
            }
            serverPlacement.addEventListener('change', toggleServerPages);
            toggleServerPages();

            const del = document.getElementById('deleteAlert');
            if (del) {
                del.addEventListener('click', function () {
                    swal({
                        type: 'warning',
                        title: 'Excluir este alerta?',
                        showCancelButton: true,
                        confirmButtonText: 'Excluir',
                        confirmButtonColor: '#d9534f',
                        closeOnConfirm: false,
                    }, function () {
                        document.getElementById('deleteAlertForm').submit();
                    });
                });
            }
        })();
    </script>
@endsection
