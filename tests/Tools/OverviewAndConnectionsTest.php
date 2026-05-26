<?php

namespace Platform\Datawarehouse\Tests\Tools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Tools\CreateConnectionTool;
use Platform\Datawarehouse\Tools\DwhOverviewTool;
use Platform\Datawarehouse\Tools\GetConnectionTool;
use Platform\Datawarehouse\Tools\ListProvidersTool;
use Tests\TestCase;

class OverviewAndConnectionsTest extends TestCase
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

    public function test_overview_returns_concepts_and_tool_map(): void
    {
        $result = (new DwhOverviewTool())->execute([], $this->context);

        $this->assertTrue($result->success);
        $data = $result->data;
        $this->assertSame('datawarehouse', $data['module']);
        $this->assertArrayHasKey('streams', $data['concepts']);
        $this->assertArrayHasKey('kpis', $data['concepts']);
        $this->assertArrayHasKey('dashboards', $data['concepts']);
        $this->assertSame('datawarehouse.streams.GET', $data['related_tools']['streams']['list']);
        $this->assertSame('datawarehouse.kpis.executeAllRanges', $data['related_tools']['kpis']['executeAllRanges']);
    }

    public function test_list_providers_filters_system_by_default(): void
    {
        $result = (new ListProvidersTool())->execute([], $this->context);

        $this->assertTrue($result->success);
        foreach ($result->data['data'] as $provider) {
            $this->assertFalse($provider['is_system'], 'System provider leaked: ' . $provider['key']);
        }
    }

    public function test_create_connection_rejects_unknown_provider(): void
    {
        $result = (new CreateConnectionTool())->execute([
            'provider_key' => 'nonexistent_provider',
            'name'         => 'Test',
            'credentials'  => ['foo' => 'bar'],
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_get_connection_omits_credentials(): void
    {
        $conn = DatawarehouseConnection::create([
            'team_id'      => $this->team->id,
            'user_id'      => $this->user->id,
            'provider_key' => 'lexoffice',
            'name'         => 'My Lexoffice',
            'credentials'  => ['api_key' => 'super-secret-token'],
            'is_active'    => true,
        ]);

        $result = (new GetConnectionTool())->execute([
            'connection_id' => $conn->id,
        ], $this->context);

        $this->assertTrue($result->success);
        $this->assertSame(['api_key'], $result->data['credential_keys_set']);
        $this->assertArrayNotHasKey('credentials', $result->data);

        $json = json_encode($result->data);
        $this->assertStringNotContainsString('super-secret-token', $json);
    }
}
