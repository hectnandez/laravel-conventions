<?php

declare(strict_types=1);

namespace Deployer;

/**
 * Receta base compartida de Deployer.
 *
 * El deploy.php del proyecto define host, repository, branch y las rutas, y luego
 * incluye esta receta:
 *
 *     require __DIR__.'/deploy/conventions/base.php';
 *
 * Requiere deployer/deployer y el recipe/laravel.
 */

require 'recipe/laravel.php';

set('keep_releases', 5);
set('writable_dirs', ['storage', 'bootstrap/cache']);
set('writable_mode', 'chmod');
set('writable_use_sudo', false);

desc('Sube el .env de la release desde el archivo local indicado en DEPLOY_ENV_FILE');
task('deploy:secrets', static function (): void {
    $file = getenv('DEPLOY_ENV_FILE');

    if (! is_string($file) || $file === '' || ! is_file($file)) {
        warning('DEPLOY_ENV_FILE no está definido: se conserva el .env del servidor.');

        return;
    }

    upload($file, '{{deploy_path}}/shared/.env');
});

desc('Ajusta permisos de storage y bootstrap/cache');
task('deploy:permissions', static function (): void {
    run('cd {{release_path}} && chmod -R ug+rwx storage bootstrap/cache');
});

desc('Reinicia los workers de la cola');
task('deploy:queue:restart', static function (): void {
    run('cd {{release_path}} && {{bin/php}} artisan queue:restart');
});

desc('Comprueba que la aplicación responde');
task('deploy:healthcheck', static function (): void {
    $url = getenv('DEPLOY_HEALTHCHECK_URL');

    if (! is_string($url) || $url === '') {
        return;
    }

    $status = run(sprintf('curl -fsS -o /dev/null -w "%%{http_code}" %s', escapeshellarg($url)));

    if (trim($status) !== '200') {
        throw new \RuntimeException(sprintf('Healthcheck falló con HTTP %s.', trim($status)));
    }
});

after('deploy:shared', 'deploy:secrets');
after('deploy:shared', 'deploy:permissions');
after('deploy:symlink', 'deploy:queue:restart');
after('deploy:symlink', 'deploy:healthcheck');
after('deploy:failed', 'deploy:unlock');
