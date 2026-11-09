@inject('panelAlertResolver', 'Pterodactyl\Services\PanelAlerts\PanelAlertResolver')
@php($panelAlerts = $panelAlertResolver->forAdmin(Auth::user()))
@foreach ($panelAlerts as $panelAlert)
    <div class="alert alert-{{ $panelAlert->type }}" role="alert" data-panel-alert="{{ $panelAlert->id }}" data-revision="{{ $panelAlert->revision() }}">
        @if ($panelAlert->dismissible)
            <button type="button" class="close" aria-label="Fechar" data-panel-alert-close>&times;</button>
        @endif
        @if ($panelAlert->title)
            <strong>{{ $panelAlert->title }}</strong><br>
        @endif
        {!! $panelAlert->renderedMessage() !!}
    </div>
@endforeach
@if ($panelAlerts->isNotEmpty())
    <script>
        (function () {
            // Shared with the client area (see components/PanelAlerts.tsx).
            const key = 'panel_alerts_dismissed';
            function read() {
                try {
                    return JSON.parse(localStorage.getItem(key)) || {};
                } catch (e) {
                    return {};
                }
            }

            document.querySelectorAll('[data-panel-alert]').forEach(function (element) {
                const id = element.dataset.panelAlert;
                const button = element.querySelector('[data-panel-alert-close]');
                if (!button) {
                    return;
                }

                if (read()[id] === element.dataset.revision) {
                    element.remove();
                    return;
                }

                button.addEventListener('click', function () {
                    element.remove();
                    try {
                        const dismissed = read();
                        dismissed[id] = element.dataset.revision;
                        localStorage.setItem(key, JSON.stringify(dismissed));
                    } catch (e) {
                        // The alert just comes back on the next page load.
                    }
                });
            });
        })();
    </script>
@endif
