<?php

namespace App\Modules\Tenancy\Exceptions;

use RuntimeException;

class TenantSlugAlreadyExistsException extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("A identificação da organização '{$slug}' já está em uso.");
    }
}
