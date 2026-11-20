# Excepciones de dominio

Aplica a `app/Common/**`, `app/Exceptions/**` y `bootstrap/app.php`.

## Jerarquía

```
App\Exceptions\DomainException (abstracta, extiende \Exception)
├── BusinessRule   → 422
├── Conflict       → 409
├── NotFound       → 404
└── Forbidden      → 403
```

- `DomainException` define `$domain`, `$errorCode`, `getDomain()`, `getErrorCode()` y
  `toArray()`.
- Las excepciones de negocio reciben **clave de traducción + parámetros**, nunca texto
  literal.
- El código HTTP viaja en `getCode()` y se mapea una sola vez en `bootstrap/app.php`.

## Uso

```php
use App\Exceptions\BusinessRule;

throw new BusinessRule('messages.orders.quantity_positive', ['quantity' => $quantity]);
```

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (DomainException $exception) {
        return response()->json([
            'error' => [
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ],
        ], $exception->getCode() ?: 500);
    });
})
```

## Reglas

- Prohibido `*HttpException` dentro de `app/Common`.
- Prohibido `\Exception` / `\RuntimeException` genéricos en Commands y Queries.
- Prohibido devolver un mensaje de error como valor de retorno (D1-bis).
- Prohibido mapear excepciones a HTTP en controllers, middleware o Commands.
- Una excepción nueva se añade a la jerarquía, no se duplica en otro namespace.
