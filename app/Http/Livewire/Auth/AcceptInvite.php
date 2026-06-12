<?php

declare(strict_types=1);

namespace App\Http\Livewire\Auth;

use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptInvite extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public ?Operator $operator = null;

    #[Locked]
    public string $token = '';

    public function mount(Operator $operator): void
    {
        $this->token = (string) request()->query('token', '');

        if (! $this->inviteIsValid($operator, $this->token)) {
            abort(403, 'Este convite ja foi utilizado ou a conta ja esta ativa.');
        }

        $this->operator = $operator;
    }

    protected function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }

    public function acceptInvite(): void
    {
        $this->validate();

        $operator = DB::transaction(function (): Operator {
            $operator = Operator::query()
                ->whereKey($this->operator?->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->inviteIsValid($operator, $this->token)) {
                throw ValidationException::withMessages([
                    'password' => 'Convite expirado ou ja utilizado. Solicite um novo convite ao administrador.',
                ]);
            }

            $operator->password_hash = bcrypt($this->password);
            $operator->status = 'active';
            $operator->invite_token_hash = null;
            $operator->invite_expires_at = null;
            $operator->save();

            return $operator;
        });

        Auth::login($operator);
        session()->regenerate();

        $this->redirect(match ($operator->role) {
            'admin' => '/admin/dashboard',
            default => '/customers',
        }, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.accept-invite');
    }

    private function inviteIsValid(Operator $operator, string $token): bool
    {
        return $operator->status === 'pending'
            && $operator->invite_token_hash !== null
            && $operator->invite_expires_at !== null
            && $operator->invite_expires_at->isFuture()
            && $token !== ''
            && Hash::check($token, $operator->invite_token_hash);
    }
}
