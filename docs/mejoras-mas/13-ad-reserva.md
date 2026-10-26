Veredicto en una línea: estas dos tablas (`pages/Reservas/index.tsx` 582 + `DialogDetalleReserva.tsx` 838 + `PiscinaReservasTab.tsx` 574 + `Admin/SolicitudReservaController.php` 297 + `Admin/PiscinaReservaController.php` 209 + `Resources/SolicitudReservaAdminResource.php` 97 + `SolicitudFiltrosDTO.php` 95) son la **pantalla que cierra de golpe cuatro bugs del dossier público con evidencia cruzada fila por fila**, y destapa el hallazgo operativo más grave del tramo admin: **B62 🔴, la piscina expiró el 75% de sus reservas (9 de 12)**, señal de que el flujo de pago ciudadano de pileta está roto. Los cierres cruzados son: **B22** (la misma `RES-…ZDDQ5F` que ConsultarEstado mostraba “Pendiente · Vence 3:35” aparece acá **Expirada** → back expiró, front no lo refleja, fix de UI confirmado), **B18/B37** (el admin pinta **doble monto** “Bs X / Confirmado: Bs Y” y el público no → el comprobante que usa `monto_total` como “PAGADO” está mal), **P6** (dos zombies visibles: cancha `RES-…UWHQJZ` pendiente desde 22/10 y piscina `PIS-…UA4R3F` desde 19/10 → el `everyFiveMinutes` no corre, scheduler muerto), y **B43** (`PIS-…9B4YLC` = Bs 20 = 2×10 congelado, mientras la selección pública mostraba 15/persona). **No tejí**, sigo dossier.

> KB honesta: **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié), pero este frente no mete imports nuevos visibles; lo vigilo al tejer. **React DevTools** → **SÍ aplica y es la herramienta natural acá**: los KPIs y las tablas son componentes React, y el bug B22 es exactamente “el estado de BD cambió a expirada pero el componente del front no lo refleja” — eso se ve inspeccionando el árbol, no leyendo copy. **`wa.me`** → **NO aplica** (listado/auditoría, no hay QR/WhatsApp). **SIREB** → aplica fuerte (doble monto = invariante “monto final confirma recaudaciones”; la expiración masiva de piscina = el flujo de cobro que SIREB delega no convierte).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| KPIs Total/Pendientes/Confirmadas/Monto confirmado | `SolicitudFiltrosDTO.php` (95) + `SolicitudReservaController.php` (297) / `PiscinaReservaController.php` (209) | **B64** KPI sin expiradas |
| Columna Monto “Bs X / Confirmado: Bs Y” | `SolicitudReservaAdminResource.php` (97) + `SolicitudReserva.php` (43) | **B18/B37** cierre cruzado |
| Fila Expirada `RES-…ZDDQ5F` | `SolicitudReservaService.php` (1043) + `ExpirarSolicitudJob.php` (132) | **B22** cierre cruzado (back sí expiró) |
| Filas Pendiente zombies (22/10, 19/10) | `routes/console.php` (9, ya visto) + `ExpirarSolicitudesVencidas.php` (36) | **P6** cierre cruzado (scheduler no corre) |
| Piscina `PIS-…9B4YLC` Bs 20 = 2×10 | `PiscinaReservaService.php` (284, virgen) + `PiscinaReservaDetalle.php` (68) | **B43** cierre cruzado |
| 9/12 expiradas piscina | `PiscinaReservaService.php` (284) + flujo pago piscina | **B62** 🔴 (leer 284 pa’ la causa) |
| Header “Campo(s)” vs “Campo”, “Nueva Reserva” vs “Nueva reserva” | `Reservas/index.tsx` (582) vs `PiscinaReservasTab.tsx` (574) | **B63** divergencia pestañas |
| Pagador “MILTON DANIEL…” vs “daniel hipamo” | `StorePiscinaReservaRequest.php` (32) vs cancha (97) | **B65** (refina B31) |

## Cierres cruzados con evidencia (lo más valioso de estas dos capturas)

