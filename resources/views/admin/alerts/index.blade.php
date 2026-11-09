@extends('layouts.admin')

@section('title')
    Alertas
@endsection

@section('content-header')
    <h1>Alertas<small>Avisos exibidos no painel, nos servidores e no admin.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Alertas</li>
    </ol>
@endsection

@section('content')
    @php
        $typeLabels = ['info' => 'label-info', 'success' => 'label-success', 'warning' => 'label-warning', 'danger' => 'label-danger'];
        $statusLabels = ['Ativo' => 'label-success', 'Agendado' => 'label-info', 'Encerrado' => 'label-default', 'Inativo' => 'label-default'];
        $placements = \Pterodactyl\Models\PanelAlert::PLACEMENTS;
        $types = \Pterodactyl\Models\PanelAlert::TYPES;
    @endphp
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Alertas</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.alerts.new') }}" class="btn btn-sm btn-primary">Novo alerta</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <tbody>
                        <tr>
                            <th>Alerta</th>
                            <th class="text-center">Tipo</th>
                            <th>Onde</th>
                            <th class="text-center">Público</th>
                            <th>Período</th>
                            <th class="text-center">Status</th>
                        </tr>
                        @forelse ($alerts as $alert)
                            <tr>
                                <td><a href="{{ route('admin.alerts.view', $alert->id) }}">{{ $alert->title ?: \Illuminate\Support\Str::limit($alert->message, 70) }}</a></td>
                                <td class="text-center"><span class="label {{ $typeLabels[$alert->type] ?? 'label-default' }}">{{ $types[$alert->type] ?? $alert->type }}</span></td>
                                <td>
                                    {{ collect($alert->placements)->map(fn ($placement) => $placements[$placement] ?? $placement)->implode(', ') }}
                                    @if($alert->isTargeted())
                                        <span class="label label-primary">Filtrado</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $alert->audience === 'admins' ? 'Admins' : 'Todos' }}</td>
                                <td>
                                    @if($alert->starts_at || $alert->ends_at)
                                        {{ $alert->starts_at?->format('d/m/Y H:i') ?? 'Agora' }} → {{ $alert->ends_at?->format('d/m/Y H:i') ?? 'sem fim' }}
                                    @else
                                        <span class="text-muted">Sempre</span>
                                    @endif
                                </td>
                                <td class="text-center"><span class="label {{ $statusLabels[$alert->status()] }}">{{ $alert->status() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Nenhum alerta criado.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
