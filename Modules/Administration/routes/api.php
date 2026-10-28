<?php

use Illuminate\Support\Facades\Route;
use Modules\Administration\Http\Controllers\Admin\AuditLogController;
use Modules\Administration\Http\Controllers\Admin\CsvController;
use Modules\Administration\Http\Controllers\Admin\HomepageFaqController;
use Modules\Administration\Http\Controllers\Admin\HomepageNeedsController;
use Modules\Administration\Http\Controllers\Admin\HomepageSeoController;

Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('csv/{resource}/export', [CsvController::class, 'export'])->middleware('throttle:20,1,csv-export');
    Route::get('csv/{resource}/template', [CsvController::class, 'template'])->middleware('throttle:60,1,csv-template');
    Route::post('csv/{resource}/import', [CsvController::class, 'import'])->middleware('throttle:10,1,csv-import');
    Route::get('homepage/needs', [HomepageNeedsController::class, 'show']);
    Route::put('homepage/needs', [HomepageNeedsController::class, 'update']);
    Route::post('homepage/needs/publish', [HomepageNeedsController::class, 'publish']);
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});

Route::get('homepage/needs', [HomepageNeedsController::class, 'published'])->middleware('throttle:120,1');

Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('homepage/faq', [HomepageFaqController::class, 'show']);
    Route::put('homepage/faq', [HomepageFaqController::class, 'update']);
    Route::post('homepage/faq/publish', [HomepageFaqController::class, 'publish']);
});

Route::get('homepage/faq', [HomepageFaqController::class, 'published'])->middleware('throttle:120,1');

Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('homepage/seo', [HomepageSeoController::class, 'show']);
    Route::put('homepage/seo', [HomepageSeoController::class, 'update']);
    Route::post('homepage/seo/publish', [HomepageSeoController::class, 'publish']);
});
Route::get('homepage/seo', [HomepageSeoController::class, 'published'])->middleware('throttle:120,1');
