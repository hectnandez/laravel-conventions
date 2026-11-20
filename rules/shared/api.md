# API REST

Aplica a `app/Http/Controllers/**`, `app/Http/Requests/**`, `app/Http/Resources/**` y
`routes/**`.

## Controllers

- **Invokable** (una sola acción) e implementan `HasMiddleware`:

```php
final class ShowController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('set.api.context')];
    }

    public function __invoke(string $slug): CollectionResource
    {
        return new CollectionResource(
            app(GetCollectionQuery::class)->handle(new GetCollectionQueryDTO($slug)),
        );
    }
}
```

- Devuelven un `JsonResource`, nunca un DTO o un modelo.
- Validan con un FormRequest: nada de validación inline.
- No tocan `App\Models\` ni `DB::` (G5).
- Máximo ~25 líneas (H1).

## Requests y Resources

- Un FormRequest por endpoint con validación y `authorize()`.
- El Resource decide qué se expone; nunca `return $model`.
- Paginación explícita (`LengthAwarePaginator`) y metadatos consistentes.

## Rutas

- Versionadas: `routes/api.php` con `Route::prefix('v1')` y nombres `api.v1.*`.
- Sin lógica en el archivo de rutas: solo mapeo a controllers.
- Middleware por controller (`HasMiddleware`), no en el grupo, salvo el contexto global.

## Errores

- El mapeo excepción→HTTP vive en `bootstrap/app.php` (ver `exceptions.md`).
- Respuestas de error con la misma forma en toda la API.
- Un 404 de recurso es `NotFound`; un 422 de negocio es `BusinessRule`; un 409 es `Conflict`.

## Contexto de petición

- Los datos de contexto (moneda, idioma, tenant, token de carrito) se resuelven en un
  middleware y se guardan en un objeto request-scoped.
- Los controllers no leen cabeceras a mano ni consultan el contenedor para el contexto.
