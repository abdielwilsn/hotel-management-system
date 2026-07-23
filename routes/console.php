<?php

use App\Support\AnnLedgerImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('annledger:import {teamSlug?}', function (?string $teamSlug = null) {
    $summary = app(AnnLedgerImporter::class)->import($teamSlug);

    $this->info('Ann\'s Haven Ledger data imported successfully.');
    $this->table(['Entity', 'Imported'], collect($summary)
        ->map(fn ($value, $key) => [str_replace('_', ' ', (string) $key), $value])
        ->values()
        ->all());
})->purpose('Import seeded data from Ann\'s Haven Ledger into the HMS app.');

/*
 * Hand back rooms whose guests were due to leave. Hourly rather than daily so a
 * room freed at noon is sellable that afternoon, not the next morning.
 */
Schedule::command('stays:close-departed')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * A guest checking out today with an unpaid bar tab still on the folio is
 * worth flagging before the desk gets to checkout, not just after.
 */
Schedule::command('pos:flag-unsettled-room-charges')
    ->everyFourHours()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * A room forgotten in maintenance or cleaning is either a housekeeping
 * oversight or a turnover risk for the next guest.
 */
Schedule::command('rooms:flag-stale-status')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Nothing else in the app ever marks an invoice overdue — this is that.
 */
Schedule::command('invoices:flag-overdue')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Occupancy/collection/profitability risk, pushed instead of left for
 * whoever remembers to open the Forecasts page.
 */
Schedule::command('forecasts:daily-digest')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();
