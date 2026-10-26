Veredicto en una línea: arranco el tramo admin con el Dashboard (`pages/Dashboard/index.tsx` 221 + `DashboardController.php` 34 + `DashboardService.php` 134 + `DashboardTest.php` 197) y el hallazgo central es de **paridad admin↔público (lente b)**: el funcionario ve “hoy = sábado 24”, el ciudadano vio “25” en el catálogo/comprobante y el reloj real dice 26 → tres “hoy” distintos, que es **B36 (timezone) visto desde la otra capa**, más “Campos activos 7” vs “4 disponibles” del público (B7), y los KPIs que **ocultan las pendientes** justo la cola que el funcionario tiene que desatascar (P6/B46). El único acierto de dinero que **refuerza el fix de B18**: el dashboard dice explícitamente “Monto **confirmado**”, o sea el admin ya distingue `monto_confirmado` y el comprobante público que pinta `monto_total` como “PAGADO” es el que está mal. **No tejí** — y ojo con la ambigüedad de “empecemos”: lo trato como **empezar el análisis de admin (dossier)**, porque para tejer el Dashboard necesito `DashboardService` (134) + `DashboardTest` (197) que no tengo, y tejer sin ellos sería el “ciegas” que evitamos toda la sesión; al final te dejo la puerta abierta a “arranca a corregir el Dashboard” si a eso te referías.

> KB honesta, con la corrección que anuncié el turno pasado: **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié, a diferencia de web‑public), pero en esta captura el Dashboard no introduce imports nuevos visibles; lo vigilo al tejer. **React DevTools** → **SÍ aplica** (admin es de interacción: los 4 KPI y el gráfico de barras son componentes React; si un número no refresca o la barra pinta mal, la extensión es la herramienta natural, no el copy). **`wa.me`** → **NO aplica** al Dashboard (no hay QR/WhatsApp acá; eso es ventanilla, frente siguiente). **SIREB** → aplica (KPI “Monto confirmado” = invariante; piscina mezclada en KPIs binarios = B43/B35).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| “Resumen del día — sábado, 24 de octubre de 2026” | `DashboardService.php` (134) | **B44** timezone/hoy |
| “Campos activos: 7 En el sistema” | `DashboardService.php` (134) + `DashboardTest.php` (197) | **B45** 7 vs 4 (paridad B7) |
| “Reservas hoy: 1 Confirmadas” / “Ingresos Bs 50 Monto confirmado” | `DashboardService.php` (134) | **B46** pendientes ocultas + **acierto** monto_confirmado (refina B18) |
| “Ocupados ahora: 1 / 7” | `DashboardService.php` (134) | **B47** piscina mezclada en binario cancha |
| Gráfico “Distribución de reservas de hoy” (barra 17:00=1) | `DashboardService.php` (134) + `chart-area-interactive.tsx` (291) | **B46/D31** ¿cuenta pendientes? |
| Sidebar con rol `admin_parametricas` viendo TODO el menú | `app-sidebar.tsx` (206) + `RoleMiddleware.php` (59) | **B48** visibilidad vs permiso (lente e / A1/S7) |
| Endpoint que alimenta todo | `DashboardController.php` (34) | params/rol que llama |

## Bugs (numeración continua: público cerró en B43/D28/S24; admin abre en B44)

**B44 🔴 (paridad, sospecha) — tres “hoy” distintos entre capas.** El dashboard dice “sábado, **24** de octubre de 2026”; el catálogo público mostró sync “**25**/10/2026” y el comprobante “**25** de octubre”; el reloj real es **26**/10 (lunes). Si las tres capturas son del mismo día real, el Dashboard calcula “hoy” con un criterio distinto al público → es **B36 (timezone) desde la otra capa**: el `DashboardService` (134) probablemente usa `now()`/`today()` en UTC y Bolivia es UTC‑4, así que el “día” se corre. **No lo afirmo**: puede ser que tomaste el dashboard el 24 y las otras el 25 (fechas de captura distintas, no bug). La distinción la da leer 134/197.
*Mini‑prompt (verificar):* leer `DashboardService.php` (134) → cómo deriva la fecha de “hoy” (¿`Carbon::today()` con tz configurable? ¿`now()` sin tz?). Si es UTC vs `America/La_Paz`, es B44=B36 de raíz (S25: un helper de “hoy” compartido dashboard+público).

