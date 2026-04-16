<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Platform\Datawarehouse\Models\DatawarehouseStream;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('datawarehouse::livewire.sidebar', [
                'streams' => collect(),
            ]);
        }

        $streams = DatawarehouseStream::where('team_id', $user->currentTeam->id)
            ->whereIn('status', ['active', 'onboarding'])
            ->orderBy('name')
            ->get();

        return view('datawarehouse::livewire.sidebar', [
            'streams' => $streams,
        ]);
    }
}
