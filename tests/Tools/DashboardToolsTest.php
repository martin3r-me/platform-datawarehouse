<?php

namespace Platform\Datawarehouse\Tests\Tools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Tools\AttachKpiToDashboardTool;
use Platform\Datawarehouse\Tools\CreateDashboardTool;
use Platform\Datawarehouse\Tools\DetachKpiFromDashboardTool;
use Platform\Datawarehouse\Tools\ReorderDashboardKpisTool;
use Tests\TestCase;

class DashboardToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $team;
    private ToolContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create();
        $this->user->teams()->attach($this->team);
        $this->context = new ToolContext(user: $this->user, team: $this->team);
    }

    public function test_create_then_attach_then_detach_lifecycle(): void
    {
        $createResult = (new CreateDashboardTool())->execute([
            'name' => 'Sales',
        ], $this->context);
        $this->assertTrue($createResult->success);
        $dashboardId = $createResult->data['id'];

        $kpi = DatawarehouseKpi::create([
            'team_id'    => $this->team->id,
            'user_id'    => $this->user->id,
            'name'       => 'Total Orders',
            'definition' => ['streams' => [], 'aggregations' => []],
            'status'     => 'active',
        ]);

        $attachResult = (new AttachKpiToDashboardTool())->execute([
            'dashboard_id' => $dashboardId,
            'kpi_id'       => $kpi->id,
            'position'     => 7,
        ], $this->context);
        $this->assertTrue($attachResult->success);
        $this->assertSame(7, $attachResult->data['position']);
        $this->assertDatabaseHas('datawarehouse_dashboard_kpis', [
            'dashboard_id' => $dashboardId,
            'kpi_id'       => $kpi->id,
            'position'     => 7,
        ]);

        // Re-Attach mit anderer Position muss bestehende Pivot-Position aktualisieren.
        $attachAgain = (new AttachKpiToDashboardTool())->execute([
            'dashboard_id' => $dashboardId,
            'kpi_id'       => $kpi->id,
            'position'     => 1,
        ], $this->context);
        $this->assertTrue($attachAgain->success);
        $this->assertDatabaseHas('datawarehouse_dashboard_kpis', [
            'dashboard_id' => $dashboardId,
            'kpi_id'       => $kpi->id,
            'position'     => 1,
        ]);

        $detachResult = (new DetachKpiFromDashboardTool())->execute([
            'dashboard_id' => $dashboardId,
            'kpi_id'       => $kpi->id,
        ], $this->context);
        $this->assertTrue($detachResult->success);
        $this->assertSame(1, $detachResult->data['detached']);
        $this->assertDatabaseMissing('datawarehouse_dashboard_kpis', [
            'dashboard_id' => $dashboardId,
            'kpi_id'       => $kpi->id,
        ]);
    }

    public function test_reorder_ignores_unattached_kpis(): void
    {
        $dashboard = DatawarehouseDashboard::create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'name'    => 'Ops',
        ]);
        $attached = DatawarehouseKpi::create([
            'team_id'    => $this->team->id,
            'user_id'    => $this->user->id,
            'name'       => 'K1',
            'definition' => ['streams' => [], 'aggregations' => []],
            'status'     => 'active',
        ]);
        $other = DatawarehouseKpi::create([
            'team_id'    => $this->team->id,
            'user_id'    => $this->user->id,
            'name'       => 'K2 (not attached)',
            'definition' => ['streams' => [], 'aggregations' => []],
            'status'     => 'active',
        ]);
        $dashboard->kpis()->attach($attached->id, ['position' => 1]);

        $result = (new ReorderDashboardKpisTool())->execute([
            'dashboard_id' => $dashboard->id,
            'items'        => [
                ['kpi_id' => $attached->id, 'position' => 99],
                ['kpi_id' => $other->id,    'position' => 0],
            ],
        ], $this->context);

        $this->assertTrue($result->success);
        $this->assertSame(1, $result->data['updated_count']);
        $this->assertSame(1, $result->data['error_count']);

        $this->assertSame(99, (int)$dashboard->kpis()->wherePivot('kpi_id', $attached->id)->first()->pivot->position);
    }

    public function test_dashboard_access_is_team_scoped(): void
    {
        $otherTeam = Team::factory()->create();
        $dashboard = DatawarehouseDashboard::create([
            'team_id' => $otherTeam->id,
            'user_id' => $this->user->id,
            'name'    => 'Foreign',
        ]);

        $result = (new AttachKpiToDashboardTool())->execute([
            'dashboard_id' => $dashboard->id,
            'kpi_id'       => 1,
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('NOT_FOUND', $result->errorCode);
    }
}
