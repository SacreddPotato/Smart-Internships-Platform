<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;

Route::fallback(function () {
    $viteTags = Vite::useBuildDirectory('build')([
        'resources/css/app.css',
        'resources/js/main.jsx',
    ]);

    return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Smart Internship Matching Platform</title>
        {$viteTags}
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
HTML);
});
