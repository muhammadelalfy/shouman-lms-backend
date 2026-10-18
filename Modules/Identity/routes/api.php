<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\Admin\RoleController;
use Modules\Identity\Http\Controllers\Admin\RolePermissionsController;
use Modules\Identity\Http\Controllers\Admin\UserController;
use Modules\Identity\Http\Controllers\Admin\UserRolesController;
use Modules\Identity\Http\Controllers\Admin\UserStatusController;
use Modules\Identity\Http\Controllers\Auth\AccessTokenController;
use Modules\Identity\Http\Controllers\Auth\NewPasswordController;
use Modules\Identity\Http\Controllers\Auth\PasswordResetLinkController;
use Modules\Identity\Http\Controllers\Auth\RegisteredUserController;
use Modules\Identity\Http\Controllers\PasswordController;
use Modules\Identity\Http\Controllers\ProfileController;

/*
 * Routes are registered under the /api/v1 prefix with the "api.v1." name prefix.
 */

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:identity.register')->name('register');
    Route::post('login', [AccessTokenController::class, 'store'])
        ->middleware('throttle:identity.login')->name('login');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:identity.password')->name('password.email');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:identity.password')->name('password.reset');

    Route::post('logout', [AccessTokenController::class, 'destroy'])
        ->middleware(['auth:sanctum', 'active'])->name('logout');
});

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('me', [ProfileController::class, 'show'])->name('me.show');
    Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
    Route::put('me/password', [PasswordController::class, 'update'])->name('me.password.update');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('users/{user}/status', [UserStatusController::class, 'update'])->name('users.status.update');
        Route::put('users/{user}/roles', [UserRolesController::class, 'update'])->name('users.roles.update');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role:name}/permissions', [RolePermissionsController::class, 'update'])->name('roles.permissions.update');
    });
});
