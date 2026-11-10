@php
    $runLabels = ['queued' => 'na fila', 'running' => 'rodando', 'ok' => 'ok', 'warning' => 'mantida', 'error' => 'erro'];
    $runClass = ['queued' => 'pending', 'running' => 'pending', 'ok' => 'ok', 'warning' => 'rejected', 'error' => 'error'];
@endphp
@if($runs->isEmpty())
    <p class="ipl-muted" style="margin:0">Nenhuma sincronização registrada.</p>
@else
    <div class="acl-wrap" style="max-height:none">
        <table class="data list">
            <thead>
            <tr>
                <th>#</th>
                <th>Alvo</th>
                <th>Status</th>
                <th>Motivo</th>
                <th>Quando</th>
                <th>Resultado</th>
            </tr>
            </thead>
            <tbody>
            @foreach($runs as $run)
                <tr>
                    <td class="ipl-mono">{{ $run->id }}</td>
                    <td style="white-space:nowrap">{{ $run->label }}@if($run->force) <span class="ipl-muted">(forçado)</span>@endif</td>
                    <td style="white-space:nowrap">
                        <span class="ipl-st {{ $runClass[$run->status] ?? '' }}">{{ $runLabels[$run->status] ?? $run->status }}</span>
                        @if($run->attempts > 1)<span class="ipl-muted">{{ $run->attempts }}ª tentativa</span>@endif
                    </td>
                    <td class="ipl-msg">{{ implode(' · ', $run->reasons ?? []) }}</td>
                    <td style="white-space:nowrap" title="na fila {{ $run->created_at }}{{ $run->finished_at ? ' · fim ' . $run->finished_at : '' }}">
                        {{ $run->created_at?->diffForHumans() }}
                        @if($run->finished_at && $run->started_at)<span class="ipl-muted">· {{ max(0, $run->started_at->diffInSeconds($run->finished_at, true)) }}s</span>@endif
                    </td>
                    <td class="ipl-msg">
                        @if($run->summary)
                            @php $first = strtok($run->summary, "\n"); @endphp
                            @if(str_contains($run->summary, "\n"))
                                <details><summary>{{ $first }}</summary><pre style="white-space:pre-wrap;font-size:11px;margin:4px 0 0">{{ $run->summary }}</pre></details>
                            @else
                                {{ $run->summary }}
                            @endif
                        @else
                            <span class="ipl-muted">—</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
