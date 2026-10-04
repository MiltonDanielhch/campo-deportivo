# 📁 ROADMAP_MODULO_6_OPERACION_DASHBOARD.md

**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni  
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke  
**Versión:** 2.1.0 · **Formato:** Guía Arquitectónica Explicativa  
**Tiempo estimado:** 10–12 horas · **No bloquea Módulo 7** — es complementario y habilita integración final de asistencia en sitio

> **Nota de renumeración y contexto:** este módulo implementa la Épica F del Documento 3 v3 (HU-F1 a HU-F4). Originalmente se pensó que bloqueaba el Módulo 7, pero con el avance actual del proyecto (Módulo 7 backend cerrado, asistencia implementada, integración SIREB/Paitití activa), ahora es **complementario**: proporciona la pantalla operativa de sitio que el funcionario de control necesita, y habilita la integración final de la asistencia (7.5.B).

> **Objetivo del Módulo:** permitir que el Funcionario de Control verifique en sitio reservas válidas de sus campos asignados, que Administración y Gerencia vean el mapa global de ocupación, y que Gerencia consulte ingresos operativos, horas pico y clientes frecuentes. Ninguna historia depende del Core de Recaudaciones; todas leen datos que Canchas ya tiene localmente (`reservas`, `solicitudes_reserva`).

> **Relación con SIREB/Paitití:** los reportes de ingresos que genera este módulo son la **vista operativa de Canchas**, no la conciliación financiera oficial. Paitití/SIREB es la fuente autoritativa de liquidaciones y pagos. Esta distinción debe quedar explícita en la UI del dashboard gerencial.

---

## 🗺️ Mapa del Módulo

```
Módulo 6
├── Fase 6.0 → Reconciliación: confirmar roles, middleware y dependencias
├── Fase 6.1 → Backend: ocupación en tiempo real y verificación de código
├── Fase 6.2 → Backend: mapa global de ocupación
├── Fase 6.3 → Backend: reportes gerenciales (ingresos, horas pico, clientes frecuentes)
├── Fase 6.4 → Panel Web: pantalla de ocupación para Funcionario de Control
├── Fase 6.5 → Panel Web: mapa global y dashboard gerencial
└── Fase 6.6 → Smoke test final y commit de cierre
```

---

## 🔍 Verificaciones Fase 6.0 — Resultados

Antes de implementar código, se verificaron estas precondiciones sobre el estado actual del repo (post-Módulo 7 y post-integración SIREB):

### ✅ Confirmado

- **Rol `gerencia`**: existe en `RolesSeeder` con permisos `['*']` (wildcard total). Se usará para mapa global y reportes.
- **Middleware real**: el proyecto usa `auth.oauth` + alias `role`, **no** `auth:sanctum`. Las rutas de este módulo seguirán el mismo patrón que Fases 7.1/7.2/7.3.
- **Índices de performance**: todos existen en `2026_09_01_142141_add_performance_indexes.php`:
  - `idx_reservas_campo_fecha` (compuesto)
  - `idx_solicitudes_pagador` (compuesto: `ci_nit_pagador` + `telefono_pagador`)
  - `idx_detalle_campo_fecha` (parcial, WHERE estado_solicitud IN pendiente/confirmada)
  - `idx_solicitudes_pendientes_expiracion` (parcial)
  - `idx_campos_lat_lng`
  - `idx_asignaciones_funcionario`
- **Dependencias frontend**: `recharts@^3.8.0` ya instalado. Falta instalar `leaflet`, `react-leaflet` y `@types/leaflet` en Fase 6.5.
- **Sidebar actual**: `Reservas` apunta a `/panel/reservas` (pantalla real del Módulo 7). No se renombra.
- **DisponibilidadService**: consulta `solicitud_reserva_detalle.estado_solicitud` (columna denormalizada). `OcupacionService` reutilizará el mismo criterio.

### 📋 Matriz de roles resultante

| Ruta | Roles |
|---|---|
| `GET /api/v1/ocupacion/mis-campos` | `admin_parametricas`, `admin_reservas`, `funcionario_control` |
| `GET /api/v1/ocupacion/verificar/{codigo}` | `admin_parametricas`, `admin_reservas`, `funcionario_control` |
| `GET /api/v1/ocupacion/mapa-global` | `admin_parametricas`, `gerencia` |
| `GET /api/v1/reportes/ingresos` | `admin_parametricas`, `gerencia` |
| `GET /api/v1/reportes/horas-pico` | `admin_parametricas`, `gerencia` |
| `GET /api/v1/reportes/clientes-frecuentes` | `admin_parametricas`, `gerencia` |

### 🎯 Decisiones tomadas

| # | Decisión | Justificación |
|---|---|---|
| 1 | **Opción A para `gerencia`** | Ya existe el rol con permisos wildcard |
| 2 | **Opción A para asistencia** (read-only) | No mezclar M6 con deuda de M7. La acción de marcar/desmarcar queda para Fase 7.5.B |
| 3 | **No renombrar `Reservas`** | `/panel/reservas` es pantalla operativa del Módulo 7. Se agrega `Ocupación` como ítem paralelo |
| 4 | **Middleware `auth.oauth`** | Consistente con Fases 7.1/7.2/7.3 y resto del sistema |
| 5 | **Eliminar "franjas bloqueadas temporalmente"** del alcance | No existe modelo de bloqueo manual en BD. Los estados considerados son solo `pendiente` y `confirmada` |
| 6 | **Nota financiera obligatoria** en dashboard | Los reportes son vista operativa. Paitití/SIREB es fuente autoritativa de conciliación |