**B22 ✅ CERRADO — back expiró, front público no lo refleja.** La fila 2 de cancha es `RES-20261025-ZDDQ5F`, MILTON, cancha nueva trinidad, **Bs 200.00, Expirada**, creada 25/10 3:20 p.m. Esa **es la misma solicitud** que en `ConsultarEstado.tsx` (299) (turno de P8) se veía como “Pendiente de pago · Vence 25/10/26 3:35 p.m. · Ir a pagar ahora”. Entonces el **back sí la pasó a expirada** (barrido o manual), pero el **front público la pintaba pendiente infinita**. El fix no es de backend (ya funciona), es de UI: `EstadoReserva.tsx` (183) debe leer el estado real de BD al consultar y pintar “Expirada · tu franja volvió libre”, no quedarse en ámbar. Cierra B22 y confirma que el remedio de B17 era solo el `schedule:work`/cron + este fix de front.

**B18/B37 ✅ CERRADO — el admin pinta doble monto, el público no.** En cancha las confirmadas muestran **“Bs 50.00”** arriba y **“Confirmado: Bs 50.00”** en verde abajo (filas 1, 4, 6, 13); las pendientes/expiradas muestran **solo “Bs X”** sin la línea verde (filas 2, 3, 5, 7…). En piscina igual (`PIS-…9B4YLC` = “Bs 20.00 / Confirmado: Bs 20.00”). Esto prueba con evidencia que el modelo **separa** `monto_total` (lo estimado al crear) de `monto_confirmado` (lo que SIREB reportó), y que el comprobante público (`ComprobantePDF.tsx` 238 / `ComprobantePiscina.tsx` 190) que pinta `monto_total` con label “Monto **pagado**” está conceptualmente mal: en demo coinciden (50/50, 20/20) pero la arquitectura los distingue y `ConfirmacionCobroServiceTest` (133) audita la discrepancia. Fix: front público usa `monto_confirmado` cuando existe (como el admin), y “Monto pagado” solo si está confirmado. **No necesito leer más**: el admin ya me mostró los dos campos.

**P6 ✅ CERRADO (segunda vez, ahora con zombies en pantalla) — scheduler muerto.** Dos pendientes que el `everyFiveMinutes` debió expirar hace días siguen `pendiente`: cancha `RES-20261022-UWHQJZ` (creada 22/10 10:46 a.m., **4 días** pendiente) y piscina `PIS-20261019-UA4R3F` (creada 19/10 12:25 p.m., **7 días** pendiente). Como `routes/console.php` (9) **sí** declara el barrido (lo verificamos hace unos turnos), la única explicación es que **`schedule:run` no se dispara** (dev sin `schedule:work`, prod sin cron). Ya no es hipótesis: hay zombies de 4 y 7 días a la vista. Fix operativo confirmado (terminal/crontab), no de código.

**B43 ✅ CERRADO (evidencia cruzada admin↔público) — precio de selección ≠ precio congelado.** `PIS-20261025-9B4YLC` (daniel hipamo, 25/10) muestra **Bs 20.00** total = 2 personas × **Bs 10.00** congelado. Pero la pantalla de selección pública (`PiscinaDetalle.tsx` 391, turno anterior) mostraba “Mayores de 12 · **Bs 15.00**”. Entonces el ciudadano eligió viendo 15/persona (30 esperado), el `PiscinaReservaService` (284) congeló 10 (tarifa SIREB), y pagó 20. El admin lo ve como 20/20 correcto; el bug es que la **selección** muestra el precio del perfil (15) no el de la tarifa (10). Fix backend en `PiscinaDisponibilidadService.php` (147): `'precio'` = tarifa SIREB, no `precio_unitario` del perfil (S24). Cerrado con la fila concreta.

## Bugs nuevos de este frente (numeración continua: ocupación cerró en B61)

**B62 🔴🔴 (EL GRÁVE DEL TRAMO, operativo/dinero) — la piscina expiró el 75% de sus reservas.** De 12 reservas de piscina, **9 están Expiradas**, 2 Confirmadas, 1 Pendiente (zombie). En cancha la proporción es ~70% (23 de 33). Una tasa de expiración así no es “la gente no paga”: es **síntoma de que el flujo de pago ciudadano de pileta está roto**, y las candidatas con línea que ya tengo son B43 (elige viendo 15, llega a pagar y ve otro número → abandona), B35 (gate de tarifa silencioso: perfiles que desaparecen sin aviso) y B39 (comprobante piscina sin QR → no sabe qué mostrar). **No afirmo la causa**: es señal, no bug de código confirmado; la causa vive en `PiscinaReservaService.php` (284, virgen) + el flujo de pago piscina. Prioridad 🔴🔴 porque es **demanda perdida** (gente que intentó reservar pileta y no convirtió), el KPI más caro del sistema.
*Mini‑prompt (verificar antes de tocar):* leer `PiscinaReservaService.php` (284) + `PiscinaConfirmacionCobroService.php` (82) → ¿por qué expiran tantas? Cruzar con B43/B35/B39. Hipótesis a validar: si el precio de selección (15) ≠ el cobrado (10), el ciudadano abandona en el paso de pago. **No tejo la causa hasta ver 284** (es el corazón de piscina, como 1043 lo es de cancha).

