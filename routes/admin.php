<?php

use App\Http\Controllers\Admin\DevelopmentEvaluationCriterionController;
use App\Http\Controllers\Admin\ManagementController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Organization administration — users & stores. Super admins only.
 */
Route::middleware(['auth', 'verified', 'super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('management', [ManagementController::class, 'index'])->name('management');

        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::post('stores', [StoreController::class, 'store'])->name('stores.store');
        Route::put('stores/{store}', [StoreController::class, 'update'])->name('stores.update');
        Route::delete('stores/{store}', [StoreController::class, 'destroy'])->name('stores.destroy');

        Route::post('development-criteria', [DevelopmentEvaluationCriterionController::class, 'store'])->name('development-criteria.store');
        Route::patch('development-criteria/{criterion}', [DevelopmentEvaluationCriterionController::class, 'update'])->name('development-criteria.update');
        Route::delete('development-criteria/{criterion}', [DevelopmentEvaluationCriterionController::class, 'destroy'])->name('development-criteria.destroy');
    });
