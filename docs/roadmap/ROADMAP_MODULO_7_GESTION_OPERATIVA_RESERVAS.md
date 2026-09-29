# 📁 ROADMAP_MODULO_7_GESTION_OPERATIVA_RESERVAS.md

**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 1.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 6–8 horas · **Bloquea:** nada crítico — es una capa de auditoría y gestión que se apoya sobre lo ya construido, sin modificar el flujo de cobro

> **Nota de renumeración:** este módulo **no existía** en el roadmap original.
> Surge de una necesidad operativa detectada durante la implementación de los
> Módulos 5 y 6: el sistema ya sabe *todo* sobre cada reserva (quién pagó,
> cuánto, cuándo, si asistió o no), pero hasta ahora esa información solo era
> consultable *de a una* por código de seguimiento desde la app pública. El
> GAD necesita mirarla *en conjunto*: listar, filtrar, auditar y exportar.
> Por eso el antiguo Módulo 7 (Épica G: seguridad, concurrencia y
> publicación) **pasa a ser Módulo 8**, y este toma el número 7.

> **Objetivo del Módulo:** darle al GAD Beni la **gestión operativa completa**
> de las reservas que el sistema ya genera: un listado filtrable de todas las
> solicitudes con su detalle auditable, la capacidad de **marcar asistencia**
> en sitio (el dato que convierte una reserva pagada en una cancha efectivamente
> usada), y la **exportación CSV** para reportes externos. Cierra la brecha
> entre "el sistema funciona" y "el GAD puede operar y auditar el sistema".

> **Lo que NO toca este módulo:** el flujo de cobro del Módulo 5 (webhook,
> polling, expiración) y los endpoints de ocupación/reportes del Módulo 6.
> Este módulo **solo lee** lo que esos módulos ya escriben, y **agrega una
> sola columna nueva** a la tabla `reservas` (asistencia). Si el Módulo 5 o 6
> cambian su lógica interna, este módulo sigue funcionando igual.

---

## 🗺️ Mapa del Módulo

