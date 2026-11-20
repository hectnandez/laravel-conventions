# Convenciones CQRS — v1.7

Aplica a todos los proyectos Laravel que adopten este paquete. Este documento es la
fuente de verdad. Cambiar una regla exige subir la versión, actualizar el test canónico
y añadir una entrada al registro de decisiones.

Ruta canónica en el proyecto: `.agents/rules/cqrs-conventions.md`.
Test canónico: `tests/Unit/CqrsContractTest.php` (ver §I).

## Registro de decisiones

| ID | Decisión | Valor | Motivo |
|----|----------|-------|--------|
| D-A1 | Mediación | **Sin bus de comandos** | La inyección directa evita divergencia sin beneficio proporcional |
| D-A2 | Modelo de lectura | **CQRS lite**: Commands y Queries comparten los modelos Eloquent | No hay read models separados |
| D-A3 | Retorno de Command | Agregado afectado o `void` | Evita un round-trip extra por POST |
| D-A4 | Excepciones de dominio | `App\Exceptions\DomainException` + `BusinessRule`/`NotFound`/`Forbidden`/`Conflict` | Jerarquía única mapeada a HTTP |
| D-A5 | Rigor transaccional | Obligatorio solo si el Command escribe en ≥2 tablas | Evita transacciones innecesarias en mono-escritura |
| D-A6 | Violaciones heredadas | Congeladas en `tests/cqrs-baseline.php`, solo decreciente | Permite activar las reglas sin bloquear la adopción |

## A · Estructura

- **A1.** `app/Common/{Contexto}/Commands/{Accion}/{Accion}Command.php` + `{Accion}CommandDTO.php`.
  Carpetas en plural. Prohibido `Command/` o `Query/` en singular.
- **A2.** `app/Common/{Contexto}/Queries/{Accion}/{Accion}Query.php` + `{Accion}QueryDTO.php`.
- **A3.** Una acción = una carpeta con Command + DTO. Sin helpers dentro de la carpeta.
- **A4.** Acción descriptiva sin prefijo del contexto: `CreateVendor`, no `VendorCreate`.
- **A5.** Marcadores `App\Common\Contracts\{CommandInterface,QueryInterface}`; documentan
  `execute(CommandDTO)` / `handle(QueryDTO)`.

## B · DTO

- **B1.** `final readonly class`, promoted properties, `declare(strict_types=1)`.
- **B2.** Sin dependencias HTTP: prohibido `Request`, `Validator`, `Response`.
- **B3.** Solo escalares, IDs, enums y DTOs planos. Prohibido entidades Eloquent y `Auth::user()`.
- **B4.** El controller construye el DTO con `new`. Nada de `fromArray()` en los CommandDTO.

## C · Firma

- **C1.** `execute({Accion}CommandDTO $command)`: exactamente 1 parámetro, siempre el DTO.
- **C2.** `handle({Accion}QueryDTO $query)`: exactamente 1 parámetro, siempre el DTO.
- **C3.** Nombre del parámetro: `$command` / `$query`.

## D · Retorno (decisión D-A3)

- **D1.** Command devuelve `void` | modelo Eloquent | DTO de dominio | `int` (solo procesos por lote).
  Prohibido `bool`, `string`, `array`, `Collection`, `Builder`.
  Excepción: un Command de infraestructura puede devolver la ruta de un archivo subido.
- **D1-bis.** Prohibido devolver un mensaje de error como valor de retorno. Los fallos de
  regla de negocio se lanzan como `BusinessRule`.
- **D2.** Query devuelve `Collection` | `LengthAwarePaginator` | DTO | `array` tipado.
- **D3.** Prohibido devolver `Builder` desde una Query **que alimente un controller HTTP**.
  Excepción: las Queries usadas como `resolveQuery` de una tabla Filament pueden devolver
  `Builder`, porque Filament pagina, filtra y ordena en servidor sobre él.

## E · Transacciones

- **E1.** Todo Command que escriba en ≥2 tablas se envuelve en `DB::transaction()`.
  Mono-escritura: opcional.
- **E2.** Prohibido `beginTransaction()` / `commit()` / `rollBack()` manual.
- **E3.** Un Command no abre transacción si ya corre dentro de una (sin anidamiento).
- **E4.** Cada entrypoint asíncrono (jobs, handlers de cola, comandos de consola) despacha
  **un único** Command y no abre transacción propia: la transacción vive en el Command (E1).
  Si un entrypoint llegara a orquestar ≥2 Commands, envolverá esa orquestación en
  `DB::transaction()`. No existe un runner genérico.

