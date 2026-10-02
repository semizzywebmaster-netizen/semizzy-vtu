<?php

use Illuminate\\Support\\Facades\\Route;
use Inertia\\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'appName' => config('app.name', 'SEMIZZY ONE'),
]))->name('home');

Route::get('/admin/login', fn () => Inertia::render('Auth/AdminLogin'))->name('admin.login');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
});
