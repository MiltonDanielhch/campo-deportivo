Veredicto en una línea: estas dos son `carrito_resumen_screen.dart` (132) y `datos_solicitante_screen.dart` (174) — el flujo de reserva de cancha en el móvil — y son **exactamente la captura donde la desactualización muerde de verdad**, cumpliendo lo que dije el turno pasado (“el listado/detalle estaban limpios/adelante; la brecha la mido en dinero/formulario/zombie”): destapo **B160 🔴🔴, el contrato de campos está INVERTIDO** — el móvil pone “Teléfono / celular *” (obligatorio) y “CI / NIT (opcional)” (opcional), mientras la ventanilla admin (`DialogCrearReservaVentanilla.tsx` 961 / `DialogCrearReservaPiscinaVentanilla.tsx` 489) pone lo contrario (“CI / NIT *” obligatorio, “Teléfono (opcional)”) — y como **CI/NIT es invariante SIREB** (identifica al pagador en la boleta del Banco Unión), un móvil que lo deja opcional puede confirmar reserva **sin identificador** → boleta SIREB inválida o rechazo en banco; esto **autocorrige mi acotamiento de hace varios turnos** (B13/B31 no eran “solo público”, son **público + móvil**, y el canónico es la ventanilla). Le siguen **B154 🔴 / B162 🔴** (la invariante SIREB está declarada en 3 pantallas del mismo flujo móvil con 3 wordings distintos — “lo confirma el sistema al reservar” / “Bs 100.00 estimado” / footer “SIREB” — y **ninguna dice SIREB en el punto de confirmación**, que es justo donde más importa), **B153 🔴** (el carrito **pierde la etiqueta diurno/nocturna** que el detalle sí mostraba con sol/bombita: usa ícono calendario‑check genérico para ambas franjas), y **B161** (“2 franja(s)” = i18n crudo no pluralizado visible al ciudadano). **B165** (¿el móvil salta medio de pago / countdown?) queda **abierto honestamente** hasta ver `pago_qr_screen` (343). **No tejí**, sigo dossier.

