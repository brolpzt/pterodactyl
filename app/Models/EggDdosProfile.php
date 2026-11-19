<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Gcore policy a client may pick for the game port, shown under its own name.
 *
 * @property int $id
 * @property int $egg_id
 * @property string $policy Gcore ACL policy (never shown to the client)
 * @property string $label name the client sees
 * @property string $alert_type info | success | warning | danger
 * @property string|null $alert_title
 * @property string|null $alert_message inline Markdown, rendered like panel alerts
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class EggDdosProfile extends Model
{
    protected $table = 'egg_ddos_profiles';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'egg_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public static array $validationRules = [
        'egg_id' => 'required|integer|exists:eggs,id',
        'policy' => 'required|string|max:64',
        'label' => 'required|string|max:64',
        'alert_type' => 'required|string|in:info,success,warning,danger',
        'alert_title' => 'nullable|string|max:191',
        'alert_message' => 'nullable|string|max:2000',
        'sort_order' => 'integer',
    ];

    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * The notice shown to the client, with the message rendered like a panel alert.
     *
     * @return array{type: string, title: string|null, html: string}|null
     */
    public function alertPayload(): ?array
    {
        $title = trim((string) $this->alert_title);
        $message = trim((string) $this->alert_message);
        if ($title === '' && $message === '') {
            return null;
        }

        $html = $message === '' ? '' : (new PanelAlert())->forceFill(['message' => $message])->renderedMessage();

        return [
            'type' => array_key_exists($this->alert_type, PanelAlert::TYPES) ? $this->alert_type : 'info',
            'title' => $title === '' ? null : $title,
            'html' => $html,
        ];
    }
}
