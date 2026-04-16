<?php

namespace Platform\Datawarehouse\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\ProviderRegistry;

class Connections extends Component
{
    public function render()
    {
        $user = Auth::user();

        $connections = DatawarehouseConnection::query()
            ->forTeam($user->currentTeam->id)
            ->withCount('streams')
            ->orderBy('name')
            ->get();

        $registry = app(ProviderRegistry::class);

        return view('datawarehouse::livewire.connections', [
            'connections' => $connections,
            'providerLabels' => $registry->options(),
            'hasProviders' => count($registry->all()) > 0,
        ])->layout('platform::layouts.app');
    }

    public function delete(int $id): void
    {
        $user = Auth::user();
        $conn = DatawarehouseConnection::where('team_id', $user->currentTeam->id)->find($id);
        if (!$conn) {
            return;
        }
        // Guard: don't delete a connection that still has streams attached.
        if ($conn->streams()->exists()) {
            $this->addError('delete', 'Diese Verbindung ist noch in Streams eingebunden und kann nicht gelöscht werden.');
            return;
        }
        $conn->delete();
    }

    #[On('datawarehouse:connection-saved')]
    public function onConnectionSaved(): void
    {
        // Livewire picks up the refresh automatically via re-render.
    }
}
