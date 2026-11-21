<?php

// Laravel renders only the landing page and the page that loads the React app
// (SPECIFICATION.md §10.2). Everything else is the JSON API in routes/api.php.

use App\Services\Vocabulary;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (Vocabulary $vocabulary) => view('landing', ['pairs' => $vocabulary->pairSizes()]))->name('landing');

// Every /app/… URL loads the React app, which picks the screen from the URL.
Route::view('/app/{path?}', 'app')->where('path', '.*')->name('app');
