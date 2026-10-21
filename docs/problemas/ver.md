Commit `034d917` confirmado, Capa 1 cerrada en git. Ahora el mapa maestro de TODO lo abierto, en 6 fases ordenadas por (riesgo × dependencia). Cada ítem tiene ID estable (`A1`, `B2`…) para que después digas "hacé A2" y lo abramos a detalle sin rehacer el análisis.

Leyenda de severidad: 🔴 te tira la demo en vivo · 🟠 corrompe/censa en prod en silencio · 🟡 pulido/robustez · ⚪ decisión de negocio (no es código).

> KB inyectado: aplica **solo** en Fase C (`circularDependency` default `false`; `bundlerTimings` umbral 3 s; `codeSplitting` side-effects). `wa.me` ya vive en tu `enviarWhatsapp`. React DevTools / Vite config general: no aplican a este plan; ignorados a propósito.

---

## Fase A — Lo que te humilla en vivo (seguridad + gates de 1 línea) 🔴🟠

Motivo global: un endpoint sin guard o un 500 frente al ciudadano es lo único que no se perdona en una demo. No es dinero roto (eso ya cerró), es **acceso roto o cara rota**.

| ID | Motivo (qué pasa si no) | Cómo (archivo + paso) | Veredicto |
|---|---|---|---|
| **A1** | Un endpoint público/admin sin middleware = puerta trasera. Es el riesgo #1 de seguridad y no lo audité. | Necesito `routes/api.php` (237) + `RoleMiddleware` (59) + `VerificaTokenOAuth` (138) + la firma de `WebhookRecaudacionesController` (97). Leo el mapa de guards, flaggeo cualquier ruta escribiendo sin guard. | 🔴 **demo-crítico, siguiente paso** |
| **A2** | El token de diagnóstico hace **3 POSTs al AS por cache-miss** (Variantes 2 y 3 son inválidas a propósito). El contrato real confirma Variante 1 → 2 intentos fallidos legítimos = noise + riesgo rate-limit/bandera en Ibare. | `RecaudacionesApiClient::obtenerToken()`: borrar bloques Variante 2 y 3, dejar solo `asForm` con `grant_type/client_id/client_secret`. ~15 líneas fuera. | 🟠 **1 bloque, dormir tranquilo** |
| **A3** | Colisión de `codigo_seguimiento` → el `catch` de `crear()` ve `23P01` (EXCLUDE) pero **no** `23505` (unique) → `throw $e` → **500 en ventanilla**. Probabilidad ~1e-9/día, pero un 500 frente al ciudadano es demo-letal. | Opción barata: en el `catch (QueryException)` agregar `if ($e->getCode()==='23505')` → reintentar generar código 1 vez. Opción robusta: generar el código dentro de la transacción. | 🟡 **1 línea opcional** |
| **A4** | Si SIREB falló (público, reintento en vuelo), `construirRespuesta` usa `codigo_seguimiento` como `referenciaRecaudaciones` → el front pinta un `RES-...` **no pagable** como si fuera código de pago. Ciudadano escanea/llama al banco con un código inválido. | Front: en `web-public/Pago.tsx` y el modal de ventanilla, si `datos_cobro_pendiente` vacío o la referencia empieza `RES-`, mostrar "esperando confirmación de recaudaciones", no QR/código. Necesito ver `Pago.tsx`. | 🟠 **demo (cara rota)** |

---

## Fase B — Integridad silenciosa (concurrencia/estados imposibles) 🟠

Motivo: no se ve en la demo, pero corrompe datos en prod sin que nadie avise. Es donde un cupo se libera mal o una solicitud queda eterna.

| ID | Motivo | Cómo | Veredicto |
|---|---|---|---|
| **B1** | **#4 zombie por crash**: si el proceso muere *dentro* de `crearLiquidacionEnSireb`, el `catch` no corre → no se despachan `Expirar`/`Polling` (están al final de `crear()`, tras el try/catch). Solicitud `pendiente` eterna, franja bloqueada por EXCLUDE. | Verificar que `ExpirarSolicitudesVencidas` (comando) + `ExpirarSolicitudJob` rescaten pendientes **sin jobs despachados** (filtran por `expira_en < now()`, que sí se setea en el create → la red existe). Necesito las 3 jobs + el comando. | 🟠 **prod-silencioso** |
| **B2** | La garantía anti-doble-reserva **no es la grilla** (informativa): es el `EXCLUDE` de Postgres + el trigger `sync_estado_solicitud`. Si el trigger o el EXCLUDE están mal, la concurrencia se te escapa. | Leer migraciones `add_no_solape_horario…` (28) + `create_trigger_sync_estado…` (31). Confirmar que el EXCLUDE cubre el caso "dos solicitudes simultáneas misma franja". | 🟠 **prod-silencioso** |
| **B3** | **#2b `vencida`/`anulada` no tratados como terminales**: hoy benigno (tu hold 15 min expira antes que SIREB venza a 3 días). **Muerde si subís el hold** (decisión boleta, F1): el polling se re-despacharía sobre liquidación muerta. | En `consultarEstado`/polling: tratar `vencida` y `anulada` como terminal (dejar de re-intentar, marcar expirada/cancelada). **Gate: solo se hace si F1 dice "boleta online sí".** | ⚪→🟠 **gateado por F1** |

