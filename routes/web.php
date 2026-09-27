<?php

use App\Livewire\Admin\Roles\Index as AdminRolesIndex;
use App\Livewire\Admin\Sports\Index as AdminSportsIndex;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Dashboard;
use App\Livewire\Players\Index as PlayersIndex;
use App\Livewire\Teams\Index as TeamsIndex;
use App\Livewire\Tournaments\Index as TournamentsIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', Dashboard::class)
        ->name('dashboard');

    Route::get('/equipos', TeamsIndex::class)
        ->name('teams.index');

    Route::get('/jugadores', PlayersIndex::class)
        ->name('players.index');

    Route::get('/torneos', TournamentsIndex::class)
        ->name('tournaments.index');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/deportes', AdminSportsIndex::class)->name('admin.sports.index');
        Route::get('/admin/usuarios', AdminUsersIndex::class)->name('admin.users.index');
        Route::get('/admin/roles', AdminRolesIndex::class)->name('admin.roles.index');
    });
});
