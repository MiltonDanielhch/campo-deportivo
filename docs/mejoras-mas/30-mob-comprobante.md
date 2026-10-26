Veredicto en una línea: esta es `comprobante_screen.dart` (104) — el comprobante final del flujo de cancha en el móvil (listado→detalle→carrito→datos→pago→**acá**) — y es la captura que **convierte mi sospecha de dinero en evidencia visible y grave**: pinta **“¡Reserva confirmada!” con check verde + “Monto pagado: Bs 100.00” en verde**, o sea grita visualmente “ya pagaste”, cuando el paso anterior (pago QR 343) tenía **countdown 05:31 y “escaneá con tu app de banca móvil”** — el ciudadano **todavía tenía que pagar en el banco** —, así que **B174 🔴🔴 (EL HALLAZGO DURO DEL TRAMO MÓVIL)**: si el check verde y “Monto pagado” derivan de `estado == confirmada` (liquidación **emitida** por SIREB) en vez de `estado == pagado` (pago **recibido** por el banco), el comprobante **miente sobre el dinero** justo donde el ciudadano decide si ir al campo o reclamar — es **B84** (admin “Estado: pagada / Pagado: No” contradictorio) + **B172** (“el Core confirma” colapsa niveles) + **B170/B18/B37/B76** convergiendo en una sola pantalla con evidencia visible; **NO lo confirmo sin leer 104/106/133** porque no veo el derivado. Le siguen **B175 🔴** (el comprobante muestra `RES-` cabecera + dos `RSV-` por franja con “presenta **estos** códigos”, pero **omite el `4‑4‑3` de pago en ventanilla** que el admin 961 sí tenía — ¿el ciudadano que no pagó llega al campo con solo `RSV-` y con qué paga?), **B177** (el comprobante **perdió el footer de conciliación SIREB** que listado/detalle tenían — B152/B170/B6/B30/G1), **B180** (único botón “Volver al inicio”, sin “Descargar comprobante”/“Enviar por WhatsApp”/“Ver estado” que el admin 961 sí — B169/P8/S38) y **B179 ✅** (ícono de **ticket** por franja, semánticamente correcto para “presentar al llegar”, contracara sana del ícono genérico del carrito B153). **B178** (¿el comprobante aparece sin pago real?) queda **abierto honestamente** junto con **B173** (background) porque ambos requieren emulador, no web. **No tejí**, sigo dossier.