> KB honesta de esta captura, con **autocorrección** y **límite de dispositivo**: **AUTOCORRECCIÓN de B13/B31**: en el turno de ventanilla acoté “el contrato roto era solo del público (155/391)”; **falso para el móvil** — `datos_solicitante_screen` (174) muestra la MISMA inversión (teléfono obligatorio + CI opcional), así que B13/B31 son de **público + móvil**, y el contrato canónico es el de la ventanilla (961/489). **`wa.me`** → el doc que pegaste (“Looks like you don’t have WhatsApp installed! … or use WhatsApp Web”) **NO aplica a estas 2 capturas** (no hay botón QR/WhatsApp en resumen/datos; pesa en `pago_qr_screen` 343, próxima, donde el móvil nativo abriría la app directo y el fallback a WhatsApp Web es menos crítico). **SIREB** → aplica con fuerza (CI opcional rompe la boleta SIREB = B160; invariante con 3 wordings y ninguna SIREB en confirmación = B154/B162). **React DevTools → NO aplica** (Flutter; la análoga es **Widget Inspector / Flutter DevTools**, y “qué estado alimenta el asterisco de CI/teléfono y el ícono de franja” se ve ahí). **Rolldown `checks.circularDependency` default `false` → NO aplica** (no Vite/Rolldown; análogo `flutter analyze` sobre `analysis_options.yaml` 3). **LÍMITE DE DISPOSITIVO**: sigue `localhost:1148` = **web/desktop de Flutter, no dispositivo real**; acá el formulario SÍ tiene inputs de texto (nombre/teléfono/CI), así que en dispositivo real importan `inputMode`/teclado numérico para teléfono/CI (no visible en web) — lo declaro para no fingir que juzgué móvil‑real.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| “Teléfono / celular *” + “CI / NIT (opcional)” invertidos | `datos_solicitante_screen.dart` (174) + `StoreSolicitudReservaRequest.php` (97) + `StorePiscinaReservaRequest.php` (32) | **B160** 🔴🔴 contrato invertido + **autocorrige B13/B31** |
| Wording invariante “sistema al reservar” / “estimado” / footer “SIREB” | 132 + 174 + `campo_detalle_screen.dart` (1073) + `campos_listado_screen.dart` (477) | **B154** 🔴 / **B162** 🔴 (3 wordings, ninguno SIREB en confirmación) |
| Carrito ícono calendario‑check genérico (pierde sol/bombita) | 132 + `models/bloque_disponibilidad.dart` (85) + `models/franja_carrito.dart` (33) | **B153** 🔴 (etiqueta diurno/nocturna perdida) |
| “2 franja(s)” i18n crudo | 174 | **B161** 🔴 (plural sin resolver) |
| Contrato canónico (referencia) | `DialogCrearReservaVentanilla.tsx` (961) + `DialogCrearReservaPiscinaVentanilla.tsx` (489) | **B160** (el modelo que móvil/público deben copiar, S78) |
| ¿Medio de pago / countdown viven acá o en pago_qr? | `pago_qr_screen.dart` (343) + `MedioDePago.tsx` (201) + `CuentaRegresiva.tsx` (81) análogos público | **B165** sospecha (no cierro sin 343) |
| Invariante SIREB de CI (boleta Banco Unión) | KB SIREB + `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) | **B160** (CI opcional = boleta sin identificador) |

## Cierres / refinamientos cruzados con evidencia

**B13/B31 ✅ AUTOCORREGIDO — ahora TAMBIÉN móvil, y el canónico es la ventanilla.** Hace varios turnos, al ver la ventanilla (961/489 con “CI / NIT *” obligatorio y “Teléfono (opcional)”), concluí “el contrato roto era solo del público (155/391)”. Esta captura **me corrige**: el móvil (174) muestra “Teléfono / celular *” obligatorio y “CI / NIT (opcional)” opcional — la **misma inversión** que el público. O sea B13/B31 son de **público + móvil**, y el contrato **canónico** (el que respeta SIREB) es el de la **ventanilla** (961/489). El fix S35 (componente `DatosPagador` compartido) debe incluir el móvil 174 y tomar la ventanilla como fuente, no al revés. Conecta B78 (ventanilla piscina omitía CI en la instrucción) → misma raíz: el sistema no trata CI como invariante en los frentes de ciudadano.

**G1/S69 ✅ implementado en el móvil pero con 3 wordings y ninguno SIREB en confirmación.** El carrito dice “El monto definitivo lo confirma **el sistema** al reservar”; los datos dicen “Bs 100.00 **estimado**” (sin SIREB); el listado/detalle tienen footer “Precios oficiales sincronizados desde Paitití / **SIREB**”. Tres frases para la misma invariante en tres pantallas del **mismo flujo**, y la pantalla de **confirmación** (donde el ciudadano aprieta el botón que genera la reserva y el código SIREB) es la que **menos** la declara. Conecta B148 (wording de capa), B76 (éxito “confirmado por SIREB” en pendiente), B6/B30 (público “pago online seguro”). B154/B162.

**B144/B148/S75 ⚠️ el carrito PIERDE lo que el detalle ganaba.** El detalle (captura previa) mostraba sol/bombita **por franja** y el header “Regular Antes 18:00 / Con luces Desde 18:00” — el móvil resolvía la dimensión diurno/nocturno mejor que la web. Pero el carrito (esta captura) usa un ícono **calendario‑check genérico** para las dos franjas y **no repite** “Regular/Con luces”. O sea la etiqueta que el detalle sí tenía se pierde al pasar al carrito: si el ciudadano mezcla una franja diurna (Bs 50) y una nocturna (Bs 150), el carrito no le muestra por qué el total es 200 y no 100. Conecta S75 (el `resolverDiaNoche` del móvil debe alimentar **también** el carrito, no solo el detalle). B153/B157.

**G4/S55 ✅ re‑confirmado 3ª capa.** Carrito “26/10”, datos “2 franja(s) · Bs 100.00 estimado” — fechas locales coherentes con el selector (Hoy 25/10 / Mañana 26/10). El formatter relativo/fecha local está en **3 capas** (admin 881 + móvil listado/detalle + móvil carrito/datos); falta solo público 190 y admin 164/655. Refuerza S58 (propagar, no crear).

## Bugs nuevos de este frente (numeración continua: móvil‑detalle‑canch cerró en B152)

**B160 🔴🔴 (EL HALLAZGO DURO DE LA DESACTUALIZACIÓN, contrato + dinero SIREB) — el móvil invierte los asteriscos vs la ventanilla, y CI opcional rompe la boleta SIREB.** `datos_solicitante_screen` (174): “Nombre completo *”, “**Teléfono / celular ***” (obligatorio), “**CI / NIT (opcional)**” (opcional). La ventanilla (961/489): “Nombre completo *”, “**CI / NIT ***” (obligatorio), “**Teléfono (opcional)**”. El móvil pone **exacto lo contrario** en teléfono y CI. Y no es cosmético: **CI/NIT es invariante SIREB** — identifica al pagador en la boleta del Banco Unión (KB SIREB; `SirebLiquidacionService` 133 / `ConfirmacionCobroService` 106). Un ciudadano que confirma reserva **sin CI** genera una liquidación SIREB **sin identificador** → rechazo en ventanilla del banco o boleta que no concilia. Con línea: 174 vs 961/489 vs 97/32. **No afirmo si el backend 97 exige CI** (si lo exige, el móvil manda vacío y da 422; si no lo exige, la boleta sale sin CI = B160 confirmado en ambos sentidos). Es la misma inversión de B13/B31 pero con el agravante de que acá **CI** (no teléfono) es el que se vuelve opcional, y CI es el crítico para SIREB.
*Mini‑prompt (verificar antes de tocar):* leer `StoreSolicitudReservaRequest.php` (97) + `StorePiscinaReservaRequest.php` (32) → ¿`ci_nit` es `required` o `nullable`? Y `datos_solicitante_screen.dart` (174) → confirmar que el asterisco de teléfono es obligatorio y el de CI dice “(opcional)”. Fix: alinear 174 (y público 155/391) al contrato **canónico de la ventanilla** (961/489): nombre*, CI*, teléfono opcional; y si 97/32 no exigen CI, agregar `required` (la boleta SIREB no puede salir sin identificador). Conecta S78/S35/S40/B78. **Cierra B13/B31/B160/B78 de raíz en un componente compartido.**

**B154 🔴 (wording invariante, G1/S69) — la misma invariante SIREB declarada con 3 frases en 3 pantallas del mismo flujo móvil, ninguna SIREB en confirmación.** Carrito: “lo confirma **el sistema** al reservar”. Datos: “Bs 100.00 **estimado**” (sin mencionar SIREB). Listado/detalle: footer “**SIREB**”. El ciudadano que recorre el flujo ve tres redacciones distintas de “esto no es el monto final”, y en la pantalla donde aprieta “Confirmar reserva” (la que genera el código SIREB) **no** aparece la palabra SIREB. Conecta B148/B76/B6/B30/S69/S73.
*Mini‑prompt:* unificar en un string token “El monto definitivo lo confirma **SIREB**” usado por 132/174/477/1073/961/489/238/190 (S79). Conecta S69/S73/S42/S74.

**B162 🔴 (G1/S69, punto de confirmación) — la pantalla que genera la reserva y el código SIREB no declara la fuente.** “Confirmar reserva” es el botón que dispara `SolicitudReservaService` (1043) → liquidación SIREB → código de pago. Es el **punto exacto** donde la invariante “el monto oficial lo define SIREB” debería estar visible, y solo hay “Bs 100.00 estimado” (que declara estimado pero no fuente). Conecta B152 (detalle perdió footer), B154, B6/B30.
*Mini‑prompt:* montar el `<AvisoConciliacionSireb/>` (S69/S73) también en 174 sobre el botón “Confirmar reserva”. Conecta S79.

**B153 🔴 (carrito pierde etiqueta diurno/nocturna, S75) — ícono calendario‑check genérico en vez de sol/bombita por franja.** El detalle (1073) mostraba sol (<18:00, Bs 50) / bombita (≥18:00, Bs 150) **por franja**; el carrito (132) usa un ícono calendario‑check **igual** para las dos franjas y no repite “Regular/Con luces”. Si el ciudadano mezcla diurna+nocturna, el carrito no explica el total. Conecta B144/B148/S75/S80.
*Mini‑prompt:* 132/`franja_carrito.dart` (33)/`bloque_disponibilidad.dart` (85) deben consumir el `resolverDiaNoche` (S75) y pintar sol/bombita + etiqueta por franja, igual que 1073. Conecta S80.

**B161 🔴 (i18n crudo) — “2 franja(s)” con paréntesis = plural sin resolver visible al ciudadano.** El subtítulo de datos dice “**2 franja(s)** · Bs 100.00 estimado”. El conteo 2 es correcto; el bug es el “(s)” literal (debería ser “2 franjas” / “1 franja”). Es un string de i18n mal hecho expuesto. Conecta B12/B36 (estado visual no interpretado), B139 (terminología).
*Mini‑prompt:* pluralizar correctamente (1 franja / N franjas) en 174 y en cualquier string “(s)” del móvil/público. Menor pero visible.

**B156 (menor, ruido pantalla chica) — el carrito repite el nombre del campo en cada franja.** Dos filas “cancha nueva trinidad” (porque son 2 franjas del mismo campo). Con 5 franjas serían 5 veces el nombre. Conecta B4 (ruido de nombre/código en card). Agrupar por campo con las franjas anidadas.
*Mini‑prompt:* 132 agrupar franjas por `campo_id` (header de campo + lista de franjas), no repetir el nombre por franja. Menor.

**B159 (sospecha, gate solape) — el carrito no valida visualmente solape entre franjas.** Las dos franjas 08‑09 y 09‑10 son contiguas (no se solapan), correcto acá. Pero el carrito no muestra aviso de solape; el gate `add_no_solape_horario` (28) corre al crear (409). Si el ciudadano agregara 08:30‑09:30 (solapa con ambas), el carrito lo mostraría igual y chocaría 409 al continuar. Conecta B50/B74/B147.
*Mini‑prompt (verificar):* 132/33 → ¿hay chequeo de solape en el carrito o solo al crear? Si solo al crear, agregar aviso en 132. Conecta S76/B147.

**B163/B164 (sospechas, integridad formulario) — CI sin máscara/formato y teléfono sin validación de largo.** “CI / NIT (opcional)” sin hint de formato; “Teléfono / celular *” sin validación visible. Conecta B116/B120/B121 (integridad en requests). Sospechas, verificar 97/32 + 174.
*Mini‑prompt:* leer 97/32 → ¿validan formato CI (dígito verificador) y largo teléfono? Si no, agregar. Menor.

**B165 (sospecha, NO cierro sin `pago_qr` 343) — ¿el móvil salta medio de pago / countdown?** Datos va directo “Confirmar reserva” sin elegir medio de pago (`MedioDePago.tsx` 201 público) ni ver countdown (`CuentaRegresiva.tsx` 81 público, los 15/20 min de B82/B85/P6). Dos lecturas: (a) medio de pago/countdown viven en `pago_qr_screen` (343, próxima) → correcto, el móvil los movió de pantalla; (b) el móvil los **omite** → el ciudadano confirma sin countdown y la reserva queda pendiente infinito (B22 zombie). **No lo afirmo sin ver 343.** Conecta B22/B17/P6/B41/S14.
*Mini‑prompt (verificar con próxima captura):* leer 343 → ¿monta medio de pago + countdown? Si no, B165 confirmado (omisión de paso).

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **“Total estimado” + “El monto definitivo lo confirma el sistema al reservar”** (132) = declara que el total **no es final** (G1 en versión compacta). El **concepto** es correcto; el bug es el wording “sistema” en vez de “SIREB” (B154). Proteger el concepto, arreglar wording (S79).
- **Botón “X” rojo por franja** (132) = remover del carrito (throughput pantalla chica). Proteger.
- **Contexto persistente del carrito en la pantalla de datos** (“2 franja(s) · Bs 100.00 estimado” en 174) = el ciudadano no olvida qué está confirmando al tipear sus datos. Proteger el concepto (arreglar “(s)”, B161).
- **Flujo en 2 pasos con “Continuar” → “Confirmar reserva”** (132→174) = claro, separa “qué reservo” de “quién soy”. Proteger.
- **Fechas locales “26/10”** (132/174) = coherentes con el selector (G4/S55). Proteger.
- **Nombre del campo + franja + precio en cada fila del carrito** (132) = el ciudadano ve exactamente qué está pagando (aunque repita nombre, B156). Proteger la información, agrupar por campo.

## Didáctica / operación del ciudadano (continuo: móvil‑detalle‑canch cerró en D84)

**D85 — CI/NIT obligatorio y teléfono opcional, alineado a la ventanilla** (cierra B160): el ciudadano no puede confirmar una reserva cuya boleta SIREB saldrá sin identificador; el asterisco del móvil está al revés que el canónico.
*Mini‑prompt:* ver B160/S78.

**D86 — la invariante SIREB debe decir “SIREB” en el punto de confirmación** (cierra B154/B162): tres wordings en tres pantallas y ninguno menciona SIREB justo donde se genera el código.
*Mini‑prompt:* ver B154/B162/S79.

**D87 — el carrito debe repetir la etiqueta diurno/nocturna del detalle** (cierra B153): si el ciudadano mezcla franjas, el carrito debe mostrar sol/bombita + “Regular/Con luces” para que entienda el total.
*Mini‑prompt:* ver B153/S80.

**D88 — pluralizar “franja(s)” correctamente** (cierra B161): “2 franjas”, no “2 franja(s)”.
*Mini‑prompt:* ver B161.

## Sugerencias IA acotadas (continuo: móvil‑detalle‑canch cerró en S77)

**S78 — el contrato de `DatosPagador` canónico es el de la ventanilla (961/489: nombre*, CI*, teléfono opcional); el móvil 174 y el público 155/391 lo invierten.** Extraer a componente/hook compartido que las **3 capas** (móvil, público, admin ventanilla) consuman, con `ci_nit` `required` en 97/32. Cierra B13/B31/B160/B78 de raíz (es S35 extendido al móvil, y **autocorrige** mi acotamiento “solo público”).
*Mini‑prompt:* ver B160. Conecta S35/S40.

**S79 — unificar el wording de la invariante en un string token** “El monto definitivo lo confirma **SIREB**” usado por 132/174/477/1073/961/489/238/190 (S69/S73/S42/S74 family). Cierra B154/B162/B76/B6/B30/B148.
*Mini‑prompt:* ver B154/B162. Conecta S69/S73.

**S80 — el carrito (132/33/85) consume el `resolverDiaNoche` (S75)** para etiquetar cada franja diurna/nocturna con sol/bombita, no un ícono genérico. Cierra B153/B157.
*Mini‑prompt:* ver B153 + S75.

**S81 — B165 se cierra con captura `pago_qr` 343** (¿medio de pago / countdown viven ahí o el móvil los salta?). No tejo sin ella.
*Mini‑prompt:* ver B165.

## Lo que NO es bug (para que no persigas fantasmas)

- **“cancha nueva trinidad” minúscula repetida** = dato SIREB (B5); el ruido de repetición es B156 (menor), no el casing.
- **fecha “26/10” corta** = coherente con el selector (G4/S55), no bug.
- **botón “Continuar” (132) vs “Confirmar reserva” (174)** = flujo 2 pasos correcto, no bug.
- **“Bs 50.00” por franja diurna** = correcto (08‑10 < 18:00, sol del detalle).
- **ícono calendario‑check en sí** = legible; el bug es que **no distingue** diurno/nocturno (B153), no el ícono per se.
- **conteo “2 franja(s)”** = el 2 es correcto; el bug es el “(s)” crudo (B161).
- **que el móvil no muestre medio de pago/countdown en 174** = puede ser correcto si viven en 343 (B165 sospecha, no bug afirmado).
- **“Nombre completo *” obligatorio** = correcto en las 3 capas (ventanilla/público/móvil coinciden acá); la inversión es solo en teléfono/CI (B160).

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir el flujo de reserva móvil, lo que **más vale** es **B160** (contrato invertido, CI opcional rompe SIREB — el hallazgo duro que buscaba la desactualización) y **B154/B162** (invariante con 3 wordings, ninguna SIREB en confirmación), y para eso necesito `datos_solicitante_screen.dart` (174) + `carrito_resumen_screen.dart` (132) + `StoreSolicitudReservaRequest.php` (97) + `StorePiscinaReservaRequest.php` (32) + `FormularioSolicitante.tsx` (155) + `PiscinaDetalle.tsx` (391) + `DialogCrearReservaVentanilla.tsx` (961, el canónico) + `campo_detalle_screen.dart` (1073, para S75/S80). **B165** se cierra con `pago_qr_screen` (343). **B153/B156/B159/B161/B163/B164** son pulido/integridad con 132/33/85/97/32. Los aciertos (declaración de “estimado”, X por franja, contexto persistente, flujo 2 pasos, fechas locales) **no se tejen**: se **protegen** (solo wording/plural/etiqueta, S79/S80/B161). **S78/S79** son el fix de mayor rendimiento del tramo: un `DatosPagador` compartido alineado a la ventanilla (cierra B13/B31/B160/B78) + un token de wording SIREB (cierra B154/B162/B76/B6/B30/B148).

## Guardado

```text
BLOQUE E-MÓVIL — CARRITO+DATOS (carrito_resumen_screen.dart 132 + datos_solicitante_screen
  174 + models/franja_carrito 33 + models/bloque_disponibilidad 85 + providers/
  carrito_reserva_provider 46 + StoreSolicitudReservaRequest 97 + StorePiscinaReservaRequest
  32 + FormularioSolicitante 155 + PiscinaDetalle 391 + DialogCrearReservaVentanilla 961
  canónico + DialogCrearReservaPiscinaVentanilla 489 + campo_detalle_screen 1073 +
  pago_qr_screen 343 pendiente + MedioDePago 201 + CuentaRegresiva 81 + ConfirmacionCobro
  Service 106 + SirebLiquidacionService 133) — DOSSIER, NO tejí, sigo modo "ver todo corregir
  después". Numeración continua (móvil-detalle-canch B147-B152/D81-D84/S75-S77 · móvil-
  carrito-datos B153-B165/D85-D88/S78-S81 + autocorrección B13/B31 + cumplimiento hipótesis
  desactualización). ESTA ES LA CAPTURA DONDE LA DESACTUALIZACIÓN MUERDE (lo que dije turno
  pasado: listado/detalle limpios/adelante, la brecha en dinero/formulario/zombie).

