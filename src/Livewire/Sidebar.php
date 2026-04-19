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
                'systemStreams' => collect(),
                'userStreams'   => collect(),
            ]);
        }

        $teamId = $user->currentTeam->id;

        $systemStreams = DatawarehouseStream::where('team_id', $teamId)
            ->system()
            ->whereIn('status', ['active', 'onboarding'])
            ->orderBy('name')
            ->get();

        $userStreams = DatawarehouseStream::where('team_id', $teamId)
            ->userCreated()
            ->whereIn('status', ['active', 'onboarding'])
            ->orderBy('name')
            ->get();

        return view('datawarehouse::livewire.sidebar', [
            'systemStreams' => $systemStreams,
            'userStreams'   => $userStreams,
        ]);
    }
}
