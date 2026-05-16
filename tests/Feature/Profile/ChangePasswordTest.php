<?php

declare(strict_types=1);

use App\Http\Livewire\Profile\ChangePassword;
use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('operador altera senha com sucesso mantendo sessão password_hash e audit log', function () {
    $operator = Operator::factory()->create([
        'password_hash' => Hash::make('oldpassword123'),
    ]);

    actingAs($operator);
    $sessionIdBefore = session()->getId();

    Livewire::actingAs($operator)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'oldpassword123')
        ->set('newPassword', 'newpassword123')
        ->set('newPasswordConfirmation', 'newpassword123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', msg: 'Senha alterada com sucesso.');

    expect(session()->getId())->toBe($sessionIdBefore);
    $operator->refresh();
    expect($operator->password_hash)->not->toBeNull()
        ->and(Hash::check('newpassword123', $operator->password_hash))->toBeTrue();

    $log = AuditLog::where('action', 'operator.password_changed')
        ->where('actor_id', $operator->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->resource_type)->toBe('operator')
        ->and((string) $log->resource_id)->toBe((string) $operator->id)
        ->and($log->payload)->toBe(['method' => 'self_change']);
});

it('rejeita senha atual incorreta com erro em currentPassword', function () {
    $operator = Operator::factory()->create([
        'password_hash' => Hash::make('correctpass123'),
    ]);

    Livewire::actingAs($operator)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'wrongpass123')
        ->set('newPassword', 'newpassword123')
        ->set('newPasswordConfirmation', 'newpassword123')
        ->call('save')
        ->assertHasErrors(['currentPassword']);

    expect(Hash::check('correctpass123', $operator->refresh()->password_hash))->toBeTrue();
    expect(AuditLog::where('action', 'operator.password_changed')->exists())->toBeFalse();
});

it('rejeita nova senha sem confirmação correspondente com erro em newPassword', function () {
    $operator = Operator::factory()->create([
        'password_hash' => Hash::make('oldpassword123'),
    ]);

    Livewire::actingAs($operator)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'oldpassword123')
        ->set('newPassword', 'newpassword123')
        ->set('newPasswordConfirmation', 'otherpassword456')
        ->call('save')
        ->assertHasErrors(['newPassword']);
});

it('rejeita nova senha com menos de 8 caracteres com erro em newPassword', function () {
    $operator = Operator::factory()->create([
        'password_hash' => Hash::make('oldpassword123'),
    ]);

    Livewire::actingAs($operator)
        ->test(ChangePassword::class)
        ->set('currentPassword', 'oldpassword123')
        ->set('newPassword', 'short')
        ->set('newPasswordConfirmation', 'short')
        ->call('save')
        ->assertHasErrors(['newPassword']);
});
