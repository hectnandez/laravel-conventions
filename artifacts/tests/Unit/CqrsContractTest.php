<?php

declare(strict_types=1);

/**
 * CANONICAL v1.0 — NO EDITAR A MANO.
 * Fuente de verdad: .agents/rules/cqrs-conventions.md
 * Excepciones por repo: tests/cqrs-baseline.php (este archivo no se toca).
 */

namespace Tests\Unit;

use App\Common\Contracts\CommandInterface;
use App\Common\Contracts\QueryInterface;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use SplFileInfo;

final class CqrsContractTest extends TestCase
{
    private static string $root;

    /** @var array<string, list<string>> */
    private static array $baseline = [];

    public static function setUpBeforeClass(): void
    {
        self::$root = dirname(__DIR__, 2);

        $file = self::$root.'/tests/cqrs-baseline.php';

        if (is_file($file)) {
            /** @var array<string, list<string>> $baseline */
            $baseline = require $file;
            self::$baseline = $baseline;
        }
    }

    #[Test]
    public function commands_implement_the_contract_and_receive_a_command_dto(): void
    {
        $files = $this->phpFiles(self::$root.'/app/Common/*/Commands/*/*Command.php');

        $this->assertNotEmpty($files, 'No se encontraron Commands: revisa la convencion A1.');

        foreach ($files as $file) {
            if ($this->isExempt('C1', $file)) {
                continue;
            }

            $class = $this->classNameFor($file);
            $this->assertNotNull($class, 'No se pudo resolver la clase de '.$this->relative($file));

            $this->assertContains(
                CommandInterface::class,
                class_implements($class) ?: [],
                $this->relative($file).' debe implementar CommandInterface (A5).'
            );

            $parameters = (new ReflectionMethod($class, 'execute'))->getParameters();

            $this->assertCount(
                1,
                $parameters,
                $this->relative($file).': execute() debe recibir exactamente el CommandDTO (C1).'
            );

            $type = $parameters[0]->getType();
            $name = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

            $this->assertStringEndsWith(
                'CommandDTO',
                $name,
                $this->relative($file).': execute() debe recibir un *CommandDTO, recibe '.$name.' (C1).'
            );
        }
    }

    #[Test]
    public function queries_implement_the_contract_and_receive_a_query_dto(): void
    {
        $files = array_merge(
            $this->phpFiles(self::$root.'/app/Common/*/Queries/*/*Query.php'),
            $this->phpFiles(self::$root.'/app/Common/*/Queries/*Query.php'),
        );

        $this->assertNotEmpty($files, 'No se encontraron Queries: revisa la convencion A2.');

        foreach ($files as $file) {
            if ($this->isExempt('C2', $file)) {
                continue;
            }

            $class = $this->classNameFor($file);
            $this->assertNotNull($class, 'No se pudo resolver la clase de '.$this->relative($file));

            $this->assertContains(
                QueryInterface::class,
                class_implements($class) ?: [],
                $this->relative($file).' debe implementar QueryInterface (A5).'
            );

            $parameters = (new ReflectionMethod($class, 'handle'))->getParameters();

            $this->assertCount(
                1,
                $parameters,
                $this->relative($file).': handle() debe recibir exactamente el QueryDTO (C2).'
            );

            $type = $parameters[0]->getType();
            $name = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

            $this->assertStringEndsWith(
                'QueryDTO',
                $name,
                $this->relative($file).': handle() debe recibir un *QueryDTO, recibe '.$name.' (C2).'
            );
        }
    }

    #[Test]
    public function every_command_has_its_command_dto_sibling(): void
    {
        foreach ($this->phpFiles(self::$root.'/app/Common/*/Commands/*/*Command.php') as $file) {
            if ($this->isExempt('A3', $file)) {
                continue;
            }

            $dto = substr($file, 0, -strlen('Command.php')).'CommandDTO.php';

            $this->assertFileExists($dto, $this->relative($file).': falta su CommandDTO en la misma carpeta (A3).');
        }
    }

