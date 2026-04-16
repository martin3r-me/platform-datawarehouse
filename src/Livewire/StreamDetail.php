<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Datawarehouse\Models\DatawarehouseStream;

class StreamDetail extends Component
{
    public DatawarehouseStream $stream;

    public string $activeTab = 'overview';

    public function mount(DatawarehouseStream $stream): void
    {
        $user = Auth::user();

        abort_unless($stream->team_id === $user->currentTeam->id, 403);

        // Onboarding streams belong on the onboarding page
        if ($stream->isOnboarding()) {
            $this->redirect(route('datawarehouse.stream.onboarding', $stream));
            return;
        }

        $this->stream = $stream;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function pause(): void
    {
        if ($this->stream->status === 'active') {
            $this->stream->update(['status' => 'paused']);
        }
    }

    public function resume(): void
    {
        if ($this->stream->status === 'paused') {
            $this->stream->update(['status' => 'active']);
        }
    }

    public function archive(): void
    {
        $this->stream->update(['status' => 'archived']);
    }

    public function unarchive(): void
    {
        if ($this->stream->status === 'archived') {
            $this->stream->update(['status' => 'paused']);
        }
    }

    public function render()
    {
        $imports = $this->stream->imports()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $columns = $this->stream->columns()->orderBy('position')->get();

        $tableName = $this->stream->table_name;
        $rowCount  = null;
        $latestRows = collect();

        if ($tableName && Schema::hasTable($tableName)) {
            $rowCount = DB::table($tableName)->count();
            $latestRows = DB::table($tableName)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return view('datawarehouse::livewire.stream-detail', [
            'imports'    => $imports,
            'columns'    => $columns,
            'rowCount'   => $rowCount,
            'latestRows' => $latestRows,
        ])->layout('platform::layouts.app');
    }
}