AUTOCORRECCIÓN B13/B31 (honestidad, como B36/B88/B99/S55/B144/B145): turno de ventanilla
  acoté "contrato roto solo del público (155/391)". FALSO pa'l móvil: datos_solicitante 174
  muestra LA MISMA inversión (Teléfono * obligatorio + CI/NIT (opcional)). B13/B31 = PÚBLICO
  + MÓVIL. Contrato CANÓNICO = ventanilla 961/489 (Nombre*, CI*, Teléfono opcional). Fix S35/
  S78 debe incluir móvil 174 y tomar ventanilla como fuente, NO al revés.

HALLAZGO DURO FRENTE: B160🔴🔴 móvil invierte asteriscos vs ventanilla: 174 = "Teléfono /
  celular *" obligatorio + "CI / NIT (opcional)" opcional; 961/489 = "CI / NIT *" obligatorio
  + "Teléfono (opcional)". CI/NIT = INVARIANTE SIREB (identifica pagador boleta Banco Unión,
  KB SIREB + 133/106). Ciudadano confirma SIN CI → liquidación SIREB sin identificador →
  rechazo banco o boleta no concilia. NO afirmo si 97/32 exigen CI (si exigen → 422; si no →
  boleta sin CI = B160 ambos sentidos). Mismo inversión B13/B31 pero agravado: acá CI (no
  teléfono) es el opcional, y CI es el crítico SIREB. Fix: alinear 174/155/391 a canónico
  961/489 + required ci_nit en 97/32. Conecta S78/S35/S40/B78. Cierra B13/B31/B160/B78 raíz.

