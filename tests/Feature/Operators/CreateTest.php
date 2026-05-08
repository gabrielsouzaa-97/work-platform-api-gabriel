<?php

declare(strict_types=1);

use App\Http\Livewire\Auth\AcceptInvite;
use App\Http\Livewire\Operators\Create;
use App\Http\Livewire\Operators\Index;
use App\Mail\OperatorInviteMail;
use App\Models\Operator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('admin can access operators index', function () {
    $admin = Operator::factory()->admin()->create();

    actingAs($admin)
        ->get(route('operators.index'))
        ->assertOk();
});

it('suporte cannot access operators index and gets 403', function () {
    $suporte = Operator::factory()->create(['role' => 'suporte']);

    actingAs($suporte)
        ->get(route('operators.index'))
        ->assertForbidden();
});

it('operador cannot access operators create and gets 403', function () {
    $operador = Operator::factory()->create(['role' => 'operador']);

    actingAs($operador)
        ->get(route('operators.create'))
        ->assertForbidden();
});

it('admin creates operator and invite email is sent', function () {
    Mail::fake();

    $admin = Operator::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('email', 'novoop@test.local')
        ->set('name', 'Novo Operador')
        ->set('role', 'operador')
        ->call('save')
        ->assertRedirect(route('operators.index'));

    Mail::assertQueued(OperatorInviteMail::class, function ($mail) {
        return $mail->hasTo('novoop@test.local');
    });

    $this->assertDatabaseHas('operators', [
        'email' => 'novoop@test.local',
        'status' => 'pending',
        'role' => 'operador',
    ]);
});

it('duplicate email returns validation error', function () {
    $admin = Operator::factory()->admin()->create();
    Operator::factory()->create(['email' => 'existing@test.local']);

    Livewire::actingAs($admin)
        ->test(Create::class)
        ->set('email', 'existing@test.local')
        ->set('name', 'Another')
        ->set('role', 'operador')
        ->call('save')
        ->assertHasErrors(['email']);
});

it('accept invite with valid signed URL activates operator and logs in', function () {
    $operator = Operator::factory()->pending()->create([
        'email' => 'invite@test.local',
    ]);

    Livewire::test(AcceptInvite::class, ['operator' => $operator])
        ->set('password', 'newstrongpassword1')
        ->set('password_confirmation', 'newstrongpassword1')
        ->call('acceptInvite')
        ->assertRedirectContains('/customers');

    $operator->refresh();
    expect($operator->status)->toBe('active');
});

it('accept invite with expired signed URL returns 403', function () {
    $operator = Operator::factory()->pending()->create();

    $expiredUrl = URL::temporarySignedRoute(
        'operators.accept-invite',
        now()->subHour(),
        ['operator' => $operator],
    );

    $parsed = parse_url($expiredUrl);
    $path = $parsed['path'].'?'.$parsed['query'];

    get($path)->assertForbidden();
});

it('already active operator cannot use accept invite link', function () {
    $operator = Operator::factory()->create(['status' => 'active']);

    $signedUrl = URL::temporarySignedRoute(
        'operators.accept-invite',
        now()->addHours(48),
        ['operator' => $operator],
    );

    $parsed = parse_url($signedUrl);
    $path = $parsed['path'].'?'.$parsed['query'];

    get($path)->assertForbidden();
});

it('admin resends invite email for pending operator', function () {
    Mail::fake();

    $admin = Operator::factory()->admin()->create();
    $pending = Operator::factory()->pending()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('resendInvite', $pending->id);

    Mail::assertQueued(OperatorInviteMail::class, function ($mail) use ($pending) {
        return $mail->hasTo($pending->email);
    });
});
