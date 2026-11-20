<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use RuntimeException;

final class Project
{
    private function __construct(private readonly string $root) {}

    public function root(): string
    {
        return $this->root;
    }

    public static function resolve(string $packageRoot, ?string $explicit): self
    {
        $candidate = $explicit ?? (getenv('CONVENTIONS_PROJECT') ?: null);

        if (is_string($candidate) && $candidate !== '') {
            $resolved = realpath($candidate);

            if ($resolved === false || ! is_dir($resolved)) {
                throw new RuntimeException(sprintf('El proyecto %s no existe o no es un directorio.', $candidate));
            }

            return new self($resolved);
        }

        $cwd = getcwd() ?: $packageRoot;
        $found = self::walkUp($cwd);

        if ($found !== null && realpath($found) !== realpath($packageRoot)) {
            return new self((string) realpath($found));
        }

        $vendorRoot = self::fromVendor($packageRoot);

        if ($vendorRoot !== null) {
            return new self($vendorRoot);
        }

        throw new RuntimeException(
            'No se encontró el proyecto. Ejecuta desde la raíz del proyecto o usa --project=<ruta>.',
        );
    }

    private static function walkUp(string $start): ?string
    {
        $directory = $start;

        while (true) {
            if (is_file($directory.'/composer.json')) {
                return $directory;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }
    }

    private static function fromVendor(string $packageRoot): ?string
    {
        if (basename(dirname($packageRoot, 2)) !== 'vendor') {
            return null;
        }

        $root = dirname($packageRoot, 3);

        return is_file($root.'/composer.json') ? $root : null;
    }
}
