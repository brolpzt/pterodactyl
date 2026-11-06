@extends('layouts.admin')

@section('title')
    FastDL Node: {{ $node->name }} — Bucket
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Pastas do bucket × servidores</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.fastdl') }}">FastDL Nodes</a></li>
        <li><a href="{{ route('admin.fastdl.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Bucket</li>
    </ol>
@endsection

@php
    $labels = [
        'orphan' => ['danger', 'Servidor excluído'],
        'disabled' => ['warning', 'FastDL desligado'],
        'other_location' => ['warning', 'Outra location'],
        'unknown' => ['default', 'Não é de servidor'],
        'active' => ['success', 'Em uso'],
    ];
    $removable = collect($audit['folders'] ?? [])->whereIn('status', \Pterodactyl\Services\FastDl\FastDlBucketAuditService::REMOVABLE);
@endphp

@section('content')
<div class="row">
    <div class="col-xs-12">
        @if($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @else
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Bucket <code>{{ $node->bucket }}</code> @if($audit['root'] !== '') / <code>{{ $audit['root'] }}</code>@endif</h3>
                    <div class="box-tools">
                        <a href="{{ route('admin.fastdl.audit', $node->id) }}" class="btn btn-sm btn-default"><i class="fa fa-refresh"></i> Atualizar</a>
                    </div>
                </div>
                <form action="{{ route('admin.fastdl.clean', $node->id) }}" method="POST" id="cleanForm">
                    {!! csrf_field() !!}
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <th style="width:30px;"><input type="checkbox" id="selectAll" title="Selecionar todas removíveis" @if($removable->isEmpty()) disabled @endif /></th>
                                    <th>Pasta</th>
                                    <th>Situação</th>
                                    <th>Servidor</th>
                                    <th class="text-right">Arquivos</th>
                                    <th class="text-right">Tamanho</th>
                                    <th>Última alteração</th>
                                </tr>
                                @forelse($audit['folders'] as $row)
                                    @php([$tone, $label] = $labels[$row['status']])
                                    <tr>
                                        <td>
                                            @if(in_array($row['status'], \Pterodactyl\Services\FastDl\FastDlBucketAuditService::REMOVABLE, true))
                                                <input type="checkbox" name="folders[]" value="{{ $row['folder'] }}" class="folder-check" />
                                            @endif
                                        </td>
                                        <td><code>{{ $row['folder'] === '' ? '(arquivos na raiz)' : $row['folder'] . '/' }}</code></td>
                                        <td><span class="label label-{{ $tone }}">{{ $label }}</span></td>
                                        <td>
                                            @if($row['server'])
                                                <a href="{{ route('admin.servers.view', $row['server']->id) }}">#{{ $row['server']->id }} {{ $row['server']->name }}</a>
                                                <span class="text-muted small">({{ $row['server']->node?->name }})</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-right">{{ number_format($row['objects']) }}</td>
                                        <td class="text-right">{{ \Illuminate\Support\Number::fileSize($row['bytes'], 1) }}</td>
                                        <td>{{ $row['last_modified'] ? \Carbon\Carbon::parse($row['last_modified'])->format('d/m/Y H:i') : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted">O bucket está vazio.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer">
                        <p class="text-muted small">
                            Cada servidor sincroniza em <code>{{ $audit['root'] }}&lt;8 primeiros caracteres do UUID&gt;/</code>.
                            Só dá para remover pastas de servidores excluídos, com FastDL desligado ou em outra location. Pastas em uso e as que não são de servidor nunca são removidas por aqui.
                        </p>
                        <button type="submit" class="btn btn-danger btn-sm" id="cleanButton" disabled>Remover pastas selecionadas</button>
                        <span class="text-muted small" style="margin-left:8px;">{{ $removable->count() }} pasta(s) removível(is), {{ \Illuminate\Support\Number::fileSize($removable->sum('bytes'), 1) }}</span>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        (function () {
            var checks = Array.prototype.slice.call(document.querySelectorAll('.folder-check'));
            var all = document.getElementById('selectAll');
            var button = document.getElementById('cleanButton');
            var form = document.getElementById('cleanForm');
            if (!form) return;
            function refresh() {
                var n = checks.filter(function (c) { return c.checked; }).length;
                button.disabled = n === 0;
                button.textContent = n ? 'Remover ' + n + ' pasta(s) selecionada(s)' : 'Remover pastas selecionadas';
            }
            checks.forEach(function (c) { c.addEventListener('change', refresh); });
            if (all) all.addEventListener('change', function () { checks.forEach(function (c) { c.checked = all.checked; }); refresh(); });
            form.addEventListener('submit', function (e) {
                var n = checks.filter(function (c) { return c.checked; }).length;
                if (!n) { e.preventDefault(); return; }
                if (button.dataset.confirmed === '1') return;
                e.preventDefault();
                swal({
                    type: 'warning',
                    title: 'Remover ' + n + ' pasta(s) do bucket?',
                    text: 'Os arquivos serão apagados do FastDL e não podem ser recuperados.',
                    showCancelButton: true,
                    confirmButtonText: 'Remover',
                    confirmButtonColor: '#d9534f',
                    closeOnConfirm: false,
                    showLoaderOnConfirm: true,
                }, function () { button.dataset.confirmed = '1'; form.submit(); });
            });
        })();
    </script>
@endsection
