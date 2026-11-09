<?php

namespace Pterodactyl\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;

/**
 * A banner configured by an administrator and shown across the panel.
 *
 * @property int $id
 * @property string|null $title
 * @property string $message
 * @property string $type
 * @property bool $enabled
 * @property bool $dismissible
 * @property string $audience
 * @property array $placements
 * @property array|null $server_pages
 * @property array|null $nest_ids
 * @property array|null $egg_ids
 * @property array|null $node_ids
 * @property array|null $location_ids
 * @property array|null $server_ids
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PanelAlert extends Model
{
    public const RESOURCE_NAME = 'panel_alert';

    public const TYPES = [
        'info' => 'Info',
        'success' => 'Sucesso',
        'warning' => 'Aviso',
        'danger' => 'Perigo',
    ];

    public const AUDIENCES = [
        'all' => 'Todos os usuários',
        'admins' => 'Somente administradores',
    ];

    public const PLACEMENT_DASHBOARD = 'dashboard';
    public const PLACEMENT_ACCOUNT = 'account';
    public const PLACEMENT_SERVER = 'server';
    public const PLACEMENT_ADMIN = 'admin';

    public const PLACEMENTS = [
        self::PLACEMENT_DASHBOARD => 'Lista de servidores (início)',
        self::PLACEMENT_ACCOUNT => 'Minha conta',
        self::PLACEMENT_SERVER => 'Páginas do servidor',
        self::PLACEMENT_ADMIN => 'Painel admin',
    ];

    /** Client server routes an alert can be limited to; keys are matched against the URL. */
    public const SERVER_PAGES = [
        '/' => 'Início',
        '/console' => 'Console',
        '/stats' => 'Stats',
        '/files' => 'Arquivos',
        '/anti-ddos' => 'Anti-DDoS',
        '/databases' => 'Databases',
        '/schedules' => 'Schedules',
        '/users' => 'Usuários',
        '/backups' => 'Backups',
        '/network' => 'Network',
        '/addons' => 'Addons',
        '/workshop' => 'Workshop',
        '/firewall' => 'Firewall',
        '/dns' => 'DNS',
        '/activity' => 'Atividade',
        '/settings' => 'Configurações',
        '/ts3' => 'TeamSpeak',
        '/amxx' => 'AMXX',
        '/webrcon' => 'WebRCON',
    ];

    protected $table = 'panel_alerts';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'enabled' => 'boolean',
        'dismissible' => 'boolean',
        'placements' => 'array',
        'server_pages' => 'array',
        'nest_ids' => 'array',
        'egg_ids' => 'array',
        'node_ids' => 'array',
        'location_ids' => 'array',
        'server_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $attributes = [
        'type' => 'info',
        'enabled' => true,
        'dismissible' => true,
        'audience' => 'all',
    ];

    public static array $validationRules = [
        'title' => 'nullable|string|max:191',
        'message' => 'required|string',
        'type' => 'required|string|in:info,success,warning,danger',
        'enabled' => 'boolean',
        'dismissible' => 'boolean',
        'audience' => 'required|string|in:all,admins',
        'placements' => 'required|array|min:1',
        'server_pages' => 'nullable|array',
        'nest_ids' => 'nullable|array',
        'egg_ids' => 'nullable|array',
        'node_ids' => 'nullable|array',
        'location_ids' => 'nullable|array',
        'server_ids' => 'nullable|array',
        'starts_at' => 'nullable|date',
        'ends_at' => 'nullable|date',
    ];

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * The state shown to administrators, taking the schedule into account.
     */
    public function status(): string
    {
        return match (true) {
            !$this->enabled => 'Inativo',
            $this->ends_at !== null && $this->ends_at->isPast() => 'Encerrado',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Agendado',
            default => 'Ativo',
        };
    }

    public function isPlacedOn(string ...$placements): bool
    {
        return count(array_intersect($placements, $this->placements ?? [])) > 0;
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->audience !== 'admins' || $user->root_admin;
    }

    /**
     * Whether this alert is restricted to a subset of the servers on the panel.
     */
    public function isTargeted(): bool
    {
        return !empty($this->nest_ids)
            || !empty($this->egg_ids)
            || !empty($this->node_ids)
            || !empty($this->location_ids)
            || !empty($this->server_ids);
    }

    /**
     * Nests and eggs are alternatives ("all of nest A plus egg X from nest B"), every
     * other filter narrows the result further.
     */
    public function matchesServer(Server $server): bool
    {
        if (!empty($this->nest_ids) || !empty($this->egg_ids)) {
            $inNest = in_array($server->nest_id, $this->nest_ids ?? [], true);
            $inEgg = in_array($server->egg_id, $this->egg_ids ?? [], true);
            if (!$inNest && !$inEgg) {
                return false;
            }
        }

        if (!empty($this->node_ids) && !in_array($server->node_id, $this->node_ids, true)) {
            return false;
        }

        if (!empty($this->location_ids) && !in_array($server->node->location_id, $this->location_ids, true)) {
            return false;
        }

        return empty($this->server_ids) || in_array($server->id, $this->server_ids, true);
    }

    /**
     * Inline Markdown as HTML. Raw HTML is escaped and unsafe link schemes are dropped,
     * which is what makes this output safe to print unescaped.
     */
    public function renderedMessage(): string
    {
        $message = trim(str_replace(["\r\n", "\r"], "\n", $this->message));

        // Markdown drops blank lines between paragraphs, so they are kept out of the
        // conversion and printed as line breaks.
        $parts = preg_split('/(\n(?:[ \t]*\n)+)/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        $html = '';
        foreach ($parts as $index => $part) {
            $html .= $index % 2 === 0
                ? trim($this->inlineMarkdown($part))
                : str_repeat("<br>\n", substr_count($part, "\n"));
        }

        return $html;
    }

    private function inlineMarkdown(string $text): string
    {
        return Str::inlineMarkdown($text, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
            'external_link' => ['open_in_new_window' => true],
        ], [new ExternalLinkExtension()]);
    }

    /**
     * Changes whenever the alert is edited, so that a dismissed alert comes back.
     */
    public function revision(): string
    {
        return (string) $this->updated_at->getTimestamp();
    }
}
