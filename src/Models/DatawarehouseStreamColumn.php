<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatawarehouseStreamColumn extends Model
{
    protected $table = 'datawarehouse_stream_columns';

    protected $fillable = [
        'stream_id',
        'source_key',
        'column_name',
        'label',
        'data_type',
        'precision',
        'scale',
        'unit',
        'is_indexed',
        'is_nullable',
        'default_value',
        'transform',
        'position',
        'is_active',
    ];

    protected $casts = [
        'is_indexed'  => 'boolean',
        'is_nullable' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseStream::class, 'stream_id');
    }

    /**
     * Map data_type to Laravel Schema Builder column type.
     */
    public function toLaravelColumnType(): array
    {
        return match ($this->data_type) {
            'string'   => ['type' => 'string', 'args' => [255]],
            'integer'  => ['type' => 'bigInteger', 'args' => []],
            'decimal'  => ['type' => 'decimal', 'args' => [$this->precision ?? 10, $this->scale ?? 2]],
            'boolean'  => ['type' => 'boolean', 'args' => []],
            'date'     => ['type' => 'date', 'args' => []],
            'datetime' => ['type' => 'dateTime', 'args' => []],
            'text'     => ['type' => 'text', 'args' => []],
            'json'     => ['type' => 'json', 'args' => []],
            default    => ['type' => 'string', 'args' => [255]],
        };
    }

    /**
     * Apply configured transformation to a value.
     */
    public function applyTransform(mixed $value): mixed
    {
        if (!$this->transform || $value === null) {
            return $value;
        }

        return match ($this->transform) {
            'trim'                 => is_string($value) ? trim($value) : $value,
            'url_decode'           => is_string($value) ? urldecode($value) : $value,
            'cast_german_decimal'  => $this->castGermanDecimal($value),
            'lowercase'            => is_string($value) ? mb_strtolower($value) : $value,
            'uppercase'            => is_string($value) ? mb_strtoupper($value) : $value,
            'strip_tags'           => is_string($value) ? strip_tags($value) : $value,
            'to_integer'           => (int) $value,
            'to_boolean'           => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default                => $value,
        };
    }

    protected function castGermanDecimal(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = str_replace('.', '', (string) $value); // remove thousands separator
        $value = str_replace(',', '.', $value);          // swap decimal comma

        return (float) $value;
    }
}
