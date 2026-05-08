<?php

declare(strict_types=1);

namespace App\Http\Livewire\Auth;

use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptInvite extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public ?Operator $operator = null;

    public function mount(Operator $operator): void
    {
        if ($operator->status !== 'pending') {
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

        $this->operator->update([
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        Auth::login($this->operator);
        session()->regenerate();

        $this->redirect(match ($this->operator->role) {
            'admin' => '/admin/dashboard',
            default => '/customers',
        }, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.accept-invite');
    }
}
