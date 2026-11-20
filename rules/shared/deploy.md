# Despliegue

Aplica a `deploy/**`, `deploy*.php` y `.gitlab-ci.yml`.

## Modelo

- Stage y producción corren **nginx + PHP-FPM nativos**, sin Docker.
- El despliegue se automatiza con **Deployer** o con un job de GitLab CI; nunca a mano
  en el servidor.
- Un pipeline no despliega si los tests fallan.

## Deployer

- El `deploy.php` del proyecto define host, repositorio, rama y rutas.
- La receta base compartida se incluye con
  `require __DIR__.'/deploy/conventions/base.php';`.
- `keep_releases` ≥ 5 y cada release es un directorio nuevo con symlink `current`.
- El `.env` vive en `shared/.env` y no se versiona.

## GitLab CI

- Jobs en orden: build → quality → deploy.
- El deploy solo corre en la rama/tag esperados y con `resource_group` para evitar
  despliegues concurrentes.
- Las credenciales viven en variables protegidas de CI, nunca en el repo.

## Migraciones

- Deben ser **compatibles hacia atrás**: el rollback no las revierte.
- No borrar columnas/tablas en el mismo release que deja de usarlas.
- Una migración destructiva requiere dos releases.

## Rollback

- Volver a la release anterior con el mecanismo del proyecto (Deployer `rollback` o
  symlink).
- Un rollback no deshace migraciones: verificar compatibilidad antes de desplegar.
- Tras el rollback, reiniciar workers y limpiar cachés.

## Salud

- Un healthcheck HTTP 200 después del symlink.
- `php artisan queue:restart` en cada deploy.
- Logs y Sentry revisados tras el despliegue.
