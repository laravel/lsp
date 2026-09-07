<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'show'])->name('home.show');

Route::middleware('signed')->group(function () {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard');
});

Route::withoutMiddleware('web')
    ->post('gitlab/webhook', [GitLabWebhookController::class, 'store'])
    ->middleware(VerifyGitLabWebhookRequest::class)
    ->name('gitlab.webhook.store');
