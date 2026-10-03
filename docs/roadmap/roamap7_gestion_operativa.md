Sí. Te dejo el **roadmap actualizado del Módulo 7**, versión **1.1.0**, corregido contra el contexto maestro, la auditoría real del repo y los hallazgos de la Fase 8.5.

La idea es que este reemplace al roadmap literal que me pasaste. Después vas pudiendo **tachar tareas** según avancemos.

---

# 📁 ROADMAP_MODULO_7_GESTION_OPERATIVA_RESERVAS.md  
**Versión actualizada:** 1.1.0  
**Fecha de actualización:** 2026-10-02  
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni  
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke  
**Estado general del módulo:** 🟨 Parcialmente adelantado por Fase 8.5  
**Bloquea:** nada crítico del flujo de cobro  
**No debe tocar:** webhook SIREB, polling, expiración, ConfirmacionCobroService, RecaudacionesApiClient, reglas de anulación condicionada, resource público `SolicitudEstadoResource`.

---

## 🧭 Resumen ejecutivo del Módulo 7

El Módulo 7 convierte al sistema en operable y auditable por el GAD Beni.

Debe permitir:

1. **Listar solicitudes/reservas desde el admin** con filtros, búsqueda y paginación.
2. **Ver detalle auditable** de cada solicitud, incluyendo historial de auditoría.
3. **Restringir server-side** lo que ve un `funcionario_control`.
4. **Marcar asistencia** en franjas confirmadas.
5. **Exportar CSV** para reportes externos.
6. **Integrar todo en web-admin** con la misma línea visual del panel.

---

# 🔁 Cambios principales respecto al roadmap v1.0.0

Estas son las correcciones importantes antes de empezar a tachar.

| # | Roadmap original | Roadmap actualizado | Motivo |
|---|---|---|---|
| 1 | Rutas bajo `auth:sanctum` | Rutas bajo `auth.oauth` | El panel ya usa OAuth2/Ibare. Sanctum quedó como legado/test. |
| 2 | `RoleMiddleware(['admin_parametricas', 'admin_reservas', 'funcionario_control'])` | `role:admin_parametricas\|admin_reservas\|funcionario_control` | Laravel separa middleware por coma. El fix actual del middleware soporta pipe. |
| 3 | Crear `SolicitudReservaAdminController` nuevo | Auditar/extender `Admin/SolicitudReservaController.php` existente | Ya existe controller admin de 202 LoC desde Fase 8.5. |
| 4 | Crear `SolicitudReservaResource` desde cero | Auditar/ampliar `SolicitudReservaResource.php` existente | Ya existe resource de 38 LoC. Probablemente incompleto o inline. |
| 5 | `/panel/reservas` es placeholder | Ya existe `pages/Reservas/index.tsx` con 213 LoC | 7.4 es refactor/mejora, no pantalla desde cero. |
| 6 | 7.1 incluye `asistencia_marcada_en` | 7.1 no depende de asistencia; el campo recién se agrega en 7.2 | La columna no existe todavía. |
| 7 | Migración usa `after('confirmado_en')` | Evitar `after()` en PostgreSQL | `after()` es más propio de MySQL; en Postgres puede no comportarse igual. |
| 8 | 7.5 integra en `MisCampos.tsx` | Primero ubicar o crear pantalla de Ocupación/MisCampos | En la auditoría no aparece `MisCampos.tsx` claro en web-admin. |
| 9 | Estados permitidos: pendiente, confirmada, expirada, rechazada | Incluir también `cancelada` | El enum `EstadoSolicitudReserva` incluye cancelada. |
| 10 | Stats cards solo frontend | Backend debe devolver `meta.stats` opcional | Para que los cards reflejen filtros reales y no solo página actual. |
| 11 | CI verde full suite | CI verde al menos para tests del módulo; deuda D-01 puede bloquear suite completa | Hay 19+ tests rotos por `tipo_tarifa` NOT NULL, deuda preexistente. |

---

# 🗺️ Mapa actualizado del Módulo 7

```text
Módulo 7
├── Fase 7.0 → Reconciliación de estado real vs roadmap
├── Fase 7.1 → Backend: listado admin de solicitudes con filtros, detalle auditable y stats
├── Fase 7.2 → Backend: marcar asistencia en sitio con ventana temporal y auditoría
├── Fase 7.3 → Backend: exportación CSV de reservas
├── Fase 7.4 → Web-admin: listado operativo /panel/reservas con dialog de detalle
├── Fase 7.5 → Web-admin: integración de asistencia en Ocupación y en detalle
└── Fase 7.6 → Smoke test final y commit de cierre
```

## Orden recomendado de ejecución

```text
7.0 Reconciliación
  ↓
7.1 Backend listado/detalle/stats
  ↓
7.4 Web-admin listado/detalle sin asistencia todavía
  ↓
7.2 Backend asistencia
  ↓
7.3 Backend export CSV
  ↓
7.5 Web-admin asistencia en Ocupación + detalle
  ↓
7.6 Smoke y cierre
```

### Por qué este orden

1. **7.1 primero** paga deuda de la Fase 8.5 y cierra el hueco de seguridad de `funcionario_control`.
2. **7.4 después de 7.1** permite validar el payload admin en UI sin esperar asistencia.
3. **7.2 después** agrega la única migración nueva del módulo.
4. **7.3 después de 7.2** porque el CSV debe incluir `asistencia_marcada_en`.
5. **7.5 al final de funcionalidad** porque depende de 7.2 y de ubicar correctamente la pantalla de Ocupación.
6. **7.6 cierra** con smoke completo.

---

# 🟦 FASE 7.0 — Reconciliación de estado real vs roadmap

**Objetivo:** confirmar qué ya existe, qué está parcial y qué rutas/prefijos usar para no romper lo construido en la Fase 8.5.

## Archivos a auditar

### Backend

