# 📁 ROADMAP_MODULO_8_INTEGRACION_SIREB.md

**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 18–24 horas · **Bloquea:** el pase a producción real del sistema

> **Nota de renumeración:** este módulo se inserta entre el Módulo 7
> (gestión operativa de reservas) y el antiguo Módulo 7 (Épica G: seguridad
> y publicación). El antiguo Módulo 7 de seguridad y publicación **pasa a
> ser Módulo 9**. No tiene sentido desplegar a producción con el simulador
> de cobro todavía activo: primero hay que conectar con SIREB de verdad.

> **Objetivo del Módulo:** reemplazar el `RecaudacionesApiClientSimulado`
> (Módulos 4 y 5) por llamadas reales al Gateway de Recaudaciones **SIREB**
> del GAD Beni (el *Hub* del patrón Hub & Spoke documentado en ADR-003).
> Al cerrar este módulo: una reserva iniciada desde la web pública genera
> una liquidación real en SIREB, el ciudadano paga por QR bancario o
> ventanilla, SIREB nos avisa por webhook, y el sistema confirma la reserva
> — todo sin intervención manual.

> **Estado del arte (lo que ya existe):** la estructura para soportar la
> integración real **ya está construida**. El trabajo de este módulo es
> *llenar de contenido real* clases que hoy son stubs. Concretamente:
> - `RecaudacionesApiClientInterface.php` (interfaz ya definida con
>   `solicitarCobro`, `consultarEstado`, `verificarFirma`, `parsearWebhook`)
> - `RecaudacionesApiClientSimulado.php` (implementación fake que usamos
>   en desarrollo, queda como fallback para testing)
> - `RecaudacionesApiClient.php` (clase real — hoy probablemente con
>   `TODO` o lógica mínima)
> - DTOs: `SolicitudCobroDTO`, `RespuestaCobroDTO`, `EstadoCobroDTO`,
>   `SolicitudCreadaDTO`, `WebhookPayloadDTO`
> - `SolicitudReservaService.php` y `ConfirmacionCobroService.php` ya
>   consumen la interfaz — no cambian su firma, solo cambia qué
>   implementación se inyecta
> - `WebhookRecaudacionesController.php`, `PollingSolicitudJob.php` y
>   `ExpirarSolicitudJob.php` ya existen con tests
>
> En resumen: **este módulo es más "conectar y configurar" que "diseñar"**.
> El riesgo no está en la arquitectura sino en la negociación del contrato
> real con el equipo de SIREB/Ibare.

---

## 📖 Contrato real de SIREB (confirmado en consola de pruebas)

> **Fuente:** documentación oficial disponible en
> `https://test.sireb.beni.gob.bo/docs`. Lo que sigue es lo que ya sabemos
> con certeza; lo que falta está marcado en la sección siguiente.

### Endpoints confirmados y su uso en Canchas

| Endpoint SIREB | Método | Para qué lo usa Canchas |
|---|---|---|
| `/api/v1/clientes` | `GET` (query `ci_nit`) | Ver si el ciudadano ya existe antes de crear liquidación |
| `/api/v1/clientes` | `POST` | Registrar al ciudadano la primera vez que reserva |
| `/api/v1/clientes/{clienteId}` | `PATCH` | Actualizar datos si cambian (opcional, ver nota abajo) |
| `/api/v1/liquidaciones` | `POST` | Crear la liquidación de la reserva (con Idempotency-Key) |
| `/api/v1/liquidaciones/{codigoPublico}` | `GET` | Consultar estado de pago (polling de respaldo) |
| `/api/v1/liquidaciones/{liquidacionId}` | `GET` | Detalle completo para admin (solo dependencias propias) |
| `/api/v1/liquidaciones/{liquidacionId}` | `PATCH /anular` | Anular liquidación pendiente sin pago activo |
| `/api/v1/liquidaciones/{liquidacionId}/pago-manual` | `GET` / `POST` | Fuera de alcance (es responsabilidad de SIREB) |

### Estructura de respuesta de una liquidación (confirmada)

```json
{
  "data": {
    "id": "uuid",
    "codigo_publico": "7ZQK-P4M2-9AX",
    "monto": "120.00",
    "estado": "pendiente | pagada | anulada | vencida",
    "canal_origen": "api_sistema",
    "referencia_externa": "nuestro codigo_seguimiento",
    "sucursal_id": "uuid-sucursal-sedede",
    "fecha_emision": "ISO-8601",
    "fecha_vencimiento": "ISO-8601",
    "cliente": {
      "id": "uuid",
      "ci_nit": "string",
      "nombre_completo": "string",
      "telefono": "string",
      "email": "string"
    },
    "items": [
      {
        "id": "uuid",
        "servicio": { "id": "uuid", "codigo": "string", "nombre": "string" },
        "tarifa_id": "uuid",
        "descripcion": "string",
        "cantidad": "string",
        "unidad_medida": "string",
        "precio_unitario": "string",
        "subtotal": "string"
      }
    ],
    "tiene_pago_registrado": true,
    "fecha_pago": "ISO-8601 | null",
    "pago": {
      "id": "uuid",
      "tipo_pago": "manual | qr | transferencia",
      "monto_pagado": "80.00",
      "estado": "pendiente | validado | rechazado",
      "numero_boleta": "string",
      "entidad_bancaria": "string",
      "fecha_validacion": "ISO-8601 | null"
    }
  }
}
```

### Códigos de error tipados

| HTTP | Código | Significado | Cómo lo manejamos |
|---|---|---|---|
| 401 | `TOKEN_INVALIDO` | Token ausente, expirado o con firma inválida | Refrescar token y reintentar una vez; si persiste, error |
| 403 | `FORBIDDEN_DEPENDENCIA` | Operación sobre liquidación de otra dependencia | Error de programación (bug nuestro); alertar |
| 404 | `FORBIDDEN_DEPENDENCIA` | Liquidación inexistente (o de otra dependencia — SIREB no distingue) | Tratar como "no encontrada", nunca reintenta |
| 422 | `LIQUIDACION_NO_ANULABLE` | Intento de anular con pago activo o ya vencida | Mostrar al admin: "No se puede anular: ya tiene pago" |

