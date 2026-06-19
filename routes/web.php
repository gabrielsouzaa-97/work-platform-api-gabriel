<?php

use App\Http\Livewire\ApiKeys\Index as ApiKeysIndex;
use App\Http\Livewire\Audit\Index as AuditIndex;
use App\Http\Livewire\Auth\AcceptInvite;
use App\Http\Livewire\Auth\ForgotPassword;
use App\Http\Livewire\Auth\Login;
use App\Http\Livewire\Auth\ResetPassword;
use App\Http\Livewire\ClusterServers\Create as ClusterCreate;
use App\Http\Livewire\ClusterServers\Edit as ClusterEdit;
use App\Http\Livewire\ClusterServers\Index as ClusterIndex;
use App\Http\Livewire\Customers\Create as CustomerCreate;
use App\Http\Livewire\Customers\Index as CustomerIndex;
use App\Http\Livewire\Customers\OccPanel as CustomerOccPanel;
use App\Http\Livewire\Customers\Show as CustomerShow;
use App\Http\Livewire\Farms\Index as FarmsIndex;
use App\Http\Livewire\Jobs\Index as JobsIndex;
use App\Http\Livewire\Jobs\Show as JobsShow;
use App\Http\Livewire\Operators\Create;
use App\Http\Livewire\Operators\Edit as OperatorsEdit;
use App\Http\Livewire\Operators\Index;
use App\Http\Livewire\Profile\ChangePassword;
use App\Http\Livewire\Settings\WebhookIpAllowlist;
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
    Route::get('/forgot-password', ForgotPassword::class)->name('password.request');
    Route::get('/reset-password/{token}', ResetPassword::class)->middleware('signed')->name('password.reset');
});

Route::middleware(['auth', 'active.operator'])->group(function () {
    Route::get('/profile/password', ChangePassword::class)->name('profile.password');

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

    Route::get('/operators/{operatorId}/edit', OperatorsEdit::class)
        ->middleware('can:manage-operators')
        ->name('operators.edit');

    // ===== Dashboard =====
    Route::get('/dashboard', function () {
        $now = now();

        // Chart: jobs per day for the last 7 days (success + failed)
        $chartDays = collect(range(6, 0))->map(fn ($d) => $now->copy()->subDays($d)->startOfDay());

        $jobsRaw = Job::whereIn('state', ['success', 'failed'])
            ->where('finished_at', '>=', $now->copy()->subDays(6)->startOfDay())
            ->selectRaw('DATE(finished_at) as day, state, COUNT(*) as total')
            ->groupBy('day', 'state')
            ->get()
            ->groupBy('day');

        $chartLabels = $chartDays->map(fn ($d) => $d->format('d/m'))->values();
        $chartSuccess = $chartDays->map(fn ($d) => (int) ($jobsRaw->get($d->format('Y-m-d'))?->firstWhere('state', 'success')?->total ?? 0))->values();
        $chartFailed = $chartDays->map(fn ($d) => (int) ($jobsRaw->get($d->format('Y-m-d'))?->firstWhere('state', 'failed')?->total ?? 0))->values();

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
            'chartLabels' => $chartLabels,
            'chartSuccess' => $chartSuccess,
            'chartFailed' => $chartFailed,
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

    Route::get('/settings/webhook-ip', WebhookIpAllowlist::class)
        ->middleware('can:manage-cluster-servers')
        ->name('settings.webhook-ip');

    Route::middleware('can:manage-cluster-servers')->group(function () {
        Route::get('/cluster-servers', ClusterIndex::class)->name('cluster-servers.index');
        Route::get('/cluster-servers/create', ClusterCreate::class)->name('cluster-servers.create');
        Route::get('/cluster-servers/{clusterServer}/edit', ClusterEdit::class)->name('cluster-servers.edit');
    });

    // ===== Logs de Provisionamento (Jobs queue) =====
    Route::get('/queue', JobsIndex::class)->name('queue.index');
    Route::get('/queue/{jobId}', JobsShow::class)->name('queue.show');

    Route::get('/customers', CustomerIndex::class)->name('customers.index');

    Route::get('/customers/create', CustomerCreate::class)
        ->middleware('can:provision-customers')
        ->name('customers.create');

    Route::get('/customers/{slug}', CustomerShow::class)->name('customers.show');
    Route::get('/customers/{slug}/occ', CustomerOccPanel::class)->name('customers.occ');

    Route::get('/farms', FarmsIndex::class)
        ->middleware('can:manage-operators')
        ->name('farms.index');
});

Route::get('/operators/{operator}/accept-invite', AcceptInvite::class)
    ->middleware('signed')
    ->name('operators.accept-invite');