```text
backend/routes/api.php
backend/app/Http/Controllers/Api/V1/Admin/SolicitudReservaController.php
backend/app/Http/Resources/SolicitudReservaResource.php
backend/app/Http/Resources/SolicitudEstadoResource.php
backend/app/Services/SolicitudReservaService.php
backend/app/Models/SolicitudReserva.php
backend/app/Models/SolicitudReservaDetalle.php
backend/app/Models/Reserva.php
backend/app/Models/CampoDeportivo.php
backend/app/Models/Funcionario.php
backend/app/Models/Rol.php
backend/app/Models/AsignacionFuncionario.php
backend/app/Models/Auditoria.php
backend/app/Enums/EstadoSolicitudReserva.php
backend/app/Http/Middleware/RoleMiddleware.php
backend/app/Http/Middleware/VerificaTokenOAuth.php
backend/tests/TestCase.php
```

### Web-admin

```text
web-admin/src/App.tsx
web-admin/src/pages/Reservas/index.tsx
web-admin/src/components/reservas/DialogDetalleReserva.tsx
web-admin/src/components/reservas/AnularLiquidacionDialog.tsx
web-admin/src/services/solicitudesReservaService.ts
web-admin/src/services/apiClient.ts
web-admin/src/types/reservas.ts
web-admin/src/components/app-sidebar.tsx
```

## Tareas

- [ ] Confirmar prefijo real de rutas admin actuales.
  - Preferido nuevo: `/api/v1/admin/solicitudes-reserva`
  - Si ya existe `/api/v1/solicitudes-reserva` protegido por OAuth y roles, mantener compatibilidad o crear alias controlado.
- [ ] Confirmar si `SolicitudReservaResource` actual es admin o solo un borrador.
- [ ] Confirmar si `Admin/SolicitudReservaController` ya tiene `index()` y `show()` o solo anulación.
- [ ] Confirmar si `web-admin/src/services/solicitudesReservaService.ts` ya llama listado/detalle.
- [ ] Confirmar si `/panel/reservas` ya renderiza tabla real o solo placeholder mejorado.
- [ ] Decidir si se incluye `meta.stats` en 7.1 o se pospone a 7.4.
  - Recomendación: incluirlo en 7.1 backend para que 7.4 no invente agregados en frontend.
- [ ] Identificar dónde vive la pantalla de Ocupación/MisCampos del Módulo 6.
  - Si no existe, crear ticket previo o adaptar 7.5 para trabajar solo desde detalle de reserva hasta tener Ocupación.
- [ ] Registrar deuda preexistente D-01: tests rotos por `tipo_tarifa` NOT NULL.
  - No bloquea 7.1 si los nuevos tests crean tarifas con `tipo_tarifa`.
  - Sí puede bloquear “suite completa verde” en 7.6.

## Criterio de salida de 7.0

- [ ] Tengo claro el prefijo de rutas admin.
- [ ] Sé qué archivos existentes debo extender y no recrear.
- [ ] Sé si `MisCampos.tsx` existe o es bloqueante para 7.5.
- [ ] El roadmap queda alineado al repo real.

---

# 🟩 FASE 7.1 — Backend: Listado Admin de Solicitudes con Filtros, Detalle Auditable y Stats

**Objetivo:** entregar el endpoint admin de solicitudes/reservas con filtros, búsqueda, paginación, restricción server-side para `funcionario_control`, detalle con auditoría y estadísticas opcionales.

## Principios rectores

1. **Resource admin distinto al público.**
   - `SolicitudReservaResource` puede exponer contacto y referencia SIREB.
   - `SolicitudEstadoResource` público no debe exponer `nombre_pagador`, `telefono_pagador`, `ci_nit_pagador` ni `referencia_recaudaciones`.

2. **Restricción server-side obligatoria.**
   - Un `funcionario_control` nunca puede espiar otros campos.
   - Si manda `funcionario_control_id` de otro, el backend lo ignora y fuerza el propio.
   - Además, solo debe ver detalles de campos asignados a él.

3. **No tocar flujo de cobro.**
   - Esta fase solo lee `solicitudes_reserva`, `solicitud_reserva_detalle`, `reservas`, `auditoria`, `campos_deportivos`, `asignaciones_funcionario`.

4. **No depender de asistencia todavía.**
   - La columna `asistencia_marcada_en` recién se crea en 7.2.
   - En 7.1, la reserva anidada puede exponer `id` y `codigo_reserva`, pero no asistencia.

---

## Endpoints objetivo

Prefijo recomendado:

```http
GET /api/v1/admin/solicitudes-reserva
GET /api/v1/admin/solicitudes-reserva/{id}
```

Si el proyecto ya usa otro prefijo admin compatible, mantenerlo y documentarlo.

Middleware:

```php
auth.oauth
role:admin_parametricas|admin_reservas|funcionario_control
```

No usar:

```php
auth:sanctum
```

en rutas admin nuevas.

---

## Query params soportados

Todos opcionales:

```text
estado
desde
hasta
campo_id
funcionario_control_id
buscar
page
per_page
```

### Reglas

- `estado`:
  - Valores válidos del enum:
    - `pendiente`
    - `confirmada`
    - `expirada`
    - `cancelada`
    - `rechazada`
- `desde` / `hasta`:
  - Formato `YYYY-MM-DD`.
  - Filtran sobre `creado_en`.
  - `desde` => inicio del día.
  - `hasta` => fin del día.
  - Usar timezone de la aplicación, idealmente `America/La_Paz` si ya está configurado.
- `campo_id`:
  - UUID válido.
  - Devuelve solicitudes que tengan al menos un detalle en ese campo.
- `funcionario_control_id`:
  - Solo respetado para `admin_parametricas` y `admin_reservas`.
  - Para `funcionario_control`, se sobrescribe por su propio ID.
  - Filtra solicitudes que tengan al menos un detalle en campos asignados a ese funcionario.
- `buscar`:
  - Case-insensitive.
  - Matchea:
    - `codigo_seguimiento`
    - `nombre_pagador`
    - `telefono_pagador`
    - `ci_nit_pagador`
  - En PostgreSQL usar `ILIKE` o `LOWER(...) LIKE LOWER(...)`.
- `page`:
  - Default 1.
- `per_page`:
  - Default 15.
  - Máximo 100.

Orden por defecto:

```text
creado_en DESC, id DESC
```

---

## Visibilidad para `funcionario_control`

Regla fina importante:

