@extends('layouts.admin')

@section('title')
    Gcore · Regras vinculadas
@endsection

@section('content-header')
    <h1>Regras vinculadas<small>Regras ACL extras que os slots de porta dos eggs incluem automaticamente</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.gcore') }}">Gcore DDoS</a></li>
        <li class="active">Regras vinculadas</li>
    </ol>
@endsection

@section('content')
@include('admin.gcore.ip-lists._styles')
@php
    $form = $editing;
    $sipValue = old('sip_list', $form ? implode(', ', $form->sipList()) : '');
@endphp
<div class="gcore-ddos-wrap">
    <div class="meta-row">
        <a href="{{ route('admin.gcore') }}">← Perfis</a>
        <span>
            No egg, cada slot de porta tem "Também incluir em": as portas dos servidores daquele slot entram nestas regras em todo sync
            (criar/excluir servidor ou "Sync Gcore" do node). Portas que não são de allocations do painel ficam como estão.
            Uma regra sem nenhuma porta é removida da Gcore (dport vazio valeria para todas as portas). Regras novas entram no topo da tabela;
            depois disso o sync mantém a posição em que você deixar.
        </span>
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
                    <th>Política</th>
                    <th>Proto</th>
                    <th>sip fixo</th>
                    <th>Portas</th>
                    <th>Usada em</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($rules as $rule)
                    @php $used = $usage[$rule->id] ?? []; @endphp
                    <tr>
                        <td><strong>{{ $rule->name }}</strong></td>
                        <td class="ipl-mono">{{ $rule->policy }}</td>
                        <td class="ipl-mono">{{ $rule->proto ?? 'any' }}</td>
                        <td class="ipl-mono">{{ implode(', ', $rule->sipList()) ?: '—' }}</td>
                        <td>{{ $rule->modeLabel() }}</td>
                        <td>
                            @if($used === [])
                                <span class="ipl-st pending" title="O sync ignora regras que nenhum egg usa">nenhum egg</span>
                            @else
                                @foreach($used as $u)<div class="ipl-mono">{{ $u }}</div>@endforeach
                            @endif
                        </td>
                        <td style="white-space:nowrap">
                            <a class="btn" href="{{ route('admin.gcore.linked-rules', ['edit' => $rule->id]) }}">Editar</a>
                            <form method="post" action="{{ route('admin.gcore.linked-rules.delete', $rule->id) }}" style="display:inline" onsubmit="return confirm('Excluir a regra vinculada {{ $rule->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">Excluir</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ipl-muted">Nenhuma regra vinculada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div class="panel-head">{{ $form ? 'Editar: ' . $form->name : 'Nova regra vinculada' }}</div>
            <div class="panel-body">
                <form method="post" action="{{ $form ? route('admin.gcore.linked-rules.update', $form->id) : route('admin.gcore.linked-rules') }}" class="ipl-form">
                    @csrf
                    @if($form) @method('PATCH') @endif
                    <div class="field field-full">
                        <label>Nome</label>
                        <input type="text" name="name" value="{{ old('name', $form?->name) }}" maxlength="100" placeholder="CS 1.6 BOOST" required>
                    </div>
                    <div class="field field-full">
                        <label>Política</label>
                        <select name="policy" required>
                            @foreach($policies as $p)
                                <option value="{{ $p }}" @selected(old('policy', $form?->policy) === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field field-full">
                        <label>Proto</label>
                        <select name="proto">
                            <option value="">any</option>
                            @foreach($protos as $p)
                                <option value="{{ $p }}" @selected(old('proto', $form?->proto) === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field field-full">
                        <label>sip fixo (opcional; IPs/CIDRs separados por vírgula)</label>
                        <input type="text" name="sip_list" value="{{ $sipValue }}" placeholder="216.238.125.59">
                    </div>
                    <div class="field field-full">
                        <label>Portas do servidor entram em</label>
                        <select name="mode" required>
                            <option value="dport" @selected(old('mode', $form?->mode ?? 'dport') === 'dport')>dport (ex.: BOOST)</option>
                            <option value="same_port" @selected(old('mode', $form?->mode) === 'same_port')>sport + dport: origem = destino (ex.: DROP)</option>
                        </select>
                    </div>
                    <p class="ipl-muted" style="font-size:11px">
                        "Origem = destino": a Gcore cruza as listas, então a regra também casa origem e destino diferentes entre essas portas
                        (ex.: 27015→27017). Não marque em slots que usam portas de onde vêm respostas legítimas (ex.: 27010/27011 do master server).
                    </p>
                    <div style="margin-top:10px">
                        <button type="submit" class="btn btn-primary">{{ $form ? 'Salvar' : 'Criar' }}</button>
                        @if($form)<a class="btn" href="{{ route('admin.gcore.linked-rules') }}">Cancelar</a>@endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
