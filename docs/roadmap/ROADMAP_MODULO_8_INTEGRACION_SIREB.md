# 📁 ROADMAP_MODULO_8_INTEGRACION_SIREB.md

**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.1.0 · **Formato:** Guía Arquitectónica Explicativa
**Última actualización:** 2026-10-01 (post-Fase 8.2)
**Tiempo estimado restante:** 12–16 horas · **Bloquea:** el pase a producción real

> **Nota de renumeración (v2.1.0):** la implementación real reveló que la
> Fase 8.3 del roadmap v2.0 (adaptación del service) era inseparable de la
> 8.2 y se implementaron juntas. La Fase 8.4 original (webhooks) no aplica:
> SIREB v1 no emite webhooks, solo admite polling vía consulta pública.

> **Estado actual:**
> - ✅ Fase 8.1 (Cliente OAuth2 + cache de token) — completada 2026-09-30
> - ✅ Fase 8.2 (Liquidaciones reales + anulación) — completada 2026-10-01
> - ⏳ Fases 8.3 a 8.7 — pendientes

> **Objetivo del Módulo:** reemplazar el `RecaudacionesApiClientSimulado`
> por llamadas reales al Gateway de Recaudaciones **SIREB** del GAD Beni.
> Al cerrar este módulo: una reserva iniciada desde la web pública genera
> una liquidación real en SIREB, el ciudadano paga por ventanilla con el
> código público, el encargado valida el pago en el panel de SIREB, el
> polling de Canchas detecta el pago, y el sistema confirma la reserva
> — todo sin intervención manual del equipo de Canchas.

---

## 📖 Contrato real de SIREB (confirmado en implementación)

> **Fuente:** documentación oficial en
> `https://test.sireb.beni.gob.bo/docs/api/consola#/` y verificado con
> smoke tests reales el 2026-10-01.

### Endpoints confirmados y su uso en Canchas

| Endpoint SIREB | Método | Para qué lo usa Canchas |
|---|---|---|
| `/api/v1/clientes` | `GET` (query `ci_nit`) | Ver si el ciudadano ya existe |
| `/api/v1/clientes` | `POST` | Registrar al ciudadano la primera vez |
| `/api/v1/clientes/{id}` | `PATCH` | **No usado:** evitar problemas de auditoría si hay familiar con mismo CI |
| `/api/v1/liquidaciones` | `POST` | Crear liquidación con `Idempotency-Key` |
| `/api/v1/liquidaciones/{codigoPublico}` | `GET` (sin token) | **Polling principal:** consulta pública de estado de pago |
| `/api/v1/liquidaciones/{liquidacionId}` | `GET` (con token) | Detalle completo para admin |
| `/api/v1/liquidaciones/{id}/anular` | `PATCH` | Anular liquidación pendiente |
| `/api/v1/liquidaciones/{id}/pago-manual` | `GET`/`POST` | **No usado:** es responsabilidad de SIREB |

### Autenticación (OAuth2 vía Ibare)

- **URL de token (test):** `https://test.ibare.beni.gob.bo/oauth/token`
- **URL de token (prod):** `https://ibare.beni.gob.bo/oauth/token`
- **Grant:** `client_credentials`
- **Formato:** `application/x-www-form-urlencoded`
- **Respuesta típica:**
  ```json
  { "token_type": "Bearer", "expires_in": 600, "access_token": "eyJ..." }
  ```
- **TTL confirmado en test:** 600s. Cache TTL efectivo: `expires_in - 60 = 540s`
- **No hay refresh_token:** al vencer, se pide otro con las mismas credenciales

### ⚠️ Dos clientes OAuth distintos (NO mezclar)

| Cliente | Propósito | Grant | Variables `.env` |
|---|---|---|---|
| `canchas-web-admin` | Login de funcionarios humanos (Módulo 0.9) | `authorization_code` | `IBARE_CLIENT_ID`, `IBARE_CLIENT_SECRET` |
| `sedede` | Llamadas server-to-server a SIREB (Módulo 8) | `client_credentials` | `SIREB_CLIENT_ID`, `SIREB_CLIENT_SECRET` |

Las variables del bloque `ibare` pertenecen al login humano y **no deben reutilizarse** para la integración con SIREB.

### SIREB v1: lo que NO tiene