> KB honesta de esta captura, con **autocorrección de alcance** y **límite de dispositivo**: **AUTOCORRECCIÓN**: el turno pasado traté B84/B172 como “semántica SIREB en admin/pago”; esta captura prueba que la **misma invariante rota llega al comprobante final del ciudadano** con check verde y “Monto pagado”, o sea no es un detalle de etiqueta de admin, es **lo último que ve el ciudadano antes de irse al campo** — subo la severidad de B174 a 🔴🔴 por eso. **`wa.me`** → el doc que pegaste (“Looks like you don’t have WhatsApp installed! … or use WhatsApp Web”) **NO aplica como fallo acá** (el comprobante móvil no tiene botón WhatsApp, B180 lo nota como **ausencia**, no como bug de wa.me); el fallback robusto del admin 961/489 sigue siendo evidencia de que el **móvil** es el que omite el botón. **SIREB** → aplica con fuerza máxima (B174 “Monto pagado” vs liquidación/pago = ADR‑003 hub‑spoke; B175 códigos RES‑/RSV‑/4‑4‑3; B177 footer ausente). **React DevTools → NO aplica** (Flutter; análoga **Widget Inspector / Flutter DevTools**, y “qué estado alimenta el check verde y el string ‘Monto pagado’” se ve ahí, no en copy). **Rolldown `checks.circularDependency` default `false` → NO aplica** (no Vite/Rolldown; análogo `flutter analyze` sobre `analysis_options.yaml` 3). **LÍMITE DE DISPOSITIVO**: sigue `localhost:1148` = web de Flutter, **no dispositivo real**; **B173** (countdown/polling en background) y **B178** (¿comprobante aparece sin pago?) **NO son juzgables en web** — los declaro abiertos y dependientes de emulador, no los cierro con esta captura.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| “¡Reserva confirmada!” check verde + “Monto pagado: Bs 100.00” | `comprobante_screen.dart` (104) + `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) + `EstadoSolicitudReserva.php` (10) + migr `add_monto_confirmado` (16) | **B174** 🔴🔴 ¿“pagado” deriva de liquidación o pago? + **B176** monto_total vs confirmado |
| `RES-20261025-PQR5J2` (cabecera) + 2× `RSV-…` (por franja) | 104 + `SolicitudReserva.php` (43) + `SolicitudReservaDetalle.php` (53) + `DialogCrearReservaVentanilla.tsx` (961, referencia 4‑4‑3) | **B175** 🔴 omite el 4‑4‑3 de pago ventanilla + **B168/B77** |
| “Monto pagado” sin footer SIREB | 104 + `Comprobante.tsx` (210) / `ComprobantePiscina.tsx` (190) público | **B177** (G1/S69 perdido) + **B18/B37** cruzado |
| Único botón “Volver al inicio” | 104 + `ConsultarEstado.tsx` (299) análogo + `DialogCrearReservaVentanilla.tsx` (961) | **B180** (sin Descargar/WhatsApp/Ver estado) + **B169/P8/S38** |
| Ícono ticket por franja + “presenta estos códigos al llegar” | 104 + `MarcarAsistenciaService.php` (125) + `AsistenciaController.php` (56) | **B179 ✅** acierto (ticket=asistencia) + **P5** (el `RSV-` alimenta asistencia) |
| “Bs 100.00” coherente con carrito 2×50 | 104 + `SolicitudReservaService.php` (1043) | **acierto** (no recalcula mal, contracara B42/B37) proteger |

## Cierres / refinamientos cruzados con evidencia

**B84/B172 ✅ CONVERGEN ACÁ con evidencia visible (autocorrección de alcance).** El admin (838) mostraba “Estado: pagada / Pagado: No” en la misma caja (B84); el pago QR móvil (343) decía “cuando el Core confirma tu pago” colapsando niveles (B172). Esta captura es la **tercera cara de la misma moneda**: el comprobante final pinta check verde + “Monto pagado” **sin que yo pueda ver si el derivado es `confirmada` (liquidación emitida) o `pagado` (pago recibido)**. Si es `confirmada`, el ciudadano ve “pagado” cuando el banco aún no registró nada → se va al campo con una reserva que no pagó, o reclama un dinero que sí salió. **No es un detalle de etiqueta: es lo último que ve el ciudadano antes de decidir.** Conecta B170 (pago sin transición estimado→definitivo), B18/B37 (público `monto_total` como “pagado”), B76 (éxito “confirmado por SIREB” en pendiente). **La invariante SIREB rota se propaga de admin→pago→comprobante en el móvil.**

**B77/B168 ✅ RE‑CRUZADO con los códigos del comprobante.** El admin ventanilla (961/489) tenía **tres** representaciones: `4‑4‑3` grande (“código de pago en ventanilla”), `RES‑`/`SIREB:` (cabecera) y `RSV‑` (por franja). El pago QR móvil (343) mostraba `RES‑` + QR. El comprobante móvil (acá) muestra `RES‑` (cabecera) + **dos `RSV‑`** (por franja) con “presenta **estos** códigos al llegar”. O sea el móvil **sabe** que los `RSV‑` son de asistencia (presentar al campo) y el `RES‑` de soporte, pero **no muestra el `4‑4‑3` de pago ventanilla** que el admin sí. Pregunta con línea: si el ciudadano **no pagó** (B174) y llega al campo con solo `RSV‑`, ¿paga ahí? ¿con qué código? ¿el `4‑4‑3` existe solo para el admin o el móvil lo debió mostrar? Conecta B168 (payload del QR de 343: ¿codificaba `RES‑` o `4‑4‑3`?). **Sospecha**: el móvil fragmenta los códigos y pierde el de pago.

**G1/S69/B177 ✅ la invariante se pierde en los frentes de dinero del móvil.** Listado (477) y detalle (1073) **tenían** el footer “Precios oficiales sincronizados desde Paitití / SIREB”. Carrito (132) lo diluyó a “lo confirma el sistema”. Datos (174) a “Bs 100.00 estimado”. Pago (343) a “el Core confirma”. **Comprobante (104) no lo tiene** (B177). O sea la declaración de fuente SIREB **se degrada pantalla por pantalla** a lo largo del flujo de pago móvil, y desaparece justo en el comprobante (donde el ciudadano guarda la prueba). Conecta B154/B162/B152/B170/B6/B30. **El móvil tiene G1 en el listado pero lo pierde en el funnel de dinero** — inversión de mi narrativa “móvil plantillo”: acá el móvil está **peor** que su propio listado.

**B179 ✅ acierto de semántica de ícono (contracara sana de B153).** El carrito (132) usaba ícono calendario‑check **genérico** y perdía la etiqueta diurno/nocturna (B153). El comprobante (104) usa ícono de **ticket/boleto** por franja, que es **semánticamente correcto** para “presenta estos códigos al llegar” (ticket = acceso/asistencia, no franja horaria). O sea el móvil **sí** sabe elegir ícono según el significado del dato en el comprobante; el bug B153 era del carrito, no de una incapacidad general del móvil. Proteger B179 y usarlo como modelo para arreglar B153 (S80).

**P5 ✅ el `RSV-` alimenta asistencia (conexión con el gate que falta).** “Presenta estos códigos al llegar al campo deportivo” + ícono ticket = el `RSV-20261025-ZFVD7A` es el **código de asistencia por franja**, que `MarcarAsistenciaService` (125) / `AsistenciaController` (56) consumen (P5, el último gate grande sin dueño de archivo visto). O sea el comprobante móvil **cierra el loop** reserva→pago→asistencia: el ciudadano presenta el `RSV-` y el funcionario marca asistencia. Conecta B86 (el detalle admin de pileta **omitía** “Marcar asistencia”; acá el móvil de cancha **sí** da el código para marcarla). **Acierto de flujo**, proteger.

## Bugs nuevos de este frente (numeración continua: móvil‑pago‑qr cerró en B173)

**B174 🔴🔴 (EL HALLAZGO DURO DEL TRAMO MÓVIL, dinero/semántica SIREB) — el comprobante pinta check verde + “Monto pagado” sin que yo pueda ver si deriva de liquidación emitida o pago recibido.** “¡Reserva confirmada!” (check verde) + “Monto pagado: Bs 100.00” (verde). El paso anterior (343) tenía countdown 05:31 y “escaneá con tu app de banca móvil” → el ciudadano **todavía debía pagar en el banco**. Si el comprobante se navega desde 343 cuando el backend pone `estado = confirmada` (liquidación **emitida** por SIREB, `SirebLiquidacionService` 133) en vez de `estado = pagado` (pago **recibido**, `ConfirmacionCobroService` 106 / webhook 97), entonces el ciudadano ve “pagado” **antes de pagar** → se va al campo con reserva no pagada, o reclama dinero que sí salió. Es **B84** (admin “pagada / Pagado: No”) + **B172** (“el Core confirma” colapsa) + **B170/B18/B37/B76** convergiendo, ahora en la pantalla final del ciudadano con evidencia visual (check verde + “pagado” en verde). **NO lo confirmo sin leer 104/106/133/10/16** porque no veo el derivado del check ni del string. Severidad 🔴🔴 por ser lo último que ve el ciudadano antes de decidir ir o reclamar.
*Mini‑prompt (verificar antes de tocar):* leer `comprobante_screen.dart` (104) → ¿el check verde y “Monto pagado” se condicionan a `solicitud.estado == pagado` (recibido) o a `== confirmada` (emitida)? Y `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) + `EstadoSolicitudReserva.php` (10) + migr `add_monto_confirmado` (16) → ¿hay dos estados distintos (confirmada vs pagada) y dos montos (total vs confirmado)? Fix: el comprobante **solo** pinta check verde + “Monto pagado” si `estado == pagado` (recibido por banco); si `estado == confirmada` (liquidación emitida, aún no pagada), pintar “Reserva **en curso de pago**” / “Liquidación emitida, pendiente de pago” con el countdown/QR de 343 aún accesible. Conecta B84/B172/B170/B18/B37/B76/S42/S83. **Cierra la invariante SIREB en el funnel móvil de raíz.**

