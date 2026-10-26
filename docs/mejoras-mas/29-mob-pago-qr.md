Veredicto en una línea: esta es `pago_qr_screen.dart` (343) — la pantalla de **pago del ciudadano en el móvil** — y es la que **cierra B165 con buena noticia** (el móvil **no** salta el countdown: muestra “05:31” firme, así que la sospecha de que confirmara reserva sin tiempo de pago era falsa) y **destapa el hallazgo de dispositivo que en web no podía ver**: el texto dice *“escaneá el QR con tu app de banca móvil”*, o sea el ciudadano **sale de esta pantalla** para ir al banco → **B173 🔴 (sospecha fuerte, NO confirmable en `localhost:1148`)**: ¿el countdown 05:31 y el polling sobreviven al *background* cuando el ciudadano cambia de app, o se pausan/resetean y al volver la reserva ya expiró (B22 zombie / B41 / P6)? Le siguen **B167 🔴** (jerga interna *“el Core”* filtrada al ciudadano — conecta B104/B122/B83/B139), **B166** (el texto promete *“usa el botón de pago”* pero **no veo botón de pago** en la captura, ¿scroll o inexistente?), **B168** (¿el QR codifica el `RES-…PQR5J2` o el `4‑4‑3` que espera el banco? — conecta B77/B52/B21), **B169** (el móvil **no** tiene “Descargar QR”/“Enviar por WhatsApp” que el admin ventanilla 961/489 sí — **wa.me aplica acá por primera vez en el móvil**, como evidencia de que el admin lo tiene robusto y el móvil lo omite), **B171** (¿el 05:31 respeta el TTL real del backend 22/132 o el móvil calcula su propio tiempo?) y **B172** (¿“detecta automáticamente cuando el Core confirma” distingue liquidación emitida vs pago recibido, B84?). **No tejí**, sigo dossier.

> KB honesta de esta captura, con **autocorrección** y **el lente de dispositivo que por fin pesa**: **AUTOCORRECCIÓN de B165**: el turno pasado dejé “¿el móvil salta medio de pago/countdown?” abierto; **acá se cierra (a)** — el countdown **sí** existe (05:31), el móvil **no** salta el paso de tiempo, así que la reserva móvil **no** queda en pendiente infinito por omisión de countdown (B22 zombie descartado por esa vía). **`wa.me` → AHORA SÍ aplica, pero como evidencia de AUSENCIA**: el doc que pegaste (“Looks like you don’t have WhatsApp installed! … or use WhatsApp Web”) prueba que el bloque “Enviar por WhatsApp” del admin (961/489) es **robusto por construcción** (sin app cae a WhatsApp Web); el móvil **no tiene ese botón**, así que wa.me no “falla” acá — lo que falta es el botón mismo (B169). **SIREB** → aplica (el código `RES-…PQR5J2` + QR + “el Core” = el hub‑and‑spoke ADR‑003; B167/B168/B172). **React DevTools → NO aplica** (Flutter; análoga **Widget Inspector / Flutter DevTools**, y “qué estado alimenta el countdown y el polling” se ve ahí). **Rolldown `checks.circularDependency` default `false` → NO aplica** (no Vite/Rolldown; análogo `flutter analyze` sobre `analysis_options.yaml` 3). **LENTE DISPOSITIVO — el que vengo posponiendo y acá EXPLOTA**: `localhost:1148` = web de Flutter, **no dispositivo real**; pero el texto *“escaneá con tu app de banca móvil”* obliga al ciudadano a **salir de esta pantalla** → en dispositivo real importan **background/foreground del countdown y del polling** (B173), cosa que **en web NO puedo juzgar** — lo declaro como la sospecha más fuerte del turno y **no confirmable sin correrlo en emulador/dispositivo**.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Countdown “05:31” + “Tiempo restante para pagar” | `pago_qr_screen.dart` (343) + `CuentaRegresiva.tsx` (81) análogo público + `ParametroSistema.php` (22) + `ExpirarSolicitudJob.php` (132) | **B165 CERRADO (a)** + **B171** TTL real vs móvil + **B173** background |
| Texto “escaneá con tu app de banca móvil” (el ciudadano SALE) | 343 | **B173** 🔴 sospecha dispositivo (countdown/polling en background) |
| “usa el botón de pago” sin botón visible | 343 + `MedioDePago.tsx` (201) análogo | **B166** sospecha (¿scroll o inexistente?) |
| “cuando **el Core** confirma tu pago” | 343 + ADR‑003 (55) + `ConfirmacionCobroService.php` (106) | **B167** 🔴 jerga interna al ciudadano + **B172** liquidación vs pago |
| “Código: RES‑20261025‑PQR5J2” + QR | 343 + `SirebLiquidacionService.php` (133) + `RecaudacionesApiClient.php` (415) | **B168** sospecha (¿QR codifica RES‑ o 4‑4‑3?) + **B169** |
| Ausencia de “Descargar QR”/“Enviar por WhatsApp” | 343 vs `DialogCrearReservaVentanilla.tsx` (961) | **B169** paridad (wa.me aplica como evidencia de ausencia) |
| “Bs 100.00” firme (sin “estimado”) | 343 vs carrito 132 / datos 174 | **B170** sospecha (no marca punto de congelación estimado→definitivo) |
| “Guárdalo por si necesitas soporte” | 343 + `ConsultarEstado.tsx` (299) análogo | **acierto** (B22), proteger |