| Feature | ¿Existe en SIREB v1? | Impacto en Canchas |
|---|---|---|
| **Webhooks** | ❌ No | No hay Fase 8.4 original; usamos solo polling |
| **Pago electrónico (QR, checkout)** | ❌ No | El ciudadano paga por ventanilla con `codigo_publico` |
| **Categorías en tarifas** | ❌ No (solo `etiqueta`) | Discriminador diurno/nocturno por substring en `etiqueta` |

### Reglas de anulación (HU-047)

- ✅ Solo anula si `estado === pendiente` **Y** `tiene_pago_registrado === false`
- ✅ Requiere campo `motivo` obligatorio
- ❌ Devuelve 422 `LIQUIDACION_NO_ANULABLE` si ya hay pago activo

---

## 🗺️ Mapa del Módulo (actualizado)

```
Módulo 8
├── Fase 8.1 → ✅ Cliente HTTP con OAuth2 y cache de token
├── Fase 8.2 → ✅ Liquidaciones reales + anulación + service adaptado
├── Fase 8.3 → ⏳ Confirmación de pago por polling + sincronización de tarifas
├── Fase 8.4 → ⏳ Frontend web pública (Pago.tsx con código público)
├── Fase 8.5 → ⏳ Frontend admin (detalle + botón anulación + link SIREB)
├── Fase 8.6 → ⏳ Smoke tests end-to-end con pago real
└── Fase 8.7 → ⏳ Deploy a producción
```

---

## ✅ FASE 8.1 — Credenciales, Configuración y Cliente HTTP con OAuth2

**Estado:** ✅ **Completada el 2026-09-30**
**Commit:** `feat(sireb): cliente HTTP con OAuth2 client_credentials via Ibare`

### Lo que se implementó

- Token JWT cacheado en Laravel Cache (driver `database`, migrable a Redis) con TTL dinámico (`expires_in - 60s`)
- Refresco automático ante `TOKEN_INVALIDO` (401)
- Retry en errores 5xx (3 intentos, 100ms entre sí)
- Logging en canal `sireb` (`storage/logs/sireb.log`)
- Métodos del cliente real: `buscarCliente`, `registrarCliente`, `listarCatalogo`, `crearLiquidacion`, `anularLiquidacion`, `consultarLiquidacionPorCodigo`, `consultarLiquidacionDetalle`
- Binding condicional por flag `RECAUDACIONES_SIMULADOR_HABILITADO`

### Smoke tests validados

- ✅ Token obtenido con `sedede` + `client_credentials`
- ✅ Cache TTL correcto (540s en test)
- ✅ `buscarCliente('9999999')` → cliente Milton Test
- ✅ `listarCatalogo()` → array (inicialmente vacío, luego con servicios)

---

## ✅ FASE 8.2 — Liquidaciones Reales + Anulación + Adaptación del Service

**Estado:** ✅ **Completada el 2026-10-01**
**Commit:** `feat(sireb): reservas conectadas a liquidaciones reales de SIREB`

### Infraestructura de datos

- **Migración 1:** `servicio_sireb_id` en `campos_deportivos`
- **Migración 2:** `liquidacion_id` y `motivo_rechazo` en `solicitudes_reserva`
- **Seeders:** `MapeoSirebSeeder` con mapeo campo local ↔ servicio SIREB
- **Comando artisan:** `sireb:sincronizar-mapeo` para gestión del mapeo

### Lógica de negocio

- **`CatalogoSirebService`:** cachea catálogo 10 min, resuelve `tarifa_id` por etiqueta (Diurno/Nocturno según `hora_inicio_noche` del campo)
- **`SolicitudReservaService::crear()` nuevo flujo:**
  1. Validar franjas (capa 1 aplicativa + capa 2 EXCLUDE)
  2. `buscarCliente(ci_nit)` → si no existe, `registrarCliente()`
  3. Para cada franja: resolver `tarifa_id` (Diurno/Nocturno)
  4. `crearLiquidacion()` con `Idempotency-Key: sedede:reserva:{solicitud.id}`
  5. **`monto_total` se sobrescribe con el monto autoritativo de SIREB**
  6. Guardar `liquidacion_id`, `referencia_recaudaciones = codigo_publico`
  7. Despachar `ExpirarSolicitudJob` y `PollingSolicitudJob`
  8. Si falla SIREB → despachar `ReintentarSolicitudJob` (5 intentos × 60s)