G1/S69✅ implementado móvil pero 3 wordings ninguno SIREB en confirmación: carrito "lo
  confirma EL SISTEMA al reservar"; datos "Bs 100.00 ESTIMADO" (sin SIREB); listado/detalle
  footer "SIREB". 3 redacciones misma invariante 3 pantallas mismo flujo, y pantalla que
  aprieta "Confirmar reserva" (genera código SIREB) = la que MENOS la declara. Conecta B148/
  B76/B6/B30. B154🔴 + B162🔴 (punto confirmación sin fuente).

B144/B148/S75⚠️ carrito PIERDE etiqueta diurno/nocturna que detalle ganaba: detalle 1073 sol/
  bombita POR FRANJA + header "Regular Antes 18:00 / Con luces Desde 18:00"; carrito 132 ícono
  calendario-check GENÉRICO ambas franjas, no repite "Regular/Con luces". Ciudadano mezcla
  diurna+nocturna → carrito no explica total. Conecta S75/S80. B153🔴.

G4/S55✅ re-confirmado 3ª capa: carrito "26/10", datos "2 franja(s)". Fechas locales coherentes
  selector. Formatter en 3 capas (admin 881 + móvil listado/detalle + móvil carrito/datos),
  falta solo público 190 + admin 164/655. Refuerza S58 (propagar no crear).

