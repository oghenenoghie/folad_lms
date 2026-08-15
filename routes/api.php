<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function () {
    return request()->user();
});
