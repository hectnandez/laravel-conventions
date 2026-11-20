<?php

declare(strict_types=1);

namespace Hectnandez\Conventions;

use InvalidArgumentException;
use Throwable;

final class Cli
{
    private const COMMANDS = ['sync', 'check', 'diff', 'update', 'help', 'version'];

    public function __construct(private readonly string $packageRoot) {}

    public function run(array $argv): int
    {
        try {
            $options = $this->parse(array_slice($argv, 1));
        } catch (InvalidArgumentException $exception) {
            fwrite(STDERR, $exception->getMessage().PHP_EOL);
            fwrite(STDERR, 'Usa `conventions help` para ver las opciones.'.PHP_EOL);

            return 2;
        }

        if ($options['help'] || $options['command'] === 'help') {
            $this->help();

            return 0;
        }

        if ($options['version'] || $options['command'] === 'version') {
            echo trim((string) file_get_contents($this->packageRoot.'/VERSION')).PHP_EOL;

            return 0;
        }

        $command = $options['command'] ?? 'check';

        if (! in_array($command, self::COMMANDS, true)) {
            fwrite(STDERR, sprintf('Comando desconocido: %s%s', $command, PHP_EOL));

            return 2;
        }

        try {
            $manifest = Manifest::load($this->packageRoot);
            $project = Project::resolve($this->packageRoot, $options['project']);
            $reporter = new Reporter($options['json']);
            $config = ProjectConfig::load($project->root());

            return match ($command) {
                'sync' => (new SyncCommand($this->packageRoot, $project, $manifest, $reporter, $config))
                    ->run($options['force']),
                'update' => (new SyncCommand($this->packageRoot, $project, $manifest, $reporter, $config))
                    ->run(true),
                'check' => (new CheckCommand($this->packageRoot, $project, $manifest, $reporter, $config))
                    ->run(),
                'diff' => (new CheckCommand($this->packageRoot, $project, $manifest, $reporter, $config))
                    ->run(diffOnly: true),
                default => 2,
            };
        } catch (Throwable $exception) {
            if (! $options['json']) {
                fwrite(STDERR, 'Error: '.$exception->getMessage().PHP_EOL);
            }

            return 1;
        }
    }

    /**
     * @param  list<string>  $args
     * @return array{command: ?string, project: ?string, force: bool, json: bool, help: bool, version: bool}
     */
    private function parse(array $args): array
    {
        $options = [
            'command' => null,
            'project' => null,
            'force' => false,
            'json' => false,
            'help' => false,
            'version' => false,
        ];

        $count = count($args);

        for ($index = 0; $index < $count; $index++) {
            $arg = $args[$index];

            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;

                continue;
            }

            if ($arg === '--version' || $arg === '-V') {
                $options['version'] = true;

                continue;
            }

            if ($arg === '--force' || $arg === '-f') {
                $options['force'] = true;

                continue;
            }

            if ($arg === '--json') {
                $options['json'] = true;

                continue;
            }

            if (str_starts_with($arg, '--project=')) {
                $options['project'] = substr($arg, strlen('--project='));

                continue;
            }

            if ($arg === '--project') {
                $value = $args[$index + 1] ?? null;

                if ($value === null || str_starts_with($value, '-')) {
                    throw new InvalidArgumentException('--project necesita una ruta.');
                }

                $options['project'] = $value;
                $index++;

                continue;
            }

            if (str_starts_with($arg, '-')) {
                throw new InvalidArgumentException(sprintf('Opción desconocida: %s', $arg));
            }

            if ($options['command'] !== null) {
                throw new InvalidArgumentException(sprintf('Argumento inesperado: %s', $arg));
            }

            $options['command'] = $arg;
        }

        return $options;
    }

    private function help(): void
    {
        echo <<<'HELP'
laravel-conventions — reglas y artefactos canónicos para proyectos Laravel.

Uso:
  conventions [opciones] [comando]

Comandos:
  sync      Copia los artefactos canónicos y escribe .conventions.lock.json.
  check     Verifica que el proyecto coincide con el paquete (por defecto).
  diff      Muestra solo las diferencias.
  update    Igual que sync, sobrescribiendo cambios locales.
  version   Muestra la versión del paquete.
  help      Muestra esta ayuda.

Opciones:
  --project=<ruta>  Proyecto destino (por defecto, el directorio actual).
  --force, -f       Sobrescribe archivos canónicos modificados localmente.
  --json            Salida JSON (para CI).
  --help, -h        Muestra esta ayuda.
  --version, -V     Muestra la versión.

Ejemplos:
  conventions sync --project=../mi-app
  conventions check --json
  conventions update

HELP;
    }
}
