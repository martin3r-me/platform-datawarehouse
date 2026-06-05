<?php

namespace Platform\Datawarehouse\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;

class Providers extends Component
{
    public function render()
    {
        $user = Auth::user();
        $teamId = $user->currentTeam->id;

        $definitions = DatawarehouseProviderDefinition::query()
            ->forTeam($teamId)
            ->orderBy('label')
            ->get();

        // Connection counts per provider key (for usage hints / delete guard).
        $usage = DatawarehouseConnection::query()
            ->where('team_id', $teamId)
            ->selectRaw('provider_key, COUNT(*) as c')
            ->groupBy('provider_key')
            ->pluck('c', 'provider_key');

        return view('datawarehouse::livewire.providers', [
            'definitions' => $definitions,
            'usage'       => $usage,
        ])->layout('platform::layouts.app');
    }

    public function delete(int $id): void
    {
        $user = Auth::user();
        $def = DatawarehouseProviderDefinition::where('team_id', $user->currentTeam->id)->find($id);
        if (!$def) {
            return;
        }

        $usedBy = DatawarehouseConnection::where('team_id', $user->currentTeam->id)
            ->where('provider_key', $def->key)
            ->count();
        if ($usedBy > 0) {
            $this->addError('delete', "Provider '{$def->label}' wird noch von {$usedBy} Verbindung(en) genutzt und kann nicht gelöscht werden.");
            return;
        }

        $def->delete();
    }

    #[On('datawarehouse:provider-definition-saved')]
    public function onSaved(): void
    {
        // Re-render picks up the change.
    }
}
