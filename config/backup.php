<?php

declare(strict_types=1);

/**
 * Respaldo de la base de datos (php artisan db:backup).
 *
 * Los volcados contienen datos personales (DNI, fechas de nacimiento):
 * se guardan fuera de public/ y nunca se suben al repositorio.
 */
return [
    // Ruta del ejecutable mysqldump. En XAMPP: C:\xampp\mysql\bin\mysqldump.exe
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),

    'directory' => env('BACKUP_DIRECTORY', storage_path('app/backups')),

    // Cuantos volcados se conservan (los mas recientes).
    'keep' => (int) env('BACKUP_KEEP', 14),
];
