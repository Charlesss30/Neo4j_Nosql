<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ConsoleController;
use Illuminate\Support\Facades\Route;

Route::get('/console', [ConsoleController::class, 'index'])->name('console.index');
Route::post('/console/run', [ConsoleController::class, 'run'])->name('console.run');
Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
Route::post('/admin/profile', [AdminController::class, 'profile'])->name('admin.profile');
Route::post('/admin/reset', [AdminController::class, 'reset'])->name('admin.reset');