**B175 🔴 (códigos fragmentados, paridad B77/B168) — el comprobante muestra `RES-` + `RSV-` pero omite el `4‑4‑3` de pago ventanilla que el admin sí tenía.** Cabecera `RES-20261025-PQR5J2` (“Código:”) + dos filas con `RSV-20261025-ZFVD7A` / `RSV-20261025-3TBLVH` (“presenta **estos** códigos al llegar”). El admin ventanilla (961/489) tenía el `4‑4‑3` grande (“código de pago en ventanilla”) **además** de `RES-`/`RSV-`. El móvil **no** lo muestra. Consecuencia con línea: si el ciudadano **no pagó** (B174) y llega al campo con solo `RSV-` (asistencia), ¿paga ahí? ¿con qué código? El `4‑4‑3` es el que la ventanilla del banco/campo escanea para cobrar; si el móvil lo omitió, el ciudadano no tiene cómo pagar in‑situ. O el `4‑4‑3` es exclusivo admin (entonces el móvil está bien y la pregunta es si el campo puede cobrar sin él). **No lo afirmo sin leer 104/961/133.** Conecta B168 (payload QR de 343: ¿codificaba `RES-` o `4‑4‑3`?), B77/B52/B21/B83.
*Mini‑prompt (verificar):* leer 104 → ¿el comprobante móvil debió mostrar el `4‑4‑3` de pago o solo `RES-`/`RSV-`? Y 343 → ¿el QR codificaba el `4‑4‑3` (entonces el pago es por QR, no por código tipeado, y B175 se atenúa) o el `RES-` (entonces falta el `4‑4‑3` en todo el funnel móvil)? Conecta B168/S84.

**B176 (sospecha, dinero, cruza B18/B37) — “Monto pagado: Bs 100.00” ¿es `monto_total` (lo que se debía) o `monto_confirmado` (lo que SIREB registró)?** El string dice “pagado”, pero si el valor viene de `monto_total` (migr 16 agregó `monto_confirmado` justamente para separar), entonces pinta “pagado Bs 100” cuando lo confirmado por SIREB pudo ser otro (o cero si no pagó). Es **B18/B37** (público `monto_total` como “pagado”) + **B76** (éxito “confirmado por SIREB” en pendiente) en el móvil. Conecta B174 (mismo derivado). **No lo afirmo sin leer 104/16.**
*Mini‑prompt:* leer 104 → ¿“Monto pagado” lee `monto_total` o `monto_confirmado`? Fix: si `monto_confirmado` es null (no pagado), **no** pintar “Monto pagado” (B174); si existe, pintar `monto_confirmado` con etiqueta “Confirmado por SIREB”. Conecta S42/B18/B37.

**B177 (G1/S69 perdido en el funnel, cruza B152/B170/B6/B30) — el comprobante no tiene footer de conciliación SIREB.** Listado/detalle (477/1073) **tenían** “Precios oficiales sincronizados desde Paitití / SIREB”. Carrito/datos/pago lo diluyeron (“el sistema”/“estimado”/“el Core”). **Comprobante no lo tiene** → el ciudadano guarda un comprobante de “Bs 100.00 pagado” **sin la declaración de que SIREB es la fuente oficial**. Conecta B154/B162/B152/B170/B6/B30. **El móvil degrada G1 pantalla por pantalla a lo largo del funnel de dinero** (inversión de “móvil plantillo”: acá el móvil está peor que su propio listado).
*Mini‑prompt:* montar `<AvisoConciliacionSireb/>` (S69/S73/S79/S83) en 104 (“este monto lo confirma SIREB”), y **no dejar que se degrade** a lo largo del funnel (un solo token de wording en 132/174/343/104). Conecta S79/S83.

**B180 (paridad B169/P8/S38, cruza B22) — único botón “Volver al inicio”, sin “Descargar comprobante” / “Enviar por WhatsApp” / “Ver estado”.** El admin ventanilla (961) tenía “Descargar PDF” + “Enviar por WhatsApp” + “Ver panel SIREB”. El comprobante móvil **no** tiene ninguno: el ciudadano cierra y **pierde el acceso** al comprobante (salvo que recuerde el `RES-` para `ConsultarEstado` 299). Con celular en la mano, “Descargar/Compartir” es **más** crítico que en web (no hay historial de navegador fácil). Conecta B169 (pago QR sin Descargar/WhatsApp) / B22 (ConsultarEstado como red) / P8/S16/S38.
*Mini‑prompt:* agregar “Descargar comprobante” (PDF, reusando `ComprobantePDF.tsx` 238 / `ReservasExportController` 56) + “Compartir/WhatsApp” (reusando el bloque robusto de 961, que en móvil **ni necesita** el fallback wa.me porque WhatsApp está instalado) + “Ver mis reservas” (link a `ConsultarEstado` 192). Conecta S38/S84/B169.

