# Integración Paitití / SIREB — Diseño técnico

**Última actualización:** 2026-10-03
**Estado:** implementado y verificado contra `https://test.sireb.beni.gob.bo`

> Este documento describe **cómo está construida** la integración hoy. Para el
> detalle de qué se tocó archivo por archivo ver `RESUMEN_IMPLEMENTACION_SIREB.md`,
> y para el estado operativo y los pendientes ver `ESTADO_FINAL_IMPLEMENTACION.md`.

---

## 1. Objetivo y reglas del sistema

Este repositorio es el **satélite de Canchas** del ecosistema GAD Beni
(patrón Hub & Spoke). El cobro se delega al **Core de Recaudaciones (SIREB)**,
que es un proyecto independiente.

Cuatro invariantes que el código debe respetar siempre:

1. **SIREB es la fuente de verdad de los precios.** Este sistema no fija
   tarifas: las refleja. No existe endpoint, formulario ni comando para que una
   persona escriba un precio.
2. **Ningún cliente llama a SIREB directamente.** Web pública, panel y mobile
   consumen la API de este backend. Solo el backend habla con SIREB.
3. **La vinculación campo ↔ servicio SIREB es 1:1.** Un servicio de SIREB no
   puede estar vinculado a dos campos locales (índice único parcial).
4. **El código interno del campo no se publica.** La web pública muestra el
   código oficial de SIREB (`SEDEDE-CS1`, `0005`, …); el interno (`CD-001`,
   `FS-001`, …) queda para uso del GAD.

---

## 2. Arquitectura

```
ciudadano ──▶ web-public ─┐
ciudadano ──▶ mobile ─────┼──▶ API Canchas (Laravel) ──▶ SIREB (API REST + OAuth2)
operador  ──▶ web-admin ──┘                              test.sireb.beni.gob.bo
```

- **Autenticación del backend contra SIREB:** OAuth2 `client_credentials`
  contra Ibare (`https://test.ibare.beni.gob.bo/oauth/token`). El token dura
  `expires_in` segundos, no hay refresh: se pide otro con las mismas
  credenciales. Se cachea en Redis con la clave `sireb:access_token`.
- **Catálogo de servicios:** se consulta `GET /api/v1/catalogo/servicios` y se
  cachea 10 minutos (clave `sireb:catalogo`). `CatalogoSirebService` expone
  `catalogoCacheado()`, `refrescarCatalogo()` (ignora la caché),
  `servicioPorId()` y `ultimaActualizacion()`.
- **Simulador:** `RECAUDACIONES_SIMULADOR_HABILITADO=true` inyecta
  `RecaudacionesApiClientSimulado` en lugar del cliente real. Se usa en
  desarrollo y en la suite de tests; en test/producción va en `false`.

---

## 3. Modelo de datos

### `campos_deportivos`

| Columna | Rol |
| --- | --- |
| `codigo`, `nombre`, `direccion`, `latitud`, `longitud`, `imagen_url` | Datos operativos locales (los administra el GAD) |
| `hora_inicio_noche` | Hora de corte entre tarifa regular y con iluminación. Es una decisión **local** y sigue siendo editable |
| `servicio_sireb_id` | UUID del servicio en SIREB. `NULL` = sin vincular. Índice único parcial `uq_campos_servicio_sireb` |
| `servicio_sireb_codigo` | Código legible del servicio (`SEDEDE-CS1`, `0005`, …) |

El nombre de la web pública es el **oficial de SIREB**; el interno viaja como
`nombre_local` en el JSON público.

### `tarifas_campo`

Tabla **espejo** del tarifario de SIREB, versionada por fecha:

| Columna | Rol |
| --- | --- |
| `tipo_tarifa` | `diurna` (bloques antes de la hora de corte) o `nocturna` |
| `precio_por_hora` | Precio copiado de SIREB |
| `vigente_desde` / `vigente_hasta` | Ventana de vigencia. `vigente_hasta NULL` = versión activa |
| `creado_por` | Funcionario que la registró. **Siempre `NULL`**: las escribe el comando de sincronización, no una persona |