- **`ExpirarSolicitudJob` (definitivo):**
  - Antes de expirar local: intenta anular liquidación en SIREB
  - Si SIREB responde `LIQUIDACION_NO_ANULABLE` (pago activo) → **NO expira**, el polling confirmará
  - Otros errores: expira local igual y loguea

- **`ReintentarSolicitudJob`:** 5 reintentos × 60s; al agotarlos rechaza con motivo `error_cobro_inicial`

### Smoke tests validados (2026-10-01)

- ✅ Reserva `RES-20261001-W8FVB8` → liquidación `XG6G-4RHF-373` en SIREB
- ✅ `monto_total` sobrescrito a 50.00 (autoritativo SIREB)
- ✅ Idempotency-Key funciona (mismo key = misma liquidación)
- ✅ Job de expiración → estado local `expirada` + liquidación `anulada` en SIREB
- ✅ Regla `LIQUIDACION_NO_ANULABLE` → solicitud queda pendiente para polling

---

## ⏳ FASE 8.3 — Confirmación de Pago por Polling + Sincronización de Tarifas

**Estado:** ⏳ Pendiente
**Depende de:** Fase 8.2 ✅

### Por qué esta fase importa

La Fase 8.2 creó el camino **ida** (reserva → liquidación). Esta fase prueba el camino **vuelta** (pago en SIREB → confirmación en Canchas). Es el único camino porque SIREB v1 no emite webhooks.

### Tareas

```
[ ] Smoke test de confirmación por polling
    → Crear solicitud fresca (pendiente, con liquidacion_id)
    → En el panel Paitití: registrar y validar pago manual sobre la
      liquidación correspondiente
    → Ejecutar manualmente PollingSolicitudJob sobre la solicitud
    → Verificar que ConfirmacionCobroService::confirmar() dispara:
      - solicitud.estado = 'confirmada'
      - solicitud.monto_confirmado = monto de SIREB
      - se crean las filas en reservas
    → Registrar en auditoria
```

```
[ ] Ajustar ConfirmacionCobroService si hay discrepancia de monto
    → Hoy calcula monto_total × 1.0 (un solo ítem, una hora)
    → Cuando haya múltiples franjas, comparar monto_total vs
      monto_confirmado con tolerancia de redondeo
    → Si discrepancia > 0.01: loguear warning pero igual confirmar
      (SIREB es la fuente de verdad)
```

```
[ ] Comando sireb:sincronizar-tarifas
    → Nuevo artisan command que lee GET /catalogo/servicios y refresca
      la tabla tarifas_campo (espejo validado, no fuente de verdad)
    → Para cada campo con servicio_sireb_id mapeado:
      - buscar tarifa vigente correspondiente (por servicio)
      - actualizar precio_por_hora en tarifas_campo
    → Reportar discrepancias (ej. campo con 2 tarifas locales pero
      3 en SIREB)
```

```
[ ] Scheduler: sincronización diaria
    → En routes/console.php:
      Schedule::command('sireb:sincronizar-tarifas')->daily();
    → Loguear resultado en canal sireb
```

```
[ ] Botón manual de sincronización en admin
    → Endpoint POST /api/v1/admin/sireb/sincronizar-tarifas
    → Protegido por RoleMiddleware(['admin_parametricas'])
    → Toast de éxito con cantidad de campos actualizados
```

```
[ ] Worker de cola en producción
    → Documentar que se necesita correr:
      php artisan queue:work --tries=3
    → Para que PollingSolicitudJob, ExpirarSolicitudJob y
      ReintentarSolicitudJob se ejecuten de verdad
```

### Commit sugerido
```
feat(sireb): Fase 8.3 - confirmación por polling y sincronización de tarifas
```

---

## ⏳ FASE 8.4 — Frontend Web Pública (Pago.tsx con QR del código público)

**Estado:** ⏳ Pendiente
**Depende de:** Fase 8.3 ✅

### Decisión de UX

SIREB v1 no emite QR ni checkout reales, pero **mantenemos la UX del QR**
generándolo localmente con el `codigo_publico` como payload. El ciudadano
puede:

