<?php

declare(strict_types=1);

use App\Http\Livewire\Operators\Create;
use App\Models\Operator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('returns pt-BR validation message when operator create form is submitted empty', function () {
    $admin = Operator::factory()->admin()->create();

    $component = Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('email', '')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name', 'email']);

    expect($component->errors()->first('name'))->toBe('O campo nome é obrigatório.');
});

it('mass assignment via Operator::create does not persist privileged role field', function () {
    $operator = Operator::create([
        'id' => Str::uuid()->toString(),
        'email' => 'mass-assign@test.local',
        'name' => 'Mass Assign Test',
        'role' => 'admin',
        'password_hash' => Hash::make('password123'),
        'status' => 'active',
    ]);

    expect($operator->fresh()->role)->toBe('operador');
});
