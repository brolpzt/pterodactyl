@extends('layouts.admin')

@section('title')
    Novo Domínio Cloudflare
@endsection

@section('content-header')
    <h1>Novo Domínio<small>Adicione um domínio da sua conta Cloudflare.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.cloudflare') }}">Cloudflare DNS</a></li>
        <li class="active">Novo</li>
    </ol>
@endsection

@section('content')
<form action="{{ route('admin.cloudflare.zones.new') }}" method="POST">
    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Detalhes do Domínio</h3>
                </div>
                <div class="box-body">
                    {!! csrf_field() !!}
                    <div class="form-group">
                        <label for="pLabel" class="control-label">Label</label>
                        <input type="text" name="label" id="pLabel" class="form-control" value="{{ old('label') }}" placeholder="TeamSpeak 3" required />
                        <p class="text-muted small">Nome exibido ao usuário no painel ao escolher o domínio.</p>
                    </div>
                    <div class="form-group">
                        <label for="pZoneDomain" class="control-label">Zona Cloudflare</label>
                        <input type="text" name="domain" id="pZoneDomain" class="form-control" value="{{ old('domain') }}" placeholder="hostgamer.net" required />
                        <p class="text-muted small">Domínio raiz da zona na Cloudflare. O painel busca o Zone ID automaticamente.</p>
                    </div>
                    <div class="form-group">
                        <label for="pPublicDomain" class="control-label">Domínio público</label>
                        <input type="text" name="public_domain" id="pPublicDomain" class="form-control" value="{{ old('public_domain') }}" placeholder="ts3.hostgamer.net" required />
                        <p class="text-muted small">Domínio que o usuário poderá usar. Deve ser a zona raiz ou um subdomínio dela (ex: <code>ts3.hostgamer.net</code> dentro de <code>hostgamer.net</code>).</p>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pIsActive" name="is_active" type="checkbox" value="1" {{ old('is_active', true) ? 'checked' : '' }} />
                            <label for="pIsActive">Domínio ativo</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pAllowUserCreate" name="allow_user_create" type="checkbox" value="1" {{ old('allow_user_create', true) ? 'checked' : '' }} />
                            <label for="pAllowUserCreate">Permitir que usuários criem registros DNS neste domínio</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pDefaultProxied" name="default_proxied" type="checkbox" value="1" {{ old('default_proxied') ? 'checked' : '' }} />
                            <label for="pDefaultProxied">Proxy Cloudflare padrão para CNAME</label>
                            <p class="text-muted small">Registros do tipo A para jogos devem permanecer sem proxy. Use proxy apenas para CNAMEs de sites.</p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <a href="{{ route('admin.cloudflare') }}" class="btn btn-default">Cancelar</a>
                    <button type="submit" class="btn btn-primary pull-right">Adicionar Domínio</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