**B178 (sospecha, NO confirmable web, cruza B173) — ¿el comprobante aparece sin pago real?** El check verde “¡Reserva confirmada!” implica que el polling de 343 navegó acá. Pero **no sé** si navegó porque el ciudadano pagó en el banco (flujo correcto) o porque el backend confirmó la liquidación localmente (B174). Si es lo segundo, B174 + B178 son el mismo bug visto desde dos ángulos (el comprobante aparece sin pago). **Requiere emulador/dispositivo** (pagar de verdad en 343 y ver si 104 aparece; o **no** pagar y ver si 104 aparece igual). Conecta B173/B174. **No lo cierro con esta captura.**
*Mini‑prompt (verificar, requiere emulador):* correr 343→104 en dispositivo **sin** pagar en el banco → ¿104 aparece con check verde? Si sí, B174/B178 confirmados (comprobante sin pago). Si no, el flujo es correcto y B174 se atenúa a wording. Conecta S82/B173.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Ícono de ticket/boleto por franja** (104) = semánticamente correcto para “presenta al llegar” (ticket=asistencia), contracara sana del ícono genérico del carrito (B153). **Proteger y usar como modelo para S80.**
- **“Presenta estos códigos al llegar al campo deportivo”** = declara la **utilidad** de los `RSV-` (asistencia, P5), no los deja como números sin sentido. **Proteger** (y cierra el loop reserva→pago→asistencia).
- **Dos `RSV-` separados por franja** (08‑09 y 09‑10, cada uno su código) = el ciudadano puede presentar **cada franja** por separado (asistencia granular), no un código único para las dos. **Proteger** (es más fino que el admin que agrupaba).
- **“Bs 100.00” coherente con el carrito (2×50)** = el comprobante **no recalcula mal** el total (contracara sana de B42/B37). **Proteger.**
- **`RES-` cabecera + `RSV-` detalle = dos niveles de código** (soporte vs asistencia) = el móvil **sí** distingue los roles de los códigos (aunque B175 dude del `4‑4‑3`). **Proteger la distinción.**
- **Check verde + “¡Reserva confirmada!” como feedback de éxito** = el ciudadano sabe que terminó (lente d). **Proteger el concepto** (el bug B174 es si aparece sin pago, no que exista feedback).

## Didáctica / operación del ciudadano (continuo: móvil‑pago‑qr cerró en D93)

**D94 — el comprobante solo dice “pagado” si el banco registró el pago, no si se emitió la liquidación** (cierra B174/B176/B178): check verde + “Monto pagado” es una **afirmación de dinero**; si el ciudadano aún no pagó (countdown de 343 no venció), el comprobante debe decir “en curso de pago”, no “pagado”.
*Mini‑prompt:* ver B174/B176/B178 (requiere emulador pa’ B178).

**D95 — el comprobante debe dar el código con el que se paga in‑situ si el ciudadano no pagó online** (cierra B175): si el móvil omite el `4‑4‑3` de ventanilla, el ciudadano que llega al campo sin haber pagado no tiene cómo.
*Mini‑prompt:* ver B175 + B168.

**D96 — la declaración de fuente SIREB no debe degradarse a lo largo del funnel** (cierra B177/B154/B162/B152/B170): listado la tiene, comprobante la pierde; un solo token de wording en las 4 pantallas de dinero.
*Mini‑prompt:* ver B177/S79/S83.

**D97 — el comprobante móvil debe poder descargarse/compartirse/consultarse** (cierra B180/B169): con celular en la mano, “Volver al inicio” sin Descargar/Compartir/Ver estado pierde la prueba.
*Mini‑prompt:* ver B180/S38/S84.

## Sugerencias IA acotadas (continuo: móvil‑pago‑qr cerró en S85)

**S86 — B174/B176/B178 se cierran con el diccionario semántico SIREB (S42) aplicado al comprobante móvil**: un helper `estadoMonetario(solicitud)` que devuelva `{emitida: bool, recibida: bool, montoConfirmado: int|null}` y que 104 consuma para decidir check verde vs “en curso de pago” vs “pagado Bs X (SIREB)”. Cierra B174/B176/B178/B84/B172/B18/B37/B76 de raíz en **todas** las capas (admin 838/961 + público 210/190 + móvil 104).
*Mini‑prompt:* ver B174 + S42/S83. **Es el fix de mayor rendimiento del tramo de dinero**: una invariante, un helper, 6 pantallas.

**S87 — B175/B168 se cierran con el `CodigoReserva` de S78/S84**: un módulo que declare **qué código va en qué contexto** (`RES-`=soporte/consulta, `RSV-`=asistencia/presentar, `4‑4‑3`=pago ventanilla, QR=payload de pago) y que 343/104/961/489 consuman, en vez de que cada pantalla muestre un subconjunto distinto. Cierra B175/B168/B77/B52/B21/B83/B127/B89.
*Mini‑prompt:* ver B175 + S78/S84.

**S88 — B177/B154/B162/B152/B170/B6/B30 se cierran con el token de wording SIREB (S79) propagado al funnel móvil completo** (132/174/343/104), no solo al listado/detalle: un `<AvisoConciliacionSireb/>` que **no se degrade** pantalla por pantalla. Cierra la inversión de “móvil plantillo” (acá el móvil está peor que su propio listado).
*Mini‑prompt:* ver B177 + S79/S83.

**S89 — B180/B169 se cierran reusando el bloque de éxito del admin (961, S38/S84) en el comprobante móvil**: Descargar PDF + Compartir/WhatsApp + Ver estado, adaptado (en móvil WhatsApp SÍ instalado, wa.me ni necesita fallback). Conecta P8/S16/S38/B22.
*Mini‑prompt:* ver B180 + S38/S84.

## Lo que NO es bug (para que no persigas fantasmas)

