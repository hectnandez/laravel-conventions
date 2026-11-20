# Autorización

Aplica a `app/Policies/**`, `app/Common/**/Commands/**` y `app/Common/**/Queries/**`.

## Identidad

- El actor viaja en el DTO (`actorId`), no se lee del guard dentro del Command (G1).
- Excepción: los Commands de autenticación pueden usar `Auth::guard(...)->attempt()`.
- Un Command que necesita el usuario completo lo recibe como DTO o lo carga por ID.

## Policies

- Una policy por modelo con reglas explícitas (`viewAny`, `view`, `create`, `update`,
  `delete`, y acciones de dominio).
- Las policies consultan permisos o ownership, nunca roles hardcodeados.
- El panel y la API comparten la misma policy; no se duplica la regla.

## Permisos

- Los nombres de permisos son constantes en un catálogo (`PermissionCatalog` o similar).
- Los roles se resuelven con un servicio único (`RoleResolver`), no consultando tablas
  pivote a mano.
- Un permiso nuevo se añade al catálogo y al seeder idempotente; no se escribe como
  string suelto en el código.

## Ownership

- El acceso a registros propios se comprueba por `user_id`/`owner_id` en la policy o en la
  Query, nunca solo ocultando el botón en la UI.
- Una regla de negocio no depende de que la pantalla oculte una acción: se valida en el
  Command.

## Prohibido

- `auth()`, `Auth::user()` o `Auth::id()` dentro de Commands (salvo la excepción G1).
- Comprobar roles por nombre en controllers o vistas.
- Devolver datos de otro tenant/owner por falta de filtro en la Query.