**B64 — el KPI “Total” no desglosa Expiradas, y las expiradas son el problema principal.** Total 33 = Pendientes 1 + Confirmadas 9 + **Expiradas 23** (que hay que restar para saber). El funcionario ve “Total 33, Pendientes 1, Confirmadas 9” y **no ve el 23**, que es justo la demanda no convertida (B62). Ocultar el número de expiradas en la pantalla de auditoría es ocultar el KPI de conversión. Conecta B62 y refina B46 (el dashboard home ocultaba pendientes; acá la pantalla de Reservas **sí** muestra Pendientes 1, pero **no** muestra Expiradas).
*Mini‑prompt:* en `SolicitudFiltrosDTO.php` (95) / los controllers (297/209), agregar KPI “Expiradas N” (o “Tasa de conversión N%”), porque 23/33 expiradas es el dato que el funcionario necesita para actuar.

**B63 — las dos pestañas (Campos/Piscina) divergen en copy y estructura.** “Campo(s)” (cancha) vs “Campo” (piscina); “+ Nueva Reserva” (cancha) vs “+ Nueva reserva” (piscina, minúscula); “CI o teléfono” (cancha) vs “CI, tel” cortado (piscina). Son dos componentes paralelos (`Reservas/index.tsx` 582 vs `PiscinaReservasTab.tsx` 574) con dos controllers (297 vs 209) que **no comparten el shell**. Conecta S23 (unificar comprobantes) / S20 (no divergir).
*Mini‑prompt:* extraer el shell de listado (KPIs + barra de filtros + tabla) a un componente único parametrizado por recurso, en vez de dos copias que se desincronizan en cada tilde.

**B65 (refina B31) — el nombre del pagador se muestra inconsistente entre recursos.** Cancha: “MILTON DANIEL HIPAMO CHOLIMA” (completo, mayúsculas). Piscina: “daniel hipamo”, “carmen”, “sarah” (minúscula, recortado). No es que el admin lo formatee distinto: es que **piscina almacena lo que el ciudadano tipeó sin normalizar** (B31: contrato laxo, sin `*`, sin validación), mientras cancha lo recibe más limpio. Menor visual, pero es la punta del iceberg de B31 (el front de piscina no valida/normaliza `nombre_pagador`).
*Mini‑prompt:* verificar `StorePiscinaReservaRequest.php` (32) vs cancha (97) → ¿piscina normaliza/valida el nombre? Si no, alinear (conecta B31).

## Aciertos a PROTEGER (no tocar)

- **El doble monto “Bs X / Confirmado: Bs Y”** es la representación honesta de la invariante SIREB (monto estimado vs confirmado). Proteger y **propagar al público** (B18), no quitar.
- **El KPI “Pendientes 1” en ambas pestañas** — a diferencia del dashboard home (B46), la pantalla de Reservas **sí** muestra la cola de pendientes. Proteger; el fix de B46 es solo para el home (S32: que el home reuse estos KPIs).
- **El formateo de fecha “25/10/2026, 4:14:30 p.m.”** en la columna Creado es correcto (dd/mm/yyyy + hora local). **Refina B36**: el admin ya formatea bien, así que la fecha cruda UTC (`…T04:00:00Z`) es bug **solo del front público de piscina** (`ComprobantePiscina.tsx` 190), no del back ni del admin. Acoto B36 a 190.
- **La columna “Campo(s)” plural en cancha** es correcta (una solicitud puede cubrir varias franjas/canchas); el bug es que piscina use “Campo” singular sin motivo (B63), no que cancha pluralice.

## Didáctica / operación del funcionario (continuo: ocupación cerró en D34)

