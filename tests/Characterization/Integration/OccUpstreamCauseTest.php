<?php

declare(strict_types=1);

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Core\Ssh\Exceptions\SshTimeoutException;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Customers\Services\OccPassthroughService;
use App\Modules\Integration\Exceptions\UpstreamUnavailableException;

beforeEach(function (): void {
    if (config('app.key') === '' || config('app.key') === null) {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }
});

it('propagates SshTimeoutException cause through OccPassthroughService', function (): void {
    $cluster = ClusterServer::factory()->create(['status' => 'active']);
    $customer = Customer::create([
        'slug' => 'cause-chain',
        'cluster_server_id' => $cluster->id,
        'domain' => 'cause.example.com',
        'status' => 'active',
    ]);

    $ssh = Mockery::mock(SshClientInterface::class);
    $ssh->shouldReceive('run')->once()->andThrow(new SshTimeoutException('Timeout'));
    app()->instance(SshClientInterface::class, $ssh);

    try {
        app(OccPassthroughService::class)->exec($customer, 'maintenance:mode', ['--off']);
        expect(false)->toBeTrue('expected exception');
    } catch (UpstreamUnavailableException $e) {
        expect($e->transportCause())->toBeInstanceOf(SshTimeoutException::class);
        expect($e->getPrevious())->toBeInstanceOf(SshTimeoutException::class);
    }
});