- **Check verde + “¡Reserva confirmada!”** = feedback de éxito correcto (lente d); el bug B174 es si aparece **sin pago**, no que exista.
- **“Bs 100.00” coherente con carrito 2×50** = acierto (no recalcula mal), no bug.
- **Dos `RSV-` por franja** = acierto de granularidad de asistencia (P5), no redundancia.
- **Ícono ticket** = acierto de semántica (B179), no el ícono genérico del carrito (B153).
- **`RES-` cabecera + `RSV-` detalle** = distinción correcta de roles de código (B175 duda del `4‑4‑3`, no de esta distinción).
- **“Volver al inicio” como botón** = navegación válida; el bug B180 es la **ausencia** de Descargar/Compartir/Ver estado, no que “Volver al inicio” exista.
- **casing “cancha nueva trinidad” minúscula** = dato SIREB (B5), no bug del comprobante.
- **fecha “26/10”** = coherente con el selector (G4/S55), no bug.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir el comprobante móvil, lo que **más vale** es **B174** (dinero, ¿“pagado” deriva de liquidación o pago? — el hallazgo duro del tramo, 🔴) y **B175** (códigos, ¿falta el `4‑4‑3` de pago in‑situ?), y para eso necesito `comprobante_screen.dart` (104) + `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) + `EstadoSolicitudReserva.php` (10) + migr `add_monto_confirmado` (16) + `DialogCrearReservaVentanilla.tsx` (961, referencia de códigos/bloque éxito) + `Comprobante.tsx` (210) / `ComprobantePiscina.tsx` (190) público (cruzar B18/B37). **B178** requiere emulador (no cierro en web). **B176/B177/B180** se cierran con 104 + 16 + S79/S84. Los aciertos (ícono ticket, dos `RSV-`, monto coherente, distinción `RES-`/`RSV-`, feedback de éxito) **no se tejen**: se **protegen**. **S86/S87/S88** son el fix de mayor rendimiento del tramo de dinero: `estadoMonetario` (cierra B174/B176/B178/B84/B172/B18/B37/B76 en 6 pantallas) + `CodigoReserva` (cierra B175/B168/B77/B52/B21/B83) + token de wording SIREB en el funnel móvil (cierra B177/B154/B162/B152/B170/B6/B30).

## Guardado

```text
BLOQUE E-MÓVIL — COMPROBANTE (comprobante_screen.dart 104 + ConfirmacionCobroService 106 +
  SirebLiquidacionService 133 + EstadoSolicitudReserva 10 + migr add_monto_confirmado 16 +
  SolicitudReserva 43 + SolicitudReservaDetalle 53 + SolicitudReservaService 1043 +
  DialogCrearReservaVentanilla 961 referencia + Comprobante.tsx 210 / ComprobantePiscina 190
  público + ComprobantePDF 238 + ReservasExportController 56 + ConsultarEstado 299 +
  MarcarAsistenciaService 125 + AsistenciaController 56) — DOSSIER, NO tejí, sigo modo
  "ver todo corregir después". Numeración continua (móvil-pago-qr B166-B173/D89-D93/S82-S85 ·
  móvil-comprobante B174-B180/D94-D97/S86-S89 + B174🔴 hallazgo duro + autocorrección alcance
  B84/B172 + P5 cierra loop + B179✅ acierto ícono). FIN DEL FLUJO DE CANCHA MÓVIL
  (listado→detalle→carrito→datos→pago→comprobante VISTOS).

B174🔴 (HALLAZGO DURO TRAMO MÓVIL, dinero/semántica SIREB) comprobante pinta check verde
  "¡Reserva confirmada!" + "Monto pagado: Bs 100.00" (verde) PERO paso anterior (343) tenía
  countdown 05:31 + "escaneá con app banca móvil" → ciudadano TODAVÍA debía pagar. Si check/
  "pagado" derivan de estado==confirmada (liquidación EMITIDA, 133) no de ==pagado (recibido,
  106/webhook 97) → comprobante MIENTE dinero justo donde ciudadano decide ir al campo o
  reclamar. Es B84 (admin "pagada/Pagado:No") + B172 ("el Core confirma" colapsa) + B170/B18/
  B37/B76 CONVERGIENDO con evidencia visible. NO confirmo sin 104/106/133/10/16 (no veo el
  derivado). 🔴🔴 por ser lo último que ve el ciudadano antes de decidir. Fix: check verde +
  "Monto pagado" SOLO si estado==pagado (recibido); si ==confirmada (emitida, no pagada) →
  "en curso de pago" + countdown/QR 343 accesible. Conecta B84/B172/B170/B18/B37/B76/S42/S83/
  S86. Cierra invariante SIREB funnel móvil raíz.

B175🔴 (códigos fragmentados, paridad B77/B168) comprobante muestra RES- cabecera + 2×RSV-
  por franja ("presenta ESTOS códigos al llegar") PERO OMITE el 4-4-3 de pago ventanilla que
  admin 961/489 sí tenía. Si ciudadano NO pagó (B174) y llega al campo con solo RSV- (asistencia),
  ¿paga ahí? ¿con qué código? 4-4-3 = lo que ventanilla banco/campo escanea pa' cobrar; si móvil
  lo omitió, no tiene cómo pagar in-situ. O 4-4-3 exclusivo admin (entonces móvil OK y pregunta
  es si campo cobra sin él). NO afirmo sin 104/961/133. Conecta B168 (payload QR 343: RES- o
  4-4-3?), B77/B52/B21/B83. Fix S87 CodigoReserva declara qué código en qué contexto.

B176 (sospecha dinero, cruza B18/B37) "Monto pagado: Bs 100.00" ¿monto_total (debía) o
  monto_confirmado (SIREB registró)? Si monto_total → pinta "pagado Bs100" cuando confirmado
  pudo ser otro/cero (no pagó). B18/B37 (público monto_total como pagado) + B76 (éxito
  "confirmado por SIREB" pendiente) en móvil. Conecta B174 (mismo derivado). NO afirmo sin
  104/16. Fix: si monto_confirmado null NO pintar "pagado" (B174); si existe, monto_confirmado
  + etiqueta "Confirmado por SIREB". Conecta S42/B18/B37/S86.