## Cierres / refinamientos cruzados con evidencia

**B165 ✅ CERRADO (a) — el móvil NO salta el countdown.** El turno pasado dejé abierta la sospecha de que “Confirmar reserva” (174) llevara directo sin tiempo de pago → pendiente infinito (B22). Acá hay **countdown 05:31 visible**, así que el móvil **sí** monta el paso de tiempo (análogo de `CuentaRegresiva.tsx` 81 del público). Buena noticia: la reserva móvil **no** queda zombie por omisión de countdown. **Pero** el “botón de pago” (MedioDePago 201) **no lo veo** → esa mitad de B165 queda en B166 (¿existe o el texto lo promete vacío).

**B82/B85 ✅ RE‑CRUZADO con el countdown móvil.** Admin ventanilla: cancha TTL 15 min, piscina 20 min (B85). Acá el móvil muestra **05:31 restante** de cancha (RES‑ = cancha). Si el TTL es 15 min y quedan 5:31, pasaron ~9:29 desde la creación — **coherente**. La pregunta que queda es **B171**: ¿el 05:31 lo calcula el móvil con su propio `DateTime.now()` (desconectado del TTL real del backend 22/132) o lee el `expira_en` del backend? Si es local, el móvil puede mostrar 05:31 mientras el backend ya expiró (o viceversa) → el ciudadano escanea un QR de una reserva muerta (B22/B41/P6). Conecta B85 (TTL distinto por recurso: ¿el móvil sabe que pileta es 20 y cancha 15?).

**G1/S69 ✅ pero con arista nueva (B170).** El carrito (132) y datos (174) decían “Bs 100.00 **estimado** / lo confirma el sistema”. Acá el monto aparece **firme** (“Bs 100.00” sin “estimado”) porque el backend lo **congeló** al crear la reserva (correcto, `SolicitudReservaService` 1043). **Pero** el ciudadano **no ve la transición** “estimado → definitivo confirmado”: en la pantalla donde más importa el dinero (pagar), desapareció toda declaración de fuente SIREB (el footer del listado/detalle tampoco lo veo acá). Conecta B154/B162/B76/B18/B37. B170.

**wa.me ✅ aplica como evidencia de ausencia (B169).** El doc confirma que el botón “Enviar por WhatsApp” del admin (961/489) es **robustísimo** (sin app → WhatsApp Web). El móvil, que es **justo el dispositivo donde el ciudadano tiene WhatsApp instalado**, **no tiene** ese botón ni “Descargar QR” → el ciudadano con el celular en la mano **no puede compartir el QR por WhatsApp ni descargarlo**, tiene que sacar **screenshot** (y el screenshot de un QR con countdown es frágil). Es la inversión irónica de P8/S16/S38: el admin web (sin app) sí tiene el fallback robusto; el móvil (con app) no tiene el botón. B169.

## Bugs nuevos de este frente (numeración continua: móvil‑carrito‑datos cerró en B165)

