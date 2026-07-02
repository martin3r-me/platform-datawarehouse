<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\StreamImportService;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Ingests already-parsed rows into a stream via MCP — the tool-driven
 * counterpart to the webhook/file-upload paths. The caller (e.g. the LLM,
 * after parsing an .xlsx/.csv locally) pushes an array of row objects; the
 * rows run through the exact same mapping + write pipeline as every other
 * import (StreamImportService::importFromPayload → column transforms →
 * ImportWriter, honouring the stream's sync_strategy). No file upload needed.
 */
class IngestStreamTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    private const MAX_ROWS = 20000;

    public function getName(): string
    {
        return 'datawarehouse.streams.ingest';
    }

    public function getDescription(): string
    {
        return 'POST /datawarehouse/streams/{id}/ingest - Schreibt bereits geparste Zeilen in einen Stream (z. B. aus einem lokal geparsten Excel/CSV). ERFORDERLICH: stream_id, rows (Array von Objekten; Schlüssel = source_key der Stream-Spalten). Die Zeilen laufen durch dieselbe Mapping-/Transform-/Writer-Pipeline wie Webhook/Pull (inkl. sync_strategy: append/current/snapshot/scd2). Der Stream sollte aktiv sein; die dynamische Tabelle wird sonst automatisch erzeugt. Max ' . self::MAX_ROWS . ' Zeilen pro Aufruf (sonst in Batches senden).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team.'],
                'stream_id' => ['type' => 'integer', 'description' => 'ID des Streams (ERFORDERLICH). Nutze "datawarehouse.streams.GET" um IDs zu finden.'],
                'rows'      => [
                    'type' => 'array',
                    'description' => 'Zeilen als Objekte. Schlüssel müssen den source_key der aktiven Stream-Spalten entsprechen (dot-notation möglich).',
                    'items' => ['type' => 'object', 'additionalProperties' => true],
                ],
            ],
            'required' => ['stream_id', 'rows'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $found = $this->validateAndFindModel(
                $arguments, $context, 'stream_id', DatawarehouseStream::class,
                'NOT_FOUND', 'Stream nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int) $stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }

            $rows = $arguments['rows'] ?? null;
            if (!is_array($rows) || empty($rows)) {
                return ToolResult::error('VALIDATION_ERROR', 'rows muss ein nicht-leeres Array von Objekten sein.');
            }
            if (count($rows) > self::MAX_ROWS) {
                return ToolResult::error('VALIDATION_ERROR', 'Zu viele Zeilen (' . count($rows) . '). Max ' . self::MAX_ROWS . ' pro Aufruf — bitte in Batches senden.');
            }
            // Guard against a single scalar / assoc row being passed directly.
            $rows = array_values($rows);
            if (!is_array($rows[0] ?? null)) {
                return ToolResult::error('VALIDATION_ERROR', 'Jede Zeile muss ein Objekt sein (Schlüssel = source_key).');
            }

            $import = app(StreamImportService::class)->importFromPayload($stream, $rows, $context->user?->id);

            return ToolResult::success([
                'stream_id'     => $stream->id,
                'import_id'     => $import->id,
                'status'        => $import->status,
                'rows_received' => $import->rows_received,
                'rows_imported' => $import->rows_imported,
                'rows_skipped'  => $import->rows_skipped,
                'errors'        => $import->error_log,
                'team_id'       => $teamId,
                'message'       => 'Import ausgeführt. Prüfe mit "datawarehouse.stream.preview".',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Ingest: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => false,
            'category' => 'action',
            'tags' => ['datawarehouse', 'streams', 'ingest', 'import'],
            'risk_level' => 'write',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => false,
        ];
    }
}
