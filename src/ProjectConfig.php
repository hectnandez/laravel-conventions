<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use RuntimeException;

final class ProjectConfig
{
    public const NAME = '.conventions.json';

    /**
     * @param  list<string>  $ignore
     */
    private function __construct(private readonly array $ignore) {}

    public static function load(string $projectRoot): self
    {
        $path = $projectRoot.'/'.self::NAME;

        if (! is_file($path)) {
            return new self([]);
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException(self::NAME.' debe contener un objeto JSON.');
        }

        $ignore = $data['ignore'] ?? [];

        if (! is_array($ignore)) {
            throw new RuntimeException(self::NAME.': "ignore" debe ser un array de ids.');
        }

        $ignore = array_values(array_filter($ignore, static fn (mixed $id): bool => is_string($id) && $id !== ''));

        return new self($ignore);
    }

    /**
     * @return list<string>
     */
    public function ignore(): array
    {
        return $this->ignore;
    }

    public function ignores(string $artifactId): bool
    {
        return in_array($artifactId, $this->ignore, true);
    }
}
