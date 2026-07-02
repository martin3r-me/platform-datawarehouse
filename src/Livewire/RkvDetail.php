<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Services\RkvForecastService;

/**
 * Detail page for one RKV tile (er | ev | jrv). Same page scaffold as the KPI
 * detail page — clicking an RKV tile navigates here (consistent with KPIs),
 * showing the monthly composition (IST vs Forecast), Jahresprognose, active
 * staffel and expected JRV.
 */
class RkvDetail extends Component
{
    public string $segment;

    public function mount(string $segment): void
    {
        abort_unless(Auth::user()?->currentTeam, 403);
        abort_unless(in_array($segment, ['er', 'ev', 'jrv'], true), 404);

        $this->segment = $segment;
    }

    #[Computed]
    public function model(): array
    {
        $user = Auth::user();

        return app(RkvForecastService::class)->compute($user->currentTeam->id, $user->id);
    }

    /** URL back to the RKV dashboard (the /dashboards/{id} entry). */
    #[Computed]
    public function backUrl(): string
    {
        $d = DatawarehouseDashboard::customViewFor(Auth::user()->currentTeam->id, 'rkv');

        return $d ? route('datawarehouse.dashboard.view', $d) : route('datawarehouse.dashboard');
    }

    public function render()
    {
        return view('datawarehouse::livewire.rkv-detail')
            ->layout('platform::layouts.app');
    }
}
