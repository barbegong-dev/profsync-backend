<?php

use Illuminate\Support\Facades\Route;

// The UI is the Next.js app; the API root just reports that it is running
Route::get('/', function () {
    return response()->json(['name' => 'ProfSync API', 'status' => 'ok']);
});
