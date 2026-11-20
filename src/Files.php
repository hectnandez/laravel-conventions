<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use RuntimeException;

final class Files
{
    public static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('No se pudo crear el directorio %s', $directory));
        }
    }

    public static function copy(string $from, string $to): void
    {
        self::ensureDirectory(dirname($to));

        if (! copy($from, $to)) {
            throw new RuntimeException(sprintf('No se pudo copiar %s a %s', $from, $to));
        }
    }

    public static function hash(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $hash = hash_file('sha256', $path);

        return $hash === false ? null : $hash;
    }

    /**
     * @return list<string> Rutas relativas con separador `/`, ordenadas.
     */
    public static function files(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $files[] = str_replace(
                DIRECTORY_SEPARATOR,
                '/',
                substr($file->getPathname(), strlen($directory) + 1),
            );
        }

        sort($files);

        return $files;
    }

    public static function relative(string $base, string $path): string
    {
        $base = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $base), '/');
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);

        if (str_starts_with($path, $base.'/')) {
            return substr($path, strlen($base) + 1);
        }

        return $path;
    }
}
