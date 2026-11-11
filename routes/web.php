<?php

// Laravel renders only the landing page and the page that loads the React app
// (SPECIFICATION.md §10.2). Everything else is the JSON API in routes/api.php.

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('landing');

// Every /app/… URL loads the React app, which picks the screen from the URL.
Route::view('/app/{path?}', 'app')->where('path', '.*')->name('app');