**B45 — “Campos activos: 7” vs “4 disponibles” del público (paridad de conteo, lente b).** El funcionario ve 7, el ciudadano 4 (B7). Con línea del árbol: el presenter `visibles()` (CampoController 140) excluye padres‑de‑variantes y `sin_reserva_online`, y el `estado` incluye `mantenimiento`. Entonces el 7 del dashboard **puede ser correcto** (el funcionario ve lo que el público no) **o** puede estar contando campos en `mantenimiento` como “activos” (lente c: mantenimiento no está operativo). No sé cuál sin ver 134.
*Mini‑prompt (verificar):* leer `DashboardService.php` (134) → qué cuenta “Campos activos” (¿`estado=activo` puro? ¿incluye mantenimiento? ¿aplica los mismos filtros que `visibles()`?). El fix no es “igualar a 4”, es **que el label sea honesto** (“7 registrados / N operativos”) y que la diferencia con el público sea por diseño declarado, no por dos queries incompatibles.

**B46 🔴 (operar si SIREB falla, lente d) — los KPIs ocultan las pendientes, que es justo la cola que el funcionario desatasca.** “Reservas hoy: **1** / Confirmadas” y el gráfico muestran **solo confirmadas**. Si hay una solicitud `pendiente` (zombie P6/B17/B22), el funcionario **no la ve** en el dashboard → no sabe que tiene que anular/reintentar. El dashboard es la pantalla de “estado del día”; esconder la cola de pendientes le quita su función operativa. Conecta directo con P6 (cerramos que el `schedule` no corre en dev) y con B22 (card “Pendiente” infinita en el público): el mismo zombie, invisible para el funcionario.
*Mini‑prompt (verificar):* leer `DashboardService.php` (134) + `DashboardTest.php` (197) → ¿el conteo de “Reservas hoy” filtra `estado=confirmada`? Si sí, agregar un desglose o badge de `pendiente`/`expirada` (S27). El gráfico de distribución debería pintar pendientes en otro color (D31) para que el funcionario vea la demanda no pagada.

**B47 (paridad de modelo, lente b) — piscina mezclada en KPIs binarios de cancha.** “Ocupados ahora: **1 / 7**” asume el modelo binario de cancha (una cancha o está ocupada o no). La piscina es por **perfil/cupo** (B43/B35): “ocupada” no es 0/1. Si el dashboard mete la piscina en el mismo denominador “7 campos”, el “1/7” es un número que no significa lo mismo para cancha que para piscina. Conecta con B7 (llamar “canchas” a la piscina) pero en el KPI del funcionario.
*Mini‑prompt (verificar):* leer `DashboardService.php` (134) → ¿“Ocupados ahora” trata cancha y piscina con la misma métrica? Si sí, separar o etiquetar (D30).

