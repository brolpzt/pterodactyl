@extends('layouts.admin')

@section('title')
    Cloudflare DNS
@endsection

@section('content-header')
    <h1>Cloudflare DNS<small>Gerencie a integração com a API Cloudflare e os domínios disponíveis.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Cloudflare DNS</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Conta Cloudflare</h3>
            </div>
            <form action="{{ route('admin.cloudflare.account') }}" method="POST">
                <div class="box-body">
                    {!! csrf_field() !!}
                    <input type="hidden" name="_method" value="PATCH" />
                    <div class="form-group">
                        <label for="pName" class="control-label">Nome</label>
                        <input type="text" name="name" id="pName" class="form-control" value="{{ old('name', $account->name ?? 'Cloudflare') }}" />
                    </div>
                    <div class="form-group">
                        <label for="pApiToken" class="control-label">API Token</label>
                        <input type="password" name="api_token" id="pApiToken" class="form-control" value="" placeholder="{{ $account && $account->isConfigured() ? '•••••••• (deixe em branco para manter)' : 'Token com permissão Zone.DNS:Edit' }}" />
                        <p class="text-muted small">Crie um token em <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noreferrer">Cloudflare Dashboard</a> com permissão <code>Zone.DNS:Edit</code> nas zonas desejadas.</p>
                    </div>
                    @if($account && $account->isConfigured())
                        <span class="label label-success">Configurado</span>
                    @else
                        <span class="label label-warning">Não configurado</span>
                    @endif
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right">Salvar Conta</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Domínios</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.cloudflare.zones.new') }}"><button type="button" class="btn btn-sm btn-primary">Adicionar Domínio</button></a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Label</th>
                            <th>Domínio público</th>
                            <th>Zona CF</th>
                            <th>Status</th>
                            <th>Usuários</th>
                            <th>Registros</th>
                        </tr>
                        @forelse ($zones as $zone)
                            <tr>
                                <td><a href="{{ route('admin.cloudflare.zones.view', $zone->id) }}">{{ $zone->label }}</a></td>
                                <td><code>{{ $zone->publicDomain() }}</code></td>
                                <td><code>{{ $zone->domain }}</code></td>
                                <td>
                                    @if($zone->is_active)
                                        <span class="label label-success">Ativo</span>
                                    @else
                                        <span class="label label-danger">Inativo</span>
                                    @endif
                                </td>
                                <td>
                                    @if($zone->allow_user_create)
                                        <span class="label label-info">Podem criar</span>
                                    @else
                                        <span class="label label-default">Somente admin</span>
                                    @endif
                                </td>
                                <td>{{ $zone->dns_records_count }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Nenhum domínio configurado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Registros DNS Criados</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Servidor</th>
                            <th>Domínio</th>
                            <th>Tipo</th>
                            <th>Nome</th>
                            <th>Conteúdo</th>
                            <th>Proxy</th>
                            <th class="text-right">Criado</th>
                        </tr>
                        @forelse ($records as $record)
                            <tr>
                                <td>
                                    @if($record->server)
                                        <a href="{{ route('admin.servers.view', $record->server->id) }}">{{ $record->server->name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $record->zone?->label }} <code>{{ $record->zone?->publicDomain() }}</code></td>
                                <td><code>{{ $record->type }}</code></td>
                                <td><code>{{ $record->name }}</code></td>
                                <td><code>{{ $record->content }}</code></td>
                                <td>{{ $record->proxied ? 'Sim' : 'Não' }}</td>
                                <td class="text-right">{{ $record->created_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">Nenhum registro DNS criado pelo painel.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($records->hasPages())
                <div class="box-footer clearfix">
                    {!! $records->render() !!}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
