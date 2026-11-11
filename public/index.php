<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// When the whole project is uploaded into a web folder, its .htaccess forwards requests into
// public/. Make Laravel treat that folder, not public/, as the app's base URL (SPECIFICATION.md §10.5).
$script = $_SERVER['SCRIPT_NAME'] ?? '';
if (str_ends_with($script, '/public/index.php')) {
    $folder = substr($script, 0, -strlen('/public/index.php'));

    if (! str_starts_with($_SERVER['REQUEST_URI'] ?? '', $folder.'/public/')) {
        $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $folder.'/index.php';
    }
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
