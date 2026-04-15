<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatawarehouseSchemaMigration extends Model
{
    protected $table = 'datawarehouse_schema_migrations';

    protected $fillable = [
        'stream_id',
        'user_id',
        'version',
        'operation',
        'column_name',
        'old_definition',
        'new_definition',
        'sql_executed',
        'status',
    ];

    protected $casts = [
        'old_definition' => 'array',
        'new_definition' => 'array',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseStream::class, 'stream_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }
}