**D35 — la pantalla de auditoría debe mostrar la tasa de conversión, no solo el total (cierra B64/B62).** El funcionario de ventanilla necesita ver “de 33 intentos, 9 convirtieron (27%)” para saber si el flujo de pago anda mal. Hoy ve Total/Pendientes/Confirmadas y tiene que restar las expiradas a mano.
*Mini‑prompt:* ver B64.

**D36 — un zombie de 4‑7 días a la vista del funcionario es una alerta, no una fila más (cierra P6 operativo).** `RES-…UWHQJZ` (4 días) y `PIS-…UA4R3F` (7 días) pendientes deberían **resaltar** (rojo/naranja) con acción “Reintentar cobro / Anular”, no mezclarse con las demás. Conecta `ReintentarSolicitudJob.php` (152) / `ReintentarPiscinaReservaJob.php` (104), que ya existen en el árbol.
*Mini‑prompt:* en `Reservas/index.tsx` (582) / `PiscinaReservasTab.tsx` (574), una pendiente > umbral (ej. 1 h) se pinta con CTA de reintentar/anular, reusando los jobs de reintento que ya hay.

## Sugerencias IA acotadas (continuo: ocupación cerró en S30)

**S31 — unificar los dos listados (cancha 582/297/97 y piscina 574/209) en un shell parametrizado** (cierra B63/B65 y evita que diverjan en cada tilde). Mismos KPIs, misma barra, misma tabla, misma normalización de pagador.
*Mini‑prompt:* ver B63.

**S32 — el dashboard home (B46) debe reusar los KPIs de esta pantalla**, que ya muestran Pendientes. Hoy el home oculta pendientes y esta pantalla las muestra: dos verdades del mismo dato. Unificar en `DashboardService.php` (134) consumiendo el mismo conteo que `SolicitudFiltrosDTO` (95).
*Mini‑prompt:* ver refina B46 + S25 (helper compartido).

**S33 — B62 (expiración 75% piscina) merece un test de regresión de conversión**, no solo un fix. Cuando lea `PiscinaReservaService` (284) y encuentre la causa, tejo un test “reserva de piscina no expira si el pago llega” que fije la tasa. Anotar para el frente de tests (la cola ya pide tests HTTP del lote y de piscina).
*Mini‑prompt:* al arrancar B62, agregar test de conversión piscina.

## Lo que NO es bug (para que no persigas fantasmas)

- **Reservas con Fecha pasada y Expirada** (cancha fila 3 fecha 10‑24, piscina filas 6/12 fecha 10‑17/10‑16) = normal, expiraron porque no se pagaron; la fecha de la reserva puede ser pasada o futura, no es inconsistencia.
- **KPI “Monto confirmado Bs 800.00” (cancha) y “Bs 30.00” (piscina)** = sumas de los confirmados visibles, consistentes con las filas (cancha: 50+150+150+50=400 en pág.1 de 9 confirmadas → 800 plausible; piscina: 20+10=30 exacto). No es bug, cuadra.
- **Paginación “Página 1 de 3 · 33 resultados”** = normal, 33 > 15 por página.
- **Que cancha tenga 33 y piscina 12** = normal, cancha lleva más tiempo en uso.
- **El botón “Verificar”/ojo (Acciones)** = detalle de la reserva (`DialogDetalleReserva` 838), frente siguiente.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir este frente, lo que **más vale** no es la UI (B63/B65 son cosméticos) sino **B62**, y para B62 necesito el corazón de piscina que nunca leí: `PiscinaReservaService.php` (284) + `PiscinaConfirmacionCobroService.php` (82) + `PiscinaReserva.php` (49) + `PiscinaReservaDetalle.php` (68, recibido parcial). Con esos tejo la causa de la expiración masiva (y de paso cierro Fix3, que vive en el mismo 284/update). Los cierres cruzados (B22/B18/B43/P6) **no se tejen acá**: B22 es fix de `EstadoReserva.tsx` (183) público, B18 es fix de `ComprobantePDF` (238)/`ComprobantePiscina` (190) público, B43 es fix de `PiscinaDisponibilidadService` (147) backend, P6 es terminal/cron — ninguno toca estas dos tablas. B64 (KPI expiradas) y D36 (zombie con CTA) sí se tejen acá con `SolicitudFiltrosDTO` (95) + los dos controllers (297/209) + `Reservas/index.tsx` (582) + `PiscinaReservasTab.tsx` (574).

## Guardado