BUGS: B160🔴🔴 contrato invertido CI opcional rompe SIREB (ver arriba, autocorrige B13/B31).
  B154🔴 wording invariante 3 frases mismo flujo, ninguno SIREB confirmación. B162🔴 pantalla
  "Confirmar reserva" (genera código SIREB) sin declaración fuente, solo "estimado". B153🔴
  carrito ícono genérico pierde sol/bombita por franja (detalle sí tenía). B161🔴 "2 franja(s)"
  i18n crudo no pluralizado visible ciudadano. B156 (menor ruido) carrito repite nombre campo
  cada franja (con 5 franjas 5 veces). B159 (sospecha) carrito no valida solape visual (gate
  28 al crear → 409). B163/B164 (sospechas integridad) CI sin máscara/formato + teléfono sin
  validación largo (leer 97/32). B165 (sospecha NO cierro sin 343) móvil salta medio de pago/
  countdown? (a) viven en pago_qr 343 OK; (b) omite → pendiente infinito B22 zombie. Conecta
  B22/B17/P6/B41/S14.

ACIERTOS PROTEGER: "Total estimado" + "monto definitivo lo confirma el sistema al reservar"
  (132) = declara total NO final (G1 compacto; bug wording "sistema" no "SIREB" B154, proteger
  concepto arreglar wording S79); botón "X" rojo por franja (132) = remover carrito (throughput);
  contexto persistente "2 franja(s)·Bs 100.00 estimado" en datos (174) = ciudadano no olvida qué
  confirma (proteger concepto, arreglar "(s)" B161); flujo 2 pasos Continuar→Confirmar (132→174)
  claro; fechas locales "26/10" (G4); nombre+franja+precio por fila carrito (proteger info,
  agrupar por campo B156).