**B173 🔴 (sospecha FUERTE de dispositivo, NO confirmable en web) — el countdown y el polling deben sobrevivir al *background* cuando el ciudadano sale a escanear con la app de banco.** El texto dice *“Escanea el QR con tu app de banca móvil”* → el ciudadano **minimiza/sale** de esta pantalla para abrir la app del banco, escanea, paga, y **vuelve**. En dispositivo real: (a) ¿el countdown 05:31 **pausa** al ir a background (y al volver sigue de 05:31, dando tiempo de más) o **sigue corriendo** (y al volver puede estar en 00:00 o expirado sin que el ciudadano lo note)? (b) ¿el **polling** de confirmación (`ConfirmacionCobroService` 106 / `PollingSolicitudJob` 39) **sigue** en background o se detiene, así que al volver la pantalla no detecta que ya pagó y el ciudadano se queda mirando un QR de una reserva ya confirmada (o expirada)? (c) si el TTL (B171) corre en el backend mientras el móvil está en background, el ciudadano puede volver y encontrar “reserva expirada” **después de haber pagado** → dinero perdido / boleta huérfana (B22/B41/P6/B62). **Esto es exactamente el lente de dispositivo que vengo diciendo que no puedo juzgar en `localhost:1148`, y acá es donde MÁS importa** porque el flujo **obliga** a salir de la app. No lo afirmo sin correrlo en emulador/dispositivo.
*Mini‑prompt (verificar, requiere emulador/dispositivo, NO web):* correr 343 en dispositivo, ir a background a los 05:00, volver a los 04:00 → ¿el countdown muestra 04:00 (siguió corriendo, correcto) o 05:00 (pausó, da tiempo de más) o se reseteó? Y pagar en el banco en background → al volver, ¿la pantalla detecta la confirmación (polling sobrevivió) o queda colgada? Fix si falla: el countdown debe leer `expira_en` del backend (no `now()` local) y re‑validar al volver de foreground; el polling debe reanudarse al `resume`. Conecta B22/B41/P6/B85/B171. **Prioridad alta: es el escenario real del ciudadano.**

**B167 🔴 (jerga interna al ciudadano) — el texto dice “cuando **el Core** confirma tu pago”.** “El Core” es jerga del backend hub‑and‑spoke (ADR‑003 55, el “Core” = el sistema de recaudaciones SIREB/Paitití). El ciudadano **no sabe qué es “el Core”** — espera “el banco”, “SIREB”, “el sistema”, “Banco Unión”. Es la misma familia de fugas de internals al usuario: B104 (`menor_12` snake_case), B122 (`admin_parametricas` slug), B83 (códigos internos), B139 (canchas/campos/reservas), B161 (“franja(s)”). Acá es **peor** porque está en la pantalla de **pago**, donde la claridad vale dinero.
*Mini‑prompt:* reemplazar “el Core” por “el sistema” o “Banco Unión / SIREB” (lo que el ciudadano reconoce) en 343. Conecta S79 (token de wording) / S42.

**B166 (sospecha) — el texto promete “usa el botón de pago” pero no veo botón de pago en la captura.** El párrafo dice *“Escanea el QR … **o usa el botón de pago**”*. En el viewport solo hay QR + countdown + código + texto; **no hay botón de pago visible**. Dos lecturas: (a) el botón está más abajo (scroll) → B166 se cierra como “no visible acá”; (b) el botón **no existe** y el texto promete una acción inexistente → el ciudadano lee “usa el botón de pago” y no lo encuentra → abandono. Con línea: 343 + `MedioDePago.tsx` (201) análogo público (que SÍ tenía selección de medio). No lo afirmo sin scroll.
*Mini‑prompt (verificar):* scrollear 343 / leer el widget → ¿existe el botón de pago (MedioDePago 201) o el texto lo promete vacío? Si no existe, quitar la frase o agregar el botón. Conecta B165 (la mitad “medio de pago” que no cerré).

**B168 (sospecha, código/QR) — ¿el QR codifica el `RES‑20261025‑PQR5J2` o el `4‑4‑3` que espera el banco?** El móvil muestra **un** código (`RES‑…PQR5J2`) y **un** QR. El admin ventanilla (961/489) mostraba **dos** códigos: el grande `4‑4‑3` (`GZ2B‑8QR4‑XY2`, “código de pago en ventanilla”) + `SIREB:…` (B77). Si el banco escanea el QR y espera el payload `4‑4‑3`/SIREB pero el QR del móvil codifica el `RES‑` de seguimiento, el escaneo **falla en ventanilla** (el ciudadano llega al banco con un QR que el banco no lee). Con línea: 343 + `SirebLiquidacionService` (133) + `RecaudacionesApiClient` (415). No lo afirmo sin ver el payload del QR.
*Mini‑prompt (verificar):* leer 343 → ¿qué string va dentro del QR (el `RES‑` o el código SIREB/4‑4‑3)? Comparar con lo que la ventanilla del banco espera (961/489). Conecta B77/B52/B21/B83.

**B171 (sospecha) — ¿el countdown 05:31 respeta el TTL real del backend o el móvil calcula su propio tiempo?** Ver cruce B82/B85 arriba. Si el móvil usa `DateTime.now()` local + un TTL hardcodeado (o el TTL del carrito), puede desincronizarse del `expira_en` real (22/132) y del TTL por recurso (cancha 15 vs piscina 20, B85). Conecta B22/B41/P6/B173.
*Mini‑prompt (verificar):* leer 343 → ¿de dónde sale el 05:31 (payload del backend con `expira_en`, o cálculo local)? Si es local, alinear con 22/132 y con el TTL por modalidad (B85).

