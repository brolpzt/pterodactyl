{{-- Expects $logs (GcoreIpListLog collection) and $showList (bool). --}}
@php
    $statusLabel = ['changed' => 'atualizada', 'unchanged' => 'sem alteração', 'rejected' => 'rejeitada', 'error' => 'erro'];
    $triggerLabel = ['schedule' => 'agendado', 'manual' => 'manual', 'force' => 'forçado'];
@endphp
<div class="acl-wrap" style="max-height:none">
    <table class="data list">
        <thead>
        <tr>
            <th>Quando</th>
            @if($showList)<th>Lista</th>@endif
            <th>Resultado</th>
            <th>Origem</th>
            <th>Entradas</th>
            <th>Mudanças</th>
            <th>Perfis Gcore</th>
            <th>Mensagem</th>
        </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="ipl-mono" title="{{ $log->created_at }}">{{ $log->created_at?->format('d/m H:i') }}<br><span class="ipl-muted">{{ $log->created_at?->diffForHumans() }}</span></td>
                @if($showList)
                    <td>
                        @if($log->list)
                            <a href="{{ route('admin.gcore.ip-lists.view', $log->gcore_ip_list_id) }}">{{ $log->list->name }}</a>
                        @else
                            <span class="ipl-muted">#{{ $log->gcore_ip_list_id }}</span>
                        @endif
                    </td>
                @endif
                <td><span class="ipl-st {{ $log->status }}">{{ $statusLabel[$log->status] ?? $log->status }}</span>@if($log->http_status)<br><span class="ipl-muted ipl-mono">HTTP {{ $log->http_status }}</span>@endif</td>
                <td class="ipl-muted">{{ $triggerLabel[$log->trigger] ?? $log->trigger }}</td>
                <td class="ipl-mono">
                    @if($log->status === 'changed'){{ $log->previous_count }} → {{ $log->entry_count }}@else{{ $log->entry_count }}@endif
                </td>
                <td class="ipl-diff">
                    @php $added = $log->added ?? []; $removed = $log->removed ?? []; @endphp
                    @if($added === [] && $removed === [])
                        <span class="ipl-muted">—</span>
                    @else
                        <details>
                            <summary>
                                <span class="add">+{{ count($added) }}</span> / <span class="del">−{{ count($removed) }}</span>
                                @if($log->status === 'rejected') <span class="ipl-muted">(não aplicadas)</span>@endif
                            </summary>
                            @foreach($added as $c)<div class="add">+ {{ $c }}</div>@endforeach
                            @foreach($removed as $c)<div class="del">− {{ $c }}</div>@endforeach
                        </details>
                    @endif
                </td>
                <td>
                    @forelse($log->profiles ?? [] as $p)
                        <div>
                            <a href="{{ route('admin.gcore.profile', (int) $p['profile_id']) }}" class="ipl-mono">{{ $p['ip'] ?: '#' . $p['profile_id'] }}</a>
                            <span class="ipl-st {{ $p['status'] === 'updated' ? 'ok' : $p['status'] }}">{{ $p['status'] }}</span>
                            <div class="ipl-muted" style="font-size:11px">{{ $p['message'] }}</div>
                        </div>
                    @empty
                        <span class="ipl-muted">—</span>
                    @endforelse
                </td>
                <td class="ipl-msg">{{ $log->message }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $showList ? 8 : 7 }}" class="ipl-muted">Nenhum registro ainda.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