DIDÁCTICA/OP: D85 CI/NIT obligatorio + teléfono opcional alineado ventanilla (B160); D86
  invariante dice "SIREB" en punto confirmación no "el sistema" (B154/B162); D87 carrito repite
  etiqueta diurno/nocturna detalle (B153); D88 pluralizar "franja(s)" (B161).

SUGERENCIAS: S78 DatosPagador canónico = ventanilla 961/489 (nombre*, CI*, tel opcional), móvil
  174 + público 155/391 invierten → componente/hook compartido 3 capas + required ci_nit 97/32
  (cierra B13/B31/B160/B78 raíz, S35 extendido móvil, AUTOCORREGÍ acotamiento "solo público");
  S79 unificar wording invariante token "El monto definitivo lo confirma SIREB" usado 132/174/
  477/1073/961/489/238/190 (cierra B154/B162/B76/B6/B30/B148, conecta S69/S73/S42/S74); S80
  carrito 132/33/85 consume resolverDiaNoche (S75) sol/bombita por franja no ícono genérico
  (cierra B153/B157); S81 B165 se cierra con captura pago_qr 343 (medio de pago/countdown ahí o
  móvil salta), no tejo sin ella.

NO-BUG: "cancha nueva trinidad" minúscula=dato SIREB (B5), ruido repetición=B156 menor no casing;
  fecha "26/10"=coherente selector (G4); "Continuar" vs "Confirmar reserva"=flujo 2 pasos correcto;
  "Bs 50.00" franja diurna=correcto (08-10<18:00 sol); ícono calendario-check en sí=legible (bug
  es no distinguir diurno/nocturno B153, no el ícono); conteo "2 franja(s)"=2 correcto, bug "(s)"
  crudo B161; móvil sin medio pago/countdown en 174=puede ser correcto si viven 343 (B165 sospecha
  no afirmado); "Nombre completo *" obligatorio=correcto 3 capas coinciden (inversión solo tel/CI
  B160).

