<?php

declare(strict_types=1);

namespace App\Modules\Integration\Support;

use App\Models\ClusterServer;
use App\Models\Customer;
use App\Modules\Core\Ssh\Dto\SshResponse;
use App\Modules\Core\Ssh\SshClientInterface;
use Illuminate\Support\Facades\Http;

final class TenantReadinessGateChecker
{
    public function __construct(
        private readonly SshClientInterface $ssh,
    ) {}

    public function passesAll(Customer $customer, ClusterServer $cluster, int $timeoutSec): bool
    {
        if ($customer->image_mode) {
            return $this->passesMeMailHttp($customer, $timeoutSec);
        }

        if (! $this->passesAppList($customer, $cluster, $timeoutSec)) {
            return false;
        }

        if (! $this->passesUserList($customer, $cluster, $timeoutSec)) {
            return false;
        }

        if (! $this->passesExternalLocation($customer, $cluster, $timeoutSec)) {
            return false;
        }

        if (! $this->passesForceSso($customer, $cluster, $timeoutSec)) {
            return false;
        }

        return $this->passesMeMailHttp($customer, $timeoutSec);
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

    private function passesAppList(Customer $customer, ClusterServer $cluster, int $timeoutSec): bool
    {
        $response = $this->runOcc($customer, $cluster, 'app:list', ['--json'], $timeoutSec);
        if ($response->exitCode !== 0) {
            return false;
        }

        $enabled = $response->parsedJson['enabled'] ?? null;
        if (! is_array($enabled)) {
            return false;
        }

        return ($enabled['mework360_memail'] ?? false) === true
            && ($enabled['me360_theme'] ?? false) === true;
    }

    private function passesUserList(Customer $customer, ClusterServer $cluster, int $timeoutSec): bool
    {
        return $this->runOcc($customer, $cluster, 'user:list', ['--json'], $timeoutSec)->exitCode === 0;
    }

    private function passesExternalLocation(Customer $customer, ClusterServer $cluster, int $timeoutSec): bool
    {
        $response = $this->runOcc(
            $customer,
            $cluster,
            'config:app:get',
            ['mework360_memail', 'externalLocation', '--json'],
            $timeoutSec,
        );
        if ($response->exitCode !== 0) {
            return false;
        }

        $value = $response->parsedJson['value'] ?? trim($response->stdout);

        return is_string($value) && $value !== '';
    }

    private function passesForceSso(Customer $customer, ClusterServer $cluster, int $timeoutSec): bool
    {
        $response = $this->runOcc(
            $customer,
            $cluster,
            'config:app:get',
            ['mework360_memail', 'forceSSO', '--json'],
            $timeoutSec,
        );
        if ($response->exitCode !== 0) {
            return false;
        }

        $value = $response->parsedJson['value'] ?? trim($response->stdout);

        return is_string($value) && strcasecmp($value, 'yes') === 0;
    }

    private function passesMeMailHttp(Customer $customer, int $timeoutSec): bool
    {
        $path = $customer->image_mode ? '/login' : '/apps/mework360_memail/';
        $url = sprintf('https://%s%s', $customer->domain, $path);

        try {
            $response = Http::timeout($timeoutSec)->get($url);
        } catch (\Throwable) {
            return false;
        }

        return $response->status() === 200;
    }
}
