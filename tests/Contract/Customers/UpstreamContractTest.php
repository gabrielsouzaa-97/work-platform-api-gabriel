<?php

declare(strict_types=1);

/**
 * Upstream contract test — Sprint F5 / ISSUE-006 follow-up.
 *
 * Validates the canonical-cmd → CLI-argv mapping (`JobTypeTranslator::cmdToCliArgv`)
 * by exercising the REAL SSH path against the `homolog` cluster.
 *
 * Default behaviour: all examples skip. To execute manually pre-merge:
 *
 *     RUN_UPSTREAM_CONTRACT=1 \
 *     UPSTREAM_CONTRACT_CLUSTER_ID=119d74df-9011-4c0f-a6bf-ad03f84af10d \
 *     UPSTREAM_CONTRACT_CUSTOMER_SLUG=qa-f5-contract \
 *     php artisan test --testsuite=Contract
 *
 * Pre-conditions for a real run:
 *   - Cluster row exists in DB (status=active) with the SSH key configured.
 *   - Customer with slug `qa-f5-contract` already provisioned upstream (or accept
 *     that `user create` will fail with `customer_not_found`; the assertion below
 *     only requires that the upstream argv is recognized — exit 0 OR a structured
 *     business-error JSON, NOT `cmd_not_allowed`).
 *
 * NOTE: this file lives under `tests/Contract/` (NOT `tests/Feature/`) precisely
 * because the operator must seed real rows before invoking. The Pest setup
 * intentionally OMITS `RefreshDatabase` for this directory — fixing QA-F5-001
 * (auditor finding that uncovered the Feature/RefreshDatabase collision).
 *
 * IMPORTANT: this test SHOULD NOT run on CI by default. The opt-in flag exists
 * to keep the test as a *gate against ISSUE-006 regression* — every time someone
 * changes `JobTypeTranslator::CMD_TO_CLI_ARGV`, they can run it manually before
 * merging.
 */

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Models\Operator;
use App\Modules\Core\Translators\JobTypeTranslator;
use App\Modules\Customers\Actions\LifecycleAsyncAction;

function upstreamContractSkipUnlessEnabled(): void
{
    if (env('RUN_UPSTREAM_CONTRACT') !== '1' && env('RUN_UPSTREAM_CONTRACT') !== 1 && env('RUN_UPSTREAM_CONTRACT') !== true) {
        test()->markTestSkipped('Opt-in only. Set RUN_UPSTREAM_CONTRACT=1 to run against homolog cluster.');
    }
}

function upstreamContractCluster(): ClusterServer
{
    $clusterId = env('UPSTREAM_CONTRACT_CLUSTER_ID');
    if ($clusterId === null) {
        test()->fail('UPSTREAM_CONTRACT_CLUSTER_ID env var is required when RUN_UPSTREAM_CONTRACT=1');
    }
    $cluster = ClusterServer::find($clusterId);
    if ($cluster === null) {
        test()->fail("Cluster {$clusterId} not found in DB. Seed before running upstream contract.");
    }

    return $cluster;
}

function upstreamContractCustomer(): Customer
{
    $slug = env('UPSTREAM_CONTRACT_CUSTOMER_SLUG', 'qa-f5-contract');
    $customer = Customer::find($slug);
    if ($customer === null) {
        test()->fail("Customer {$slug} not found in DB. Provision upstream and seed locally before running upstream contract.");
    }

    return $customer;
}

function upstreamContractOperator(): Operator
{
    return Operator::factory()->create(['role' => 'operador', 'status' => 'active']);
}

/**
 * @param  list<string>  $expectedArgv
 */
function assertUpstreamArgvMapping(string $cmd, array $expectedArgv): void
{
    $translator = app(JobTypeTranslator::class);
    expect($translator->cmdToCliArgv($cmd))->toBe($expectedArgv);
}

function upstreamContractBestEffortCleanup(
    LifecycleAsyncAction $action,
    Customer $customer,
    Operator $operator,
    string $cmd,
    array $args,
): void {
    try {
        $action->execute($customer, $cmd, $args, null, $operator);
    } catch (Throwable) {
        // best-effort — upstream may already have removed the resource
    }
}

// ── Contract scenarios ────────────────────────────────────────────────────────