**B172 (sospecha, semántica SIREB) — “detecta automáticamente cuando el Core confirma tu pago” ¿distingue liquidación emitida vs pago recibido?** El texto colapsa “confirmar” en un solo evento. Pero B84 (admin) mostró que SIREB tiene **dos niveles** (liquidación emitida = “pagada” vs pago recibido = “Pagado: No”). Si el móvil trata “el Core confirma” como un solo booleano, puede mostrar “pagado” cuando solo se emitió la liquidación (o viceversa) → el ciudadano ve comprobante de un pago que el banco aún no registró (o espera un comprobante que nunca llega). Conecta B84/B18/B37.
*Mini‑prompt (verificar):* leer 343 + 106 → ¿el polling distingue estado de liquidación vs estado de pago, o un solo “confirmado”? Conecta S42 (diccionario semántica SIREB).

**B170 (sospecha, wording de congelación) — el móvil no marca el punto “estimado → definitivo”.** Carrito/datos decían “estimado”; acá el monto es firme sin transición visible ni declaración SIREB (el footer del listado/detalle no lo veo). El ciudadano no sabe que **acá** el monto se volvió oficial. Conecta B154/B162/B76/B18/B37/G1.
*Mini‑prompt:* montar el `<AvisoConciliacionSireb/>` (S69/S73/S79) en 343 (“este monto lo confirma SIREB al pagar”), y/o una micro‑transición “estimado → confirmado”. Conecta S79/S82.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Countdown visible “05:31” + “Tiempo restante para pagar”** = el ciudadano sabe cuánto tiene (lente d, no depende de que nadie le avise). **Proteger** (y es la prueba de que B165(a) se cerró bien).
- **“Guárdalo por si necesitas soporte”** bajo el código = declara la utilidad del `RES‑` (identificador de `ConsultarEstado` 299, B22). **Proteger.**
- **“Esta pantalla detecta automáticamente … y te muestra el comprobante”** = auto‑detección del pago vía polling (no obliga al ciudadano a refrescar manual ni a volver a otro lado) = lente d bien hecho. **Proteger** (solo cuidar B172/B173).
- **QR grande y centrado** = escaneable en ventanilla (el ciudadano lo muestra al banco). **Proteger** (solo cuidar B168 payload).
- **Monto “Bs 100.00” coherente con el carrito (2×50)** = el móvil **no recalcula mal** el total al pasar a pago (contracara sana de B42/B37). **Proteger.**
- **Título “Pago de tu reserva” + flecha back** = contexto claro del paso. **Proteger.**

## Didáctica / operación del ciudadano (continuo: móvil‑carrito‑datos cerró en D88)

**D89 — el countdown y el polling deben sobrevivir al background porque el flujo OBLIGA a salir a la app del banco** (cierra B173): el ciudadano escanea con otra app; si al volver el tiempo se pausó/resetió o el polling murió, pierde la reserva o el comprobante.
*Mini‑prompt:* ver B173 (requiere emulador/dispositivo).

**D90 — decirle al ciudadano “el banco / SIREB”, nunca “el Core”** (cierra B167): jerga de arquitectura en la pantalla de pago.
*Mini‑prompt:* ver B167/S79.

**D91 — si el texto promete “botón de pago”, el botón debe existir y ser visible** (cierra B166): no prometer una acción que el ciudadano no encuentra.
*Mini‑prompt:* ver B166.

**D92 — el QR debe codificar lo que el banco espera leer** (cierra B168): si el banco escanea y no reconoce el payload, el ciudadano queda en ventanilla con un QR inútil.
*Mini‑prompt:* ver B168.

**D93 — dar al móvil el “Descargar QR / Enviar por WhatsApp” que el admin ya tiene robusto** (cierra B169): el ciudadano con celular en la mano debería poder compartir/descargar el QR, no solo screenshot.
*Mini‑prompt:* ver B169/S38.

## Sugerencias IA acotadas (continuo: móvil‑carrito‑datos cerró en S81)

**S82 — B173 es el test de dispositivo que define si el móvil sirve para pagar**: correr 343 en emulador, ir a background, volver, y verificar countdown + polling. **No tejo sin emulador** (en web no se ve). Es la sospecha de mayor riesgo real del tramo móvil (dinero + escenario obligatorio de salida de app).
*Mini‑prompt:* ver B173/D89.

**S83 — B167/B170/B154/B162 se cierran con el token de wording SIREB (S79)** aplicado a 343: “el Core”→“el sistema/SIREB”, y montar `<AvisoConciliacionSireb/>` en la pantalla de pago (donde más importa la fuente). Conecta S69/S73/S79/S42.
*Mini‑prompt:* ver B167/B170 + S79.