### Reglas de anulación (HU-047, implementada 2026-09-25)

- ✅ Solo anula si `estado === pendiente` **Y** `tiene_pago_registrado === false`
- ✅ Requiere campo `motivo` obligatorio (string)
- ✅ Valida que la liquidación sea de la misma dependencia
- ❌ Devuelve 422 `LIQUIDACION_NO_ANULABLE` si hay pago activo
- 📝 **Decisión de diseño:** exponemos anulación en el admin con `AlertDialog`
  que pida el motivo al operador, y registramos la acción en `auditoria`.

---

## ❓ Dependencias pendientes del contrato (a obtener antes de la Fase 8.2)

| Dato faltante | Impacto | Acción requerida |
|---|---|---|
| Body exacto de `POST /liquidaciones` | Bloquea Fase 8.2 (no podemos crear liquidaciones) | Pedir al equipo SIREB o inspeccionar en consola |
| Body exacto de `POST /clientes` | Bloquea Fase 8.2 | Pedir o inspeccionar |
| Mecanismo de firma del webhook (header, algoritmo) | Bloquea Fase 8.4 | Pedir al equipo SIREB |
| Catálogo de eventos del webhook (qué eventos manda) | Bloquea Fase 8.4 | Pedir al equipo SIREB |
| URL exacta del endpoint de token OAuth2 (Ibare) | Bloquea Fase 8.1 | Pedir al equipo Ibare |
| Catálogo de servicios (servicio_id para "alquiler cancha diurna" vs "con iluminación") | Bloquea Fase 8.2 | Pedir al equipo SIREB |
| `sucursal_id` del SEDEDE en SIREB | Bloquea Fase 8.3 | Pedir o consultar en panel |

**Regla:** no arrancar ninguna fase hasta tener las dependencias de esa
fase resueltas. El orden del roadmap ya está pensado para que se puedan
pedir todas las dependencias juntas al inicio y trabajar en paralelo.

---

## 🗺️ Mapa del Módulo

```
Módulo 8
├── Fase 8.1 → Credenciales, configuración y cliente HTTP con OAuth2
├── Fase 8.2 → Endpoints de Clientes y Liquidaciones en SIREB (con anulación)
├── Fase 8.3 → Adaptación del service de reserva al contrato real
├── Fase 8.4 → Webhook real + polling de respaldo + manejo de caídas
├── Fase 8.5 → Frontend: flujo UX real, dashboard con estado SIREB y anulación
├── Fase 8.6 → Pruebas de integración con entorno de prueba de SIREB
└── Fase 8.7 → Documentación, credenciales de producción y deploy
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 8.1 — Credenciales, Configuración y Cliente HTTP con OAuth2

## Por qué empezar por acá

Sin un token OAuth2 válido no se puede llamar a ningún endpoint de SIREB.
Esta fase es la base obligatoria de todas las demás. El flujo es el
estándar OAuth2 `client_credentials`: Canchas pide un token a Ibare
(presentando `client_id` + `client_secret`), Ibare devuelve un token con
TTL, Canchas lo cachea y lo reutiliza hasta que expire, momento en el cual
pide uno nuevo automáticamente.

## Una nota sobre el TTL del token

No todos los providers OAuth2 devuelven exactamente 3600 segundos. Algunos
devuelven 1800, otros 7200. El cliente **nunca** debe hardcodear la
duración: debe confiar en el campo `expires_in` de la respuesta y restarle
un margen de seguridad (ej. 60 segundos) para evitar usar un token en el
último segundo de vida.

## Dependencia crítica

Antes de arrancar esta fase, necesitamos que el equipo de Ibare nos
confirme la URL exacta del endpoint de emisión de tokens (probablemente
`https://ibare.beni.gob.bo/oauth/token` o similar). Sin eso, no se puede
implementar `obtenerToken()`.

---

## Tareas de la Fase 8.1

```
[ ] Variables de entorno
    → backend/.env:
        RECAUDACIONES_API_URL=https://test.sireb.beni.gob.bo
        RECAUDACIONES_API_CLIENT_ID=sedede
        RECAUDACIONES_API_CLIENT_SECRET=<secreto-real>
        RECAUDACIONES_API_TOKEN_URL=<url-confirmada-de-ibare>
        RECAUDACIONES_API_SUCURSAL_ID=<uuid-sucursal-sedede>
        RECAUDACIONES_WEBHOOK_SECRET=<secreto-para-verificar-firmas>
        RECAUDACIONES_TIMEOUT_SEGUNDOS=10
    → backend/.env.example: agregar las mismas variables con
      placeholders para que el siguiente dev sepa qué configurar.

[ ] Configurar el bloque en config/services.php
    → Agregar entrada 'recaudaciones' con todas las variables.

[ ] Implementar obtenerToken() en RecaudacionesApiClient
    → POST a RECAUDACIONES_API_TOKEN_URL con grant_type=client_credentials,
      client_id, client_secret (form-encoded, estándar OAuth2).
    → Cachear el token en Redis (ya disponible desde el Módulo 0) con
      clave 'sireb:access_token' y TTL = expires_in - 60.
    → Método auxiliar tokenValido() que revisa si hay token en cache y
      no está a punto de expirar.
    → Refrescar automáticamente: si tokenValido() devuelve false,
      llamar a obtenerToken() de nuevo. Ningún otro método del cliente
      debe preocuparse por esto.

[ ] Implementar el cliente HTTP base
    → Usar el HTTP client de Laravel (Http::withToken(...)) con:
      - base_uri = RECAUDACIONES_API_URL
      - headers estándar: Accept: application/json, Content-Type:
        application/json
      - timeout configurable (RECAUDACIONES_TIMEOUT_SEGUNDOS)
      - retry(3, 100) solo para errores 5xx transitorios (no para 4xx,
        esos son errores de contrato y no se reintentan).

[ ] Manejo específico del error TOKEN_INVALIDO (401)
    → Si una llamada devuelve 401 con codigo='TOKEN_INVALIDO', invalidar
      el token cacheado y reintentar UNA vez con token nuevo. Si vuelve
      a fallar, propagar excepción (no reintentar en loop infinito).

[ ] Logging específico de SIREB
    → Configurar un canal 'sireb' en config/logging.php que escriba en
      storage/logs/sireb.log con formato que incluya: timestamp, método
      HTTP, URL, status code, tiempo de respuesta, referencia externa.
    → Loguear TODAS las llamadas (éxito y error) a nivel info; los
      errores a nivel error. Nunca loguear el client_secret ni el
      webhook_secret; el token sí se puede loguear truncado (primeros
      8 chars + "...") para debugging.

[ ] Tests unitarios del manejo de token
    → obtenerToken() cachea el token en Redis con el TTL correcto.
    → Segunda llamada dentro del TTL no llama de nuevo a Ibare.
    → Llamada con token a 30 segundos de expirar sí pide uno nuevo.
    → Si Ibare responde 5xx al pedir token, se propaga excepción.
    → Si Ibare responde 4xx (client_id malo), se propaga excepción.
    → Si una llamada protegida devuelve TOKEN_INVALIDO, se refresca el
      token y se reintenta una vez.

[ ] Commit de la fase
    → Mensaje: "feat(sireb): cliente HTTP con OAuth2 client_credentials y cache de token"
```

