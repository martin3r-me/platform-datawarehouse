<?php

namespace Platform\Datawarehouse\Tests\Services;

use Platform\Datawarehouse\Models\DatawarehouseRkvConfig;
use Platform\Datawarehouse\Services\RkvForecastService;
use Tests\TestCase;

/**
 * Pure calculation tests for the RKV forecast: JRV staffel lookup, IST/Forecast
 * month splice, Vorjahr×Faktor fallback and eventura WKZ. Exercises the DB-free
 * RkvForecastService::assemble() with fixture maps + the tracker defaults.
 */
class RkvForecastServiceTest extends TestCase
{
    private function service(): RkvForecastService
    {
        return app(RkvForecastService::class);
    }

    private function cfg(): array
    {
        return DatawarehouseRkvConfig::defaultConfig();
    }

    /** Full Jul–Dez forecast map (all months present) so no Vorjahr fallback fires. */
    private function forecastMonths(array $overrides = []): array
    {
        $m = [];
        for ($i = 7; $i <= 12; $i++) {
            $m[$i] = 0.0;
        }
        return $overrides + $m;
    }

    public function test_splice_prognose_and_event_rent_staffel(): void
    {
        $r = $this->service()->assemble(
            $this->cfg(),
            [6 => 270178.33],                          // ER IST (Jan–Jun)
            $this->forecastMonths([9 => 176443.06]),   // ER Forecast (Jul–Dez)
            [6 => 69796.77],                           // EV IST
            $this->forecastMonths([9 => 130287.08]),   // EV Forecast
        );

        $this->assertEqualsWithDelta(270178.33, $r['er']['ist_sum'], 0.01);
        $this->assertEqualsWithDelta(446621.39, $r['er']['prognose'], 0.01);
        // 446.621,39 liegt im Band 400–500 T€ → 5 %
        $this->assertEqualsWithDelta(446621.39 * 0.05, $r['gesamt']['jrv_er'], 0.01);
        $this->assertSame(500000.0, (float) $r['er']['progress']['target']);
        $this->assertEqualsWithDelta(89.3, (float) $r['er']['progress']['pct'], 0.05);

        // eventura 200.083,85 < 300k JRV-Schwelle → keine JRV, kein WKZ
        $this->assertEqualsWithDelta(200083.85, $r['ev']['prognose'], 0.01);
        $this->assertSame(0.0, (float) $r['gesamt']['jrv_ev']);
        $this->assertSame(0.0, (float) $r['gesamt']['wkz']);
        $this->assertEqualsWithDelta(446621.39 * 0.05, $r['gesamt']['total'], 0.01);
    }

    public function test_vorjahr_factor_fallback_for_missing_forecast_months(): void
    {
        // No forecast data at all → Jul–Dez fall back to Vorjahr[m] × factor (1,87).
        $r = $this->service()->assemble($this->cfg(), [6 => 1000.0], [], [], []);

        $this->assertEqualsWithDelta(1000.0, $r['er']['series'][6], 0.01);          // IST month kept
        $this->assertEqualsWithDelta(317290.95 * 1.87, $r['er']['series'][12], 0.01); // Dez fallback
    }

    public function test_eventura_staffel_and_wkz_over_threshold(): void
    {
        $r = $this->service()->assemble(
            $this->cfg(),
            [],
            $this->forecastMonths(),
            [6 => 320000.0],           // eventura prognose 320k → Band 300–400 T€ (1,5 %)
            $this->forecastMonths(),
        );

        $this->assertEqualsWithDelta(320000.0, $r['ev']['prognose'], 0.01);
        $this->assertEqualsWithDelta(320000.0 * 0.015, $r['gesamt']['jrv_ev'], 0.01);
        $this->assertSame(20000.0, (float) $r['gesamt']['wkz']); // höchste WKZ-Stufe ab 300k
    }

    public function test_event_rent_top_band_capped_progress(): void
    {
        $r = $this->service()->assemble(
            $this->cfg(),
            [6 => 900000.0],
            $this->forecastMonths(),
            [],
            $this->forecastMonths(),
        );

        $this->assertEqualsWithDelta(900000.0 * 0.0775, $r['gesamt']['jrv_er'], 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $r['er']['progress']['pct'], 0.01);
    }
}
