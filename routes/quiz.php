<?php

use App\Http\Controllers\PublicQuizController;
use Illuminate\Support\Facades\Route;

/*
 * The employee-facing quiz — reached via an unguessable token link shared
 * manually by a manager. Deliberately outside the `auth` middleware: the
 * employee has no account in this system.
 */
Route::get('quiz/{token}', [PublicQuizController::class, 'show'])->name('quiz.show');
Route::post('quiz/{token}', [PublicQuizController::class, 'store'])->name('quiz.store');
Route::post('quiz/{token}/report-mismatch', [PublicQuizController::class, 'reportMismatch'])->name('quiz.report-mismatch');
