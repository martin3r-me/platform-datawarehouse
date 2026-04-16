<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DatawarehouseConnection extends Model
{
    use SoftDeletes;

    protected $table = 'datawarehouse_connections';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'provider_key',
        'name',
        'description',
        'credentials',
        'meta',
        'is_active',
        'last_check_at',
        'last_check_status',
        'last_check_error',
    ];

    protected $casts = [
        // Credentials stay encrypted at rest via Laravel's encrypted:array cast.
        'credentials'   => 'encrypted:array',
        'meta'          => 'array',
        'is_active'     => 'boolean',
        'last_check_at' => 'datetime',
    ];

    /**
     * Never expose credentials when the model is serialized (API, logs).
     */
    protected $hidden = [
        'credentials',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conn) {
            if (empty($conn->uuid)) {
                $conn->uuid = (string) Str::uuid();
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

    public function streams(): HasMany
    {
        return $this->hasMany(DatawarehouseStream::class, 'connection_id');
    }

    // --- Helpers ---

    public function credential(string $key, mixed $default = null): mixed
    {
        $creds = $this->credentials ?? [];
        return $creds[$key] ?? $default;
    }

    public function markCheckSuccess(): void
    {
        $this->update([
            'last_check_at'     => now(),
            'last_check_status' => 'success',
            'last_check_error'  => null,
        ]);
    }

    public function markCheckError(string $message): void
    {
        $this->update([
            'last_check_at'     => now(),
            'last_check_status' => 'error',
            'last_check_error'  => $message,
        ]);
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

    public function scopeProvider($query, string $providerKey)
    {
        return $query->where('provider_key', $providerKey);
    }
}
