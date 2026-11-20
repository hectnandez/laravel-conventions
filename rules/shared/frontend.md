# Frontend

Aplica a `resources/**`, `app/Filament/**` y `app/Livewire/**`.

## Stack

- **Filament** para paneles de administración.
- **Livewire 3** para componentes interactivos; **Alpine.js** para interacción local.
- **Tailwind CSS v4** para estilos; **Vite** para el build.
- No se introduce React/Vue/Inertia sin aprobación explícita.

## Livewire

- Estado en el servidor; el componente refleja el estado, no lo duplica.
- Validación con reglas del componente o FormRequest; autorización en la acción.
- `wire:model` para binding, `wire:click` para acciones, `wire:loading` para estados.
- Evitar queries en la vista: calcular en `mount()` o en métodos del componente.
- Un componente = una responsabilidad; extraer lógica de negocio a Commands/Queries.

## Filament

- Los Resources no contienen reglas de negocio: delegan en Commands/Queries.
- Las tablas pueden usar una Query como `resolveQuery` y devolver `Builder` (D3).
- Formularios con validación declarativa y `mutateFormDataBeforeSave` solo para
  transformaciones de presentación.
- Autorización por policies y permisos, no por `visible()` únicamente.

## Tailwind

- Seguir las utilidades existentes del proyecto antes de introducir patrones nuevos.
- Evitar CSS ad-hoc si una utilidad cubre el caso.
- Extraer componentes Blade/Livewire para patrones repetidos.

## Build

- Si un cambio de UI no se refleja, el manifest de Vite puede estar viejo:
  `npm run build` o `npm run dev`.
- No commitear `public/build` ni assets generados.
