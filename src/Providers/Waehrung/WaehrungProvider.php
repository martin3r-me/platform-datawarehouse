<?php

namespace Platform\Datawarehouse\Providers\Waehrung;

use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal lookup provider for common business currencies.
 *
 * No external API — ~30 currencies are hardcoded.
 */
class WaehrungProvider implements PullProvider
{
    private const WAEHRUNGEN = [
        ['id' => 'EUR', 'code' => 'EUR', 'name' => 'Euro',                        'name_de' => 'Euro',                        'symbol' => '€',   'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'USD', 'code' => 'USD', 'name' => 'US Dollar',                    'name_de' => 'US-Dollar',                   'symbol' => '$',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'GBP', 'code' => 'GBP', 'name' => 'British Pound',               'name_de' => 'Britisches Pfund',            'symbol' => '£',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'CHF', 'code' => 'CHF', 'name' => 'Swiss Franc',                 'name_de' => 'Schweizer Franken',           'symbol' => 'CHF', 'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'JPY', 'code' => 'JPY', 'name' => 'Japanese Yen',                'name_de' => 'Japanischer Yen',             'symbol' => '¥',   'decimal_places' => 0, 'is_eu' => false],
        ['id' => 'CAD', 'code' => 'CAD', 'name' => 'Canadian Dollar',             'name_de' => 'Kanadischer Dollar',          'symbol' => 'CA$', 'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'AUD', 'code' => 'AUD', 'name' => 'Australian Dollar',           'name_de' => 'Australischer Dollar',        'symbol' => 'A$',  'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'NZD', 'code' => 'NZD', 'name' => 'New Zealand Dollar',          'name_de' => 'Neuseeland-Dollar',           'symbol' => 'NZ$', 'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'SEK', 'code' => 'SEK', 'name' => 'Swedish Krona',               'name_de' => 'Schwedische Krone',           'symbol' => 'kr',  'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'NOK', 'code' => 'NOK', 'name' => 'Norwegian Krone',             'name_de' => 'Norwegische Krone',           'symbol' => 'kr',  'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'DKK', 'code' => 'DKK', 'name' => 'Danish Krone',                'name_de' => 'Dänische Krone',              'symbol' => 'kr',  'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'PLN', 'code' => 'PLN', 'name' => 'Polish Zloty',                'name_de' => 'Polnischer Zloty',            'symbol' => 'zł',  'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'CZK', 'code' => 'CZK', 'name' => 'Czech Koruna',               'name_de' => 'Tschechische Krone',          'symbol' => 'Kč',  'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'HUF', 'code' => 'HUF', 'name' => 'Hungarian Forint',            'name_de' => 'Ungarischer Forint',          'symbol' => 'Ft',  'decimal_places' => 0, 'is_eu' => true],
        ['id' => 'RON', 'code' => 'RON', 'name' => 'Romanian Leu',                'name_de' => 'Rumänischer Leu',             'symbol' => 'lei', 'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'BGN', 'code' => 'BGN', 'name' => 'Bulgarian Lev',               'name_de' => 'Bulgarischer Lew',            'symbol' => 'лв',  'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'HRK', 'code' => 'HRK', 'name' => 'Croatian Kuna',              'name_de' => 'Kroatische Kuna',             'symbol' => 'kn',  'decimal_places' => 2, 'is_eu' => true],
        ['id' => 'TRY', 'code' => 'TRY', 'name' => 'Turkish Lira',               'name_de' => 'Türkische Lira',              'symbol' => '₺',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'CNY', 'code' => 'CNY', 'name' => 'Chinese Yuan',                'name_de' => 'Chinesischer Yuan',           'symbol' => '¥',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'INR', 'code' => 'INR', 'name' => 'Indian Rupee',                'name_de' => 'Indische Rupie',              'symbol' => '₹',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'KRW', 'code' => 'KRW', 'name' => 'South Korean Won',            'name_de' => 'Südkoreanischer Won',         'symbol' => '₩',   'decimal_places' => 0, 'is_eu' => false],
        ['id' => 'BRL', 'code' => 'BRL', 'name' => 'Brazilian Real',              'name_de' => 'Brasilianischer Real',        'symbol' => 'R$',  'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'MXN', 'code' => 'MXN', 'name' => 'Mexican Peso',                'name_de' => 'Mexikanischer Peso',          'symbol' => 'MX$', 'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'ZAR', 'code' => 'ZAR', 'name' => 'South African Rand',          'name_de' => 'Südafrikanischer Rand',       'symbol' => 'R',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'SGD', 'code' => 'SGD', 'name' => 'Singapore Dollar',            'name_de' => 'Singapur-Dollar',             'symbol' => 'S$',  'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'HKD', 'code' => 'HKD', 'name' => 'Hong Kong Dollar',            'name_de' => 'Hongkong-Dollar',             'symbol' => 'HK$', 'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'THB', 'code' => 'THB', 'name' => 'Thai Baht',                   'name_de' => 'Thailändischer Baht',         'symbol' => '฿',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'ILS', 'code' => 'ILS', 'name' => 'Israeli New Shekel',          'name_de' => 'Israelischer Schekel',        'symbol' => '₪',   'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'AED', 'code' => 'AED', 'name' => 'United Arab Emirates Dirham', 'name_de' => 'VAE-Dirham',                  'symbol' => 'د.إ', 'decimal_places' => 2, 'is_eu' => false],
        ['id' => 'SAR', 'code' => 'SAR', 'name' => 'Saudi Riyal',                 'name_de' => 'Saudi-Rial',                  'symbol' => '﷼',   'decimal_places' => 2, 'is_eu' => false],
    ];

    public function key(): string
    {
        return 'waehrung';
    }

    public function label(): string
    {
        return 'Währungen';
    }

    public function description(): ?string
    {
        return 'Gängige Geschäftswährungen mit ISO-Code, Symbol und Dezimalstellen.';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-currency-euro';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'waehrungen' => new Endpoint(
                key: 'waehrungen',
                label: 'Währungen',
                description: 'Gängige Geschäftswährungen (~30) mit ISO-Code, Name, Symbol und Dezimalstellen.',
                paginated: false,
                incrementalField: null,
                defaultStrategy: 'current',
                naturalKey: 'id',
                supportedStrategies: ['current', 'snapshot'],
            ),
        ];
    }

    public function testConnection(DatawarehouseConnection $connection): bool
    {
        return true;
    }

    public function fetch(PullContext $context): PullResult
    {
        $ids = array_column(self::WAEHRUNGEN, 'id');

        return new PullResult(
            rows: self::WAEHRUNGEN,
            nextCursor: null,
            seenExternalIds: $ids,
            meta: ['total' => count(self::WAEHRUNGEN)],
        );
    }
}
