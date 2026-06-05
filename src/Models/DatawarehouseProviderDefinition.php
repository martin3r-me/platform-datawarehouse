<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A user/LLM-configured pull provider stored in the database.
 *
 * Unlike code providers (LexofficeProvider, …) these are pure configuration:
 * the ProviderRegistry wraps a definition in a GenericHttpProvider at runtime.
 * Definitions are team-scoped; the `key` is globally unique so the registry can
 * resolve a provider by key alone (without team context) inside console pull jobs.
 */
class DatawarehouseProviderDefinition extends Model
{
    use SoftDeletes;

    protected $table = 'datawarehouse_provider_definitions';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'key',
        'label',
        'description',
        'icon',
        'is_active',
        'base_url',
        'auth_type',
        'auth_config',
        'endpoints',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'auth_config' => 'array',
        'endpoints'   => 'array',
    ];

    public const AUTH_TYPES = ['none', 'bearer', 'header', 'query'];

    protected static function booted(): void
    {
        static::creating(function (self $def) {
            if (empty($def->uuid)) {
                $def->uuid = (string) Str::uuid();
            }
            if (empty($def->key)) {
                $def->key = self::generateKey();
            }
        });
    }

    /**
     * Generate a globally-unique provider key, e.g. "cfg_a1b2c3d4".
     */
    public static function generateKey(): string
    {
        do {
            $key = 'cfg_' . Str::lower(Str::random(8));
        } while (self::withTrashed()->where('key', $key)->exists());

        return $key;
    }

    // --- Relationships ---

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
