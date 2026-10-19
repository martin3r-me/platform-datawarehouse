<?php

namespace Platform\Datawarehouse\Tests\Http;

use PHPUnit\Framework\TestCase;
use Platform\Datawarehouse\Http\Controllers\IngestController;
use ReflectionMethod;

/**
 * The onboarding sample must stay small: stored uncapped it sits in
 * datawarehouse_streams.metadata and makes the row wide enough that sorting
 * the team's streams exhausts MySQL's sort buffer (SQLSTATE HY001 / 1038).
 *
 * Pure logic - runs without the application container.
 */
class IngestSampleTrimTest extends TestCase
{
    private function trim(array $payload): array
    {
        $method = new ReflectionMethod(IngestController::class, 'trimSample');
        $method->setAccessible(true);

        return $method->invoke(new IngestController(), $payload);
    }

    private function rows(int $count, int $width = 1): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => $i, 'text' => str_repeat('x', $width)];
        }

        return $rows;
    }

    public function test_a_long_list_is_cut_to_the_row_cap(): void
    {
        $this->assertCount(200, $this->trim($this->rows(5000)));
    }

    public function test_a_short_list_passes_through_untouched(): void
    {
        $payload = $this->rows(12);

        $this->assertSame($payload, $this->trim($payload));
    }

    public function test_a_single_object_payload_is_kept(): void
    {
        $payload = ['kostenstelle' => '1000', 'betrag' => 250.0];

        $this->assertSame($payload, $this->trim($payload));
    }

    public function test_few_but_very_wide_rows_fall_back_to_one_row(): void
    {
        // 10 rows x ~100 KB: under the row cap, far over the byte ceiling.
        $result = $this->trim($this->rows(10, 100_000));

        $this->assertCount(1, $result);
    }

    public function test_a_single_oversized_row_is_replaced_by_a_marker(): void
    {
        $result = $this->trim($this->rows(1, 500_000));

        $this->assertArrayHasKey('_truncated', $result);
    }

    public function test_the_stored_sample_never_exceeds_the_ceiling(): void
    {
        foreach ([$this->rows(5000), $this->rows(10, 100_000), $this->rows(1, 500_000)] as $payload) {
            $encoded = strlen((string) json_encode($this->trim($payload)));
            $this->assertLessThanOrEqual(262144, $encoded);
        }
    }
}