1. **Escanear el QR** → copia el código al portapapeles
2. **Mostrarlo en ventanilla** → el cajero lo lee y registra el pago
3. **Usar el código escrito** → aparece al lado del QR en grande

El `checkout_url` sigue siendo null (no existe en SIREB v1).

### Estructura de `datos_cobro_pendiente`

```json
{
  "codigo_publico": "XG6G-4RHF-373",
  "qr_string": "SIREB:XG6G-4RHF-373",
  "qr_image_base64": null,
  "monto": "50.00",
  "fecha_vencimiento": "2026-10-04T12:41:07+00:00",
  "items": [...]
}
```

El `qr_string` se construye localmente al crear la liquidación. El
frontend genera la imagen QR del string usando una librería como
`qrcode.react`.

### Tareas

```
[ ] Generar qr_string en SolicitudReservaService
    → Al guardar datos_cobro_pendiente, construir:
      "SIREB:{codigo_publico}"
    → El frontend lo convierte en imagen QR con librería cliente
```

```
[ ] Actualizar Pago.tsx
    → Renderizar QR con qr_string (usando qrcode.react o similar)
    → Mostrar codigo_publico en grande al lado del QR (con botón Copiar)
    → Mensaje claro: "Presentá este código o escaneá el QR en ventanilla
      del Banco Unión junto con tu CI para pagar tu reserva"
    → Mostrar el monto (de datos_cobro_pendiente.monto)
    → Mostrar expira_en (countdown)
    → NO mostrar checkout_url (no existe)
    → Link al panel de SIREB:
      https://test.sireb.beni.gob.bo/panel/liquidaciones/{codigo_publico}
      (para que el ciudadano pueda verificar él mismo el estado)
```

```
[ ] Mensajes específicos por motivo_rechazo
    → 'error_cobro_inicial' (SIREB caído al crear liquidación)
      → "No pudimos conectar con el sistema de pagos. Intentá nuevamente
        en unos minutos o contactá a soporte"
    → 'pago_rechazado_core' (banco rechazó pago)
      → "Tu pago no pudo procesarse. Verificá con tu banco e intentá
        nuevamente"
    → 'liquidacion_anulada_core' (SIREB anuló la liquidación)
      → "La liquidación fue anulada. Por favor generá una nueva reserva"
    → 'expirada_sin_pago' (timer venció)
      → "El tiempo para pagar expiró. Generá una nueva reserva"
```

```
[ ] Página /estado/{codigo_seguimiento}
    → Consultar SolicitudReservaService::consultar()
    → Mostrar estado actual + código de seguimiento
    → Si estado=pending: countdown + botón "Refrescar" + QR
    → Si estado=confirmada: link al comprobante
    → Si estado=expirada/rechazada: mensaje + botón "Nueva reserva"
```

```
[ ] Tests manuales / Playwright
    → Flujo completo desde web pública hasta comprobante
    → Verificar que el QR se renderiza correctamente
    → Verificar que Copiar funciona
    → Verificar que escanear el QR copia el código
    → Verificar que el link a SIREB abre la liquidación correcta
```

### Commit sugerido
```
feat(web-public): Pago.tsx con QR del codigo_publico de SIREB
```

---

## ⏳ FASE 8.5 — Frontend Admin (detalle + botón anulación + link SIREB)

**Estado:** ⏳ Pendiente
**Depende de:** Fase 8.3 ✅

### Tareas

```
[ ] Bloque "Integración con SIREB" en DialogDetalleReserva
    → Mostrar codigo_publico con botón Copiar
    → Link directo al panel de SIREB:
      https://test.sireb.beni.gob.bo/panel/liquidaciones/{codigo_publico}
    → Estado de la liquidación consultado en vivo (botón
      "Refrescar desde SIREB")
    → Mostrar liquidacion_id (UUID interno, solo debug)
    → Mostrar motivo_rechazo si está rechazada
```

```
[ ] Botón "Anular liquidación" (solo si estado=pending y sin pago)
    → Abre AlertDialog con textarea de motivo (mínimo 10 caracteres,
      obligatorio)
    → Al confirmar: POST /api/v1/admin/solicitudes-reserva/{id}/anular-liquidacion
    → Backend llama a anularLiquidacion(liquidacion_id, motivo)
    → Si SIREB responde LIQUIDACION_NO_ANULABLE: toast "No se puede
      anular: la liquidación ya tiene pago registrado"
    → Si éxito: toast "Liquidación anulada" + refrescar vista
    → Registra en auditoria: 'anular_liquidacion_manual' con
      funcionario, motivo y referencia SIREB
```