---

# FASE 8.2 — Endpoints de Clientes y Liquidaciones en SIREB (con anulación)

## Cambio clave respecto al roadmap anterior

El roadmap anterior excluía la anulación de liquidaciones por miedo a
problemas de conciliación. El contrato real de SIREB (HU-047, implementada
el 2026-09-25) **ya hace la validación de conciliación por nosotros**:
solo permite anular si la liquidación está pendiente y no tiene pago
activo. Esto hace seguro exponer la anulación en nuestro admin con
`AlertDialog` pidiendo motivo.

## Los endpoints que realmente usamos

| Endpoint SIREB | Uso en Canchas |
|---|---|
| `GET /api/v1/clientes?ci_nit=X` | Ver si el ciudadano ya existe antes de crear liquidación |
| `POST /api/v1/clientes` | Registrar al ciudadano la primera vez que reserva |
| `POST /api/v1/liquidaciones` | Crear la liquidación de la reserva (con Idempotency-Key) |
| `GET /api/v1/liquidaciones/{codigoPublico}` | Consultar estado de pago (polling de respaldo) |
| `PATCH /api/v1/liquidaciones/{id}/anular` | **NUEVO:** anular desde el admin con motivo |

El endpoint `POST /liquidaciones/{id}/pago-manual` queda **fuera del
alcance** de este módulo: es responsabilidad de SIREB registrar pagos
manuales, no de Canchas.

## La clave de la idempotencia: `Idempotency-Key`

SIREB exige un `Idempotency-Key` al crear liquidaciones. Si Canchas pierde
conectividad en medio de un `POST /liquidaciones` y reintenta, SIREB debe
devolver la **misma** liquidación creada antes, no una nueva. El key se
genera así:

```
idempotency_key = "sedede:reserva:{solicitud_reserva.id}"
```

Con eso, si el mismo solicitud_reserva_id se reintenta 10 veces, SIREB
responde con la misma liquidación las 10 veces. **Nunca** usar UUID
aleatorio: perderíamos la capacidad de reconectar.

## Mapeo bidireccional de referencias

SIREB acepta un campo `referencia_externa` en la liquidación. Lo usamos
para guardar nuestro `codigo_seguimiento`, de modo que:

- **Canchas → SIREB:** al crear la liquidación, mandamos nuestro
  `codigo_seguimiento` como `referencia_externa`.
- **SIREB → Canchas:** en el webhook y en el polling, recibimos esa
  referencia y la usamos para encontrar la solicitud.
- **Admin → SIREB:** el link al panel de SIREB se construye como
  `https://sireb.beni.gob.bo/panel/liquidaciones/{codigo_publico}`.

---

## Tareas de la Fase 8.2

```
[ ] Implementar buscarCliente($ciNit)
    → GET /api/v1/clientes?ci_nit={ciNit}
    → Devuelve ?ClienteSirebDTO (id, ci_nit, nombre, telefono, email).
    → Si SIREB responde 404, devolver null (no es un error).
    → Si SIREB responde 5xx, propagar RecaudacionesApiException.
    → Body: pendiente de confirmar con SIREB.

[ ] Implementar registrarCliente($datos)
    → POST /api/v1/clientes con DTO ClienteSirebDTO.
    → Devuelve ClienteSirebDTO con id asignado por SIREB.
    → Manejar 422 (validación) como excepción de dominio con el detalle
      de qué campo falló.
    → Body: pendiente de confirmar con SIREB.

[ ] Implementar crearLiquidacion($items, $clienteId, $idempotencyKey, $referenciaExterna)
    → POST /api/v1/liquidaciones con header Idempotency-Key.
    → Body: { cliente_id, sucursal_id (de config), items: [...],
      referencia_externa, metadata }.
    → Items = uno por cada franja × tarifa, con servicio_id del
      catálogo de SIREB (pendiente de obtener).
    → Devuelve RespuestaCobroDTO con codigo_publico, qr_string,
      qr_image_base64, checkout_url, monto_total, fecha_vencimiento.
    → Body exacto: pendiente de confirmar con SIREB.

[ ] Implementar consultarLiquidacionPorCodigoPublico($codigoPublico)
    → GET /api/v1/liquidaciones/{codigoPublico}
    → Devuelve EstadoCobroDTO con: pagado (bool), montoConfirmado,
      pagado_en, referenciaExterna, estado, tiene_pago_registrado.

[ ] Implementar consultarLiquidacionDetalle($liquidacionId)
    → GET /api/v1/liquidaciones/{liquidacionId}
    → Devuelve la liquidación completa (para el admin, con items, pago,
      cliente). Útil para el detalle en el admin (Fase 8.5).
    → Solo funciona para liquidaciones de la misma dependencia; si
      devuelve 403 FORBIDDEN_DEPENDENCIA, tratar como no encontrada.

[ ] Implementar anularLiquidacion($liquidacionId, $motivo)
    → PATCH /api/v1/liquidaciones/{liquidacionId}/anular
    → Body: { motivo: string } (obligatorio).
    → Devuelve la liquidación con estado='anulada'.
    → Si devuelve 422 LIQUIDACION_NO_ANULABLE, propagar excepción con
      mensaje legible para mostrar al admin.

[ ] Implementar verificarFirma($request) y parsearWebhook($payload)
    → verificarFirma: mecanismo pendiente de confirmar con SIREB
      (probablemente HMAC-SHA256 del body crudo con
      RECAUDACIONES_WEBHOOK_SECRET, comparado contra un header).
    → parsearWebhook: devuelve WebhookPayloadDTO con tipoEvento,
      referenciaExterna, montoConfirmado, pagado_en, payload crudo.
    → Rechazar 401 si la firma no coincide; nunca procesar el payload.

[ ] Tests unitarios del cliente
    → Mockear Http::fake() para cada endpoint.
    → Idempotencia: llamar dos veces con mismo idempotency_key y
      verificar que el header se envía igual.
    → Errores 4xx convierten a RecaudacionesApiException con el
      `codigo` del error preservado (TOKEN_INVALIDO,
      FORBIDDEN_DEPENDENCIA, LIQUIDACION_NO_ANULABLE).
    → Errores 5xx propagan después de los 3 reintentos.
    → verificarFirma() acepta firma válida y rechaza firma inválida.
    → anularLiquidacion() con motivo vacío: el service lo rechaza antes
      de llamar a SIREB.

[ ] Commit de la fase
    → Mensaje: "feat(sireb): endpoints de clientes, liquidaciones, anulación y verificación de webhook"
```