## F · Errores

- **F1.** Prohibido `*HttpException` en `app/Common`.
- **F2.** Base única `App\Exceptions\DomainException`. Hermanos canónicos:
  `BusinessRule` (422) y `Conflict` (409). `NotFound` y `Forbidden` heredan de la misma
  base; su consolidación es deuda declarada del burn-down.
- **F3.** `BusinessRule` y `Conflict` reciben clave de traducción + parámetros, nunca texto literal.
- **F4.** Prohibido `\Exception` / `\RuntimeException` genéricos en Commands/Queries.
- **F5.** El mapeo excepción→HTTP vive solo en `bootstrap/app.php` y usa el código de la
  excepción (`getCode()`), que debe ser el status HTTP (422, 404, 403, 409…).

## G · Dependencias y límites

- **G1.** Prohibido `auth()`, `Auth::user()`, `Auth::id()` dentro de Commands: el `actorId`
  viaja en el DTO. Excepción: los Commands de autenticación pueden usar
  `Auth::guard(...)->attempt()` porque validan credenciales, no leen un actor existente.
- **G2.** Prohibido `Request`, `Response`, `Log::`, escritura en `Cache` dentro de Queries.
- **G3.** Un Command puede inyectar Repositorios, otros Commands y Queries.
- **G4.** Una Query puede inyectar Repositorios y otras Queries.
- **G5.** Prohibido `App\Models\` y `DB::` en `app/Http/Controllers`.

## H · Entrypoints

- **H1.** Controller ≤ 25 líneas: FormRequest → DTO → Command/Query → Resource.
- **H2.** Validación siempre en FormRequest, nunca inline.
- **H3.** Respuesta por `ApiResponse` + Resource.

## I · Verificación

- **I1.** `tests/Unit/CqrsContractTest.php` es idéntico byte a byte en todos los proyectos
  que adopten estas reglas. Header `CANONICAL v1.0`. Prohibido editarlo.
- **I2.** Las excepciones por proyecto viven en `tests/cqrs-baseline.php`, con formato
  `['REGLA' => ['ruta/relativa.php', ...]]`.
- **I3.** El CI compara el `sha256` del test contra `contract_test_sha256`. Valor esperado:
  `e2b1a21d4ef5c1c045c80446ec455c4f714b4d5d99f294161dafbf4e6c85067b`
- **I4.** El baseline solo puede encogerse. Cada ola quema sus entradas.
- **I5.** Reglas automatizadas por el test: A1, A5, B1, C1, C2, E2, F1, G1, G5.
  El resto se revisa en PR contra este documento.

## J · Alcance y excepciones

- **J1.** Los controllers que solo son **adaptadores de pasarela de pago** quedan exentos de
  CQRS: delegan en `App\External\Payment\*` y en la estrategia de pasarela.
- **J2.** Los controllers que solo envuelven un módulo legacy se migran cuando se desmonte
  ese módulo, no antes. La excepción se registra en el baseline.
- **J3.** Un controller stub sin lógica de negocio no necesita CQRS.
- **J4.** Cualquier otro controller con lógica real debe migrarse y registrarse como deuda
  con ticket hasta que ocurra.

## Changelog

| Versión | Fecha | Cambio |
|---------|-------|--------|
| 1.0 | 2026-11-19 | Versión inicial tras alinear los proyectos |
| 1.1 | 2026-11-19 | F2/F5 precisadas a la implementación: `BusinessRule`/`Conflict` creados, mapeo por `getCode()` |
| 1.2 | 2026-11-19 | D3 acotada: `Builder` permitido solo como `resolveQuery` de tablas Filament |
| 1.3 | 2026-11-19 | D1: excepción documentada para subida de archivos; nuevo D1-bis |
| 1.4 | 2026-11-19 | E4: se descarta `CommandRunner`; la transacción vive en el Command |
| 1.5 | 2026-11-19 | Nuevo bloque J: adaptadores de pasarela y legacy |
| 1.6 | 2026-11-19 | G1: excepción documentada para Commands de autenticación |
| 1.7 | 2026-11-20 | Versión pública genérica: sin referencias a proyectos, hosts ni módulos internos |
