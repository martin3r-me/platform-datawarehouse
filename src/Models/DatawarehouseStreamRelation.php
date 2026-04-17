<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatawarehouseStreamRelation extends Model
{
    protected $table = 'datawarehouse_stream_relations';

    protected $fillable = [
        'team_id',
        'source_stream_id',
        'source_column',
        'target_stream_id',
        'target_column',
        'label',
        'relation_type',
    ];

    public function sourceStream(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseStream::class, 'source_stream_id');
    }

    public function targetStream(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseStream::class, 'target_stream_id');
    }
}
