<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    protected string $domain = 'Domain Exception';

    protected string $errorCode = 'DOMAIN_ERROR';

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array{domain: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];
    }
}
