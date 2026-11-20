<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * A business rule prevents the action (HTTP 422).
 *
 * The message is meant to be shown to the user as is, so it takes a translation
 * key plus its parameters instead of a literal string.
 */
class BusinessRule extends DomainException
{
    protected string $domain = 'Business Rule';

    protected string $errorCode = 'BUSINESS_RULE';

    /**
     * @param  array<string, string|int|float|null>  $parameters
     */
    public function __construct(string $key, array $parameters = [], ?Throwable $previous = null)
    {
        parent::__construct(__($key, $parameters), 422, $previous);
    }
}
