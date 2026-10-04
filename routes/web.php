<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\StreamerController;
use Illuminate\Support\Facades\Route;

// Публичные страницы
Route::get('/', [ConnectController::class, 'home'])->name('home');
Route::view('/how-it-works', 'pages.how')->name('how');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/terms', 'pages.terms')->name('terms');

// Авторизация через DonationAlerts
Route::middleware('throttle:20,1')->prefix('da')->group(function () {
    Route::get('connect', [ConnectController::class, 'redirect'])->name('da.connect');
    Route::get('callback', [ConnectController::class, 'callback'])->name('da.callback');
});

// Личный кабинет стримера
Route::controller(StreamerController::class)->prefix('me')->group(function () {
    Route::get('/', 'show')->name('me');
    Route::post('regenerate', 'regenerate')->name('me.regenerate');
    Route::post('logout', 'logout')->name('me.logout');
    Route::delete('/', 'destroy')->name('me.destroy');
    Route::post('test-donation', 'testDonation')->middleware('throttle:6,1')->name('me.test');
});

// Админка
Route::prefix('admin')->group(function () {
    Route::get('login', [AdminController::class, 'loginForm'])->name('admin.login');
    Route::post('login', [AdminController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('admin.index');
        Route::post('logout', [AdminController::class, 'logout'])->name('admin.logout');
        Route::patch('streamers/{streamer}/toggle', [AdminController::class, 'toggle'])->name('admin.toggle');
        Route::post('streamers/{streamer}/regenerate', [AdminController::class, 'regenerate'])->name('admin.regenerate');
        Route::delete('streamers/{streamer}', [AdminController::class, 'destroy'])->name('admin.destroy');
    });
});
