<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseRkvConfig;
use Platform\Datawarehouse\Services\RkvForecastService;

/**
 * Native "RKV Rückvergütung 2026" page: replicates the standalone RKV tracker —
 * IST (Stream 14) + Forecast (Streams 16/17) → Jahresprognose → JRV/WKZ/Gesamt,
 * with staffel tables and the monthly Netto-Mietumsatz chart. All parameters
 * come from DatawarehouseRkvConfig (tool-editable).
 */
class RkvRueckverguetung extends Component
{
    /** Inline-editable forecast parameters (mirror of config). */
    public ?float $factor = null;
    public ?int $istThroughMonth = null;
    public bool $paramsSaved = false;

    public function mount(): void
    {
        abort_unless(Auth::user()?->currentTeam, 403);

        $cfg = DatawarehouseRkvConfig::forTeamOrDefault(Auth::user()->currentTeam->id, Auth::id())->config;
        $this->factor = (float) ($cfg['factor'] ?? 1.87);
        $this->istThroughMonth = (int) ($cfg['ist_through_month'] ?? 6);
    }

    /**
     * Persist the two inline parameters; staffeln/WKZ/Vorjahr stay tool-only.
     */
    public function saveParams(): void
    {
        $this->paramsSaved = false;

        $factor = (float) $this->factor;
        $month  = (int) $this->istThroughMonth;

        if ($factor <= 0) {
            $this->addError('factor', 'Faktor muss > 0 sein.');
            return;
        }
        if ($month < 0 || $month > 12) {
            $this->addError('istThroughMonth', 'Monat muss zwischen 0 und 12 liegen.');
            return;
        }

        $user = Auth::user();
        DatawarehouseRkvConfig::forTeamOrDefault($user->currentTeam->id, $user->id)
            ->applyPatch(['factor' => $factor, 'ist_through_month' => $month]);

        unset($this->model); // force recompute with the new parameters
        $this->paramsSaved = true;
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
