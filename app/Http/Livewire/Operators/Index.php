<?php

declare(strict_types=1);

namespace App\Http\Livewire\Operators;

use App\Mail\OperatorInviteMail;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $filterRole = '';

    public string $filterStatus = '';

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function resendInvite(string $operatorId): void
    {
        Gate::authorize('manage-operators');

        $operator = Operator::findOrFail($operatorId);

        if ($operator->status !== 'pending') {
            abort(403, 'Convite disponivel apenas para operadores pendentes.');
        }

        $plainInviteToken = Str::random(64);
        $inviteExpiresAt = now()->addHours(48);

        $operator->invite_token_hash = Hash::make($plainInviteToken);
        $operator->invite_expires_at = $inviteExpiresAt;
        $operator->save();

        $signedUrl = URL::temporarySignedRoute(
            'operators.accept-invite',
            $inviteExpiresAt,
            ['operator' => $operator, 'token' => $plainInviteToken],
        );

        Mail::to($operator->email)->send(new OperatorInviteMail($operator, $signedUrl));

        session()->flash('status', "Convite reenviado para {$operator->email}");
    }

    public function deactivate(string $operatorId): void
    {
        Gate::authorize('manage-operators');

        $operator = Operator::findOrFail($operatorId);
        $operator->status = 'inactive';
        $operator->save();
        DB::table('sessions')->where('user_id', $operator->id)->delete();

        session()->flash('status', "Operador {$operator->name} desativado.");
    }

    public function render(): View
    {
        $query = Operator::query()->orderBy('created_at', 'desc');

        if ($this->filterRole !== '') {
            $query->where('role', $this->filterRole);
        }

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.operators.index', [
            'operators' => $query->paginate(25),
        ]);
    }
}