---

# FASE 8.3 — Adaptación del Service de Reserva al Contrato Real

## Lo que cambia (y lo que NO cambia)

La interfaz `RecaudacionesApiClientInterface` fue diseñada en el Módulo 4
pensando en este momento. El `SolicitudReservaService` consume la
interfaz, no la implementación. Entonces:

- **NO cambia** la firma de `crear()`, `ConfirmacionCobroService`, los
  controllers ni los DTOs de salida hacia el frontend.
- **SÍ cambia** el binding en `AppServiceProvider`: de
  `RecaudacionesApiClientSimulado` a `RecaudacionesApiClient` real.

## El nuevo flujo en `crear()` (reemplazo del actual)

```
1. validarFranjasDisponibles()                (sin cambios)
2. calcularMontoTotal()                       (sin cambios)
3. CREAR SolicitudReserva en estado 'pendiente'
4. buscarCliente(ci_nit_pagador)              ← NUEVO
   → si existe: usar su id
   → si no: registrarCliente() y guardar el id en la solicitud
5. crearLiquidacion(items, clienteId, idempotencyKey, codigo_seguimiento)  ← NUEVO
   → items = uno por cada franja × tarifa, con servicio_id del catálogo
   → idempotencyKey = "sedede:reserva:{solicitud.id}"
   → referencia_externa = codigo_seguimiento (para mapeo bidireccional)
6. actualizar solicitud con:
     - referencia_recaudaciones = codigo_publico de SIREB
     - datos_cobro_pendiente = { qr_string, qr_image_base64,
       checkout_url } (JSON)
     - expira_en = fecha_vencimiento devuelto por SIREB
     - liquidacion_id = id devuelto por SIREB (para anulación futura)
7. despachar ExpirarSolicitudJob y PollingSolicitudJob  (sin cambios)
8. devolver SolicitudCreadaDTO al frontend    (sin cambios)
```

Los pasos 1, 2, 3, 7, 8 ya están escritos. El trabajo de esta fase es
**agregar 4, 5 y 6** reemplazando la llamada actual al simulador.

## El caso del cliente que ya existe con datos distintos

Si `buscarCliente(ci)` devuelve un cliente cuyo nombre/telefono no
coincide con el que está creando la solicitud, **NO** lo actualizamos. La
razón: puede ser un familiar que usa el mismo CI o un error de tipeo del
ciudadano. Actualizarlo sin confirmar crearía problemas de auditoria en
SIREB. Se registra una warning en el log y se usa el cliente tal como
está en SIREB. Si más adelante el GAD quiere que actualicemos, se agrega
una flag en config y se hace solo con esa flag activa.

## El caso de SIREB caído al crear la liquidación

Si `crearLiquidacion()` lanza excepción (5xx, timeout, red), la solicitud
no queda colgada: el catch en el service la deja en estado `'pendiente'`
pero con `referencia_recaudaciones = null`. Un **job de reintento** (nuevo)
intenta crear la liquidación cada 60 segundos por hasta 5 minutos. Si
después de 5 minutos sigue sin poder, la solicitud se marca
`'rechazada'` con motivo `'error_cobro_inicial'` y se notifica al
ciudadano (ver Fase 8.5).

---

## Tareas de la Fase 8.3

