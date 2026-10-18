<?php

use Illuminate\Support\Facades\Route;
use Modules\Course\Http\Controllers\Admin\LessonController;
use Modules\Course\Http\Controllers\Admin\LessonOrderController;
use Modules\Course\Http\Controllers\Admin\LessonStatusController;
use Modules\Course\Http\Controllers\Admin\OfferingController;
use Modules\Course\Http\Controllers\Admin\OfferingInstructorsController;
use Modules\Course\Http\Controllers\Admin\OfferingStatusController;
use Modules\Course\Http\Controllers\Admin\SubjectController;
use Modules\Course\Http\Controllers\Admin\SubjectOrderController;
use Modules\Course\Http\Controllers\Admin\SubjectStatusController;
use Modules\Course\Http\Controllers\Catalog\CatalogOfferingController;

/*
 * Routes are registered under the /api/v1 prefix with the "api.v1." name prefix.
 */

Route::prefix('catalog')->name('catalog.')->middleware('throttle:catalog')->group(function () {
    Route::get('offerings', [CatalogOfferingController::class, 'index'])->name('offerings.index');
    Route::get('offerings/{slug}', [CatalogOfferingController::class, 'show'])->name('offerings.show');
});

Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('offerings', [OfferingController::class, 'index'])->name('offerings.index');
    Route::post('offerings', [OfferingController::class, 'store'])->name('offerings.store');
    Route::get('offerings/{offering}', [OfferingController::class, 'show'])->name('offerings.show');
    Route::put('offerings/{offering}', [OfferingController::class, 'update'])->name('offerings.update');
    Route::delete('offerings/{offering}', [OfferingController::class, 'destroy'])->name('offerings.destroy');
    Route::put('offerings/{offering}/status', [OfferingStatusController::class, 'update'])->name('offerings.status.update');
    Route::put('offerings/{offering}/instructors', [OfferingInstructorsController::class, 'update'])->name('offerings.instructors.update');

    Route::post('offerings/{offering}/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('offerings/{offering}/subjects/order', [SubjectOrderController::class, 'update'])->name('subjects.order.update');
    Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
    Route::put('subjects/{subject}/status', [SubjectStatusController::class, 'update'])->name('subjects.status.update');

    Route::post('subjects/{subject}/lessons', [LessonController::class, 'store'])->name('lessons.store');
    Route::put('subjects/{subject}/lessons/order', [LessonOrderController::class, 'update'])->name('lessons.order.update');
    Route::put('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');
    Route::put('lessons/{lesson}/status', [LessonStatusController::class, 'update'])->name('lessons.status.update');
});
