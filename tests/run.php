<?php

declare(strict_types=1);

/**
 * Suite del CLI. No requiere Composer ni Laravel.
 *
 *   php tests/run.php
 */

require __DIR__.'/../src/autoload.php';

$failures = 0;
$tests = 0;
$temporary = sys_get_temp_dir().'/laravel-conventions-'.bin2hex(random_bytes(4));
$fixture = $temporary.'/app';

mkdir($fixture, 0o775, true);
file_put_contents($fixture.'/composer.json', "{}\n");

/**
 * @param  list<string>  $args
 * @return array{0: int, 1: string}
 */
function runCli(array $args): array
{
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../bin/conventions');

    foreach ($args as $arg) {
        $command .= ' '.escapeshellarg($arg);
    }

    $output = [];
    $code = 0;
    exec($command.' 2>&1', $output, $code);

    return [$code, implode("\n", $output)];
}

function assertTrue(bool $condition, string $message): void
{
    global $failures, $tests;

    $tests++;

    if ($condition) {
        echo 'ok   '.$message.PHP_EOL;

        return;
    }

    $failures++;
    fwrite(STDERR, 'FAIL '.$message.PHP_EOL);
}

function assertContains(string $needle, string $haystack, string $message): void
{
    assertTrue(str_contains($haystack, $needle), $message.' (buscando "'.$needle.'")');
}

function removeTree(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($directory);
}