```
Módulo 7
├── Fase 7.1 → Backend: listado admin de solicitudes con filtros y detalle auditable
├── Fase 7.2 → Backend: marcar asistencia en sitio (con ventana y auditoría)
├── Fase 7.3 → Backend: exportación CSV de reservas
├── Fase 7.4 → Web-admin: listado operativo /panel/reservas con dialog de detalle
├── Fase 7.5 → Web-admin: integración de asistencia en Ocupación y en el detalle
└── Fase 7.6 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 7.1 — Backend: Listado Admin de Solicitudes con Filtros y Detalle Auditable

## Por qué un resource admin distinto al público

El `SolicitudEstadoResource` del Módulo 5 (Fase 5.4) está diseñado para el
ciudadano: expone estado, monto y códigos de reserva, y **oculta
deliberadamente** nombre del pagador, teléfono y referencia al Core.

El admin necesita lo contrario para poder operar: **contactar al pagador**
cuando hay un reclamo, **cruzar con el Core** por referencia, y **filtrar
mucho**. Son dos casos de uso distintos → dos resources distintos → dos
rutas distintas. No se mezclan, y el público nunca gana campos nuevos.

## La restricción de asignación se aplica server-side, no en la UI

El filtro `funcionario_control_id` existe para que un admin pueda mirar "las
reservas de Juan". Pero un `funcionario_control` **nunca** puede usar ese
filtro para espiar a otro: el servicio **ignora el parámetro** y fuerza el
filtro al funcionario autenticado. La regla del Documento 1 v3 ("cada
funcionario de control solo ve sus campos") se cumple en el servidor, igual
que en el Módulo 6 Fase 6.1.

## Endpoints nuevos

### `GET /api/v1/solicitudes-reserva` — Listado paginado

**Query params (todos opcionales):**
- `estado` (pendiente | confirmada | expirada | rechazada)
- `desde` / `hasta` (rango sobre `creado_en`, formato `YYYY-MM-DD`)
- `campo_id`
- `funcionario_control_id` — **solo respetado para admins**; un
  funcionario_control queda forzado a su propio id
- `buscar` — matchea `codigo_seguimiento`, `nombre_pagador`,
  `telefono_pagador`, `ci_nit_pagador` (case-insensitive)
- `page` / `per_page`

**Respuesta (`SolicitudReservaResource` — versión admin):**
```json
{
  "data": [
    {
      "id": "uuid",
      "codigo_seguimiento": "RES-20260929-ABC123",
      "estado": "confirmada",
      "monto_total": 280.00,
      "monto_confirmado": 280.00,
      "nombre_pagador": "Juan Pérez",
      "telefono_pagador": "70000000",
      "ci_nit_pagador": "1234567",
      "referencia_recaudaciones": "REC-XXXXX",
      "creado_en": "2026-09-29T10:15:00Z",
      "expira_en": "2026-09-29T10:30:00Z",
      "confirmado_en": "2026-09-29T10:22:33Z",
      "detalles": [
        {
          "id": "uuid-detalle",
          "campo_id": "uuid-campo",
          "campo_nombre": "Cancha Central",
          "fecha_reserva": "2026-10-01",
          "hora_inicio": "18:00",
          "hora_fin": "20:00",
          "tarifa_aplicada": 140.00,
          "reserva": {
            "id": "uuid-reserva",
            "codigo_reserva": "RSV-8F3K2",
            "asistencia_marcada_en": null
          }
        }
      ]
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 48, "per_page": 15 }
}
```

### `GET /api/v1/solicitudes-reserva/{id}` — Detalle auditable

Misma estructura, más el **historial de auditoría** de la solicitud
(acciones `crear`, `expirar_automaticamente`, `confirmacion_tardia_*`,
`discrepancia_monto_confirmado`, etc.) ordenado cronológicamente. Es lo que
alimenta la timeline del dialog de detalle en la Fase 7.4.

---

## Tareas de la Fase 7.1

```
[ ] Crear SolicitudFiltrosDTO
    → app/DTOs/SolicitudFiltrosDTO.php — propiedades opcionales tipadas:
      ?string estado, ?Carbon desde, ?Carbon hasta, ?string campoId,
      ?string funcionarioControlId, ?string buscar, int page, int perPage.

[ ] Crear SolicitudReservaResource (versión admin)
    → app/Http/Resources/SolicitudReservaResource.php — distinto al
      SolicitudEstadoResource público. Incluye datos de contacto,
      referencia_recaudaciones y los detalles con su reserva anidada
      (codigo_reserva + asistencia_marcada_en).

[ ] Agregar método listarAdmin() a SolicitudReservaService
    → Recibe SolicitudFiltrosDTO + el Funcionario autenticado.
    → Si el funcionario es funcionario_control, SOBRESCRIBE
      funcionarioControlId con su propio id (regla server-side).
    → Combina filtros con AND; el filtro por campo usa
      whereHas('detalles', ...); la búsqueda usa un where anidado con
      orWhere sobre los 4 campos de texto.

[ ] Crear SolicitudReservaAdminController
    → app/Http/Controllers/Api/V1/SolicitudReservaAdminController.php
    → index(): lista paginada con SolicitudReservaResource.
    → show($id): detalle + historial de auditoria de la solicitud.

[ ] Registrar rutas en routes/api.php
    → Bajo auth:sanctum + RoleMiddleware(['admin_parametricas',
      'admin_reservas', 'funcionario_control']):
        GET /v1/solicitudes-reserva
        GET /v1/solicitudes-reserva/{id}

[ ] Tests
    → index() sin filtros devuelve todas las solicitudes paginadas.
    → index(estado='confirmada') solo devuelve confirmadas.
    → index(desde, hasta) filtra por rango de creado_en.
    → index(campo_id) solo devuelve solicitudes con al menos un detalle
      de ese campo.
    → index(buscar='juan') matchea nombre_pagador case-insensitive.
    → Un funcionario_control con 2 campos asignados que pide
      funcionario_control_id=<otro> recibe SOLO sus propios campos
      (la restricción server-side gana).
    → show() incluye el historial de auditoria ordenado.
    → Un ciudadano no autenticado recibe 401; un rol sin permiso, 403.

[ ] Commit de la fase
    → Mensaje: "feat(admin): listado de solicitudes con filtros y detalle auditable"
```

---

# FASE 7.2 — Backend: Marcar Asistencia en Sitio

## El dato que faltaba: ¿la cancha se usó de verdad?

Hasta hoy el sistema sabe que alguien **pagó** por una franja, pero no si
**la usó**. Ese dato es el que cierra el ciclo operativo: sin él, el GAD no
puede distinguir "reservada y usada" de "reservada y no presentada" (que
tiene implicancias de política pública: canchas ociosas vs. demanda real).

## Por qué es una acción sensible y auditada

1. **Quién:** solo un funcionario de control **asignado al campo** de esa
   reserva, o un admin. Server-side, igual que siempre.
2. **Cuándo:** dentro de una **ventana razonable** — desde 1 hora antes del
   inicio hasta 24 horas después del fin. Fuera de esa ventana, solo un
   admin puede marcarlo (corrección a posteriori).
3. **Rastro:** cada marcado y desmarcado queda en `auditoria` con quién lo
   hizo y cuándo. Desmarcar no borra el historial: lo complementa.
4. **Estado previo:** solo se marca asistencia sobre reservas de solicitudes
   **confirmadas**. Una franja expirada nunca tuvo pago, no hay asistencia
   que marcar.

## Migración nueva (la única del módulo)

```php
// database/migrations/2026_XX_XX_add_asistencia_to_reservas_table.php
Schema::table('reservas', function (Blueprint $table) {
    $table->timestamp('asistencia_marcada_en')
          ->nullable()
          ->after('confirmado_en');
    $table->foreignUuid('asistencia_marcada_por')
          ->nullable()
          ->after('asistencia_marcada_en')
          ->constrained('funcionarios')
          ->nullOnDelete();
});
```

## Endpoint

### `POST /api/v1/reservas/{id}/asistencia`

Body: `{ "marcar": true | false }`

| Situación | Respuesta |
|---|---|
| Marcado/desmarcado exitoso | `200` + reserva actualizada |
| Reserva no existe o no es de solicitud confirmada | `404` / `422` |
| Funcionario no asignado al campo (y no es admin) | `403` |
| Fuera de ventana y no es admin | `422` con mensaje explicativo |

---

## Tareas de la Fase 7.2

```
[ ] Migración
    → 2026_XX_XX_add_asistencia_to_reservas_table.php — las dos columnas
      nuevas en reservas.

[ ] Actualizar modelo Reserva
    → app/Models/Reserva.php — agrega los campos al $fillable, cast de
      asistencia_marcada_en a datetime, y relación
      asistenciaMarcadaPor() ->belongsTo(Funcionario::class).

[ ] Crear MarcarAsistenciaService
    → app/Services/MarcarAsistenciaService.php con método
      ejecutar(Reserva $reserva, Funcionario $funcionario, bool $marcar).
    → Valida en orden: solicitud confirmada → permiso (asignación o
      admin) → ventana temporal (o admin) → ejecuta el update →
      registra en auditoria ('marcar_asistencia' o
      'desmarcar_asistencia') con funcionario y timestamp.

[ ] Crear AsistenciaController + StoreAsistenciaRequest
    → POST /v1/reservas/{id}/asistencia — el request valida que 'marcar'
      sea boolean requerido.

[ ] Registrar ruta
    → Bajo auth:sanctum + RoleMiddleware(['admin_parametricas',
      'admin_reservas', 'funcionario_control']).

[ ] Tests
    → Funcionario asignado marca dentro de ventana → 200,
      asistencia_marcada_en != null, fila en auditoria.
    → Mismo funcionario fuera de ventana → 422.
    → Funcionario NO asignado → 403.
    → Admin marca fuera de ventana → 200.
    → Desmarcar deja asistencia_marcada_en = null y audita
      'desmarcar_asistencia'.
    → Marcar dos veces seguidas no duplica el timestamp (idempotente en
      el estado final, pero audita ambas acciones).
    → Intentar marcar sobre una reserva de solicitud expirada → 422.

[ ] Commit de la fase
    → Mensaje: "feat(admin): marcar asistencia en sitio con ventana temporal y auditoria"
```

---

# FASE 7.3 — Backend: Exportación CSV de Reservas

## Stream, no memoria

El GAD puede acumular miles de reservas al año. Construir el CSV completo
en memoria antes de enviarlo puede agotar el worker de PHP. La solución es
`StreamedResponse` con `chunk()`: Laravel escribe fila por fila al buffer,
el navegador descarga progresivamente, y la memoria queda constante sin
importar el volumen.

## Endpoint

### `GET /api/v1/reservas/export?formato=csv`

Acepta los mismos filtros del listado (estado, desde, hasta, campo_id,
funcionario_control_id con la misma regla server-side).

Headers: `Content-Type: text/csv`, `Content-Disposition: attachment;
filename="reservas-YYYY-MM-DD.csv"`.

Columnas:
```
codigo_seguimiento,estado,nombre_pagador,telefono_pagador,ci_nit_pagador,codigo_reserva,campo_nombre,fecha_reserva,hora_inicio,hora_fin,monto_pagado,asistencia_marcada_en
```

---

## Tareas de la Fase 7.3

```
[ ] Agregar metodo exportarCsv() a SolicitudReservaService
    → Devuelve un Generator: primera fila headers, luego yield por cada
      reserva aplanada (una fila por franja, no por solicitud), usando
      chunk(500) sobre el query con eager load de detalles y campo.

[ ] Crear ReservasExportController
    → app/Http/Controllers/Api/V1/ReservasExportController.php con
      método csv(Request) que envuelve el generator en StreamedResponse
      con los headers correctos.

[ ] Registrar ruta
    → GET /v1/reservas/export
    → Bajo auth:sanctum + RoleMiddleware(['admin_parametricas',
      'admin_reservas']) — un funcionario_control NO exporta (evita fuga
      masiva de datos de contacto desde una cuenta de campo).

[ ] Tests
    → Export sin filtros incluye una fila por franja confirmada sembrada.
    → Export con estado='confirmada' excluye pendientes.
    → Export con rango de fechas respeta el rango.
    → Un funcionario_control recibe 403 en esta ruta.
    → El CSV incluye el header Content-Disposition con fecha de hoy.

[ ] Commit de la fase
    → Mensaje: "feat(admin): exportacion CSV de reservas con streamed response"
```

---

# FASE 7.4 — Web-admin: Listado Operativo /panel/reservas

## Reemplaza el placeholder "Próximamente"

La ruta `/panel/reservas` existe desde el Módulo 0.8 con un placeholder.
Esta fase la convierte en la pantalla operativa real, con el mismo lenguaje
visual del admin rediseñado (eyebrow, stats cards, toolbar unificada, tabla
con hover, dropdown de acciones, badges semánticos).

## Estructura de la pantalla

```
┌──────────────────────────────────────────────────────────┐
│ GESTIÓN OPERATIVA                                         │
│ Reservas                                   [⬇ Exportar]  │
│ Auditoría de todas las solicitudes del sistema            │
├──────────────────────────────────────────────────────────┤
│ [📋 48 total] [⏳ 2 pendientes] [✓ 40 confirmadas]         │
│ [💰 Bs 1840 confirmado]                                   │
├──────────────────────────────────────────────────────────┤
│ toolbar: [🔍 código/pagador/CI] [Estado▾] [📅 desde-hasta]│
│          [Campo▾]                                         │
├──────────────────────────────────────────────────────────┤
│ Tabla: código · pagador · campo(s) · fecha · monto ·      │
│        estado (badge) · creada (relativa) · ⋯             │
│                                                           │
│ Click en fila → Dialog de detalle:                        │
│   - Card del pagador (nombre, CI, teléfono con tel:)      │
│   - Timeline de estados desde auditoria                   │
│   - Franjas con codigo_reserva y badge de asistencia      │
│   - Botón "Ver comprobante" → /comprobante/{codigo}       │
│   - Botón "Marcar/Desmarcar asistencia" (si aplica)       │
└──────────────────────────────────────────────────────────┘
```

## Badges de estado (coherentes con el resto del admin)
- 🟢 Confirmada → emerald + CheckCircle2
- 🟡 Pendiente → amber + Clock (con countdown si `expira_en > now`)
- ⚫ Expirada → slate + XCircle
- 🔴 Rechazada → red + AlertTriangle

---

## Tareas de la Fase 7.4

```
[ ] Crear types/reservas.ts
    → Interfaces: SolicitudReservaAdmin, DetalleSolicitudAdmin,
      ReservaAnidada, FiltrosReserva, EntradaAuditoria.

[ ] Crear reservasService.ts
    → listar(filtros), obtener(id), exportarCsv(filtros) — este último
      descarga el blob y dispara el download del navegador.

[ ] Crear components/reserva/DialogDetalleReserva.tsx
    → Dialog con: card del pagador, timeline vertical de auditoria,
      lista de franjas con badge de asistencia, y acciones
      (ver comprobante, marcar/desmarcar asistencia con AlertDialog).

[ ] Reemplazar pages/Reservas/index.tsx
    → Header con eyebrow + botón Exportar, stats cards, toolbar
      unificada, tabla paginada, dialog de detalle.
    → Consistencia visual con CamposDeportivos.tsx / TiposCampo.tsx.

[ ] Verificar breadcrumb y sidebar
    → '/panel/reservas' ya está en RUTA_LABELS como 'Reservas';
      confirmar que el ítem del sidebar apunta a la ruta real.

[ ] Tests manuales
    → Listado carga con stats correctos.
    → Cada filtro funciona y se combina con los demás.
    → Click en fila abre el detalle con timeline y franjas.
    → Exportar descarga un CSV que abre bien en Excel.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): listado operativo de reservas con detalle auditable"