**B48 (permisos, lente e / A1/S7) — rol `admin_parametricas` ve el menú completo.** El usuario está como `admin_parametricas` pero el sidebar pinta Dashboard, Ocupación, **Reservas**, Campos, Tipos, **Funcionarios**, **Asignaciones**, Mapa Global, Reportes. La pregunta: ¿un `admin_parametricas` **debería** ver Reservas (ventanilla) y Funcionarios/Asignaciones (usuarios)? Si el sidebar no filtra por rol, el funcionario ve enlaces que quizá el backend le niega → **403 opaco al clicar** (S7 de A1, `RoleMiddleware` 59 nunca llegó). Visibilidad ≠ permiso, y mostrar de más confunde tanto como ocultar de más.
*Mini‑prompt (verificar):* leer `app-sidebar.tsx` (206) → ¿filtra ítems por rol o pinta todo? y `RoleMiddleware.php` (59) → ¿qué rol accede a qué? El fix es alinear sidebar con middleware (S26) y que el 403 sea claro, no opaco.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **“Ingresos hoy: Bs 50,00 / Monto confirmado”** — el dashboard **usa `monto_confirmado` explícitamente**. Esto es evidencia de que el admin ya distingue confirmado vs total, así que **B18/B37** (comprobante público pintando `monto_total` como “PAGADO”) es fix del **front público**, no del back: el back ya tiene el dato correcto. Proteger el label “Monto confirmado”.
- **Coherencia interna del gráfico**: 1 reserva confirmada de Bs 50 a las 17:00 → KPI “Reservas hoy 1” + “Ingresos 50” + barra 17:00=1. Los tres cuadran. Eso es un acierto de consistencia (lente b en positivo) que hay que **no romper** al agregar pendientes (B46): si pinto pendientes, que la barra de confirmadas siga sumando 50, no 100.
- **“Accesos rápidos” (Reportes/Mapa/Ocupación/Reservas)** — throughput del funcionario (lente a): entra y va directo a la operación. Bien.

## Didáctica / operación del funcionario (continuo: público cerró en D28)

**D29 — el dashboard debería alertar la cola de pendientes (cierra B46).** La función #1 de un dashboard de ventanilla es “qué tengo que resolver hoy”; una pendiente atascada (P6) es exactamente eso, y hoy está invisible.
*Mini‑prompt:* ver B46/S27.

**D30 — separar o etiquetar cancha vs piscina en los KPIs (cierra B47).** El funcionario de pileta y el de cancha son operaciones distintas; mezclarlos en “1/7” oculta la realidad de la piscina (cupos por perfil).
*Mini‑prompt:* ver B47.

**D31 — el gráfico de distribución sin pendientes oculta la demanda no pagada.** Ver una barra solo de confirmadas hace pensar “hoy hubo 1 reserva” cuando quizá hubo 3 intentos y 2 no pagaron.
*Mini‑prompt:* pintar pendientes/expiradas en otro tono en `chart-area-interactive.tsx` (291), coherente con la leyenda de estados (S8 paridad cross‑capa).

## Sugerencias IA acotadas (continuo: público cerró en S24)

**S25 — unificar la definición de “hoy” (timezone La Paz) en un solo helper** usado por dashboard **y** público. Cierra **B44 y B36 de raíz** (mismo bug, dos capas): en vez de arreglar el formateo en `ComprobantePiscina.tsx` (190) y el `today()` en `DashboardService.php` (134) por separado, un `hoyBolivia()` compartido.
*Mini‑prompt:* al tejer, crear el helper en `backend` (config `beni.php` 58 ya existe para zona) y consumirlo en ambos lados.

**S26 — sidebar filtrado por rol + 403 claro (cierra B48 / A1/S7).** Que el menú refleje el permiso real y que un acceso negado diga “tu rol no puede”, no un 403 opaco.
*Mini‑prompt:* ver B48.

**S27 — KPIs con desglose confirmado/pendiente/expirado, no solo “confirmadas” (cierra B46/D29).** El número grande puede seguir siendo confirmadas, pero con un secundario “+N pendientes” que es lo operativo.
*Mini‑prompt:* ver B46.

## Lo que NO es bug (para que no persigas fantasmas)

- **“1 reserva / Bs 50 / barra 17:00=1”** internamente coherente = acierto, no bug.
- **“Campos activos 7”** puede ser **correcto** para el funcionario (él ve lo que el público no); el problema no es el 7, es que el label no diga qué incluye (B45).
- **Rol `admin_parametricas` mostrado en el sidebar** = normal; el problema es si ve menús que no le corresponden (B48), no que muestre el rol.
- **Header “GAD Beni” + toggle de tema** = normal de admin interno.
- **“sábado 24 de octubre”** = el día‑semana cuadra con la fecha (24/10/2026 fue sábado); el problema no es el nombre del día, es que “hoy” difiera entre capas (B44).

