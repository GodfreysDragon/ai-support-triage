<?php

use App\Demo\CreateDemoAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:prune', function () {
    $this->info('Deleted '.CreateDemoAccount::pruneExpired().' expired demo account(s).');
})->purpose('Delete demo accounts older than DEMO_LIFETIME_HOURS');

Schedule::command('demo:prune')->hourly();
