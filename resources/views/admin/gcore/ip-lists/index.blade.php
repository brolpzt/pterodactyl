@extends('layouts.admin')

@section('title')
    Gcore · Listas de IP
@endsection

@section('content-header')
    <h1>Listas de IP<small>Listas remotas de IPv4 usadas no sip das regras Gcore</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.gcore') }}">Gcore DDoS</a></li>
        <li class="active">Listas de IP</li>
    </ol>
@endsection

@section('content')
@include('admin.gcore.ip-lists._styles')
@php
    $statusLabel = ['changed' => 'atualizada', 'unchanged' => 'sem alteração', 'rejected' => 'rejeitada', 'error' => 'erro'];
@endphp
<div class="gcore-ddos-wrap">
    <div class="meta-row">
        <a href="{{ route('admin.gcore') }}">← Perfis</a>
        <span>Baixadas automaticamente no intervalo de cada lista. IPv6 é descartado. Se o download falhar, vier vazio ou encolher mais de {{ (int) config('gcore.ip_lists.max_shrink_percent', 50) }}%, a versão anterior é mantida.</span>
    </div>

    @if($errors->any())
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">{{ implode(' ', $errors->all()) }}</div>
    @endif

    <div class="ipl-grid">
        <div class="acl-wrap" style="max-height:none">
            <table class="data list">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>URL</th>
                    <th>Entradas</th>
                    <th>Intervalo</th>
                    <th>Última verificação</th>
                    <th>Última mudança</th>
                    <th>Regras</th>
                    <th>Conferido na Gcore</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($lists as $list)
                    @php $used = $usage[$list->id] ?? []; $bad = collect($used)->whereIn('status', ['error', 'orphan'])->count(); @endphp
                    <tr>
                        <td><a href="{{ route('admin.gcore.ip-lists.view', $list->id) }}"><strong>{{ $list->name }}</strong></a>@unless($list->enabled) <span class="ipl-st">pausada</span>@endunless</td>
                        <td class="ipl-mono" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $list->url }}">{{ $list->url }}</td>
                        <td class="ipl-mono">{{ $list->entry_count }}</td>
                        <td>{{ $list->refresh_hours }}h</td>
                        <td>
                            @if($list->last_checked_at)
                                <span title="{{ $list->last_checked_at }}">{{ $list->last_checked_at->diffForHumans() }}</span>
                                <span class="ipl-st {{ $list->last_status }}" title="{{ $list->last_error }}">{{ $statusLabel[$list->last_status] ?? $list->last_status }}</span>
                            @else
                                <span class="ipl-muted">nunca</span>
                            @endif
                        </td>
                        <td>{{ $list->last_changed_at?->diffForHumans() ?? '—' }}</td>
                        <td>{{ count($used) }}@if($bad) <span class="ipl-st error">{{ $bad }} com problema</span>@endif</td>
                        <td>
                            @php
                                $checked = collect($used)->reject(fn ($u) => $u['status'] === 'orphan');
                                $failed = $checked->filter(fn ($u) => ($u['verify_missing'] ?? 0) > 0);
                                $unknown = $checked->filter(fn ($u) => $u['verified_at'] === null || $u['verify_missing'] === null);
                                $oldest = $checked->pluck('verified_at')->filter()->sort()->first();
                            @endphp
                            @if($checked->isEmpty())
                                <span class="ipl-muted">—</span>
                            @elseif($failed->isNotEmpty())
                                <span class="ipl-st error" title="{{ $failed->pluck('verify_message')->implode(' | ') }}">✗ faltam {{ $failed->sum('verify_missing') }} em {{ $failed->count() }} regra(s)</span>
                            @elseif($unknown->isNotEmpty())
                                <span class="ipl-st pending" title="{{ $unknown->pluck('verify_message')->filter()->implode(' | ') }}">⚠ {{ $unknown->count() }} não conferida(s)</span>
                            @else
                                <span class="ipl-st ok">✓ ok</span>
                            @endif
                            @if($oldest)<span class="ipl-muted" title="{{ $oldest }}">{{ $oldest->diffForHumans() }}</span>@endif
                        </td>
                        <td>
                            <form method="post" action="{{ route('admin.gcore.ip-lists.refresh', $list->id) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn">Atualizar agora</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ipl-muted">Nenhuma lista cadastrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div class="panel-head">Nova lista</div>
            <div class="panel-body">
                <form method="post" action="{{ route('admin.gcore.ip-lists') }}" class="ipl-form">
                    @csrf
                    <div class="field field-full">
                        <label>Nome</label>
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="64" placeholder="Cloudflare" required>
                    </div>
                    <div class="field field-full">
                        <label>URL (texto com um IP/CIDR por linha, ou JSON)</label>
                        <input type="url" name="url" value="{{ old('url') }}" placeholder="https://www.cloudflare.com/ips-v4/" required>
                    </div>
                    <div class="field field-full">
                        <label>Atualizar a cada (horas)</label>
                        <input type="number" name="refresh_hours" min="1" max="720" value="{{ old('refresh_hours', 24) }}" required>
                    </div>
                    <label class="check"><input type="checkbox" name="enabled" value="1" checked> Atualização automática</label>
                    <div style="margin-top:10px">
                        <button type="submit" class="btn btn-primary">Criar e baixar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($runs->isNotEmpty())
        <h1 class="page-title" style="margin-top:18px">Atualizações na fila</h1>
        <p class="meta-row">Download e envio à Gcore rodam na fila; a tabela se atualiza sozinha.</p>
        @include('admin.gcore._runs_box', ['runs' => $runs, 'filter' => ['kind' => 'ip_list']])
    @endif

    <h1 class="page-title" style="margin-top:18px">Log de atualizações</h1>
    <p class="meta-row">Últimos 50 registros de todas as listas</p>
    @include('admin.gcore.ip-lists._log', ['logs' => $logs, 'showList' => true])
</div>
@endsection