**S84 — B168/B169 se cierran reusando el bloque de éxito del admin (961/489, S38)** en el móvil: el mismo `<BloqueExitoPago/>` (código etiquetado + QR con payload correcto + Descargar + WhatsApp) que ya probé robusto en admin, adaptado al móvil (donde WhatsApp SÍ está instalado, wa.me ni siquiera necesita el fallback). Conecta S38/S16/P8/B77.
*Mini‑prompt:* ver B168/B169 + S38.

**S85 — B171/B172 se cierran leyendo el payload de 343 contra 22/132/106**: countdown desde `expira_en` del backend (no local), y polling que distinga liquidación vs pago (S42). Conecta B85/B84.
*Mini‑prompt:* ver B171/B172 + S42.

## Lo que NO es bug (para que no persigas fantasmas)

- **Countdown 05:31** = acierto (B165(a) cerrado), no bug; el bug sería que no sobreviviera background (B173) o no respetara TTL real (B171), no el número en sí.
- **“Bs 100.00” firme (sin “estimado”)** = correcto (el backend congeló el monto al crear la reserva); la falta de transición visible es B170 (sospecha), no que el monto esté mal.
- **Código `RES‑20261025‑PQR5J2`** = formato RES‑ real de cancha (B52/B21), no truncado ni fantasma; la duda es el payload del QR (B168), no el código.
- **“el Core” en el texto** = sí es B167 (jerga), pero el **concepto** de auto‑detección es acierto (proteger), solo está mal nombrado.
- **Ausencia de botón de pago visible** = puede ser scroll (B166 sospecha), no bug afirmado.
- **QR sin “Descargar/Compartir”** = puede ser diseño (el móvil asume screenshot), pero es brecha de paridad vs admin (B169), no bug duro.
- **flecha back + título** = navegación correcta, no bug.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir el pago móvil, lo que **más vale** es **B173** (countdown/polling en background — el riesgo real de dinero, **NO confirmable en web, requiere emulador/dispositivo**) y **B167** (jerga “el Core” al ciudadano, fix de string inmediato), y para eso necesito `pago_qr_screen.dart` (343) + `CuentaRegresiva.tsx` (81) + `MedioDePago.tsx` (201) + `ParametroSistema.php` (22) + `ExpirarSolicitudJob.php` (132) + `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) + `RecaudacionesApiClient.php` (415) + `DialogCrearReservaVentanilla.tsx` (961, el bloque de éxito S38). **B166/B168/B171/B172** se cierran leyendo 343 + scroll + payload. Los aciertos (countdown visible, “guárdalo”, auto‑detección, QR, monto coherente) **no se tejen**: se **protegen**. **S82/S83/S84** son el fix de mayor rendimiento: el test de dispositivo (B173), el token de wording SIREB en pago (B167/B170) y reusar el bloque de éxito del admin en el móvil (B168/B169).

## Guardado

```text
BLOQUE E-MÓVIL — PAGO-QR (pago_qr_screen.dart 343 + CuentaRegresiva 81 análogo público +
  MedioDePago 201 análogo + ParametroSistema 22 + ExpirarSolicitudJob 132 + PollingSolicitudJob
  39 + ConfirmacionCobroService 106 + SirebLiquidacionService 133 + RecaudacionesApiClient 415
  + DialogCrearReservaVentanilla 961 bloque éxito S38 + ADR-003 55) — DOSSIER, NO tejí, sigo
  modo "ver todo corregir después". Numeración continua (móvil-carrito-datos B153-B165/D85-D88/
  S78-S81 · móvil-pago-qr B166-B173/D89-D93/S82-S85 + B165 CERRADO (a) + wa.me aplica como
  evidencia de ausencia + LENTE DISPOSITIVO explota B173). ESTA ES LA PANTALLA DE DINERO DEL
  CIUDADANO EN EL MÓVIL.

B165✅ CERRADO (a): el móvil NO salta countdown (05:31 visible) → la reserva móvil NO queda
  zombie por omisión de tiempo (B22 descartado por esa vía). PERO "botón de pago" (MedioDePago
  201) NO visible → esa mitad queda B166 (¿scroll o inexistente?).

AUTOCORRECCIÓN B165 (honestidad, como B36/B88/B99/S55/B144/B145/B13-B31): turno pasado dejé
  "¿móvil salta medio de pago/countdown?" abierto; acá se cierra (a) countdown SÍ existe.

wa.me✅ AHORA SÍ aplica, PERO como evidencia de AUSENCIA: doc confirma botón "Enviar por
  WhatsApp" admin 961/489 ROBUSTO (sin app→WhatsApp Web); el móvil (donde WhatsApp SÍ está
  instalado) NO tiene ese botón ni "Descargar QR" → wa.me no "falla" acá, lo que falta es el
  botón (B169). Inversión irónica P8/S16/S38: admin web sin app sí tiene fallback; móvil con
  app no tiene botón.