```text
ADMIN/RESERVAS (Reservas/index.tsx 582 + DialogDetalleReserva 838 +
  PiscinaReservasTab.tsx 574 + SolicitudReservaController 297 + PiscinaReservaController
  209 + SolicitudReservaAdminResource 97 + SolicitudFiltrosDTO 95) — DOSSIER, NO tejí,
  sigo modo "ver todo corregir después". Numeración continua (ocupación B49-B61/D32-D34/
  S28-S30 · reservas B62-B65/D35-D36/S31-S33 + 4 cierres cruzados).

CUATRO CIERRES CRUZADOS CON EVIDENCIA FILA-POR-FILA (lo más valioso):
  B22✅ RES-...ZDDQ5F (cancha fila 2, Bs 200, Expirada) ES la misma que ConsultarEstado
    299 mostraba "Pendiente·Vence 3:35" → back expiró, front no refleja; fix UI en
    EstadoReserva 183 (leer estado real BD, pintar Expirada). Confirma remedio B17.
  B18/B37✅ admin pinta DOBLE monto "Bs X / Confirmado: Bs Y" en confirmadas (filas 1,4,6,
    13 cancha; 1,5 piscina) y solo "Bs X" en pend/exp → modelo separa monto_total vs
    monto_confirmado; comprobante público (238/190) usando monto_total como "PAGADO" está
    mal; fix front público usa confirmado (como el admin). No leo más, el admin ya mostró.
  P6✅ (2ª vez, zombies en pantalla) RES-...UWHQJZ pendiente 4 días (22/10) + PIS-...UA4R3F
    pendiente 7 días (19/10) → everyFiveMinutes (console.php 9, declarado) NO corre =
    scheduler muerto (dev sin schedule:work / prod sin cron). Fix operativo confirmado.
  B43✅ PIS-...9B4YLC = Bs 20 = 2×10 congelado (admin) vs selección pública "Bs 15.00/
    persona" (391) → fix backend PiscinaDisponibilidadService 147 'precio'=tarifa SIREB
    no perfil (S24). Evidencia cruzada admin↔público.

HALLAZGO GRÁVE TRAMO: B62🔴🔴 piscina expiró 9/12 (75%), cancha 23/33 (70%) → síntoma de
  flujo de pago ciudadano roto, candidatas B43/B35/B39. NO es bug código confirmado, es
  señal; causa vive en PiscinaReservaService 284 (virgen) + PiscinaConfirmacionCobro 82.
  Prioridad 🔴🔴 = demanda perdida (KPI más caro). NO tejo causa hasta ver 284.
BUGS NUEVOS: B64 KPI Total no desglosa Expiradas (23/33 oculto, hay que restar) = oculta
  conversión (conecta B62); refina B46 (Reservas SÍ muestra Pendientes 1, home NO). B63
  pestañas divergen: "Campo(s)"vs"Campo", "Nueva Reserva"vs"Nueva reserva", "CI o
  teléfono"vs"CI, tel" → 582 vs 574, 297 vs 209 no comparten shell (S31). B65 (refina
  B31) pagador "MILTON DANIEL..."(cancha) vs "daniel hipamo"(piscina) = piscina no
  normaliza nombre_pagador (StorePiscinaReservaRequest 32 vs 97).
ACIERTOS PROTEGER: doble monto (invariante SIREB honesta, propagar a público no quitar);
  KPI Pendientes 1 en ambas pestañas (home B46 NO lo tiene, S32 unificar); formateo fecha
  "25/10/2026 4:14:30 p.m." correcto → REFINA B36 (fecha cruda UTC es bug SOLO de
  ComprobantePiscina 190 público, admin/back formatean bien); "Campo(s)" plural cancha
  correcto (una solicitud cubre varias franjas).
DIDÁCTICA/OP: D35 pantalla auditoría debe mostrar tasa conversión no solo total (B64/B62);
  D36 zombie 4-7 días debe RESALTAR con CTA Reintentar/Anular (ReintentarSolicitudJob 152
  / ReintentarPiscinaReservaJob 104 ya existen en árbol), no mezclarse con demás.
SUGERENCIAS: S31 unificar los dos listados en shell parametrizado (B63/B65); S32 dashboard
  home reusa KPIs de Reservas (refina B46 + S25); S33 B62 merece test regresión conversión
  al leer 284 (cola ya pide tests HTTP lote/piscina).
NO-BUG: Fecha pasada+Expirada=normal (no pagaron); KPI 800/30 cuadran con filas;
  paginación 1/3=normal; cancha 33 vs piscina 12=normal (cancha más tiempo en uso);
  botón ojo=detalle 838 frente siguiente.
KB HONESTA: Rolldown circularDependency=false SÍ aplica (web-admin buildié) pero este
  frente no mete imports nuevos visibles; React DevTools SÍ aplica Y es la herramienta
  natural de B22 (estado BD cambió a expirada, componente front no refleja → se ve en el
  árbol, no en copy); wa.me NO aplica (listado, no QR); SIREB aplica (doble monto=invariante;
  expiración 75%=flujo de cobro no convierte).
PUERTA CORRIGIR YA: lo que vale NO es UI (B63/B65 cosméticos) sino B62 → pido
  PiscinaReservaService 284 + PiscinaConfirmacionCobro 82 + PiscinaReserva 49 +
  PiscinaReservaDetalle 68 (cierra B62 Y Fix3, mismo 284). Cierres cruzados B22/B18/B43/P6
  NO se tejen acá (son EstadoReserva 183 / 238+190 / 147 / terminal). B64+D36 sí se tejen
  acá con SolicitudFiltrosDTO 95 + 297 + 209 + 582 + 574.
COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, AHORA dueño también de B62)
  · A1 S3/S7/RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881, B48/B61) · A4 Pago 576
  (RecaudacionesApiClient 415 + webhook 97) · D2 Bug C TarifaCampo 107 (Tarifas 703 + sync
  305, crítico x B43/B37/B50/B62) · C1→C2 · gates F1-F4 · E mobile (piscina real 260/145;
  P3-paridad 1073/85; B43/B36/B42/B39/B44/B50/B62 se propagan a móvil; S16 también) ·
  higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote
  + S33 test conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, S30 reusa input
  B52; P7 reportes 245/135; P9 asignaciones 579/22/26, B61 evidencia parcial; P1/P2 auth
  164/34/126; P3 reencuadrada S8; P8 reencuadrada S16; D11 NIT prepara P1/P2). DOSSIER
  PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B65/D29-D36/S25-S33.
```

