<?php

use Platform\Datawarehouse\Livewire\Dashboard;
use Platform\Datawarehouse\Livewire\StreamDetail;
use Platform\Datawarehouse\Livewire\StreamOnboarding;

Route::get('/', Dashboard::class)->name('datawarehouse.dashboard');
Route::get('/streams/{stream}/onboarding', StreamOnboarding::class)->name('datawarehouse.stream.onboarding');
Route::get('/streams/{stream}', StreamDetail::class)->name('datawarehouse.stream.detail');
