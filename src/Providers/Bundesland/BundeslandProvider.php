<?php

namespace Platform\Datawarehouse\Providers\Bundesland;

use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal lookup provider for DACH federal states / cantons.
 *
 * No external API — all entries are hardcoded.
 */
class BundeslandProvider implements PullProvider
{
    private const BUNDESLAENDER = [
        // Deutschland (16 Bundesländer)
        ['id' => 'BW', 'name' => 'Baden-Württemberg',        'kuerzel' => 'BW', 'hauptstadt' => 'Stuttgart',     'flaeche_km2' => 35748, 'einwohner' => 11100394, 'region' => 'Süd',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'BY', 'name' => 'Bayern',                   'kuerzel' => 'BY', 'hauptstadt' => 'München',       'flaeche_km2' => 70542, 'einwohner' => 13124737, 'region' => 'Süd',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'BE', 'name' => 'Berlin',                   'kuerzel' => 'BE', 'hauptstadt' => 'Berlin',        'flaeche_km2' => 891,   'einwohner' => 3677472,  'region' => 'Ost',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'BB', 'name' => 'Brandenburg',              'kuerzel' => 'BB', 'hauptstadt' => 'Potsdam',       'flaeche_km2' => 29654, 'einwohner' => 2537868,  'region' => 'Ost',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'HB', 'name' => 'Bremen',                   'kuerzel' => 'HB', 'hauptstadt' => 'Bremen',        'flaeche_km2' => 419,   'einwohner' => 676463,   'region' => 'Nord', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'HH', 'name' => 'Hamburg',                  'kuerzel' => 'HH', 'hauptstadt' => 'Hamburg',       'flaeche_km2' => 755,   'einwohner' => 1853935,  'region' => 'Nord', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'HE', 'name' => 'Hessen',                   'kuerzel' => 'HE', 'hauptstadt' => 'Wiesbaden',     'flaeche_km2' => 21116, 'einwohner' => 6293154,  'region' => 'West', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'MV', 'name' => 'Mecklenburg-Vorpommern',   'kuerzel' => 'MV', 'hauptstadt' => 'Schwerin',      'flaeche_km2' => 23295, 'einwohner' => 1611160,  'region' => 'Ost',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'NI', 'name' => 'Niedersachsen',            'kuerzel' => 'NI', 'hauptstadt' => 'Hannover',      'flaeche_km2' => 47710, 'einwohner' => 8027031,  'region' => 'Nord', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'NW', 'name' => 'Nordrhein-Westfalen',      'kuerzel' => 'NW', 'hauptstadt' => 'Düsseldorf',    'flaeche_km2' => 34112, 'einwohner' => 17924591, 'region' => 'West', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'RP', 'name' => 'Rheinland-Pfalz',          'kuerzel' => 'RP', 'hauptstadt' => 'Mainz',         'flaeche_km2' => 19858, 'einwohner' => 4098391,  'region' => 'West', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'SL', 'name' => 'Saarland',                 'kuerzel' => 'SL', 'hauptstadt' => 'Saarbrücken',   'flaeche_km2' => 2571,  'einwohner' => 982348,   'region' => 'West', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'SN', 'name' => 'Sachsen',                  'kuerzel' => 'SN', 'hauptstadt' => 'Dresden',       'flaeche_km2' => 18449, 'einwohner' => 4056941,  'region' => 'Ost',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'ST', 'name' => 'Sachsen-Anhalt',           'kuerzel' => 'ST', 'hauptstadt' => 'Magdeburg',     'flaeche_km2' => 20454, 'einwohner' => 2180684,  'region' => 'Ost',  'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'SH', 'name' => 'Schleswig-Holstein',       'kuerzel' => 'SH', 'hauptstadt' => 'Kiel',          'flaeche_km2' => 15804, 'einwohner' => 2922005,  'region' => 'Nord', 'land_id' => 'DE', 'typ' => 'bundesland'],
        ['id' => 'TH', 'name' => 'Thüringen',                'kuerzel' => 'TH', 'hauptstadt' => 'Erfurt',        'flaeche_km2' => 16202, 'einwohner' => 2120237,  'region' => 'Ost',  'land_id' => 'DE', 'typ' => 'bundesland'],
        // Österreich (9 Bundesländer)
        ['id' => 'AT-1', 'name' => 'Burgenland',             'kuerzel' => 'Bgld', 'hauptstadt' => 'Eisenstadt',   'flaeche_km2' => 3962,  'einwohner' => 296010,   'region' => 'Ost',  'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-2', 'name' => 'Kärnten',                'kuerzel' => 'Ktn',  'hauptstadt' => 'Klagenfurt',   'flaeche_km2' => 9536,  'einwohner' => 564513,   'region' => 'Süd',  'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-3', 'name' => 'Niederösterreich',       'kuerzel' => 'NÖ',   'hauptstadt' => 'St. Pölten',   'flaeche_km2' => 19186, 'einwohner' => 1690879,  'region' => 'Ost',  'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-4', 'name' => 'Oberösterreich',         'kuerzel' => 'OÖ',   'hauptstadt' => 'Linz',         'flaeche_km2' => 11982, 'einwohner' => 1495608,  'region' => 'Nord', 'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-5', 'name' => 'Salzburg',               'kuerzel' => 'Sbg',  'hauptstadt' => 'Salzburg',     'flaeche_km2' => 7154,  'einwohner' => 562606,   'region' => 'West', 'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-6', 'name' => 'Steiermark',             'kuerzel' => 'Stmk', 'hauptstadt' => 'Graz',         'flaeche_km2' => 16401, 'einwohner' => 1252922,  'region' => 'Süd',  'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-7', 'name' => 'Tirol',                  'kuerzel' => 'T',    'hauptstadt' => 'Innsbruck',    'flaeche_km2' => 12648, 'einwohner' => 760105,   'region' => 'West', 'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-8', 'name' => 'Vorarlberg',             'kuerzel' => 'Vbg',  'hauptstadt' => 'Bregenz',      'flaeche_km2' => 2601,  'einwohner' => 399237,   'region' => 'West', 'land_id' => 'AT', 'typ' => 'bundesland'],
        ['id' => 'AT-9', 'name' => 'Wien',                   'kuerzel' => 'W',    'hauptstadt' => 'Wien',         'flaeche_km2' => 415,   'einwohner' => 1920949,  'region' => 'Ost',  'land_id' => 'AT', 'typ' => 'bundesland'],
        // Schweiz (26 Kantone)
        ['id' => 'CH-ZH', 'name' => 'Zürich',               'kuerzel' => 'ZH', 'hauptstadt' => 'Zürich',       'flaeche_km2' => 1729, 'einwohner' => 1553423, 'region' => 'Mittelland', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-BE', 'name' => 'Bern',                  'kuerzel' => 'BE', 'hauptstadt' => 'Bern',         'flaeche_km2' => 5959, 'einwohner' => 1043132, 'region' => 'Mittelland', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-LU', 'name' => 'Luzern',               'kuerzel' => 'LU', 'hauptstadt' => 'Luzern',       'flaeche_km2' => 1493, 'einwohner' => 416347,  'region' => 'Zentralschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-UR', 'name' => 'Uri',                   'kuerzel' => 'UR', 'hauptstadt' => 'Altdorf',      'flaeche_km2' => 1077, 'einwohner' => 36819,   'region' => 'Zentralschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-SZ', 'name' => 'Schwyz',               'kuerzel' => 'SZ', 'hauptstadt' => 'Schwyz',       'flaeche_km2' => 908,  'einwohner' => 162157,  'region' => 'Zentralschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-OW', 'name' => 'Obwalden',             'kuerzel' => 'OW', 'hauptstadt' => 'Sarnen',       'flaeche_km2' => 491,  'einwohner' => 38108,   'region' => 'Zentralschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-NW', 'name' => 'Nidwalden',            'kuerzel' => 'NW', 'hauptstadt' => 'Stans',        'flaeche_km2' => 276,  'einwohner' => 43520,   'region' => 'Zentralschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-GL', 'name' => 'Glarus',               'kuerzel' => 'GL', 'hauptstadt' => 'Glarus',       'flaeche_km2' => 685,  'einwohner' => 40851,   'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-ZG', 'name' => 'Zug',                  'kuerzel' => 'ZG', 'hauptstadt' => 'Zug',          'flaeche_km2' => 239,  'einwohner' => 129469,  'region' => 'Zentralschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-FR', 'name' => 'Freiburg',             'kuerzel' => 'FR', 'hauptstadt' => 'Freiburg',     'flaeche_km2' => 1671, 'einwohner' => 325496,  'region' => 'Mittelland', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-SO', 'name' => 'Solothurn',            'kuerzel' => 'SO', 'hauptstadt' => 'Solothurn',    'flaeche_km2' => 791,  'einwohner' => 278385,  'region' => 'Mittelland', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-BS', 'name' => 'Basel-Stadt',          'kuerzel' => 'BS', 'hauptstadt' => 'Basel',        'flaeche_km2' => 37,   'einwohner' => 196735,  'region' => 'Nordwestschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-BL', 'name' => 'Basel-Landschaft',     'kuerzel' => 'BL', 'hauptstadt' => 'Liestal',      'flaeche_km2' => 518,  'einwohner' => 292817,  'region' => 'Nordwestschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-SH', 'name' => 'Schaffhausen',         'kuerzel' => 'SH', 'hauptstadt' => 'Schaffhausen', 'flaeche_km2' => 298,  'einwohner' => 83947,   'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-AR', 'name' => 'Appenzell Ausserrhoden','kuerzel' => 'AR', 'hauptstadt' => 'Herisau',     'flaeche_km2' => 243,  'einwohner' => 55309,   'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-AI', 'name' => 'Appenzell Innerrhoden', 'kuerzel' => 'AI', 'hauptstadt' => 'Appenzell',   'flaeche_km2' => 173,  'einwohner' => 16128,   'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-SG', 'name' => 'St. Gallen',           'kuerzel' => 'SG', 'hauptstadt' => 'St. Gallen',   'flaeche_km2' => 2031, 'einwohner' => 516484,  'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-GR', 'name' => 'Graubünden',           'kuerzel' => 'GR', 'hauptstadt' => 'Chur',         'flaeche_km2' => 7105, 'einwohner' => 200096,  'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-AG', 'name' => 'Aargau',               'kuerzel' => 'AG', 'hauptstadt' => 'Aarau',        'flaeche_km2' => 1404, 'einwohner' => 694072,  'region' => 'Nordwestschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-TG', 'name' => 'Thurgau',              'kuerzel' => 'TG', 'hauptstadt' => 'Frauenfeld',   'flaeche_km2' => 991,  'einwohner' => 282909,  'region' => 'Ostschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-TI', 'name' => 'Tessin',               'kuerzel' => 'TI', 'hauptstadt' => 'Bellinzona',   'flaeche_km2' => 2812, 'einwohner' => 350363,  'region' => 'Südschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-VD', 'name' => 'Waadt',                'kuerzel' => 'VD', 'hauptstadt' => 'Lausanne',     'flaeche_km2' => 3212, 'einwohner' => 814762,  'region' => 'Westschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-VS', 'name' => 'Wallis',               'kuerzel' => 'VS', 'hauptstadt' => 'Sitten',       'flaeche_km2' => 5225, 'einwohner' => 348503,  'region' => 'Westschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-NE', 'name' => 'Neuenburg',            'kuerzel' => 'NE', 'hauptstadt' => 'Neuenburg',    'flaeche_km2' => 803,  'einwohner' => 176496,  'region' => 'Westschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-GE', 'name' => 'Genf',                 'kuerzel' => 'GE', 'hauptstadt' => 'Genf',         'flaeche_km2' => 282,  'einwohner' => 504128,  'region' => 'Westschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
        ['id' => 'CH-JU', 'name' => 'Jura',                 'kuerzel' => 'JU', 'hauptstadt' => 'Delémont',     'flaeche_km2' => 839,  'einwohner' => 73584,   'region' => 'Westschweiz', 'land_id' => 'CH', 'typ' => 'kanton'],
    ];

    public function key(): string
    {
        return 'bundesland';
    }

    public function label(): string
    {
        return 'Bundesländer / Kantone';
    }

    public function description(): ?string
    {
        return 'Deutsche Bundesländer (16), österreichische Bundesländer (9) und Schweizer Kantone (26).';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-map';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'bundeslaender' => new Endpoint(
                key: 'bundeslaender',
                label: 'Bundesländer / Kantone',
                description: 'DACH-Regionen: 16 DE-Bundesländer, 9 AT-Bundesländer, 26 CH-Kantone.',
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
        $ids = array_column(self::BUNDESLAENDER, 'id');

        return new PullResult(
            rows: self::BUNDESLAENDER,
            nextCursor: null,
            seenExternalIds: $ids,
            meta: ['total' => count(self::BUNDESLAENDER)],
        );
    }
}
