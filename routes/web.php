<?php

use Illuminate\Support\Facades\Route;
use NepalPay\Gateway\Http\Controllers\AdminController;
use NepalPay\Gateway\Http\Controllers\PaymentController;
use NepalPay\Gateway\Http\Middleware\EnsureAdminAuthenticated;

$prefix = config('nepal-payment.route_prefix', 'payment');
$adminPrefix = config('nepal-payment.admin.route_prefix', 'nepal-payment/admin');

Route::middleware('web')->prefix($prefix)->name('nepal-payment.')->group(function () {
    Route::match(['get', 'post'], '/{gateway}/initiate', [PaymentController::class, 'initiate'])
        ->name('initiate');

    Route::match(['get', 'post'], '/{gateway}/success', [PaymentController::class, 'success'])
        ->name('success');

    Route::match(['get', 'post'], '/{gateway}/failure', [PaymentController::class, 'failure'])
        ->name('failure');
    Route::get('/{gateway}/status', [PaymentController::class, 'status'])
        ->name('status');
});

Route::middleware('web')->prefix($adminPrefix)->name('nepal-payment.admin.')->group(function () {
    Route::get('/login', [AdminController::class, 'showLogin'])->name('login');
Route::post('/login', [AdminController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.submit');    Route::post('/logout', [AdminController::class, 'logout'])->name('logout');

    Route::middleware(EnsureAdminAuthenticated::class)->group(function () {
        Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::post('/gateway/{gateway}/toggle', [AdminController::class, 'toggle'])->name('toggle');
        Route::get('/gateway/{gateway}/edit', [AdminController::class, 'editCredentials'])->name('edit');
        Route::post('/gateway/{gateway}/update', [AdminController::class, 'updateCredentials'])->name('update');
    });
});