```
[ ] Migración: agregar motivo_rechazo y liquidacion_id a solicitudes_reserva
    → 2026_XX_XX_add_sireb_fields_to_solicitudes_reserva_table.php
    → Columnas:
      - string motivo_rechazo nullable (para 'expirada_sin_pago',
        'error_cobro_inicial', 'liquidacion_anulada_core',
        'pago_rechazado_core')
      - uuid liquidacion_id nullable (el id de SIREB, para anulación)
      - string codigo_publico_sireb nullable (el codigo_publico de SIREB)

[ ] Actualizar modelo SolicitudReserva
    → app/Models/SolicitudReserva.php — agregar los tres campos al
      $fillable.

[ ] Crear ReintentarSolicitudJob
    → app/Jobs/ReintentarSolicitudJob.php
    → Despachado cuando crearLiquidacion() falla.
    → Reintenta hasta 5 veces con delay de 60s entre intentos.
    → Si los 5 fallan, marca la solicitud como Rechazada con
      motivo='error_cobro_inicial' y registra en auditoria.

[ ] Adaptar SolicitudReservaService::crear()
    → Reemplazar la llamada al simulador por la secuencia real:
      buscarCliente → (registrar si no existe) → crearLiquidacion →
      actualizar campos → despachar jobs.
    → Envolver la llamada a SIREB en try/catch; en caso de error,
      despachar ReintentarSolicitudJob en lugar de fallar la request.
    → Guardar liquidacion_id y codigo_publico_sireb para poder anular
      después desde el admin.

[ ] Adaptar ExpirarSolicitudJob para anular en SIREB
    → Cuando una solicitud expira por timer, ANTES de marcarla
      'expirada' intentamos anular la liquidación en SIREB llamando a
      anularLiquidacion(liquidacion_id, 'solicitud_expirada_sin_pago').
    → Si SIREB responde 422 LIQUIDACION_NO_ANULABLE (ya tiene pago),
      loguear warning y NO expirar la solicitud — dejarla en estado
      que se resolverá cuando llegue el webhook.
    → Si SIREB responde 5xx, loguear error pero igual expirar la
      solicitud localmente (el polling de SIREB se encargará).

[ ] Actualizar AppServiceProvider
    → Cambiar el binding:
      $this->app->bind(
        RecaudacionesApiClientInterface::class,
        RecaudacionesApiClient::class  // antes: Simulado
      );
    → Agregar flag 'recaudaciones.simulador_habilitado' en
      config/services.php para poder volver al simulador en testing
      sin tocar código.

[ ] Tests del flujo adaptado
    → Con cliente simulado (flag activo): los tests del Módulo 4/5
      siguen pasando sin cambios.
    → Con cliente real (Http::fake()): crear solicitud genera
      llamada a /clientes y /liquidaciones, guarda referencia y
      despacha los 3 jobs.
    → Si crearLiquidacion falla 5 veces: solicitud queda Rechazada con
      motivo='error_cobro_inicial'.
    → Cliente ya existente con datos distintos: no lo actualiza, solo
      loguea warning.
    → ExpirarSolicitudJob sobre solicitud con liquidacion_id llama a
      anularLiquidacion con motivo 'solicitud_expirada_sin_pago'.
    → ExpirarSolicitudJob cuando SIREB responde LIQUIDACION_NO_ANULABLE:
      loguea y no expira.

[ ] Commit de la fase
    → Mensaje: "feat(sireb): SolicitudReservaService conectado al cliente real de SIREB"
```

---

# FASE 8.4 — Webhook Real + Polling de Respaldo + Manejo de Caídas

## Qué cambia respecto al Módulo 5

En el Módulo 5 implementamos el webhook y el polling **contra el
simulador**. La lógica del `ConfirmacionCobroService::confirmar()` ya
está probada (idempotencia, confirmación tardía, discrepancia de monto).
El trabajo de esta fase es:

1. Asegurar que `verificarFirma()` valida correctamente la firma de SIREB
   real (el simulador usaba un header de prueba).
2. Ajustar el formato del payload parseado al contrato real de SIREB.
3. Configurar la URL pública del webhook en el panel de SIREB.

## El catálogo de eventos del webhook de SIREB

> **PENDIENTE:** pedir al equipo de SIREB el listado oficial de eventos
> que emite. Mientras tanto, asumimos un conjunto típico:

- `liquidacion.pagada` → dispara `ConfirmacionCobroService::confirmar()`
- `liquidacion.anulada` → marca la solicitud como `Rechazada` con
  motivo `liquidacion_anulada_core` (si estaba pendiente)
- `liquidacion.vencida` → marca como `Expirada` (refuerzo del
  `ExpirarSolicitudJob`, por si el webhook llega antes)
- `pago.rechazado` → marca como `Rechazada` con motivo
  `pago_rechazado_core`

Cualquier otro evento se loguea a nivel info y se ignora. **Nunca** se
procesa un evento desconocido como si fuera un pago.

## Manejo defensivo del webhook

- Si el webhook trae una `referencia_externa` que no matchea ninguna
  solicitud local: loguear advertencia y responder 200 igual (para que
  SIREB no reintente indefinidamente).
- Si la solicitud ya está en estado terminal (confirmada/expirada/rechazada):
  aplicar la regla de **confirmación tardía** del Módulo 5 (no revivir,
  registrar en auditoria).

---

## Tareas de la Fase 8.4

```
[ ] Ajustar verificarFirma() al mecanismo real de SIREB
    → Consultar con el equipo de SIREB:
      - ¿Qué header lleva la firma? (X-Sireb-Signature, X-Signature, ...)
      - ¿Qué algoritmo? (HMAC-SHA256 es el estándar)
      - ¿Qué se firma? (body crudo, o body + timestamp)
    → Implementar y documentar en el código.

[ ] Ajustar parsearWebhook() al payload real
    → Mapear los campos reales del payload al WebhookPayloadDTO.
    → Agregar tipoEvento como string crudo + enum TipoWebhookEvento con
      los eventos conocidos.

[ ] Manejar el evento 'liquidacion.anulada'
    → Si la solicitud está 'pendiente', marcarla 'rechazada' con
      motivo='liquidacion_anulada_core' y registrar en auditoria.
    → Si ya está confirmada: loguear warning (no se puede des-confirmar
      una reserva confirmada — problema de conciliación que se trata
      por fuera).

[ ] Manejar el evento 'pago.rechazado'
    → Si la solicitud está 'pendiente', marcarla 'rechazada' con
      motivo='pago_rechazado_core' y registrar en auditoria.

[ ] Exponer endpoint de health del webhook
    → GET /api/v1/webhooks/recaudaciones/health que devuelve 200 con
      { ok: true, version: "1.0" } — sirve para que SIREB valide que
      la URL está viva antes de enviarnos eventos.

[ ] Configurar URL del webhook en SIREB
    → Acción manual (fuera del código):
      - Entorno test: apuntar a https://test.canchas.beni.gob.bo/api/v1/webhooks/recaudaciones
        (o ngrok en dev local).
      - Entorno prod: URL pública definitiva.
    → Verificar con SIREB que mandan el evento de prueba.

[ ] Tests del webhook con contrato real
    → Con payload firmado válido: dispara confirmar().
    → Con firma inválida: responde 401, no crea nada.
    → Con evento 'liquidacion.anulada' sobre solicitud pendiente:
      marca rechazada con motivo='liquidacion_anulada_core'.
    → Con evento 'liquidacion.anulada' sobre solicitud ya confirmada:
      loguea warning, no cambia estado.
    → Con evento 'pago.rechazado' sobre solicitud pendiente:
      marca rechazada con motivo='pago_rechazado_core'.
    → Con evento desconocido: responde 200, no crea nada, loguea.
    → Con referencia_externa inexistente: responde 200, loguea advertencia.
    → Endpoint /health responde 200.

[ ] Commit de la fase
    → Mensaje: "feat(sireb): webhook adaptado al contrato real con manejo de eventos"
```

