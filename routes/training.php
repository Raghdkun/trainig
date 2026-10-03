<?php

use App\Http\Controllers\Training\CategoryController;
use App\Http\Controllers\Training\ChecklistItemController;
use App\Http\Controllers\Training\EvaluationController;
use App\Http\Controllers\Training\MediaController;
use App\Http\Controllers\Training\MediaUploadController;
use App\Http\Controllers\Training\QuizAttemptController;
use App\Http\Controllers\Training\QuizController;
use App\Http\Controllers\Training\QuizQuestionController;
use App\Http\Controllers\Training\QuizResultController;
use App\Http\Controllers\Training\SectionController;
use App\Http\Controllers\Training\TraineeController;
use App\Http\Controllers\Training\TraineeDevelopmentController;
use App\Http\Controllers\Training\TraineeManagerController;
use Illuminate\Support\Facades\Route;

/*
 * Content builder — standardized training program. Super admins only.
 */
Route::middleware(['auth', 'verified', 'super_admin'])
    ->prefix('training')
    ->name('training.')
    ->group(function (): void {
        // Sections
        Route::get('sections', [SectionController::class, 'index'])->name('sections.index');
        Route::post('sections', [SectionController::class, 'store'])->name('sections.store');
        Route::post('sections/reorder', [SectionController::class, 'reorder'])->name('sections.reorder');
        Route::get('sections/{section}', [SectionController::class, 'edit'])->name('sections.edit');
        Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
        Route::patch('sections/{section}/publish', [SectionController::class, 'publish'])->name('sections.publish');
        Route::patch('sections/{section}/unpublish', [SectionController::class, 'unpublish'])->name('sections.unpublish');

        // Categories
        Route::post('categories/move', [CategoryController::class, 'move'])->name('categories.move');
        Route::post('categories/bulk-destroy', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
        Route::post('sections/{section}/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::post('sections/{section}/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Checklist items (and sub-items via parent_id)
        Route::post('items/move', [ChecklistItemController::class, 'move'])->name('items.move');
        Route::post('items/bulk-destroy', [ChecklistItemController::class, 'bulkDestroy'])->name('items.bulk-destroy');
        Route::post('categories/{category}/items', [ChecklistItemController::class, 'store'])->name('items.store');
        Route::post('categories/{category}/items/reorder', [ChecklistItemController::class, 'reorder'])->name('items.reorder');
        Route::put('items/{checklistItem}', [ChecklistItemController::class, 'update'])->name('items.update');
        Route::delete('items/{checklistItem}', [ChecklistItemController::class, 'destroy'])->name('items.destroy');

        // Media
        Route::post('items/{checklistItem}/media', [MediaController::class, 'store'])->name('media.store');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        // Chunked/resumable uploads for large files (video).
        Route::post('items/{checklistItem}/media/uploads', [MediaUploadController::class, 'init'])->name('media.uploads.init');
        Route::post('media/uploads/{upload}/chunk', [MediaUploadController::class, 'chunk'])->name('media.uploads.chunk');
        Route::post('media/uploads/{upload}/complete', [MediaUploadController::class, 'complete'])->name('media.uploads.complete');
        Route::delete('media/uploads/{upload}', [MediaUploadController::class, 'cancel'])->name('media.uploads.cancel');

        // Quiz authoring
        Route::post('sections/{section}/quiz', [QuizController::class, 'store'])->name('quizzes.store');
        Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');
        Route::post('quizzes/{quiz}/questions', [QuizQuestionController::class, 'store'])->name('quiz-questions.store');
        Route::put('quiz-questions/{question}', [QuizQuestionController::class, 'update'])->name('quiz-questions.update');
        Route::delete('quiz-questions/{question}', [QuizQuestionController::class, 'destroy'])->name('quiz-questions.destroy');

        // Quiz results — training team only, never the store manager.
        Route::get('quiz-results', [QuizResultController::class, 'index'])->name('quiz-results.index');
        Route::get('quiz-results/{attempt}', [QuizResultController::class, 'show'])->name('quiz-results.show');
        Route::delete('quiz-results/{attempt}', [QuizResultController::class, 'destroy'])->name('quiz-results.destroy');
    });

/*
 * Trainees & evaluations — managers (assigned) and super admins.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('development-zone', [TraineeDevelopmentController::class, 'index'])->name('development-zone.index');
    Route::get('development-zone/{trainee}', [TraineeDevelopmentController::class, 'show'])->name('development-zone.show');

    Route::resource('trainees', TraineeController::class);

    Route::patch('trainees/{trainee}/archive', [TraineeController::class, 'archive'])->name('trainees.archive');
    Route::patch('trainees/{trainee}/restore', [TraineeController::class, 'restore'])->name('trainees.restore');

    Route::post('trainees/{trainee}/development-zone', [TraineeDevelopmentController::class, 'store'])->name('trainees.development.store');
    Route::delete('trainees/{trainee}/development-zone', [TraineeDevelopmentController::class, 'destroy'])->name('trainees.development.destroy');
    Route::put('trainees/{trainee}/development-plan', [TraineeDevelopmentController::class, 'updatePlan'])->name('trainees.development.update');
    Route::patch('trainees/{trainee}/development-complete', [TraineeDevelopmentController::class, 'complete'])->name('trainees.development.complete');

    Route::post('trainees/{trainee}/quiz-attempts', [QuizAttemptController::class, 'store'])->name('trainees.quiz-attempts.store');

    Route::put('trainees/{trainee}/managers', [TraineeManagerController::class, 'update'])
        ->name('trainees.managers.update');

    Route::put('trainees/{trainee}/checklist-items/{checklistItem}/evaluation', [EvaluationController::class, 'update'])
        ->name('trainees.evaluations.update');
});
