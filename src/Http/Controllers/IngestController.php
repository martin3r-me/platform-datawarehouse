<?php

namespace Platform\Datawarehouse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Services\StreamImportService;

class IngestController extends Controller
{
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
        $payload = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON payload.'], 422);
        }

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

    protected function handleOnboarding(DatawarehouseStream $stream, array $payload, string $rawData): JsonResponse
    {
        // Store sample payload in metadata
        $metadata = $stream->metadata ?? [];
        $metadata['sample_payload'] = $payload;
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
