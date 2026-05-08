<?php

use App\Http\Livewire\Auth\AcceptInvite;
use App\Http\Livewire\Auth\Login;
use App\Http\Livewire\Operators\Create;
use App\Http\Livewire\Operators\Index;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect('/login')->with('status', 'Sessao encerrada');
    })->name('logout');

    Route::get('/operators', Index::class)
        ->middleware('can:manage-operators')
        ->name('operators.index');

    Route::get('/operators/create', Create::class)
        ->middleware('can:manage-operators')
        ->name('operators.create');

    Route::get('/admin/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::get('/customers', function () {
        return view('customers.index');
    })->name('customers.index');
});

Route::get('/operators/{operator}/accept-invite', AcceptInvite::class)
    ->middleware('signed')
    ->name('operators.accept-invite');