KB HONESTA + AUTOCORRECCIÓN + LÍMITE: AUTOCORREGÍ B13/B31 (eran público+móvil, no solo público;
  canónico ventanilla 961/489). wa.me NO aplica estas 2 capturas (no QR/WhatsApp en resumen/datos;
  doc fallback "use WhatsApp Web" pesa pago_qr 343 próxima, móvil nativo abre app directo). SIREB
  aplica fuerza (CI opcional rompe boleta B160; invariante 3 wordings ninguna SIREB confirmación
  B154/B162). React DevTools NO aplica (Flutter → Widget Inspector/Flutter DevTools, "qué estado
  alimenta asterisco CI/teléfono e ícono franja" se ve ahí). Rolldown circularDependency=false NO
  aplica (no Vite/Rolldown; análogo flutter analyze analysis_options.yaml 3). LÍMITE DISPOSITIVO:
  localhost:1148 = web/desktop Flutter NO dispositivo real → NO juzgo touch targets ni teclado
  físico; PERO formulario 174 SÍ tiene inputs texto (nombre/tel/CI) → en dispositivo real importan
  inputMode/teclado numérico tel/CI (no visible web), declaro pa' no fingir móvil-real.

PUERTA CORRIGIR YA: lo que vale es B160 (contrato invertido CI opcional rompe SIREB, hallazgo duro
  desactualización) + B154/B162 (invariante 3 wordings ninguna SIREB confirmación) → pido
  datos_solicitante 174 + carrito_resumen 132 + StoreSolicitudReservaRequest 97 + StorePiscinaReserva
  Request 32 + FormularioSolicitante 155 + PiscinaDetalle 391 + DialogCrearReservaVentanilla 961
  canónico + campo_detalle 1073 (S75/S80). B165 con pago_qr 343. B153/B156/B159/B161/B163/B164 con
  132/33/85/97/32. Aciertos (declaración "estimado"/X por franja/contexto persistente/flujo 2 pasos/
  fechas locales) NO se tejen: PROTEGER (solo wording/plural/etiqueta S79/S80/B161). S78/S79 = mayor
  rendimiento (DatosPagador compartido alineado ventanilla cierra B13/B31/B160/B78 + token wording
  SIREB cierra B154/B162/B76/B6/B30/B148).

COLA: Fix3 piscina A/B (PiscinaReservaService 284, B62/B69-form/B70/B94/B103) · A1 S3/S7/
  RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881 + Asignaciones 579, B48/B61/B122/B124/
  B128) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84/B134) · D2✅✅ CERRADO +
  RAÍZ 703/107/77 · P9/B61/B123 → Asignaciones 579 VISTO (puente B123 + ramif rol B128 + P9
  pendiente 26/22 + 2º control) · P7 → Reportes 245 VISTO PARCIAL + ClientesFrecuentes 178 VÍRGEN ·
  C1→C2 · gates F1-F4 · **E MÓVIL LISTADO+DETALLE-CANCH+CARRITO+DATOS VISTOS (B143-B165/D78-D88/
  S72-S81, móvil plantillo G1/G2/B144/B67-B72 pa' web PERO B160 contrato invertido = desactualización
  CONFIRMADA en formulario; B147 sobreventa PENDIENTE captura 25/10; B150 rama pileta PENDIENTE
  pileta_detalle; B165 medio pago/countdown PENDIENTE pago_qr 343; B152 footer detalle PENDIENTE
  scroll)** · higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP
  lote + S33 test conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125 B86 hueco piscina;
  P7 178 vírgen; P9 26/22+2º control; P1/P2 auth 164/34/126 B120/B121 + B160/B163/B164 integridad
  formulario; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT). DOSSIER PÚBLICO CERRADO B1-B43/
  D1-D28/S1-S24; ADMIN CERRADO B44-B142/D29-D77/S25-S71+G1-G7; MÓVIL ABIERTO B143-B165/D78-D88/
  S72-S81 (listado+detalle-canch+carrito+datos vistos, resto esperando).