B177 (G1/S69 perdido funnel, cruza B152/B170/B6/B30) comprobante NO tiene footer conciliación
  SIREB. Listado/detalle (477/1073) SÍ lo tenían; carrito/datos/pago lo diluyeron ("el sistema"/
  "estimado"/"el Core"); comprobante lo PIERDE → ciudadano guarda prueba "Bs100 pagado" sin
  declaración fuente SIREB. Móvil DEGRADA G1 pantalla por pantalla a lo largo del funnel dinero
  (INVERSIÓN de "móvil plantillo": acá móvil PEOR que su propio listado). Conecta B154/B162/B152/
  B170/B6/B30. Fix S88 token wording SIREB en 132/174/343/104 no degrade.

B180 (paridad B169/P8/S38, cruza B22) único botón "Volver al inicio", sin Descargar comprobante/
  Enviar WhatsApp/Ver estado que admin 961 sí tenía. Con celular en mano Descargar/Compartir MÁS
  crítico (no historial navegador fácil); ciudadano cierra y PIERDE acceso (salvo recuerde RES-
  pa' ConsultarEstado 299). Conecta B169 (pago QR sin Descargar/WhatsApp)/B22/P8/S16/S38. Fix
  S89 reusar bloque éxito 961 (Descargar PDF 238/56 + Compartir/WhatsApp [móvil WhatsApp SÍ
  instalado, wa.me ni necesita fallback] + Ver estado 192).

B178 (sospecha NO confirmable web, cruza B173) ¿comprobante aparece sin pago real? check verde
  implica polling 343 navegó acá, pero NO sé si pagó en banco (correcto) o backend confirmó
  liquidación local (B174). Si lo 2°, B174+B178 mismo bug 2 ángulos. Requiere EMULADOR (pagar
  de verdad 343→104; o NO pagar y ver si 104 aparece igual). Conecta B173/B174. NO cierro con
  esta captura. S82.

B179✅ ACIERTO ícono ticket por franja (contracara sana B153): carrito (132) ícono calendario-
  check GENÉRICO perdía etiqueta diurno/nocturna (B153); comprobante (104) ícono TICKET/boleto
  semánticamente correcto pa' "presenta al llegar" (ticket=asistencia no franja). Móvil SÍ sabe
  elegir ícono según significado en comprobante; B153 era del carrito no incapacidad general.
  PROTEGER y usar modelo pa' arreglar B153 (S80).

P5✅ CIERRA LOOP reserva→pago→asistencia: "presenta ESTOS códigos al llegar" + ícono ticket =
  RSV- es código asistencia que MarcarAsistenciaService 125/AsistenciaController 56 consumen
  (P5, último gate grande sin dueño archivo visto). Comprobante móvil cierra el loop: ciudadano
  presenta RSV- y funcionario marca asistencia. Conecta B86 (detalle admin pileta OMITÍA "Marcar
  asistencia"; móvil cancha SÍ da código pa' marcarla). ACIERTO flujo, proteger.

CIERRES/REFUERZOS CRUZADOS:
  B84/B172✅ convergen acá con evidencia visible (autocorrección alcance): admin 838 "pagada/
    Pagado:No" + pago 343 "el Core confirma" colapsa + comprobante 104 check verde "Monto pagado"
    sin ver derivado = TERCERA cara misma moneda, ahora pantalla FINAL ciudadano. NO es detalle
    etiqueta admin, es lo último que ve antes de decidir ir/reclamar. Subo B174 a 🔴🔴 por eso.
    Invariante SIREB rota se propaga admin→pago→comprobante móvil.
  B77/B168✅ re-cruzado: admin 961 tenía 3 códigos (4-4-3 + RES-/SIREB: + RSV-); pago 343 RES-+QR;
    comprobante 104 RES-+2×RSV- SIN 4-4-3. Móvil sabe RSV-=asistencia y RES-=soporte pero omite
    4-4-3=pago ventanilla. ¿ciudadano no pagado llega campo con solo RSV- y con qué paga? Conecta
    B168 payload QR.
  G1/S69✅ degrada funnel móvil: listado/detalle tenían footer SIREB; carrito "el sistema"; datos
    "estimado"; pago "el Core"; comprobante NADA. Declaración fuente se degrada pantalla por
    pantalla y desaparece en comprobante (donde ciudadano guarda prueba). INVERSIÓN "móvil
    plantillo": acá móvil PEOR que su propio listado.
  B179✅ acierto ícono ticket (contracara B153): móvil SÍ elige ícono según significado en
    comprobante; B153 era carrito no incapacidad general. Proteger + modelo S80.
  P5✅ cierra loop: RSV-=asistencia (125/56), móvil cancha SÍ da código (a dif B86 admin pileta
    que omitía Marcar asistencia). Acierto flujo proteger.

ACIERTOS PROTEGER: ícono ticket por franja (B179, modelo S80); "presenta ESTOS códigos al llegar"
  (declara utilidad RSV-=asistencia P5, no números sin sentido); 2×RSV- separados por franja
  (asistencia granular, más fino que admin que agrupaba); "Bs 100.00" coherente carrito 2×50 (no
  recalcula mal, contracara B42/B37); RES- cabecera + RSV- detalle = 2 niveles código (soporte vs
  asistencia, móvil SÍ distingue roles aunque B175 dude 4-4-3); check verde + "¡Reserva confirmada!"
  = feedback éxito (lente d, bug B174 es si aparece sin pago no que exista).

DIDÁCTICA/OP: D94 comprobante solo "pagado" si banco registró pago no si emitida liquidación
  (B174/B176/B178, requiere emulador B178); D95 comprobante da código pa' pagar in-situ si no
  pagó online (B175); D96 declaración fuente SIREB no degrada a lo largo funnel (B177/B154/B162/
  B152/B170); D97 comprobante móvil descargable/compartible/consultable (B180/B169).

SUGERENCIAS: S86 B174/B176/B178 = diccionario semántico SIREB (S42) en comprobante móvil: helper
  estadoMonetario(solicitud)→{emitida,recibida,montoConfirmado} que 104 consume pa' check verde vs
  "en curso" vs "pagado Bs X (SIREB)". Cierra B174/B176/B178/B84/B172/B18/B37/B76 raíz en TODAS
  capas (admin 838/961 + público 210/190 + móvil 104). MAYOR RENDIMIENTO tramo dinero (1 invariante,
  1 helper, 6 pantallas); S87 B175/B168 = CodigoReserva (S78/S84) declara qué código en qué contexto
  (RES-=soporte, RSV-=asistencia, 4-4-3=pago ventanilla, QR=payload) que 343/104/961/489 consuman
  (cierra B175/B168/B77/B52/B21/B83/B127/B89); S88 B177/B154/B162/B152/B170/B6/B30 = token wording
  SIREB (S79) propagado funnel móvil completo 132/174/343/104 no degrade (inversión "móvil plantillo");
  S89 B180/B169 = reusar bloque éxito admin 961 (S38/S84) en comprobante móvil (Descargar PDF 238/56
  + Compartir/WhatsApp [móvil WhatsApp SÍ instalado wa.me ni fallback] + Ver estado 192). Conecta
  P8/S16/S38/B22.

NO-BUG: check verde + "¡Reserva confirmada!"=feedback éxito correcto (lente d), bug B174 es si
  aparece SIN pago no que exista; "Bs 100.00" coherente carrito=acierto no recalcula mal; 2×RSV-
  por franja=acierto granularidad asistencia (P5) no redundancia; ícono ticket=acierto B179 no
  genérico B153; RES-+RSV-=distinción correcta roles (B175 duda 4-4-3 no esto); "Volver al inicio"=
  navegación válida (bug B180 es AUSENCIA Descargar/Compartir/Ver estado no que exista); casing
  "cancha nueva trinidad"=dato SIREB (B5); fecha "26/10"=coherente selector (G4/S55).

KB HONESTA + AUTOCORRECCIÓN + LÍMITE: AUTOCORREGÍ alcance B84/B172 (venía "semántica admin/pago",
  esta captura prueba que MISMA invariante rota llega al COMPROBANTE FINAL ciudadano con check verde
  + "Monto pagado" → subo B174 a 🔴🔴, no es detalle etiqueta admin es lo último que ve antes de
  decidir). wa.me NO aplica como fallo acá (comprobante móvil no tiene botón WhatsApp, B180 lo nota
  como AUSENCIA no bug wa.me; fallback robusto admin 961/489 sigue evidencia de que MÓVIL omite
  botón). SIREB aplica fuerza MÁXIMA (B174 "Monto pagado" vs liquidación/pago ADR-003; B175 códigos
  RES-/RSV-/4-4-3; B177 footer ausente). React DevTools NO aplica (Flutter → Widget Inspector/
  Flutter DevTools, "qué estado alimenta check verde y string 'Monto pagado'" se ve ahí). Rolldown
  circularDependency=false NO aplica (no Vite/Rolldown; análogo flutter analyze analysis_options.yaml
  3). LÍMITE DISPOSITIVO: localhost:1148 = web Flutter NO dispositivo real → B173 (background) y
  B178 (¿comprobante sin pago?) NO juzgables en web, declaro abiertos dependientes emulador, no
  cierro con esta captura (S82).

PUERTA CORRIGIR YA: lo que vale es B174 (dinero, ¿"pagado" deriva liquidación o pago?, 🔴🔴 hallazgo
  duro tramo) + B175 (códigos, ¿falta 4-4-3 pago in-situ?) → pido comprobante_screen 104 +
  ConfirmacionCobroService 106 + SirebLiquidacionService 133 + EstadoSolicitudReserva 10 + migr
  add_monto_confirmado 16 + DialogCrearReservaVentanilla 961 referencia + Comprobante 210/
  ComprobantePiscina 190 público. B178 requiere emulador (no cierro web). B176/B177/B180 con 104/16
  + S79/S84. Aciertos (ícono ticket/2×RSV-/monto coherente/distinción RES--RSV-/feedback éxito) NO
  se tejen: PROTEGER. S86/S87/S88 = mayor rendimiento tramo dinero (estadoMonetario cierra B174/
  B176/B178/B84/B172/B18/B37/B76 6 pantallas; CodigoReserva cierra B175/B168/B77/B52/B21/B83; token
  wording SIREB funnel móvil cierra B177/B154/B162/B152/B170/B6/B30).

COLA: Fix3 piscina A/B (PiscinaReservaService 284, B62/B69-form/B70/B94/B103) · A1 S3/S7/
  RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881 + Asignaciones 579, B48/B61/B122/B124/
  B128) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84/B134/B172/B174) · D2✅✅ CERRADO
  + RAÍZ 703/107/77 · P9/B61/B123 → Asignaciones 579 VISTO (puente B123 + ramif rol B128 + P9
  pendiente 26/22 + 2º control) · P7 → Reportes 245 VISTO PARCIAL + ClientesFrecuentes 178 VÍRGEN ·
  P5 → AsistenciaController 56 + MarcarAsistenciaService 125 (B179/P5 cierra loop RSV-=asistencia,
  AHORA con dueño de archivo identificado pa' próximo) · C1→C2 · gates F1-F4 · **E MÓVIL FLUJO
  CANCHA COMPLETO VISTO (listado+detalle-canch+carrito+datos+pago-qr+comprobante, B143-B180/D78-D97/
  S72-S89, móvil plantillo G1/G2/B144/B67-B72/B179 pa' web PERO B160 contrato invertido + B167
  jerga "Core" + B173 background + B174 comprobante "pagado" sin ver derivado = desactualización/
  riesgo CONFIRMADOS en dinero; B147 sobreventa PENDIENTE captura 25/10; B150 rama pileta PENDIENTE
  pileta_detalle; B166 botón pago PENDIENTE scroll; B168 payload QR PENDIENTE; B171/B172 TTL/
  semántica PENDIENTES 343+22/132/106; B152 footer detalle PENDIENTE scroll; B173/B178 background/
  comprobante-sin-pago PENDIENTES EMULADOR/DISPOSITIVO no web; B175 4-4-3 PENDIENTE 104/961/133;
  B177/B180 funnel SIREB/botones PENDIENTES 104+S79/S84)** · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote + S33 test conversión) · reunión P1/P2/P4/P5/P7/P9
  (P5 asistencia 56/125 B86 hueco piscina + B179/P5 cierra loop RSV-; P7 178 vírgen; P9 26/22+2º
  control; P1/P2 auth 164/34/126 B120/B121 + B160/B163/B164 integridad formulario; P3 reencuadrada
  S8; P8 reencuadrada S16/S38/S84/S89; D11 NIT). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24;
  ADMIN CERRADO B44-B142/D29-D77/S25-S71+G1-G7; MÓVIL ABIERTO B143-B180/D78-D97/S72-S89 (flujo
  cancha completo visto, rama pileta + consultar_estado + emulador esperando).