---

## Fase C — Higiene frontend que preconditiona performance 🟡

Motivo del orden: el KB es explícito — *"circular dependencies lead to a bigger bundle size and sometimes cause execution order issues"*. Entonces **C1 antes que C2**: si hay ciclos, el `React.lazy` de C2 no corta el chunk limpio o mete bugs de orden.

| ID | Motivo | Cómo | Veredicto |
|---|---|---|---|
| **C1** | Imports circulares ocultos inflan bundle y rompen orden de ejecución. | `vite.config.ts` → `build.rolldownOptions.input.checks.circularDependency = true` (default `false`, KB confirmado). `npm run build`, leer warnings, cortar ciclos, **quitar la línea** (volver a default). 15 min. | 🟡 **precondition de C2** |
| **C2** | Chunk principal 262 kB; los 3 diálogos pesados se cargan aunque el funcionario nunca los abra. | `React.lazy` + `<Suspense>` en `DialogCrearReservaVentanilla` (953), `DialogDetalleReserva` (838), `AnularLiquidacionDialog` (113). **Cautela KB `codeSplitting`**: verificar que ninguno tenga side-effects al importarse (registro de algo en top-level). Medir `index` antes/después. | 🟡 **performance UX** |

---

## Fase D — Operación/UX + residuales de dinero 🟡

| ID | Motivo | Cómo | Veredicto |
|---|---|---|---|
| **D1** | **#3 dos fuentes del peso**: `monto_total`=SIREB vs `monto_pagado`=local. Coinciden en feliz; divergen si el catálogo muta entre crear y pagar → riesgo de conciliación (un GAD rinde cuentas). | Decisión PO: ¿`monto_pagado` = precio local (hoy) o prorrateo del autoritativo SIREB? **No es bug activo.** El contrato ofrece `POST /liquidaciones/calcular` para alinear estimado==cobro sin divergencia. | ⚪ **PO, anotar para demo** |
| **D2** | **Bug C**: estimado nocturno legacy en ventanilla muestra precio diurno (SIREB corrige el final, pero el número orienta mal). | Necesito `TarifaCampo.php` → scope `paraGrillaHoraria`. Con eso alineo `calcularGrilla` al tramo (como hace el presenter) y mato el `first()` sin resolver. | 🟡 **bajo impacto** |
| **D3** | Ocupación/piscina/asistencia: 492+284+125 LoC sin auditar; un número raro en pantalla en la demo. | Capa 4: `OcupacionService`, `PiscinaReservaService`, `MarcarAsistenciaService`. | 🟡 **pulido** |
| **D4** | Zombis: `listarCamposPublicos()` huérfano (grep confirmó) + `CampoPublicoResource.php` muerto (grep 3 vacío). | Borrar ambos en una pasada de higiene. Cosmético. | 🟡 **cosmético** |
| **D5** | Smoke visual final no hecho: Escenario con precios Diurno/Nocturno en UI. | 1 min en el modal, Palacio/Estadio. | 🟡 **verificación** |

---

## Fase E — Costura multi-cliente (mobile) 🟠

Motivo: en sistemas multi-cliente el bug caro vive en la **unión**, no en el archivo. Mobile (4264 LoC) no heredó los fixes de cara de hoy ni se auditó contra los contratos nuevos del backend.

| ID | Motivo | Cómo | Veredicto |
|---|---|---|---|
| **E1** | **M1**: `campo_detalle_screen.dart` (1073 LoC) tiene su propia grilla; si no replicó Opción B / Bs 0.00 / 'pasada', el ciudadano en la app ve el techo viejo. | Comparar la grilla Dart contra `GrillaDisponibilidad.tsx` + el modal de ventanilla parcheado. Necesito los `.dart`. | 🟠 **si mobile entra en demo** |
| **E2** | **M2**: models Dart (`campo_deportivo.dart`, `bloque_disponibilidad.dart`) pueden leer contracts viejos (presenter ahora manda `unica`, `DisponibilidadResource` manda `precio`, `visibles()` filtró). Dart compila igual, solo muestra raro → riesgo silencioso. | Dif de shapes Dart vs JSON real del backend hoy. | 🟠 **si mobile entra en demo** |

