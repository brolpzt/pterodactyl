@extends('layouts.admin')

@section('title')
    Server Boost · Blacklist
@endsection

@section('content-header')
    <h1>Blacklist do boost<small>Jogadores que o sistema de boost não deve mandar para nenhum servidor</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.boost') }}">Server Boost</a></li>
        <li class="active">Blacklist</li>
    </ol>
@endsection

@section('content')
@include('admin.gcore.ip-lists._styles')
@php $tz = (string) config('app.stats_timezone', 'UTC'); @endphp
<style>
    .bst-doc { font-family: var(--mono); font-size: 10.5px; line-height: 1.5; background: var(--surface2); border: 1px solid var(--border); border-radius: 4px; padding: 6px 8px; white-space: pre-wrap; overflow-wrap: anywhere; margin: 6px 0; }
    .bst-url { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
    .bst-url input { flex: 1; min-width: 0; font-family: var(--mono); font-size: 11px; background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 4px; height: 26px; padding: 0 6px; }
    .bst-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 10px; }
    .bst-toolbar input { background: var(--surface2); border: 1px solid var(--border); color: var(--text); border-radius: 4px; height: 26px; padding: 0 8px; font-size: 12px; }
    tr.expired td { opacity: .55; }
</style>
<div class="gcore-ddos-wrap">
    <div class="meta-row">
        <a href="{{ route('admin.boost') }}">← Server Boost</a>
        <span>{{ $activeCount }} {{ $activeCount === 1 ? 'entrada ativa' : 'entradas ativas' }}. IP aceita faixa CIDR; SteamID e nome comparam sem diferenciar maiúsculas. Entradas expiradas saem da API sozinhas.</span>
    </div>

    @if($errors->any())
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">{{ implode(' ', $errors->all()) }}</div>
    @endif

    <div class="ipl-grid">
        <div>
            <form method="get" action="{{ route('admin.boost.blacklist') }}" class="bst-toolbar">
                <input type="search" name="q" value="{{ $search }}" placeholder="Buscar valor ou motivo" style="width:240px">
                <button type="submit" class="btn">Buscar</button>
                @if($search !== '')<a class="btn btn-ghost" href="{{ route('admin.boost.blacklist') }}">Limpar</a>@endif
            </form>
            <div class="acl-wrap" style="max-height:none;min-height:0">
                <table class="data">
                    <thead>
                    <tr><th>Tipo</th><th>Valor</th><th>Motivo</th><th>Adicionado</th><th>Expira</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($entries as $e)
                        <tr class="{{ $e->isExpired() ? 'expired' : '' }}">
                            <td>{{ $types[$e->type] ?? $e->type }}</td>
                            <td class="ipl-mono">{{ $e->value }}</td>
                            <td>{{ $e->reason ?? '—' }}</td>
                            <td class="ipl-mono" title="{{ $e->creator?->username }}">{{ $e->created_at->copy()->setTimezone($tz)->format('d/m/Y H:i') }}@if($e->creator) <span class="ipl-muted">· {{ $e->creator->username }}</span>@endif</td>
                            <td class="ipl-mono">
                                @if($e->expires_at === null)
                                    <span class="ipl-muted">nunca</span>
                                @elseif($e->isExpired())
                                    <span class="ipl-st pending">expirou</span>
                                @else
                                    {{ $e->expires_at->copy()->setTimezone($tz)->format('d/m/Y H:i') }}
                                @endif
                            </td>
                            <td style="text-align:right">
                                <form method="post" action="{{ route('admin.boost.blacklist.delete', $e->id) }}" style="display:inline" onsubmit="return confirm('Tirar {{ $e->value }} da blacklist?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn">Remover</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ipl-muted">Blacklist vazia{{ $search !== '' ? ' para esta busca' : '' }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())<div style="margin-top:8px">{{ $entries->links() }}</div>@endif
        </div>

        <div>
            <div class="panel" style="margin-bottom:12px">
                <div class="panel-head">Adicionar</div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.boost.blacklist.store') }}" class="ipl-form">
                        @csrf
                        <div class="field field-full">
                            <label>Tipo</label>
                            <select name="type" required>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type', 'steam_id') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field field-full">
                            <label>Valor</label>
                            <input type="text" name="value" value="{{ old('value') }}" maxlength="255" placeholder="STEAM_0:1:123 · 177.10.0.5 · 177.10.0.0/16 · Nome" required>
                        </div>
                        <div class="field field-full">
                            <label>Motivo (opcional)</label>
                            <input type="text" name="reason" value="{{ old('reason') }}" maxlength="191">
                        </div>
                        <div class="field field-full">
                            <label>Expira em (dias; vazio = nunca)</label>
                            <input type="number" name="days" value="{{ old('days') }}" min="1" max="3650">
                        </div>
                        <button type="submit" class="btn btn-primary" style="margin-top:6px">Adicionar à blacklist</button>
                    </form>
                    <p class="ipl-muted" style="margin:8px 0 0">Também dá para marcar direto na tabela de jogadores do <a href="{{ route('admin.boost') }}">Server Boost</a>.</p>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head">API para o sistema de boost</div>
                <div class="panel-body">
                    @if($apiUrl === null)
                        <p style="margin:0">Gere o token em <a href="{{ route('admin.boost') }}">Server Boost</a> para obter a URL.</p>
                    @else
                        <div class="bst-url"><input type="text" readonly value="{{ $apiUrl }}" onclick="this.select()"></div>
                        <div class="bst-doc">GET …/blacklist
{"count": 2,
 "ips": ["177.10.0.0/16"],
 "steam_ids": ["STEAM_0:1:123"],
 "names": ["Fulano"],
 "entries": [{"id": 1, "type": "ip", "value": "…",
   "reason": "…", "created_at": "…", "expires_at": null}, …]}

…/blacklist?format=txt&amp;type=ip      um valor por linha
                 (type=ip | steam_id | name)

Envie If-None-Match com o último ETag:
304 quando nada mudou.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
