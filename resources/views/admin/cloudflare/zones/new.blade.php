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
                        <label for="pDomain" class="control-label">Domínio</label>
                        <input type="text" name="domain" id="pDomain" class="form-control" value="{{ old('domain') }}" placeholder="jogos.exemplo.com" required />
                        <p class="text-muted small">O domínio deve existir na conta Cloudflare configurada. O painel irá buscar o Zone ID automaticamente.</p>
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
