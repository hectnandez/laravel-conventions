# Idioma y textos

Aplica a todo el código.

## Regla base

Ningún texto visible se escribe en el código. Va en `lang/{locale}/*.php` y se usa con
`__('dominio.clave')`.

## Claves

- Estructura por dominio: `messages.orders.quantity_positive`,
  `enums.appointment_status.confirmed`, `fields.email`.
- Una clave, un significado. No reutilizar claves con parámetros distintos.
- Los parámetros se pasan como array: `__('messages.orders.minimum', ['min' => 3])`.

## Enums

- Las etiquetas de un enum salen de `enums.*`.
- El valor persistido es el `value` del backed enum, no la etiqueta traducida.
- Un enum nuevo añade sus etiquetas en todos los locales soportados.

## Errores de negocio

- `BusinessRule`/`Conflict` reciben la clave, no el texto:

```php
throw new BusinessRule('messages.orders.quantity_positive');
```

- Prohibido concatenar traducciones o construir mensajes con `sprintf` en el dominio.

## Nombres

- Nombres de dominio en el idioma del negocio; lo técnico, en inglés.
- Textos de interfaz en el idioma del proyecto; el código y los identificadores, en inglés.
- Fechas, monedas y números se formatean en la capa de presentación, no en el dominio.
