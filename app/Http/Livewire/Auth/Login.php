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

    public bool $remember = false;

    protected array $rules = [
        'email' => ['required', 'email', 'max:255'],
        'password' => ['required', 'string'],
    ];

    public function login(): void
    {
        $this->validate();

        $key = 'login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        $operator = Operator::where('email', $this->email)->first();

        if (! $operator || $operator->status !== 'active' || ! Auth::attempt(
            ['email' => $this->email, 'password' => $this->password],
            $this->remember,
        )) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages([
                'email' => 'Credenciais invalidas ou conta desativada.',
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();
        $operator->update(['last_login_at' => now()]);

        $this->redirect($this->redirectByRole($operator->role), navigate: true);
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
