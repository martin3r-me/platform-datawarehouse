<?php

namespace Platform\Datawarehouse\Providers\Sprache;

use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Internal lookup provider for common business languages.
 *
 * No external API — ~25 languages are hardcoded (ISO 639-1).
 */
class SpracheProvider implements PullProvider
{
    private const SPRACHEN = [
        ['id' => 'de', 'code' => 'de', 'name_de' => 'Deutsch',        'name_en' => 'German',      'name_native' => 'Deutsch',      'is_eu_amtssprache' => true],
        ['id' => 'en', 'code' => 'en', 'name_de' => 'Englisch',       'name_en' => 'English',     'name_native' => 'English',      'is_eu_amtssprache' => true],
        ['id' => 'fr', 'code' => 'fr', 'name_de' => 'Französisch',    'name_en' => 'French',      'name_native' => 'Français',     'is_eu_amtssprache' => true],
        ['id' => 'es', 'code' => 'es', 'name_de' => 'Spanisch',       'name_en' => 'Spanish',     'name_native' => 'Español',      'is_eu_amtssprache' => true],
        ['id' => 'it', 'code' => 'it', 'name_de' => 'Italienisch',    'name_en' => 'Italian',     'name_native' => 'Italiano',     'is_eu_amtssprache' => true],
        ['id' => 'pt', 'code' => 'pt', 'name_de' => 'Portugiesisch',  'name_en' => 'Portuguese',  'name_native' => 'Português',    'is_eu_amtssprache' => true],
        ['id' => 'nl', 'code' => 'nl', 'name_de' => 'Niederländisch', 'name_en' => 'Dutch',       'name_native' => 'Nederlands',   'is_eu_amtssprache' => true],
        ['id' => 'pl', 'code' => 'pl', 'name_de' => 'Polnisch',       'name_en' => 'Polish',      'name_native' => 'Polski',       'is_eu_amtssprache' => true],
        ['id' => 'cs', 'code' => 'cs', 'name_de' => 'Tschechisch',    'name_en' => 'Czech',       'name_native' => 'Čeština',      'is_eu_amtssprache' => true],
        ['id' => 'sk', 'code' => 'sk', 'name_de' => 'Slowakisch',     'name_en' => 'Slovak',      'name_native' => 'Slovenčina',   'is_eu_amtssprache' => true],
        ['id' => 'hu', 'code' => 'hu', 'name_de' => 'Ungarisch',      'name_en' => 'Hungarian',   'name_native' => 'Magyar',       'is_eu_amtssprache' => true],
        ['id' => 'ro', 'code' => 'ro', 'name_de' => 'Rumänisch',      'name_en' => 'Romanian',    'name_native' => 'Română',       'is_eu_amtssprache' => true],
        ['id' => 'bg', 'code' => 'bg', 'name_de' => 'Bulgarisch',     'name_en' => 'Bulgarian',   'name_native' => 'Български',    'is_eu_amtssprache' => true],
        ['id' => 'hr', 'code' => 'hr', 'name_de' => 'Kroatisch',      'name_en' => 'Croatian',    'name_native' => 'Hrvatski',     'is_eu_amtssprache' => true],
        ['id' => 'da', 'code' => 'da', 'name_de' => 'Dänisch',        'name_en' => 'Danish',      'name_native' => 'Dansk',        'is_eu_amtssprache' => true],
        ['id' => 'fi', 'code' => 'fi', 'name_de' => 'Finnisch',       'name_en' => 'Finnish',     'name_native' => 'Suomi',        'is_eu_amtssprache' => true],
        ['id' => 'sv', 'code' => 'sv', 'name_de' => 'Schwedisch',     'name_en' => 'Swedish',     'name_native' => 'Svenska',      'is_eu_amtssprache' => true],
        ['id' => 'el', 'code' => 'el', 'name_de' => 'Griechisch',     'name_en' => 'Greek',       'name_native' => 'Ελληνικά',     'is_eu_amtssprache' => true],
        ['id' => 'sl', 'code' => 'sl', 'name_de' => 'Slowenisch',     'name_en' => 'Slovenian',   'name_native' => 'Slovenščina',  'is_eu_amtssprache' => true],
        ['id' => 'et', 'code' => 'et', 'name_de' => 'Estnisch',       'name_en' => 'Estonian',    'name_native' => 'Eesti',        'is_eu_amtssprache' => true],
        ['id' => 'lv', 'code' => 'lv', 'name_de' => 'Lettisch',       'name_en' => 'Latvian',     'name_native' => 'Latviešu',     'is_eu_amtssprache' => true],
        ['id' => 'lt', 'code' => 'lt', 'name_de' => 'Litauisch',      'name_en' => 'Lithuanian',  'name_native' => 'Lietuvių',     'is_eu_amtssprache' => true],
        ['id' => 'tr', 'code' => 'tr', 'name_de' => 'Türkisch',       'name_en' => 'Turkish',     'name_native' => 'Türkçe',       'is_eu_amtssprache' => false],
        ['id' => 'ru', 'code' => 'ru', 'name_de' => 'Russisch',       'name_en' => 'Russian',     'name_native' => 'Русский',      'is_eu_amtssprache' => false],
        ['id' => 'zh', 'code' => 'zh', 'name_de' => 'Chinesisch',     'name_en' => 'Chinese',     'name_native' => '中文',          'is_eu_amtssprache' => false],
        ['id' => 'ja', 'code' => 'ja', 'name_de' => 'Japanisch',      'name_en' => 'Japanese',    'name_native' => '日本語',        'is_eu_amtssprache' => false],
        ['id' => 'ko', 'code' => 'ko', 'name_de' => 'Koreanisch',     'name_en' => 'Korean',      'name_native' => '한국어',        'is_eu_amtssprache' => false],
        ['id' => 'ar', 'code' => 'ar', 'name_de' => 'Arabisch',       'name_en' => 'Arabic',      'name_native' => 'العربية',      'is_eu_amtssprache' => false],
    ];

    public function key(): string
    {
        return 'sprache';
    }

    public function label(): string
    {
        return 'Sprachen';
    }

    public function description(): ?string
    {
        return 'Gängige Geschäftssprachen mit ISO 639-1 Code und EU-Amtssprachen-Status.';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-language';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'sprachen' => new Endpoint(
                key: 'sprachen',
                label: 'Sprachen',
                description: 'Gängige Geschäftssprachen (~25) mit ISO-Code, Namen und EU-Amtssprachen-Status.',
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
        $ids = array_column(self::SPRACHEN, 'id');

        return new PullResult(
            rows: self::SPRACHEN,
            nextCursor: null,
            seenExternalIds: $ids,
            meta: ['total' => count(self::SPRACHEN)],
        );
    }
}
