@extends('layouts.admin')

@section('title')
    hgHLDS
@endsection

@section('content-header')
    <h1>hgHLDS<small>Ativação de licença do engine.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">hgHLDS</li>
    </ol>
@endsection

@section('content')
    <form action="{{ route('admin.hghlds') }}" method="POST" autocomplete="off">
        {!! csrf_field() !!}
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">IPs autorizados</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Allowlist</label>
                            <textarea name="allowlist" class="form-control" rows="8" spellcheck="false" placeholder="89.39.161.189">{{ old('allowlist', $allowlist) }}</textarea>
                            <p class="text-muted small">Um IP ou range CIDR por linha. Use o IP de <strong>saída</strong> dos containers de cada node, que pode ser diferente do IP das alocações. Com a lista vazia, nenhuma ativação é aceita.</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary btn-sm">Salvar</button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Chave de assinatura</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label class="control-label">Chave pública</label>
                            @if($publicKey)
                                <input type="text" class="form-control" value="{{ $publicKey }}" readonly>
                                <p class="text-muted small">Precisa ser igual à chave pública embutida no engine.</p>
                            @else
                                <p class="text-danger">Nenhuma chave configurada. As ativações respondem 503.</p>
                            @endif
                        </div>
                        <div class="form-group">
                            <label class="control-label">{{ $publicKey ? 'Substituir chave privada' : 'Chave privada' }}</label>
                            <input type="password" name="private_key" class="form-control" autocomplete="new-password" spellcheck="false" maxlength="128">
                            <p class="text-muted small">Ed25519 em hex: semente (64 caracteres) ou chave secreta de 64 bytes (128 caracteres). Fica criptografada e nunca é exibida. Deixe em branco para manter a atual.</p>
                        </div>
                        <div class="form-group">
                            <label class="control-label">Endpoint</label>
                            <input type="text" class="form-control" value="POST {{ route('api:hghlds.activate') }}" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