```
[ ] Endpoint admin para anular liquidación
    → POST /api/v1/admin/solicitudes-reserva/{id}/anular-liquidacion
    → Body: { motivo: string } (obligatorio, min 10 chars)
    → Protegido por RoleMiddleware(['admin_parametricas', 'admin_reservas'])
    → Validación: solicitud debe estar pendiente, tener liquidacion_id
```

```
[ ] Endpoint admin para refrescar estado desde SIREB
    → GET /api/v1/admin/solicitudes-reserva/{id}/refrescar-sireb
    → Llama a consultarLiquidacionDetalle(liquidacion_id) y devuelve el
      estado actual de SIREB
    → Útil para diagnóstico cuando el webhook/polling se demoran
```

```
[ ] Hook de eventos en ConfirmacionCobroService
    → Al final de confirmar(), disparar event(new
      SolicitudConfirmada($solicitud))
    → Crear el evento (app/Events/SolicitudConfirmada.php), sin
      listeners por ahora
    → Prepara el Módulo 10 (notificaciones email/SMS)
```

```
[ ] Tests de frontend admin
    → Ver bloque SIREB en detalle de reserva
    → Copiar código al portapapeles
    → Link abre liquidación correcta en SIREB
    → Botón Refrescar actualiza estado
    → Botón Anular abre dialog; al confirmar con motivo la liquidación
      queda anulada y la solicitud rechazada
    → Intentar anular con pago → SIREB 422 y UI muestra mensaje claro
```

### Commit sugerido
```
feat(web-admin): bloque SIREB, botón anulación y link al panel
```

---

## ⏳ FASE 8.6 — Smoke Tests de Integración End-to-End

**Estado:** ⏳ Pendiente
**Depende de:** Fases 8.3, 8.4, 8.5 ✅

### Entorno requerido

- Credenciales SIREB **test**
- URL pública de Canchas accesible desde internet (ngrok en dev)
- Usuario de prueba con CI/NIT válido en SIREB
- Worker de cola corriendo: `php artisan queue:work`

### Tareas

```
[ ] Smoke test del flujo feliz (el crítico)
    1. Crear reserva desde web pública con CI de prueba
    2. Verificar que en SIREB test aparece la liquidación con:
       - items correctos
       - codigo_publico visible
       - referencia_externa = nuestro codigo_seguimiento
       - canal_origen = api_sistema
    3. Pagar la liquidación desde el panel Paitití (registrar pago
       manual + validarlo)
    4. Verificar que el PollingSolicitudJob detecta el pago
    5. Verificar que la solicitud pasa a 'confirmada'
    6. Verificar que se crean las filas en reservas con los datos correctos
    7. Ver el comprobante con los códigos de reserva
```

```
[ ] Smoke test de idempotencia
    1. Crear una reserva
    2. Desde Postman, reenviar la misma solicitud 5 veces
    3. Verificar que en SIREB hay exactamente 1 liquidación
    4. Verificar que en reservas hay exactamente las filas esperadas
```

```
[ ] Smoke test de expiración con anulación
    1. Crear reserva y NO pagarla
    2. Esperar a que ExpirarSolicitudJob la marque expirada
    3. Verificar en SIREB que la liquidación quedó anulada
```

```
[ ] Smoke test de expiración con pago en proceso
    1. Crear reserva, registrar pago en SIREB (sin validar todavía)
    2. Dejar vencer el timer local
    3. Verificar que ExpirarSolicitudJob intenta anular, recibe 422
       LIQUIDACION_NO_ANULABLE, loguea warning y NO expira localmente
    4. Validar el pago en SIREB
    5. Verificar que el polling confirma la solicitud
```

```
[ ] Smoke test de anulación desde admin
    1. Crear reserva sin pagar
    2. Desde admin, anular con motivo "prueba"
    3. Verificar que en SIREB la liquidación queda anulada
    4. Verificar que la solicitud queda rechazada
    5. Verificar fila en auditoria con el motivo
```