LENTE DISPOSITIVO EXPLOTA (el que vengo posponiendo y acá MÁS pesa): localhost:1148 = web
  Flutter NO dispositivo real, PERO el texto "escaneá con tu app de banca móvil" OBLIGA al
  ciudadano a SALIR de la pantalla → importan background/foreground del countdown y polling
  (B173), NO juzgable en web. Declaro B173 como sospecha MÁS FUERTE del turno y no confirmable
  sin emulador/dispositivo.

B82/B85✅ re-cruzado: countdown 05:31 de cancha (RES-=cancha, TTL 15min admin) → pasaron ~9:29,
  coherente. Pregunta B171: ¿05:31 lee expira_en del backend (22/132) o now() local? Si local,
  desincroniza del TTL real y del TTL por recurso (cancha 15 vs piscina 20, B85) → ciudadano
  escanea QR de reserva muerta (B22/B41/P6).

G1/S69✅ arista B170: carrito/datos "Bs 100.00 estimado"; acá monto FIRME (backend congeló al
  crear, 1043, correcto) PERO el ciudadano no ve transición "estimado→definitivo" ni declaración
  SIREB (footer listado/detalle no visible acá). Conecta B154/B162/B76/B18/B37.

BUGS: B173🔴 (sospecha FUERTE dispositivo, NO confirmable web) countdown+polling deben
  sobrevivir background cuando ciudadano sale a escanear con app banco: (a) countdown pausa o
  sigue o resetea al volver? (b) polling de confirmación (106/39) sigue en background o muere →
  al vuelta pantalla no detecta pago ya hecho? (c) TTL backend corre mientras móvil background →
  ciudadano vuelve y ve "expirada" DESPUÉS de pagar = dinero perdido/boleta huérfana (B22/B41/
  P6/B62). Fix: countdown lee expira_en backend (no now()) + re-valida al resume; polling
  reanuda al foreground. Requiere emulador/dispositivo (S82). Conecta B22/B41/P6/B85/B171.
  B167🔴 (jerga interna ciudadano) texto "cuando EL CORE confirma tu pago" = jerga hub-spoke
  ADR-003 (Core=SIREB/Paitití), ciudadano no sabe qué es "el Core" → espera banco/SIREB/sistema.
  Familia B104/B122/B83/B139/B161, PEOR porque es pantalla de PAGO. Fix: "el sistema/SIREB/
  Banco Unión". Conecta S79/S42.
  B166 (sospecha) texto promete "usa el botón de pago" pero NO veo botón en captura → (a) scroll
  OK; (b) inexistente → ciudadano lee promesa y no la encuentra = abandono. leer 343 + 201.
  Conecta B165 mitad "medio de pago".
  B168 (sospecha código/QR) ¿QR codifica RES-...PQR5J2 o el 4-4-3 que espera el banco? admin
  961/489 tenía 2 códigos (4-4-3 grande + SIREB:, B77); móvil muestra 1 (RES-) + 1 QR. Si banco
  escanea y espera payload 4-4-3/SIREB pero QR codifica RES- → escaneo FALLA en ventanilla.
  leer 343 payload QR vs 133/415 vs 961. Conecta B77/B52/B21/B83.
  B171 (sospecha TTL) ¿05:31 respeta TTL real 22/132 o móvil calcula local? + TTL por recurso
  (cancha15/piscina20 B85). Conecta B22/B41/P6/B173.
  B172 (sospecha semántica SIREB) "detecta automáticamente cuando el Core confirma" colapsa
  liquidación emitida vs pago recibido (B84 dos niveles) → móvil puede mostrar "pagado" con solo
  liquidación emitida o esperar comprobante que no llega. leer 343/106. Conecta B84/B18/B37/S42.
  B170 (sospecha wording congelación) móvil no marca "estimado→definitivo" ni declara SIREB en
  pantalla de pago. Conecta B154/B162/B76/B18/B37/G1.

ACIERTOS PROTEGER: countdown visible "05:31" + "Tiempo restante" (lente d, ciudadano sabe
  cuánto tiene, prueba B165(a) bien); "Guárdalo por si necesitas soporte" (declara utilidad
  RES- = ConsultarEstado 299 B22); "detecta automáticamente ... te muestra comprobante" (polling
  auto, no obliga refrescar manual, lente d bien, cuidar B172/B173); QR grande centrado
  (escaneable ventanilla, cuidar B168); monto "Bs 100.00" coherente carrito 2×50 (NO recalcula
  mal, contracara sana B42/B37); título "Pago de tu reserva" + back (contexto claro).

