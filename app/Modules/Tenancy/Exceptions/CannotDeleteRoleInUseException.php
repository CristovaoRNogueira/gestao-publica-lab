<?php

namespace App\Modules\Tenancy\Exceptions;

use Exception;

class CannotDeleteRoleInUseException extends Exception
{
    public function __construct($message = "Não é possível excluir um papel que está atribuído a associações.")
    {
        parent::__construct($message);
    }
}