```
[ ] Smoke test de caída de SIREB al crear
    1. Mockear SIREB con 5xx
    2. Crear reserva
    3. Verificar que ReintentarSolicitudJob reintenta 5 veces
    4. Al fallar las 5: solicitud Rechazada con motivo error_cobro_inicial
    5. Verificar que la UI muestra mensaje específico
```

```
[ ] Smoke test de referencia_externa bidireccional
    1. Crear reserva, capturar codigo_seguimiento
    2. Consultar desde panel SIREB con referencia_externa
    3. Verificar que aparece la liquidación correcta
    4. Desde admin, verificar que el link a SIREB abre esa liquidación
```

```
[ ] Smoke test de discrepancia de monto (caso raro)
    1. Forzar discrepancia entre monto_total y monto_confirmado
    2. Verificar que ConfirmacionCobroService loguea warning pero
      confirma igual
```

```
[ ] Documentar bugs encontrados
    → Todo lo que no coincida con el contrato va a un issue en el repo
    → No se parchea del lado de Canchas sin consultar con SIREB
```

### Commit sugerido
```
test(sireb): smoke tests end-to-end con pago real
```

---

## ⏳ FASE 8.7 — Documentación, Credenciales de Producción y Deploy

**Estado:** ⏳ Pendiente
**Depende de:** Fase 8.6 ✅

### Tareas

```
[ ] Documentar la integración
    → docs/integrations/SIREB.md con:
      - Credenciales necesarias y quién las pide
      - Endpoints consumidos y contratos
      - Flujo completo con diagrama de secuencia
      - Manejo de errores y reintentos
      - Troubleshooting: "si el polling no confirma, revisar worker",
        "si la anulación da 422, verificar pago", etc.
      - Matriz de códigos de error y cómo los manejamos
```

```
[ ] Actualizar README del backend
    → Sección: "Integración con SIREB (Recaudaciones GAD Beni)"
      con variables de entorno y flujo
```

```
[ ] Solicitar credenciales de producción
    → Pedir al equipo de Ibare:
      - client_id/client_secret para sedede en producción
      - URL del token de producción
    → Pedir al equipo de SIREB:
      - Verificar que el sistema sedede esté activo en producción
      - Confirmar catálogo de servicios cargado en producción
```

```
[ ] Configurar el panel de SIREB para producción
    → Verificar que el sistema sedede está en el listado
    → Verificar que el catálogo tiene los servicios con tarifario vigente
```

```
[ ] Ajustar parámetros de producción
    → SIREB_TOKEN_URL=https://ibare.beni.gob.bo/oauth/token
    → RECAUDACIONES_API_URL=https://sireb.beni.gob.bo
    → RECAUDACIONES_TIMEOUT_SEGUNDOS=15 (más holgado)
    → Verificar QUEUE_CONNECTION=database (o Redis) y workers
```

```
[ ] Configurar alertas de caídas
    → En canal sireb: alertar si >5 errores 5xx en 5 minutos
    → Alertar si el polling no confirma nada en 24h (puede ser que
      SIREB tenga un bug o la cola esté caída)
```

```
[ ] Plan de rollback
    → El flag RECAUDACIONES_SIMULADOR_HABILITADO=true permite volver al
      simulador en segundos sin redeployar
    → IMPORTANTE: volver al simulador con liquidaciones reales ya creadas
      en SIREB deja esas liquidaciones "huérfanas". Documentar
      procedimiento manual de conciliación.
```

```
[ ] Deploy
    → Merge rama sireb-integration a main
    → Deploy a staging → pruebas finales con credenciales test
    → Deploy a producción → monitoreo intenso primeras 48 horas
```

```
[ ] Comunicar al equipo del GAD
    → Nota formal al equipo SIREB/Ibare avisando que Canchas ya está
      conectado en producción y los volúmenes esperados
```

### Commit sugerido
```
chore: cierre Módulo 8 - integración real con SIREB
Tag: v1.0.0-sireb-integration
```

---

## 📝 Notas finales

### Matriz de estados de solicitud al cierre del Módulo 8

