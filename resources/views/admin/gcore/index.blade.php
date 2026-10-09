@extends('layouts.admin')

@section('title')
    Gcore DDoS
@endsection

@section('content-header')
    <h1>Gcore Anti-DDoS<small>Perfis IaaS / Bare Metal — ACL e rate limiters</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Gcore DDoS</li>
    </ol>
@endsection

@section('content')
<link rel="stylesheet" href="/themes/pterodactyl/css/gcore-ddos.css">
<div class="gcore-ddos-wrap">
    <h1 class="page-title">Perfis IaaS</h1>
    <p class="meta-row">Anti-DDoS bare metal · edite ACL e rate limiters por IP</p>

    @if(!$configured)
        <div class="flash flash-err">
            Configure <code>GCORE_API_KEY</code> no <code>.env</code> do painel e limpe o cache de config
            (<code>php artisan config:clear</code>).
        </div>
    @elseif($error)
        <div class="flash flash-err">{{ $error }}</div>
    @elseif($profiles === [])
        <p style="color:var(--muted)">Nenhum perfil nesta conta.</p>
    @else
        <div class="acl-wrap" style="max-height:none">
            <table class="data list">
                <thead>
                <tr>
                    <th class="col-idx">#</th>
                    <th>ID</th>
                    <th>IP protegido</th>
                    <th>POP</th>
                    <th>Template</th>
                    <th>Plano</th>
                    <th>BGP</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($profiles as $n => $p)
                    @php
                        $st = (string) ($p['status']['status'] ?? '—');
                        $chip = str_contains(strtolower($st), 'pending') ? 'status-pending' : 'status-updated';
                        $opt = $p['options'] ?? [];
                    @endphp
                    <tr>
                        <td class="col-idx">{{ $n + 1 }}</td>
                        <td>{{ (int) $p['id'] }}</td>
                        <td><code>{{ $p['ip_address'] ?? '' }}</code></td>
                        <td>{{ $p['site'] ?? '' }}</td>
                        <td>{{ $p['profile_template']['name'] ?? '' }}</td>
                        <td>{{ $p['plan'] ?? '' }}</td>
                        <td>{{ !empty($opt['bgp']) ? 'on' : 'off' }}</td>
                        <td><span class="chip {{ $chip }}">{{ $st }}</span></td>
                        <td>
                            <a class="btn btn-primary" href="{{ route('admin.gcore.profile', (int) $p['id']) }}">Editar ACL</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
