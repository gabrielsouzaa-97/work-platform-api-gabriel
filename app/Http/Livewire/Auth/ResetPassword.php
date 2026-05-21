<?php

declare(strict_types=1);

namespace App\Http\Livewire\Auth;

use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    protected array $rules = [
        'token' => ['required'],
        'email' => ['required', 'email'],
        'password' => ['required', 'confirmed', 'min:8'],
    ];

    public function resetPassword(): void
    {
        $this->validate();

        $status = Password::broker('operators')->reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (Operator $operator, string $password) {
                $operator->forceFill([
                    'password_hash' => Hash::make($password),
                ])->save();

                event(new PasswordReset($operator));

                AuditLog::create([
                    'id' => Str::uuid()->toString(),
                    'actor_id' => null,
                    'action' => 'password_reset_completed',
                    'resource_type' => 'operator',
                    'resource_id' => $operator->id,
                    'payload' => [
                        'email_hash' => hash('sha256', strtolower(trim($operator->email))),
                    ],
                ]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        $this->redirect(route('login'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
