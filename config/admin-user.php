<?php

/*
 * Administrador inicial que crea AdminUserSeeder (php artisan db:seed).
 * Se lee desde .env para no dejar credenciales reales en el repositorio.
 * En produccion ADMIN_EMAIL y ADMIN_PASSWORD son obligatorios; en local,
 * si faltan, el seeder usa una cuenta de desarrollo.
 */
return [
    'name' => env('ADMIN_NAME', 'Administrador'),
    'email' => env('ADMIN_EMAIL'),
    'password' => env('ADMIN_PASSWORD'),
];
