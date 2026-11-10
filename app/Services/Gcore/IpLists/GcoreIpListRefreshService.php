<?php

namespace Pterodactyl\Services\Gcore\IpLists;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\GcoreIpList;
use Pterodactyl\Models\GcoreIpListLog;

/**
 * Downloads an IP list, validates it and, if it changed, pushes it to the bound Gcore rules.
 *
 * Safety: a failed download, an empty list, a list over max_entries or one that shrank by
 * more than max_shrink_percent is rejected and the previous version stays in use (an empty
 * sip_list on Gcore means "any source"). A forced refresh skips only the shrink check.
 */
class GcoreIpListRefreshService
{
    public function __construct(
        private readonly GcoreIpListBindingService $bindings,
        private readonly ?IpListParser $parser = null,
    ) {
    }

    /** @return list<GcoreIpListLog> */
    public function refreshDue(): array
    {
        $logs = [];
        foreach (GcoreIpList::query()->where('enabled', true)->orderBy('id')->get() as $list) {
            if ($list->isDue()) {
                $logs[] = $this->refresh($list, 'schedule');
            }
        }

        return $logs;
    }

    /**
     * @param 'schedule'|'manual'|'force' $trigger
     */
    public function refresh(GcoreIpList $list, string $trigger = 'manual'): GcoreIpListLog
    {
        $previous = $list->entryList();
        $log = [
            'gcore_ip_list_id' => $list->id,
            'trigger' => $trigger,
            'previous_count' => count($previous),
            'entry_count' => count($previous),
        ];

        try {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->withHeaders(['User-Agent' => 'Pterodactyl-Panel IP list updater', 'Accept' => 'text/plain, application/json, */*'])
                ->retry(2, 1500, null, false)
                ->get($list->url);
            $log['http_status'] = $response->status();

            if (!$response->successful()) {
                return $this->finish($list, $log + ['status' => 'error', 'message' => 'Download falhou: HTTP ' . $response->status() . '. Versão anterior mantida.']);
            }

            $body = $response->body();
            if (strlen($body) > (int) config('gcore.ip_lists.max_bytes', 5 * 1024 * 1024)) {
                return $this->finish($list, $log + ['status' => 'error', 'message' => 'Arquivo grande demais (' . strlen($body) . ' bytes). Versão anterior mantida.']);
            }
        } catch (\Throwable $e) {
            return $this->finish($list, $log + ['status' => 'error', 'message' => 'Download falhou: ' . $e->getMessage() . '. Versão anterior mantida.']);
        }

        $parsed = ($this->parser ?? IpListParser::fromConfig())->parse($body);
        $entries = $parsed['cidrs'];
        $log['ignored_ipv6'] = $parsed['ipv6'];
        $log['ignored_invalid'] = $parsed['invalid'] + $parsed['too_broad'];
        $notes = $this->ignoredNote($parsed);

        $added = array_values(array_diff($entries, $previous));
        $removed = array_values(array_diff($previous, $entries));
        $log['added'] = $added;
        $log['removed'] = $removed;

        $reject = $this->rejectReason($entries, $previous, $trigger === 'force');
        if ($reject !== null) {
            return $this->finish($list, $log + [
                'status' => 'rejected',
                'message' => $reject . ' Versão anterior mantida (' . count($previous) . ' entradas).' . $notes,
            ]);
        }

        $log['entry_count'] = count($entries);

        if ($added === [] && $removed === []) {
            return $this->finish($list, $log + ['status' => 'unchanged', 'message' => 'Sem alterações.' . $notes]);
        }

        $list->forceFill([
            'entries' => $entries,
            'entry_count' => count($entries),
            'entries_hash' => hash('sha256', implode("\n", $entries)),
            'last_changed_at' => now(),
        ])->save();

        $profiles = $this->bindings->applyPending([$list->id]);
        $log['profiles'] = $profiles;

        $failed = array_filter($profiles, fn ($p) => $p['status'] === 'error');
        $message = sprintf('+%d / -%d entradas.', count($added), count($removed));
        $message .= $profiles === []
            ? ' Nenhuma regra usa esta lista.'
            : sprintf(' %d perfil(is) Gcore processado(s)%s.', count($profiles), $failed ? ', ' . count($failed) . ' com erro (nova tentativa a cada execução)' : '');

        return $this->finish($list, $log + ['status' => 'changed', 'message' => $message . $notes]);
    }

    /**
     * @param list<string> $entries
     * @param list<string> $previous
     */
    private function rejectReason(array $entries, array $previous, bool $force): ?string
    {
        if ($entries === []) {
            return 'Lista veio vazia (nenhum IPv4 válido).';
        }

        $max = (int) config('gcore.ip_lists.max_entries', 5000);
        if ($max > 0 && count($entries) > $max) {
            return sprintf('Lista tem %d entradas, acima do limite de %d.', count($entries), $max);
        }

        $shrink = (int) config('gcore.ip_lists.max_shrink_percent', 50);
        if (!$force && $previous !== [] && $shrink > 0) {
            $lost = (count($previous) - count($entries)) / count($previous) * 100;
            if ($lost > $shrink) {
                return sprintf(
                    'Lista encolheu %d%% (%d → %d entradas), acima do limite de %d%%. Use "Forçar atualização" se for esperado.',
                    (int) round($lost),
                    count($previous),
                    count($entries),
                    $shrink,
                );
            }
        }

        return null;
    }

    /** @param array{ipv6:int, invalid:int, too_broad:int} $parsed */
    private function ignoredNote(array $parsed): string
    {
        $parts = [];
        if ($parsed['ipv6'] > 0) {
            $parts[] = $parsed['ipv6'] . ' IPv6';
        }
        if ($parsed['invalid'] > 0) {
            $parts[] = $parsed['invalid'] . ' inválida(s)';
        }
        if ($parsed['too_broad'] > 0) {
            $parts[] = $parsed['too_broad'] . ' ampla(s) demais (< /' . (int) config('gcore.ip_lists.min_prefix', 8) . ')';
        }

        return $parts === [] ? '' : ' Ignoradas: ' . implode(', ', $parts) . '.';
    }

    /** @param array<string, mixed> $log */
    private function finish(GcoreIpList $list, array $log): GcoreIpListLog
    {
        $list->forceFill([
            'last_checked_at' => now(),
            'last_status' => $log['status'],
            'last_error' => in_array($log['status'], ['error', 'rejected'], true) ? $log['message'] : null,
        ])->save();

        return GcoreIpListLog::query()->create($log);
    }

    public function purgeLogs(): int
    {
        $days = (int) config('gcore.ip_lists.log_retention_days', 180);

        return GcoreIpListLog::query()->where('created_at', '<', now()->subDays($days))->delete();
    }
}
