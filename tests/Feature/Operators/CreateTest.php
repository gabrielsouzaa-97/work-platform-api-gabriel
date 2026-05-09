<?php

declare(strict_types=1);

use App\Http\Livewire\Auth\AcceptInvite;
use App\Http\Livewire\Operators\Create;
use App\Http\Livewire\Operators\Index;
use App\Mail\OperatorInviteMail;
use App\Models\Operator;
use Illuminate\Http\Request;
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

    Mail::assertQueued(OperatorInviteMail::class, function (OperatorInviteMail $mail) {
        $request = Request::create($mail->signedUrl);

        return $mail->hasTo('novoop@test.local')
            && str_contains($mail->signedUrl, 'token=')
            && URL::hasValidSignature($request);
    });

    $operator = Operator::where('email', 'novoop@test.local')->firstOrFail();

    expect($operator->invite_token_hash)->not->toBeNull()
        ->and($operator->invite_expires_at)->not->toBeNull()
        ->and($operator->invite_expires_at->diffInHours(now()))->toBeLessThanOrEqual(48);

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
    $token = 'valid-invite-token';
    $operator = Operator::factory()->invited($token)->create([
        'email' => 'invite@test.local',
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'operators.accept-invite',
        $operator->invite_expires_at,
        ['operator' => $operator, 'token' => $token],
    );

    $parsed = parse_url($signedUrl);
    $path = $parsed['path'].'?'.$parsed['query'];

    get($path)
        ->assertOk()
        ->assertSee('Ativar conta');

    Livewire::withQueryParams(['token' => $token])
        ->test(AcceptInvite::class, ['operator' => $operator])
        ->set('password', 'newstrongpassword1')
        ->set('password_confirmation', 'newstrongpassword1')
        ->call('acceptInvite')
        ->assertRedirectContains('/customers');

    $operator->refresh();
    expect($operator->status)->toBe('active')
        ->and($operator->invite_token_hash)->toBeNull()
        ->and($operator->invite_expires_at)->toBeNull();
});

it('accept invite with expired signed URL returns 403', function () {
    $operator = Operator::factory()->invited('expired-token')->create();

    $expiredUrl = URL::temporarySignedRoute(
        'operators.accept-invite',
        now()->subHour(),
        ['operator' => $operator, 'token' => 'expired-token'],
    );

    $parsed = parse_url($expiredUrl);
    $path = $parsed['path'].'?'.$parsed['query'];

    get($path)->assertForbidden();
});

it('loaded invite cannot be accepted after stored expiration passes', function () {
    $token = 'expires-after-load';
    $operator = Operator::factory()->invited($token)->create();

    $component = Livewire::withQueryParams(['token' => $token])
        ->test(AcceptInvite::class, ['operator' => $operator])
        ->set('password', 'newstrongpassword1')
        ->set('password_confirmation', 'newstrongpassword1');

    $this->travel(49)->hours();

    $component
        ->call('acceptInvite')
        ->assertHasErrors(['password']);

    expect($operator->refresh()->status)->toBe('pending');
});

it('already active operator cannot use accept invite link', function () {
    $operator = Operator::factory()->create(['status' => 'active']);

    $signedUrl = URL::temporarySignedRoute(
        'operators.accept-invite',
        now()->addHours(48),
        ['operator' => $operator, 'token' => 'unused-token'],
    );

    $parsed = parse_url($signedUrl);
    $path = $parsed['path'].'?'.$parsed['query'];

    get($path)->assertForbidden();
});

it('admin resends invite email for pending operator', function () {
    Mail::fake();

    $admin = Operator::factory()->admin()->create();
    $pending = Operator::factory()->invited('old-token')->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('resendInvite', $pending->id);

    Mail::assertQueued(OperatorInviteMail::class, function (OperatorInviteMail $mail) use ($pending) {
        $request = Request::create($mail->signedUrl);

        return $mail->hasTo($pending->email)
            && str_contains($mail->signedUrl, 'token=')
            && URL::hasValidSignature($request);
    });

    expect($pending->refresh()->invite_token_hash)->not->toBeNull();
});