```

---

# FASE 7.5 — Web-admin: Integración de Asistencia en Ocupación y en el Detalle

## Dos lugares, una misma acción

La asistencia se marca **en dos contextos distintos** con el mismo endpoint
de la Fase 7.2:

1. **En sitio (Módulo 6, Fase 6.4):** el funcionario está en la pantalla de
   Ocupación viendo la grilla del día de SU campo. Cada franja confirmada
   de hoy muestra un botón **"Marcar asistencia"** (o el badge
   "✓ Asistió 18:42" si ya está marcada). Es el flujo principal: rápido,
   con el celular en la mano, frente al ciudadano.

2. **A posteriori (Fase 7.4):** desde el dialog de detalle del listado, un
   admin puede corregir un marcado erróneo o registrar una asistencia que
   el funcionario olvidó marcar en cancha (fuera de ventana → solo admin).

## Regla de UI

El botón solo aparece si la reserva está **confirmada** y la franja está
**dentro de la ventana** (o el usuario es admin). Fuera de esas condiciones
se muestra el estado como texto, no como acción — la UI no ofrece botones
que el backend vaya a rechazar.

---

## Tareas de la Fase 7.5

```
[ ] Integrar en MisCampos.tsx (pantalla de Ocupación del Módulo 6)
    → Cada franja confirmada del día muestra:
      - Badge "✓ Asistió HH:MM" si asistencia_marcada_en != null
      - Botón "Marcar asistencia" si está en ventana y sin marcar
    → Confirmación con AlertDialog antes de marcar.
    → Toast de éxito con la hora exacta del marcado.

