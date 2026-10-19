<?php

namespace Platform\Datawarehouse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Services\PayloadNormalizer;
use Platform\Datawarehouse\Services\StreamImportService;

class IngestController extends Controller
{
    /** Rows kept from an onboarding payload - same cap the upload path uses. */
    private const SAMPLE_ROWS = 200;

    /** Hard ceiling for the stored sample, encoded. */
    private const SAMPLE_MAX_BYTES = 262144;

    /**
     * POST /api/datawarehouse/ingest/{token}
     *
     * Webhook endpoint for receiving data. Token-based auth (no session required).
     */
    public function __invoke(Request $request, string $token, StreamImportService $importService): JsonResponse
    {
        $stream = DatawarehouseStream::where('endpoint_token', $token)
            ->whereIn('status', ['onboarding', 'active'])
            ->first();

        if (!$stream) {
            return response()->json(['error' => 'Invalid or inactive token.'], 404);
        }

        $rawData = $request->getContent();

        // Parse body: JSON → URL-decoded JSON → form fields
        $payload = PayloadNormalizer::parse($rawData);

        if ($payload === null) {
            $input = $request->all();
            if (!empty($input)) {
                $payload = $input;
                $rawData = json_encode($input);
            } else {
                return response()->json([
                    'error'                 => 'Invalid JSON payload.',
                    'hint'                  => 'Send data as JSON body with Content-Type: application/json, URL-encoded JSON, or form fields.',
                    'received_content_type' => $request->header('Content-Type'),
                ], 422);
            }
        }

        // Normalize known payload shapes (4D LIST → flat rows, etc.)
        $payload = PayloadNormalizer::normalize($payload);

        // Onboarding: store sample payload, don't import into dynamic table
        if ($stream->isOnboarding()) {
            return $this->handleOnboarding($stream, $payload, $rawData);
        }

        // Active: normal import
        $import = $importService->importFromPayload($stream, $payload);

        return response()->json([
            'import_id'     => $import->id,
            'status'        => $import->status,
            'rows_received' => $import->rows_received,
            'rows_imported' => $import->rows_imported,
            'rows_skipped'  => $import->rows_skipped,
            'duration_ms'   => $import->duration_ms,
            'errors'        => $import->error_log,
        ], $import->status === 'error' ? 422 : 200);
    }

    /**
     * Keep the stored onboarding sample small.
     *
     * The sample only feeds the column mapping in StreamOnboarding - a
     * couple of rows are enough, the full payload never was. Stored
     * uncapped it lands in datawarehouse_streams.metadata and makes the row
     * so wide that merely listing the team's streams ("order by name" over
     * select *) exhausts MySQL's sort buffer and 500s the whole module.
     *
     * The untouched payload stays available in datawarehouse_imports.raw_payload,
     * so nothing is lost - it just no longer sits in a column that every
     * stream listing drags along.
     */
    protected function trimSample(array $payload): array
    {
        $sample = array_is_list($payload)
            ? array_slice($payload, 0, self::SAMPLE_ROWS)
            : $payload;

        if (strlen((string) json_encode($sample)) <= self::SAMPLE_MAX_BYTES) {
            return $sample;
        }

        // Few but very wide rows: keep the first one.
        $first = array_is_list($sample) ? array_slice($sample, 0, 1) : $sample;

        if (strlen((string) json_encode($first)) <= self::SAMPLE_MAX_BYTES) {
            return $first;
        }

        return ['_truncated' => 'Sample zu gross zum Speichern - siehe raw_payload des Imports.'];
    }

    protected function handleOnboarding(DatawarehouseStream $stream, array $payload, string $rawData): JsonResponse
    {
        // Store sample payload in metadata - trimmed, see trimSample().
        $metadata = $stream->metadata ?? [];
        $metadata['sample_payload'] = $this->trimSample($payload);
        $stream->update(['metadata' => $metadata]);

        // Create import record with raw_payload for later processing
        DatawarehouseImport::create([
            'stream_id'     => $stream->id,
            'status'        => 'pending',
            'mode'          => $stream->mode,
            'rows_received' => is_array($payload) ? (isset($payload[0]) ? count($payload) : 1) : 0,
            'raw_payload'   => $rawData,
        ]);

        return response()->json([
            'status'  => 'onboarding',
            'message' => 'Sample received. Configure your stream to start importing.',
        ]);
    }
}
