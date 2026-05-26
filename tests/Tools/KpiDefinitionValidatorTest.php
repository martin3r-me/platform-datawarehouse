<?php

namespace Platform\Datawarehouse\Tests\Tools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Services\KpiDefinitionValidator;
use Platform\Datawarehouse\Tools\CreateKpiTool;
use Platform\Datawarehouse\Tools\UpdateKpiTool;
use Tests\TestCase;

class KpiDefinitionValidatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private ToolContext $context;
    private DatawarehouseStream $stream;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->user->teams()->attach($this->team);
        $this->context = new ToolContext(user: $this->user, team: $this->team);

        $this->stream = DatawarehouseStream::create([
            'team_id'       => $this->team->id,
            'user_id'       => $this->user->id,
            'name'          => 'Bestellungen',
            'source_type'   => 'webhook_post',
            'sync_strategy' => 'append',
            'table_created' => true,
            'status'        => 'active',
        ]);
        DatawarehouseStreamColumn::create([
            'stream_id'   => $this->stream->id,
            'source_key'  => 'betrag',
            'column_name' => 'betrag',
            'data_type'   => 'decimal',
            'precision'   => 10,
            'scale'       => 2,
            'is_active'   => true,
            'position'    => 1,
        ]);
    }

    public function test_validator_accepts_minimum_valid_definition(): void
    {
        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
            'aggregations' => [['function' => 'SUM', 'column' => 'betrag', 'stream_alias' => 's0']],
        ], $this->team->id);

        $this->assertNull($error);
    }

    public function test_validator_rejects_unknown_column(): void
    {
        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
            'aggregations' => [['function' => 'SUM', 'column' => 'unknown_col', 'stream_alias' => 's0']],
        ], $this->team->id);

        $this->assertNotNull($error);
        $this->assertStringContainsString('unknown_col', $error);
    }

    public function test_validator_rejects_sql_injection_in_column(): void
    {
        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
            'aggregations' => [['function' => 'SUM', 'column' => 'betrag); DROP TABLE users; --', 'stream_alias' => 's0']],
        ], $this->team->id);

        $this->assertNotNull($error);
        $this->assertStringContainsString('ungültige Zeichen', $error);
    }

    public function test_validator_rejects_unknown_aggregation_function(): void
    {
        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
            'aggregations' => [['function' => 'EXEC', 'column' => 'betrag', 'stream_alias' => 's0']],
        ], $this->team->id);

        $this->assertNotNull($error);
    }

    public function test_validator_rejects_alias_pattern_violation(): void
    {
        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $this->stream->id, 'alias' => 'users_table']],
            'aggregations' => [['function' => 'COUNT', 'column' => '*', 'stream_alias' => 'users_table']],
        ], $this->team->id);

        $this->assertNotNull($error);
        $this->assertStringContainsString('alias', $error);
    }

    public function test_validator_rejects_invalid_date_range(): void
    {
        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
            'aggregations' => [['function' => 'COUNT', 'column' => '*', 'stream_alias' => 's0']],
            'calendar_filters' => [
                'date_column' => 'betrag',
                'date_range'  => 'eternity',
            ],
        ], $this->team->id);

        $this->assertNotNull($error);
        $this->assertStringContainsString('date_range', $error);
    }

    public function test_validator_rejects_cross_team_stream(): void
    {
        $otherTeam = Team::factory()->create();
        $otherStream = DatawarehouseStream::create([
            'team_id'     => $otherTeam->id,
            'user_id'     => $this->user->id,
            'name'        => 'Other',
            'source_type' => 'webhook_post',
        ]);

        $validator = new KpiDefinitionValidator();
        $error = $validator->validate([
            'streams'      => [['stream_id' => $otherStream->id, 'alias' => 's0']],
            'aggregations' => [['function' => 'COUNT', 'column' => '*', 'stream_alias' => 's0']],
        ], $this->team->id);

        $this->assertNotNull($error);
        $this->assertStringContainsString('nicht gefunden', $error);
    }

    public function test_create_kpi_tool_runs_definition_through_validator(): void
    {
        $tool = new CreateKpiTool();
        $result = $tool->execute([
            'name' => 'Bad KPI',
            'definition' => [
                'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
                'aggregations' => [['function' => 'SUM', 'column' => "x;DROP", 'stream_alias' => 's0']],
            ],
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
        $this->assertDatabaseMissing('datawarehouse_kpis', ['name' => 'Bad KPI']);
    }

    public function test_update_kpi_clears_cache_when_definition_changes(): void
    {
        $kpi = DatawarehouseKpi::create([
            'team_id'      => $this->team->id,
            'user_id'      => $this->user->id,
            'name'         => 'Sum Betrag',
            'definition'   => [
                'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
                'aggregations' => [['function' => 'SUM', 'column' => 'betrag', 'stream_alias' => 's0']],
            ],
            'cached_value' => 99.5,
            'cached_at'    => now(),
            'status'       => 'active',
        ]);

        $tool = new UpdateKpiTool();
        $result = $tool->execute([
            'kpi_id'     => $kpi->id,
            'definition' => [
                'streams'      => [['stream_id' => $this->stream->id, 'alias' => 's0']],
                'aggregations' => [['function' => 'COUNT', 'column' => '*', 'stream_alias' => 's0']],
            ],
        ], $this->context);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['definition_changed']);

        $kpi->refresh();
        $this->assertNull($kpi->cached_value);
        $this->assertNull($kpi->cached_at);
    }
}
