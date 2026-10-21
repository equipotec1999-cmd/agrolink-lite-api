<?php

use Illuminate\Support\Facades\Route;

// Lite: la API no sirve páginas; la raíz solo responde quién es (útil para comprobar que está viva).
Route::get('/', fn () => response()->json(['name' => config('app.name', 'AgroLink'), 'edition' => 'lite']));