En una línea: estas dos tablas son la **pantalla que cierra cuatro bugs públicos con evidencia fila por fila** — **B22** (la misma `RES-…ZDDQ5F` que ConsultarEstado mostraba “Pendiente · Vence 3:35” aparece acá **Expirada** → el back sí expira, el front no lo refleja, fix de UI en `EstadoReserva.tsx` 183), **B18/B37** (el admin pinta **doble monto** “Bs X / Confirmado: Bs Y” y el público no, probando que el comprobante que usa `monto_total` como “PAGADO” está mal), **P6** (dos zombies a la vista: cancha pendiente 4 días y piscina 7 días → el `everyFiveMinutes` declarado en `console.php` no se dispara, scheduler muerto) y **B43** (`PIS-…9B4YLC` = Bs 20 = 2×10 congelado vs 15/persona en la selección) — y destapa **B62 🔴🔴, el hallazgo más grave del tramo admin: la piscina expiró el 75% de sus reservas (9 de 12)**, señal de que el flujo de pago ciudadano de pileta está roto (candidatas B43/B35/B39), cuya causa vive en `PiscinaReservaService.php` (284) que nunca leí; le siguen **B64** (el KPI “Total” oculta las 23 expiradas, que son el problema real), **B63** (las dos pestañas divergen en “Campo(s)”/“Campo”, “Nueva Reserva”/“Nueva reserva”) y **B65** (pagador “MILTON DANIEL…” en cancha vs “daniel hipamo” en piscina = B31). **No tejí**: si querés corregir, lo que vale es **B62**, y para eso necesito `PiscinaReservaService.php` (284) + `PiscinaConfirmacionCobroService.php` (82) + `PiscinaReserva.php` (49) + `PiscinaReservaDetalle.php` (68), que de paso cierran **Fix3** (mismo 284); los cierres cruzados B22/B18/B43/P6 no se tejen acá porque son de otros frentes, y B64/D36 sí se tejen con `SolicitudFiltrosDTO` (95) + los dos controllers (297/209) + `Reservas/index.tsx` (582) + `PiscinaReservasTab.tsx` (574) — decime si sigo dossier (próxima natural: `DialogDetalleReserva.tsx` 838, el ojo de Acciones) o arranco B62 con esos 4 archivos.