---

# FASE 8.5 — Frontend: Flujo UX Real, Dashboard con Estado SIREB y Anulación

## Qué cambia en la web pública (muy poco)

El flujo ya está diseñado en el Módulo 5 para ser agnóstico del proveedor.
Los cambios concretos son:

- **Pago.tsx:** el QR que muestra viene de SIREB (campo `qr_string` o
  `qr_image_base64` del DTO). Si SIREB manda `checkout_url` además del
  QR, mostrar un botón "Pagar en la web de SIREB" como opción
  alternativa.
- **Pago.tsx (expiración/rechazo):** si el motivo_rechazo es
  `'error_cobro_inicial'` (SIREB estuvo caído al crear la liquidación),
  mostrar mensaje específico ("No pudimos conectar con el sistema de
  pagos, intentá de nuevo en unos minutos") en vez del mensaje genérico.
- **Pago.tsx (rechazo por Core):** si motivo_rechazo es
  `'pago_rechazado_core'`, mostrar mensaje específico ("Tu pago no pudo
  procesarse. Verificá con tu banco e intentá nuevamente").
- **Comprobante.tsx:** sin cambios; ya muestra lo que debe mostrar.

## Qué cambia en el admin (Módulo 7)

En el dialog de detalle de reserva (Módulo 7 Fase 7.4), agregar un bloque
"**Integración con SIREB**" que muestre:

- `codigo_publico_sireb` (el código legible tipo "7ZQK-P4M2-9AX") con
  botón "Copiar" y link directo al panel de SIREB:
  `https://sireb.beni.gob.bo/panel/liquidaciones/{codigo_publico}`
- `liquidacion_id` (UUID interno, solo para debug)
- Estado de la liquidación consultado en vivo (botón "Refrescar desde
  SIREB") para los casos en que webhook y polling no hayan llegado aún.
- Si la liquidación está en estado `pendiente` y sin pago registrado:
  botón **"Anular liquidación"** que abre un `AlertDialog` pidiendo el
  motivo (textarea obligatoria).
- Motivo de rechazo si la solicitud fue rechazada.

## Notificaciones al ciudadano — alcance acotado

El roadmap original mencionaba un `NotificacionService` para email/SMS.
**Esto se posterga a un módulo futuro (Módulo 10) por dos razones:**

1. El sistema actual funciona sin ellas: el ciudadano ve el estado en la
   pantalla `/estado` y recibe el comprobante al confirmar.
2. Agregar email/SMS implica elegir provider (SES, SendGrid, Twilio),
   manejar templates, bounces, unsubscribes — un alcance que merece su
   propio roadmap.

En este módulo solo se deja **preparado el hook**: al final de
`ConfirmacionCobroService::confirmar()`, emitir un evento Laravel
`SolicitudConfirmada` que más adelante (Módulo 10) disparará los
listeners de email/SMS. Por ahora no hay listeners registrados.

---

## Tareas de la Fase 8.5

```
[ ] Actualizar Pago.tsx (web-public)
    → Si hay checkout_url además de QR, mostrar botón secundario
      "Pagar en la web de SIREB".
    → Mensajes específicos según motivo_rechazo:
      - 'error_cobro_inicial' → "No pudimos conectar con el sistema de pagos"
      - 'pago_rechazado_core' → "Tu pago no pudo procesarse"
      - 'liquidacion_anulada_core' → "La liquidación fue anulada"

[ ] Agregar bloque "Integración con SIREB" en DialogDetalleReserva
    → (web-admin, Fase 7.4)
    → Mostrar codigo_publico_sireb con botón Copiar.
    → Link directo al panel de SIREB.
    → Estado en vivo con botón "Refrescar desde SIREB".
    → Si corresponde: botón "Anular liquidación" con AlertDialog que
      pida motivo (textarea obligatoria, mínimo 10 caracteres).
    → Al confirmar anulación: llamar al endpoint nuevo
      POST /api/v1/admin/solicitudes-reserva/{id}/anular-liquidacion.
    → Toast de éxito o error según respuesta de SIREB.

[ ] Endpoint admin para anular liquidación
    → POST /api/v1/admin/solicitudes-reserva/{id}/anular-liquidacion
    → Body: { motivo: string } (obligatorio).
    → Llama a anularLiquidacion(liquidacion_id, motivo).
    → Si SIREB responde LIQUIDACION_NO_ANULABLE, propaga 422 con el
      mensaje al frontend.
    → Registra en auditoria: 'anular_liquidacion' con funcionario,
      motivo y referencia SIREB.
    → Protegido por RoleMiddleware(['admin_parametricas',
      'admin_reservas']).

[ ] Endpoint admin para refrescar estado desde SIREB
    → GET /api/v1/admin/solicitudes-reserva/{id}/refrescar-sireb
    → Llama a consultarLiquidacionDetalle(liquidacion_id) y devuelve el
      estado actual de SIREB.
    → Protegido por RoleMiddleware.
    → Se usa solo desde el dialog de detalle, como diagnóstico.

[ ] Hook de eventos en ConfirmacionCobroService
    → Al final de confirmar(), disparar event(new
      SolicitudConfirmada($solicitud)).
    → Crear el evento (app/Events/SolicitudConfirmada.php), sin
      listeners por ahora.

[ ] Tests de frontend (manuales o Playwright)
    → Flujo real desde web pública: crear reserva → ver QR de SIREB
      → pagar → comprobante.
    → Admin puede ver codigo_publico_sireb en el detalle de reserva.
    → Botón "Copiar" copia el código al portapapeles.
    → Link al panel de SIREB abre en nueva pestaña.
    → Botón "Refrescar desde SIREB" actualiza el estado.
    → Botón "Anular liquidación" abre dialog, al confirmar con motivo
      la liquidación queda anulada y la solicitud rechazada.
    → Intentar anular liquidación con pago → SIREB responde 422 y la
      UI muestra "No se puede anular: ya tiene pago".

[ ] Commit de la fase
    → Mensaje: "feat(sireb): UX real de pago, anulación y bloque de integración en admin"
```

---

# FASE 8.6 — Pruebas de Integración con Entorno de Prueba de SIREB

## Por qué una fase entera de pruebas

Hasta ahora testeamos con mocks y con el simulador. Esta fase es la
primera vez que **una reserva real atraviesa todo el sistema y termina en
SIREB de verdad**. Los bugs que aparecen acá no son de código sino de
contrato: "el campo X que SIREB manda en el webhook no se llama como
pensábamos", "el QR que devuelve SIREB no se renderiza bien en móviles",
etc. Por eso merece una fase propia con checklist explícita.

## Entorno requerido

- Credenciales de SIREB **test** (no producción).
- URL pública de Canchas accesible desde internet (ngrok en dev, staging
  en QA).
- Webhook registrado en el panel de SIREB apuntando a esa URL.
- Usuario de prueba del GAD con CI/NIT válido en SIREB.

---

## Tareas de la Fase 8.6

```
[ ] Smoke test del flujo feliz
    1. Crear reserva desde web pública con CI de prueba.
    2. Verificar que en SIREB test aparece la liquidación con los
       items correctos, codigo_publico visible, referencia_externa =
       nuestro codigo_seguimiento.
    3. Pagar la liquidación desde el panel de SIREB (o QR real si
       SIREB test lo permite).
    4. Verificar que el webhook llega y dispara confirmar().
    5. Verificar que la solicitud pasa a 'confirmada' y se crean las
       reservas.
    6. Ver el comprobante con los códigos de reserva correctos.

[ ] Smoke test de idempotencia
    1. Crear una reserva.
    2. Desde Postman, reenviar el mismo webhook de pago 5 veces.
    3. Verificar que reservas tiene exactamente las filas esperadas
       (ni una más, ni una menos).

[ ] Smoke test de expiración con anulación en SIREB
    1. Crear reserva y NO pagarla.
    2. Esperar a que ExpirarSolicitudJob la marque expirada.
    3. Verificar en SIREB que la liquidación quedó anulada (estado
      'anulada' con motivo 'solicitud_expirada_sin_pago').

[ ] Smoke test de expiración cuando ya hay pago en proceso
    1. Crear reserva, iniciar pago en SIREB (que quede pago
      "pendiente" de validación).
    2. Dejar vencer el timer local.
    3. Verificar que ExpirarSolicitudJob intenta anular, recibe 422
      LIQUIDACION_NO_ANULABLE, loguea warning y NO expira la solicitud
      local.
    4. Verificar que cuando SIREB valida el pago (minutos después),
      el webhook confirma la solicitud normalmente.

[ ] Smoke test de anulación desde admin
    1. Crear reserva sin pagar.
    2. Desde el admin, anular la liquidación con motivo "prueba".
    3. Verificar que en SIREB la liquidación queda anulada.
    4. Verificar que la solicitud queda rechazada con motivo
      'liquidacion_anulada_core' (si usamos el admin como "Core") o
      con motivo del operador (si registramos como acción manual).
    5. Verificar fila en auditoria con el motivo.

[ ] Smoke test de caída de SIREB al crear
    1. Con SIREB test temporalmente inaccesible (o mockeando 5xx),
       crear una reserva.
    2. Verificar que ReintentarSolicitudJob reintenta hasta 5 veces.
    3. Al fallar las 5, verificar que la solicitud queda rechazada
       con motivo 'error_cobro_inicial'.
    4. Verificar que la UI muestra el mensaje específico.

[ ] Smoke test de webhook con firma inválida
    1. Enviar POST al endpoint de webhook con firma inventada.
    2. Verificar 401 y que no se toca ninguna solicitud.

[ ] Smoke test de referencia_externa bidireccional
    1. Crear reserva, capturar codigo_seguimiento.
    2. Consultar desde SIREB con ese codigo_seguimiento como
      referencia_externa: debe devolver la liquidación creada.
    3. Desde el admin, verificar que el link al panel de SIREB abre
      exactamente esa liquidación.

[ ] Load test básico (opcional)
    → Crear 20 reservas en paralelo y verificar que el idempotency-key
      no se rompe, el cache de tokens no colapsa, y SIREB test aguanta.

[ ] Documentar bugs encontrados
    → Todo lo que no coincida con el contrato asumido va a un issue en
      el repo y se notifica al equipo de SIREB. No se "parchea" del
      lado de Canchas sin consultar.

[ ] Commit de la fase
    → Mensaje: "test(sireb): smoke tests de integración con entorno real de SIREB"
```

---

# FASE 8.7 — Documentación, Credenciales de Producción y Deploy

## Checklist de pase a producción

```
[ ] Documentar la integración
    → docs/integrations/SIREB.md con:
      - Credenciales necesarias y quién las pide.
      - Endpoints consumidos y contratos.
      - Flujo completo con diagrama de secuencia.
      - Manejo de errores y reintentos.
      - Troubleshooting: "si el webhook no llega, revisar X", "si el
        QR no carga, verificar Y".
      - Matriz de códigos de error y cómo los manejamos.

[ ] Actualizar README del backend
    → Sección nueva: "Integración con SIREB (Recaudaciones GAD Beni)"
      con las variables de entorno y el flujo.

[ ] Solicitar credenciales de producción
    → Acción manual: pedir al equipo de Ibare el client_id/client_secret
      para el sistema sedede en producción, y el webhook_secret real.

[ ] Configurar el panel de SIREB para producción
    → Acción manual: registrar la URL pública de producción como
      endpoint de webhook en el panel de SIREB.

[ ] Ajustar parámetros de producción
    → RECAUDACIONES_API_URL = https://sireb.beni.gob.bo (no test)
    → RECAUDACIONES_TIMEOUT_SEGUNDOS = 15 (más holgado que en test)
    → Verificar QUEUE_CONNECTION=redis y que hay workers escuchando.

[ ] Configurar alertas de caídas de SIREB
    → En el canal 'sireb' del log, alertar si hay más de 5 errores 5xx
      en 5 minutos. Integración con el sistema de monitoreo que tenga
      el GAD (Prometheus + Grafana, CloudWatch, etc.).
    → Alertar si el webhook no recibe ningún evento en 24h (puede ser
      que SIREB dejó de mandar o se rompió la URL).

[ ] Plan de rollback
    → Si en producción algo sale mal, el flag
      'recaudaciones.simulador_habilitado' permite volver al simulador
      en segundos sin redeployar. Documentar cómo se activa.
    → IMPORTANTE: volver al simulador en producción con liquidaciones
      reales ya creadas en SIREB dejaría esas liquidaciones "huérfanas".
      Documentar el procedimiento manual de conciliación para ese caso.

[ ] Deploy
    → Merge de la rama sireb-integration a main.
    → Deploy a staging → pruebas finales con credenciales test.
    → Deploy a producción → monitoreo intenso las primeras 48 horas.

[ ] Commit de cierre
    → Mensaje: "chore: cierre Módulo 8 - integración real con SIREB (Recaudaciones GAD Beni)"
    → Tag sugerido: v1.0.0-sireb-integration

[ ] Comunicar al equipo del GAD
    → Nota formal al equipo de SIREB/Ibare avisando que Canchas ya está
      conectado en producción y cuáles son los volúmenes esperados.
```

---

## 📝 Notas finales

### Matriz de estados de solicitud al cierre del Módulo 8

| Estado | Cómo se llega | Causa típica |
|---|---|---|
| `pendiente` | `crear()` exitoso, esperando pago | Ciudadano aún no pagó |
| `confirmada` | webhook o polling confirma pago | Pago OK en SIREB |
| `expirada` | `ExpirarSolicitudJob` vence el timer | Ciudadano no pagó a tiempo |
| `rechazada` | webhook anulación/rechazo, o 5 reintentos fallidos | `motivo_rechazo` aclara cuál |

No se agrega un quinto estado. Los casos especiales (SIREB caído al
inicio, liquidación anulada, pago rechazado por el Core) se discriminan
por `motivo_rechazo`.

### Valores posibles de `motivo_rechazo`

| Valor | Origen | Significado |
|---|---|---|
| `error_cobro_inicial` | `ReintentarSolicitudJob` falla 5 veces | SIREB caído al crear liquidación |
| `liquidacion_anulada_core` | webhook `liquidacion.anulada` | SIREB anuló la liquidación (por ejemplo, desde su panel) |
| `pago_rechazado_core` | webhook `pago.rechazado` | El banco rechazó el pago del ciudadano |
| `expirada_sin_pago` | `ExpirarSolicitudJob` | Timer venció sin pago (también anula en SIREB) |
| `anulada_por_operador` | admin desde el dialog | Operador anuló manualmente con motivo |

### Migraciones del módulo

Una sola: `2026_XX_XX_add_sireb_fields_to_solicitudes_reserva_table.php`
(agrega `motivo_rechazo`, `liquidacion_id`, `codigo_publico_sireb`).

### Relación con los módulos vecinos

- **Módulos 4 y 5:** los tests del simulador siguen corriendo con el flag
  `recaudaciones.simulador_habilitado=true`. No se rompen.
- **Módulo 6 (dashboard gerencial):** los reportes de ingresos ahora
  tienen un dato real de SIREB para cruzar.
- **Módulo 7 (gestión operativa):** el listado de reservas gana el
  bloque de "Integración con SIREB" en el detalle + botón de anulación.
- **Módulo 9 (Épica G: seguridad y publicación):** ahora sí tiene sentido
  hacerlo, porque el sistema está conectado de verdad.

### Orden sugerido de implementación

1. **Obtener TODAS las dependencias pendientes** del contrato (tabla
   anterior). Esto puede hacerse en paralelo con 8.1.
2. **8.1 + 8.2** en paralelo (token + endpoints)
3. **8.3** (adaptación del service, depende de 8.2)
4. **8.4** (webhook real, puede ir en paralelo con 8.3)
5. **8.5** (frontend, depende de 8.3)
6. **8.6** (smoke tests con SIREB test, depende de todo lo anterior)
7. **8.7** (deploy, al final)

### Riesgos conocidos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| SIREB cambia el contrato sin avisar | Tests de contrato que corren en CI; si rompen, saltan alertas |
| SIREB test es inestable | Flag para volver al simulador en dev en segundos |
| Webhook no llega (firewall, DNS) | Polling de respaldo cada 60s + health endpoint |
| Token de SIREB se revoca antes del TTL | `tokenValido()` con margen de 60s + reintento ante TOKEN_INVALIDO |
| Ciudadano paga pero webhook se pierde | Polling lo detecta en el siguiente ciclo |
| Admin anula liquidación que ya tenía pago | SIREB devuelve 422 LIQUIDACION_NO_ANULABLE, UI lo muestra claro |
| SIREB caído al crear liquidación | ReintentarSolicitudJob reintenta 5 veces; después rechaza con motivo claro |
| Rollback a simulador en producción con liquidaciones reales | Documentar procedimiento manual de conciliación |

### Decisiones de diseño explícitas

1. **Sí exponemos anulación en el admin** porque SIREB ya hace la
   validación de conciliación (HU-047). Con `AlertDialog` pidiendo
   motivo y registrando en auditoria.
2. **No exponemos pago manual** porque es responsabilidad de SIREB.
3. **No agregamos un quinto estado** a solicitudes_reserva: usamos
   `motivo_rechazo` para discriminar los casos de rechazo.
4. **Postergamos email/SMS** al Módulo 10: el sistema funciona sin
   ellos, y agregarlos es un alcance que merece su propio roadmap.
5. **No parcheamos del lado de Canchas** los bugs de contrato que
   encontremos: se reportan al equipo de SIREB.

---

> **Siguiente módulo:** el Módulo 9 (antes Módulo 7) implementa la Épica G —
> rate limiting contra abuso en la app anónima, pruebas de concurrencia
> formales y repetibles, y el checklist de publicación en Google Play
> Store. Con SIREB ya integrado, ese módulo cierra el proyecto.

---
