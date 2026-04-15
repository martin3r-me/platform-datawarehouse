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
        'frequency',
        'mode',
        'upsert_key',
        'endpoint_token',
        'pull_url',
        'pull_headers',
        'table_name',
        'table_created',
        'schema_version',
        'is_active',
        'last_run_at',
        'last_status',
        'metadata',
    ];

    protected $casts = [
        'pull_headers'  => 'array',
        'metadata'      => 'array',
        'table_created' => 'boolean',
        'is_active'     => 'boolean',
        'last_run_at'   => 'datetime',
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
