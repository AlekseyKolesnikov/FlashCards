<?php

use App\Http\Controllers\Admin\CardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Sends each user to the start page of their role.
Route::get('/', function (Request $request) {
    return redirect()->route($request->user()->isAdmin() ? 'admin.index' : 'learn.index');
})->middleware('auth')->name('home');

Route::get('/install', [InstallController::class, 'show'])->name('install');
Route::post('/install', [InstallController::class, 'store']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::redirect('/', '/admin/cards')->name('index');

    Route::delete('cards', [CardController::class, 'destroyMany'])->name('cards.destroy-many');
    Route::resource('cards', CardController::class)->except('show');
    Route::resource('languages', LanguageController::class)->except('show', 'create');

    Route::get('import', [ImportController::class, 'index'])->name('import.index');
    Route::post('import/csv', [ImportController::class, 'uploadCsv'])->name('import.csv');
    Route::post('import/anki', [ImportController::class, 'uploadAnki'])->name('import.anki');
    Route::get('import/mapping', [ImportController::class, 'mapping'])->name('import.mapping');
    Route::post('import/mapping', [ImportController::class, 'saveMapping']);
    Route::get('import/preview', [ImportController::class, 'preview'])->name('import.preview');
    Route::post('import', [ImportController::class, 'store'])->name('import.store');
    Route::delete('import', [ImportController::class, 'cancel'])->name('import.cancel');
    Route::get('export', ExportController::class)->name('export');
});

// Admins learn too, so they can fix cards they find wrong while studying.
Route::middleware(['auth', 'role:learner,admin'])->group(function () {
    Route::get('/learn', [LearnController::class, 'index'])->name('learn.index');
    Route::post('/learn', [LearnController::class, 'start'])->name('learn.start');
    Route::get('/learn/sessions/{session}', [LearnController::class, 'show'])->name('learn.show');
    Route::post('/learn/sessions/{session}/answer', [LearnController::class, 'answer'])->name('learn.answer');
    Route::get('/learn/sessions/{session}/result', [LearnController::class, 'result'])->name('learn.result');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
