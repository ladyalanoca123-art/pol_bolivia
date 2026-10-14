<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario de la base de datos a las 02:00 (ver app/Console/Commands/RespaldarBaseDatos.php).
Schedule::command('respaldo:base-datos')->dailyAt('02:00')->withoutOverlapping();
