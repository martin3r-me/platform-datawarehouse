<?php

namespace Platform\Datawarehouse\Tests\Services;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use Platform\Datawarehouse\Services\KpiSnapshotSchedule;
use Platform\Datawarehouse\Services\ScheduleExpression;

/**
 * Gating rules for the per-KPI snapshot cadence. KpiSnapshotSchedule is
 * DB-free by design, so these run without the application container.
 */
class KpiSnapshotScheduleTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-09-21 10:30:00');
    }

    private function isDue(
        ?string $schedule,
        ?string $cachedAt,
        ?string $lastImportAt = null,
        bool $isDateBound = false
    ): bool {
        return KpiSnapshotSchedule::isDue(
            $schedule,
            $cachedAt !== null ? Carbon::parse($cachedAt) : null,
            $lastImportAt !== null ? Carbon::parse($lastImportAt) : null,
            $isDateBound,
            $this->now,
        );
    }

    // --- auto mode ---

    public function test_auto_recomputes_when_a_stream_delivered_new_data(): void
    {
        $this->assertTrue($this->isDue('auto', '2026-09-21 08:00:00', '2026-09-21 09:00:00'));
    }

    public function test_auto_skips_while_no_source_moved(): void
    {
        $this->assertFalse($this->isDue('auto', '2026-09-21 08:00:00', '2026-09-21 07:00:00'));
    }

    public function test_auto_skips_when_the_kpi_has_no_import_timestamp_yet(): void
    {
        $this->assertFalse($this->isDue('auto', '2026-09-21 08:00:00', null));
    }

    public function test_auto_recomputes_a_rolling_range_after_midnight(): void
    {
        $this->assertTrue($this->isDue('auto', '2026-09-20 23:50:00', '2026-09-20 06:00:00', isDateBound: true));
    }

    public function test_auto_leaves_a_rolling_range_alone_within_the_same_day(): void
    {
        $this->assertFalse($this->isDue('auto', '2026-09-21 00:05:00', '2026-09-20 06:00:00', isDateBound: true));
    }

    public function test_auto_leaves_a_range_free_kpi_alone_across_midnight(): void
    {
        $this->assertFalse($this->isDue('auto', '2026-09-20 23:50:00', '2026-09-20 06:00:00'));
    }

    public function test_auto_recomputes_once_the_cached_value_exceeds_max_age(): void
    {
        $stale = $this->now->copy()->subHours(KpiSnapshotSchedule::MAX_AGE_HOURS)->toDateTimeString();

        $this->assertTrue($this->isDue('auto', $stale, '2026-01-01 00:00:00'));
    }

    public function test_auto_is_the_default_for_an_empty_schedule(): void
    {
        $this->assertTrue($this->isDue(null, '2026-09-21 08:00:00', '2026-09-21 09:00:00'));
        $this->assertTrue($this->isDue('', '2026-09-21 08:00:00', '2026-09-21 09:00:00'));
        $this->assertFalse($this->isDue(null, '2026-09-21 08:00:00', '2026-09-21 07:00:00'));
    }

    // --- fixed cadence ---

    public function test_hourly_only_fires_on_the_hour(): void
    {
        $this->now = Carbon::parse('2026-09-21 10:00:00');
        $this->assertTrue($this->isDue('hourly', '2026-09-21 09:00:00'));

        $this->now = Carbon::parse('2026-09-21 10:30:00');
        $this->assertFalse($this->isDue('hourly', '2026-09-21 09:00:00'));
    }

    public function test_every_minute_keeps_the_old_behaviour_when_asked_for(): void
    {
        $this->assertTrue($this->isDue('every_minute', '2026-09-21 10:28:00'));
    }

    public function test_raw_cron_expressions_are_honoured(): void
    {
        $this->now = Carbon::parse('2026-09-21 06:00:00');
        $this->assertTrue($this->isDue('0 6 * * *', '2026-09-20 06:00:00'));

        $this->now = Carbon::parse('2026-09-21 07:00:00');
        $this->assertFalse($this->isDue('0 6 * * *', '2026-09-20 06:00:00'));
    }

    public function test_overlapping_ticks_do_not_snapshot_twice(): void
    {
        $this->assertFalse($this->isDue('every_minute', '2026-09-21 10:29:30'));
    }

    // --- edges ---

    public function test_manual_is_never_picked_up_by_the_scheduler(): void
    {
        $this->assertFalse($this->isDue('manual', '2026-09-21 08:00:00', '2026-09-21 09:00:00'));
        $this->assertFalse($this->isDue('manual', null));
    }

    public function test_a_kpi_without_a_cached_value_is_always_due(): void
    {
        $this->assertTrue($this->isDue('auto', null));
        $this->assertTrue($this->isDue('hourly', null));
    }

    public function test_a_broken_expression_falls_back_to_auto_instead_of_freezing(): void
    {
        $this->assertTrue($this->isDue('jeden_dritten_dienstag', '2026-09-21 08:00:00', '2026-09-21 09:00:00'));
        $this->assertFalse($this->isDue('jeden_dritten_dienstag', '2026-09-21 08:00:00', '2026-09-21 07:00:00'));
    }

    // --- helpers ---

    public function test_validation_accepts_options_and_cron_but_rejects_nonsense(): void
    {
        $this->assertTrue(KpiSnapshotSchedule::isValid('auto'));
        $this->assertTrue(KpiSnapshotSchedule::isValid('manual'));
        $this->assertTrue(KpiSnapshotSchedule::isValid('every_15_min'));
        $this->assertTrue(KpiSnapshotSchedule::isValid('*/10 * * * *'));
        $this->assertTrue(KpiSnapshotSchedule::isValid(null));

        $this->assertFalse(KpiSnapshotSchedule::isValid('stündlich'));
        $this->assertFalse(KpiSnapshotSchedule::isValid('99 99 * * *'));
    }

    public function test_normalize_and_label(): void
    {
        $this->assertSame('auto', KpiSnapshotSchedule::normalize(''));
        $this->assertSame('auto', KpiSnapshotSchedule::normalize(null));
        $this->assertSame('hourly', KpiSnapshotSchedule::normalize(' hourly '));

        $this->assertSame('Stündlich', KpiSnapshotSchedule::label('hourly'));
        $this->assertSame('0 6 * * *', KpiSnapshotSchedule::label('0 6 * * *'));
    }

    public function test_shared_shortcut_map_still_covers_the_stream_syntax(): void
    {
        $this->assertSame('*/15 * * * *', ScheduleExpression::toCron('every_15_min'));
        $this->assertSame('0 0 1 1,4,7,10 *', ScheduleExpression::toCron('quarterly'));
        $this->assertSame('0 6 * * *', ScheduleExpression::toCron('0 6 * * *'));
        $this->assertNull(ScheduleExpression::toCron('bogus'));
        $this->assertNull(ScheduleExpression::toCron(''));
    }
}