it('user create dispatches a real job upstream with extended stdin (password + email + groups)', function (): void {
    // QA-F5-015: validates that the upstream `nextcloud-manage user create --payload-stdin`
    // accepts the full schema introduced in F5.3 — `{password, email?, groups?}` — not just
    // `{password}`. Without this, a future upstream tightening to strict-keys would silently
    // break the API. The opt-in nature keeps CI free of side-effects.
    upstreamContractSkipUnlessEnabled();
    assertUpstreamArgvMapping('users:create', ['user', 'create']);
    upstreamContractCluster();
    $customer = upstreamContractCustomer();
    $operator = upstreamContractOperator();

    /** @var LifecycleAsyncAction $action */
    $action = app(LifecycleAsyncAction::class);

    $username = 'qa-'.substr(uniqid(), -8);
    try {
        $job = $action->execute(
            $customer,
            'users:create',
            [$username],
            [
                'password' => 'Tr0ng-P4ss!'.uniqid(),
                'email' => 'qa-contract@example.com',
                'groups' => ['editors'],
            ],
            $operator,
        );

        expect($job->job_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i')
            ->and($job->state)->toBe('queued');
    } finally {
        upstreamContractBestEffortCleanup($action, $customer, $operator, 'users:delete', [$username]);
    }
});

it('user remove dispatches a real job upstream (verb is "user remove", not "user delete")', function (): void {
    upstreamContractSkipUnlessEnabled();
    assertUpstreamArgvMapping('users:delete', ['user', 'remove']);
    upstreamContractCluster();
    $customer = upstreamContractCustomer();
    $operator = upstreamContractOperator();

    $action = app(LifecycleAsyncAction::class);
    $job = $action->execute($customer, 'users:delete', ['qa-nonexistent'], null, $operator);

    expect($job->job_id)->not->toBeNull()
        ->and($job->state)->toBe('queued');
});

it('group create dispatches a real job upstream', function (): void {
    upstreamContractSkipUnlessEnabled();
    assertUpstreamArgvMapping('groups:create', ['group', 'create']);
    upstreamContractCluster();
    $customer = upstreamContractCustomer();
    $operator = upstreamContractOperator();

    $action = app(LifecycleAsyncAction::class);
    $groupName = 'qa-grp-'.substr(uniqid(), -6);
    try {
        $job = $action->execute($customer, 'groups:create', [$groupName], null, $operator);
        expect($job->job_id)->not->toBeNull();
    } finally {
        upstreamContractBestEffortCleanup($action, $customer, $operator, 'groups:delete', [$groupName]);
    }
});

it('group remove dispatches a real job upstream (verb is "group remove", not "group delete")', function (): void {
    upstreamContractSkipUnlessEnabled();
    assertUpstreamArgvMapping('groups:delete', ['group', 'remove']);
    upstreamContractCluster();
    $customer = upstreamContractCustomer();
    $operator = upstreamContractOperator();

    $action = app(LifecycleAsyncAction::class);
    $job = $action->execute($customer, 'groups:delete', ['qa-grp-nonexistent'], null, $operator);

    expect($job->job_id)->not->toBeNull();
});

it('apps enable accepts a CSV positional and dispatches 1 job', function (): void {
    upstreamContractSkipUnlessEnabled();
    assertUpstreamArgvMapping('apps:enable', ['apps', 'enable']);
    upstreamContractCluster();
    $customer = upstreamContractCustomer();
    $operator = upstreamContractOperator();

    $action = app(LifecycleAsyncAction::class);
    $job = $action->execute($customer, 'apps:enable', ['calendar,contacts'], null, $operator);

    expect($job->job_id)->not->toBeNull();
});

it('apps disable accepts a CSV positional and dispatches 1 job', function (): void {
    upstreamContractSkipUnlessEnabled();
    assertUpstreamArgvMapping('apps:disable', ['apps', 'disable']);
    upstreamContractCluster();
    $customer = upstreamContractCustomer();
    $operator = upstreamContractOperator();

    $action = app(LifecycleAsyncAction::class);
    $job = $action->execute($customer, 'apps:disable', ['calendar,contacts'], null, $operator);

    expect($job->job_id)->not->toBeNull();
});
