<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseDashboard;
use Platform\Datawarehouse\Models\DatawarehouseRkvConfig;

/**
 * UI editor for the RKV Rückvergütung parameters (factor, IST cutoff, JRV
 * staffeln, eventura WKZ, prior-year reference). Mirrors the dashboard editor
 * UX: edit in a form, save, redirect back to the overview. Same effect as the
 * datawarehouse.rkv_config.PUT tool. Staffel rate is entered as a percent for
 * usability and stored as a fraction.
 */
class RkvEditor extends Component
{
    // Nullable so clearing the number input (Livewire sends "") does not throw
    // a TypeError on hydration; null is caught in save().
    public ?float $factor = 1.87;
    public ?int $istThroughMonth = 6;
    public string $erLabel = 'Event Rent';
    public string $evLabel = 'eventura';
    public array $erStaffel = [];
    public array $evStaffel = [];
    public array $evWkz = [];
    public array $vorjahrEr = [];
    public array $vorjahrEv = [];

    public array $months = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    public function mount(): void
    {
        abort_unless(Auth::user()?->currentTeam, 403);

        $cfg = DatawarehouseRkvConfig::forTeamOrDefault(Auth::user()->currentTeam->id, Auth::id())->config;

        $this->factor = (float) ($cfg['factor'] ?? 1.87);
        $this->istThroughMonth = (int) ($cfg['ist_through_month'] ?? 6);
        $this->erLabel = (string) ($cfg['er']['label'] ?? 'Event Rent');
        $this->evLabel = (string) ($cfg['ev']['label'] ?? 'eventura');
        $this->erStaffel = $this->staffelToForm($cfg['er']['staffel'] ?? []);
        $this->evStaffel = $this->staffelToForm($cfg['ev']['staffel'] ?? []);
        $this->evWkz = array_map(fn ($w) => ['ab' => $w['ab'], 'wkz' => $w['wkz']], $cfg['ev']['wkz'] ?? []);

        for ($m = 1; $m <= 12; $m++) {
            $this->vorjahrEr[$m] = $cfg['vorjahr']['er'][$m] ?? 0;
            $this->vorjahrEv[$m] = $cfg['vorjahr']['ev'][$m] ?? 0;
        }
    }

    private function staffelToForm(array $staffel): array
    {
        return array_map(fn ($s) => [
            'l'       => $s['l'] ?? '',
            'v'       => $s['v'] ?? 0,
            'b'       => $s['b'] ?? null,
            'satzPct' => round((float) ($s['s'] ?? 0) * 100, 4),
        ], $staffel);
    }

    public function addStaffelRow(string $which): void
    {
        $row = ['l' => '', 'v' => 0, 'b' => null, 'satzPct' => 0];
        $which === 'er' ? $this->erStaffel[] = $row : $this->evStaffel[] = $row;
    }

    public function removeStaffelRow(string $which, int $i): void
    {
        $prop = $which === 'er' ? 'erStaffel' : 'evStaffel';
        unset($this->{$prop}[$i]);
        $this->{$prop} = array_values($this->{$prop});
    }

    public function addWkzRow(): void
    {
        $this->evWkz[] = ['ab' => 0, 'wkz' => 0];
    }

    public function removeWkzRow(int $i): void
    {
        unset($this->evWkz[$i]);
        $this->evWkz = array_values($this->evWkz);
    }

    public function save(): void
    {
        if ($this->factor === null || $this->factor <= 0) {
            $this->addError('factor', 'Faktor muss eine Zahl > 0 sein.');
            return;
        }
        if ($this->istThroughMonth === null || $this->istThroughMonth < 0 || $this->istThroughMonth > 12) {
            $this->addError('istThroughMonth', 'Monat muss zwischen 0 und 12 liegen.');
            return;
        }

        $erLabel = trim($this->erLabel);
        $evLabel = trim($this->evLabel);
        if ($erLabel === '' || $evLabel === '') {
            $this->addError('labels', 'Die Bezeichnungen dürfen nicht leer sein.');
            return;
        }

        $erStaffel = $this->formToStaffel($this->erStaffel, 'erStaffel');
        $evStaffel = $this->formToStaffel($this->evStaffel, 'evStaffel');
        if ($erStaffel === null || $evStaffel === null) {
            return; // error already added
        }

        $wkz = [];
        foreach ($this->evWkz as $w) {
            $wkz[] = ['ab' => (float) $w['ab'], 'wkz' => (float) $w['wkz']];
        }

        $vorjahrEr = [];
        $vorjahrEv = [];
        for ($m = 1; $m <= 12; $m++) {
            $vorjahrEr[$m] = (float) ($this->vorjahrEr[$m] ?? 0);
            $vorjahrEv[$m] = (float) ($this->vorjahrEv[$m] ?? 0);
        }

        $user = Auth::user();
        DatawarehouseRkvConfig::forTeamOrDefault($user->currentTeam->id, $user->id)->applyPatch([
            'factor'            => (float) $this->factor,
            'ist_through_month' => (int) $this->istThroughMonth,
            'er'      => ['label' => $erLabel, 'staffel' => $erStaffel],
            'ev'      => ['label' => $evLabel, 'staffel' => $evStaffel, 'wkz' => $wkz],
            'vorjahr' => ['er' => $vorjahrEr, 'ev' => $vorjahrEv],
        ]);

        session()->flash('rkv_saved', true);
        $this->redirect($this->backUrl(), navigate: true);
    }

    /** URL back to the RKV dashboard (the /dashboards/{id} entry). */
    #[Computed]
    public function backUrl(): string
    {
        $d = DatawarehouseDashboard::customViewFor(Auth::user()->currentTeam->id, 'rkv');

        return $d ? route('datawarehouse.dashboard.view', $d) : route('datawarehouse.dashboard');
    }

    /** @return array<int,array>|null null on validation error */
    private function formToStaffel(array $rows, string $prop): ?array
    {
        $out = [];
        foreach (array_values($rows) as $i => $r) {
            $v = (float) ($r['v'] ?? 0);
            $b = ($r['b'] === null || $r['b'] === '') ? null : (float) $r['b'];
            $pct = (float) ($r['satzPct'] ?? 0);

            if ($v < 0) {
                $this->addError($prop, 'Bandstart (ab) muss >= 0 sein.');
                return null;
            }
            if ($pct < 0 || $pct > 100) {
                $this->addError($prop, 'Satz muss zwischen 0 und 100 % liegen.');
                return null;
            }
            if ($b !== null && $b < $v) {
                $this->addError($prop, 'Bandende (bis) muss >= Bandstart sein oder leer.');
                return null;
            }

            $out[] = [
                'l' => (string) ($r['l'] ?? ''),
                'v' => $v,
                'b' => $b,
                's' => round($pct / 100, 6),
            ];
        }
        return $out;
    }

    public function render()
    {
        return view('datawarehouse::livewire.rkv-editor')
            ->layout('platform::layouts.app');
    }
}