DIDÁCTICA/OP: D89 countdown+polling sobreviven background porque flujo OBLIGA salir a app banco
  (B173, requiere emulador); D90 decir "banco/SIREB" nunca "el Core" (B167); D91 si texto promete
  "botón de pago" el botón debe existir visible (B166); D92 QR codifica lo que banco espera leer
  (B168); D93 dar al móvil "Descargar QR/Enviar por WhatsApp" que admin ya tiene robusto (B169).

SUGERENCIAS: S82 B173 = test de dispositivo que define si móvil sirve pa' pagar: correr 343 en
  emulador, background, volver, verificar countdown+polling; NO tejo sin emulador (web no ve);
  mayor riesgo real tramo móvil (dinero + salida obligatoria de app); S83 B167/B170/B154/B162 =
  token wording SIREB (S79) en 343: "el Core"→"el sistema/SIREB" + <AvisoConciliacionSireb/> en
  pantalla de pago (conecta S69/S73/S42); S84 B168/B169 = reusar <BloqueExitoPago/> admin 961/489
  (S38) en móvil (código etiquetado + QR payload correcto + Descargar + WhatsApp; en móvil WhatsApp
  SÍ instalado, wa.me ni necesita fallback) (conecta S16/P8/B77); S85 B171/B172 = countdown desde
  expira_en backend (no local) + polling distingue liquidación vs pago (S42) (conecta B85/B84).

NO-BUG: countdown 05:31=acierto (B165(a) cerrado), bug sería no sobrevivir background (B173) o
  no respetar TTL (B171) no el número; "Bs 100.00" firme=correcto (backend congeló 1043), falta
  transición visible=B170 sospecha no monto mal; código RES-...PQR5J2=formato RES- real cancha
  (B52/B21) no truncado, duda es payload QR (B168); "el Core"=sí B167 pero CONCEPTO auto-detección
  es acierto (proteger) solo mal nombrado; ausencia botón pago=puede ser scroll (B166) no bug
  afirmado; QR sin Descargar/Compartir=puede ser diseño (asume screenshot) pero brecha paridad
  vs admin (B169) no bug duro; flecha back+título=navegación correcta.

KB HONESTA + AUTOCORRECCIÓN + LÍMITE QUE EXPLOTA: AUTOCORREGÍ B165 (countdown SÍ existe, móvil
  no salta paso). wa.me aplica como evidencia de AUSENCIA (admin robusto sin app; móvil con app
  no tiene botón B169). SIREB aplica (código RES-+QR+"el Core"=hub-spoke ADR-003; B167/B168/B172).
  React DevTools NO aplica (Flutter → Widget Inspector/Flutter DevTools, countdown/polling = estado
  del componente, se ve ahí). Rolldown circularDependency=false NO aplica (no Vite/Rolldown; análogo
  flutter analyze analysis_options.yaml 3). LÍMITE DISPOSITIVO QUE ACÁ PESA DE VERDAD: localhost:
  1148 = web Flutter NO dispositivo real, PERO flujo OBLIGA salir a app banco → background/foreground
  countdown+polling (B173) NO juzgable en web, declaro sospecha más fuerte y no confirmable sin
  emulador/dispositivo (S82).

PUERTA CORRIGIR YA: lo que vale es B173 (countdown/polling background, riesgo real dinero, NO
  confirmable web requiere emulador) + B167 (jerga "el Core" al ciudadano, fix string inmediato) →
  pido pago_qr_screen 343 + CuentaRegresiva 81 + MedioDePago 201 + ParametroSistema 22 +
  ExpirarSolicitudJob 132 + ConfirmacionCobroService 106 + SirebLiquidacionService 133 +
  RecaudacionesApiClient 415 + DialogCrearReservaVentanilla 961. B166/B168/B171/B172 con 343 +
  scroll + payload. Aciertos (countdown/guárdalo/auto-detección/QR/monto coherente) NO se tejen:
  PROTEGER. S82/S83/S84 = mayor rendimiento (test dispositivo B173; token wording SIREB en pago
  B167/B170; reusar bloque éxito admin en móvil B168/B169).