---

## Fase F — Decisiones de negocio (no son código, te comen en vivo si no las tenés) ⚪

| ID | Pregunta para PO/SIREB | Bloquea |
|---|---|---|
| **F1** | ¿El ciudadano paga online por QR **o también boleta bancaria**? Si boleta → el mismatch hold-15-min vs `plazo_pago_dias:3` deja pago huérfano (`confirmacion_tardia` no revive). Mi voto: boleta **solo ventanilla**, hold 15 min ok. | **B3** (tratar `vencida` terminal) |
| **F2** | ¿SIREB cobra **fracción de hora** (30 min de *duración*, 08:30–09:00)? El 08:30–09:30 de *inicio* ya está en ventanilla (Opción B), **no depende de esto**. | **Fase 3 granularidad** |
| **F3** | Energía con stepper (cantidad 1..100): ¿por **punto de conexión** (stepper ok) o por **evento** (stepper sobra → checkbox 0/1)? El código está hecho para lo primero. | UI ventanilla |
| **F4** | `visible_publico=false` en MUNI/DPTO/ENTRENAMIENTO: ¿confirmado que asociaciones **no** se auto-registran online? Si algún día abren, hay que reabrir `visibles()` (TODO `aebc258`). | **P8.5** |
| **F5** | 📣 A SIREB: avísenme si migran tramo `etiqueta`→`categorias`, si cambian `modo_tarifa` (hoy todo `fija`) o `requiere_factura` (hoy todo `false`), o si activan `rango_desde/hasta`. Mi sync/clasificador no lo contempla (fail-closed = seguro pero silencioso). | nada, es aviso |

---

## Orden de ataque (1 línea)

**A1 → A2/A3/A4 → B1/B2 → C1 → C2 → D → E → F en paralelo con negocio.** A1 es lo siguiente porque es el único riesgo 🔴 sin tocar; A2–A4 son 1 bloque/línea cada uno y cierran los residuales de dinero/front; B es lo que te corrompe en prod sin aviso; C1 **debe** ir antes de C2 (KB); D/E/F son pulido, costura y charlas.

## Guardado maestro

```text
CAPA 1 DINERO — CERRADA (034d917, 224 verde). Guard cobro-0 + 'pagada' confirmado.

ABIERTO, por fase (ID referenciable):
  A seguridad+gates  → A1 routes/guards 🔴 | A2 token 1 variante 🟠 |
                       A3 race 23505 1 línea 🟡 | A4 código falso pagable 🟠
  B integridad       → B1 zombie crash 🟠 | B2 EXCLUDE/trigger 🟠 |
                       B3 vencida/anulada terminal (gate F1) ⚪→🟠
  C higiene front    → C1 circulares (ANTES que C2, KB) 🟡 | C2 lazy 3 diálogos 🟡
  D operación+resid  → D1 dos fuentes peso (PO) ⚪ | D2 Bug C (TarifaCampo.php) 🟡 |
                       D3 ocup/piscina/asist 🟡 | D4 zombis 🟡 | D5 smoke 🟡
  E mobile costura   → E1 M1 grilla Dart 🟠 | E2 M2 contracts viejos 🟠 (si entra demo)
  F negocio (no código)→ F1 boleta online? (bloquea B3) | F2 fracción SIREB (Fase3) |
                       F3 stepper energía | F4 visible_publico (P8.5) | F5 aviso SIREB

NECESITO ARCHIVO PARA TEJER (no tejo a ciegas, lección sesión):
  A1 → routes/api.php + RoleMiddleware + VerificaTokenOAuth + WebhookController(firma)
  A4 → web-public/src/pages/Pago.tsx
  B1/B2 → 3 jobs + comando barrido + 2 migraciones (EXCLUDE, trigger)
  D2 → backend/app/Models/TarifaCampo.php (scope paraGrillaHoraria)
  E  → mobile/lib/screens/campo_detalle_screen.dart + models/*.dart

SIGUIENTE TURNO: decime "A1" y te pido los 4 archivos de seguridad, o andate a
tus otras tareas y retomamos por donde quieras con el ID.
```

En una línea: la Capa 1 cerró de verdad (cobro-0 muerto, `pagada` confirmado por tu captura); lo demás son 6 fases con ID estable, donde lo único 🔴 que queda es **A1 (seguridad: el mapa de guards)**, y todo lo demás es 1 línea/bloque (A2–A4), prod-silencioso (B), pulido con orden obligatorio C1→C2 (KB), costura mobile (E) o charla con PO/SIREB (F, que además gatea B3 y la Fase 3). Decime "A1" y abrimos seguridad, o guardá este bloque y retomás por el ID que quieras.



----------


