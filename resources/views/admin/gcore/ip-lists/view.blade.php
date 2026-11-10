@extends('layouts.admin')

@section('title')
    Gcore · Lista {{ $list->name }}
@endsection

@section('content-header')
    <h1>{{ $list->name }}<small>{{ $list->entry_count }} entradas IPv4</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.gcore') }}">Gcore DDoS</a></li>
        <li><a href="{{ route('admin.gcore.ip-lists') }}">Listas de IP</a></li>
        <li class="active">{{ $list->name }}</li>
    </ol>
@endsection

@section('content')
@include('admin.gcore.ip-lists._styles')
@php
    $statusLabel = ['changed' => 'atualizada', 'unchanged' => 'sem alteração', 'rejected' => 'rejeitada', 'error' => 'erro'];
@endphp
<div class="gcore-ddos-wrap">
    <div class="meta-row">
        <a href="{{ route('admin.gcore.ip-lists') }}">← Listas</a>
        <span class="chip"><strong>Entradas</strong> {{ $list->entry_count }}</span>
        <span class="chip"><strong>Intervalo</strong> {{ $list->refresh_hours }}h</span>
        <span class="chip"><strong>Verificada</strong> {{ $list->last_checked_at?->diffForHumans() ?? 'nunca' }}</span>
        <span class="chip"><strong>Mudou</strong> {{ $list->last_changed_at?->diffForHumans() ?? '—' }}</span>
        @if($list->last_status)<span class="ipl-st {{ $list->last_status }}">{{ $statusLabel[$list->last_status] ?? $list->last_status }}</span>@endif
    </div>

    @if($errors->any())
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">{{ implode(' ', $errors->all()) }}</div>
    @endif
    @if($list->last_error)
        <div class="flash flash-err" style="border-radius:6px;margin-bottom:8px">Última verificação: {{ $list->last_error }}</div>
    @endif

    <div class="toolbar">
        <form method="post" action="{{ route('admin.gcore.ip-lists.refresh', $list->id) }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-primary">Atualizar agora</button>
        </form>
        <form method="post" action="{{ route('admin.gcore.ip-lists.refresh', $list->id) }}" style="display:inline"
              onsubmit="return confirm('Aceitar a lista mesmo que tenha encolhido além do limite? Lista vazia continua sendo recusada.')">
            @csrf
            <input type="hidden" name="force" value="1">
            <button type="submit" class="btn">Forçar atualização</button>
        </form>
        <span class="toolbar-spacer"></span>
        <span class="toolbar-hint">“Forçar” ignora só o limite de encolhimento. Mudanças são enviadas na hora para as regras que usam a lista.</span>
    </div>

    <div class="ipl-grid">
        <div>
            <div class="panel" style="margin-bottom:12px">
                <div class="panel-head">Regras que usam esta lista ({{ count($usage) }})</div>
                @if($usage === [])
                    <div class="panel-body ipl-muted">Nenhuma. Selecione a lista no campo <code>sip</code> de uma regra no editor do perfil.</div>
                @else
                    <table class="data list">
                        <thead><tr><th>Perfil</th><th>Regra</th><th>Estado</th><th>Aplicada</th></tr></thead>
                        <tbody>
                        @foreach($usage as $u)
                            <tr>
                                <td><a href="{{ route('admin.gcore.profile', $u['profile_id']) }}" class="ipl-mono">{{ $u['ip'] ?: '#' . $u['profile_id'] }}</a></td>
                                <td class="ipl-mono">{{ $u['policy'] }} · {{ $u['proto'] }} · dport {{ $u['dports'] ? implode(',', array_slice($u['dports'], 0, 6)) . (count($u['dports']) > 6 ? '…' : '') : 'todas' }}</td>
                                <td><span class="ipl-st {{ $u['status'] }}">{{ $u['status'] }}</span>@if($u['error'])<div class="ipl-muted" style="font-size:11px">{{ $u['error'] }}</div>@endif</td>
                                <td>{{ $u['applied_at']?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="panel">
                <div class="panel-head">Entradas atuais ({{ $list->entry_count }})</div>
                @if($list->entryList() === [])
                    <div class="panel-body ipl-muted">Vazia. A lista só pode ser usada numa regra depois de baixada com sucesso.</div>
                @else
                    <div class="ipl-entries">@foreach($list->entryList() as $c)<div>{{ $c }}</div>@endforeach</div>
                @endif
            </div>
        </div>

        <div>
            <div class="panel" style="margin-bottom:12px">
                <div class="panel-head">Configuração</div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.gcore.ip-lists.view', $list->id) }}" class="ipl-form">
                        @csrf
                        @method('PATCH')
                        <div class="field field-full">
                            <label>Nome</label>
                            <input type="text" name="name" value="{{ old('name', $list->name) }}" maxlength="64" required>
                        </div>
                        <div class="field field-full">
                            <label>URL</label>
                            <input type="url" name="url" value="{{ old('url', $list->url) }}" required>
                        </div>
                        <div class="field field-full">
                            <label>Atualizar a cada (horas)</label>
                            <input type="number" name="refresh_hours" min="1" max="720" value="{{ old('refresh_hours', $list->refresh_hours) }}" required>
                        </div>
                        <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $list->enabled))> Atualização automática</label>
                        <div style="margin-top:10px">
                            <button type="submit" class="btn btn-primary">Salvar</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head">Excluir</div>
                <div class="panel-body">
                    <form method="post" action="{{ route('admin.gcore.ip-lists.delete', $list->id) }}" onsubmit="return confirm({{ json_encode('Excluir a lista ' . $list->name . '?') }})">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn" @disabled($usage !== []) title="{{ $usage !== [] ? 'Em uso por regras' : '' }}">Excluir lista</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <h1 class="page-title" style="margin-top:18px">Log</h1>
    @include('admin.gcore.ip-lists._log', ['logs' => $logs, 'showList' => false])
</div>
@endsection
