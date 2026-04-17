<?php

use Platform\Datawarehouse\Livewire\Connections;
use Platform\Datawarehouse\Livewire\Dashboard;
use Platform\Datawarehouse\Livewire\KpiEditor;
use Platform\Datawarehouse\Livewire\StreamDetail;
use Platform\Datawarehouse\Livewire\StreamOnboarding;

Route::get('/', Dashboard::class)->name('datawarehouse.dashboard');
Route::get('/connections', Connections::class)->name('datawarehouse.connections');
Route::get('/streams/{stream}/onboarding', StreamOnboarding::class)->name('datawarehouse.stream.onboarding');
Route::get('/streams/{stream}', StreamDetail::class)->name('datawarehouse.stream.detail');
Route::get('/kpis/create', KpiEditor::class)->name('datawarehouse.kpi.create');
Route::get('/kpis/{kpi}/edit', KpiEditor::class)->name('datawarehouse.kpi.edit');
