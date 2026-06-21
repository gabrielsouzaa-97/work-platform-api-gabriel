<?php

declare(strict_types=1);

namespace App\Modules\Mail\Providers;

use ArrayAccess;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * @implements ArrayAccess<string, mixed>
 */
final class MailAwareConfigRepository implements ArrayAccess, ConfigRepository
{
    public function __construct(private readonly ConfigRepository $inner) {}

    public function has($key)
    {
        return $this->inner->has($key);
    }

    public function get($key, $default = null)
    {
        if ($key === 'mail_api.api_key') {
            return $this->resolveMailApiKey();
        }

        if ($key === 'mail_api') {
            /** @var array<string, mixed> $config */
            $config = $this->inner->get('mail_api', []);
            $config['api_key'] = $this->resolveMailApiKey();

            return $config;
        }

        return $this->inner->get($key, $default);
    }

    public function all()
    {
        return $this->inner->all();
    }

    public function set($key, $value = null)
    {
        $result = $this->inner->set($key, $value);

        if ($key === 'mail_api.api_key_encrypted' || (is_array($key) && array_key_exists('mail_api.api_key_encrypted', $key))) {
            $this->inner->set('mail_api.api_key', $this->resolveMailApiKey());
        }

        return $result;
    }

    public function prepend($key, $value)
    {
        return $this->inner->prepend($key, $value);
    }

    public function push($key, $value)
    {
        return $this->inner->push($key, $value);
    }

    public function offsetExists($offset): bool
    {
        return $this->inner->offsetExists($offset);
    }

    public function offsetGet($offset): mixed
    {
        if ($offset === 'mail_api') {
            return $this->get('mail_api');
        }

        if (is_string($offset) && str_contains($offset, '.')) {
            return $this->get($offset);
        }

        return $this->inner->offsetGet($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        $this->inner->offsetUnset($offset);
    }

    private function resolveMailApiKey(): string
    {
        $encrypted = $this->inner->get('mail_api.api_key_encrypted');

        if (is_string($encrypted) && $encrypted !== '') {
            return decrypt($encrypted);
        }

        return (string) $this->inner->get('mail_api.api_key', '');
    }
}
