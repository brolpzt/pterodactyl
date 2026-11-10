{{-- Sync runs list that refreshes itself while something is queued/running. --}}
<style>
    .gcore-runs .ipl-st { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 10px; border: 1px solid var(--border); white-space: nowrap; }
    .gcore-runs .ipl-st.ok { border-color: rgba(16,185,129,.55); color: #6ee7b7; }
    .gcore-runs .ipl-st.pending, .gcore-runs .ipl-st.rejected { border-color: rgba(245,158,11,.7); color: #fcd34d; }
    .gcore-runs .ipl-st.error { border-color: rgba(239,68,68,.7); color: #fca5a5; }
    .gcore-runs .ipl-muted { color: var(--muted); }
    .gcore-runs .ipl-mono { font-family: var(--mono); font-size: 11px; }
    .gcore-runs .ipl-msg { max-width: 520px; }
    .gcore-runs td { vertical-align: top; }
    .gcore-runs details summary { cursor: pointer; }
</style>
<div class="gcore-runs" data-url="{{ route('admin.gcore.live.runs', $filter ?? []) }}" data-pending="{{ $runs->contains(fn ($r) => $r->isPending()) ? 1 : 0 }}">
    @include('admin.gcore._runs', ['runs' => $runs])
</div>
<script>
(function () {
    const box = document.currentScript.previousElementSibling;
    if (!box || box.dataset.bound) return;
    box.dataset.bound = '1';
    function poll() {
        if (box.dataset.pending !== '1') return;
        setTimeout(() => {
            fetch(box.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(j => {
                    box.innerHTML = j.html;
                    const was = box.dataset.pending;
                    box.dataset.pending = j.pending ? '1' : '0';
                    if (was === '1' && !j.pending) box.dispatchEvent(new CustomEvent('gcore-runs-done', { bubbles: true }));
                    poll();
                })
                .catch(() => setTimeout(poll, 10000));
        }, 3000);
    }
    poll();
})();
</script>
