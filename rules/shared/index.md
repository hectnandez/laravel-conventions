# Reglas compartidas

Antes de planificar o editar, busca en la tabla el glob que coincide con los paths que vas
a tocar y lee el archivo indicado. Si varios coinciden, lee todos.

| Aplica a | Regla |
| --- | --- |
| `**` | [language.md](language.md) |
| `app/Common/**` | [cqrs-conventions.md](../cqrs-conventions.md) · [exceptions.md](exceptions.md) |
| `app/Http/Controllers/**`, `routes/**` | [api.md](api.md) |
| `app/Policies/**`, `app/Common/**/Commands/**` | [auth.md](auth.md) |
| `tests/**`, `phpunit.xml`, `pest.php` | [testing.md](testing.md) |
| `resources/**`, `app/Filament/**`, `app/Livewire/**` | [frontend.md](frontend.md) |
| `docker-compose*`, `compose.conventions.yml`, `Makefile`, `docker-compose/**` | [docker.md](docker.md) |
| `deploy/**`, `deploy*.php`, `.gitlab-ci.yml` | [deploy.md](deploy.md) |

## Precedencia

1. `cqrs-conventions.md` es la fuente de verdad de la arquitectura.
2. Estas reglas compartidas no se editan en el proyecto: se sincronizan con
   `vendor/bin/conventions sync`.
3. Las reglas específicas del proyecto viven en `.agents/rules/local/` y solo pueden
   **añadir** restricciones, nunca relajar las compartidas. Cada excepción se registra en
   `.conventions/deviations.md` con ticket.
