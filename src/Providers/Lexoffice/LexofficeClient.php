<?php

namespace Platform\Datawarehouse\Providers\Lexoffice;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around Laravel Http for the Lexoffice public API.
 *
 * - Base URL: https://api.lexoffice.io/v1
 * - Auth: Bearer <api-key>
 * - Rate limit: ~2 requests/second → we politely sleep 500ms between requests.
 */
class LexofficeClient
{
    public const BASE_URL = 'https://api.lexoffice.io/v1';

    public function __construct(
        protected string $apiKey,
        protected int $timeout = 30,
        protected int $throttleMs = 500,
    ) {}

    /**
     * Perform a GET against a Lexoffice path.
     *
     * @param  string  $path   e.g. "/contacts" or "/voucherlist"
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->request()->get(self::BASE_URL . $path, $query);

        // Respect rate limit between calls.
        if ($this->throttleMs > 0) {
            usleep($this->throttleMs * 1000);
        }

        $this->assertOk($response, $path);

        return $response->json() ?? [];
    }

    /**
     * Lightweight credentials check — GET /profile returns the company profile.
     */
    public function testProfile(): array
    {
        return $this->get('/profile');
    }

    protected function request(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->withToken($this->apiKey)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ]);
    }

    protected function assertOk(Response $response, string $path): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $body   = $response->body();

        $message = match (true) {
            $status === 401 => 'Lexoffice: API-Key ungültig (401).',
            $status === 403 => 'Lexoffice: Zugriff verweigert (403).',
            $status === 404 => "Lexoffice: Endpunkt {$path} nicht gefunden (404).",
            $status === 429 => 'Lexoffice: Rate-Limit erreicht (429).',
            $status >= 500  => "Lexoffice: Serverfehler ({$status}).",
            default         => "Lexoffice: HTTP {$status} bei {$path}.",
        };

        throw new RuntimeException($message . ' ' . substr($body, 0, 300));
    }
}
