<?php

/*
 * FALLBACK front controller — only if you CANNOT point the domain's document
 * root at the app's /public folder.
 *
 * Layout on the server:
 *   ~/shaheen-app/          ← the whole Laravel app (outside public_html)
 *   ~/public_html/          ← contents of shaheen-app/public/ + THIS index.php
 *
 * Copy this file over public_html/index.php after copying public/*.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$app = __DIR__.'/../shaheen-app';

if (file_exists($maintenance = $app.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app.'/vendor/autoload.php';

/** @var Application $application */
$application = require_once $app.'/bootstrap/app.php';

// Tell Laravel that public_html is the public folder (for asset() and storage links).
$application->usePublicPath(__DIR__);

$application->handleRequest(Request::capture());
