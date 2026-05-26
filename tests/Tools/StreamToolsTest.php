<?php

namespace Platform\Datawarehouse\Tests\Tools;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\ActivateStreamTool;
use Platform\Datawarehouse\Tools\ArchiveStreamTool;
use Platform\Datawarehouse\Tools\BulkCreateStreamColumnsTool;
use Platform\Datawarehouse\Tools\CreateStreamTool;
use Platform\Datawarehouse\Tools\DeleteStreamTool;
use Platform\Datawarehouse\Tools\GetStreamTool;
use Platform\Datawarehouse\Tools\ListStreamsTool;
use Platform\Datawarehouse\Tools\PauseStreamTool;
use Platform\Datawarehouse\Tools\ResumeStreamTool;
use Platform\Datawarehouse\Tools\UpdateStreamTool;
use Tests\TestCase;

class StreamToolsTest extends TestCase
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

    public function test_create_stream_defaults_to_onboarding_status(): void
    {
        $tool = new CreateStreamTool();
        $result = $tool->execute([
            'name'        => 'Bestellungen',
            'source_type' => 'webhook_post',
        ], $this->context);

        $this->assertTrue($result->success);
        $data = $result->data;
        $this->assertSame('onboarding', $data['status']);
        $this->assertSame('append', $data['sync_strategy']);
        $this->assertDatabaseHas('datawarehouse_streams', [
            'id' => $data['id'],
            'team_id' => $this->team->id,
            'status' => 'onboarding',
            'is_system' => false,
        ]);
    }

    public function test_create_stream_rejects_invalid_source_type(): void
    {
        $tool = new CreateStreamTool();
        $result = $tool->execute([
            'name'        => 'Test',
            'source_type' => 'ftp',
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_create_stream_requires_natural_key_for_current_strategy(): void
    {
        $tool = new CreateStreamTool();
        $result = $tool->execute([
            'name'          => 'Kontakte',
            'source_type'   => 'webhook_post',
            'sync_strategy' => 'current',
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_create_stream_pull_requires_connection_and_endpoint(): void
    {
        $tool = new CreateStreamTool();
        $result = $tool->execute([
            'name'        => 'Lexoffice Pull',
            'source_type' => 'pull_get',
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_get_stream_denies_access_for_other_team(): void
    {
        $otherTeam = Team::factory()->create();
        $stream = DatawarehouseStream::create([
            'team_id'     => $otherTeam->id,
            'user_id'     => $this->user->id,
            'name'        => 'Other Team Stream',
            'source_type' => 'webhook_post',
        ]);

        $tool = new GetStreamTool();
        $result = $tool->execute(['stream_id' => $stream->id], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('ACCESS_DENIED', $result->errorCode);
    }

    public function test_list_streams_hides_system_by_default(): void
    {
        DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'User Stream',
            'source_type' => 'webhook_post',
        ]);
        DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'System Stream',
            'source_type' => 'pull_get',
            'is_system'   => true,
        ]);

        $tool = new ListStreamsTool();
        $result = $tool->execute([], $this->context);

        $this->assertTrue($result->success);
        $this->assertCount(1, $result->data['data']);
        $this->assertSame('User Stream', $result->data['data'][0]['name']);

        $resultWithSystem = $tool->execute(['include_system' => true], $this->context);
        $this->assertCount(2, $resultWithSystem->data['data']);
    }

    public function test_update_stream_blocks_sync_strategy_change_after_table_created(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'       => $this->team->id,
            'user_id'       => $this->user->id,
            'name'          => 'Bestellungen',
            'source_type'   => 'webhook_post',
            'sync_strategy' => 'append',
            'table_created' => true,
            'status'        => 'active',
        ]);

        $tool = new UpdateStreamTool();
        $result = $tool->execute([
            'stream_id'     => $stream->id,
            'sync_strategy' => 'snapshot',
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);

        $stream->refresh();
        $this->assertSame('append', $stream->sync_strategy);
    }

    public function test_pause_resume_lifecycle(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'Bestellungen',
            'source_type' => 'webhook_post',
            'status'      => 'active',
        ]);

        $pauseResult = (new PauseStreamTool())->execute(['stream_id' => $stream->id], $this->context);
        $this->assertTrue($pauseResult->success);
        $stream->refresh();
        $this->assertSame('paused', $stream->status);

        // Pausieren ist nur von active erlaubt
        $doublePause = (new PauseStreamTool())->execute(['stream_id' => $stream->id], $this->context);
        $this->assertFalse($doublePause->success);

        $resumeResult = (new ResumeStreamTool())->execute(['stream_id' => $stream->id], $this->context);
        $this->assertTrue($resumeResult->success);
        $stream->refresh();
        $this->assertSame('active', $stream->status);
    }

    public function test_activate_requires_columns(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'Bestellungen',
            'source_type' => 'webhook_post',
            'status'      => 'onboarding',
        ]);

        $tool = new ActivateStreamTool();
        $result = $tool->execute(['stream_id' => $stream->id], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_archive_blocks_system_streams(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'Lookup Sprache',
            'source_type' => 'pull_get',
            'is_system'   => true,
            'status'      => 'active',
        ]);

        $tool = new ArchiveStreamTool();
        $result = $tool->execute(['stream_id' => $stream->id], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_delete_stream_blocks_system_streams(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'Lookup',
            'source_type' => 'pull_get',
            'is_system'   => true,
        ]);

        $tool = new DeleteStreamTool();
        $result = $tool->execute(['stream_id' => $stream->id], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
        $this->assertDatabaseHas('datawarehouse_streams', ['id' => $stream->id, 'deleted_at' => null]);
    }

    public function test_bulk_create_stream_columns_enforces_max_50(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'Bestellungen',
            'source_type' => 'webhook_post',
        ]);

        $items = array_fill(0, 51, ['source_key' => 'k', 'data_type' => 'string']);

        $tool = new BulkCreateStreamColumnsTool();
        $result = $tool->execute([
            'stream_id' => $stream->id,
            'items'     => $items,
        ], $this->context);

        $this->assertFalse($result->success);
        $this->assertSame('VALIDATION_ERROR', $result->errorCode);
    }

    public function test_bulk_create_stream_columns_collects_partial_errors(): void
    {
        $stream = DatawarehouseStream::create([
            'team_id'     => $this->team->id,
            'user_id'     => $this->user->id,
            'name'        => 'Bestellungen',
            'source_type' => 'webhook_post',
        ]);

        $tool = new BulkCreateStreamColumnsTool();
        $result = $tool->execute([
            'stream_id' => $stream->id,
            'items' => [
                ['source_key' => 'id',     'data_type' => 'integer'],
                ['source_key' => 'name',   'data_type' => 'string'],
                ['source_key' => '',       'data_type' => 'string'],          // fehlende source_key
                ['source_key' => 'total',  'data_type' => 'gibberish'],       // ungültiger Typ
                ['source_key' => 'id',     'data_type' => 'string'],          // Duplikat
            ],
        ], $this->context);

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->data['created_count']);
        $this->assertSame(3, $result->data['error_count']);

        $this->assertDatabaseCount('datawarehouse_stream_columns', 2);
    }
}
