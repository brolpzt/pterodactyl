@extends('layouts.admin')

@section('title')
    Gcore DDoS
@endsection

@section('content-header')
    <h1>Gcore Anti-DDoS<small>Perfis IaaS / Bare Metal - ACL e rate limiters</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Gcore DDoS</li>
    </ol>
@endsection

@section('content')
<link rel="stylesheet" href="/themes/pterodactyl/css/gcore-ddos.css">
<div class="gcore-ddos-wrap">
    <h1 class="page-title">Perfis IaaS</h1>
    <p class="meta-row">Anti-DDoS bare metal · edite ACL e rate limiters por IP · <a href="{{ route('admin.gcore.ip-lists') }}">Listas de IP →</a> · <a href="{{ route('admin.gcore.linked-rules') }}">Regras vinculadas →</a></p>

    @if(!$configured)
        <div class="flash flash-err">
            Configure <code>GCORE_API_KEY</code> no <code>.env</code> do painel e limpe o cache de config
            (<code>php artisan config:clear</code>).
        </div>
    @else
        <p class="meta-row" id="profiles-sync" data-url="{{ route('admin.gcore.live.profiles') }}">
            @if($fetchedAt)
                <span>Cópia de {{ $fetchedAt->diffForHumans() }}</span> · <span class="sync-state">atualizando com a Gcore…</span>
            @else
                <span class="sync-state">Carregando perfis da Gcore…</span>
            @endif
        </p>
        <div class="flash flash-err" id="profiles-error" hidden></div>
        <div id="profiles-box">
            @if($profiles !== null)
                @include('admin.gcore._profiles', ['profiles' => $profiles])
            @endif
        </div>
        <script>
        (function () {
            const sync = document.getElementById('profiles-sync');
            const box = document.getElementById('profiles-box');
            const err = document.getElementById('profiles-error');
            const state = () => sync.querySelector('.sync-state');
            fetch(sync.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(r => r.json().then(j => ({ ok: r.ok, j })))
                .then(({ ok, j }) => {
                    if (!ok || j.error) throw new Error(j.error || 'erro ao consultar a Gcore');
                    box.innerHTML = j.html;
                    sync.innerHTML = '<span class="sync-state" style="color:#6ee7b7">✓ atualizado com a Gcore agora</span>';
                })
                .catch(e => {
                    err.textContent = 'Não foi possível atualizar com a Gcore: ' + e.message + (box.children.length ? ' (mostrando a última cópia)' : '');
                    err.hidden = false;
                    if (state()) state().textContent = 'falhou';
                });
        })();
        </script>
    @endif
</div>
@endsection