Índice único parcial `uq_tarifa_activa_por_tipo` sobre `(campo_id, tipo_tarifa)`
donde `vigente_hasta IS NULL`: como máximo una tarifa activa por tipo.

---

## 4. Endpoints

### Públicos (sin token)

| Método | Ruta | Qué devuelve |
| --- | --- | --- |
| `GET` | `/api/v1/public/campos` | Catálogo con precios de SIREB, `reservable_online` y `meta.fuente_precios` |
| `GET` | `/api/v1/public/campos/{id}` | Detalle de un campo |

El bloque `sireb` del JSON trae `id`, `codigo`, `nombre`, `descripcion`,
`rubro`, `estado`, `modo_tarifa`, `tarifario`, `unidad_medida`, `precio_min`,
`precio_max` y `tarifas`. Si el campo no está vinculado, `fuente_precios` es
`NO_VINCULADO` y `reservable_online` es `false`.

### Panel (rol `admin_parametricas`)

| Método | Ruta | Qué hace |
| --- | --- | --- |
| `GET` | `/api/v1/admin/catalogo-sireb/campos` | Lista el catálogo de SIREB con su campo local y estado de vinculación |
| `PATCH` | `/api/v1/campos-deportivos/{id}/vinculo-sireb` | Vincula el campo a un servicio (rechaza servicios ya tomados) |
| `DELETE` | `/api/v1/campos-deportivos/{id}/vinculo-sireb` | Desvincula |
| `POST` | `/api/v1/admin/sireb/sincronizar-tarifas` | Espeja el tarifario de SIREB. **Única vía por la que cambian los precios** |
| `GET` | `/api/v1/campos-deportivos/{id}/tarifas` | Tarifas activas + historial + hora de corte. **Solo lectura** |

No existe `POST /api/v1/campos-deportivos/{id}/tarifas`: la ruta se eliminó
junto con `StoreTarifaRequest` y `TarifaCampoService::actualizarTarifa`.

---

## 5. Comandos y scheduler

```bash
# Vincula los campos locales con el catálogo vigente de SIREB.
# Completa servicio_sireb_id y servicio_sireb_codigo. Es idempotente.
php artisan sireb:mapear-campos [--dry-run] [--listar]
php artisan sireb:mapear-campos --crear --tipo-campo=Fútbol
php artisan sireb:mapear-campos --map=CD-010:SEDEDE-CS3

# Espeja el tarifario. Versiona: cierra la tarifa vigente y crea una nueva.
php artisan sireb:sincronizar-tarifas [--dry-run]
```

`sireb:sincronizar-tarifas` corre **todos los días** por el scheduler
(`routes/console.php`) con `withoutOverlapping()`.

### Correspondencia de etiquetas

SIREB no manda "diurna/nocturna": manda etiquetas libres. El mapeo es por
texto, en `SincronizarTarifasSireb::tipoDesdeEtiqueta()` y en el presenter:

| Etiqueta SIREB | `tipo_tarifa` |
| --- | --- |
| contiene "Diurno" | `diurna` |
| contiene "Nocturno" | `nocturna` |
| cualquier otra | se ignora y se reporta en la tabla del comando |

---

## 6. Variables de entorno

### Backend (`backend/.env`)

```env
# URL base de SIREB (donde viven los /api/v1/*)
RECAUDACIONES_API_URL=https://test.sireb.beni.gob.bo

# Credenciales del sistema consumidor, emitidas por Ibare
SIREB_TOKEN_URL=https://test.ibare.beni.gob.bo/oauth/token
SIREB_CLIENT_ID=sedede
SIREB_CLIENT_SECRET=<secreto>

# Paths configurables por entorno
SIREB_PATH_CATALOGO_SERVICIOS=/api/v1/catalogo/servicios
SIREB_PATH_CLIENTES=/api/v1/clientes
SIREB_PATH_LIQUIDACIONES=/api/v1/liquidaciones

# false = cliente real contra SIREB; true = simulador local
RECAUDACIONES_SIMULADOR_HABILITADO=false
RECAUDACIONES_TIMEOUT_SEGUNDOS=10
```