## La ambigüedad de “empecemos” — te la devuelvo explícita

“Empecemos con este” lo interpreté como **empezar el análisis de admin con esta pantalla** (dossier), coherente con todo el tramo público. Pero si quisiste decir **arranca a corregir el Dashboard ya**, no puedo sin tejer a ciegas: me faltan `DashboardService.php` (134) y `DashboardTest.php` (197), que son los que dicen si B44 es timezone o fecha de captura, si B45/B46/B47 son reales, y si el sidebar filtra por rol (B48). Entonces:

- **Si seguimos dossier**: pasame la siguiente pantalla de admin (te propongo **ventanilla de cancha/piscina** como orden 1, donde viven B13/B43/D13/Fix3) y sigo analizando sin tejer.
- **Si arranco a corregir el Dashboard**: pasame `DashboardService.php` (134) + `DashboardController.php` (34) + `DashboardTest.php` (197) + `app-sidebar.tsx` (206) y tejo B44–B48 con línea, en un commit verde (suite 226 + `npm run build`).

Tú eliges el modo; yo no tejo hasta que confirmes, para no romper el 226 con un fix de dashboard tejido a ciegas.

## Guardado

```text
ADMIN/BACK TRAMO ABIERTO — Dashboard (pages/Dashboard/index.tsx 221 + DashboardController
  34 + DashboardService 134 + DashboardTest 197 + chart-area-interactive 291 +
  app-sidebar 206). Auditoría pegada = LA MISMA (2026-10-25 15:23 / 92797 / 548, cero
  delta), usada como mapa de terreno. Numeración continua (público B1-B43/D1-D28/
  S1-S24 · dashboard B44-B48/D29-D31/S25-S27).
HALLAZGO CENTRAL = PARIDAD admin↔público (lente b): B44🔴 tres "hoy" (dashboard 24 /
  público 25 / real 26) = B36 timezone desde otra capa (SOSPECHA, puede ser fechas de
  captura distintas, no afirmo sin 134/197); B45 "Campos activos 7" vs público "4" (B7)
  — el 7 puede ser correcto pa'l funcionario, el bug es label no honesto (¿incluye
  mantenimiento/padres/variantes?); B46🔴 KPIs ocultan PENDIENTES (zombie P6/B17/B22) =
  la cola que el funcionario desatasca, invisible en su pantalla de estado (lente d);
  B47 piscina mezclada en KPI binario "Ocupados 1/7" (B43/B35); B48 rol
  admin_parametricas ve menú completo = visibilidad vs permiso (lente e / A1/S7 403
  opaco, RoleMiddleware 59 nunca llegó, app-sidebar 206 ¿filtra?).
ACIERTOS PROTEGER: "Monto confirmado" explícito = EVIDENCIA de que el back ya distingue
  monto_confirmado → B18/B37 es fix del FRONT público (usar confirmado como el dashboard),
  NO del back; coherencia interna 1 reserva=Bs50=barra 17:00=1 (no romperla al agregar
  pendientes B46); accesos rápidos = throughput (lente a).
DIDÁCTICA/OPERACIÓN FUNCIONARIO: D29 dashboard debe alertar cola pendientes (B46/S27);
  D30 separar/etiquetar cancha vs piscina (B47); D31 gráfico sin pendientes oculta
  demanda no pagada (chart-area-interactive 291, S8 cross-capa).
SUGERENCIAS: S25 unificar "hoy" (tz La Paz) en helper compartido dashboard+público =
  cierra B44+B36 de raíz (config beni.php 58 ya existe); S26 sidebar filtrado por rol +
  403 claro (B48/A1/S7); S27 KPIs con desglose confirmado/pendiente/expirado (B46/D29).
NO-BUG: 1/Bs50/barra17 coherente=acierto; "7 activos" puede ser correcto pa'l func;
  rol mostrado=normal; header/toggle tema=normal; "sábado 24" día-semana cuadra (el
  problema es el "hoy" entre capas B44, no el nombre del día).
KB HONESTA + CORRECCIÓN ANUNCIADA: Rolldown circularDependency=false SÍ aplica (web-admin
  sí buildié, a dif web-public) pero Dashboard no mete imports nuevos visibles, vigilo al
  tejer; React DevTools SÍ aplica (admin=interacción, KPIs/gráfico son componentes, si no
  refresca o pinta mal es la herramienta, no copy); wa.me NO aplica al Dashboard (QR/
  WhatsApp = ventanilla, frente siguiente); SIREB aplica (KPI monto confirmado=invariante,
  piscina mezclada=B43/B35).
AMBIGÜEDAD "EMPECEMOS" DEVUELTA AL USUARIO: interpretado como "empezar análisis admin
  (dossier)" coherente con tramo público; si quiso "corregir Dashboard ya" NO puedo sin
  tejer ciegas → pido DashboardService 134 + Controller 34 + Test 197 + app-sidebar 206.
  DOS CAMINOS: (a) seguir dossier → próxima = ventanilla cancha+piscina (orden 1, B13/
  B43/D13/Fix3); (b) arrancar a corregir Dashboard → con esos 4 archivos tejo B44-B48
  commit verde (226 + build). NO tejo hasta confirmar modo.
COLA REORDENADA (dueños de archivo admin/back): Fix3 piscina A/B (Turnos 164 +
  PiscinaReservaService 284) · A1 S3 throttle/S7 403/RoleMiddleware 59 (app-sidebar 206 +
  RoleMiddleware 59 + Funcionarios 881, B48 cae acá) · A4 Pago 576 (RecaudacionesApiClient
  415 + webhook 97; análogo admin ventanilla 961) · D2 Bug C TarifaCampo 107 (Tarifas 703 +
  sync 305, crítico x B43/B37) · C1→C2 · gates F1-F4 · E mobile (piscina real 260/145;
  P3-paridad 1073/85; B43/B36/B42/B39+B44 se propagan a móvil; S16 también móvil) ·
  higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote) ·
  reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125; P7 reportes 245/135; P9 asignaciones
  579/22/26; P1/P2 auth 164/34/126; P3 reencuadrada S8; P8 reencuadrada S16; D11 NIT
  prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24.
```

