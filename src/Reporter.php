<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

final class Reporter
{
    public function __construct(private readonly bool $json = false) {}

    public function jsonEnabled(): bool
    {
        return $this->json;
    }

    public function line(string $message = ''): void
    {
        if ($this->json) {
            return;
        }

        echo $message.PHP_EOL;
    }

    public function error(string $message): void
    {
        if ($this->json) {
            return;
        }

        fwrite(STDERR, $message.PHP_EOL);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function data(array $data): void
    {
        if (! $this->json) {
            return;
        }

        echo json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ).PHP_EOL;
    }
}
