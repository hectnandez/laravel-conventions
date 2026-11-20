<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

final class CheckCommand
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly Project $project,
        private readonly Manifest $manifest,
        private readonly Reporter $reporter,
        private readonly ProjectConfig $config,
    ) {}

    public function run(bool $diffOnly = false): int
    {
        $projectRoot = $this->project->root();
        $lock = LockFile::read($projectRoot);
        $checked = 0;
        $issues = [];
        $ignored = [];

        if ($lock === null) {
            $issues[] = [
                'type' => 'missing-lock',
                'path' => LockFile::NAME,
                'message' => 'No existe el lock. Ejecuta `conventions sync`.',
            ];

            return $this->finish($issues, $checked, $diffOnly, $ignored);
        }

        $lockedVersion = $lock['version'] ?? null;

        if ($lockedVersion !== $this->manifest->version) {
            $issues[] = [
                'type' => 'outdated',
                'path' => 'VERSION',
                'message' => sprintf(
                    'El lock está en %s y el paquete en %s. Ejecuta `conventions sync`.',
                    is_string($lockedVersion) ? $lockedVersion : 'una versión desconocida',
                    $this->manifest->version,
                ),
            ];
        }

        $expected = ['files' => [], 'blocks' => [], 'stubs' => []];
        $knownIds = array_map(static fn (Artifact $artifact): string => $artifact->id, $this->manifest->artifacts());

        foreach ($this->config->ignore() as $id) {
            if (! in_array($id, $knownIds, true)) {
                $issues[] = [
                    'type' => 'unknown-ignore',
                    'path' => ProjectConfig::NAME,
                    'message' => sprintf('El artefacto "%s" no existe en el manifiesto.', $id),
                ];
            }
        }

        foreach ($this->manifest->artifacts() as $artifact) {
            if ($this->config->ignores($artifact->id)) {
                $ignored[] = $artifact->id;

                continue;
            }

            match ($artifact->mode) {
                'copy' => $this->collectCopy($artifact, $projectRoot, $expected),
                'managed-block' => $this->collectBlock($artifact, $expected),
                'stub' => $expected['stubs'][] = (string) $artifact->to,
                'reference' => null,
            };
        }

        $lockedFiles = is_array($lock['files'] ?? null) ? $lock['files'] : [];
        $lockedBlocks = is_array($lock['blocks'] ?? null) ? $lock['blocks'] : [];
        $lockedStubs = is_array($lock['stubs'] ?? null) ? $lock['stubs'] : [];

        foreach ($expected['files'] as $relative => $hash) {
            $checked++;
            $locked = $lockedFiles[$relative] ?? null;

            if (! is_string($locked) || $locked !== $hash) {
                $issues[] = [
                    'type' => 'outdated',
                    'path' => $relative,
                    'message' => 'El lock no coincide con el paquete. Ejecuta `conventions sync`.',
                ];

                continue;
            }

            $current = Files::hash($projectRoot.'/'.$relative);

            if ($current === null) {
                $issues[] = [
                    'type' => 'missing',
                    'path' => $relative,
                    'message' => 'Falta el archivo canónico. Ejecuta `conventions sync`.',
                ];

                continue;
            }

            if ($current !== $hash) {
                $issues[] = [
                    'type' => 'modified',
                    'path' => $relative,
                    'message' => 'Archivo canónico modificado localmente. Usa `conventions sync --force` para restaurarlo.',
                ];
            }
        }

        foreach ($expected['blocks'] as $relative => $hash) {
            $checked++;
            $locked = $lockedBlocks[$relative] ?? null;

            if (! is_string($locked) || $locked !== $hash) {
                $issues[] = [
                    'type' => 'outdated',
                    'path' => $relative,
                    'message' => 'El lock no coincide con el paquete. Ejecuta `conventions sync`.',
                ];

                continue;
            }

            $target = $projectRoot.'/'.$relative;

            if (! is_file($target)) {
                $issues[] = [
                    'type' => 'missing',
                    'path' => $relative,
                    'message' => 'Falta el archivo con el bloque gestionado. Ejecuta `conventions sync`.',
                ];

                continue;
            }

            $inner = ManagedBlock::extract((string) file_get_contents($target));

            if ($inner === null) {
                $issues[] = [
                    'type' => 'missing-block',
                    'path' => $relative,
                    'message' => 'Faltan los marcadores `conventions:start/end`. Ejecuta `conventions sync`.',
                ];

                continue;
            }

            if (hash('sha256', $inner) !== $hash) {
                $issues[] = [
                    'type' => 'modified',
                    'path' => $relative,
                    'message' => 'Bloque gestionado modificado localmente. Usa `conventions sync` para restaurarlo.',
                ];
            }
        }

        foreach ($expected['stubs'] as $relative) {
            $checked++;

            if (! is_file($projectRoot.'/'.$relative)) {
                $issues[] = [
                    'type' => 'missing',
                    'path' => $relative,
                    'message' => 'Falta el stub. Ejecuta `conventions sync`.',
                ];
            }
        }

        foreach (array_keys($lockedFiles) as $relative) {
            if (! array_key_exists($relative, $expected['files'])) {
                $issues[] = [
                    'type' => 'stale',
                    'path' => $relative,
                    'message' => 'El paquete ya no define este archivo; bórralo del proyecto.',
                ];
            }
        }

        foreach (array_keys($lockedBlocks) as $relative) {
            if (! array_key_exists($relative, $expected['blocks'])) {
                $issues[] = [
                    'type' => 'stale',
                    'path' => $relative,
                    'message' => 'El paquete ya no define este bloque; elimínalo del proyecto.',
                ];
            }
        }

        foreach ($lockedStubs as $relative) {
            if (! is_string($relative) || ! in_array($relative, $expected['stubs'], true)) {
                $issues[] = [
                    'type' => 'stale',
                    'path' => is_string($relative) ? $relative : '(desconocido)',
                    'message' => 'El paquete ya no define este stub; bórralo del lock.',
                ];
            }
        }

        return $this->finish($issues, $checked, $diffOnly, $ignored);
    }

    /**
     * @param  array{files: array<string, string>, blocks: array<string, string>, stubs: list<string>}  $expected
     */
    private function collectCopy(Artifact $artifact, string $projectRoot, array &$expected): void
    {
        $source = $this->packageRoot.'/'.$artifact->from;
        $target = $projectRoot.'/'.$artifact->to;

        if (is_dir($source)) {
            foreach (Files::files($source) as $relative) {
                $hash = Files::hash($source.'/'.$relative);

                if ($hash !== null) {
                    $expected['files'][Files::relative($projectRoot, $target.'/'.$relative)] = $hash;
                }
            }

            return;
        }

        $hash = Files::hash($source);

        if ($hash !== null) {
            $expected['files'][Files::relative($projectRoot, $target)] = $hash;
        }
    }

    /**
     * @param  array{files: array<string, string>, blocks: array<string, string>, stubs: list<string>}  $expected
     */
    private function collectBlock(Artifact $artifact, array &$expected): void
    {
        $source = $this->packageRoot.'/'.$artifact->from;
        $inner = ManagedBlock::content((string) file_get_contents($source));
        $expected['blocks'][(string) $artifact->to] = hash('sha256', $inner);
    }

    /**
     * @param  list<array{type: string, path: string, message: string}>  $issues
     * @param  list<string>  $ignored
     */
    private function finish(array $issues, int $checked, bool $diffOnly, array $ignored): int
    {
        $projectRoot = $this->project->root();
        $ok = $issues === [];

        if ($this->reporter->jsonEnabled()) {
            $this->reporter->data([
                'ok' => $ok,
                'version' => $this->manifest->version,
                'project' => $projectRoot,
                'checked' => $checked,
                'ignored' => $ignored,
                'issues' => $issues,
            ]);

            return $ok ? 0 : 1;
        }

        $this->reporter->line(sprintf(
            'laravel-conventions %s · %s',
            $this->manifest->version,
            $projectRoot,
        ));

        foreach ($ignored as $id) {
            $this->reporter->line('  ignore        '.$id);
        }

        if ($ok) {
            $this->reporter->line($diffOnly
                ? 'Sin diferencias.'
                : sprintf('OK %d artefacto(s) verificados.', $checked));

            return 0;
        }

        foreach ($issues as $issue) {
            $this->reporter->line(sprintf(
                '  %-13s %s — %s',
                strtoupper($issue['type']),
                $issue['path'],
                $issue['message'],
            ));
        }

        $this->reporter->line(sprintf('FAIL %d problema(s).', count($issues)));

        return 1;
    }
}
