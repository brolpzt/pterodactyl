@extends('layouts.admin')

@section('title')
    Steam Workshop
@endsection

@section('content-header')
    <h1>Steam Workshop<small>Credenciais de download e mods instalados nos servidores.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Steam Workshop</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Conta Steam para download (SteamCMD)</h3>
            </div>
            <form action="{{ route('admin.workshop') }}" method="POST">
                <div class="box-body">
                    <p class="text-muted">
                        Usada pelos drivers que descarregam conteúdo Workshop (ex.: L4D2). A conta deve estar
                        <strong>subscrita</strong> aos mods que os utilizadores instalam. Se deixar em branco, cada servidor usa
                        <code>STEAM_USER</code> / <code>STEAM_PASS</code> definidos no Startup do egg.
                    </p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pWorkshopUser" class="control-label">Utilizador Steam</label>
                                <input type="text" class="form-control" id="pWorkshopUser" name="pterodactyl:steam:workshop_user" value="{{ old('pterodactyl:steam:workshop_user', $workshopUser) }}" autocomplete="username" />
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pWorkshopPass" class="control-label">Palavra-passe</label>
                                <input type="password" class="form-control" id="pWorkshopPass" name="pterodactyl:steam:workshop_pass" autocomplete="new-password" placeholder="@if($workshopPassConfigured)•••••••• (configurada)@else Opcional @endif" />
                                <p class="text-muted small">Deixe em branco para manter. Introduza <code>!e</code> para remover.</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="pWorkshopAuth" class="control-label">Steam Guard (código)</label>
                                <input type="password" class="form-control" id="pWorkshopAuth" name="pterodactyl:steam:workshop_auth" autocomplete="one-time-code" placeholder="@if($workshopAuthConfigured)•••••••• (configurado)@else Opcional @endif" />
                                <p class="text-muted small">Deixe em branco para manter. Introduza <code>!e</code> para remover.</p>
                            </div>
                        </div>
                    </div>
                    <div class="callout callout-info no-margin">
                        <p class="no-margin">
                            <strong>Steam Web API Key</strong> (browse Workshop):
                            @if($steamApiKeyConfigured)
                                <span class="label label-success">Configurada</span>
                            @else
                                <span class="label label-warning">Não configurada</span>
                            @endif
                            — configure em <a href="{{ route('admin.settings.advanced') }}">Settings → Advanced</a>.
                        </p>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <input type="hidden" name="_method" value="PATCH" />
                    <button type="submit" class="btn btn-primary btn-sm pull-right">Guardar credenciais</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Mods instalados</h3>
                <div class="box-tools">
                    <span class="label label-default">{{ $totalItems }} mods</span>
                    <span class="label label-default">{{ $totalServers }} servidores</span>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                @if($items->isEmpty())
                    <p class="text-muted" style="padding: 15px;">Nenhum mod Workshop instalado em servidores.</p>
                @else
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mod</th>
                                <th>Workshop ID</th>
                                <th>Servidor</th>
                                <th>Egg</th>
                                <th>Proprietário</th>
                                <th class="text-right">Instalado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                @php($server = $item->server)
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px;">
                                            @if($item->preview_url)
                                                <img src="{{ $item->preview_url }}" alt="" style="width:48px;height:32px;object-fit:cover;border-radius:3px;" />
                                            @endif
                                            <div>
                                                <strong>{{ $item->title ?: 'Sem título' }}</strong><br />
                                                <a href="https://steamcommunity.com/sharedfiles/filedetails/?id={{ $item->published_file_id }}" target="_blank" rel="noopener">Ver na Steam</a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code>{{ $item->published_file_id }}</code></td>
                                    <td>
                                        @if($server)
                                            <a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $server?->egg?->name ?? '—' }}</td>
                                    <td>
                                        @if($server?->user)
                                            <a href="{{ route('admin.users.view', $server->user->id) }}">{{ $server->user->username }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $item->created_at?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @if($items->hasPages())
                <div class="box-footer clearfix">
                    {!! $items->render() !!}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
