<?php

declare(strict_types=1);

use App\Http\Livewire\Auth\Login;
use App\Models\Operator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    RateLimiter::clear('login:127.0.0.1');
    RateLimiter::clear('login_email:lockout@test.local');
    RateLimiter::clear('login_email:email-lock@test.local');
});

it('redirects guest to login when accessing root', function () {
    get('/')->assertRedirect('/login');
});

it('renders login page', function () {
    Livewire::test(Login::class)->assertOk();
});

it('renders login page with resolvable livewire component alias', function () {
    get('/login')
        ->assertOk()
        ->assertSee('&quot;name&quot;:&quot;auth.login&quot;', false)
        ->assertDontSee('app.http.livewire.auth.login');
});

it('admin can login and is redirected to admin dashboard', function () {
    $admin = Operator::factory()->create([
        'email' => 'admin@test.local',
        'password_hash' => Hash::make('securepassword123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'admin@test.local')
        ->set('password', 'securepassword123')
        ->call('login')
        ->assertRedirect('/admin/dashboard');
});

it('suporte can login and is redirected to customers', function () {
    Operator::factory()->create([
        'email' => 'suporte@test.local',
        'password_hash' => Hash::make('securepassword123'),
        'role' => 'suporte',
        'status' => 'active',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'suporte@test.local')
        ->set('password', 'securepassword123')
        ->call('login')
        ->assertRedirect('/customers');
});

it('admin dashboard is forbidden for non admin operators', function () {
    $support = Operator::factory()->create([
        'role' => 'suporte',
        'status' => 'active',
    ]);

    actingAs($support)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('customers create route is forbidden for suporte', function () {
    $support = Operator::factory()->create([
        'role' => 'suporte',
        'status' => 'active',
    ]);

    actingAs($support)
        ->get(route('customers.create'))
        ->assertForbidden();
});

it('customers destroy route is forbidden for suporte (D3-F009)', function () {
    $support = Operator::factory()->create([
        'role' => 'suporte',
        'status' => 'active',
    ]);

    actingAs($support)
        ->deleteJson(route('api.customers.destroy', 'acme-rm-gate'), [
            'confirm_slug' => 'acme-rm-gate',
            'backup_first' => true,
        ])
        ->assertForbidden();
});

it('inactive authenticated operator is logged out and blocked', function () {
    $operator = Operator::factory()->create([
        'role' => 'operador',
        'status' => 'inactive',
    ]);

    actingAs($operator)
        ->get(route('customers.index'))
        ->assertForbidden();

    $this->assertGuest();
});

it('shows generic error for wrong password', function () {
    Operator::factory()->create([
        'email' => 'op@test.local',
        'password_hash' => Hash::make('correctpassword123'),
        'role' => 'operador',
        'status' => 'active',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'op@test.local')
        ->set('password', 'wrongpassword123')
        ->call('login')
        ->assertHasErrors(['email']);
});

it('blocks login after 5 failed attempts for 15 minutes', function () {
    Operator::factory()->create([
        'email' => 'lockout@test.local',
        'password_hash' => Hash::make('correctpassword123'),
        'role' => 'operador',
        'status' => 'active',
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', 'lockout@test.local')
        ->set('password', 'wrongpassword123');

    for ($i = 0; $i < 5; $i++) {
        $component->call('login');
    }

    $component
        ->set('password', 'correctpassword123')
        ->call('login')
        ->assertHasErrors(['email']);

    $errors = $component->errors();
    expect($errors->first('email'))->toContain('Muitas tentativas');
});

it('blocks login after 5 failed attempts for same email on 6th attempt', function () {
    Operator::factory()->create([
        'email' => 'email-lock@test.local',
        'password_hash' => Hash::make('correctpassword123'),
        'role' => 'operador',
        'status' => 'active',
    ]);

    $component = Livewire::test(Login::class)
        ->set('email', 'email-lock@test.local')
        ->set('password', 'wrongpassword123');

    for ($i = 0; $i < 5; $i++) {
        $component->call('login');
    }

    $component
        ->set('password', 'correctpassword123')
        ->call('login')
        ->assertHasErrors(['email']);

    expect($component->errors()->first('email'))->toContain('Muitas tentativas');
});

it('shows disabled message for inactive operator', function () {
    Operator::factory()->create([
        'email' => 'inactive@test.local',
        'password_hash' => Hash::make('securepassword123'),
        'role' => 'operador',
        'status' => 'inactive',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'inactive@test.local')
        ->set('password', 'securepassword123')
        ->call('login')
        ->assertHasErrors(['email']);
});

it('pending operator cannot login', function () {
    Operator::factory()->create([
        'email' => 'pending@test.local',
        'password_hash' => Hash::make('securepassword123'),
        'role' => 'operador',
        'status' => 'pending',
    ]);

    Livewire::test(Login::class)
        ->set('email', 'pending@test.local')
        ->set('password', 'securepassword123')
        ->call('login')
        ->assertHasErrors(['email']);
});

it('logout invalidates session', function () {
    $admin = Operator::factory()->create([
        'password_hash' => Hash::make('securepassword123'),
        'role' => 'admin',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->withSession(['_token' => 'test-token'])
        ->post(route('logout'), ['_token' => 'test-token'])
        ->assertRedirect('/login');

    $this->assertGuest();
});
