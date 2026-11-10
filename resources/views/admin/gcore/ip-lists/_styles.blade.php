<link rel="stylesheet" href="/themes/pterodactyl/css/gcore-ddos.css">
<style>
    .ipl-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 12px; align-items: start; }
    @media (max-width: 1100px) { .ipl-grid { grid-template-columns: minmax(0, 1fr); } }
    .ipl-st { display: inline-block; padding: 1px 7px; border-radius: 9px; font-size: 10px; border: 1px solid var(--border); white-space: nowrap; }
    .ipl-st.changed, .ipl-st.ok { border-color: rgba(16,185,129,.55); color: #6ee7b7; }
    .ipl-st.unchanged { color: var(--muted); }
    .ipl-st.rejected, .ipl-st.pending, .ipl-st.orphan { border-color: rgba(245,158,11,.7); color: #fcd34d; }
    .ipl-st.error { border-color: rgba(239,68,68,.7); color: #fca5a5; }
    .ipl-muted { color: var(--muted); }
    .ipl-mono { font-family: var(--mono); font-size: 11px; }
    .ipl-diff { font-family: var(--mono); font-size: 10px; line-height: 1.5; max-width: 420px; }
    .ipl-diff .add { color: #6ee7b7; }
    .ipl-diff .del { color: #fca5a5; }
    .ipl-diff details summary { cursor: pointer; color: var(--muted); }
    .ipl-entries { columns: 4 150px; font-family: var(--mono); font-size: 11px; padding: 8px 10px; max-height: 360px; overflow: auto; }
    .ipl-msg { max-width: 460px; }
    .ipl-form .field { margin-bottom: 8px; }
    .ipl-form .check { display: flex; gap: 6px; align-items: center; font-size: 12px; }
    table.data td { vertical-align: top; }
</style>
