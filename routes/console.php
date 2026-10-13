<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldo diario de la base (N3). Necesita el cron del hosting:
//   * * * * * php /ruta/al/proyecto/artisan schedule:run
// o, si el hosting solo permite tareas a una hora fija, llamar directo a
//   php /ruta/al/proyecto/artisan db:backup
Schedule::command('db:backup')->dailyAt('23:00');
