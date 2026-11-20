# laravel-conventions

Reglas y artefactos canónicos compartidos para proyectos Laravel. Centraliza lo que no
debe divergir entre repositorios: el contrato CQRS, las excepciones de dominio, las reglas
para agentes, el target de Makefile, la base de Docker Compose, la plantilla de CI y la de
despliegue.

El paquete **no** contiene código de aplicación ni dependencias de Laravel. Solo textos,
archivos canónicos y un CLI en PHP plano.

## Requisitos

- PHP 8.4+ (para ejecutar el CLI).
- Composer 2.
- Git.

## Instalación en un proyecto

El paquete se distribuye desde GitHub. Añade el repositorio y la dependencia de desarrollo:

```bash
composer config repositories.laravel-conventions vcs https://github.com/hectnandez/laravel-conventions.git
composer require --dev hectnandez/laravel-conventions:^1.0
```

Luego sincroniza:

```bash
vendor/bin/conventions sync
```

Esto copia los artefactos, crea el baseline si falta, inyecta el bloque de `AGENTS.md` y
escribe `.conventions.lock.json`. El lock **se commitea**.

> Antes del primer `sync`, haz commit del estado actual. El sync puede reemplazar
> archivos existentes (`app/Common/Contracts`, `app/Exceptions`) para alinearlos con el
> canon. Revisa el diff antes de continuar.

## Comandos

```bash
vendor/bin/conventions sync      # instala/actualiza artefactos y escribe el lock
vendor/bin/conventions check     # falla si hay deriva (lo que corre el CI)
vendor/bin/conventions diff      # muestra solo las diferencias
vendor/bin/conventions update    # sync sobrescribiendo cambios locales
vendor/bin/conventions version
vendor/bin/conventions help
```

Opciones:

| Opción | Efecto |
| --- | --- |
| `--project=<ruta>` | Proyecto destino. Por defecto, el directorio actual. |
| `--force`, `-f` | Sobrescribe archivos canónicos modificados a mano. |
| `--json` | Salida JSON para CI. |

`check` verifica tres cosas: que el manifiesto coincida con el lock, que el lock coincida
con la versión instalada y que el working tree coincida con el lock. Detecta tanto una
edición local como un paquete desactualizado.

## Qué sincroniza

| Artefacto | Destino | Modo |
| --- | --- | --- |
| Test canónico CQRS | `tests/Unit/CqrsContractTest.php` | copy |
| Baseline de excepciones | `tests/cqrs-baseline.php` | stub |
| Contratos CQRS | `app/Common/Contracts/` | copy |
| Excepciones de dominio | `app/Exceptions/` | copy |
| Regla CQRS | `.agents/rules/cqrs-conventions.md` | copy |
| Reglas compartidas | `.agents/rules/shared/` | copy |
| Skills compartidas | `.agents/skills/` | copy |
| Target de Makefile | `.conventions/Makefile` | copy |
| Base de Compose | `compose.conventions.yml` | copy |
| Base de PHPStan | `phpstan.base.neon` | copy |
| Bloque de `AGENTS.md` | `AGENTS.md` | managed-block |
| Config estricta de Pint | — | reference |

`copy` se verifica por hash; `stub` solo se crea si falta; `managed-block` se inyecta entre
los marcadores `<!-- conventions:start/end -->`; `reference` es solo documentación.

## Integración

### Makefile

```make
include .conventions/Makefile
```

Después del include puedes usar `make conventions-check` y `make conventions-sync`.

### CI (GitLab)

```yaml
include:
    - remote: 'https://raw.githubusercontent.com/hectnandez/laravel-conventions/v1.0.0/ci/conventions.yml'

conventions:
    extends: .conventions:check
```

O añade el job inline con la imagen del proyecto:

```yaml
conventions:
    stage: quality
    image: $CI_REGISTRY_IMAGE:app-latest
    before_script: []
    script:
        - ./vendor/bin/conventions check --json
```

### AGENTS.md y agentes

`sync` inyecta un bloque gestionado en `AGENTS.md` que apunta a las reglas. Si usas
OpenCode, añade también:

```json
{
    "instructions": [".agents/rules/*.md", ".agents/rules/shared/*.md"]
}
```

Laravel Boost regenera su propia sección de `AGENTS.md`; el bloque gestionado no se toca.

## Excepciones por proyecto

Crea `.conventions.json` en la raíz del proyecto:

```json
{
    "ignore": ["pint"],
    "deviations": {
        "exceptions": "TICKET-123: el proyecto mantiene su propia jerarquía hasta Q2"
    }
}
```

- `ignore` omite artefactos por `id` en `sync` y `check`.
- `deviations` es documentación; cada excepción debe tener ticket y fecha de retiro.
- El baseline CQRS (`tests/cqrs-baseline.php`) solo puede encoger.

## Adopción

Guía paso a paso en [`docs/adoption.md`](docs/adoption.md).

## Versionado

- Semver en `VERSION` y `conventions.json` (deben coincidir).
- Un cambio de regla sube la minor; una regla nueva obligatoria sube la major.
- El test canónico lleva su propio header `CANONICAL v1.0` y un `sha256` documentado en
  `rules/cqrs-conventions.md` (§I3).
- El repo es público: **no** incluyas nombres de clientes, hosts, IPs ni rutas internas.

## Desarrollo

```bash
php tests/run.php        # suite del CLI
composer validate        # valida composer.json
```

No se necesita `composer install` para ejecutar el CLI: `bin/conventions` incluye un
autoloader mínimo.

## Estructura

```
bin/            CLI
src/            implementación (manifest, lock, sync, check, managed block)
rules/          reglas canónicas en Markdown
artifacts/      archivos que se copian a los proyectos
docker/         base de Compose (referencia parametrizada)
make/           targets compartidos
ci/             job de GitLab
deploy/         recetas base de Deployer y plantilla de stage
skills/         skills de agentes compartidas
snippets/       bloque gestionado de AGENTS.md
docs/           guía de adopción
```

## Licencia

MIT.
