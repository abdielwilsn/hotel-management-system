<?php

use App\Support\AnnLedgerImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