Si una solicitud tiene varios detalles y solo algunos pertenecen a campos asignados al `funcionario_control`, el backend debe:

1. Incluir la solicitud solo si tiene al menos un detalle visible.
2. Retornar únicamente los detalles visibles.
3. No exponer detalles de campos no asignados.

Esto evita filtración lateral dentro de una misma solicitud multi-cancha.

Ejemplo:

```text
Solicitud con:
- Cancha A asignada a Juan
- Cancha B no asignada a Juan

Juan ve:
- La solicitud
- Solo detalle de Cancha A

Juan no ve:
- Detalle de Cancha B
- Montos parciales de Cancha B si estuvieran separados
- Datos sensibles del otro campo
```

Para admins:

- Ven todos los detalles.

---

## Respuesta listada

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
            "codigo_reserva": "RSV-8F3K2"
          }
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "total": 48,
    "per_page": 15,
    "stats": {
      "total": 48,
      "pendientes": 2,
      "confirmadas": 40,
      "expiradas": 3,
      "canceladas": 1,
      "rechazadas": 2,
      "monto_confirmado": 1840.00
    }
  }
}
```

### Nota sobre `stats`

Las `stats` deben calcularse con los mismos filtros del listado, **excepto** el filtro `estado`, para que tenga sentido mostrar distribución por estado.

Ejemplo:

Si el usuario filtra `estado=confirmada`, la tabla muestra solo confirmadas, pero los stats pueden mostrar:

```text
total: todas las que cumplen desde/hasta/campo/buscar/funcionario_control
pendientes: ...
confirmadas: ...
```

Si se prefiere simpler, se puede hacer que `stats` respete todos los filtros incluyendo estado, pero entonces los cards pierden utilidad operativa.

Recomendación: **stats sin filtro de estado**.

---

## Respuesta detalle

```json
{
  "data": {
    "id": "uuid",
    "codigo_seguimiento": "RES-...",
    "estado": "confirmada",
    "monto_total": 280.00,
    "monto_confirmado": 280.00,
    "nombre_pagador": "Juan Pérez",
    "telefono_pagador": "70000000",
    "ci_nit_pagador": "1234567",
    "referencia_recaudaciones": "REC-...",
    "creado_en": "...",
    "expira_en": "...",
    "confirmado_en": "...",
    "detalles": [...],
    "auditoria": [
      {
        "id": "...",
        "entidad": "solicitud_reserva",
        "entidad_id": "...",
        "accion": "crear",
        "usuario": "...",
        "funcionario_id": "...",
        "antes": null,
        "despues": {...},
        "creado_en": "..."
      },
      {
        "id": "...",
        "entidad": "solicitud_reserva",
        "entidad_id": "...",
        "accion": "confirmar_cobro",
        "usuario": "...",
        "funcionario_id": null,
        "antes": {...},
        "despues": {...},
        "creado_en": "..."
      }
    ]
  }
}
```

La auditoría debe venir ordenada cronológicamente ascendente para alimentar la timeline del dialog.

---

## Tareas Fase 7.1

### DTO

- [ ] Crear `backend/app/DTOs/SolicitudFiltrosDTO.php`.
  - Propiedades:
    - `?string $estado`
    - `?CarbonImmutable $desde`
    - `?CarbonImmutable $hasta`
    - `?string $campoId`
    - `?string $funcionarioControlId`
    - `?string $buscar`
    - `int $page`
    - `int $perPage`
  - Método estático:
    - `fromRequest(Request $request): self`
  - Validaciones suaves:
    - estado válido según enum;
    - fechas parseables;
    - `perPage` entre 1 y 100;
    - UUIDs si se validan estrictamente.

### Resource admin

- [ ] Auditar `backend/app/Http/Resources/SolicitudReservaResource.php`.
- [ ] Ampliarlo para versión admin.
  - Incluir:
    - contacto;
    - referencia SIREB;
    - montos;
    - fechas;
    - detalles;
    - reserva anidada básica.
  - No incluir `asistencia_marcada_en` en esta fase.
- [ ] Asegurar que `reserva` pueda ser `null` si la solicitud aún no generó reserva.
- [ ] Separar claramente del resource público `SolicitudEstadoResource`.

### Servicio

- [ ] Auditar `backend/app/Services/SolicitudReservaService.php`.
- [ ] Agregar o refactorizar método `listarAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario)`.
  - Aplicar restricción server-side:
    - si rol es `funcionario_control`, forzar `funcionarioControlId` al propio;
    - filtrar solicitudes con detalles en campos asignados;
    - restringir detalles visibles a campos asignados.
  - Eager loading:
    - `detalles.campo`
    - `detalles.reserva`
    - posiblemente `detalles.campo.asignaciones` si se necesita filtro por asignación.
  - Filtros:
    - estado;
    - rango `creado_en`;
    - campo_id;
    - funcionario_control_id;
    - búsqueda ILIKE.
  - Paginación.
  - Orden `creado_en DESC, id DESC`.
- [ ] Agregar método `obtenerAdmin(string $id, Funcionario $funcionario)`.
  - Busca solicitud.
  - Aplica restricción de visibilidad.
  - Carga auditoría ordenada.
  - Devuelve estructura para resource/show.
- [ ] Agregar método o query auxiliar para `stats`.
  - Puede ser `calcularStatsAdmin(SolicitudFiltrosDTO $filtros, Funcionario $funcionario)`.
  - Debe respetar visibilidad del funcionario.
  - Idealmente ignora filtro `estado`.

### Controller

- [ ] Auditar `backend/app/Http/Controllers/Api/V1/Admin/SolicitudReservaController.php`.
- [ ] No crear controller duplicado si ya existe.
- [ ] Agregar/ajustar `index()`.
  - Recibe request.
  - Construye DTO.
  - Obtiene funcionario autenticado.
  - Llama servicio.
  - Devuelve paginated resource.
  - Incluye `meta.stats` si se implementa.
- [ ] Agregar/ajustar `show($id)`.
  - Valida acceso.
  - Devuelve detalle + auditoría.
- [ ] Mantener `anular()` existente sin cambiar reglas críticas.
  - Regla #5: anulación condicionada.
  - Si SIREB responde `LIQUIDACION_NO_ANULABLE`, no cancelar localmente si hay pago posible.

### Rutas

- [ ] Actualizar `backend/routes/api.php`.
- [ ] Usar middleware:

```php
auth.oauth
role:admin_parametricas|admin_reservas|funcionario_control
```

- [ ] Registrar:

```http
GET /api/v1/admin/solicitudes-reserva
GET /api/v1/admin/solicitudes-reserva/{id}
```

o el prefijo admin ya existente.

- [ ] No registrar estas rutas bajo guard Sanctum para producción.

### Tests

Crear o ampliar:

```text
backend/tests/Feature/Api/V1/Admin/SolicitudReservaListadoTest.php
```

Tests mínimos:

- [ ] `index()` sin filtros devuelve solicitudes paginadas.
- [ ] `index(estado='confirmada')` solo devuelve confirmadas.
- [ ] `index(estado='cancelada')` funciona, porque el enum incluye cancelada.
- [ ] `index(desde, hasta)` filtra por `creado_en`.
- [ ] `index(campo_id)` solo devuelve solicitudes con detalle en ese campo.
- [ ] `index(buscar='juan')` matchea `nombre_pagador` case-insensitive.
- [ ] `index(buscar='1234567')` matchea `ci_nit_pagador`.
- [ ] `index(funcionario_control_id=X)` para admin filtra por campos asignados a X.
- [ ] `funcionario_control` que manda `funcionario_control_id=otro` recibe solo sus propios campos.
- [ ] `funcionario_control` no ve detalles de campos no asignados aunque la solicitud tenga varios detalles.
- [ ] `show()` incluye auditoría ordenada cronológicamente.
- [ ] `show()` de solicitud no existente devuelve 404.
- [ ] `show()` de solicitud fuera de visibilidad para `funcionario_control` devuelve 403 o 404 según convención del proyecto.
- [ ] Usuario no autenticado recibe 401.
- [ ] Rol sin permiso recibe 403.
- [ ] Regresión: `SolicitudEstadoResource` público sigue sin exponer contacto ni referencia SIREB.

Consideraciones para tests:

- [ ] Usar `Sanctum::actingAs($funcionario)` en tests admin.
- [ ] Confiar en `withoutMiddleware(VerificaTokenOAuth::class)` global de `TestCase`.
- [ ] Al crear `TarifaCampo`, incluir `tipo_tarifa` para no reproducir deuda D-01.
- [ ] No asumir factories inexistentes para `Funcionario`, `TipoCampo`, `CampoDeportivo`.
- [ ] Crear datos explícitamente.

### Criterios de aceptación 7.1

- [ ] Admin puede listar solicitudes con filtros combinados.
- [ ] `funcionario_control` solo ve sus campos, incluso manipulando query params.
- [ ] Detalle incluye timeline de auditoría.
- [ ] Resource admin no contamina resource público.
- [ ] Tests nuevos de 7.1 pasan.
- [ ] No se rompió anulación SIREB existente.
- [ ] No se tocó webhook/polling/expiración.

### Commit sugerido

```text
feat(admin): listado de solicitudes con filtros, detalle auditable y restricción server-side
```

---

# 🟨 FASE 7.2 — Backend: Marcar Asistencia en Sitio

**Objetivo:** agregar la capacidad de marcar/desmarcar asistencia sobre franjas confirmadas, con ventana temporal, permisos y auditoría.

## Reglas de negocio

1. Solo reservas pertenecientes a solicitudes **confirmadas** pueden tener asistencia.
2. `funcionario_control`:
   - solo puede marcar asistencia en campos asignados;
   - solo dentro de ventana temporal.
3. `admin_parametricas` / `admin_reservas`:
   - pueden marcar/desmarcar fuera de ventana como corrección a posteriori.
4. Ventana temporal:
   - desde 1 hora antes de `hora_inicio`;
   - hasta 24 horas después de `hora_fin`.
5. Cada marcado/desmarcado queda auditado.
6. Marcar dos veces no debe duplicar timestamp final, pero puede auditar ambas acciones según decisión de producto.
   - Roadmap original exige auditar ambas acciones. Mantener eso salvo cambio explícito.

---

## Migración

Nombre sugerido:

```text
backend/database/migrations/2026_10_02_000001_add_asistencia_to_reservas_table.php
```

Contenido corregido para PostgreSQL:

```php
Schema::table('reservas', function (Blueprint $table) {
    $table->timestamp('asistencia_marcada_en')->nullable();
    $table->foreignUuid('asistencia_marcada_por')
          ->nullable()
          ->constrained('funcionarios')
          ->nullOnDelete();
});
```

Evitar:

```php
->after('confirmado_en')
```

porque en PostgreSQL no es confiable/estándar como en MySQL.

---

## Endpoint

Prefijo recomendado:

```http
POST /api/v1/admin/reservas/{id}/asistencia
```

Body:

```json
{
  "marcar": true
}
```

o:

```json
{
  "marcar": false
}
```

Middleware:

```php
auth.oauth
role:admin_parametricas|admin_reservas|funcionario_control
```

Respuestas:

| Caso | HTTP |
|---|---:|
| Marcado/desmarcado exitoso | 200 |
| Reserva no encontrada | 404 |
| Reserva no pertenece a solicitud confirmada | 422 |
| Funcionario no asignado al campo y no es admin | 403 |
| Fuera de ventana y no es admin | 422 |
| Payload inválido | 422 |

Respuesta exitosa sugerida:

```json
{
  "data": {
    "id": "uuid-reserva",
    "codigo_reserva": "RSV-...",
    "asistencia_marcada_en": "2026-10-02T18:42:00Z",
    "asistencia_marcada_por": "uuid-funcionario"
  }
}
```

---

## Tareas Fase 7.2

### Migración

- [ ] Crear migración `add_asistencia_to_reservas_table`.
- [ ] Ejecutar en local/test.
- [ ] Verificar FK a `funcionarios`.
- [ ] Verificar `nullOnDelete`.

### Modelo Reserva

- [ ] Actualizar `backend/app/Models/Reserva.php`.
  - Agregar a `$fillable`:
    - `asistencia_marcada_en`
    - `asistencia_marcada_por`
  - Cast:
    - `asistencia_marcada_en` => `datetime`
  - Relación:
    - `asistenciaMarcadaPor(): BelongsTo`

### Servicio

- [ ] Crear `backend/app/Services/MarcarAsistenciaService.php`.
- [ ] Método:

```php
ejecutar(Reserva $reserva, Funcionario $funcionario, bool $marcar): Reserva
```

Validaciones en orden:

1. Reserva existe.
2. Solicitud relacionada está `confirmada`.
3. Permiso:
   - admin: ok;
   - funcionario_control: debe estar asignado al campo de la reserva.
4. Ventana temporal:
   - admin: sin restricción;
   - funcionario_control: dentro de ventana.
5. Ejecutar update:
   - si `marcar=true`:
     - set `asistencia_marcada_en = now()`;
     - set `asistencia_marcada_por = funcionario->id`;
   - si `marcar=false`:
     - set null ambos campos.
6. Registrar auditoría:
   - acción `marcar_asistencia` o `desmarcar_asistencia`.
   - entidad `reserva`.
   - guardar antes/después si el servicio de auditoría lo soporta.

### Controller + Request

- [ ] Crear `backend/app/Http/Controllers/Api/V1/Admin/AsistenciaController.php` o agregar método al controller admin existente si tiene sentido.
- [ ] Crear `backend/app/Http/Requests/StoreAsistenciaRequest.php`.
  - Regla:
    - `marcar` required boolean.
- [ ] Controller delgado:
  - resuelve reserva;
  - obtiene funcionario;
  - llama servicio;
  - responde resource o JSON simple.

### Rutas

- [ ] Registrar:

```http
POST /api/v1/admin/reservas/{id}/asistencia
```

con:

```php
auth.oauth
role:admin_parametricas|admin_reservas|funcionario_control
```

### Resource

- [ ] Actualizar `SolicitudReservaResource` para incluir en reserva anidada:
  - `asistencia_marcada_en`
  - opcionalmente `asistencia_marcada_por`
- [ ] Asegurar que 7.1 siga funcionando si la columna ya existe.
  - Idealmente 7.2 se implementa después de 7.1, pero el resource final debe quedar preparado.

### Tests

Crear:

```text
backend/tests/Feature/Api/V1/Admin/AsistenciaReservaTest.php
```

Tests:

- [ ] Funcionario asignado marca dentro de ventana → 200.
- [ ] `asistencia_marcada_en` no null.
- [ ] `asistencia_marcada_por` es el funcionario.
- [ ] Existe fila en auditoría `marcar_asistencia`.
- [ ] Funcionario no asignado → 403.
- [ ] Funcionario asignado fuera de ventana → 422.
- [ ] Admin marca fuera de ventana → 200.
- [ ] Desmarcar → `asistencia_marcada_en = null`.
- [ ] Desmarcar audita `desmarcar_asistencia`.
- [ ] Marcar dos veces seguidas no cambia timestamp final o lo actualiza según decisión.
  - Roadmap original: idempotente en estado final, pero audita ambas acciones.
- [ ] Intentar marcar reserva de solicitud pendiente → 422.
- [ ] Intentar marcar reserva de solicitud expirada → 422.
- [ ] Intentar marcar reserva de solicitud cancelada → 422.
- [ ] Reserva inexistente → 404.

### Criterios de aceptación 7.2

- [ ] Asistencia solo sobre solicitudes confirmadas.
- [ ] Ventana temporal aplicada correctamente.
- [ ] Admin puede corregir fuera de ventana.
- [ ] Funcionario control solo en sus campos y en ventana.
- [ ] Auditoría registra marcado y desmarcado.
- [ ] Tests pasan.

### Commit sugerido

```text
feat(admin): marcar asistencia en sitio con ventana temporal y auditoria
```

---

# 🟧 FASE 7.3 — Backend: Exportación CSV de Reservas

**Objetivo:** exportar reservas/franjas filtradas a CSV usando stream, sin cargar todo en memoria.

## Reglas importantes

1. Exporta una fila por franja/detalle/reserva, no por solicitud.
2. Solo admins pueden exportar:
   - `admin_parametricas`
   - `admin_reservas`
3. `funcionario_control` NO puede exportar.
   - Motivo: evitar fuga masiva de datos de contacto desde cuenta de campo.
4. Debe reutilizar filtros del listado.
5. Debe incluir `asistencia_marcada_en`, por eso va después de 7.2.
6. Debe usar `StreamedResponse` + `chunk()`.

---

## Endpoint

```http
GET /api/v1/admin/reservas/export?formato=csv
```

Middleware:

```php
auth.oauth
role:admin_parametricas|admin_reservas
```

Headers:

```text
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="reservas-YYYY-MM-DD.csv"
```

Para Excel, agregar BOM UTF-8:

```text
\xEF\xBB\xBF
```

Columnas:

```text
codigo_seguimiento
estado
nombre_pagador
telefono_pagador
ci_nit_pagador
codigo_reserva
campo_nombre
fecha_reserva
hora_inicio
hora_fin
monto_pagado
asistencia_marcada_en
```

Definición de `monto_pagado`:

- Si la solicitud está confirmada: `monto_confirmado`.
- Si no está confirmada: vacío, `0` o `monto_total` según decisión.
- Recomendación: vacío si no hay pago confirmado.

---

## Tareas Fase 7.3

- [ ] Agregar método `exportarCsv(SolicitudFiltrosDTO $filtros, Funcionario $funcionario): Generator` en `SolicitudReservaService`.
  - Reutilizar query de `listarAdmin()`.
  - Sin paginación.
  - Con `chunk(500)`.
  - Eager loading:
    - `detalles.campo`
    - `detalles.reserva`
  - Aplanar una fila por detalle/reserva.
- [ ] Crear `backend/app/Http/Controllers/Api/V1/Admin/ReservasExportController.php`.
  - Método `csv(Request $request)`.
  - Construye DTO.
  - Valida rol.
  - Envuelve generator en `StreamedResponse`.
- [ ] Registrar ruta:

```http
GET /api/v1/admin/reservas/export
```

con:

```php
auth.oauth
role:admin_parametricas|admin_reservas
```

- [ ] Tests:

```text
backend/tests/Feature/Api/V1/Admin/ReservasExportTest.php
```

Tests mínimos:

- [ ] Export sin filtros incluye una fila por franja sembrada.
- [ ] Export con `estado=confirmada` excluye pendientes.
- [ ] Export con rango de fechas respeta rango.
- [ ] Export con `campo_id` filtra correctamente.
- [ ] `funcionario_control` recibe 403.
- [ ] Response tiene `Content-Type: text/csv`.
- [ ] Response tiene `Content-Disposition` con fecha actual.
- [ ] CSV incluye BOM UTF-8.
- [ ] Columnas coinciden con especificación.

### Criterios de aceptación 7.3

- [ ] CSV descarga correctamente.
- [ ] No explota memoria con datasets grandes.
- [ ] Respeta filtros.
- [ ] Bloquea exportación a `funcionario_control`.
- [ ] Incluye asistencia marcada si existe.

### Commit sugerido

```text
feat(admin): exportacion CSV de reservas con streamed response
```

---

# 🟪 FASE 7.4 — Web-admin: Listado Operativo `/panel/reservas`

**Objetivo:** convertir la pantalla actual en listado operativo real, consumiendo el backend de 7.1.

## Estado actual conocido

Según auditoría, ya existen:

```text
web-admin/src/pages/Reservas/index.tsx
web-admin/src/components/reservas/DialogDetalleReserva.tsx
web-admin/src/services/solicitudesReservaService.ts
web-admin/src/types/reservas.ts
```

Por tanto, 7.4 no es “crear desde cero”, sino alinear, ampliar y pulir.

---

## Estructura objetivo

```text
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
│   - Card del pagador                                      │
│   - Timeline de auditoría                                 │
│   - Franjas con código de reserva                         │
│   - Botón "Ver comprobante"                               │
│   - Más adelante: marcar/desmarcar asistencia             │
└──────────────────────────────────────────────────────────┘
```

En primera pasada de 7.4, antes de 7.2/7.5:

- El botón “Marcar asistencia” puede estar oculto o deshabilitado.
- El botón “Exportar” puede estar oculto o deshabilitado hasta 7.3.

---

## Badges de estado

- `confirmada`: emerald + `CheckCircle2`
- `pendiente`: amber + `Clock`
  - si `expira_en > now`, mostrar countdown relativo o tiempo restante.
- `expirada`: slate + `XCircle`
- `cancelada`: zinc/slate + `Ban` o `XCircle`
- `rechazada`: red + `AlertTriangle`

---

## Tareas Fase 7.4

### Tipos

- [ ] Actualizar `web-admin/src/types/reservas.ts`.
  - Interfaces:
    - `SolicitudReservaAdmin`
    - `DetalleSolicitudAdmin`
    - `ReservaAnidada`
    - `FiltrosReserva`
    - `EntradaAuditoria`
    - `StatsReserva`
  - Alinear nombres con backend:
    - snake_case si el API devuelve snake_case;
    - o normalizar en service.

### Service

- [ ] Actualizar `web-admin/src/services/solicitudesReservaService.ts`.
  - `listar(filtros)`
  - `obtener(id)`
  - `exportarCsv(filtros)` opcional hasta 7.3.
  - Usar `apiClient` con `withCredentials: true`.
  - Retornar `data` y `meta`.
  - Manejar errores con toast o lanzamiento controlado.

### Página listado

- [ ] Refactorizar `web-admin/src/pages/Reservas/index.tsx`.
  - Header con eyebrow:
    - “GESTIÓN OPERATIVA”
    - título “Reservas”
    - subtítulo “Auditoría de todas las solicitudes del sistema”
  - Stats cards desde `meta.stats`.
  - Toolbar:
    - búsqueda;
    - estado;
    - desde/hasta;
    - campo.
  - Tabla paginada.
  - Click en fila abre dialog detalle.
  - Dropdown de acciones por fila:
    - Ver detalle;
    - Ver comprobante;
    - Exportar? no por fila, global;
    - Más adelante: asistencia.
  - Estado vacío.
  - Loading skeletons.
  - Error state.

### Tabla

Usar `@tanstack/react-table` v9 con features declarativas.

Columnas mínimas:

```text
código_seguimiento
nombre_pagador
campo(s)
fecha_reserva
monto_total / monto_confirmado
estado
creado_en
acciones
```

Para `campo(s)`:

- Si hay un detalle: mostrar nombre.
- Si hay varios: “Cancha Central +2”.

Para fecha:

- Mostrar fecha de la primera franja o rango resumido.
- Evitar sobrecargar.

### Dialog detalle

- [ ] Actualizar `web-admin/src/components/reservas/DialogDetalleReserva.tsx`.
  - Card pagador:
    - nombre;
    - CI/NIT;
    - teléfono con `tel:`;
    - referencia SIREB.
  - Montos:
    - total;
    - confirmado.
  - Fechas:
    - creado;
    - expira;
    - confirmado.
  - Timeline auditoría:
    - vertical;
    - orden cronológico;
    - ícono por acción.
  - Franjas:
    - campo;
    - fecha;
    - hora inicio-fin;
    - tarifa;
    - código reserva.
  - Botón “Ver comprobante”:
    - abre `/comprobante/{codigo_seguimiento}` o ruta pública correspondiente.
  - Reservar espacio para asistencia, pero no activarla hasta 7.5.

### Sidebar/breadcrumb

- [ ] Verificar `/panel/reservas` en sidebar.
- [ ] Verificar breadcrumb `RUTA_LABELS`.
- [ ] Confirmar que no apunta a placeholder.

### UI warnings

- [ ] Recordar que web-admin usa **base-ui**, no Radix.
- [ ] No copiar patrones con `asChild` si no son compatibles.
- [ ] Revisar dropdown/dialog para evitar warning `<button> dentro de <button>`.

### Tests manuales

- [ ] Listado carga con stats correctas.
- [ ] Filtro estado funciona.
- [ ] Filtro fechas funciona.
- [ ] Filtro campo funciona.
- [ ] Búsqueda funciona.
- [ ] Filtros combinados funcionan.
- [ ] Click en fila abre detalle.
- [ ] Timeline muestra auditoría.
- [ ] Botón comprobante abre ruta correcta.
- [ ] Paginación funciona.
- [ ] Usuario `funcionario_control` ve solo sus campos.

### Criterios de aceptación 7.4

- [ ] Pantalla operativa real.
- [ ] Consola sin errores graves.
- [ ] Coherencia visual con `CamposDeportivos.tsx` / `TiposCampo.tsx`.
- [ ] No rompe flujo de anulación existente.
- [ ] No muestra asistencia como acción funcional hasta 7.2/7.5.

### Commit sugerido

```text
feat(web-admin): listado operativo de reservas con detalle auditable
```

---

# 🟥 FASE 7.5 — Web-admin: Integración de Asistencia en Ocupación y Detalle

**Objetivo:** permitir marcar/desmarcar asistencia desde UI.

## Bloqueo detectado

El roadmap original dice integrar en `MisCampos.tsx`, pantalla de Ocupación del Módulo 6.

Pero en la auditoría no aparece claramente:

```text
web-admin/src/pages/.../MisCampos.tsx
```

Ni una pantalla obvia de Ocupación diaria en web-admin.

Por tanto, antes de 7.5 hay que resolver:

- [ ] ¿Dónde está la pantalla de Ocupación/MisCampos?
- [ ] ¿Está en Dashboard?
- [ ] ¿Está en otra ruta?
- [ ] ¿Nunca se construyó frontend del Módulo 6 Fase 6.4?
- [ ] ¿Hay que crearla como prerequisito?

Si no existe, 7.5 debe dividirse:

```text
7.5.A Descubrimiento/creación de pantalla de Ocupación
7.5.B Integración de asistencia en Ocupación
7.5.C Integración de asistencia en DialogDetalleReserva
```

---

## Regla de UI

El botón de asistencia solo debe aparecer si:

1. La solicitud está `confirmada`.
2. La reserva/franja existe.
3. El usuario puede marcarla:
   - admin siempre;
   - funcionario_control solo si está asignado al campo y dentro de ventana.

Fuera de eso:

- Mostrar estado como texto.
- No ofrecer botón que el backend vaya a rechazar.

---

## Tareas Fase 7.5

### Service

- [ ] Crear o actualizar:

```text
web-admin/src/services/asistenciaService.ts
```

Método:

```ts
marcar(reservaId: string, marcar: boolean)
```

Llama:

```http
POST /api/v1/admin/reservas/{id}/asistencia
```

### Ocupación / MisCampos

- [ ] Localizar o crear pantalla de Ocupación.
- [ ] En cada franja confirmada del día:
  - si `asistencia_marcada_en != null`:
    - badge “✓ Asistió HH:MM”;
  - si null y en ventana:
    - botón “Marcar asistencia”;
  - si null y fuera de ventana:
    - texto “Fuera de ventana” para funcionario_control;
    - botón admin si es admin.
- [ ] Confirmación con `AlertDialog`.
- [ ] Toast de éxito con hora exacta.
- [ ] Refrescar consulta local o invalidar cache.

### Dialog detalle

- [ ] Agregar acción por franja:
  - “Marcar asistencia”;
  - “Desmarcar asistencia” si ya está marcada y usuario puede.
- [ ] Mostrar badge de asistencia si existe.
- [ ] Confirmación con `AlertDialog`.
- [ ] Explicar que queda rastro en auditoría.
- [ ] Actualizar estado local tras éxito.

### Permisos UI

- [ ] Obtener rol del usuario autenticado desde `AuthContext`.
- [ ] Si es admin:
  - permitir marcar/desmarcar siempre.
- [ ] Si es funcionario_control:
  - permitir solo si franja del día/ventana y campo asignado.
- [ ] La UI debe ser espejo de backend, pero el backend siempre manda.

### Tests manuales

- [ ] Funcionario en Ocupación marca asistencia de franja de hoy → badge inmediato.
- [ ] Admin desmarca desde detalle → badge desaparece.
- [ ] Auditoría muestra marcado y desmarcado.
- [ ] Funcionario_control fuera de ventana no ve botón.
- [ ] Funcionario_control no asignado no ve botón o recibe 403 si intenta forzar.
- [ ] Marcar dos veces no rompe UI.

### Criterios de aceptación 7.5

- [ ] Asistencia marcable desde Ocupación si existe pantalla.
- [ ] Asistencia marcable/desmarcable desde detalle.
- [ ] UI no ofrece acciones que backend rechazaría.
- [ ] Toasts y estados correctos.
- [ ] No hay warnings graves de base-ui.

### Commit sugerido

```text
feat(web-admin): marcar asistencia desde ocupacion y detalle de reserva
```

---

# ⬛ FASE 7.6 — Smoke Test Final y Commit de Cierre

**Objetivo:** validar el módulo completo y cerrar.

## Checklist de smoke

- [ ] Crear solicitud desde web-public.
- [ ] Confirmarla vía webhook simulado o polling controlado.
- [ ] Verificar que aparece en `/panel/reservas` como confirmada.
- [ ] Verificar monto correcto.
- [ ] Aplicar filtros combinados:
  - estado;
  - rango;
  - campo;
  - búsqueda.
- [ ] Verificar intersección correcta.
- [ ] Abrir detalle.
- [ ] Verificar timeline de auditoría:
  - creación;
  - confirmación;
  - otros eventos si existen.
- [ ] Como `funcionario_control`:
  - listar solo sus campos;
  - manipular `funcionario_control_id` desde DevTools y confirmar que no espía.
- [ ] Marcar asistencia desde Ocupación dentro de ventana.
- [ ] Verificar badge “✓ Asistió”.
- [ ] Desmarcar desde detalle como admin.
- [ ] Verificar auditoría con ambas acciones.
- [ ] Intentar marcar fuera de ventana como funcionario_control:
  - backend 422;
  - UI no ofrece botón.
- [ ] Exportar CSV con filtros activos.
- [ ] Verificar una fila por franja.
- [ ] Verificar columnas correctas.
- [ ] Verificar Excel abre bien con UTF-8.
- [ ] Verificar `funcionario_control` recibe 403 al exportar.
- [ ] Verificar resource público `SolicitudEstadoResource` sigue sin exponer:
  - `nombre_pagador`;
  - `telefono_pagador`;
  - `ci_nit_pagador`;
  - `referencia_recaudaciones`.
- [ ] Correr tests del módulo:

```powershell
php artisan test --filter SolicitudReservaListadoTest
php artisan test --filter AsistenciaReservaTest
php artisan test --filter ReservasExportTest
```

- [ ] Correr tests admin existentes:

```powershell
php artisan test --filter SolicitudReservaAnulacionTest
php artisan test --filter ConfirmacionCobroServiceEventoTest
```

- [ ] Revisar consola web-admin:
  - sin errores;
  - warnings de base-ui controlados;
  - sin `<button> dentro de <button>` nuevos.
- [ ] Revisar red:
  - requests con credentials;
  - respuestas 200/403/422 correctas.
- [ ] CI:
  - si la suite completa aún falla por D-01, documentar baseline;
  - idealmente dejar tests del módulo verdes;
  - si se quiere CI full verde, abrir ticket aparte para D-01 antes de cerrar módulo.

## Commit final

```text
chore: cierre Módulo 7 - gestion operativa de reservas
```

Tag sugerido:

```text
v0.10.0-gestion-operativa
```

---

# 🔐 Matriz de permisos actualizada

| Endpoint | admin_parametricas | admin_reservas | funcionario_control |
|---|---|---|---|
| `GET /api/v1/admin/solicitudes-reserva` | ✅ todo | ✅ todo | ✅ solo sus campos y detalles visibles |
| `GET /api/v1/admin/solicitudes-reserva/{id}` | ✅ | ✅ | ✅ solo si tiene detalles en sus campos |
| `POST /api/v1/admin/reservas/{id}/asistencia` | ✅ sin ventana | ✅ sin ventana | ✅ solo campo asignado y dentro de ventana |
| `GET /api/v1/admin/reservas/export` | ✅ | ✅ | ❌ 403 |

---

# 🧱 Migraciones del módulo actualizado

Solo una migración nueva:

```text
2026_10_02_000001_add_asistencia_to_reservas_table.php
```

Columnas:

```text
reservas.asistencia_marcada_en TIMESTAMP NULL
reservas.asistencia_marcada_por UUID NULL FK funcionarios(id) ON DELETE SET NULL
```

No se modifican tablas existentes ni índices del Módulo 1.

---

# ⚠️ Deuda y riesgos registrados

| ID | Riesgo/deuda | Impacto | Acción |
|---|---|---|---|
| R-7.1 | Roadmap original usaba `auth:sanctum` | Alto | Usar `auth.oauth` |
| R-7.2 | Controller/resource ya existen desde 8.5 | Medio | Auditar/extender, no duplicar |
| R-7.3 | `funcionario_control` podría ver detalles no asignados en solicitudes multi-cancha | Alto | Filtrar detalles visibles server-side |
| R-7.4 | `MisCampos.tsx` no aparece claro en audit | Alto para 7.5 | Descubrir o crear pantalla de Ocupación |
| R-7.5 | D-01 tests rotos por `tipo_tarifa` | Medio | Nuevos tests deben incluir `tipo_tarifa`; decidir ticket aparte |
| R-7.6 | base-ui no equivale a Radix | Bajo/Medio | No usar patrones `asChild` heredados sin verificar |
| R-7.7 | CSV con datos personales | Alto | Bloquear export a funcionario_control |
| R-7.8 | Confirmación tardía no revive | Crítico | No tocar ConfirmacionCobroService desde Módulo 7 |

---

# ✅ Checklist corta para empezar a tachar ahora

Si querés arrancar ya, esta es la mini-checklist de la Fase 7.1:

```text
[ ] 7.0 Auditar routes/api.php
[ ] 7.0 Auditar Admin/SolicitudReservaController.php
[ ] 7.0 Auditar SolicitudReservaResource.php
[ ] 7.0 Auditar SolicitudReservaService.php
[ ] 7.1 Crear SolicitudFiltrosDTO
[ ] 7.1 Ampliar SolicitudReservaResource admin
[ ] 7.1 Agregar listarAdmin() en SolicitudReservaService
[ ] 7.1 Agregar obtenerAdmin() con auditoría
[ ] 7.1 Agregar stats admin opcionales
[ ] 7.1 Forzar visibilidad server-side para funcionario_control
[ ] 7.1 Filtrar detalles visibles de funcionario_control
[ ] 7.1 Actualizar index() en controller admin
[ ] 7.1 Actualizar show() en controller admin
[ ] 7.1 Registrar rutas con auth.oauth y role pipe
[ ] 7.1 Tests listado sin filtros
[ ] 7.1 Tests filtro estado
[ ] 7.1 Tests filtro fechas
[ ] 7.1 Tests filtro campo_id
[ ] 7.1 Tests búsqueda case-insensitive
[ ] 7.1 Tests funcionario_control no espía
[ ] 7.1 Tests show con auditoría
[ ] 7.1 Tests 401/403
[ ] 7.1 Regresión resource público
[ ] 7.1 Commit Fase 7.1
```

---

Este es el roadmap actualizado y realista. El siguiente paso práctico sería que me pases estos archivos para empezar a tachar 7.1 con precisión:

```text
backend/routes/api.php
backend/app/Http/Controllers/Api/V1/Admin/SolicitudReservaController.php
backend/app/Http/Resources/SolicitudReservaResource.php
backend/app/Services/SolicitudReservaService.php
backend/app/Models/SolicitudReserva.php
backend/app/Models/SolicitudReservaDetalle.php
backend/app/Models/Reserva.php
backend/app/Models/Funcionario.php
backend/app/Models/AsignacionFuncionario.php
backend/app/Models/Auditoria.php
backend/tests/TestCase.php
```

Con eso te armo el patch exacto de **Fase 7.1 backend**, respetando OAuth Ibare, `RoleMiddleware` con pipe, restricción server-side de `funcionario_control` y sin tocar el flujo de cobro SIREB.
