<?php

declare(strict_types=1);

use App\Http\Livewire\Operators\Edit;
use App\Models\AuditLog;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('admin updates another operator name and role and logs audit', function () {
    $admin = Operator::factory()->admin()->create();
    $other = Operator::factory()->create([
        'name' => 'Original',
        'role' => 'operador',
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(Edit::class, ['operatorId' => $other->id])
        ->set('name', 'Atualizado')
        ->set('role', 'suporte')
        ->call('save')
        ->assertHasNoErrors();

    $other->refresh();

    expect($other->name)->toBe('Atualizado')
        ->and($other->role)->toBe('suporte');

    $log = AuditLog::where('action', 'operator.profile_updated')
        ->where('resource_id', $other->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($admin->id)
        ->and($log->payload['fields_changed'])->toBe(['name', 'role']);
});

it('admin sets another operator inactive clears their sessions', function () {
    $admin = Operator::factory()->admin()->create();
    $other = Operator::factory()->create(['status' => 'active']);

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $other->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    Livewire::actingAs($admin)
        ->test(Edit::class, ['operatorId' => $other->id])
        ->set('status', 'inactive')
        ->call('save')
        ->assertHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $other->id)->count())->toBe(0);
    expect(DB::table('sessions')->where('user_id', $admin->id)->count())->toBe(1);
});

it('non-admin receives 403 when accessing operator edit page', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);
    $target = Operator::factory()->create();

    actingAs($operador)
        ->get(route('operators.edit', ['operatorId' => $target->id]))
        ->assertForbidden();
});

it('admin edits own profile without clearing own sessions', function () {
    $admin = Operator::factory()->admin()->create(['name' => 'Eu Admin']);

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'PHPUnit',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    Livewire::actingAs($admin)
        ->test(Edit::class, ['operatorId' => $admin->id])
        ->set('name', 'Eu Admin Renomeado')
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->fresh()->name)->toBe('Eu Admin Renomeado');
    expect(DB::table('sessions')->where('user_id', $admin->id)->count())->toBe(1);
});

it('admin tenta atribuir role inválido → erro de validação', function () {
    $admin = Operator::factory()->admin()->create();
    $other = Operator::factory()->create(['role' => 'operador']);

    Livewire::actingAs($admin)
        ->test(Edit::class, ['operatorId' => $other->id])
        ->set('role', 'superuser')
        ->call('save')
        ->assertHasErrors(['role']);

    expect($other->fresh()->role)->toBe('operador');
});
