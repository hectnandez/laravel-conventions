# Adopción

Guía para incorporar el paquete a un proyecto existente.

## 0. Prepara el terreno

1. Trabaja en una rama.
2. Haz commit de todo: el primer `sync` puede reemplazar archivos canónicos.
3. Revisa que el proyecto tenga PHP 8.4+ en el contenedor de CI.

## 1. Instala el paquete

```bash
composer config repositories.laravel-conventions vcs https://github.com/hectnandez/laravel-conventions.git
composer require --dev hectnandez/laravel-conventions:^1.0
```

Si el repo fuera privado, configura `COMPOSER_AUTH` con un PAT antes de este paso.

## 2. Sincroniza

```bash
vendor/bin/conventions sync
```

Revisa el diff:

- `tests/Unit/CqrsContractTest.php`: debe ser el test canónico.
- `app/Common/Contracts/*`: solo cambian docblocks.
- `app/Exceptions/*`: puede aparecer la jerarquía completa.
- `.agents/rules/*`: reglas nuevas.
- `AGENTS.md`: solo se añade el bloque gestionado.
- `compose.conventions.yml`, `.conventions/Makefile`, `phpstan.base.neon`: archivos nuevos.
- `.conventions.lock.json`: nuevo, se commitea.

Si algún archivo no debe adoptarse todavía, añádelo a `.conventions.json`:

```json
{
    "ignore": ["exceptions"]
}
```

Cada exclusión debe tener ticket en `deviations`.

## 3. Configura el Makefile

```make
include .conventions/Makefile
```

Colócalo al final, después de `compose_file` y `s`, para que el include no pise tus
variables.

## 4. Configura Docker (opcional, recomendado)

En `docker-compose.yml`:

```yaml
include:
    - compose.conventions.yml

x-networks: &networks
    networks:
        - proxy_mi-proyecto

services:
    app: *networks
    nginx: *networks
    supervisor: *networks
    db: *networks
    redis: *networks

    # Añade aquí lo específico: minio, rabbitmq, init de la BD.
    # ...

networks:
    proxy_mi-proyecto:
        external: true
```

Si el proyecto no usa red externa, no añadas `networks`: todos los servicios comparten
la red por defecto. Si la usas, aplícala a **todos** los servicios de la base (app, nginx,
supervisor, db y redis), no solo a `app`, o no podrán resolverse entre ellos.

En `.env`:

```dotenv
PROJECT_SLUG=mi-proyecto
APP_PORT=8000
DB_FORWARD_PORT=3306
```

Comprueba que arranca:

```bash
docker compose config
make up
```

Si el proyecto ya funciona con su compose, este paso puede esperar. El resto del paquete
no depende de él.

## 5. Configura PHPStan

En `phpstan.neon`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - phpstan.base.neon

parameters:
    level: 5
    paths:
        - app
        - config
        - database/seeders
        - database/factories
        - routes
```

La base no fija `level`: cada proyecto mantiene el suyo y lo sube cuando pueda.

## 6. Configura el CI

```yaml
conventions:
    stage: quality
    <<: *quality-rules
    image: $CI_REGISTRY_IMAGE:app-latest
    before_script: []
    script:
        - ./vendor/bin/conventions check --json
```

El job debe ser bloqueante. Si el pipeline construye la imagen en otra etapa, usa la misma
imagen que `phplint`/`phpstan` para no crear una dependencia nueva.

## 7. Genera el baseline CQRS

Ejecuta el test canónico:

```bash
php artisan test --compact tests/Unit/CqrsContractTest.php
```

Cada violación actual se congela en `tests/cqrs-baseline.php`:

```php
<?php

declare(strict_types=1);

return [
    'C1' => [
        'app/Common/Orders/Commands/UpdateOrder/UpdateOrderCommand.php',
    ],
];
```

Reglas:

- Solo entran violaciones existentes; no se añaden nuevas.
- Cada entrada lleva ticket y se retira en la siguiente ola.
- El baseline solo puede encoger.

## 8. OpenCode / agentes

Añade a `opencode.json`:

```json
{
    "instructions": [".agents/rules/*.md", ".agents/rules/shared/*.md"]
}
```

El bloque de `AGENTS.md` ya apunta a las reglas. Si el proyecto usa `.agents/rules/local/`,
esas reglas solo pueden añadir restricciones.

## 9. Verifica

```bash
vendor/bin/conventions check
make conventions-check
git status
git add .
git commit -m "chore: adopt laravel-conventions"
```

Criterios de aceptación:

- `conventions check` en verde.
- El `sha256` del test canónico coincide con el documentado.
- El baseline existe y no crece.
- El CI falla si se edita un archivo canónico.

## Proyecto nuevo

1. Crea el proyecto Laravel.
2. Instala y sincroniza el paquete.
3. Copia el `pint.json` del paquete si quieres el set estricto.
4. Añade el job de CI.
5. Empieza con el baseline vacío.
