<?php

declare(strict_types=1);

namespace App\Modules\Integration\Support;

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use App\Modules\Integration\Dto\ReadinessReport;
use Illuminate\Support\Facades\Http;

final class TenantReadinessGateChecker
{
    public function __construct(
        private readonly SshClientInterface $ssh,
    ) {}

    public function passesAll(Customer $customer, ClusterServer $cluster, int $timeoutSec): bool
    {
        return $this->evaluate($customer, $cluster, $timeoutSec)->ready;
    }

    public function evaluate(Customer $customer, ClusterServer $cluster, int $timeoutSec): ReadinessReport
    {
        if (config('services.ssh.driver') === 'fake') {
            return new ReadinessReport(true);
        }

        if ($customer->image_mode) {
            return $this->evaluateMeMailHttp($customer, $timeoutSec);
        }

        foreach ([
            fn (): ReadinessReport => $this->evaluateAppList($customer, $cluster, $timeoutSec),
            fn (): ReadinessReport => $this->evaluateUserList($customer, $cluster, $timeoutSec),
            fn (): ReadinessReport => $this->evaluateExternalLocation($customer, $cluster, $timeoutSec),
            fn (): ReadinessReport => $this->evaluateForceSso($customer, $cluster, $timeoutSec),
            fn (): ReadinessReport => $this->evaluateMeMailHttp($customer, $timeoutSec),
        ] as $gate) {
            $report = $gate();
            if (! $report->ready) {
                return $report;
            }
        }

        return new ReadinessReport(true);
    }

    /**
     * @param  list<string>  $extraArgs
     */
    private function runOcc(
        Customer $customer,
        ClusterServer $cluster,
        string $occSubcmd,
        array $extraArgs,
        int $timeoutSec,
    ): SshResponse {
        return $this->ssh->run(
            $cluster,
            'nextcloud-manage',
            array_merge([$customer->slug, 'occ-exec', $occSubcmd], $extraArgs),
            null,
            $timeoutSec,
        );
    }

    private function evaluateAppList(Customer $customer, ClusterServer $cluster, int $timeoutSec): ReadinessReport
    {
        $probe = 'occ-exec app:list';
        $response = $this->runOcc($customer, $cluster, 'app:list', ['--json'], $timeoutSec);
        if ($response->exitCode !== 0) {
            return new ReadinessReport(false, $this->occFailureMessage($response), $probe);
        }

        $payload = OccExecEnvelopeParser::unwrapPayload($response->parsedJson);
        if ($payload === null) {
            return new ReadinessReport(false, 'app:list returned invalid JSON', $probe);
        }

        if (! OccExecEnvelopeParser::isAppEnabled($payload, 'mework360_memail')
            || ! OccExecEnvelopeParser::isAppEnabled($payload, 'me360_theme')) {
            return new ReadinessReport(false, 'required apps not enabled', $probe);
        }

        return new ReadinessReport(true);
    }

    private function evaluateUserList(Customer $customer, ClusterServer $cluster, int $timeoutSec): ReadinessReport
    {
        $probe = 'occ-exec user:list';
        $response = $this->runOcc($customer, $cluster, 'user:list', ['--json'], $timeoutSec);
        if ($response->exitCode === 0) {
            return new ReadinessReport(true);
        }

        return new ReadinessReport(false, $this->occFailureMessage($response), $probe);
    }

    private function evaluateExternalLocation(Customer $customer, ClusterServer $cluster, int $timeoutSec): ReadinessReport
    {
        $probe = 'occ-exec config:externalLocation';
        $response = $this->runOcc(
            $customer,
            $cluster,
            'config:app:get',
            ['mework360_memail', 'externalLocation', '--json'],
            $timeoutSec,
        );
        if ($response->exitCode !== 0) {
            return new ReadinessReport(false, $this->occFailureMessage($response), $probe);
        }

        $value = OccExecEnvelopeParser::configValue($response->parsedJson, $response->stdout);
        if (is_string($value) && $value !== '') {
            return new ReadinessReport(true);
        }

        return new ReadinessReport(false, 'externalLocation not configured', $probe);
    }

    private function evaluateForceSso(Customer $customer, ClusterServer $cluster, int $timeoutSec): ReadinessReport
    {
        $probe = 'occ-exec config:forceSSO';
        $response = $this->runOcc(
            $customer,
            $cluster,
            'config:app:get',
            ['mework360_memail', 'forceSSO', '--json'],
            $timeoutSec,
        );
        if ($response->exitCode !== 0) {
            return new ReadinessReport(false, $this->occFailureMessage($response), $probe);
        }

        $value = OccExecEnvelopeParser::configValue($response->parsedJson, $response->stdout);
        if (is_string($value) && strcasecmp($value, 'yes') === 0) {
            return new ReadinessReport(true);
        }

        return new ReadinessReport(false, 'forceSSO is not yes', $probe);
    }

    private function evaluateMeMailHttp(Customer $customer, int $timeoutSec): ReadinessReport
    {
        $path = $customer->image_mode ? '/login' : '/apps/mework360_memail/';
        $probe = $customer->image_mode ? 'http:login' : 'http:memail';
        $url = sprintf('https://%s%s', $customer->domain, $path);

        try {
            $response = Http::timeout($timeoutSec)
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
                ->withOptions(['allow_redirects' => true])
                ->get($url);
        } catch (\Throwable $e) {
            return new ReadinessReport(false, $e->getMessage(), $probe);
        }

        if ($response->status() === 200) {
            return new ReadinessReport(true);
        }

        return new ReadinessReport(false, "HTTP {$response->status()}", $probe);
    }

    private function occFailureMessage(SshResponse $response): string
    {
        $stderr = trim($response->stderr);
        if ($stderr !== '') {
            return $stderr;
        }

        return "exit_code={$response->exitCode}";
    }
}
