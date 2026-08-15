<?php

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

class CannotRemoveLastEffectivePermissionException extends RuntimeException
{
    public function __construct(string $permissionName = 'crítica', int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Não é possível remover a permissão {$permissionName} deste papel, pois isso deixaria o tenant sem administradores capazes de realizar esta função.";
        parent::__construct($message, $code, $previous);
    }
}
