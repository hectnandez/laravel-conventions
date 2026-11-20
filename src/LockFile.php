<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

final class LockFile
{
    public const NAME = '.conventions.lock.json';

    public static function path(string $projectRoot): string
    {
        return $projectRoot.'/'.self::NAME;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function read(string $projectRoot): ?array
    {
        $path = self::path($projectRoot);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function write(string $projectRoot, array $data): void
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        file_put_contents(self::path($projectRoot), $json."\n");
    }
}