    #[Test]
    public function command_and_query_dtos_are_readonly(): void
    {
        $files = array_merge(
            $this->phpFiles(self::$root.'/app/Common/*/Commands/*/*CommandDTO.php'),
            $this->phpFiles(self::$root.'/app/Common/*/Queries/*/*QueryDTO.php'),
        );

        foreach ($files as $file) {
            if ($this->isExempt('B1', $file)) {
                continue;
            }

            $class = $this->classNameFor($file);
            $this->assertNotNull($class, 'No se pudo resolver la clase de '.$this->relative($file));

            $this->assertTrue(
                (new ReflectionClass($class))->isReadOnly(),
                $this->relative($file).' debe ser final readonly (B1).'
            );
        }
    }

    #[Test]
    public function command_and_query_folders_are_plural(): void
    {
        $singular = array_merge(
            glob(self::$root.'/app/Common/*/Command') ?: [],
            glob(self::$root.'/app/Common/*/Query') ?: [],
        );

        $singular = array_values(array_filter(
            $singular,
            fn (string $dir): bool => ! $this->isExempt('A1', $dir),
        ));

        $this->assertSame([], $singular, 'A1: usa Commands/ y Queries/ en plural.');
    }

    #[Test]
    public function domain_layer_does_not_throw_http_exceptions(): void
    {
        $this->assertNoContent('F1', 'HttpException', $this->phpFilesUnder(self::$root.'/app/Common'));
    }

    #[Test]
    public function domain_layer_does_not_open_manual_transactions(): void
    {
        $this->assertNoContent(
            'E2',
            'beginTransaction',
            $this->phpFilesUnder(self::$root.'/app/Common'),
            'E2: usa DB::transaction(), no beginTransaction/commit manual.'
        );
    }

    #[Test]
    public function commands_do_not_read_ambient_auth(): void
    {
        $commands = array_values(array_filter(
            $this->phpFilesUnder(self::$root.'/app/Common'),
            fn (string $file): bool => str_contains($file, '/Commands/'),
        ));

        $this->assertNoContent('G1', 'auth(', $commands, 'G1: el actorId viaja en el CommandDTO, no por auth().');
        $this->assertNoContent('G1', 'Auth::', $commands, 'G1: el actorId viaja en el CommandDTO, no por Auth::.');
    }

    #[Test]
    public function controllers_do_not_touch_models_or_db(): void
    {
        $this->assertNoContent(
            'G5',
            'App\\Models\\',
            $this->phpFilesUnder(self::$root.'/app/Http/Controllers'),
            'G5: el controller solo habla con Commands/Queries.'
        );

        $this->assertNoContent(
            'G5',
            'DB::',
            $this->phpFilesUnder(self::$root.'/app/Http/Controllers'),
            'G5: el controller solo habla con Commands/Queries.'
        );
    }

    /**
     * @param  list<string>  $files
     */
    private function assertNoContent(string $rule, string $needle, array $files, ?string $message = null): void
    {
        $offenders = [];

        foreach ($files as $file) {
            if ($this->isExempt($rule, $file)) {
                continue;
            }

            $contents = file_get_contents($file);

            if ($contents !== false && str_contains($contents, $needle)) {
                $offenders[] = $this->relative($file);
            }
        }

        $this->assertSame([], $offenders, $message ?? $rule.' prohibido: '.$needle);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $pattern): array
    {
        $files = glob($pattern) ?: [];
        sort($files);

        return array_values($files);
    }

    /**
     * @return list<string>
     */
    private function phpFilesUnder(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function classNameFor(string $file): ?string
    {
        $appPath = self::$root.'/app/';

        if (! str_starts_with($file, $appPath)) {
            return null;
        }

        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($appPath)));

        return class_exists($class) ? $class : null;
    }

    private function relative(string $file): string
    {
        return substr($file, strlen(self::$root) + 1);
    }

    private function isExempt(string $rule, string $file): bool
    {
        return in_array($this->relative($file), self::$baseline[$rule] ?? [], true);
    }
}
