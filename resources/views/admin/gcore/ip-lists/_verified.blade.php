{{-- Expects $at (Carbon|null), $missing (int|null), $message (string|null). --}}
@if(!$at)
    <span class="ipl-muted">nunca</span>
@elseif($missing === null)
    <span class="ipl-st pending" title="{{ $message }}">⚠ não conferido</span>
    <span class="ipl-muted" title="{{ $at }}">{{ $at->diffForHumans() }}</span>
@elseif($missing > 0)
    <span class="ipl-st error" title="{{ $message }}">✗ faltam {{ $missing }}</span>
    <span class="ipl-muted" title="{{ $at }}">{{ $at->diffForHumans() }}</span>
@else
    <span class="ipl-st ok" title="{{ $message }}">✓ ok</span>
    <span class="ipl-muted" title="{{ $at }}">{{ $at->diffForHumans() }}</span>
@endif
