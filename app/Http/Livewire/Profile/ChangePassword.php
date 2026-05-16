<?php

declare(strict_types=1);

namespace App\Http\Livewire\Profile;

use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ChangePassword extends Component
{
    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    protected array $rules = [
        'currentPassword' => ['required', 'string'],
        'newPassword' => ['required', 'string', 'min:8', 'same:newPasswordConfirmation'],
        'newPasswordConfirmation' => ['required', 'string'],
    ];

    public function save(): void
    {
        $this->validate();

        $operator = Auth::user();

        if (! $operator instanceof Operator || ! Hash::check($this->currentPassword, $operator->password_hash)) {
            $this->addError('currentPassword', 'Senha atual incorreta.');

            return;
        }

        $operator->update(['password_hash' => Hash::make($this->newPassword)]);

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'action' => 'operator.password_changed',
            'actor_id' => Auth::id(),
            'resource_type' => 'operator',
            'resource_id' => Auth::id(),
            'payload' => ['method' => 'self_change'],
        ]);

        $this->dispatch('toast', type: 'success', msg: 'Senha alterada com sucesso.');
        $this->reset(['currentPassword', 'newPassword', 'newPasswordConfirmation']);
    }

    public function render(): View
    {
        return view('livewire.profile.change-password');
    }
}
