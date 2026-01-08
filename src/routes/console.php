<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule mod update checks to run daily
Schedule::command('mods:check-updates')
    ->daily()
    ->at('02:00')
    ->timezone('UTC');

// Schedule Minecraft version update checks to run daily
Schedule::command('minecraft:check-version-updates')
    ->daily()
    ->at('12:00')
    ->timezone('UTC');
