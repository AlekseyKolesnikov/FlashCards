<?php

// JSON API for the React app (SPECIFICATION.md §10.3). Loaded under /api with the web
// middleware group and route names prefixed "api.", see bootstrap/app.php.

use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('user', [LoginController::class, 'show'])->name('user');
Route::post('login', [LoginController::class, 'store'])->name('login');
Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

Route::post('register', [RegisterController::class, 'store'])->name('register');
Route::post('email/resend', [RegisterController::class, 'resend'])->middleware('throttle:6,1')->name('email.resend');
Route::get('email/verify/{id}/{hash}', [RegisterController::class, 'verify'])->name('email.verify');

Route::get('install', [InstallController::class, 'show'])->name('install');
Route::post('install', [InstallController::class, 'store'])->name('install.store');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::delete('cards', [CardController::class, 'destroyMany'])->name('cards.destroy-many');
    Route::patch('cards', [CardController::class, 'updateMany'])->name('cards.update-many');
    Route::apiResource('cards', CardController::class);
    Route::apiResource('languages', LanguageController::class);

    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::post('import/csv', [ImportController::class, 'uploadCsv'])->name('import.csv');
    Route::post('import/anki', [ImportController::class, 'uploadAnki'])->name('import.anki');
    Route::get('import/mapping', [ImportController::class, 'mapping'])->name('import.mapping');
    Route::put('import/mapping', [ImportController::class, 'saveMapping'])->name('import.mapping.save');
    Route::get('import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('import', [ImportController::class, 'store'])->name('import.store');
    Route::delete('import', [ImportController::class, 'cancel'])->name('import.cancel');
    Route::get('export', ExportController::class)->name('export');
});

// Admins learn too, so they can fix cards they find wrong while studying.
Route::middleware(['auth', 'role:learner,admin'])->group(function () {
    Route::get('learn', [LearnController::class, 'index'])->name('learn.index');
    Route::post('learn', [LearnController::class, 'start'])->name('learn.start');
    Route::get('learn/sessions/{session}', [LearnController::class, 'show'])->name('learn.show');
    Route::post('learn/sessions/{session}/answer', [LearnController::class, 'answer'])->name('learn.answer');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
});
