<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * The action conflicts with the current state of the resource (HTTP 409).
 */
class Conflict extends DomainException
{
    protected string $domain = 'Conflict';

    protected string $errorCode = 'CONFLICT';

    /**
     * @param  array<string, string|int|float|null>  $parameters
     */
    public function __construct(string $key, array $parameters = [], ?Throwable $previous = null)
    {
        parent::__construct(__($key, $parameters), 409, $previous);
    }
}