[ ] Integrar en DialogDetalleReserva.tsx (Fase 7.4)
    → Acción "Marcar/Desmarcar asistencia" por franja, con AlertDialog
      que explique la consecuencia (queda rastro en auditoria).

[ ] Crear asistenciaService.ts
    → marcar(reservaId, marcar: boolean) → POST /v1/reservas/{id}/asistencia.

[ ] Tests manuales
    → Funcionario en Ocupación marca asistencia de una franja de hoy →
      badge verde inmediato.
    → Admin desmarca desde el detalle → el badge desaparece y la
      auditoria muestra ambas acciones.
    → Franja fuera de ventana para un funcionario_control: sin botón.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): marcar asistencia desde ocupacion y detalle de reserva"
```

---

# FASE 7.6 — Smoke Test Final y Commit de Cierre

## Checklist de cierre del Módulo 7

```
[ ] Crear una solicitud desde web-public y confirmarla vía webhook
    simulado; verificar que aparece en /panel/reservas con estado
    confirmada y monto correcto.

[ ] Aplicar filtros combinados (estado + rango + campo + búsqueda) y
    verificar que el resultado es la intersección correcta.

[ ] Abrir el detalle de una solicitud y verificar que la timeline de
    auditoria muestra la creación y la confirmación en orden.

