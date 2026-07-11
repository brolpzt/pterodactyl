@extends('layouts.admin')

@section('title')
    Domínio: {{ $zone->domain }}
@endsection

@section('content-header')
    <h1>{{ $zone->domain }}<small>Configurações do domínio Cloudflare.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.cloudflare') }}">Cloudflare DNS</a></li>
        <li class="active">{{ $zone->domain }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-md-6">
        <form action="{{ route('admin.cloudflare.zones.view', $zone->id) }}" method="POST">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Configuração</h3>
                </div>
                <div class="box-body">
                    {!! csrf_field() !!}
                    <input type="hidden" name="_method" value="PATCH" />
                    <div class="form-group">
                        <label class="control-label">Zone ID</label>
                        <input type="text" readonly class="form-control" value="{{ $zone->zone_id }}" />
                    </div>
                    <div class="form-group">
                        <label for="pDomain" class="control-label">Domínio</label>
                        <input type="text" name="domain" id="pDomain" class="form-control" value="{{ old('domain', $zone->domain) }}" />
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pIsActive" name="is_active" type="checkbox" value="1" @if($zone->is_active) checked @endif />
                            <label for="pIsActive">Domínio ativo</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pAllowUserCreate" name="allow_user_create" type="checkbox" value="1" @if($zone->allow_user_create) checked @endif />
                            <label for="pAllowUserCreate">Permitir que usuários criem registros DNS</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary">
                            <input id="pDefaultProxied" name="default_proxied" type="checkbox" value="1" @if($zone->default_proxied) checked @endif />
                            <label for="pDefaultProxied">Proxy Cloudflare padrão para CNAME</label>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right">Salvar</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-6">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Remover Domínio</h3>
            </div>
            <div class="box-body">
                <p>Remove este domínio do painel. Só é possível se não houver registros DNS associados.</p>
            </div>
            <div class="box-footer">
                <form action="{{ route('admin.cloudflare.zones.delete', $zone->id) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja remover este domínio?');">
                    {!! csrf_field() !!}
                    <input type="hidden" name="_method" value="DELETE" />
                    <button type="submit" class="btn btn-danger">Remover Domínio</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
