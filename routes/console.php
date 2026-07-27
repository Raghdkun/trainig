<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Sweep away temp chunk files from abandoned uploads (a completed or cancelled
 * upload cleans up after itself; this catches the ones that never finished).
 */
Schedule::call(function (): void {
    $disk = Storage::disk('local');
    $cutoff = now()->subDay()->getTimestamp();

    foreach ($disk->files('media-chunks') as $file) {
        if ($disk->lastModified($file) < $cutoff) {
            $disk->delete($file);
        }
    }
})->daily()->name('prune-media-chunks');