[ ] Como funcionario_control: el listado muestra SOLO sus campos aunque
    manipule el filtro funcionario_control_id desde DevTools.

[ ] Marcar asistencia desde la pantalla de Ocupación dentro de ventana
    → badge "✓ Asistió"; desmarcar desde el detalle como admin → el
    badge desaparece y auditoria tiene ambas filas.

[ ] Intentar marcar asistencia fuera de ventana como
    funcionario_control → el backend responde 422 y la UI no ofrece el
    botón.

[ ] Exportar CSV con filtros activos y verificar que el archivo
    contiene exactamente las filas filtradas, una por franja.

[ ] Verificar que un funcionario_control recibe 403 al intentar
    exportar CSV.

[ ] Verificar que el resource PÚBLICO de estado (Módulo 5) sigue sin
    exponer nombre_pagador, telefono_pagador ni
    referencia_recaudaciones (regresión).

[ ] Los pipelines de CI de backend y web-admin pasan en verde sobre un
    Pull Request que incluya todo el módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 7 - gestion operativa de reservas"
    → Tag sugerido: v0.10.0-gestion-operativa
```

---

## 📝 Notas finales

### Matriz de permisos del módulo

| Endpoint | admin_parametricas | admin_reservas | funcionario_control |
|---|---|---|---|
| `GET /v1/solicitudes-reserva` | ✅ todo | ✅ todo | ✅ solo sus campos |
| `GET /v1/solicitudes-reserva/{id}` | ✅ | ✅ | ✅ si es de sus campos |
| `POST /v1/reservas/{id}/asistencia` | ✅ (sin ventana) | ✅ (sin ventana) | ✅ sus campos, en ventana |
| `GET /v1/reservas/export` | ✅ | ✅ | ❌ 403 |

### Migraciones del módulo
Una sola: `2026_XX_XX_add_asistencia_to_reservas_table.php` (Fase 7.2).
No se modifican tablas existentes ni se tocan índices del Módulo 1.

### Relación con los módulos vecinos
- **Módulo 5:** este módulo lee `solicitudes_reserva` y `reservas` tal como
  las deja el flujo de cobro. No lo modifica.
- **Módulo 6:** la pantalla de Ocupación (Fase 6.4) gana el botón de
  asistencia en la Fase 7.5, pero su endpoint de ocupación no cambia.
- **Módulo 8 (antes 7):** seguridad y publicación no dependen de nada de
  este módulo; la renumeración es solo de número, no de contenido.

### Orden sugerido de implementación
1. **7.1** (listado backend) → desbloquea 7.4
2. **7.2** (asistencia backend) → desbloquea 7.5
3. **7.4** (listado frontend) en paralelo con **7.3** (CSV backend)
4. **7.5** (integración de asistencia en UI)
5. **7.6** cierre

---

