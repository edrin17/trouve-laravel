<?php

use App\Http\Controllers\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inventory');

// Page de repli hors connexion (servie par le service worker quand le réseau échoue)
Route::view('/offline', 'offline')->name('offline');

// Page de connexion (accessible uniquement aux visiteurs non authentifiés)
Route::livewire('/login', 'auth.login')->middleware('guest')->name('login');

// Déconnexion : POST classique (form + @csrf) pour rester sans JS
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth')->name('logout');

// Application : réservée aux utilisateurs authentifiés
Route::livewire('/inventory', 'inventory.tree')->middleware('auth')->name('inventory');

// Synchronisation hors-ligne (même session que l'app — pas d'API token pour 3 users)
Route::middleware('auth')->group(function () {
    Route::post('/sync/push', [SyncController::class, 'push'])->name('sync.push');
    Route::get('/sync/pull', [SyncController::class, 'pull'])->name('sync.pull');
});
