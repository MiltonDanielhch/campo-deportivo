# Bitácora de implementación — Integración SIREB

**Última actualización:** 2026-10-03
**Estado:** todas las fases cerradas

> Qué se tocó y por qué, archivo por archivo. Para el diseño ver
> `PLAN_TECNICO_SIREB_INTEGRACION.md`, y para el estado operativo
> `ESTADO_FINAL_IMPLEMENTACION.md`.

---

## Fase 1 — Backend core

| Archivo | Cambio |
| --- | --- |
| `backend/config/services.php` | Paths de SIREB (`catalogo_servicios`, `clientes`, `liquidaciones`) y credenciales OAuth2 configurables por entorno |
| `backend/app/Integrations/Recaudaciones/RecaudacionesApiClient.php` | Todos los métodos usan `config('services.recaudaciones.paths.*')` en vez de rutas hardcodeadas |
| `backend/app/Services/CatalogoSirebService.php` | Caché del catálogo (10 min) y posteriormente `refrescarCatalogo()` para ignorarla |
| `backend/database/migrations/2026_10_03_000002_add_unique_index_servicio_sireb_id_to_campos_deportivos.php` | Índice único parcial `uq_campos_servicio_sireb` |
| `backend/database/migrations/2026_10_03_000003_add_servicio_sireb_codigo_to_campos_deportivos.php` | Columna `servicio_sireb_codigo` + índice |
| `backend/app/Models/CampoDeportivo.php` | `servicio_sireb_codigo` en `$fillable` |

## Fase 2 — Backend admin

| Archivo | Cambio |
| --- | --- |
| `backend/app/Http/Controllers/Api/V1/Admin/CatalogoSirebController.php` | Nuevo. `index`, `vincular`, `desvincular`, `sincronizarTarifas`, con auditoría |
| `backend/routes/api.php` | Rutas del panel bajo `auth.oauth` + `role:admin_parametricas` |

## Fase 3 — Web pública

| Archivo | Cambio |
| --- | --- |
| `web-public/src/types/campo.ts` | Nuevo. Tipos del payload real del backend |
| `web-public/src/components/campo/CampoCard.tsx` | Valida `reservable_online`, muestra `mensaje_no_reservable`, precio y badge oficial |
| `web-public/src/pages/Campos.tsx` | Muestra `meta.sincronizado_en` y `meta.aviso` |
| `web-public/src/components/campo/MapaCampos.tsx` | Validación en el click del marcador y precios de SIREB en el popup |
| `web-public/src/components/landing/CamposDestacados.tsx` | Usa el tipo compartido y muestra badge "Oficial" y rango de precios |
| `web-public/src/pages/CampoDetalle.tsx` | Nombre oficial y "Código oficial SIREB" en el hero |

## Fase 4 — Panel Paitití

| Archivo | Cambio |
| --- | --- |
| `web-admin/src/services/catalogoSirebService.ts` | Nuevo. Catálogo, vincular, desvincular, sincronizar |
| `web-admin/src/services/camposService.ts` | `vincularSireb()` y `desvincularSireb()` |
| `web-admin/src/components/parametricas/VincularSirebDialog.tsx` | Nuevo. Diálogo de vinculación contra el catálogo oficial |
| `web-admin/src/pages/parametricas/CamposDeportivos.tsx` | Conmutador *Campos locales* / *Catálogo SIREB*, acción de alta prellenada desde el servicio y columna con el código oficial |
| `web-admin/src/components/parametricas/CampoFormDialog.tsx` | Acepta `valoresIniciales` para el alta desde un servicio de SIREB |
| `web-admin/src/pages/parametricas/Tarifas.tsx` | Reescrita en **solo lectura**: se quitó el formulario de precios y el diálogo de confirmación; se agregaron aviso, botón de sincronización y enlace al panel de SIREB |

## Fase 5 — Mobile

| Archivo | Cambio |
| --- | --- |
| `mobile/lib/models/campo_deportivo.dart` | Campos de SIREB y modelos `ServicioSireb` / `TarifaSireb` |
| `mobile/lib/services/campos_service.dart` | `obtenerMeta()` |
| `mobile/lib/screens/campos_listado_screen.dart` | Valida reservabilidad, precios de SIREB, badge "Oficial", footer con origen y última sincronización |

## Fase 6 — Operación

