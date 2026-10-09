@extends('layouts.admin')

@section('title')
    Nests &rarr; Egg: {{ $egg->name }}
@endsection

@section('content-header')
    <h1>{{ $egg->name }}<small>{{ str_limit($egg->description, 50) }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nests') }}">Nests</a></li>
        <li><a href="{{ route('admin.nests.view', $egg->nest->id) }}">{{ $egg->nest->name }}</a></li>
        <li class="active">{{ $egg->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li class="active"><a href="{{ route('admin.nests.egg.view', $egg->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nests.egg.variables', $egg->id) }}">Variables</a></li>
                <li><a href="{{ route('admin.nests.egg.scripts', $egg->id) }}">Install Script</a></li>
            </ul>
        </div>
    </div>
</div>
<form action="{{ route('admin.nests.egg.view', $egg->id) }}" enctype="multipart/form-data" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-danger">
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-8">
                            <div class="form-group no-margin-bottom">
                                <label for="pName" class="control-label">Egg File</label>
                                <div>
                                    <input type="file" name="import_file" class="form-control" style="border: 0;margin-left:-10px;" />
                                    <p class="text-muted small no-margin-bottom">If you would like to replace settings for this Egg by uploading a new JSON file, simply select it here and press "Update Egg". This will not change any existing startup strings or Docker images for existing servers.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            {!! csrf_field() !!}
                            <button type="submit" name="_method" value="PUT" class="btn btn-sm btn-danger pull-right">Update Egg</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<form action="{{ route('admin.nests.egg.view', $egg->id) }}" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Configuration</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pName" class="control-label">Name <span class="field-required"></span></label>
                                <input type="text" id="pName" name="name" value="{{ $egg->name }}" class="form-control" />
                                <p class="text-muted small">A simple, human-readable name to use as an identifier for this Egg.</p>
                            </div>
                            <div class="form-group">
                                <label for="pUuid" class="control-label">UUID</label>
                                <input type="text" id="pUuid" readonly value="{{ $egg->uuid }}" class="form-control" />
                                <p class="text-muted small">This is the globally unique identifier for this Egg which the Daemon uses as an identifier.</p>
                            </div>
                            <div class="form-group">
                                <label for="pAuthor" class="control-label">Author</label>
                                <input type="text" id="pAuthor" readonly value="{{ $egg->author }}" class="form-control" />
                                <p class="text-muted small">The author of this version of the Egg. Uploading a new Egg configuration from a different author will change this.</p>
                            </div>
                            <div class="form-group">
                                <label for="pDockerImage" class="control-label">Docker Images <span class="field-required"></span></label>
                                <textarea id="pDockerImages" name="docker_images" class="form-control" rows="4">{{ implode(PHP_EOL, $images) }}</textarea>
                                <p class="text-muted small">
                                    The docker images available to servers using this egg. Enter one per line. Users
                                    will be able to select from this list of images if more than one value is provided.
                                    Optionally, a display name may be provided by prefixing the image with the name
                                    followed by a pipe character, and then the image URL. Example: <code>Display Name|ghcr.io/my/egg</code>
                                </p>
                            </div>
                            <div class="form-group">
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input id="pForceOutgoingIp" name="force_outgoing_ip" type="checkbox" value="1" @if($egg->force_outgoing_ip) checked @endif />
                                    <label for="pForceOutgoingIp" class="strong">Force Outgoing IP</label>
                                    <p class="text-muted small">
                                        Forces all outgoing network traffic to have its Source IP NATed to the IP of the server's primary allocation IP.
                                        Required for certain games to work properly when the Node has multiple public IP addresses.
                                        <br>
                                        <strong>
                                            Enabling this option will disable internal networking for any servers using this egg,
                                            causing them to be unable to internally access other servers on the same node.
                                        </strong>
                                    </p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="pGamedig" class="control-label">GameDig</label>
                                <input type="text" id="pGamedig" name="gamedig" value="{{ $egg->gamedig }}" class="form-control" />
                                <p class="text-muted small">
                                    Valor GameDig para consulta do servidor. Também define o fundo do painel do cliente
                                    (use o slug da pasta em <code>public/bg/</code>, ex.: <code>cod4</code>, <code>bo1</code>, <code>cs16</code>).
                                </p>
                            </div>
                            <div class="form-group">
                                <div class="checkbox checkbox-primary no-margin-bottom">
                                    <input id="pWarnSlotMismatch" name="warn_slot_mismatch" type="checkbox" value="1" @if($egg->warn_slot_mismatch) checked @endif />
                                    <label for="pWarnSlotMismatch" class="strong">Aviso de slots acima do limite</label>
                                    <p class="text-muted small">
                                        Exibe um alerta no painel do cliente quando a query do jogo reportar mais slots
                                        do que o valor da variável <code>SLOTS</code>. Usar menos ou igual slots é permitido.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pDescription" class="control-label">Description</label>
                                <textarea id="pDescription" name="description" class="form-control" rows="8">{{ $egg->description }}</textarea>
                                <p class="text-muted small">A description of this Egg that will be displayed throughout the Panel as needed.</p>
                            </div>
                            <div class="form-group">
                                <label for="pStartup" class="control-label">Startup Command <span class="field-required"></span></label>
                                <textarea id="pStartup" name="startup" class="form-control" rows="8">{{ $egg->startup }}</textarea>
                                <p class="text-muted small">The default startup command that should be used for new servers using this Egg.</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigFeatures" class="control-label">Features</label>
                                <div>
                                    <select class="form-control" name="features[]" id="pConfigFeatures" multiple>
                                        @foreach(($egg->features ?? []) as $feature)
                                            <option value="{{ $feature }}" selected>{{ $feature }}</option>
                                        @endforeach
                                        @if(!in_array('fastdl', $egg->features ?? []))
                                            <option value="fastdl">fastdl</option>
                                        @endif
                                        @if(!in_array('dns', $egg->features ?? []))
                                            <option value="dns">dns</option>
                                        @endif
                                        @if(!in_array('workshop', $egg->features ?? []))
                                            <option value="workshop">workshop</option>
                                        @endif
                                        @if(!in_array('webrcon', $egg->features ?? []))
                                            <option value="webrcon">webrcon</option>
                                        @endif
                                    </select>
                                    <p class="text-muted small">Additional features belonging to the egg. Useful for configuring additional panel modifications.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Process Management</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="alert alert-warning">
                                <p>The following configuration options should not be edited unless you understand how this system works. If wrongly modified it is possible for the daemon to break.</p>
                                <p>All fields are required unless you select a separate option from the 'Copy Settings From' dropdown, in which case fields may be left blank to use the values from that Egg.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pConfigFrom" class="form-label">Copy Settings From</label>
                                <select name="config_from" id="pConfigFrom" class="form-control">
                                    <option value="">None</option>
                                    @foreach($egg->nest->eggs as $o)
                                        <option value="{{ $o->id }}" {{ ($egg->config_from !== $o->id) ?: 'selected' }}>{{ $o->name }} &lt;{{ $o->author }}&gt;</option>
                                    @endforeach
                                </select>
                                <p class="text-muted small">If you would like to default to settings from another Egg select it from the menu above.</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigStop" class="form-label">Stop Command</label>
                                <input type="text" id="pConfigStop" name="config_stop" class="form-control" value="{{ $egg->config_stop }}" />
                                <p class="text-muted small">The command that should be sent to server processes to stop them gracefully. If you need to send a <code>SIGINT</code> you should enter <code>^C</code> here.</p>
                            </div>
                            <div class="form-group">
                                <label for="pCommandTransmissionType" class="form-label">Command Transmission Type</label>
                                <select name="command_transmission_type" id="pCommandTransmissionType" class="form-control">
                                    <option value="stdin" {{ $egg->command_transmission_type !== 'rcon' ? 'selected' : '' }}>Console (Standard Stdin)</option>
                                    <option value="rcon" {{ $egg->command_transmission_type === 'rcon' ? 'selected' : '' }}>RCON Connection</option>
                                </select>
                                <p class="text-muted small">Select how console commands are sent to the game server.</p>
                            </div>
                            <div class="form-group" id="rconProtocolGroup" style="{{ $egg->command_transmission_type === 'rcon' ? '' : 'display: none;' }}">
                                <label for="pRconProtocol" class="form-label">RCON Protocol</label>
                                <select name="rcon_protocol" id="pRconProtocol" class="form-control">
                                    <option value="source" {{ $egg->rcon_protocol === 'source' ? 'selected' : '' }}>Source RCON (Minecraft, Source, Palworld, etc.)</option>
                                    <option value="quake3" {{ $egg->rcon_protocol === 'quake3' ? 'selected' : '' }}>Quake3 RCON (Quake 3 Engine UDP)</option>
                                    <option value="webrcon" {{ $egg->rcon_protocol === 'webrcon' ? 'selected' : '' }}>WebRcon (Rust/7Days WebSocket JSON)</option>
                                </select>
                                <p class="text-muted small">The specific RCON protocol to use for this game server.</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigLogs" class="form-label">Log Configuration</label>
                                <textarea data-action="handle-tabs" id="pConfigLogs" name="config_logs" class="form-control" rows="6">{{ ! is_null($egg->config_logs) ? json_encode(json_decode($egg->config_logs), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '' }}</textarea>
                                <p class="text-muted small">This should be a JSON representation of where log files are stored, and whether or not the daemon should be creating custom logs.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pConfigFiles" class="form-label">Configuration Files</label>
                                <textarea data-action="handle-tabs" id="pConfigFiles" name="config_files" class="form-control" rows="6">{{ ! is_null($egg->config_files) ? json_encode(json_decode($egg->config_files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '' }}</textarea>
                                <p class="text-muted small">This should be a JSON representation of configuration files to modify and what parts should be changed.</p>
                            </div>
                            <div class="form-group">
                                <label for="pConfigStartup" class="form-label">Start Configuration</label>
                                <textarea data-action="handle-tabs" id="pConfigStartup" name="config_startup" class="form-control" rows="6">{{ ! is_null($egg->config_startup) ? json_encode(json_decode($egg->config_startup), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '' }}</textarea>
                                <p class="text-muted small">This should be a JSON representation of what values the daemon should be looking for when booting a server to determine completion.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Port slots + Gcore ACL</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">
                        Centraliza portas e ACL Gcore. <code>SERVER_PORT</code> = allocation primária (obrigatório na lista).
                        Portas extras viram ENV; <strong>Required</strong> = auto na criação (mesmo IP).
                        Defina <strong>Gcore policy/proto por porta</strong> — só entram no sync ACL se a allocation estiver marcada como protegida.
                    </p>
                    @php
                        $portSlotSync = app(\Pterodactyl\Services\Eggs\PortSlotSyncService::class);
                        $portSlots = old('port_slots');
                        if (!is_array($portSlots)) {
                            $portSlots = $portSlotSync->slotsForEdit($egg);
                        }
                        $gcorePolicies = \Pterodactyl\Services\Gcore\GcoreClient::POLICIES;
                        $gcoreProtos = \Pterodactyl\Services\Gcore\GcoreClient::PROTOCOLS;
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-condensed" id="portSlotsTable">
                            <thead>
                            <tr>
                                <th style="width:14%">ENV</th>
                                <th style="width:12%">Nome</th>
                                <th>Descrição</th>
                                <th style="width:16%">Gcore policy</th>
                                <th style="width:10%">Proto</th>
                                <th style="width:70px">Required</th>
                                <th style="width:40px"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($portSlots as $i => $slot)
                                @php $isPrimary = strtoupper((string) ($slot['env_variable'] ?? '')) === 'SERVER_PORT'; @endphp
                                <tr>
                                    <td>
                                        @if($isPrimary)
                                            <input type="hidden" name="port_slots[{{ $i }}][env_variable]" value="SERVER_PORT">
                                            <code>SERVER_PORT</code>
                                        @else
                                            <input type="text" name="port_slots[{{ $i }}][env_variable]" class="form-control input-sm" value="{{ $slot['env_variable'] ?? '' }}" placeholder="QUERY_PORT">
                                        @endif
                                    </td>
                                    <td><input type="text" name="port_slots[{{ $i }}][name]" class="form-control input-sm" value="{{ $slot['name'] ?? '' }}" placeholder="{{ $isPrimary ? 'Game Port' : 'Query Port' }}"></td>
                                    <td><input type="text" name="port_slots[{{ $i }}][description]" class="form-control input-sm" value="{{ $slot['description'] ?? '' }}" placeholder="{{ $isPrimary ? 'Allocation primária' : 'Porta de query' }}"></td>
                                    <td>
                                        <select name="port_slots[{{ $i }}][gcore_policy]" class="form-control input-sm">
                                            <option value="">— sem ACL —</option>
                                            @foreach($gcorePolicies as $policy)
                                                <option value="{{ $policy }}" {{ ($slot['gcore_policy'] ?? '') === $policy ? 'selected' : '' }}>{{ $policy }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select name="port_slots[{{ $i }}][gcore_proto]" class="form-control input-sm">
                                            <option value="">any</option>
                                            @foreach($gcoreProtos as $proto)
                                                @continue($proto === 'any')
                                                <option value="{{ $proto }}" {{ ($slot['gcore_proto'] ?? '') === $proto ? 'selected' : '' }}>{{ $proto }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        @if($isPrimary)
                                            <input type="hidden" name="port_slots[{{ $i }}][required]" value="1">
                                            <span class="text-muted" title="Sempre a primary">primary</span>
                                        @else
                                            <input type="hidden" name="port_slots[{{ $i }}][required]" value="0">
                                            <input type="checkbox" name="port_slots[{{ $i }}][required]" value="1" {{ !empty($slot['required']) ? 'checked' : '' }}>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$isPrimary)
                                            <button type="button" class="btn btn-xs btn-danger port-slot-remove">&times;</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-xs btn-default" id="portSlotAdd">+ porta extra</button>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" name="_method" value="PATCH" class="btn btn-primary btn-sm pull-right">Save</button>
                    <a href="{{ route('admin.nests.egg.export', $egg->id) }}" class="btn btn-sm btn-info pull-right" style="margin-right:10px;">Export</a>
                    <button id="deleteButton" type="submit" name="_method" value="DELETE" class="btn btn-danger btn-sm muted muted-hover">
                        <i class="fa fa-trash-o"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@php($workshopEnabled = in_array('workshop', $egg->inherit_features ?? [], true))
<form action="{{ route('admin.nests.egg.workshop', $egg->id) }}" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Steam Workshop</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">Permite que usuários naveguem e instalem mods da Steam Workshop no painel para servidores deste egg. Requer Steam Web API Key configurada em Settings → Advanced.</p>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pWorkshopEnabled" name="enabled" type="checkbox" value="1" @if($workshopEnabled) checked @endif />
                            <label for="pWorkshopEnabled">Habilitar Steam Workshop para este egg</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pWorkshopAppId" class="control-label">Workshop App ID</label>
                        <input type="number" class="form-control" name="workshop_app_id" id="pWorkshopAppId" min="1" value="{{ old('workshop_app_id', $egg->workshop_app_id) }}" placeholder="Ex.: 4000 (GMod), 550 (L4D2), 346110 (ARK)" />
                        <p class="text-muted small">App ID do <strong>jogo</strong> na Steam Workshop (não use o ID do dedicated server). Exemplos: Garry's Mod = <code>4000</code>, Left 4 Dead 2 = <code>550</code>, ARK = <code>346110</code>.</p>
                    </div>
                    <div class="form-group">
                        <label for="pWorkshopSyncDriver" class="control-label">Driver de sync</label>
                        <select class="form-control" name="workshop_sync_driver" id="pWorkshopSyncDriver">
                            <option value="">Automático (detectar pelo nome do egg)</option>
                            @foreach($workshopSyncDrivers ?? [] as $driver)
                                <option value="{{ $driver['id'] }}" @if(old('workshop_sync_driver', $egg->workshop_sync_driver) === $driver['id']) selected @endif>
                                    {{ $driver['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-muted small" id="pWorkshopSyncDriverHelp">
                            @foreach($workshopSyncDrivers ?? [] as $driver)
                                @if(old('workshop_sync_driver', $egg->workshop_sync_driver) === $driver['id'])
                                    {{ $driver['description'] }}
                                @endif
                            @endforeach
                        </p>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <input type="hidden" name="_method" value="PATCH" />
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Salvar Workshop</button>
                </div>
            </div>
        </div>
    </div>
</form>

@php($dnsProfile = $egg->dnsProfile)
<form action="{{ route('admin.nests.egg.dns', $egg->id) }}" method="POST">
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Configuração DNS (Cloudflare)</h3>
                </div>
                <div class="box-body">
                    <p class="text-muted">Configure quais tipos de registro DNS os usuários podem criar para servidores deste egg. Marque <strong>Habilitar DNS</strong> abaixo e clique em <strong>Salvar DNS</strong> - a feature <code>dns</code> será adicionada automaticamente ao egg.</p>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <div class="checkbox checkbox-primary">
                                    <input id="pDnsEnabled" name="enabled" type="checkbox" value="1" @if($dnsProfile?->enabled) checked @endif />
                                    <label for="pDnsEnabled">Habilitar DNS para este egg</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="pDnsAllowedTypes" class="control-label">Tipos permitidos</label>
                                <select class="form-control" name="allowed_types[]" id="pDnsAllowedTypes" multiple>
                                    @foreach(['A', 'CNAME', 'SRV'] as $type)
                                        <option value="{{ $type }}" @if(in_array($type, $dnsProfile?->allowed_types ?? ['A'])) selected @endif>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" id="srv-config-fields">
                                <label class="control-label">Configuração SRV</label>
                                <div class="row">
                                    <div class="col-sm-6">
                                        <input type="text" name="srv_service" class="form-control" placeholder="Serviço (_minecraft)" value="{{ old('srv_service', $dnsProfile?->srv_service ?? '_minecraft') }}" />
                                        <p class="text-muted small">Ex.: <code>_minecraft</code>, <code>_ts3</code></p>
                                    </div>
                                    <div class="col-sm-6">
                                        <input type="text" name="srv_protocol" class="form-control" placeholder="Protocolo (_tcp)" value="{{ old('srv_protocol', $dnsProfile?->srv_protocol ?? '_tcp') }}" />
                                        <p class="text-muted small">Ex.: <code>_tcp</code> ou <code>_udp</code></p>
                                    </div>
                                </div>
                                <div class="row" style="margin-top: 10px;">
                                    <div class="col-sm-6">
                                        <label for="pSrvPriority" class="control-label">Prioridade</label>
                                        <input type="number" name="srv_priority" id="pSrvPriority" class="form-control" min="0" max="65535" value="{{ old('srv_priority', $dnsProfile?->srv_priority ?? 0) }}" />
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="pSrvWeight" class="control-label">Peso</label>
                                        <input type="number" name="srv_weight" id="pSrvWeight" class="form-control" min="0" max="65535" value="{{ old('srv_weight', $dnsProfile?->srv_weight ?? 5) }}" />
                                    </div>
                                </div>
                                <p class="text-muted small" style="margin-top: 8px;">Porta e destino SRV são obtidos automaticamente da alocação primária do servidor.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="pDnsDefaultType" class="control-label">Tipo padrão</label>
                                <select name="default_type" id="pDnsDefaultType" class="form-control">
                                    <option value="A" @if(($dnsProfile?->default_type ?? 'A') === 'A') selected @endif>A</option>
                                    <option value="CNAME" @if(($dnsProfile?->default_type ?? 'A') === 'CNAME') selected @endif>CNAME</option>
                                    <option value="SRV" @if(($dnsProfile?->default_type ?? 'A') === 'SRV') selected @endif>SRV</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="pDnsMaxRecords" class="control-label">Máximo de registros por servidor</label>
                                <input type="number" name="max_records_per_server" id="pDnsMaxRecords" class="form-control" min="1" max="50" value="{{ old('max_records_per_server', $dnsProfile?->max_records_per_server ?? 3) }}" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <input type="hidden" name="_method" value="PATCH" />
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Salvar DNS</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#pConfigFrom').select2();
    $('#deleteButton').on('mouseenter', function (event) {
        $(this).find('i').html(' Delete Egg');
    }).on('mouseleave', function (event) {
        $(this).find('i').html('');
    });
    $('textarea[data-action="handle-tabs"]').on('keydown', function(event) {
        if (event.keyCode === 9) {
            event.preventDefault();

            var curPos = $(this)[0].selectionStart;
            var prepend = $(this).val().substr(0, curPos);
            var append = $(this).val().substr(curPos);

            $(this).val(prepend + '    ' + append);
        }
    });
    $('#pConfigFeatures').select2({
        tags: true,
        selectOnClose: false,
        tokenSeparators: [',', ' '],
    });
    var workshopDriverDescriptions = @json(collect($workshopSyncDrivers ?? [])->pluck('description', 'id'));
    $('#pWorkshopSyncDriver').on('change', function () {
        var id = $(this).val();
        $('#pWorkshopSyncDriverHelp').text(id ? (workshopDriverDescriptions[id] || '') : 'Deixe em branco para detectar automaticamente com base no nome do egg.');
    });
    $('#pCommandTransmissionType').on('change', function() {
        if ($(this).val() === 'rcon') {
            $('#rconProtocolGroup').slideDown();
        } else {
            $('#rconProtocolGroup').slideUp();
        }
    });

    (function () {
        var idx = $('#portSlotsTable tbody tr').length;
        var policyOpts = @json($gcorePolicies ?? \Pterodactyl\Services\Gcore\GcoreClient::POLICIES);
        var protoOpts = @json(array_values(array_filter(\Pterodactyl\Services\Gcore\GcoreClient::PROTOCOLS, fn ($p) => $p !== 'any')));
        function policySelect(i) {
            var h = '<select name="port_slots[' + i + '][gcore_policy]" class="form-control input-sm"><option value="">— sem ACL —</option>';
            policyOpts.forEach(function (p) { h += '<option value="' + p + '">' + p + '</option>'; });
            return h + '</select>';
        }
        function protoSelect(i) {
            var h = '<select name="port_slots[' + i + '][gcore_proto]" class="form-control input-sm"><option value="">any</option>';
            protoOpts.forEach(function (p) { h += '<option value="' + p + '">' + p + '</option>'; });
            return h + '</select>';
        }
        function reindex() {
            $('#portSlotsTable tbody tr').each(function (i) {
                $(this).find('input, select').each(function () {
                    var name = $(this).attr('name');
                    if (!name) return;
                    $(this).attr('name', name.replace(/port_slots\[\d+]/, 'port_slots[' + i + ']'));
                });
            });
            idx = $('#portSlotsTable tbody tr').length;
        }
        $('#portSlotAdd').on('click', function () {
            var row = '<tr>' +
                '<td><input type="text" name="port_slots[' + idx + '][env_variable]" class="form-control input-sm" placeholder="QUERY_PORT"></td>' +
                '<td><input type="text" name="port_slots[' + idx + '][name]" class="form-control input-sm" placeholder="Query Port"></td>' +
                '<td><input type="text" name="port_slots[' + idx + '][description]" class="form-control input-sm" placeholder="Porta de query Steam"></td>' +
                '<td>' + policySelect(idx) + '</td>' +
                '<td>' + protoSelect(idx) + '</td>' +
                '<td class="text-center"><input type="hidden" name="port_slots[' + idx + '][required]" value="0">' +
                '<input type="checkbox" name="port_slots[' + idx + '][required]" value="1"></td>' +
                '<td><button type="button" class="btn btn-xs btn-danger port-slot-remove">&times;</button></td>' +
                '</tr>';
            $('#portSlotsTable tbody').append(row);
            idx++;
        });
        $('#portSlotsTable').on('click', '.port-slot-remove', function () {
            $(this).closest('tr').remove();
            reindex();
        });
    })();
    </script>
@endsection
