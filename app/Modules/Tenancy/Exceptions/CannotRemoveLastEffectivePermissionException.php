<?php

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

class CannotRemoveLastEffectivePermissionException extends RuntimeException
{
    public function __construct(string $permissionName = 'crítica', int $code = 0, ?\Throwable $previous = null)
    {
        $message = "Não é possível remover a permissão {$permissionName} desta função, pois isso deixaria a organização sem administradores capazes de realizar esta ação.";
        parent::__construct($message, $code, $previous);
    }
}