En una línea: empecé el tramo admin con el Dashboard y el hallazgo central es de **paridad admin↔público** — **B44** (tres “hoy” distintos: dashboard 24, público 25, real 26 = B36 timezone visto desde la otra capa, sospecha que no afirmo sin ver `DashboardService` 134), **B45** (“7 campos activos” vs “4 disponibles” del público = B7, donde el 7 puede ser correcto para el funcionario pero el label no es honesto), **B46** (los KPIs ocultan las **pendientes**, justo la cola zombie de P6 que el funcionario tiene que desatascar, lente de “operar si SIREB falla”), **B47** (piscina mezclada en el KPI binario “1/7” = B43/B35) y **B48** (rol `admin_parametricas` viendo el menú completo = visibilidad vs permiso, A1/S7); el único acierto de dinero que **refuerza el fix de B18** es que el dashboard dice “Monto **confirmado**”, probando que el back ya distingue `monto_confirmado` y el que está mal es el comprobante público. **No tejí** y te devuelvo la ambigüedad de “empecemos”: lo tomé como *empezar el análisis de admin* (dossier), pero si quisiste *corregir el Dashboard ya*, necesito `DashboardService.php` (134) + `DashboardController.php` (34) + `DashboardTest.php` (197) + `app-sidebar.tsx` (206) para no tejer a ciegas — decime si sigo dossier (próxima: ventanilla cancha+piscina, orden 1) o arranco a corregir el Dashboard con esos 4 archivos, sin haber tocado nada.