### Frontends

| Variable | Dónde | Default |
| --- | --- | --- |
| `VITE_API_BASE_URL` | web-admin, web-public | `http://localhost:8000/api` |
| `VITE_SIREB_PANEL_SERVICIOS_URL` | web-admin | `https://test.sireb.beni.gob.bo/panel/servicios` |
| `VITE_SIREB_PANEL_URL` | web-public | `https://test.sireb.beni.gob.bo/panel/liquidaciones` |

---

## 7. Reglas de negocio derivadas

- Un servicio es **reservable online** si está `activo` y tiene al menos una
  tarifa `liquidable` con `tarifario_estado = vigente`. Lo calcula
  `CampoPublicoSirebPresenter::esReservable()`.
- El catálogo de SIREB devuelve **solo servicios liquidables**. Un servicio
  `sin_tarifa` (por ejemplo `0002`) aparece en el panel de SIREB pero no en la
  API, así que no puede publicarse.
- Al reservar, `CatalogoSirebService::resolverTarifaId()` traduce
  (campo + hora) a un `tarifa_id` de SIREB usando `hora_inicio_noche` como
  corte. Si el campo no está vinculado, la reserva falla con un error explícito.
- Si SIREB no responde, la web pública cae al espejo local y lo declara en
  `meta.aviso` con `fuente_precios: LOCAL_FALLBACK`.

---

## 8. Deuda técnica y decisiones abiertas

| Tema | Detalle |
| --- | --- |
| `CATALOGO_PUBLICO_FALLBACK_LOCAL` | Declarada en `config/services.php` pero **ningún código la lee**. El fallback local está siempre activo. Hay que decidir si se implementa (y si en producción conviene fallar en vez de mostrar precios posiblemente desactualizados) o se elimina la clave |
| Tipo de campo de la piscina | `CD-005` quedó con tipo "Fútbol Sala" y muestra la etiqueta equivocada en la web pública. Es dato local, SIREB no lo manda |
| Direcciones locales | `CD-002` tiene `direccion = "av"` y `CD-005` `"av principal"`. Se ven junto al nombre oficial |
| Campo sin vincular | Hoy un campo sin `servicio_sireb_id` se publica marcado como no reservable. Decidir si conviene ocultarlo |
| Antigüedad del espejo | El espejo local puede quedar hasta 1 día desactualizado si SIREB cambia un precio después de la corrida diaria |

---

## 9. Criterios de aceptación

### Backend

- [x] Paths de SIREB configurables por entorno
- [x] El cliente real usa la config de paths
- [x] `GET /admin/catalogo-sireb/campos` funcionando
- [x] Vincular / desvincular con auditoría
- [x] Sincronización de tarifas funcionando y versionada
- [x] Índice único parcial por servicio
- [x] No se puede fijar un precio a mano (ruta eliminada; test lo verifica)
- [x] El comando de vinculación completa código y da de alta lo que falte

### Web pública

- [x] Valida `reservable_online` antes de dejar reservar
- [x] Muestra `mensaje_no_reservable` cuando corresponde
- [x] Muestra nombre y código oficiales, precio de SIREB y badge "Oficial"
- [x] Muestra el aviso cuando cae al espejo local
- [x] `npx tsc --noEmit` limpio

### Panel

- [x] Conmutador *Campos locales* / *Catálogo SIREB*
- [x] Vincular y desvincular desde la UI
- [x] Pantalla de tarifas en solo lectura, con acceso al panel de SIREB
- [x] Botón de sincronización
- [x] `npx tsc --noEmit` limpio

### Mobile

- [x] Modelos con los campos de SIREB
- [x] Valida `reservableOnline`
- [x] Muestra mensajes y origen de precios
