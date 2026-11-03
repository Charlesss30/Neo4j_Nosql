<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
Route::post('/admin/profile', [AdminController::class, 'profile'])->name('admin.profile');
Route::post('/admin/reset', [AdminController::class, 'reset'])->name('admin.reset');
