<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\WhmcsApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class WhmcsClient
{
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function addOrder(array $params): array
    {
        return $this->post('AddOrder', $params);
    }

    /**
     * @return array<string, mixed>
     */
    public function acceptOrder(int $orderId): array
    {
        return $this->post('AcceptOrder', ['orderid' => $orderId]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function createDedicatedOrder(array $config): array
    {
        return $this->addOrder(array_merge([
            'pid' => [(int) config('whmcs.dedicated_product_id', 6)],
            'paymentmethod' => 'vindi',
        ], $config));
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleCreate(int $serviceId): array
    {
        return $this->post('ModuleCreate', ['serviceid' => $serviceId]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function post(string $action, array $params): array
    {
        $response = $this->request()->post($this->apiUrl(), array_merge([
            'action' => $action,
            'identifier' => $this->identifier(),
            'secret' => $this->secret(),
            'responsetype' => 'json',
        ], $params));

        if (! $response->successful()) {
            throw WhmcsApiException::fromResponse($response, $action);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        if (($payload['result'] ?? '') !== 'success') {
            throw WhmcsApiException::fromWhmcsResult($payload, $action);
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        return Http::asForm()->acceptJson();
    }

    private function apiUrl(): string
    {
        return rtrim((string) config('whmcs.url'), '/').'/includes/api.php';
    }

    private function identifier(): string
    {
        return (string) config('whmcs.identifier');
    }

    private function secret(): string
    {
        return (string) config('whmcs.secret');
    }
}
