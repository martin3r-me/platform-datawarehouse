<?php

namespace Platform\Datawarehouse\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Platform\Datawarehouse\Models\DatawarehouseStream;
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
            ->where('is_active', true)
            ->first();

        if (!$stream) {
            return response()->json(['error' => 'Invalid or inactive token.'], 404);
        }

        $rawData = $request->getContent();
        $payload = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Invalid JSON payload.'], 422);
        }

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
}