### 🔬 Pendiente de validación manual

Antes de Fase 6.3, correr en `psql` o tinker:

```sql
EXPLAIN ANALYZE
SELECT * FROM reservas
WHERE campo_id = '<UUID-EXISTENTE>'
  AND fecha_reserva BETWEEN '2026-10-01' AND '2026-10-31';

EXPLAIN ANALYZE
SELECT * FROM solicitudes_reserva
WHERE ci_nit_pagador = '1234567';

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 6.0 — Reconciliación: confirmar roles, middleware y dependencias

Antes de implementar cualquier código, se deben verificar estas precondiciones que afectaron el diseño original del roadmap.

## Verificaciones obligatorias

### 1. Confirmar existencia del rol `gerencia`

El roadmap original asume `role:admin_parametricas,gerencia` para mapa global y reportes. Si el rol `gerencia` no existe en el seeder de roles, hay tres opciones:

- **Opción A:** Crear el rol `gerencia` con permisos de solo lectura sobre reportes y mapa global.
- **Opción B:** Usar `admin_parametricas` y `admin_reservas` como roles autorizados.
- **Opción C:** Dejar `gerencia` como rol futuro y no exponer rutas todavía.

Decisión recomendada: **Opción B** si no hay requerimiento explícito de separación, **Opción A** si el GAD necesita distinción clara entre operadores y gerencia.

### 2. Confirmar middleware de autenticación

El roadmap original menciona `auth:sanctum`, pero el proyecto actual usa middleware OAuth propio:

```text
auth.oauth
role:...
```

Sanctum se usa en tests con `Sanctum::actingAs()` gracias a que `TestCase` salta globalmente `VerificaTokenOAuth`. Para rutas reales de producción, el middleware correcto es el que ya usa el resto del sistema.

### 3. Confirmar índices de performance

Verificar que las migraciones del Módulo 1 crearon efectivamente:

```text
idx_reservas_campo_fecha
idx_solicitudes_pagador
```

Estos índices son críticos para que las consultas de reportes (Fase 6.3) no hagan full table scan.

### 4. Confirmar dependencias frontend

Antes de instalar librerías en Fase 6.5, verificar en `web-admin/package.json` si ya existen:

```text
react-leaflet
leaflet
recharts
```

Si ya están, no duplicar instalación. Si no están, instalar con tipos TypeScript correspondientes.

### 5. Definir alcance de asistencia en Ocupación

El Módulo 7 ya implementó backend de asistencia (`MarcarAsistenciaService`) y frontend en detalle de reserva (`DialogDetalleReserva.tsx`). Para la pantalla de Ocupación hay dos opciones:

- **Opción A (recomendada):** Mostrar estado de asistencia como dato informativo read-only. Dejar la acción de marcar/desmarcar para Fase 7.5.B.
- **Opción B:** Integrar la acción de marcar asistencia directamente en Ocupación, adelantando 7.5.B.

Decisión recomendada: **Opción A** para no mezclar cierre de Módulo 6 con deuda de Módulo 7.

### 6. Definir estructura de navegación en sidebar

El roadmap original decía renombrar "Reservas" → "Ocupación". Esto **ya no es válido** porque `/panel/reservas` es ahora una pantalla operativa real del Módulo 7.

Estructura correcta:

```text
/panel/ocupacion → Ocupación en sitio (Fase 6.4)
/panel/reservas → Gestión operativa de reservas (Módulo 7, ya existe)
/panel/reportes/* → Dashboard y reportes gerenciales (Fase 6.5)
/panel/ocupacion/mapa → Mapa global (Fase 6.5)
```

En sidebar:

```text
Ocupación (funcionario_control + admin)
Reservas (funcionario_control + admin, con filtros server-side)
Mapa Global (admin_parametricas + gerencia)
Dashboard (admin_parametricas + gerencia)
```

---

## Tareas de la Fase 6.0

```
[ ] Verificar roles existentes en backend/database/seeders/RolesSeeder.php
    → Confirmar si existe 'gerencia'.
    → Decidir Opción A/B/C y documentar.

[ ] Verificar middleware real en backend/routes/api.php
    → Confirmar uso de auth.oauth vs auth:sanctum.
    → Documentar middleware correcto para rutas nuevas.

[ ] Verificar índices de performance
    → EXPLAIN ANALYZE sobre consultas tipo:
        SELECT * FROM reservas WHERE campo_id = ? AND fecha_reserva BETWEEN ? AND ?
        SELECT * FROM solicitudes_reserva WHERE ci_nit_pagador = ?
    → Confirmar Index Scan sobre idx_reservas_campo_fecha e idx_solicitudes_pagador.
    → Si no existen o no se usan, crear migración de índices faltantes.

[ ] Verificar dependencias frontend en web-admin/package.json
    → Buscar react-leaflet, leaflet, recharts.
    → Documentar qué falta instalar en Fase 6.5.

[ ] Decidir alcance de asistencia en Ocupación
    → Opción A: read-only (recomendado).
    → Opción B: integrar marcar/desmarcar (adelanta 7.5.B).

[ ] Actualizar sidebar en web-admin/src/components/app-sidebar.tsx
    → Agregar ítem "Ocupación" apuntando a /panel/ocupacion.
    → Mantener "Reservas" apuntando a /panel/reservas.
    → Agregar ítems "Mapa Global" y "Dashboard" apuntando a /panel/ocupacion/mapa y /panel/reportes.

[ ] Documentar decisiones en docs/roadmap/ROADMAP_MODULO_6_OPERACION_DASHBOARD.md
    → Sección "Decisiones de Fase 6.0" con justificación de cada opción elegida.

[ ] Commit de la fase
    → Mensaje: "chore(modulo-6): reconciliación de roles, middleware y dependencias"
```

---

# FASE 6.1 — Backend: Ocupación en Tiempo Real y Verificación de Código

Implementa HU-F1. El objetivo real detrás de esta historia no es solo "ver una lista" — es que el Funcionario de Control, parado frente a una cancha, pueda confirmar en segundos si la persona que tiene enfrente reservó de verdad.

## Un límite que ya se decidió en el Documento 1 v3, ahora se aplica en código

La regla de negocio original es explícita: un funcionario de control **solo** ve los campos que tiene asignados. Esto se aplica del lado del servidor, no solo en la interfaz — de lo contrario, cualquiera con el token de un funcionario_control podría consultar la ocupación de canchas que no le corresponden simplemente cambiando un ID en la URL.

## Reutilización de DisponibilidadService

El `OcupacionService` no debe reinventar la lógica de franjas. Debe reutilizar o apoyarse en la misma base que `DisponibilidadService` del Módulo 3, pero con perspectiva interna/administrativa:

```text
DisponibilidadService → lo que ve el ciudadano: qué está libre.
OcupacionService → lo que ve el funcionario: qué está reservado/ocupado en sus campos.
```

## Estados considerados como ocupación

Solo estos estados de solicitud cuentan como ocupación:

```text
pendiente → reservada temporalmente, aún no paga/confirmada
confirmada → reserva válida operativa
```

Estados que **no** cuentan:

```text
expirada → liberó la franja
cancelada → liberó la franja
rechazada → nunca ocupó
```

## Asistencia como dato informativo

Si se eligió Opción A en Fase 6.0, la respuesta de ocupación puede incluir `asistencia_marcada_en` como dato informativo, sin exponer acciones de marcar/desmarcar. Esto permite al funcionario ver si la franja ya fue atendida.

## No exponer datos personales

La verificación de código y la ocupación **no** deben exponer:

```text
nombre_pagador
telefono_pagador
ci_nit_pagador (excepto en reporte de clientes frecuentes, Fase 6.3)
```

El propósito es verificar que existe una reserva válida, no ver datos de contacto.

---

## Tareas de la Fase 6.1

```
[ ] Crear OcupacionService
    → app/Services/OcupacionService.php
    → misCampos(Funcionario $funcionario, Carbon $fecha): obtiene los
      campos asignados a ese funcionario y, para cada uno, las franjas
      de ese día con su estado, reutilizando criterio de
      DisponibilidadService. Estados activos: 'pendiente'/'confirmada'.
      Muestra codigo_reserva de franjas confirmadas. Opcionalmente
      incluye asistencia_marcada_en si se eligió Opción A. No expone
      nombre_pagador ni telefono_pagador.
    → verificarCodigo(string $codigoReserva, Funcionario $funcionario):
      busca la reserva por código; si el funcionario tiene rol
      funcionario_control, valida además que el campo esté entre sus
      asignados — si no lo está, responde igual que si el código no
      existiera (404 uniforme, no filtrar existencia). Un
      admin_parametricas puede verificar cualquier código sin esa
      restricción.

[ ] Crear OcupacionController
    → app/Http/Controllers/Api/V1/OcupacionController.php
    → GET /api/v1/ocupacion/mis-campos?fecha=YYYY-MM-DD
      (middleware: auth.oauth, accesible para funcionario_control y
      admin_parametricas, admin_reservas).
    → GET /api/v1/ocupacion/verificar/{codigo_reserva}
      (misma protección).

[ ] Registrar rutas en backend/routes/api.php
    → Dentro del grupo auth.oauth existente.
    → Middleware role:funcionario_control|admin_parametricas|admin_reservas
      (ajustar según decisión de Fase 6.0 sobre gerencia).

[ ] Tests
    → backend/tests/Feature/Api/V1/OcupacionTest.php
    → Un funcionario_control con 2 campos asignados solo recibe la
      ocupación de esos 2.
    → Verificar un código de una reserva de un campo NO asignado
      responde 404 (igual que si no existiera).
    → Un admin_parametricas puede verificar cualquier código.
    → La respuesta nunca incluye nombre_pagador ni telefono_pagador.
    → Opcionalmente: si se eligió Opción A, verificar que
      asistencia_marcada_en aparece cuando corresponde.

[ ] Commit de la fase
    → Mensaje: "feat(ocupacion): ocupación filtrada por asignación y verificación de código de reserva"
```

---

# FASE 6.2 — Backend: Mapa Global de Ocupación

Implementa HU-F4. Exclusiva de `admin_parametricas` y `gerencia` (si existe) o `admin_reservas` (según decisión de Fase 6.0) — sin el filtro de asignación.

## Campos a mostrar

El mapa debe incluir campos en estos estados operativos:

```text
activo → verde/teal, reservable si está vinculado a SIREB
mantenimiento → ámbar, visible pero no reservable
```

Campos `inactivo` no aparecen en el mapa.

## Metadato administrativo: vínculo SIREB

Aunque el mapa no depende de SIREB para calcular ocupación, para administración es valioso indicar si un campo activo está vinculado a recaudaciones:

```text
campo.vinculado_sireb: true/false
campo.servicio_sireb_codigo: "SEDEDE-CS1" (si existe)
```

Esto ayuda a detectar paramétricas incompletas.

## Ocupación actual vs ocupación del día

Definir si el mapa muestra:

- **Ocupación actual (hora presente):** booleano `ocupado_ahora`.
- **Ocupación del día (resumen):** porcentaje, franjas ocupadas/totales.

Recomendación: ambos. El marcador del mapa muestra color según ocupación actual, y el tooltip/popup muestra resumen del día.

## No prometer "franjas bloqueadas temporalmente"

El roadmap original mencionaba "franjas bloqueadas temporalmente", pero no existe modelo de bloqueo manual en la BD. No incluir este concepto hasta que se implemente como feature futura (si hace falta).

---

## Tareas de la Fase 6.2

```
[ ] Extender OcupacionService
    → mapaGlobal(Carbon $fecha): todos los campos activos o en
      mantenimiento con coordenadas (latitud/longitud), estado
      operativo, vínculo SIREB (si existe), y resumen de ocupación
      del día:
        - franjas_totales
        - franjas_ocupadas (pendiente + confirmada)
        - franjas_libres
        - ocupado_ahora (boolean, si hay franja activa en este momento)
        - porcentaje_ocupacion_dia

[ ] Agregar la ruta
    → GET /api/v1/ocupacion/mapa-global?fecha=YYYY-MM-DD
    → Middleware: auth.oauth + role:admin_parametricas,gerencia
      (o admin_parametricas,admin_reservas según Fase 6.0).

[ ] Tests
    → El resumen de un campo con una reserva confirmada ahora mismo lo
      marca como ocupado_ahora = true.
    → Un funcionario_control no puede acceder a esta ruta (403).
    → Campos en mantenimiento aparecen en el mapa pero no como reservables.
    → Campos sin vínculo SIREB aparecen con vinculado_sireb = false.

[ ] Commit de la fase
    → Mensaje: "feat(ocupacion): mapa global de ocupación para administración y gerencia"
```

---

# FASE 6.3 — Backend: Reportes Gerenciales

Implementa HU-F2 y HU-F3. Se apoyan directamente en los índices que ya se crearon en el Módulo 1 (Anexo B del Documento 2 v3) — `idx_reservas_campo_fecha` para ingresos por campo y fecha, e `idx_solicitudes_pagador` para clientes frecuentes—. Vale la pena confirmar con `EXPLAIN ANALYZE` que efectivamente se usan.

## Ingresos: la vista de Canchas, no necesariamente la del Core

`ingresosPorCampo()` suma `reservas.monto_pagado` — un dato que Canchas calcula y controla por completo, sin depender del Core. Esto responde una pregunta operativa legítima ("¿cuánto factura cada cancha, cuántas reservas hubo"), pero **no reemplaza** el panel financiero de Paitití/SIREB, que es quien concilia el dinero efectivamente recibido.

### Verificación crítica antes de implementar

Confirmar que `reservas.monto_pagado` se llena con el monto confirmado por SIREB al momento de la confirmación de cobro. Revisar `ConfirmacionCobroService` y creación de `Reserva` para asegurar que:

```text
Cuando SIREB confirma pago → se crea Reserva con monto_pagado = monto SIREB
```

Si `monto_pagado` es local y SIREB devuelve otro monto, el reporte se desalineará. En ese caso, documentar la discrepancia y considerar refactor futuro (fuera de alcance de este módulo).

## Clientes frecuentes: agrupar por la clave estable, no por el nombre

Con la actualización del formulario de solicitante que ahora acepta asociaciones, la agrupación sigue siendo por CI/NIT, pero conceptualmente son "contribuyentes frecuentes" (personas naturales o jurídicas).

```php
$clientesFrecuentes = SolicitudReserva::query()
    ->where('estado', EstadoSolicitudReserva::Confirmada)
    ->whereBetween('creado_en', [$desde, $hasta])
    ->selectRaw('COALESCE(ci_nit_pagador, telefono_pagador) as clave_agrupacion')
    ->selectRaw('COUNT(*) as total_reservas')
    ->selectRaw('SUM(monto_total) as monto_total_gastado')
    ->selectRaw('(ARRAY_AGG(nombre_pagador ORDER BY creado_en DESC))[1] as nombre_mas_reciente')
    ->groupBy('clave_agrupacion')
    ->orderByDesc('total_reservas')
    ->get();
```

## Solo reservas/solicitudes confirmadas

Ninguno de los tres reportes debe contar solicitudes en estados distintos de `confirmada`:

```text
pendiente → no cuenta
expirada → no cuenta
cancelada → no cuenta
rechazada → no cuenta
```

## Nota visible en la UI

El dashboard gerencial (Fase 6.5) debe mostrar una nota visible:

```text
Estos valores son una vista operativa del sistema de canchas.
La conciliación financiera oficial corresponde a Paitití / SIREB.
```

---

## Tareas de la Fase 6.3

```
[ ] Crear ReportesService
    → app/Services/ReportesService.php con tres métodos:
        • ingresosPorCampo(Carbon $desde, Carbon $hasta, ?string $campoId):
          suma de reservas.monto_pagado agrupado por campo_id. Solo
          reservas de solicitudes confirmadas.
        • histogramaHorasPico(Carbon $desde, Carbon $hasta): conteo de
          reservas agrupado por la hora de hora_inicio. Solo reservas
          de solicitudes confirmadas.
        • clientesFrecuentes(Carbon $desde, Carbon $hasta): tal como se
          muestra arriba. Solo solicitudes confirmadas.

[ ] Crear ReportesController
    → app/Http/Controllers/Api/V1/ReportesController.php
    → GET /api/v1/reportes/ingresos?desde=...&hasta=...&campo_id=...
    → GET /api/v1/reportes/horas-pico?desde=...&hasta=...
    → GET /api/v1/reportes/clientes-frecuentes?desde=...&hasta=...
    → Middleware: auth.oauth + role:admin_parametricas,gerencia (o
      admin_parametricas,admin_reservas según Fase 6.0).

[ ] Registrar rutas en backend/routes/api.php
    → Dentro del grupo auth.oauth existente.

[ ] Verificar el uso de los índices del Módulo 1
    → EXPLAIN ANALYZE sobre cada consulta, confirmando Index Scan sobre
      idx_reservas_campo_fecha e idx_solicitudes_pagador.
    → Si no se usan, investigar por qué (query planner, estadísticas
      desactualizadas, etc.) y corregir.

[ ] Tests
    → backend/tests/Feature/Api/V1/ReportesTest.php
    → Ingresos por campo suma correctamente solo las reservas dentro
      del rango de fechas solicitado.
    → Dos solicitudes confirmadas con el mismo ci_nit_pagador pero
      nombre_pagador escrito distinto se agrupan como un solo cliente
      frecuente con 2 reservas.
    → Una solicitud en cualquier estado distinto de 'confirmada' no se
      cuenta en ninguno de los tres reportes.
    → Un funcionario_control no puede acceder a estas rutas (403).

[ ] Commit de la fase
    → Mensaje: "feat(reportes): ingresos, horas pico y clientes frecuentes"
```

---

# FASE 6.4 — Panel Web: Pantalla de Ocupación para Funcionario de Control

## Resolviendo la navegación del sidebar

Con la estructura definida en Fase 6.0, el sidebar tiene:

```text
Ocupación → /panel/ocupacion (nueva pantalla)
Reservas → /panel/reservas (ya existe, Módulo 7)
```

La pantalla de Ocupación es la vista operativa de sitio: grilla del día, verificación de código, refresco automático. La pantalla de Reservas es la vista administrativa: listado filtrable, auditoría, exportación CSV.

## Diseño mobile-first

El funcionario abre esta pantalla desde el navegador de su celular en la cancha. El diseño debe priorizar:

- Input prominente para verificación de código (arriba).
- Lista de campos asignados con grilla del día.
- Franja actual resaltada.
- Botón de refresco manual además del automático.

## Refresco automático

Cada 30–60 segundos, con indicador de última actualización:

```text
Última actualización: hace 15 segundos
[Refrescar ahora]
```

No hacer polling agresivo. Si el usuario está en otra pestaña, pausar el refresco.

## Verificación de código

Input prominente. Al ingresar un código:

- Si es válido y pertenece a un campo asignado: mostrar detalles (campo, fecha, franja, estado, asistencia si aplica).
- Si no es válido o no pertenece a un campo asignado: mostrar "Código no encontrado" (respuesta uniforme, no filtrar existencia).

No exponer datos personales del pagador.

## Asistencia read-only

Si se eligió Opción A en Fase 6.0, mostrar el estado de asistencia como badge:

```text
✓ Asistió HH:MM
```

o:

```text
Sin asistencia marcada
```

No mostrar botón de marcar/desmarcar (eso es Fase 7.5.B).

## Evitar errores de UI conocidos

En el Módulo 7 ya tuvimos problemas con:

- `<button>` dentro de `<button>` en dropdowns.
- `asChild` en componentes base-ui.
- Imports faltantes de íconos.

Para esta pantalla, usar componentes simples y verificar que no haya errores de hidratación.

---

## Tareas de la Fase 6.4

```
[ ] Crear los tipos TypeScript y el servicio de API
    → web-admin/src/types/ocupacion.ts
    → web-admin/src/services/ocupacionService.ts
    → Consumir endpoints de Fase 6.1:
        GET /api/v1/ocupacion/mis-campos?fecha=YYYY-MM-DD
        GET /api/v1/ocupacion/verificar/{codigo_reserva}

[ ] Crear la pantalla de ocupación
    → web-admin/src/pages/ocupacion/MisCampos.tsx
    → Con componentes de shadcn/ui (Card, Table, Badge).
    → Diseño mobile-first.
    → Lista los campos asignados al funcionario autenticado.
    → Grilla del día resaltando la franja que corresponde a la hora actual.
    → Refresco automático cada 30–60 segundos con indicador de última actualización.
    → Opcionalmente mostrar asistencia_marcada_en como badge read-only.

[ ] Crear el buscador de verificación de código
    → Input prominente en la misma pantalla (arriba).
    → Al ingresar código y presionar Enter o botón "Verificar":
        - Llama a GET /api/v1/ocupacion/verificar/{codigo_reserva}.
        - Si 200: mostrar detalles (campo, fecha, franja, estado, asistencia).
        - Si 404: mostrar "Código no encontrado".
    → No exponer datos personales.

[ ] Actualizar sidebar en web-admin/src/components/app-sidebar.tsx
    → Confirmar que ítem "Ocupación" apunta a /panel/ocupacion.
    → Visible para funcionario_control y admin_parametricas (y admin_reservas si aplica).

[ ] ErrorBoundary opcional
    → Envolver la pantalla en ErrorBoundary para que un error de render
      no tumbe todo el panel. Mostrar mensaje amigable y botón de retry.

[ ] Tests manuales
    → [ ] Un funcionario_control entra desde navegador de celular.
    → [ ] Ve únicamente sus campos asignados.
    → [ ] La grilla del día muestra franjas con estado correcto.
    → [ ] La franja actual está resaltada.
    → [ ] El refresco automático funciona cada 30–60 segundos.
    → [ ] Verifica un código válido: ve detalles sin datos personales.
    → [ ] Verifica un código inválido: ve "Código no encontrado".
    → [ ] Verifica un código de campo no asignado: ve "Código no encontrado" (igual que inválido).
    → [ ] Si aplica, ve badge de asistencia cuando corresponde.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): pantalla de ocupación y verificación para funcionario de control"
```

---

# FASE 6.5 — Panel Web: Mapa Global y Dashboard Gerencial

## Elección de librerías: verificar antes de instalar

Antes de instalar, verificar en Fase 6.0 qué ya existe en `web-admin/package.json`.

### Mapa global

Si no existe `react-leaflet` ni `leaflet`:

```bash
npm install react-leaflet leaflet
npm install -D @types/leaflet
```

Extiende la decisión del **ADR-005** (Módulo 3): Leaflet con OpenStreetMap.

### Gráficos

Si no existe `recharts`:

```bash
npm install recharts
```

Liviana, ampliamente usada con React, cubre los tres tipos de visualización que pide HU-F2.

## Mapa Global: marcadores por campo

Un marcador por campo activo o en mantenimiento, coloreado según:

```text
activo + vinculado SIREB → verde/teal
activo + sin vínculo SIREB → gris con warning
mantenimiento → ámbar
```

Tooltip o popup al hacer click:

```text
Nombre del campo
Estado: activo / mantenimiento
Vinculación SIREB: sí / no
Ocupación ahora: sí / no
Ocupación del día: X%
[Ver detalle] → navega a /panel/reservas con filtro campo_id
```

## Dashboard gerencial: tres reportes

### Selector de rango de fechas

Input de `desde` y `hasta` con valores por defecto:

```text
desde = primer día del mes actual
hasta = hoy
```

### Gráfico de barras: ingresos por campo

Eje X: nombre del campo.  
Eje Y: monto total en el rango.

### Serie temporal: ingresos por día

Eje X: fecha.  
Eje Y: monto total del día.

### Histograma de horas pico

Eje X: hora del día (0–23).  
Eje Y: cantidad de reservas en esa hora.

### Nota visible

Debajo del selector de fechas, mostrar:

```text
⚠️ Estos valores son una vista operativa del sistema de canchas.
La conciliación financiera oficial corresponde a Paitití / SIREB.
```

## Clientes frecuentes: tabla

Columnas:

```text
CI/NIT o teléfono (clave de agrupación)
Nombre más reciente
Total de reservas en el período
Monto total gastado en el período
```

Ordenada por total de reservas descendente.

## Habilitar ítems del sidebar

Confirmar que en Fase 6.0 se agregaron:

```text
Mapa Global → /panel/ocupacion/mapa
Dashboard → /panel/reportes
```

Visibles para `admin_parametricas` y `gerencia` (o `admin_reservas` según Fase 6.0).

---

## Tareas de la Fase 6.5

```
[ ] Verificar e instalar dependencias si faltan
    → npm install react-leaflet leaflet recharts (si no existen).
    → npm install -D @types/leaflet (si no existe).

[ ] Crear la pantalla de mapa global
    → web-admin/src/pages/ocupacion/MapaGlobal.tsx
    → Un marcador por campo activo o en mantenimiento.
    → Coloreado según estado y vínculo SIREB.
    → Tooltip/popup con resumen de ocupación.
    → Click en marcador navega a /panel/reservas con filtro campo_id.
    → Visible para admin_parametricas y gerencia (o admin_reservas).

[ ] Crear la pantalla de dashboard gerencial
    → web-admin/src/pages/reportes/Dashboard.tsx
    → Selector de rango de fechas (desde/hasta).
    → Gráfico de barras de ingresos por campo.
    → Serie temporal de ingresos por día.
    → Histograma de horas pico.
    → Nota visible aclarando que es vista operativa, no conciliación Paitití/SIREB.
    → Visible para admin_parametricas y gerencia (o admin_reservas).

[ ] Crear la pantalla de clientes frecuentes
    → web-admin/src/pages/reportes/ClientesFrecuentes.tsx
    → Tabla ordenada por cantidad de reservas.
    → Columnas: CI/NIT o teléfono, nombre más reciente, total reservas, monto total.
    → Selector de rango de fechas (desde/hasta).
    → Visible para admin_parametricas y gerencia (o admin_reservas).

[ ] Actualizar sidebar en web-admin/src/components/app-sidebar.tsx
    → Confirmar que ítems "Mapa Global" y "Dashboard" apuntan a rutas reales.
    → Visibles solo para roles autorizados.

[ ] ErrorBoundary opcional
    → Envolver mapa y dashboard en ErrorBoundary.
    → Si falla Leaflet o Recharts, mostrar mensaje amigable.

[ ] Tests manuales
    → [ ] Un admin_parametricas ve el mapa global con todos los campos.
    → [ ] Los marcadores muestran color correcto según estado y vínculo SIREB.
    → [ ] El tooltip muestra resumen de ocupación.
    → [ ] Click en marcador navega a Reservas con filtro campo_id.
    → [ ] El dashboard muestra gráficos coherentes con datos de prueba.
    → [ ] La nota de Paitití/SIREB es visible.
    → [ ] La tabla de clientes frecuentes agrupa correctamente por CI/NIT.
    → [ ] Un funcionario_control no puede acceder a estas pantallas (redirect o 403).

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): mapa global y dashboard gerencial de reportes"
```

---

# FASE 6.6 — Smoke Test Final y Commit de Cierre

## Checklist de cierre del Módulo 6

```
[ ] Reconciliación (Fase 6.0)
    → Decisiones documentadas en el roadmap.
    → Roles confirmados y middleware correcto.
    → Índices verificados con EXPLAIN ANALYZE.
    → Dependencias frontend verificadas.
    → Sidebar actualizado con Ocupación, Reservas, Mapa Global, Dashboard.

[ ] Backend ocupación (Fase 6.1)
    → GET /api/v1/ocupacion/mis-campos funciona correctamente.
    → funcionario_control solo ve sus campos asignados.
    → GET /api/v1/ocupacion/verificar/{codigo} funciona correctamente.
    → Respuesta uniforme para códigos no válidos o no asignados (404).
    → No expone nombre_pagador ni telefono_pagador.
    → Tests de OcupacionTest pasan.

[ ] Backend mapa global (Fase 6.2)
    → GET /api/v1/ocupacion/mapa-global funciona correctamente.
    → Solo roles autorizados pueden acceder.
    → funcionario_control recibe 403.
    → Campos activos y en mantenimiento aparecen.
    → Metadato de vínculo SIREB aparece cuando corresponde.
    → Tests de OcupacionTest pasan.

[ ] Backend reportes (Fase 6.3)
    → GET /api/v1/reportes/ingresos funciona correctamente.
    → GET /api/v1/reportes/horas-pico funciona correctamente.
    → GET /api/v1/reportes/clientes-frecuentes funciona correctamente.
    → Solo roles autorizados pueden acceder.
    → funcionario_control recibe 403.
    → Solo cuenta reservas/solicitudes confirmadas.
    → Índices verificados con EXPLAIN ANALYZE.
    → Tests de ReportesTest pasan.

[ ] Frontend ocupación (Fase 6.4)
    → /panel/ocupacion carga correctamente.
    → Funcionario de control ve solo sus campos asignados.
    → Grilla del día muestra franjas con estado correcto.
    → Franja actual está resaltada.
    → Refresco automático funciona cada 30–60 segundos.
    → Verificación de código funciona:
        - Código válido: muestra detalles sin datos personales.
        - Código inválido: muestra "Código no encontrado".
        - Código de campo no asignado: muestra "Código no encontrado" (igual que inválido).
    → Si aplica, badge de asistencia aparece cuando corresponde.
    → No hay errores de hidratación ni warnings en consola.
    → Build de web-admin pasa sin errores TypeScript.

[ ] Frontend mapa global (Fase 6.5)
    → /panel/ocupacion/mapa carga correctamente.
    → Admin ve todos los campos activos y en mantenimiento.
    → Marcadores muestran color correcto según estado y vínculo SIREB.
    → Tooltip/popup muestra resumen de ocupación.
    → Click en marcador navega a Reservas con filtro campo_id.
    → funcionario_control no puede acceder (redirect o 403).

[ ] Frontend dashboard (Fase 6.5)
    → /panel/reportes carga correctamente.
    → Selector de rango de fechas funciona.
    → Gráfico de barras de ingresos por campo se renderiza.
    → Serie temporal de ingresos por día se renderiza.
    → Histograma de horas pico se renderiza.
    → Nota de Paitití/SIREB es visible.
    → Datos mostrados son coherentes con datos de prueba.

[ ] Frontend clientes frecuentes (Fase 6.5)
    → /panel/reportes/clientes-frecuentes carga correctamente.
    → Tabla se renderiza correctamente.
    → Agrupación por CI/NIT funciona (mismo CI/NIT con nombres distintos = 1 fila).
    → Orden por total de reservas funciona.
    → Selector de rango de fechas funciona.

[ ] Integración con Módulo 7
    → /panel/reservas sigue funcionando correctamente (no se rompió).
    → Si se eligió Opción A para asistencia, el badge de asistencia en
      Ocupación refleja correctamente el estado marcado desde Reservas.
    → Si se eligió Opción B, el botón de marcar asistencia en Ocupación
      funciona correctamente (y Fase 7.5.B queda adelantada).

[ ] Tests automatizados
    → php artisan test --filter=OcupacionTest pasa.
    → php artisan test --filter=ReportesTest pasa.
    → php artisan test --filter=SolicitudReservaListadoTest pasa (regresión).
    → php artisan test --filter=AsistenciaReservaTest pasa (regresión).
    → php artisan test --filter=ReservasExportTest pasa (regresión).
    → npm run build en web-admin pasa sin errores.
    → npx tsc --noEmit en web-admin pasa sin errores.

[ ] Documentación
    → Roadmap actualizado con decisiones de Fase 6.0.
    → Notas de implementación en código donde corresponda.
    → CHANGELOG actualizado si el proyecto lo usa.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 6 - operación en sitio y dashboard gerencial"
    → Tag sugerido: v0.9.0-operacion-dashboard

[ ] Smoke end-to-end con datos reales (opcional pero recomendado)
    → Crear 3-5 reservas confirmadas en distintos campos.
    → Verificar que aparecen en ocupación, mapa global y reportes.
    → Verificar que clientes frecuentes agrupa correctamente.
    → Verificar que ingresos coinciden con sumas manuales.
```

---

## Notas finales

### Relación con Módulo 7

Este módulo **complementa** al Módulo 7, no lo bloquea. La pantalla de Ocupación (6.4) es la vista operativa de sitio que el funcionario de control necesita, mientras que la pantalla de Reservas (Módulo 7) es la vista administrativa con filtros, auditoría y exportación.

Si se eligió Opción A en Fase 6.0 (asistencia read-only en Ocupación), la Fase 7.5.B (integrar marcar/desmarcar asistencia en Ocupación) queda pendiente y puede implementarse después.

Si se eligió Opción B (integrar marcar asistencia en Ocupación), la Fase 7.5.B queda adelantada y puede marcarse como completada.

### Relación con SIREB/Paitití

Los reportes de ingresos de este módulo son la **vista operativa de Canchas**, no la conciliación financiera oficial. Paitití/SIREB es la fuente autoritativa de liquidaciones y pagos. Esta distinción debe quedar explícita en la UI del dashboard gerencial (Fase 6.5).

### Deuda técnica registrada

Si en Fase 6.3 se detecta que `reservas.monto_pagado` no coincide con el monto confirmado por SIREB, registrar como deuda técnica:

```text
D-XX: Refactor de ConfirmacionCobroService para asegurar que reservas.monto_pagado
      siempre refleje el monto autoritativo de SIREB, no estimación local.
```

Esto está fuera de alcance del Módulo 6 pero debe documentarse para abordarlo en iteración futura.

---

## Deuda técnica documentada (pre-existente al Módulo 6)

### D-07 — Tipos de @base-ui/react Select (14 errores TS)
**Origen:** Actualización de `@base-ui/react` que cambió el tipo de 
`Select.onValueChange` para aceptar `string | null` en vez de solo `string`.
**Archivos afectados:** CampoFormDialog, CamposDeportivos, TiposCampo, 
Reservas/index, Asignaciones, Funcionarios.
**Fix sugerido:** Refactor global usando helper tipo 
`const onChange = (v: string | null) => v && setState(v)` 
o cambiar el tipo del state a `string | null`.
**Prioridad:** Media. No rompe build en modo dev, solo en `tsc --noEmit`.

### D-08 — Rutas de auth legacy comentadas/eliminadas (12 tests fallando)
**Origen:** Migración de auth Sanctum a OAuth Ibare dejó rutas viejas 
comentadas (`POST /v1/auth/login`) o eliminadas (`GET /v1/oauth/me`).
**Tests afectados:** AuthTest (4), AuthOAuthIbareTest (7), FuncionarioTest (1).
**Fix sugerido:** 
  - Opción A: Eliminar tests legacy si ya no se usa auth Sanctum.
  - Opción B: Restaurar rutas si son necesarias como fallback.
  - Opción C: Agregar manejo de `user === null` en `AuthController::me()`.
**Prioridad:** Alta (falla en CI). Pero NO es responsabilidad del Módulo 6.

### D-09 — Warning baseUrl en web-public
**Origen:** TypeScript 6.0 deprecó `baseUrl`.
**Fix:** Agregar `"ignoreDeprecations": "6.0"` a tsconfig.
**Prioridad:** Baja (warning, no error funcional).


## Deuda técnica documentada (post-cierre)

### D-07 — Tipos de @base-ui/react Select (16 errores TS pre-existentes)
Origen: actualización de librería que cambió tipo de Select.onValueChange.
No es responsabilidad del Módulo 6. Requiere refactor global en módulo futuro.

### D-08 — Tests legacy de auth fallando (12 tests pre-existentes)
Origen: migración de auth Sanctum a OAuth Ibare dejó rutas comentadas.
No es responsabilidad del Módulo 6. Requiere decisión sobre si eliminar
tests legacy o restaurar rutas.

**Siguiente módulo:** el Módulo 7 (Épica G — seguridad, concurrencia y publicación) ya tiene backend cerrado y frontend base. Con el Módulo 6 completo, el sistema queda listo para smoke final y publicación, con todas las capas operativas implementadas.
