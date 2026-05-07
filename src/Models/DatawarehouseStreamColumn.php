<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Platform\Datawarehouse\Services\StreamSchemaService;

class DatawarehouseStreamColumn extends Model
{
    /** Pattern matching German-decimal strings (1.234,56 or 28524,8). */
    protected const GERMAN_DECIMAL_PATTERN = '/^-?(?:\d{1,3}(?:\.\d{3})+|\d+),\d+$/';

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
        if ($value === null) {
            return $value;
        }

        // Auto-promote integer columns to decimal when a German-decimal value
        // arrives. Sample-based detection often misses decimals when the
        // sample only contained zero-valued numeric cells; this self-heals
        // the schema on first sight rather than failing the import.
        if (
            !$this->transform
            && $this->data_type === 'integer'
            && is_string($value)
            && preg_match(self::GERMAN_DECIMAL_PATTERN, trim($value))
        ) {
            $this->autoPromoteIntegerToDecimal();
            // Fall through — column is now decimal, the safety-net below
            // (or the configured transform) handles the cast.
        }

        // Safety net: auto-fix German decimal values for decimal columns,
        // even when no explicit transform is configured.
        if (!$this->transform && $this->data_type === 'decimal') {
            if (is_string($value) && preg_match(self::GERMAN_DECIMAL_PATTERN, trim($value))) {
                return $this->castGermanDecimal($value);
            }

            return $value;
        }

        if (!$this->transform) {
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

    /**
     * Migrate this column from integer to decimal in-place: persist the new
     * type/transform, ALTER the dynamic table, and update the in-memory
     * instance so the rest of the import sees the new state.
     *
     * Idempotent: if a concurrent process already promoted the column,
     * we re-sync from the DB and skip the ALTER.
     */
    protected function autoPromoteIntegerToDecimal(): void
    {
        $fresh = self::find($this->id);
        if ($fresh && $fresh->data_type !== 'integer') {
            // Already promoted by another row/process — adopt the fresh state.
            $this->forceFill($fresh->getAttributes());
            $this->syncOriginal();
            return;
        }

        $rollback = [
            'data_type' => 'integer',
            'transform' => $this->transform,
            'precision' => $this->precision,
            'scale'     => $this->scale,
        ];

        $this->data_type = 'decimal';
        $this->transform = 'cast_german_decimal';
        $this->precision = $this->precision ?? 10;
        $this->scale     = $this->scale ?? 2;
        $this->save();

        try {
            app(StreamSchemaService::class)->modifyColumn(
                $this->stream,
                $this,
                ['data_type' => 'integer', 'transform' => $rollback['transform']]
            );
        } catch (\Throwable $e) {
            // Roll back the model state so a retried import doesn't drift
            // from the actual table schema.
            $this->forceFill($rollback);
            $this->save();

            Log::error("Auto-promote integer→decimal failed for column {$this->column_name} (stream {$this->stream_id}): {$e->getMessage()}");
            throw $e;
        }
    }
}
