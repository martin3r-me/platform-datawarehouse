<?php

namespace Platform\Datawarehouse\Providers\Lexoffice;

use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\AuthField;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Lexoffice pull provider.
 *
 * Exposes two endpoints for Phase 3:
 *  - contacts  → /contacts  (paginated, no server-side incremental filter)
 *  - invoices  → /voucherlist?voucherType=invoice (paginated, supports updatedDateFrom)
 */
class LexofficeProvider implements PullProvider
{
    public const PAGE_SIZE = 100;

    public function key(): string
    {
        return 'lexoffice';
    }

    public function label(): string
    {
        return 'Lexoffice';
    }

    public function description(): ?string
    {
        return 'Buchhaltung, Rechnungen & Kontakte aus Lexoffice.';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    public function authFields(): array
    {
        return [
            new AuthField(
                key: 'api_key',
                label: 'API-Key',
                type: AuthField::TYPE_PASSWORD,
                required: true,
                description: 'Zu finden in Lexoffice unter Einstellungen → Öffentliche API.',
                placeholder: 'z.B. abc123-def456-...',
            ),
        ];
    }

    public function endpoints(): array
    {
        return [
            'contacts' => new Endpoint(
                key: 'contacts',
                label: 'Kontakte',
                description: 'Kunden und Lieferanten (Stammdaten).',
                paginated: true,
                incrementalField: null,
                defaultStrategy: 'current',
                naturalKey: 'id',
                supportedStrategies: ['append', 'current', 'snapshot', 'scd2'],
                meta: ['path' => '/contacts'],
            ),
            'invoices' => new Endpoint(
                key: 'invoices',
                label: 'Rechnungen',
                description: 'Rechnungen aus der Voucher-Liste (Status "any").',
                paginated: true,
                incrementalField: 'updatedDate',
                defaultStrategy: 'current',
                naturalKey: 'id',
                supportedStrategies: ['append', 'current', 'snapshot', 'scd2'],
                meta: ['path' => '/voucherlist', 'voucherType' => 'invoice'],
            ),
        ];
    }

    public function testConnection(DatawarehouseConnection $connection): bool
    {
        $client = $this->client($connection);
        $client->testProfile();  // throws on failure
        return true;
    }

    public function fetch(PullContext $context): PullResult
    {
        $client = $this->client($context->connection);
        $endpoint = $context->endpoint;

        $page = (int) ($context->cursor['page'] ?? 0);
        $path = $endpoint->meta['path'] ?? '';
        if ($path === '') {
            throw new \RuntimeException("Lexoffice: Endpoint {$endpoint->key} hat keinen path gesetzt.");
        }

        $query = [
            'page' => $page,
            'size' => self::PAGE_SIZE,
        ];

        // Endpoint-specific query params.
        if ($endpoint->key === 'invoices') {
            $query['voucherType']   = 'invoice';
            $query['voucherStatus'] = 'any';

            // Incremental: only rows updated on/after $since.
            //
            // The /voucherlist endpoint is an undocumented-quirk case: unlike
            // the rest of the Lexoffice API (which accepts ISO 8601 datetime
            // with millisecond precision), voucherlist ONLY accepts a bare
            // date ("Y-m-d"). Both "...T07:54:03.000+02:00" and
            // "...T05:54:03.000Z" get rejected with HTTP 400
            // "Invalid value ... for request parameter 'updatedDateFrom'".
            // Reference: the Sysix/lexoffice-php-api client also serializes
            // voucherlist date params as "Y-m-d" for the same reason.
            //
            // Consequence: we operate at day granularity. We use Berlin local
            // time to pick the correct calendar day (server TZ may be UTC).
            // Row hashing / natural-key upserts deduplicate any same-day
            // re-fetches on the writer side.
            if ($context->incremental && $context->since) {
                $berlin = (clone $context->since)->setTimezone(new \DateTimeZone('Europe/Berlin'));
                $query['updatedDateFrom'] = $berlin->format('Y-m-d');
            }
        }

        $response = $client->get($path, $query);
        $raw = $response['content'] ?? [];
        $last = (bool) ($response['last'] ?? true);

        $rows = [];
        $seenIds = [];
        foreach ($raw as $item) {
            $flat = $endpoint->key === 'contacts'
                ? $this->flattenContact($item)
                : $this->flattenInvoice($item);

            $rows[] = $flat;
            if (isset($flat['id'])) {
                $seenIds[] = (string) $flat['id'];
            }
        }

        $nextCursor = $last ? null : ['page' => $page + 1];

        return new PullResult(
            rows: $rows,
            nextCursor: $nextCursor,
            seenExternalIds: $seenIds,
            meta: [
                'page'         => $page,
                'total_pages'  => $response['totalPages']   ?? null,
                'total_items'  => $response['totalElements'] ?? null,
            ],
        );
    }

    protected function client(DatawarehouseConnection $connection): LexofficeClient
    {
        $apiKey = (string) $connection->credential('api_key', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Lexoffice: api_key ist leer.');
        }
        return new LexofficeClient($apiKey);
    }

    /**
     * Flatten a /contacts item to a single-level associative array.
     * Nested company/person fields are hoisted; lists (addresses, emails,
     * phone numbers, roles) are kept as JSON so the user can decide what
     * to extract in the onboarding step.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    protected function flattenContact(array $c): array
    {
        $company = $c['company'] ?? [];
        $person  = $c['person']  ?? [];

        return [
            'id'                  => $c['id'] ?? null,
            'organizationId'      => $c['organizationId'] ?? null,
            'version'             => $c['version'] ?? null,
            'archived'            => $c['archived'] ?? null,
            'customerNumber'      => $c['roles']['customer']['number']    ?? null,
            'vendorNumber'        => $c['roles']['vendor']['number']      ?? null,
            'isCustomer'          => isset($c['roles']['customer']),
            'isVendor'            => isset($c['roles']['vendor']),
            // Company fields
            'companyName'         => $company['name']            ?? null,
            'companyTaxNumber'    => $company['taxNumber']       ?? null,
            'companyVatRegId'     => $company['vatRegistrationId'] ?? null,
            'companyAllowTaxFree' => $company['allowTaxFreeInvoices'] ?? null,
            // Person fields
            'personSalutation'    => $person['salutation']  ?? null,
            'personFirstName'     => $person['firstName']   ?? null,
            'personLastName'      => $person['lastName']    ?? null,
            // JSON blobs for arrays
            'addresses'           => isset($c['addresses'])       ? json_encode($c['addresses'], JSON_UNESCAPED_UNICODE) : null,
            'emailAddresses'      => isset($c['emailAddresses'])  ? json_encode($c['emailAddresses'], JSON_UNESCAPED_UNICODE) : null,
            'phoneNumbers'        => isset($c['phoneNumbers'])    ? json_encode($c['phoneNumbers'], JSON_UNESCAPED_UNICODE) : null,
            'note'                => $c['note'] ?? null,
            'updatedDate'         => $c['updatedDate'] ?? null,
            'createdDate'         => $c['createdDate'] ?? null,
        ];
    }

    /**
     * Flatten a /voucherlist invoice item to a single-level assoc array.
     *
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    protected function flattenInvoice(array $v): array
    {
        return [
            'id'             => $v['id'] ?? null,
            'voucherType'    => $v['voucherType']    ?? null,
            'voucherStatus'  => $v['voucherStatus']  ?? null,
            'voucherNumber'  => $v['voucherNumber']  ?? null,
            'voucherDate'    => $v['voucherDate']    ?? null,
            'updatedDate'    => $v['updatedDate']    ?? null,
            'dueDate'        => $v['dueDate']        ?? null,
            'contactId'      => $v['contactId']      ?? null,
            'contactName'    => $v['contactName']    ?? null,
            'totalAmount'    => $v['totalAmount']    ?? null,
            'openAmount'     => $v['openAmount']     ?? null,
            'currency'       => $v['currency']       ?? null,
            'archived'       => $v['archived']       ?? null,
        ];
    }
}
