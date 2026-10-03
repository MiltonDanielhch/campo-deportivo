# Estado de la integración SIREB

**Fecha:** 2026-10-03
**Entorno verificado:** `https://test.sireb.beni.gob.bo` (SIREB test) + PostgreSQL local

> Foto del estado operativo hoy. Para el diseño ver
> `PLAN_TECNICO_SIREB_INTEGRACION.md`, y para el detalle de los cambios
> `RESUMEN_IMPLEMENTACION_SIREB.md`.

---

## Resumen

La integración está **cerrada y funcionando de punta a punta**: la web pública,
el panel y el mobile leen el catálogo oficial de SIREB, los campos locales están
vinculados a sus servicios y los precios son los que define SIREB.

**Nadie fija precios en este sistema.** El único escritor de `tarifas_campo` es
`sireb:sincronizar-tarifas`, que corre a diario y versiona cada cambio.

---

## Estado por fase

| Fase | Estado |
| --- | --- |
| 1. Backend core | ✅ Completa |
| 2. Backend admin | ✅ Completa |
| 3. Web pública | ✅ Completa |
| 4. Panel Paitití | ✅ Completa |
| 5. Mobile | ✅ Completa |
| 6. Mapeo y operación | ✅ Completa |

---

## Qué funciona hoy (con evidencia)

### Catálogo público

`GET /api/v1/public/campos` devuelve **4 campos**, `meta.fuente_precios: SIREB`,
`meta.aviso: null`, y todos `reservable_online: true`:

| Nombre publicado | Código SIREB | Nombre interno | Precios |
| --- | --- | --- | --- |
| Cancha Sintética N° 2 | `SEDEDE-CS2` | Cancha Techada Central | Bs. 50 – 100 |
| Cancha Sintética N° 1 | `SEDEDE-CS1` | cancha vieja | Bs. 50 – 100 |
| Estadio Gran Mamoré - Alquiler | `SEDEDE-EGM-ENTREN` | estadio gran mamore | Bs. 100 – 400 |
| H. PISCINA OLIMPICA | `0005` | piscina | Bs. 10 |

El JSON expone `nombre` (oficial) y `nombre_local` (interno). El código interno
del campo **no** se publica: el oficial viaja en `servicio_sireb_codigo`.

### Mapeo campo ↔ servicio

| Campo local | Servicio SIREB |
| --- | --- |
| `CD-001` | `SEDEDE-CS1` |
| `FS-001` | `SEDEDE-CS2` |
| `CD-002` | `SEDEDE-EGM-ENTREN` |
| `CD-005` | `0005` |
| `CD-008` (`asdfg`, inactivo) | sin equivalente |

### Panel Paitití

- **Campos Deportivos** tiene conmutador *Campos locales* / *Catálogo SIREB*.
  La vista de catálogo muestra código, nombre, tarifario, precio, campo local y
  estado de vinculación, con acciones de vincular / desvincular.
- **Tarifas del campo** es de **solo lectura**: muestra la tarifa vigente, el
  historial versionado con quién la originó, y permite volver a sincronizar
  contra SIREB. No hay forma de escribir un precio.
- La hora de encendido de iluminación **sí** es editable: es una decisión local
  que define dónde corta la tarifa regular y dónde empieza la nocturna.

### Verificación automática

| Suite | Resultado |
| --- | --- |
| `TarifaCampoTest` | 7/7 ✅ |
| `CampoControllerTest` (catálogo público) | 6/6 ✅ |
| `CampoDeportivoTest` (ABM de campos) | 6/6 ✅ |
| Suite completa del backend | 127 ✅ / 12 ❌ **preexistentes del módulo de auth** (ver abajo) |
| `npx tsc --noEmit` en web-public | limpio ✅ |
| `npx tsc --noEmit` en web-admin | limpio ✅ |

#### Fallas preexistentes del módulo de auth (ajenas a SIREB)

No las introdujo la integración de SIREB y no se tocaron: son tests que quedaron
desincronizados con las rutas cuando se migró a Ibare OAuth.

| Tests | Causa |
| --- | --- |
| 7 × `AuthOAuthIbareTest` | Pegan a `/api/v1/oauth/me`, que no existe. La ruta viva es `/api/v1/auth/me` |
| 3 × `AuthTest` + 1 × `FuncionarioTest` | Esperan `POST /api/v1/auth/login`, que está comentado en `routes/api.php` desde la migración a OAuth |
| 2 × `AuthTest` | `GET /api/v1/auth/me` devuelve 500 en vez de 401/200: el middleware `auth.oauth` deja pasar la petición sin usuario y el controller llama `loadMissing()` sobre null |

Hay que decidir con el equipo de auth si esos tests se actualizan a las rutas
nuevas, si el login local vuelve, o si se eliminan.

---

## Cómo verificarlo

```bash
# Backend: catálogo real de SIREB tal como lo ve la web pública
cd backend
php artisan tinker   # o el endpoint: GET /api/v1/public/campos

# Estado del mapeo, sin tocar nada
php artisan sireb:mapear-campos --listar

# Ver qué cambiaría la sincronización de precios
php artisan sireb:sincronizar-tarifas --dry-run

# Aplicarla
php artisan sireb:sincronizar-tarifas
```

En el navegador: `http://localhost:5173/campos` (web pública) y
`http://localhost:5173/panel/parametricas/campos` (panel).

---

## Pendientes y decisiones abiertas

Ninguno bloquea la operación; son ajustes de datos y de producto.

| # | Tema | Qué falta decidir |
| --- | --- | --- |
| 1 | Tipo de campo de la piscina | `CD-005` quedó como "Fútbol Sala" y la web pública muestra esa etiqueta sobre `H. PISCINA OLIMPICA`. Habría que crear un tipo "Natación" y asignarlo |
| 2 | Direcciones locales | `CD-002` tiene `"av"` y `CD-005` `"av principal"`. Se ven junto al nombre oficial |
| 3 | Servicio `0002` | En `/panel/servicios` figura como `sin_tarifa`; la API solo devuelve servicios liquidables, así que no puede publicarse. Si hay que mostrarlo, lo tiene que habilitar el equipo de recaudaciones |
| 4 | `CATALOGO_PUBLICO_FALLBACK_LOCAL` | Declarada en la config pero sin uso: el fallback local está siempre activo. Decidir si se implementa (¿en producción conviene fallar antes que mostrar un precio posiblemente viejo?) o se elimina la clave |
| 5 | Campos sin vincular | Hoy se publican marcados como no reservables. Decidir si conviene ocultarlos del catálogo |
| 6 | Antigüedad del espejo | El espejo local puede quedar hasta 1 día desactualizado si SIREB cambia un precio después de la corrida diaria |

---

## Riesgos

- **Si SIREB no responde**, la web pública cae al espejo local y lo declara en
  `meta.aviso`. El precio mostrado puede diferir del que cobre SIREB si el
  tarifario cambió ese día.
- **Fuente de verdad partida mientras se migre**: las reservas ya creadas
  conservan su `referencia_recaudaciones`; el espejo local no participa del cobro.
- **Etiquetas de tarifa por texto**: el mapeo "Diurno"/"Nocturno" es por
  coincidencia de texto. Si recaudaciones renombra una etiqueta, el comando lo
  reporta como "Etiqueta no reconocida" en lugar de fallar en silencio.
