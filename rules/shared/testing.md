# Testing

Aplica a `tests/**`, `phpunit.xml` y `pest.php`.

## Framework

- El proyecto usa **PHPUnit** o **Pest**; no se mezclan estilos nuevos.
- Las clases nuevas siguen el estilo dominante del repo.
- Un test por comportamiento: nombre descriptivo, sin `test1`/`test2`.

## Qué probar

- Camino feliz, caminos de error y bordes.
- Reglas de negocio en los Commands (no solo el endpoint).
- Autorización: un usuario sin permiso no puede ejecutar la acción.
- Persistencia: el estado final de la base de datos.

## Datos

- Usar factories con estados; no crear modelos a mano si el factory lo cubre.
- `RefreshDatabase` o transacciones según la convención del proyecto.
- Nunca depender del orden de ejecución ni de datos de demo.

## Comandos

- Ejecutar el conjunto mínimo que cubre el cambio:

```bash
php artisan test --compact tests/Feature/MiTest.php
php artisan test --compact --filter=miCaso
```

- Antes de cerrar: Pint y PHPStan según el proyecto.

## Contrato CQRS

- `tests/Unit/CqrsContractTest.php` es canónico: **no se edita**.
- Su `sha256` debe coincidir con el documentado en `cqrs-conventions.md` (§I3).
- Las violaciones heredadas se congelan en `tests/cqrs-baseline.php`, con formato
  `['REGLA' => ['ruta/relativa.php', ...]]`.
- El baseline **solo puede encoger**: cada entrada lleva ticket y fecha de retiro.

## Prohibido

- Borrar tests o archivos de test sin aprobación.
- Tests que dependen de red, hora real o servicios externos sin fake.
- `sleep()` para sincronizar; usar `Queue::fake()`, `Event::fake()`, etc.
