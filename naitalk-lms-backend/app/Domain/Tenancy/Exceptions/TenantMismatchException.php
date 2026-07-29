<?php

namespace App\Domain\Tenancy\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class TenantMismatchException extends AuthorizationException
{
    public function __construct(string $message = 'This resource does not belong to the current tenant.')
    {
        parent::__construct($message);
    }
}
