<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use JsonException;
use RuntimeException;

final class Manifest
{
    /**
     * @param  list<Artifact>  $artifacts
     */
    private function __construct(
        public readonly string $version,
        public readonly string $packageRoot,
        private readonly array $artifacts,
    ) {}

    public static function load(string $packageRoot): self
    {
        $versionFile = $packageRoot.'/VERSION';
        $manifestFile = $packageRoot.'/conventions.json';

        if (! is_file($versionFile)) {
            throw new RuntimeException(sprintf('Falta %s/VERSION.', $packageRoot));
        }

        if (! is_file($manifestFile)) {
            throw new RuntimeException(sprintf('Falta %s/conventions.json.', $packageRoot));
        }

        $version = trim((string) file_get_contents($versionFile));

        try {
            $data = json_decode((string) file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('conventions.json no es JSON válido: '.$exception->getMessage());
        }

        if (! is_array($data)) {
            throw new RuntimeException('conventions.json debe contener un objeto JSON.');
        }

        $declared = $data['version'] ?? null;

        if ($declared !== $version) {
            throw new RuntimeException(sprintf(
                'VERSION (%s) y conventions.json (%s) no coinciden.',
                $version,
                is_string($declared) ? $declared : 'sin versión',
            ));
        }

        $rawArtifacts = $data['artifacts'] ?? null;

        if (! is_array($rawArtifacts) || $rawArtifacts === []) {
            throw new RuntimeException('conventions.json debe declarar al menos un artefacto.');
        }

        $artifacts = [];

        foreach ($rawArtifacts as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('Cada artefacto debe ser un objeto JSON.');
            }

            $artifacts[] = Artifact::fromArray($item);
        }

        foreach ($artifacts as $artifact) {
            $source = $packageRoot.'/'.$artifact->from;

            if (! file_exists($source)) {
                throw new RuntimeException(sprintf(
                    'El artefacto %s apunta a %s, que no existe en el paquete.',
                    $artifact->id,
                    $artifact->from,
                ));
            }
        }

        return new self($version, $packageRoot, $artifacts);
    }

    /**
     * @return list<Artifact>
     */
    public function artifacts(): array
    {
        return $this->artifacts;
    }
}
