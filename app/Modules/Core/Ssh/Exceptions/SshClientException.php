<?php

declare(strict_types=1);

namespace App\Modules\Core\Ssh\Exceptions;

use RuntimeException;

class SshClientException extends RuntimeException
{
    public static function auditCategoryFor(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof SshTimeoutException => 'timeout',
            $exception instanceof SshConnectionException => 'connection_failed',
            $exception instanceof SshRemoteException => 'remote_failed',
            default => 'unknown',
        };
    }

    public static function userMessageFor(\Throwable $exception): string
    {
        return match (true) {
            $exception instanceof SshTimeoutException => 'Timeout ao conectar via SSH.',
            $exception instanceof SshConnectionException => 'Falha de conexão SSH.',
            $exception instanceof SshRemoteException => 'Comando remoto rejeitado pelo servidor.',
            default => 'Falha ao sincronizar webhook secret.',
        };
    }
}
