<?php

use App\Http\Livewire\Audit\Index as AuditIndex;
use App\Http\Livewire\Auth\AcceptInvite;
use App\Http\Livewire\Auth\Login;
use App\Http\Livewire\ClusterServers\Create as ClusterCreate;
use App\Http\Livewire\ClusterServers\Edit as ClusterEdit;
use App\Http\Livewire\ClusterServers\Index as ClusterIndex;
use App\Http\Livewire\Customers\Create as CustomerCreate;
use App\Http\Livewire\Customers\Index as CustomerIndex;
use App\Http\Livewire\Customers\Show as CustomerShow;
use App\Http\Livewire\Jobs\Index as JobsIndex;
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

Route::middleware(['auth', 'active.operator'])->group(function () {
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
    })->middleware('can:manage-operators')->name('admin.dashboard');

    Route::get('/audit', AuditIndex::class)
        ->middleware('can:manage-operators')
        ->name('audit.index');

    Route::middleware('can:manage-cluster-servers')->group(function () {
        Route::get('/cluster-servers', ClusterIndex::class)->name('cluster-servers.index');
        Route::get('/cluster-servers/create', ClusterCreate::class)->name('cluster-servers.create');
        Route::get('/cluster-servers/{clusterServer}/edit', ClusterEdit::class)->name('cluster-servers.edit');
    });

    Route::get('/queue', JobsIndex::class)->name('queue.index');

    Route::get('/customers', CustomerIndex::class)->name('customers.index');

    Route::get('/customers/create', CustomerCreate::class)
        ->middleware('can:provision-customers')
        ->name('customers.create');

    Route::get('/customers/{slug}', CustomerShow::class)->name('customers.show');
});

Route::get('/operators/{operator}/accept-invite', AcceptInvite::class)
    ->middleware('signed')
    ->name('operators.accept-invite');
