<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    
    // Rutas exclusivas para SuperAdmin (role:0)
    Route::middleware('role:0')->group(function () {
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        Route::get('/users', [App\Http\Controllers\Users\UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [App\Http\Controllers\Users\UserController::class, 'create'])->name('users.create');
        Route::post('/users', [App\Http\Controllers\Users\UserController::class, 'store'])->name('users.store');
        Route::get('/users/{id}/edit', [App\Http\Controllers\Users\UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{id}', [App\Http\Controllers\Users\UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [App\Http\Controllers\Users\UserController::class, 'destroy'])->name('users.destroy');

        // Módulo de Categorías (CRUD)
        Route::get('/categories', [App\Http\Controllers\Categories\CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [App\Http\Controllers\Categories\CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [App\Http\Controllers\Categories\CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{id}/edit', [App\Http\Controllers\Categories\CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{id}', [App\Http\Controllers\Categories\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{id}', [App\Http\Controllers\Categories\CategoryController::class, 'destroy'])->name('categories.destroy');
    });

    // Rutas accesibles por SuperAdmin (0) y Editor (1)
    Route::get('/inventory', [App\Http\Controllers\Inventory\InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/create', [App\Http\Controllers\Inventory\InventoryController::class, 'create'])->name('inventory.create');
    Route::post('/inventory/store', [App\Http\Controllers\Inventory\InventoryController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{id}/edit', [App\Http\Controllers\Inventory\InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('/inventory/{id}', [App\Http\Controllers\Inventory\InventoryController::class, 'update'])->name('inventory.update');
    Route::put('/inventory/{id}/toggle-status', [App\Http\Controllers\Inventory\InventoryController::class, 'toggleStatus'])->name('inventory.toggle-status');
    Route::delete('/inventory/{id}', [App\Http\Controllers\Inventory\InventoryController::class, 'destroy'])->name('inventory.destroy');
});