| Archivo | Cambio |
| --- | --- |
| `backend/app/Console/Commands/MapearCamposSireb.php` | Deja de ser un listado: vincula contra el catálogo vigente. Flags `--dry-run`, `--listar`, `--crear`, `--tipo-campo`, `--map` |
| `backend/database/seeders/MapeoSirebSeeder.php` | Respaldo offline del mapeo, con la piscina (`0005`) y los códigos |
| `backend/database/mapeo_sireb.sql` | **Eliminado**: era un parche con UUIDs de reemplazo que contradecía el mapeo real |

---

## Cambios posteriores (misma fecha)

### El precio deja de ser editable

Motivo: SIREB es la fuente de verdad de precios y el job diario pisaba cualquier
valor cargado a mano, así que la pantalla prometía algo que no podía cumplir.

| Archivo | Cambio |
| --- | --- |
| `backend/routes/api.php` | Se elimina `POST /campos-deportivos/{id}/tarifas`. Queda solo el `GET` |
| `backend/app/Http/Controllers/Api/V1/TarifaCampoController.php` | Sin `store()`. Pasa a ser un controlador de consulta |
| `backend/app/Services/TarifaCampoService.php` | Sin `actualizarTarifa()` ni los helpers de identificación del funcionario. Quedan `tarifasActivas()` e `historial()` |
| `backend/app/Http/Requests/StoreTarifaRequest.php` | **Eliminado** |
| `backend/app/Console/Commands/SincronizarTarifasSireb.php` | Versiona los cambios (cierra la tarifa vigente y crea una nueva) en vez de pisar el precio, y registra auditoría. Se quita el flag `--force`, que no hacía nada |
| `backend/app/Http/Controllers/Api/V1/Admin/CatalogoSirebController.php` | Deja de pasar `--force` |
| `web-admin/src/pages/parametricas/Tarifas.tsx` | Solo lectura: aviso, botón "Sincronizar desde SIREB" y enlace a `/panel/servicios` |
| `web-admin/src/services/tarifasService.ts` | Sin `crear()` |
| `web-admin/src/types/parametricas.ts` | Sin `TarifaCampoPayload`; `creado_por` admite `null` |
| `backend/tests/Feature/Api/V1/TarifaCampoTest.php` | Reescrito: verifica que el endpoint de escritura devuelve 405, que la sincronización crea el espejo, que versiona los cambios y que el dry-run no toca nada |

### Correcciones en el panel

| Archivo | Cambio |
| --- | --- |
| `web-admin/src/pages/parametricas/Tarifas.tsx` | `creado_por` nulo (tarifas espejadas) rompía la pantalla: `typeof null === 'object'` entraba al camino que asume objeto |
| `web-admin/src/components/ui/dropdown-menu.tsx` | `DropdownMenuTrigger` traduce `asChild` a `render`: eliminaba botones anidados |
| `web-admin/src/components/ui/alert-dialog.tsx` | `AlertDialogDescription` traduce `asChild` a `render`: el atributo se filtraba al DOM y generaba `<p><div>` |

### Fixtures de test desactualizados (hallazgo)

Al correr la suite completa aparecieron **31 tests en rojo** sin relación con
SIREB. Causa: la migración `2026_09_25_180000_add_tipo_tarifa_to_tarifas_campo_table`
agregó la columna `tipo_tarifa` y le quitó el default, pero cinco fixtures
seguían creando tarifas sin indicarla, contra una columna `NOT NULL`.

Se agregó `'tipo_tarifa' => 'diurna'` (el valor que la columna tenía por
defecto antes de la migración) en:

- `backend/tests/Feature/Services/ConfirmacionCobroServiceTest.php`
- `backend/tests/Feature/Jobs/SolicitudesJobsTest.php`
- `backend/tests/Feature/ModelsTest.php`
- `backend/tests/Feature/Api/V1/WebhookRecaudacionesControllerTest.php`
- `backend/tests/Feature/Api/V1/Public/DatosCobroPendienteTest.php`

### Mapeo aplicado en el entorno de test

| Campo local | Nombre local | Servicio SIREB | Precio |
| --- | --- | --- | --- |
| `CD-001` | cancha vieja | `SEDEDE-CS1` · Cancha Sintética N° 1 | Bs. 50 – 100 |
| `FS-001` | Cancha Techada Central | `SEDEDE-CS2` · Cancha Sintética N° 2 | Bs. 50 – 100 |
| `CD-002` | estadio gran mamore | `SEDEDE-EGM-ENTREN` · Estadio Gran Mamoré - Alquiler | Bs. 100 – 400 |
| `CD-005` | piscina | `0005` · H. PISCINA OLIMPICA | Bs. 10 |
| `CD-008` | asdfg (inactivo) | sin equivalente | — |
