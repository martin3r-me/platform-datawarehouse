<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseStream;

class Dashboard extends Component
{
    #[On('datawarehouse:stream-created')]
    public function refreshStreams(): void
    {
        // Re-renders automatically
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $streams = DatawarehouseStream::where('team_id', $team->id)
            ->withCount('imports')
            ->orderBy('name')
            ->get();

        $stats = [
            'total'   => $streams->count(),
            'active'  => $streams->where('is_active', true)->count(),
            'success' => $streams->where('last_status', 'success')->count(),
            'error'   => $streams->where('last_status', 'error')->count(),
        ];

        return view('datawarehouse::livewire.dashboard', [
            'streams' => $streams,
            'stats'   => $stats,
        ])->layout('platform::layouts.app');
    }
}
