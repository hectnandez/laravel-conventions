# Docker y entorno local

Aplica a `docker-compose*.yml`, `compose.conventions.yml`, `Makefile` y `docker-compose/**`.

## Regla base

Todo se ejecuta en contenedores. No se asume PHP, Composer, Node ni Artisan instalados en
el host.

```bash
make up
make shell
docker compose exec -T app php artisan <comando>
docker compose exec -T app composer <comando>
```

## Base compartida

- `compose.conventions.yml` se sincroniza desde el paquete y no se edita a mano.
- El `docker-compose.yml` del proyecto lo incluye y añade lo específico (redes, minio,
  rabbitmq, scripts de init, puertos propios).
- Cada proyecto define en `.env`:

```dotenv
PROJECT_SLUG=mi-proyecto
APP_PORT=8000
DB_FORWARD_PORT=3306
```

- Los servicios de la base usan `${PROJECT_SLUG}` para `container_name`, para no chocar
  con otros proyectos en la misma máquina.

## Make targets

| Target | Efecto |
| --- | --- |
| `make up` | Levanta el stack. |
| `make down` | Lo baja. |
| `make shell` | Shell en el contenedor `app`. |
| `make logs` | Logs en vivo. |
| `make tests` | Pint + PHPStan + tests del proyecto. |
| `make conventions-check` | Verifica los artefactos canónicos. |
| `make conventions-sync` | Sincroniza los artefactos canónicos. |

## Reglas

- No publicar servicios que no necesiten acceso desde el host (redis, rabbitmq).
- Un servicio nuevo se justifica y se documenta en el README.
- Los healthchecks son obligatorios para `db` y `redis` (la base ya los trae).
- No meter secretos reales en `docker-compose.yml`; usar `.env`.
- El `Dockerfile` vive en `docker-compose/` y comparte targets `app` y `supervisor`.
