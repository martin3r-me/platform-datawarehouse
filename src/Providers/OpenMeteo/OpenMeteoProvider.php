<?php

namespace Platform\Datawarehouse\Providers\OpenMeteo;

use Illuminate\Support\Facades\Http;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Open-Meteo weather API provider.
 *
 * Free, no API key required. Delivers current weather + 7-day forecast.
 * Stream pull_config: { latitude: float, longitude: float, location_name: string }
 */
class OpenMeteoProvider implements PullProvider
{
    private const BASE_URL = 'https://api.open-meteo.com/v1/forecast';

    public function key(): string
    {
        return 'open_meteo';
    }

    public function label(): string
    {
        return 'Open-Meteo Wetter';
    }

    public function description(): ?string
    {
        return 'Aktuelles Wetter und 7-Tage-Vorhersage von Open-Meteo (kostenlos, kein API-Key). Stream-Config: latitude, longitude, location_name.';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-sun';
    }

    public function authFields(): array
    {
        return [];
    }

    public function endpoints(): array
    {
        return [
            'forecast' => new Endpoint(
                key: 'forecast',
                label: 'Aktuelles Wetter + 7-Tage-Vorhersage',
                description: 'Liefert 1 aktuelle Messung + 7 Tage Vorhersage als flache Rows. Konfiguration über Stream pull_config: latitude, longitude, location_name.',
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
        $response = Http::timeout(10)->get(self::BASE_URL, [
            'latitude' => 51.23,
            'longitude' => 6.78,
            'current' => 'temperature_2m',
            'timezone' => 'Europe/Berlin',
        ]);

        return $response->successful();
    }

    public function fetch(PullContext $context): PullResult
    {
        $config = $context->stream->pull_config ?? [];
        $lat = (float) ($config['latitude'] ?? 51.2277);
        $lon = (float) ($config['longitude'] ?? 6.7735);
        $locationName = (string) ($config['location_name'] ?? 'Unbekannt');

        $response = Http::timeout(30)->get(self::BASE_URL, [
            'latitude' => $lat,
            'longitude' => $lon,
            'current' => implode(',', [
                'temperature_2m',
                'relative_humidity_2m',
                'apparent_temperature',
                'weather_code',
                'wind_speed_10m',
                'wind_direction_10m',
                'pressure_msl',
            ]),
            'daily' => implode(',', [
                'weather_code',
                'temperature_2m_max',
                'temperature_2m_min',
                'apparent_temperature_max',
                'apparent_temperature_min',
                'precipitation_sum',
                'wind_speed_10m_max',
                'sunrise',
                'sunset',
            ]),
            'timezone' => 'Europe/Berlin',
            'forecast_days' => 7,
        ]);

        if (! $response->successful()) {
            return new PullResult(rows: [], nextCursor: null);
        }

        $data = $response->json();
        $rows = [];

        // Current weather row
        $current = $data['current'] ?? [];
        if (! empty($current)) {
            $rows[] = [
                'id' => "current_{$lat}_{$lon}",
                'date' => substr($current['time'] ?? now()->toDateString(), 0, 10),
                'type' => 'current',
                'location_name' => $locationName,
                'latitude' => $lat,
                'longitude' => $lon,
                'temperature_2m' => $current['temperature_2m'] ?? null,
                'relative_humidity_2m' => $current['relative_humidity_2m'] ?? null,
                'apparent_temperature' => $current['apparent_temperature'] ?? null,
                'weather_code' => $current['weather_code'] ?? null,
                'wind_speed_10m' => $current['wind_speed_10m'] ?? null,
                'wind_direction_10m' => $current['wind_direction_10m'] ?? null,
                'pressure_msl' => $current['pressure_msl'] ?? null,
                'temperature_2m_max' => null,
                'temperature_2m_min' => null,
                'apparent_temperature_max' => null,
                'apparent_temperature_min' => null,
                'precipitation_sum' => null,
                'wind_speed_10m_max' => null,
                'sunrise' => null,
                'sunset' => null,
            ];
        }

        // Forecast rows (7 days)
        $daily = $data['daily'] ?? [];
        $dates = $daily['time'] ?? [];
        foreach ($dates as $i => $date) {
            $rows[] = [
                'id' => "forecast_{$lat}_{$lon}_{$date}",
                'date' => $date,
                'type' => 'forecast',
                'location_name' => $locationName,
                'latitude' => $lat,
                'longitude' => $lon,
                'temperature_2m' => null,
                'relative_humidity_2m' => null,
                'apparent_temperature' => null,
                'weather_code' => $daily['weather_code'][$i] ?? null,
                'wind_speed_10m' => null,
                'wind_direction_10m' => null,
                'pressure_msl' => null,
                'temperature_2m_max' => $daily['temperature_2m_max'][$i] ?? null,
                'temperature_2m_min' => $daily['temperature_2m_min'][$i] ?? null,
                'apparent_temperature_max' => $daily['apparent_temperature_max'][$i] ?? null,
                'apparent_temperature_min' => $daily['apparent_temperature_min'][$i] ?? null,
                'precipitation_sum' => $daily['precipitation_sum'][$i] ?? null,
                'wind_speed_10m_max' => $daily['wind_speed_10m_max'][$i] ?? null,
                'sunrise' => $daily['sunrise'][$i] ?? null,
                'sunset' => $daily['sunset'][$i] ?? null,
            ];
        }

        $ids = array_column($rows, 'id');

        return new PullResult(
            rows: $rows,
            nextCursor: null,
            seenExternalIds: $ids,
            meta: ['location' => $locationName, 'total' => count($rows)],
        );
    }
}