try {
    [$code, $output] = runCli(['check', '--project='.$fixture]);
    assertTrue($code === 1, 'check sin lock falla con exit 1');
    assertContains('MISSING-LOCK', $output, 'check sin lock reporta el lock ausente');

    [$code, $output] = runCli(['sync', '--project='.$fixture]);
    assertTrue($code === 0, 'sync inicial termina en 0');

    if ($code !== 0) {
        fwrite(STDERR, $output.PHP_EOL);
    }

    $canonical = hash_file('sha256', __DIR__.'/../artifacts/tests/Unit/CqrsContractTest.php');
    $installed = hash_file('sha256', $fixture.'/tests/Unit/CqrsContractTest.php');
    assertTrue($canonical === $installed, 'el test canónico se copia byte a byte');

    foreach ([
        'app/Common/Contracts/CommandInterface.php',
        'app/Common/Contracts/QueryInterface.php',
        'app/Exceptions/DomainException.php',
        'app/Exceptions/BusinessRule.php',
        'app/Exceptions/Conflict.php',
        'tests/cqrs-baseline.php',
        '.agents/rules/cqrs-conventions.md',
        '.agents/rules/shared/index.md',
        '.agents/skills/laravel-best-practices/SKILL.md',
        '.conventions/Makefile',
        'compose.conventions.yml',
        'phpstan.base.neon',
        '.conventions.lock.json',
    ] as $path) {
        assertTrue(is_file($fixture.'/'.$path), 'se crea '.$path);
    }

    $agents = (string) file_get_contents($fixture.'/AGENTS.md');
    assertContains('<!-- conventions:start -->', $agents, 'AGENTS.md recibe el bloque gestionado');
    assertContains('.agents/rules/shared/index.md', $agents, 'el bloque apunta a las reglas');

    [$code, $output] = runCli(['check', '--project='.$fixture]);
    assertTrue($code === 0, 'check limpio termina en 0');
    assertContains('OK', $output, 'check limpio muestra OK');

    [$code, $output] = runCli(['diff', '--project='.$fixture]);
    assertTrue($code === 0, 'diff limpio termina en 0');
    assertContains('Sin diferencias', $output, 'diff limpio lo indica');

    // Edición local de un archivo canónico.
    file_put_contents($fixture.'/tests/Unit/CqrsContractTest.php', "<?php\n// roto\n");

    [$code, $output] = runCli(['check', '--project='.$fixture]);
    assertTrue($code === 1, 'check detecta un archivo canónico modificado');
    assertContains('MODIFIED', $output, 'check reporta el archivo modificado');

    [$code, $output] = runCli(['sync', '--project='.$fixture]);
    assertTrue($code === 1, 'sync sin --force rechaza sobrescribir');
    assertContains('--force', $output, 'sync sugiere --force');

    [$code, $output] = runCli(['sync', '--force', '--project='.$fixture]);
    assertTrue($code === 0, 'sync --force restaura el archivo');
    assertTrue(hash_file('sha256', $fixture.'/tests/Unit/CqrsContractTest.php') === $canonical, 'el hash vuelve al canónico');

    // Bloque gestionado.
    $agents = (string) file_get_contents($fixture.'/AGENTS.md');
    $agents = str_replace('## Reglas compartidas', '## Reglas locales', $agents);
    $agents .= "\n\nTexto local que debe sobrevivir.\n";
    file_put_contents($fixture.'/AGENTS.md', $agents);

    [$code, $output] = runCli(['check', '--project='.$fixture]);
    assertTrue($code === 1, 'check detecta un bloque gestionado modificado');

    [$code, $output] = runCli(['sync', '--project='.$fixture]);
    assertTrue($code === 0, 'sync restaura el bloque');
    $agents = (string) file_get_contents($fixture.'/AGENTS.md');
    assertContains('## Reglas compartidas', $agents, 'el bloque vuelve a su contenido canónico');
    assertContains('Texto local que debe sobrevivir.', $agents, 'el texto fuera del bloque se conserva');

    // Stub: no se sobrescribe.
    file_put_contents($fixture.'/tests/cqrs-baseline.php', "<?php\n\nreturn ['C1' => ['app/X.php']];\n");
    [$code, $output] = runCli(['sync', '--project='.$fixture]);
    assertTrue($code === 0, 'sync con baseline existente termina en 0');
    assertContains("['C1' => ['app/X.php']]", (string) file_get_contents($fixture.'/tests/cqrs-baseline.php'), 'el baseline no se sobrescribe');

    // Ignore por proyecto.
    file_put_contents($fixture.'/.conventions.json', "{\n    \"ignore\": [\"exceptions\"]\n}\n");

    [$code, $output] = runCli(['sync', '--project='.$fixture]);
    assertTrue($code === 0, 'sync con ignore termina en 0');
    assertContains('ignore  exceptions', $output, 'sync informa el artefacto ignorado');

    $lock = json_decode((string) file_get_contents($fixture.'/.conventions.lock.json'), true);
    $lockFiles = is_array($lock) ? ($lock['files'] ?? []) : [];
    $hasExceptions = false;

    foreach (array_keys($lockFiles) as $path) {
        if (str_starts_with($path, 'app/Exceptions/')) {
            $hasExceptions = true;
        }
    }

    assertTrue(! $hasExceptions, 'el lock no registra el artefacto ignorado');

    [$code, $output] = runCli(['check', '--project='.$fixture]);
    assertTrue($code === 0, 'check con ignore termina en 0');
    assertContains('ignore', $output, 'check informa el artefacto ignorado');

    unlink($fixture.'/.conventions.json');
    [$code, $output] = runCli(['sync', '--project='.$fixture]);
    assertTrue($code === 0, 'sync tras quitar el ignore termina en 0');
    assertTrue(is_file($fixture.'/app/Exceptions/Conflict.php'), 'el artefacto vuelve tras quitar el ignore');

    file_put_contents($fixture.'/.conventions.json', "{\n    \"ignore\": [\"no-existe\"]\n}\n");
    [$code, $output] = runCli(['check', '--project='.$fixture]);
    assertTrue($code === 1, 'check detecta un ignore desconocido');
    assertContains('UNKNOWN-IGNORE', $output, 'check reporta el ignore desconocido');
    unlink($fixture.'/.conventions.json');

    // JSON.
    [$code, $output] = runCli(['check', '--json', '--project='.$fixture]);
    $json = json_decode($output, true);
    assertTrue($code === 0, 'check --json termina en 0');
    assertTrue(is_array($json) && ($json['ok'] ?? null) === true, 'check --json devuelve ok=true');
    assertTrue(is_array($json) && ($json['version'] ?? null) === '1.0.0', 'check --json devuelve la versión');

    [$code, $output] = runCli(['version']);
    assertTrue($code === 0 && trim($output) === '1.0.0', 'version devuelve 1.0.0');

    [$code, $output] = runCli(['comando-inventado']);
    assertTrue($code === 2, 'un comando desconocido termina en 2');
} finally {
    removeTree($temporary);
}

echo PHP_EOL;
echo sprintf('%d test(s), %d fallo(s).', $tests, $failures).PHP_EOL;

exit($failures === 0 ? 0 : 1);