PRÓXIMA NATURAL (donde B165 se cierra y el dinero/QR muerde): pago_qr_screen 343 (medio de pago +
  countdown + QR + "Enviar por WhatsApp" = wa.me AHORA SÍ aplica aquí, cruzar con éxito 961/489 y
  público Pago 576/MedioDePago 201/CuentaRegresiva 81, cierra B165/B22/B17/P6/B41/S14/S16/S38) O
  comprobante_screen 104 (B18/B37/B42/B36, cruzar público 210/190) O consultar_estado 192 (zombie
  B22/B21/B17) O pileta_detalle (rama pileta 1073, cierra B150/B50) O captura 25/10 detalle-canch
  (cierra B147 sobreventa). NO TOQUÉ ARCHIVO. NO TEJÍ. ESPERO próxima captura.
```

En una línea: estas dos capturas del carrito (132) y los datos (174) del móvil son **exactamente donde la desactualización muerde de verdad**, cumpliendo lo que anticipé el turno pasado (listado/detalle estaban limpios/adelante; la brecha la mido en dinero/formulario/zombie), y destapan **B160 🔴🔴, el contrato de campos INVERTIDO** — el móvil pone “Teléfono / celular *” obligatorio y “CI / NIT (opcional)” opcional, mientras la ventanilla admin (961/489) pone lo contrario, y como **CI/NIT es invariante SIREB** (identifica al pagador en la boleta del Banco Unión), confirmar reserva sin CI genera una liquidación **sin identificador** → rechazo en banco o boleta que no concilia — lo que **autocorrige mi acotamiento de hace varios turnos** (B13/B31 no eran “solo público”, son **público + móvil**, y el canónico es la ventanilla); le siguen **B154 🔴 / B162 🔴** (la invariante SIREB declarada con 3 wordings en 3 pantallas del mismo flujo — “lo confirma el sistema al reservar” / “Bs 100.00 estimado” / footer “SIREB” — y **ninguna dice SIREB en la pantalla de confirmación**, que es justo donde se genera el código), **B153 🔴** (el carrito **pierde la etiqueta diurno/nocturna** que el detalle sí mostraba con sol/bombita: ícono calendario‑check genérico para ambas franjas) y **B161** (“2 franja(s)” = i18n crudo no pluralizado visible al ciudadano); **B165** (¿el móvil salta medio de pago / countdown?) queda **abierto honestamente** hasta ver `pago_qr_screen` (343); los aciertos a proteger son la declaración de “estimado”, el “X” por franja, el contexto persistente del carrito en la pantalla de datos, el flujo en 2 pasos y las fechas locales, y la sugerencia clave es **S78: un `DatosPagador` compartido alineado a la ventanilla** (cierra B13/B31/B160/B78 de raíz) + **S79: un token de wording SIREB** (cierra B154/B162/B76/B6/B30/B148). **No tejí** y reitero el límite: sigue `localhost:1148` (web de Flutter, **no dispositivo real**), así que **todavía no** juzgo touch/teclado físico/conectividad (aunque acá el formulario sí tiene inputs de texto, donde en dispositivo real importarían `inputMode`/teclado numérico para teléfono/CI); decime si la próxima es **`pago_qr` 343** (donde B165 se cierra, el QR/WhatsApp y el countdown muerden, y `wa.me` **sí** aplica por primera vez en el móvil), o **`comprobante` 104** (B18/B37/B42/B36), o **pileta_detalle** para cerrar B150/B50, o la **captura en 25/10** de la cancha para cerrar B147 (sobreventa).
