<?php

namespace Platform\Datawarehouse\Providers\Land;

use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal lookup provider for countries.
 *
 * No external API — DACH + EU core + key trade partners are hardcoded.
 */
class LandProvider implements PullProvider
{
    private const LAENDER = [
        // DACH
        ['id' => 'DE', 'code' => 'DE', 'name_de' => 'Deutschland',       'name_en' => 'Germany',         'iso_alpha3' => 'DEU', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'AT', 'code' => 'AT', 'name_de' => 'Österreich',        'name_en' => 'Austria',         'iso_alpha3' => 'AUT', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'CH', 'code' => 'CH', 'name_de' => 'Schweiz',           'name_en' => 'Switzerland',     'iso_alpha3' => 'CHE', 'kontinent' => 'Europa',  'is_eu' => false, 'waehrung_code' => 'CHF'],
        // EU core
        ['id' => 'FR', 'code' => 'FR', 'name_de' => 'Frankreich',        'name_en' => 'France',          'iso_alpha3' => 'FRA', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'IT', 'code' => 'IT', 'name_de' => 'Italien',           'name_en' => 'Italy',           'iso_alpha3' => 'ITA', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'ES', 'code' => 'ES', 'name_de' => 'Spanien',           'name_en' => 'Spain',           'iso_alpha3' => 'ESP', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'PT', 'code' => 'PT', 'name_de' => 'Portugal',          'name_en' => 'Portugal',        'iso_alpha3' => 'PRT', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'NL', 'code' => 'NL', 'name_de' => 'Niederlande',       'name_en' => 'Netherlands',     'iso_alpha3' => 'NLD', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'BE', 'code' => 'BE', 'name_de' => 'Belgien',           'name_en' => 'Belgium',         'iso_alpha3' => 'BEL', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'LU', 'code' => 'LU', 'name_de' => 'Luxemburg',         'name_en' => 'Luxembourg',      'iso_alpha3' => 'LUX', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'PL', 'code' => 'PL', 'name_de' => 'Polen',             'name_en' => 'Poland',          'iso_alpha3' => 'POL', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'PLN'],
        ['id' => 'CZ', 'code' => 'CZ', 'name_de' => 'Tschechien',        'name_en' => 'Czech Republic',  'iso_alpha3' => 'CZE', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'CZK'],
        ['id' => 'DK', 'code' => 'DK', 'name_de' => 'Dänemark',          'name_en' => 'Denmark',         'iso_alpha3' => 'DNK', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'DKK'],
        ['id' => 'SE', 'code' => 'SE', 'name_de' => 'Schweden',           'name_en' => 'Sweden',          'iso_alpha3' => 'SWE', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'SEK'],
        ['id' => 'FI', 'code' => 'FI', 'name_de' => 'Finnland',           'name_en' => 'Finland',         'iso_alpha3' => 'FIN', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'IE', 'code' => 'IE', 'name_de' => 'Irland',             'name_en' => 'Ireland',         'iso_alpha3' => 'IRL', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'GR', 'code' => 'GR', 'name_de' => 'Griechenland',       'name_en' => 'Greece',          'iso_alpha3' => 'GRC', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'HU', 'code' => 'HU', 'name_de' => 'Ungarn',             'name_en' => 'Hungary',         'iso_alpha3' => 'HUN', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'HUF'],
        ['id' => 'RO', 'code' => 'RO', 'name_de' => 'Rumänien',           'name_en' => 'Romania',         'iso_alpha3' => 'ROU', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'RON'],
        ['id' => 'HR', 'code' => 'HR', 'name_de' => 'Kroatien',           'name_en' => 'Croatia',         'iso_alpha3' => 'HRV', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'SK', 'code' => 'SK', 'name_de' => 'Slowakei',           'name_en' => 'Slovakia',        'iso_alpha3' => 'SVK', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'SI', 'code' => 'SI', 'name_de' => 'Slowenien',          'name_en' => 'Slovenia',        'iso_alpha3' => 'SVN', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'EUR'],
        ['id' => 'BG', 'code' => 'BG', 'name_de' => 'Bulgarien',          'name_en' => 'Bulgaria',        'iso_alpha3' => 'BGR', 'kontinent' => 'Europa',  'is_eu' => true,  'waehrung_code' => 'BGN'],
        // Non-EU Europe
        ['id' => 'GB', 'code' => 'GB', 'name_de' => 'Vereinigtes Königreich', 'name_en' => 'United Kingdom', 'iso_alpha3' => 'GBR', 'kontinent' => 'Europa', 'is_eu' => false, 'waehrung_code' => 'GBP'],
        ['id' => 'NO', 'code' => 'NO', 'name_de' => 'Norwegen',           'name_en' => 'Norway',          'iso_alpha3' => 'NOR', 'kontinent' => 'Europa',  'is_eu' => false, 'waehrung_code' => 'NOK'],
        ['id' => 'TR', 'code' => 'TR', 'name_de' => 'Türkei',             'name_en' => 'Turkey',          'iso_alpha3' => 'TUR', 'kontinent' => 'Europa',  'is_eu' => false, 'waehrung_code' => 'TRY'],
        // Key trade partners
        ['id' => 'US', 'code' => 'US', 'name_de' => 'Vereinigte Staaten', 'name_en' => 'United States',   'iso_alpha3' => 'USA', 'kontinent' => 'Nordamerika', 'is_eu' => false, 'waehrung_code' => 'USD'],
        ['id' => 'CN', 'code' => 'CN', 'name_de' => 'China',              'name_en' => 'China',           'iso_alpha3' => 'CHN', 'kontinent' => 'Asien',       'is_eu' => false, 'waehrung_code' => 'CNY'],
        ['id' => 'JP', 'code' => 'JP', 'name_de' => 'Japan',              'name_en' => 'Japan',           'iso_alpha3' => 'JPN', 'kontinent' => 'Asien',       'is_eu' => false, 'waehrung_code' => 'JPY'],
        ['id' => 'KR', 'code' => 'KR', 'name_de' => 'Südkorea',           'name_en' => 'South Korea',     'iso_alpha3' => 'KOR', 'kontinent' => 'Asien',       'is_eu' => false, 'waehrung_code' => 'KRW'],
        ['id' => 'IN', 'code' => 'IN', 'name_de' => 'Indien',             'name_en' => 'India',           'iso_alpha3' => 'IND', 'kontinent' => 'Asien',       'is_eu' => false, 'waehrung_code' => 'INR'],
    ];

    public function key(): string
    {
        return 'land';
    }

    public function label(): string
    {
        return 'Länder';
    }

    public function description(): ?string
    {
        return 'DACH + EU-Kernländer + wichtige Handelspartner (~30 Länder).';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-globe-europe-africa';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'laender' => new Endpoint(
                key: 'laender',
                label: 'Länder',
                description: 'DACH + EU-Kernländer + wichtige Handelspartner mit ISO-Codes, Kontinent, EU-Status und Währung.',
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
        $ids = array_column(self::LAENDER, 'id');

        return new PullResult(
            rows: self::LAENDER,
            nextCursor: null,
            seenExternalIds: $ids,
            meta: ['total' => count(self::LAENDER)],
        );
    }
}