| Estado | Cómo se llega | Causa típica |
|---|---|---|
| `pendiente` | `crear()` exitoso, esperando pago | Ciudadano aún no pagó |
| `confirmada` | polling confirma pago | Pago OK en SIREB |
| `expirada` | `ExpirarSolicitudJob` vence el timer | Ciudadano no pagó a tiempo |
| `rechazada` | polling anulación/rechazo, o 5 reintentos fallidos | `motivo_rechazo` aclara cuál |

### Valores posibles de `motivo_rechazo`

| Valor | Origen | Significado |
|---|---|---|
| `error_cobro_inicial` | `ReintentarSolicitudJob` falla 5 veces | SIREB caído al crear liquidación |
| `liquidacion_anulada_core` | webhook/acción externa (futuro) | SIREB anuló la liquidación |
| `pago_rechazado_core` | webhook/acción externa (futuro) | El banco rechazó el pago |
| `expirada_sin_pago` | `ExpirarSolicitudJob` | Timer venció sin pago (anula en SIREB) |
| `anulada_por_operador` | admin desde dialog | Operador anuló manualmente con motivo |

### Migraciones del módulo

1. `2026_10_01_000001_add_servicio_sireb_id_to_campos_deportivos.php` ✅
2. `2026_10_01_000002_add_sireb_fields_to_solicitudes_reserva.php` ✅

### Relación con los módulos vecinos

- **Módulos 4 y 5:** los tests del simulador siguen corriendo con
  `RECAUDACIONES_SIMULADOR_HABILITADO=true`
- **Módulo 6 (dashboard gerencial):** los reportes de ingresos ahora
  tienen dato real de SIREB para cruzar
- **Módulo 7 (gestión operativa):** el listado de reservas gana el
  bloque SIREB en el detalle + botón de anulación
- **Módulo 9 (Épica G: seguridad):** ahora sí tiene sentido hacerlo

### Orden sugerido de implementación restante

1. **8.3** (confirmación por polling + sincronización tarifas) — **bloqueante**
2. **8.4 + 8.5** en paralelo (frontend público + admin)
3. **8.6** (smoke tests end-to-end con pago real)
4. **8.7** (deploy)

### Riesgos conocidos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| SIREB cambia el contrato sin avisar | Smoke tests en CI; si rompen, alertas |
| SIREB test es inestable | Flag `RECAUDACIONES_SIMULADOR_HABILITADO` para dev |
| Polling no confirma (cola caída) | Worker supervisado + alerta si 24h sin confirmar |
| Token expira antes del TTL | `tokenValido()` con margen 60s + reintento ante 401 |
| Ciudadano paga pero polling se demora | Reintento periódico hasta confirmar |
| Admin anula liquidación con pago | SIREB devuelve 422, UI muestra mensaje claro |
| SIREB caído al crear liquidación | `ReintentarSolicitudJob` reintenta 5 veces |
| Rollback con liquidaciones huérfanas | Documentar procedimiento manual de conciliación |

### Decisiones de diseño explícitas

1. **No webhooks, solo polling:** SIREB v1 no los emite. Aceptamos la
   latencia de ~20s entre pago y confirmación.
2. **No pago electrónico:** SIREB v1 no emite QR ni checkout. El
   ciudadano paga por ventanilla con `codigo_publico`.
3. **Sí exponemos anulación en admin** porque SIREB valida conciliación
   (HU-047). Con `AlertDialog` + motivo + auditoria.
4. **No exponemos pago manual** porque es responsabilidad de SIREB.
5. **`monto_total` se sobrescribe con SIREB:** es la fuente de verdad.
   El monto local calculado es solo una estimación.
6. **Discriminador diurno/nocturno por etiqueta:** SIREB v1 no usa
   categorías. Buscamos substring "Diurno" o "Nocturno" en la etiqueta.
7. **No agregamos un quinto estado a solicitudes_reserva:** usamos
   `motivo_rechazo` para discriminar.
8. **Postergamos email/SMS al Módulo 10:** el sistema funciona sin
   ellos.
9. **No parcheamos del lado de Canchas** los bugs de contrato: se
   reportan al equipo de SIREB.

---

> **Siguiente módulo:** Módulo 9 (Épica G) — rate limiting contra abuso
> en la app anónima, pruebas de concurrencia formales, y checklist de
> publicación en Google Play Store. Con SIREB ya integrado, ese módulo
> cierra el proyecto.
