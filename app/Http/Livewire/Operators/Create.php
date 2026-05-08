<?php

declare(strict_types=1);

namespace App\Http\Livewire\Operators;

use App\Mail\OperatorInviteMail;
use App\Models\Operator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $email = '';

    public string $name = '';

    public string $role = 'operador';

    protected array $rules = [
        'email' => ['required', 'email', 'unique:operators,email'],
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'role' => ['required', 'in:admin,operador,suporte'],
    ];

    public function save(): void
    {
        Gate::authorize('manage-operators');
        $this->validate();

        $operator = Operator::create([
            'id' => Str::uuid()->toString(),
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'status' => 'pending',
            'password_hash' => bcrypt(Str::random(64)),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'operators.accept-invite',
            now()->addHours(48),
            ['operator' => $operator],
        );

        Mail::to($operator->email)->send(new OperatorInviteMail($operator, $signedUrl));

        session()->flash('status', "Convite enviado para {$operator->email}");

        $this->redirect(route('operators.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.operators.create');
    }
}
