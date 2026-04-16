<?php

namespace Platform\Datawarehouse\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Services\PullStreamService;

/**
 * Runs a single pull cycle for a given stream on the queue.
 * Triggered by the scheduler (per stream) or manually from the UI.
 */
class PullStreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public int $streamId,
        public ?int $userId = null,
    ) {}

    public function handle(PullStreamService $service): void
    {
        $stream = DatawarehouseStream::find($this->streamId);
        if (!$stream) {
            return;
        }
        if (!$stream->isPull() || $stream->status !== 'active') {
            return;
        }

        $service->pull($stream, $this->userId);
    }

    public function uniqueId(): string
    {
        return 'datawarehouse-pull-' . $this->streamId;
    }
}