COLA: Fix3 piscina A/B (PiscinaReservaService 284, B62/B69-form/B70/B94/B103) · A1 S3/S7/
  RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881 + Asignaciones 579, B48/B61/B122/B124/
  B128) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84/B134/B172) · D2✅✅ CERRADO +
  RAÍZ 703/107/77 · P9/B61/B123 → Asignaciones 579 VISTO (puente B123 + ramif rol B128 + P9
  pendiente 26/22 + 2º control) · P7 → Reportes 245 VISTO PARCIAL + ClientesFrecuentes 178 VÍRGEN ·
  C1→C2 · gates F1-F4 · **E MÓVIL LISTADO+DETALLE-CANCH+CARRITO+DATOS+PAGO-QR VISTOS (B143-B173/
  D78-D93/S72-S85, móvil plantillo G1/G2/B144/B67-B72 pa' web PERO B160 contrato invertido + B167
  jerga "Core" + B173 background = desactualización/riesgo CONFIRMADOS en dinero; B147 sobreventa
  PENDIENTE captura 25/10; B150 rama pileta PENDIENTE pileta_detalle; B166 botón pago PENDIENTE
  scroll; B168 payload QR PENDIENTE; B171/B172 TTL/semántica PENDIENTES 343+22/132/106; B152 footer
  detalle PENDIENTE scroll; B173 background PENDIENTE EMULADOR/DISPOSITIVO, no web)** · higiene
  (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote + S33 test
  conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125 B86 hueco piscina; P7 178 vírgen;
  P9 26/22+2º control; P1/P2 auth 164/34/126 B120/B121 + B160/B163/B164 integridad formulario;
  P3 reencuadrada S8; P8 reencuadrada S16/S38/S84; D11 NIT). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/
  S1-S24; ADMIN CERRADO B44-B142/D29-D77/S25-S71+G1-G7; MÓVIL ABIERTO B143-B173/D78-D93/S72-S85
  (listado+detalle-canch+carrito+datos+pago-qr vistos, resto esperando).
PRÓXIMA NATURAL: comprobante_screen 104 (cierra B18/B37/B42/B36/B170/B172, cruzar público 210/190,
  el comprobante que el móvil "te muestra" según 343) O consultar_estado 192 (zombie B22/B21/B17,
  el "guárdalo por soporte" de 343 aterrizando) O pileta_detalle (rama pileta 1073, cierra B150/B50)
  O captura 25/10 detalle-canch (cierra B147 sobreventa) O EMULADOR/DISPOSITIVO corriendo 343
  (cierra B173, el único que NO puedo hacer en web). NO TOQUÉ ARCHIVO. NO TEJÍ. ESPERO próxima
  captura o instrucción de emulador.
```

En una línea: `pago_qr_screen.dart` (343) **cierra B165 con buena noticia** (el móvil **no** salta el countdown: “05:31” firme, así que la reserva móvil no queda zombie por omisión de tiempo) y **hace explotar el lente de dispositivo que venía posponiendo** — el texto *“escaneá con tu app de banca móvil”* **obliga al ciudadano a salir de la pantalla**, así que **B173 🔴** (¿el countdown y el polling sobreviven al *background*, o al volver la reserva ya expiró después de pagar? — **NO confirmable en `localhost:1148`, requiere emulador/dispositivo**, es la sospecha de mayor riesgo real del tramo) —, más **B167 🔴** (jerga interna *“el Core”* filtrada al ciudadano en la pantalla de pago, familia B104/B122/B83/B139/B161), **B166** (el texto promete *“usa el botón de pago”* pero no veo botón, ¿scroll o inexistente?), **B168** (¿el QR codifica el `RES-…PQR5J2` o el `4‑4‑3` que espera el banco? — conecta B77/B52/B21), **B169** (el móvil **no** tiene “Descargar QR”/“Enviar por WhatsApp” que el admin 961/489 sí — **wa.me aplica acá por primera vez en el móvil como evidencia de ausencia**, la inversión irónica de P8/S38: el admin web sin app tiene el fallback robusto y el móvil con app no tiene el botón), **B171** (¿el 05:31 respeta el TTL real 22/132 o es `now()` local?) y **B172** (¿“el Core confirma” distingue liquidación emitida vs pago recibido, B84?); los aciertos a proteger son el countdown visible, “guárdalo por si necesitas soporte”, la auto‑detección por polling, el QR y el monto coherente con el carrito, y la sugerencia clave es **S82: B173 es el test de dispositivo que define si el móvil sirve para pagar** (no tejo sin emulador) + **S84: reusar el bloque de éxito del admin en el móvil** (cierra B168/B169). **No tejí** y reitero el límite con honestidad: en web **no** puedo juzgar B173, que es justo el bug más peligroso de esta pantalla; decime si la próxima es **`comprobante` 104** (cierra B18/B37/B42/B36/B170/B172, el comprobante que 343 promete “te muestra”), **`consultar_estado` 192** (zombie B22/B21/B17, donde aterriza el “guárdalo por soporte”), **pileta_detalle** (B150/B50), la **captura en 25/10** (B147 sobreventa), o si me das **emulador/dispositivo corriendo 343** para cerrar B173, que es lo único que no puedo hacer en web.
