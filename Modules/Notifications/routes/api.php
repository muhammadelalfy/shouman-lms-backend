<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\NotificationController;
use Modules\Notifications\Http\Controllers\NotificationReadController;

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::put('me/notifications/{notification}/read', NotificationReadController::class)->name('notifications.read');
    Route::get('me/notifications', [NotificationController::class, 'index'])->name('notifications.index');
});
