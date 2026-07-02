<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Services\RkvForecastService;

/**
 * Native "RKV Rückvergütung 2026" page: replicates the standalone RKV tracker —
 * IST (Stream 14) + Forecast (Streams 16/17) → Jahresprognose → JRV/WKZ/Gesamt,
 * with staffel tables and the monthly Netto-Mietumsatz chart. All parameters
 * come from DatawarehouseRkvConfig (tool-editable).
 */
class RkvRueckverguetung extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()?->currentTeam, 403);
    }

    #[Computed]
    public function model(): array
    {
        $user = Auth::user();

        return app(RkvForecastService::class)->compute($user->currentTeam->id, $user->id);
    }

    public function render()
    {
        return view('datawarehouse::livewire.rkv-rueckverguetung')
            ->layout('platform::layouts.app');
    }
}
