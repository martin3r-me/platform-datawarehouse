<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DatawarehouseStream extends Model
{
    use SoftDeletes;

    protected $table = 'datawarehouse_streams';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'slug',
        'description',
        'source_type',
        'connection_id',
        'endpoint_key',
        'pull_config',
        'pull_schedule',
        'pull_mode',
        'incremental_field',
        'last_cursor',
        'last_pull_at',
        'frequency',
        'mode',
        'sync_strategy',
        'natural_key',
        'change_detection',
        'soft_delete',
        'upsert_key',
        'endpoint_token',
        'pull_url',
        'pull_headers',
        'table_name',
        'table_created',
        'schema_version',
        'status',
        'is_system',
        'last_run_at',
        'last_status',
        'metadata',
    ];

    protected $casts = [
        'pull_headers'     => 'array',
        'pull_config'      => 'array',
        'last_cursor'      => 'array',
        'metadata'         => 'array',
        'table_created'    => 'boolean',
        'change_detection' => 'boolean',
        'soft_delete'      => 'boolean',
        'is_system'        => 'boolean',
        'last_run_at'      => 'datetime',
        'last_pull_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $stream) {
            if (empty($stream->uuid)) {
                $stream->uuid = (string) Str::uuid();
            }
            if (empty($stream->endpoint_token)) {
                $stream->endpoint_token = Str::random(64);
            }
            if (empty($stream->slug)) {
                $stream->slug = Str::slug($stream->name);
            }
            if (empty($stream->status)) {
                $stream->status = 'onboarding';
            }
        });
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

    public function columns(): HasMany
    {
        return $this->hasMany(DatawarehouseStreamColumn::class, 'stream_id')->orderBy('position');
    }

    public function imports(): HasMany
    {
        return $this->hasMany(DatawarehouseImport::class, 'stream_id');
    }

    public function schemaMigrations(): HasMany
    {
        return $this->hasMany(DatawarehouseSchemaMigration::class, 'stream_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseConnection::class, 'connection_id');
    }

    /**
     * Relations where this stream is the source (has FK columns).
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(DatawarehouseStreamRelation::class, 'source_stream_id');
    }

    /**
     * Relations where this stream is the lookup target.
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(DatawarehouseStreamRelation::class, 'target_stream_id');
    }

    // --- Status helpers ---

    public function isOnboarding(): bool
    {
        return $this->status === 'onboarding';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Backwards-compatible accessor: returns true when status is 'active'.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getSamplePayloadAttribute(): ?array
    {
        return $this->metadata['sample_payload'] ?? null;
    }

    // --- Methods ---

    public function getDynamicTableName(): string
    {
        return 'dw_' . $this->id . '_' . $this->slug;
    }

    public function isWebhook(): bool
    {
        return $this->source_type === 'webhook_post';
    }

    public function isPull(): bool
    {
        return $this->source_type === 'pull_get';
    }

    public function isManual(): bool
    {
        return $this->source_type === 'manual';
    }

    // --- Sync-Strategy helpers ---

    public function isAppendStrategy(): bool
    {
        return $this->sync_strategy === 'append';
    }

    public function isCurrentStrategy(): bool
    {
        return $this->sync_strategy === 'current';
    }

    public function isSnapshotStrategy(): bool
    {
        return $this->sync_strategy === 'snapshot';
    }

    public function isScd2Strategy(): bool
    {
        return $this->sync_strategy === 'scd2';
    }

    /**
     * True when the strategy requires a natural_key (current or scd2).
     */
    public function strategyRequiresKey(): bool
    {
        return in_array($this->sync_strategy, ['current', 'scd2'], true);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOnboarding($query)
    {
        return $query->where('status', 'onboarding');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeUserCreated($query)
    {
        return $query->where('is_system', false);
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
