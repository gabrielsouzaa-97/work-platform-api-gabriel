<?php

declare(strict_types=1);

namespace App\Http\Livewire\Operators;

use App\Mail\OperatorInviteMail;
use App\Models\AuditLog;
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

#[Layout('layouts.app')]
class Edit extends Component
{
    public Operator $operator;

    public string $name = '';

    public string $role = '';

    public string $status = '';

    public bool $showResendInvite = false;

    protected array $rules = [
        'name' => ['required', 'string', 'min:2', 'max:255'],
        'role' => ['required', 'in:admin,operador,suporte'],
        'status' => ['required', 'in:active,inactive'],
    ];

    public function mount(string $operatorId): void
    {
        Gate::authorize('manage-operators');
        $this->operator = Operator::findOrFail($operatorId);
        $this->name = $this->operator->name;
        $this->role = $this->operator->role;
        $this->status = $this->operator->status === 'active' ? 'active' : 'inactive';
        $this->syncInviteResendUi();
    }

    public function save(): void
    {
        Gate::authorize('manage-operators');
        $this->validate();

        $this->operator->refresh();
        $previousStatus = $this->operator->status;
        $original = ['name' => $this->operator->name, 'role' => $this->operator->role, 'status' => $this->operator->status];

        $this->operator->name = $this->name;
        $this->operator->role = $this->role;
        $this->operator->status = $this->status;
        $this->operator->save();

        $changedFields = array_keys(array_filter([
            'name' => $original['name'] !== $this->name,
            'role' => $original['role'] !== $this->role,
            'status' => $original['status'] !== $this->status,
        ]));

        $this->invalidateOtherOperatorSessionsIfDeactivated($previousStatus);
        $this->writeOperatorProfileAuditLog($changedFields);

        $this->dispatch('toast', type: 'success', msg: 'Perfil de operador atualizado.');
        $this->syncInviteResendUi();
    }

    public function resendInvite(): void
    {
        Gate::authorize('manage-operators');
        $this->operator->refresh();

        if ($this->operator->status !== 'pending' && $this->operator->invite_token_hash === null) {
            abort(403);
        }

        $plainInviteToken = Str::random(64);
        $inviteExpiresAt = now()->addHours(48);

        $this->operator->invite_token_hash = Hash::make($plainInviteToken);
        $this->operator->invite_expires_at = $inviteExpiresAt;
        $this->operator->save();

        $signedUrl = URL::temporarySignedRoute(
            'operators.accept-invite',
            $inviteExpiresAt,
            ['operator' => $this->operator, 'token' => $plainInviteToken],
        );

        Mail::to($this->operator->email)->send(new OperatorInviteMail($this->operator, $signedUrl));

        $this->dispatch('toast', type: 'success', msg: 'Convite reenviado.');
        $this->syncInviteResendUi();
    }

    public function render(): View
    {
        return view('livewire.operators.edit');
    }

    private function syncInviteResendUi(): void
    {
        $this->operator->refresh();
        $this->showResendInvite = $this->operator->status === 'pending'
            || $this->operator->invite_token_hash !== null;
    }

    private function invalidateOtherOperatorSessionsIfDeactivated(string $previousStatus): void
    {
        if ($previousStatus === 'inactive' || $this->status !== 'inactive') {
            return;
        }

        if ($this->operator->id === auth()->id()) {
            return;
        }

        DB::table('sessions')->where('user_id', $this->operator->id)->delete();
    }

    private function writeOperatorProfileAuditLog(array $payloadFields): void
    {
        $actorId = auth()->id();
        if ($actorId === null) {
            abort(401);
        }

        AuditLog::create([
            'id' => Str::uuid()->toString(),
            'actor_id' => $actorId,
            'action' => 'operator.profile_updated',
            'resource_type' => 'operator',
            'resource_id' => $this->operator->id,
            'payload' => ['fields_changed' => $payloadFields],
        ]);
    }
}