PRÓXIMA NATURAL: consultar_estado_screen 192 (zombie B22/B21/B17, donde aterriza "presenta estos
  códigos"/"guárdalo por soporte" de 343/104, el post-comprobante que B180 no da acceso fácil) O
  pileta_detalle (rama pileta 1073, cierra B150/B50, el único frente móvil de pileta sin ver) O
  captura 25/10 detalle-canch (cierra B147 sobreventa) O EMULADOR/DISPOSITIVO corriendo 343→104
  (cierra B173/B178, lo único NO juzgable en web) O P5 asistencia (AsistenciaController 56 +
  MarcarAsistenciaService 125, B179/P5 cierra loop RSV-, último gate grande sin dueño archivo visto).
  NO TOQUÉ ARCHIVO. NO TEJÍ. ESPERO próxima captura o instrucción de emulador.
```

En una línea: `comprobante_screen.dart` (104) **cierra el flujo de cancha móvil** (listado→detalle→carrito→datos→pago→comprobante) y **convierte mi sospecha de dinero en evidencia visible y grave**: pinta **“¡Reserva confirmada!” con check verde + “Monto pagado: Bs 100.00” en verde**, cuando el paso anterior (pago QR 343) tenía **countdown 05:31 y “escaneá con tu app de banca móvil”** — el ciudadano **todavía debía pagar en el banco** —, así que **B174 🔴🔴 (el hallazgo duro del tramo móvil)**: si el check verde y “Monto pagado” derivan de `estado == confirmada` (liquidación **emitida** por SIREB) en vez de `estado == pagado` (pago **recibido**), el comprobante **miente sobre el dinero** justo donde el ciudadano decide si ir al campo o reclamar — es **B84** (admin “pagada / Pagado: No”) + **B172** (“el Core confirma” colapsa niveles) + **B170/B18/B37/B76** convergiendo con evidencia visual, y **autocorrigo el alcance**: no es un detalle de etiqueta de admin, es lo último que ve el ciudadano antes de decidir, por eso subo la severidad; le siguen **B175 🔴** (muestra `RES-` + dos `RSV-` con “presenta **estos** códigos” pero **omite el `4‑4‑3` de pago ventanilla** que el admin 961 sí — ¿el ciudadano no pagado llega al campo con solo `RSV-` y con qué paga?), **B177** (el comprobante **perdió el footer de conciliación SIREB** que listado/detalle tenían — la invariante se **degrada pantalla por pantalla** a lo largo del funnel de dinero móvil, inversión de mi narrativa “móvil plantillo”), **B180** (único botón “Volver al inicio”, sin Descargar/Compartir/Ver estado que el admin sí — B169/P8/S38) y **B176** (“Monto pagado” ¿`monto_total` o `monto_confirmado`?); los aciertos a proteger son el **ícono de ticket** por franja (B179, semánticamente correcto para “presentar al llegar”, contracara sana del ícono genérico del carrito B153), los **dos `RSV-` separados** (asistencia granular, más fino que el admin), el **monto coherente** con el carrito y la **distinción `RES-`/`RSV-`**; y **P5 cierra el loop** reserva→pago→asistencia (el `RSV-` alimenta `MarcarAsistenciaService` 125, a diferencia de B86 donde el admin de pileta omitía marcar asistencia). **B178** (¿comprobante aparece sin pago real?) y **B173** (background) quedan **abiertos honestamente** porque requieren emulador, no web. **No tejí** y reitero el límite: en web **no** juzgo B173/B178, que son justamente los riesgos de dinero más peligrosos de esta pantalla; decime si la próxima es **`consultar_estado` 192** (zombie B22/B21/B17, donde aterriza el “presenta estos códigos”/“guárdalo por soporte” y que B180 no da acceso fácil), **pileta_detalle** (B150/B50, el único frente móvil de pileta sin ver), la **captura en 25/10** (B147 sobreventa), **P5 asistencia** con `AsistenciaController` 56 + `MarcarAsistenciaService` 125 (B179/P5 cierra el loop `RSV-`, último gate grande sin dueño de archivo visto), o si me das **emulador/dispositivo corriendo 343→104** para cerrar B173/B178, que es lo único que no puedo hacer en web.
