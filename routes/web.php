<?php

use App\Http\Livewire\ApiKeys\Index as ApiKeysIndex;
use App\Http\Livewire\Audit\Index as AuditIndex;
use App\Http\Livewire\Auth\AcceptInvite;
use App\Http\Livewire\Auth\Login;
use App\Http\Livewire\ClusterServers\Create as ClusterCreate;
use App\Http\Livewire\ClusterServers\Edit as ClusterEdit;
use App\Http\Livewire\ClusterServers\Index as ClusterIndex;
use App\Http\Livewire\Customers\Create as CustomerCreate;
use App\Http\Livewire\Customers\Index as CustomerIndex;
use App\Http\Livewire\Customers\OccPanel as CustomerOccPanel;
use App\Http\Livewire\Customers\Show as CustomerShow;
use App\Http\Livewire\Jobs\Index as JobsIndex;
use App\Http\Livewire\Operators\Create;
use App\Http\Livewire\Operators\Index;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\ClusterServer;
use App\Models\Job;
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

    // ===== Dashboard =====
    Route::get('/dashboard', function () {
        $now = now();

        return view('admin.dashboard', [
            'recentActivity' => AuditLog::with('actor')->orderByDesc('created_at')->take(8)->get(),
            'queueStats' => [
                'queued' => Job::where('state', 'queued')->count(),
                'running' => Job::where('state', 'running')->count(),
                'success_24h' => Job::where('state', 'success')->where('finished_at', '>=', $now->copy()->subDay())->count(),
                'failed_24h' => Job::where('state', 'failed')->where('finished_at', '>=', $now->copy()->subDay())->count(),
            ],
            'kpis' => [
                'active_keys' => ApiKey::whereNull('revoked_at')->count(),
                'revoked_keys' => ApiKey::whereNotNull('revoked_at')->count(),
                'audit_today' => AuditLog::where('created_at', '>=', $now->copy()->startOfDay())->count(),
                'clusters_total' => ClusterServer::count(),
                'clusters_active' => ClusterServer::where('status', 'active')->count(),
            ],
        ]);
    })->middleware('can:manage-operators')->name('dashboard');

    // Legacy redirect — inherits auth+active.operator from parent group; also gates on manage-operators
    Route::get('/admin/dashboard', fn () => redirect()->route('dashboard'))
        ->middleware('can:manage-operators')
        ->name('admin.dashboard');

    // ===== Credenciais (API Keys) =====
    Route::get('/api-keys', ApiKeysIndex::class)
        ->middleware('can:manage-operators')
        ->name('api-keys.index');

    // ===== Logs de Requisição (Audit) =====
    Route::get('/audit', AuditIndex::class)
        ->middleware('can:manage-operators')
        ->name('audit.index');

    // ===== Configurações (Cluster Servers) alias =====
    Route::get('/settings', fn () => redirect()->route('cluster-servers.index'))
        ->middleware('can:manage-cluster-servers')
        ->name('settings.index');

    Route::middleware('can:manage-cluster-servers')->group(function () {
        Route::get('/cluster-servers', ClusterIndex::class)->name('cluster-servers.index');
        Route::get('/cluster-servers/create', ClusterCreate::class)->name('cluster-servers.create');
        Route::get('/cluster-servers/{clusterServer}/edit', ClusterEdit::class)->name('cluster-servers.edit');
    });

    // ===== Logs de Provisionamento (Jobs queue) =====
    Route::get('/queue', JobsIndex::class)->name('queue.index');

    Route::get('/customers', CustomerIndex::class)->name('customers.index');

    Route::get('/customers/create', CustomerCreate::class)
        ->middleware('can:provision-customers')
        ->name('customers.create');

    Route::get('/customers/{slug}', CustomerShow::class)->name('customers.show');
    Route::get('/customers/{slug}/occ', CustomerOccPanel::class)->name('customers.occ');
});

Route::get('/operators/{operator}/accept-invite', AcceptInvite::class)
    ->middleware('signed')
    ->name('operators.accept-invite');
