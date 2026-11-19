<?php

use App\Http\Controllers\Api\V1\AnnonceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/annonces', [AnnonceController::class, 'index'])
    ->name('api.v1.annonces.index');

Route::post('/v1/annonces', [AnnonceController::class, 'store'])
    ->middleware(['web', 'auth'])
    ->name('api.v1.annonces.store');

Route::get('/v1/mes-annonces', [AnnonceController::class, 'mine'])
    ->middleware(['web', 'auth'])
    ->name('api.v1.annonces.mine');

Route::delete('/v1/mes-annonces/{annonce}', [AnnonceController::class, 'destroy'])
    ->whereNumber('annonce')
    ->middleware(['web', 'auth'])
    ->name('api.v1.annonces.destroy');

Route::get('/v1/mes-annonces/{annonce}', [AnnonceController::class, 'edit'])
    ->whereNumber('annonce')->middleware(['web', 'auth'])->name('api.v1.annonces.edit');

Route::patch('/v1/mes-annonces/{annonce}', [AnnonceController::class, 'update'])
    ->whereNumber('annonce')->middleware(['web', 'auth'])->name('api.v1.annonces.update');

Route::get('/v1/annonces/{annonce}', [AnnonceController::class, 'show'])
    ->whereNumber('annonce')
    ->name('api.v1.annonces.show');

Route::get('/v1/zones', [ZoneController::class, 'index'])->name('api.v1.zones.index');
Route::get('/v1/communes', [ZoneController::class, 'communes'])->name('api.v1.communes.index');

Route::middleware('web')->prefix('v1/auth')->group(function (): void {
    Route::get('/csrf', [AuthController::class, 'csrf']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/profil', [AuthController::class, 'updateProfile'])->middleware('auth');
    Route::post('/inscription', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/deconnexion', [AuthController::class, 'logout'])->middleware('auth');
});