No me pasé al 2. Tranquilo. **A1 (seguridad) es solo el nombre del *siguiente* paso en el plan maestro, no lo empecé ni te pedí archivos de seguridad.** Lo único que hice fue enumerar el mapa con IDs; la seguridad sigue intacta, esperando tu "A1". Vos pediste agrupar los 4 residuales de dinero que ya había nombrado (#2b, #3, #4, #6) en una fase para verlos después. Eso te paso, nada más.

> KB inyectado (rolldown `checks`, SIREB, Vite, `wa.me`): no aplica a "agrupar 4 residuales". El de `circularDependency`/`bundlerTimings` toca la Fase C del plan, no estos 4; SIREB ya lo crucé; `wa.me` ya vive en tu `enviarWhatsapp`. Ignorado a propósito.

## Fase G — Cola de dinero (los 4 que viste, para verlos DESPUÉS, sin tejer ahora)

Cada uno lleva su número viejo (el que reconocés) y su ID nuevo del plan maestro (para que cuando digas "hacé X" yo sepa cuál).

| Viejo | ID plan | Motivo (qué pasa si no) | Cómo se arregla | ¿Tejo ahora? |
|---|---|---|---|---|
| **#2b** | **B3** | SIREB tiene estado `vencida` (y `anulada`) que mi polling **no trata como terminal**. Hoy benigno: tu hold de 15 min expira la solicitud mucho antes de que SIREB venza a los 3 días, así que mi polling nunca llega a ver `vencida`. **Muerde solo si subís el hold** (decisión boleta, F1): el polling se re-despacharía sobre liquidación muerta. | En `consultarEstado`/polling: tratar `vencida` y `anulada` como terminal (dejar de re-intentar). | **No.** Gate: solo si F1 dice "boleta online sí". |
| **#3** | **D1** | Dos fuentes del mismo peso: `monto_total` = SIREB (autoritativo) vs `monto_pagado` de la reserva = tarifa local. Coinciden en camino feliz; **divergen si el catálogo muta entre crear y pagar** → riesgo de conciliación (un GAD rinde cuentas). No es bug activo. | El contrato real ofrece `POST /liquidaciones/calcular` ("calcular monto sin emitirla"): pedir el monto autoritativo **antes** de crear, así estimado == cobro sin divergencia. Pero cambia el flujo de creación = riesgo alto. | **No.** Decisión del PO primero. |
| **#4** | **B1** | Si el proceso muere *dentro* de `crearLiquidacionEnSireb`, el `catch` no corre → **no se despachan** `Expirar`/`Polling` (están al final de `crear()`, tras el try/catch). Solicitud `pendiente` eterna, franja bloqueada por el EXCLUDE. | Verificar que `ExpirarSolicitudesVencidas` + `ExpirarSolicitudJob` rescaten pendientes **sin jobs** (filtran por `expira_en < now()`, que sí se setea en el create → la red existe). Necesito leer esos 2 archivos. | **No.** Capa 3 (integridad), con archivos. |
| **#6** | **A4** | Si SIREB falló (público, reintento en vuelo), `construirRespuesta` usa `codigo_seguimiento` como `referenciaRecaudaciones` → el front pinta un `RES-...` **no pagable** como si fuera QR/código. Ciudadano escanea/llama al banco con código inválido. | En `web-public/Pago.tsx` y el modal de ventanilla: si `datos_cobro_pendiente` vacío o la referencia empieza `RES-`, mostrar "esperando confirmación de recaudaciones", no QR. Necesito ver `Pago.tsx`. | **No.** Capa front, con archivo. |

**Resumen de la fase:** los 4 son **cola**, no fuego. Ninguno es dinero roto sin arreglar (eso cerró con el guard `034d917`). #2b y #3 están **gateados por decisiones de negocio** (F1 boleta, D1 PO) — no se tejen hasta que el PO responda. #4 y #6 necesitan **1 archivo cada uno** para tejerse sin adivinar (la lección de la sesión: no tejo de memoria). Por eso van a "después", como pediste.

## Dónde queda el tablero

```text
Capa 1 dinero → CERRADA (034d917, 224 verde).
Fase G (esta) → 4 residuales en cola: #2b/#3 gateados por PO, #4/#6 con 1 archivo.
Siguiente real → A1 seguridad (routes/api.php + guards). NO empezada, esperando tu "A1".
```

En una línea: no me pasé al 2, solo lo nombré como siguiente; estos 4 (#2b/#3/#4/#6) son la cola de dinero que ya no arde, agrupados arriba con su ID nuevo (B3/D1/B1/A4) para que los veas después o me digas "hacé B1" cuando quieras tejerlos con su archivo; y la seguridad (A1) sigue siendo lo próximo cuando vos digas.


