<?php

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
Route::post('/admin/profile', [AdminController::class, 'profile'])->name('admin.profile');
Route::post('/admin/reset', [AdminController::class, 'reset'])->name('admin.reset');
Route::post('/admin/backups', [AdminController::class, 'backup'])->name('admin.backups.store');
Route::get('/admin/backups/{backup}', [AdminController::class, 'downloadBackup'])->whereUuid('backup')->name('admin.backups.download');
Route::post('/admin/restore', [AdminController::class, 'restore'])->name('admin.restore');
