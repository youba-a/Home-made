<?php

use App\Http\Controllers\Api\V1\AnnonceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/annonces', [AnnonceController::class, 'index'])
    ->name('api.v1.annonces.index');

Route::get('/v1/annonces/{annonce}', [AnnonceController::class, 'show'])
    ->whereNumber('annonce')
    ->name('api.v1.annonces.show');

Route::get('/v1/zones', [ZoneController::class, 'index'])->name('api.v1.zones.index');

Route::middleware('web')->prefix('v1/auth')->group(function (): void {
    Route::get('/csrf', [AuthController::class, 'csrf']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/inscription', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/deconnexion', [AuthController::class, 'logout'])->middleware('auth');
});
