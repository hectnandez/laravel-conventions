<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

final class SyncCommand
{
    public function __construct(
        private readonly string $packageRoot,
        private readonly Project $project,
        private readonly Manifest $manifest,
        private readonly Reporter $reporter,
        private readonly ProjectConfig $config,
    ) {}

    public function run(bool $force): int
    {
        $projectRoot = $this->project->root();
        $existing = LockFile::read($projectRoot) ?? [];
        $existingFiles = is_array($existing['files'] ?? null) ? $existing['files'] : [];
        $existingBlocks = is_array($existing['blocks'] ?? null) ? $existing['blocks'] : [];

        $lock = [
            'package' => 'hectnandez/laravel-conventions',
            'version' => $this->manifest->version,
            'generated_at' => gmdate('c'),
            'files' => [],
            'blocks' => [],
            'stubs' => [],
        ];

        $conflicts = [];
        $written = [];
        $kept = [];
        $ignored = [];

        foreach ($this->manifest->artifacts() as $artifact) {
            if ($this->config->ignores($artifact->id)) {
                $ignored[] = $artifact->id;

                continue;
            }

            match ($artifact->mode) {
                'copy' => $this->syncCopy($artifact, $projectRoot, $existingFiles, $lock, $force, $conflicts, $written),
                'stub' => $this->syncStub($artifact, $projectRoot, $lock, $written, $kept),
                'managed-block' => $this->syncBlock($artifact, $projectRoot, $existingBlocks, $lock, $written, $kept),
                'reference' => null,
            };
        }

        if ($conflicts !== []) {
            $this->reporter->error('Hay archivos canónicos modificados localmente:');

            foreach ($conflicts as $path) {
                $this->reporter->error('  - '.$path);
            }

            $this->reporter->error(
                'Revisa `conventions diff`. Usa `conventions sync --force` para sobrescribirlos.',
            );

            $this->reporter->data([
                'ok' => false,
                'version' => $this->manifest->version,
                'project' => $projectRoot,
                'conflicts' => $conflicts,
            ]);

            return 1;
        }

        LockFile::write($projectRoot, $lock);

        $this->reporter->line(sprintf(
            'laravel-conventions %s · %s',
            $this->manifest->version,
            $projectRoot,
        ));

        foreach ($written as $path) {
            $this->reporter->line('  sync    '.$path);
        }

        foreach ($kept as $path) {
            $this->reporter->line('  keep    '.$path);
        }

        foreach ($ignored as $id) {
            $this->reporter->line('  ignore  '.$id);
        }

        $this->reporter->line(sprintf('OK %d archivo(s) sincronizado(s).', count($written)));

        $this->reporter->data([
            'ok' => true,
            'version' => $this->manifest->version,
            'project' => $projectRoot,
            'written' => $written,
            'kept' => $kept,
            'ignored' => $ignored,
        ]);

        return 0;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $lock
     * @param  list<string>  $conflicts
     * @param  list<string>  $written
     */
    private function syncCopy(
        Artifact $artifact,
        string $projectRoot,
        array $existing,
        array &$lock,
        bool $force,
        array &$conflicts,
        array &$written,
    ): void {
        $source = $this->packageRoot.'/'.$artifact->from;
        $target = $projectRoot.'/'.$artifact->to;

        $pairs = [];

        if (is_dir($source)) {
            foreach (Files::files($source) as $relative) {
                $pairs[$source.'/'.$relative] = $target.'/'.$relative;
            }
        } else {
            $pairs[$source] = $target;
        }

        foreach ($pairs as $from => $to) {
            $hash = Files::hash($from);

            if ($hash === null) {
                continue;
            }

            $relative = Files::relative($projectRoot, $to);
            $current = Files::hash($to);
            $known = $existing[$relative] ?? null;

            if (! $force && $current !== null && is_string($known) && $current !== $known) {
                $conflicts[] = $relative;

                continue;
            }

            Files::copy($from, $to);
            $lock['files'][$relative] = $hash;
            $written[] = $relative;
        }
    }

    /**
     * @param  array<string, mixed>  $lock
     * @param  list<string>  $written
     * @param  list<string>  $kept
     */
    private function syncStub(
        Artifact $artifact,
        string $projectRoot,
        array &$lock,
        array &$written,
        array &$kept,
    ): void {
        $target = $projectRoot.'/'.$artifact->to;
        $relative = Files::relative($projectRoot, $target);

        if (! is_file($target)) {
            Files::copy($this->packageRoot.'/'.$artifact->from, $target);
            $written[] = $relative;
        } else {
            $kept[] = $relative;
        }

        $lock['stubs'][] = $relative;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $lock
     * @param  list<string>  $written
     * @param  list<string>  $kept
     */
    private function syncBlock(
        Artifact $artifact,
        string $projectRoot,
        array $existing,
        array &$lock,
        array &$written,
        array &$kept,
    ): void {
        $source = $this->packageRoot.'/'.$artifact->from;
        $target = $projectRoot.'/'.$artifact->to;
        $inner = ManagedBlock::content((string) file_get_contents($source));
        $contents = is_file($target) ? (string) file_get_contents($target) : '';
        $updated = ManagedBlock::apply($contents, $inner);
        $relative = Files::relative($projectRoot, $target);

        if ($updated !== $contents) {
            Files::ensureDirectory(dirname($target));
            file_put_contents($target, $updated);
            $written[] = $relative;
        } else {
            $kept[] = $relative;
        }

        $lock['blocks'][$relative] = hash('sha256', $inner);
    }
}
