<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingFeatureController;
use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/persons', [PersonController::class, 'index'])->name('persons.index');
Route::get('/persons/create', [PersonController::class, 'create'])->name('persons.create');
Route::post('/persons', [PersonController::class, 'store'])->name('persons.store');
Route::get('/persons/{id}/edit', [PersonController::class, 'edit'])->name('persons.edit');
Route::get('/persons/{id}/delete', [PersonController::class, 'confirmDelete'])->name('persons.confirm-delete');
Route::get('/persons/{id}', [PersonController::class, 'show'])->name('persons.show');
Route::match(['put', 'patch'], '/persons/{id}', [PersonController::class, 'update'])->name('persons.update');
Route::delete('/persons/{id}', [PersonController::class, 'destroy'])->name('persons.destroy');

Route::get('/relationships', PendingFeatureController::class)->name('relationships.index');
Route::post('/relationships/parent', PendingFeatureController::class)->name('relationships.parent.store');
Route::delete('/relationships/parent', PendingFeatureController::class)->name('relationships.parent.destroy');
Route::post('/relationships/spouse', PendingFeatureController::class)->name('relationships.spouse.store');
Route::delete('/relationships/spouse', PendingFeatureController::class)->name('relationships.spouse.destroy');

Route::get('/tree', PendingFeatureController::class)->name('tree.index');
Route::get('/api/tree', PendingFeatureController::class)->name('tree.data');
Route::get('/relation', PendingFeatureController::class)->name('relation.index');

if (file_exists(__DIR__.'/admin_console.php')) {
    require __DIR__.'/admin_console.php';
}
