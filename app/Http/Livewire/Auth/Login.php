<?php

declare(strict_types=1);

namespace App\Http\Livewire\Auth;

use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    protected array $rules = [
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'string'],
    ];

    public function login(): void
    {
        $this->validate();

        $ipKey = 'login:'.request()->ip();
        $emailKey = 'login_email:'.$this->email;

        if (RateLimiter::tooManyAttempts($ipKey, 5) || RateLimiter::tooManyAttempts($emailKey, 5)) {
            $seconds = max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($emailKey));
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        $operator = Operator::where('email', $this->email)->first();

        if (! $operator || $operator->status !== 'active' || ! Auth::attempt(
            ['email' => $this->email, 'password' => $this->password],
        )) {
            RateLimiter::hit($ipKey, 900);
            RateLimiter::hit($emailKey, 300);
            throw ValidationException::withMessages([
                'email' => 'Credenciais invalidas ou conta desativada.',
            ]);
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($emailKey);
        session()->regenerate();
        $operator->update(['last_login_at' => now()]);

        $this->redirect($this->redirectByRole($operator->role), navigate: false);
    }

    private function redirectByRole(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/dashboard',
            default => '/customers',
        };
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
