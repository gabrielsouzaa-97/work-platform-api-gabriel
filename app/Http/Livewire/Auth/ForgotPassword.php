<?php

declare(strict_types=1);

namespace App\Http\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    protected array $rules = [
        'email' => ['required', 'email', 'max:255'],
    ];

    public function sendResetLink(): void
    {
        $this->validate();

        $key = 'forgot-password:'.request()->ip().'|'.strtolower(trim($this->email));

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        RateLimiter::hit($key, 900); // 15 min window

        $operator = Operator::where('email', $this->email)->first();

        if ($operator && $operator->status !== 'active') {
            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => null,
                'action' => 'password_reset_blocked',
                'resource_type' => 'operator',
                'resource_id' => $operator->id,
                'payload' => [
                    'email_hash' => hash('sha256', strtolower(trim($this->email))),
                    'reason' => 'operator_inactive',
                ],
            ]);

            $this->sent = true;

            return;
        }

        if ($operator) {
            Password::broker('operators')->sendResetLink(
                ['email' => $this->email],
            );

            AuditLog::create([
                'id' => Str::uuid()->toString(),
                'actor_id' => null,
                'action' => 'password_reset_requested',
                'resource_type' => 'operator',
                'resource_id' => $operator->id,
                'payload' => [
                    'email_hash' => hash('sha256', strtolower(trim($this->email))),
                ],
            ]);
        }

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
