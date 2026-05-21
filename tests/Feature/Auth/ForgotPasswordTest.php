<?php

declare(strict_types=1);

use App\Http\Livewire\Auth\ForgotPassword;
use App\Http\Livewire\Auth\ResetPassword;
use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    RateLimiter::clear('forgot-password:127.0.0.1|test@example.com');
});

it('sends reset link for active operator', function (): void {
    $operator = Operator::factory()->create(['status' => 'active']);

    RateLimiter::clear("forgot-password:127.0.0.1|{$operator->email}");

    Livewire::test(ForgotPassword::class)
        ->set('email', $operator->email)
        ->call('sendResetLink')
        ->assertSet('sent', true);

    Mail::assertSentCount(1);

    expect(AuditLog::where('action', 'password_reset_requested')
        ->where('resource_id', $operator->id)
        ->exists()
    )->toBeTrue();
});

it('returns generic message for unknown email (anti-enumeration)', function (): void {
    Livewire::test(ForgotPassword::class)
        ->set('email', 'nobody@nonexistent.example')
        ->call('sendResetLink')
        ->assertSet('sent', true);

    Mail::assertNothingSent();
});

it('returns generic message + logs audit blocked for inactive operator', function (): void {
    $operator = Operator::factory()->inactive()->create();

    RateLimiter::clear("forgot-password:127.0.0.1|{$operator->email}");

    Livewire::test(ForgotPassword::class)
        ->set('email', $operator->email)
        ->call('sendResetLink')
        ->assertSet('sent', true);

    Mail::assertNothingSent();

    expect(AuditLog::where('action', 'password_reset_blocked')
        ->whereJsonContains('payload->reason', 'operator_inactive')
        ->exists()
    )->toBeTrue();
});

it('rate-limits 4th request within 15 minutes', function (): void {
    $operator = Operator::factory()->create(['status' => 'active']);
    $key = "forgot-password:127.0.0.1|{$operator->email}";

    RateLimiter::clear($key);

    $component = Livewire::test(ForgotPassword::class)->set('email', $operator->email);

    // 3 allowed requests
    for ($i = 0; $i < 3; $i++) {
        $component->call('sendResetLink')->assertSet('sent', true);
        $component->set('sent', false);
    }

    // 4th request should be rate-limited
    $component->call('sendResetLink')->assertHasErrors(['email']);
});

it('accepts valid token and updates password', function (): void {
    $operator = Operator::factory()->create(['status' => 'active']);
    $token = Password::broker('operators')->createToken($operator);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $operator->email)
        ->set('password', 'NewSecurePass1!')
        ->set('password_confirmation', 'NewSecurePass1!')
        ->call('resetPassword');

    expect(Hash::check('NewSecurePass1!', $operator->fresh()->password_hash))->toBeTrue();

    expect(AuditLog::where('action', 'password_reset_completed')
        ->where('resource_id', $operator->id)
        ->exists()
    )->toBeTrue();
});

it('rejects expired token (>60 min)', function (): void {
    $operator = Operator::factory()->create(['status' => 'active']);
    $token = Password::broker('operators')->createToken($operator);

    // Simulate expiry by back-dating the token record
    DB::table('password_reset_tokens')
        ->where('email', $operator->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $operator->email)
        ->set('password', 'NewSecurePass1!')
        ->set('password_confirmation', 'NewSecurePass1!')
        ->call('resetPassword')
        ->assertHasErrors(['email']);
});

it('rejects mismatched password confirmation', function (): void {
    $operator = Operator::factory()->create(['status' => 'active']);
    $token = Password::broker('operators')->createToken($operator);

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $operator->email)
        ->set('password', 'NewSecurePass1!')
        ->set('password_confirmation', 'DifferentPass!')
        ->call('resetPassword')
        ->assertHasErrors(['password']);
});

it('password reset route requires a valid signature', function (): void {
    $operator = Operator::factory()->create(['status' => 'active']);
    $token = Password::broker('operators')->createToken($operator);

    $signedUrl = URL::temporarySignedRoute(
        'password.reset',
        now()->addMinutes(60),
        ['token' => $token, 'email' => $operator->email],
    );

    $this->get($signedUrl)->assertOk();
    $this->get(route('password.reset', ['token' => $token, 'email' => $operator->email]))->assertForbidden();
});
