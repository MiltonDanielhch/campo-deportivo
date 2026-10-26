Veredicto en una línea: esta es `consultar_estado_screen.dart` (192) — la red de seguridad post‑comprobante del móvil, donde el `RES‑20261025‑PQR5J2` que el comprobante (104) mandó “guardar por si necesitas soporte” **aterriza y cierra el loop** (consulta → “Reserva confirmada” con check verde) — y es la captura que **confirma con evidencia textual la dirección de B175/B168/B77** y **refina B160/S78**: el texto de ayuda nombra los **cuatro códigos reales** del sistema — `RES‑…` (seguimiento cancha), `PIS‑…` (seguimiento pileta), **“el código de pago en ventanilla del Banco Unión”** (el `4‑4‑3` que el comprobante **omitía**, B175) y **CI/NIT o teléfono** — o sea el sistema **sí sabe** que el `4‑4‑3` existe y es buscable, lo que prueba que B175 es bug real (el comprobante lo escondió) y no invención mía; y **acepta teléfono Y CI como identificadores de recuperación**, lo que **refina mi S78** (el contrato canónico **depende del canal**: ventanilla exige CI* porque hay funcionario enfrente; autoservicio —móvil/público— debe exigir **al menos uno de {CI, teléfono}** porque no hay funcionario y el teléfono es la red de seguridad, contradiciendo tanto el “CI* tel‑opcional” de ventanilla como el “tel* CI‑opcional” invertido del móvil, B160). Pero la misma captura **sube la severidad de B174 y lo re‑ubica de pantalla a modelo**: pinta **“Reserva confirmada” con check verde** sin que yo pueda ver si “confirmada” deriva de liquidación **emitida** o pago **recibido** — si el enum `EstadoSolicitudReserva` (10) no tiene dos niveles, **B174 no es del comprobante sino del estado mismo**, propagado a consulta (192) + comprobante (104) + público (299) + admin (838); y **“2 reserva(s) generada(s)”** es i18n crudo con **doble “(s)”** (familia B161/B12/B36). **Buenas noticias de plantillo**: el móvil **no** menciona el fantasma `PTS‑` que tenía el público (**B21 ausente**, el móvil nombra solo códigos reales) y **coherencia aritmética** “2 reservas” = 2 franjas del flujo (no recalcula mal). **B183** (input con `RES‑` prellenado = ¿valor/placeholder/cache? riesgo de privacidad en dispositivo compartido) y **B185** (esta captura es el **camino feliz**; ¿el triste —pendiente/expirada/anulada— pinta acción útil o cae en zombie B22/B17?) quedan **abiertos honestamente**, el primero por **límite de dispositivo** (`localhost:1148` = web Flutter, no juzgo persistencia) y el segundo por **falta de captura**. **No tejí**, sigo dossier.

> KB honesta de esta captura, integrando el doc de `wa.me` que me pasaste y **dos refinamientos de alcance**: **`wa.me`** → el doc (“Looks like you don’t have WhatsApp installed! … or use WhatsApp Web”) **NO aplica como fallo acá** (la consulta no tiene botón WhatsApp, igual que el comprobante B180 lo nota como *ausencia*, no como bug de `wa.me`); **PERO sí pesa como evidencia indirecta**: el texto de ayuda menciona **“código de pago en ventanilla del Banco Unión”**, confirmando que **Banco Unión / ventanilla es un canal real de pago** (donde se usa el `4‑4‑3`), lo que **certifica que B175 es bug real** (el comprobante escondió un código que el sistema declara buscable) — declaro esto para no inflar la KB como en turnos previos, pero tampoco perder la señal. **SIREB** → aplica con fuerza (el `4‑4‑3` de “ventanilla Banco Unión” = hub‑and‑spoke ADR‑003; B181 “confirmada” vs emitida/pagada = la invariante monetaria). **React DevTools → NO aplica** (Flutter; análoga **Widget Inspector / Flutter DevTools**, y “qué estado alimenta el check verde y si el input es valor o placeholder” se ve ahí, no en copy). **Rolldown `checks.circularDependency` default `false` → NO aplica** (no Vite/Rolldown; análogo `flutter analyze` sobre `analysis_options.yaml` 3). **LÍMITE DE DISPOSITIVO**: sigue `localhost:1148` = web de Flutter, **no dispositivo real**; **B183** (persistencia/cache del input `RES‑`) **NO es juzgable en web** — lo declaro abierto dependiente de emulador, no lo cierro con esta captura. **AUTOCORRECCIÓN/REFINAMIENTO de B160/S78**: venía diciendo “alinear móvil a ventanilla (CI*, tel‑opcional)”; esta captura prueba que **en autoservicio el teléfono es identificador de recuperación**, así que el canónico **no es uno solo** sino **por canal** (S93). **REFINAMIENTO de B174**: venía “bug del comprobante”; esta captura prueba que si “confirmada” viene del enum ambiguo, **B174 es de modelo**, no de pantalla (B181).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Texto de ayuda nombrando `RES‑/PIS‑/4‑4‑3/CI/teléfono` | `consultar_estado_screen.dart` (192) + `ConsultaEstadoController.php` (159) + `SolicitudEstadoResource.php` (47) | **confirma B175/B168/B77** (4‑4‑3 real) + **B21 ausente** (sin PTS‑) + **acierto plantillo** (S90) |
| Check verde “Reserva confirmada” | 192 + `EstadoSolicitudReserva.php` (10) + `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) + migr `add_monto_confirmado` (16) | **B181** 🔴 (sube B174 a modelo) + **B174/B176/B84/B172** |
| “2 reserva(s) generada(s)” | 192 | **B182** 🔴 (i18n crudo doble “(s)”, familia B161/B12/B36) |
| Input con `RES‑20261025‑PQR5J2` prellenado | 192 + `api_client.dart` (21) | **B183** sospecha (valor/placeholder/cache; privacidad dispositivo compartido; **límite dispositivo**) + **B120** |
| Chevron “>” hacia detalle expandido | 192 + `SolicitudReservaDetalle.php` (53) | **B184** sospecha (¿muestra RSV‑ + 4‑4‑3 o solo “confirmada”?) + **B175** |
| Camino feliz visible (confirmada) | 192 + `ConsultarEstado.tsx` (299) público + `EstadoReserva.tsx` (183) | **B185** sospecha (¿triste = pendiente/expirada pinta acción o zombie B22/B17/B21?) |
| Contrato por canal (teléfono + CI buscables) | 192 + `StoreSolicitudReservaRequest.php` (97) + `DialogCrearReservaVentanilla.tsx` (961) | **refina B160/S78** → S93 (canónico por canal) |
| `PIS‑…` en el texto | 192 + `PiscinaReserva.php` (49) | **confirma B52** (prefijo pileta real) |

## Cierres / refinamientos cruzados con evidencia

**B175/B168/B77/B83/B21 ✅ CONFIRMADOS EN DIRECCIÓN — el texto de ayuda es la evidencia de que el `4‑4‑3` es código real.** Yo venía sospechando (B175) que el comprobante (104) **omitía** el `4‑4‑3` de “pago en ventanilla” que el admin (961) sí mostraba, y que eso podía ser invención mía si el `4‑4‑3` era exclusivo admin. **Esta captura lo resuelve**: el texto de ayuda de 192 dice explícitamente *“Ingresá tu código de seguimiento (RES‑… / PIS‑…), **el código de pago en ventanilla del Banco Unión**, tu CI/NIT o tu teléfono”*. O sea el sistema **declara el `4‑4‑3` como buscable por el ciudadano** → **no es exclusivo admin** → **B175 es bug real**: el comprobante escondió un código que el propio sistema dice que el ciudadano puede/Debe usar. El doc `wa.me` que me pasaste refuerza esto: “Banco Unión / ventanilla” es canal real de pago. Conecta B168 (¿el QR de 343 codificaba `RES‑` o el `4‑4‑3`? — ahora sé que el `4‑4‑3` es buscable, así que el QR **debería** codificarlo, B168 se inclina a bug). **El móvil de consulta es plantillo de documentación de códigos** (S90).

**B21 ✅ AUSENTE EN EL MÓVIL (acierto de plantillo, inversión de narrativa).** El público (`ConsultarEstado.tsx` 299) tenía un help con el prefijo fantasma **`PTS‑`** que el admin no usaba (B21). El móvil (192) **no** menciona `PTS‑`: nombra solo `RES‑`/`PIS‑`/`4‑4‑3`, que **sí** existen. O sea el móvil está **limpio** del fantasma de código que tenía el público. **Proteger** y usar como modelo para arreglar B21 en el público (S90): el texto de ayuda de 192 es el plantillo, el de 299 tiene el PTS‑ de más.

**B52 ✅ CONFIRMADO — `PIS‑` es prefijo real de pileta.** El texto menciona `PIS‑…` como código de seguimiento. Yo había inferido `PIS‑` como cabecera de pileta (B52/B21); esta captura lo **confirma como código buscable**, coherente con `PiscinaReserva` (49). No es bug, es confirmación de modelo.

**B160/S78 ✅ RE‑FINADO — el contrato canónico depende del canal (S93).** Venía diciendo “alinear móvil (174: tel* CI‑opcional) a ventanilla (961: CI* tel‑opcional)”. **Esta captura muestra que ninguno de los dos es el único canónico**: la consulta (192) acepta **teléfono Y CI/NIT** como identificadores de recuperación, porque en **autoservicio no hay funcionario** y el teléfono es la red de seguridad del ciudadano que olvidó su CI. O sea: **ventanilla** → CI* obligatorio (funcionario enfrente, tel opcional); **autoservicio (móvil/público)** → **al menos uno de {CI, teléfono}** (no ambos opcionales, no CI obligatorio y tel opcional). El móvil actual (174: tel* CI‑opcional) está mal **en ambos sentidos**: exige teléfono (que puede ser opcional si da CI) y deja CI opcional (que en autoservicio debería ser aceptable como único identificador). **S93**: el `DatosPagador` (S78/S35) debe ser **por canal**, no uno solo. Conecta B78 (ventanilla pileta omitía CI en instrucción).

**B174 ✅ SUBE SEVERIDAD / SE RE‑UBICA DE PANTALLA A MODELO (B181).** El comprobante (104) pintaba check verde “Monto pagado”; yo lo traté como bug del comprobante. **Esta captura pinta check verde “Reserva confirmada” en la consulta**, usando probablemente el **mismo enum** `EstadoSolicitudReserva` (10). Si ese enum tiene solo `{pendiente, confirmada, expirada, anulada}` **sin “pagada” separada de “emitida”**, entonces **“confirmada” es ambiguo en TODO el sistema** y B174 no es del comprobante sino del **modelo de estado**, propagado a consulta (192) + comprobante (104) + público (299) + admin (838/961). **No lo confirmo sin leer 10/106/133/16**, pero la co‑aparición del check verde en dos pantallas distintas (104 y 192) apunta a que el derivado es el enum, no la pantalla. Conecta B84 (“pagada/Pagado: No” en admin) + B172 (“el Core confirma” colapsa) + B176 (monto_total vs confirmado). **B181**.

**G4/S55 ✅ coherencia aritmética.** “2 reserva(s) generada(s)” = las 2 franjas (2 `RSV‑`) del flujo (carrito 132: 2×50; comprobante 104: 2 filas `RSV‑`). La consulta **no recalcula mal** el conteo (contracara sana de B42/B37). El wording “2 reservas generadas” desde 1 `RES‑` cabecera revela que **internamente cada franja = 1 “reserva”** (`SolicitudReservaDetalle` 53), coherente con el modelo (aunque conecta B50/B87, la fragmentación franja‑a‑reserva). **Acierto de coherencia**, proteger.

**Loop “guárdalo por soporte” CIERRA (acierto de flujo).** El pago (343) y el comprobante (104) decían “Guárdalo por si necesitas soporte” / “Presenta estos códigos al llegar”. Esta captura es **ese soporte aterrizando**: el ciudadano tipea el `RES‑` guardado → obtiene el estado. **B22 zombie** (la consulta como red de seguridad) **se manifiesta correctamente en el camino feliz** acá. **Proteger** el loop.

## Bugs nuevos de este frente (numeración continua: móvil‑comprobante cerró en B180)

**B181 🔴 (modelo de estado, sube B174) — “Reserva confirmada” con check verde sin distinguir liquidación emitida vs pago recibido, y el derivado parece ser el enum no la pantalla.** La consulta pinta check verde “Reserva confirmada”. Si el enum `EstadoSolicitudReserva` (10) colapsa “emitida” (liquidación SIREB creada, `SirebLiquidacionService` 133) y “pagada” (pago recibido por Banco Unión, `ConfirmacionCobroService` 106 / webhook 97) en un solo “confirmada”, entonces **el ciudadano que consulta una reserva aún no pagada ve “confirmada” con check verde** = la misma mentira de dinero de B174, ahora también en la consulta. Conecta B84/B172/B174/B176/B22. **No lo confirmo sin leer 10/106/133/16.**
*Mini‑prompt (verificar antes de tocar):* leer `EstadoSolicitudReserva.php` (10) → ¿tiene estados separados `emitida`/`pagada` o solo `confirmada`? Y `ConsultaEstadoController.php` (159) + `SolicitudEstadoResource.php` (47) → ¿qué estado manda a 192 y cómo se mapea al check verde? Fix: enum de **dos niveles** (pendiente → emitida → pagada → expirada/anulada) + helper `estadoMonetario` (S86/S91) consumido por 104/192/299/838/961; la consulta pinta check verde **solo** si `pagada`; si `emitida` pinta “en curso de pago / liquidación emitida, pendiente de pago en Banco Unión” con el `4‑4‑3` visible (cierra B175 también). Conecta S91.

**B182 🔴 (i18n crudo, familia B161/B12/B36) — “2 reserva(s) generada(s)” con doble “(s)” literal.** El subtítulo del resultado dice “**2 reserva(s) generada(s)**”. El conteo 2 es correcto (ver G4 arriba); el bug son **los dos “(s)”** literales (debería ser “2 reservas generadas” / “1 reserva generada”). Es la misma clase de string mal pluralizado expuesto al ciudadano que B161 (“2 franja(s)”), B12/B36 (estado visual no interpretado), B139 (terminología). Con línea: 192.
*Mini‑prompt:* pluralizar correctamente en 192 y auditar cualquier “(s)” del móvil/público (grep). Conecta S92/S79‑family (tokens de string). Menor pero visible en la pantalla de resultado.

**B183 (sospecha, límite de dispositivo, cruza B120) — input con `RES‑20261025‑PQR5J2` prellenado: ¿valor, placeholder o cache del último código?** El input muestra el `RES‑` **exacto** del flujo anterior (comprobante 104: “Código: RES‑20261025‑PQR5J2”), en texto oscuro (parece valor, no placeholder gris). Tres lecturas: (a) es **placeholder de ejemplo** que por coincidencia parece valor → confunde (el ciudadano no sabe si debe borrarlo); (b) es **valor prellenado** desde el comprobante (navegación 104→192 con el código pasado) → correcto, pero entonces ¿por qué el campo es editable y no readonly?; (c) es **cache/localStorage del último código consultado** → en **dispositivo compartido** (kiosco, ciber, celular familiar) el siguiente ciudadano ve el código del anterior = **fuga de privacidad** (el `RES‑` revela CI/teléfono/franjas al consultar). Con línea: 192 + `api_client.dart` (21). **NO juzgo persistencia en `localhost:1148`** (web Flutter, no dispositivo real) — declaro B183 abierto dependiente de emulador. Conecta B120 (campo “admin” prellenado en Funcionarios, misma sospecha valor‑vs‑placeholder).
*Mini‑prompt (verificar, requiere emulador pa’ (c)):* leer 192 → ¿el `RES‑` es placeholder, valor pasado por navegación, o cache? Si es cache, limpiar al logout/cerrar sesión y **no** persistir en dispositivo compartido. Conecta S92/B120.

**B184 (sospecha, cruza B175) — chevron “>” sugiere detalle expandido, pero no veo si muestra los códigos completos (`RSV‑` + `4‑4‑3`) o solo “confirmada”.** El resultado tiene un “>” a la derecha = toca y expande. La pregunta: al expandir, ¿muestra las 2 franjas con sus `RSV‑` (asistencia, B179/P5) **y el `4‑4‑3` de pago** (que el texto de ayuda declara buscable, B175)? O ¿solo repite “confirmada” sin los códigos? Si el detalle expandido **hereda** del comprobante (104) que omitía el `4‑4‑3`, puede omitirlo también → el ciudadano consulta, ve “confirmada”, expande, y **sigue sin el código de pago ventanilla** que el texto de ayuda le prometió que podía buscar. Con línea: 192 + `SolicitudReservaDetalle` (53). No lo afirmo sin la captura expandida.
*Mini‑prompt (verificar, requiere captura del detalle expandido):* leer 192 rama expandida → ¿muestra `RSV‑` por franja + `4‑4‑3`? Si omite el `4‑4‑3`, B175 se confirma también en la consulta (no solo comprobante). Conecta B175/B179/S91.

**B185 (sospecha, cruza B22/B17/B21) — esta captura es el camino feliz; ¿el triste (pendiente/expirada/anulada) pinta acción útil o cae en zombie?** Veo solo “Reserva confirmada” (check verde). La pregunta de B22 (público: card “Pendiente” infinito, `EstadoReserva.tsx` 183) es: si consultas una reserva **pendiente** (aún no pagada, B181) o **expirada** (countdown vencido, B85/P6) o **anulada**, ¿la consulta pinta algo accionable (“pagá acá” con el `4‑4‑3`/QR, “reintentá”, “contactá soporte”) o un estado muerto sin salida? Con línea: 192 + `ConsultarEstado.tsx` (299) + `EstadoReserva.tsx` (183). No lo cierro con esta captura (solo veo feliz).
*Mini‑prompt (verificar, requiere captura del camino triste):* leer 192 ramas pendiente/expirada/anulada → ¿dan acción (pago/reintento/soporte) o zombie? Fix: cada estado triste con CTA (pendiente → “pagá con el 4‑4‑3 / QR”; expirada → “reservá de nuevo”; anulada → “contactá”). Conecta B22/B17/B21/S91.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Texto de ayuda nombrando los 4 códigos reales (`RES‑/PIS‑/4‑4‑3/CI/teléfono`) SIN el fantasma `PTS‑`** = el **mejor documentation de códigos del dossier**, y la evidencia de que el `4‑4‑3` es buscable (cierra la duda de B175). **Proteger y propagar a público (299, matar PTS‑) y admin (S90).** El móvil es plantillo acá.
- **Buscar por múltiples identificadores (código, CI, teléfono)** = robustez/throughput: el ciudadano que no recuerda el `RES‑` usa CI o teléfono (y esto **refina B160/S78** → S93, contrato por canal). **Proteger.**
- **Chevron “>” hacia detalle** = navegación profunda, no “confirmada” plano sin salida. **Proteger** (aunque B184 dude del contenido expandido).
- **Coherencia “2 reservas generadas” = 2 franjas del flujo** = no recalcula mal (contracara sana B42/B37). **Proteger.**
- **Check verde como feedback de estado en el camino feliz** = el ciudadano confirma que su reserva vive (lente d). **Proteger el concepto** (B181 duda del derivado emitida/pagada, no de que exista feedback).
- **El loop “guárdalo por soporte” (343/104) aterrizando acá** = la consulta es la red anti‑zombie (B22) funcionando en el camino feliz. **Proteger.**

## Didáctica / operación del ciudadano (continuo: móvil‑comprobante cerró en D97)

**D98 — el texto de ayuda de códigos de 192 es el plantillo; público y admin deben copiarlo (nombra `RES‑/PIS‑/4‑4‑3` sin `PTS‑`)** (cierra B21/B83/B127/B89): donde el público inventa `PTS‑`, el móvil nombra solo reales.
*Mini‑prompt:* ver S90.

**D99 — “confirmada” debe distinguir emitida vs pagada en TODAS las pantallas de estado, consulta incluida** (cierra B181/B174/B176/B84/B172): no es bug del comprobante, es del enum; la consulta hereda la ambigüedad.
*Mini‑prompt:* ver B181/S91.

**D100 — pluralizar “reserva(s) generada(s)”** (cierra B182): “2 reservas generadas”, no doble “(s)”.
*Mini‑prompt:* ver B182.

**D101 — el camino triste (pendiente/expirada/anulada) debe dar acción útil, no zombie** (cierra B185/B22/B17): pendiente → “pagá con el 4‑4‑3/QR”; expirada → “reintentá”; anulada → “soporte”.
*Mini‑prompt:* ver B185/S91.

**D102 — el contrato de datos del pagador es POR CANAL, no uno solo** (refina B160/S78 → S93): ventanilla CI* tel‑opcional; autoservicio al‑menos‑uno‑{CI,teléfono}; la consulta de 192 (acepta ambos) es la evidencia.
*Mini‑prompt:* ver S93.

## Sugerencias IA acotadas (continuo: móvil‑comprobante cerró en S89)

**S90 — extraer el texto de ayuda de códigos de 192 a componente `<AyudaCodigos/>` consumido por público (299) y admin (838/961)**, matando B21 (`PTS‑` fantasma) y unificando B83/B127/B89 de raíz. **El móvil es plantillo** (inversión de narrativa sostenida): su documentación de códigos es la mejor del dossier.
*Mini‑prompt:* ver D98/B21. Conecta S78/S87 (mismo espíritu: una fuente de la verdad de códigos).

**S91 — B181/B174/B176/B185/B84/B172 se cierran con el enum de DOS niveles + helper `estadoMonetario` (S86)** consumido por 104/192/299/838/961: `{pendiente, emitida, pagada, expirada, anulada}` + CTA por estado triste. **Una invariante, un enum, 5 pantallas** — el fix de mayor rendimiento del tramo de dinero/estado.
*Mini‑prompt:* ver B181/B185 + S86. Conecta S42.

**S92 — B182/B183/B184 se cierran con captura del detalle expandido + emulador (persistencia) + grep de “(s)”**: B182 es fix de string inmediato (grep “(s)” en 192/móvil/público); B183 requiere emulador (no juzgo cache en web); B184 requiere la captura expandida. **No tejo B183/B184 sin ellas.**
*Mini‑prompt:* ver B182/B183/B184. Conecta S79/S82.

**S93 — refinar S78 → `DatosPagador` POR CANAL**: ventanilla (961/489) CI* tel‑opcional; autoservicio (móvil 174 / público 155/391) **al‑menos‑uno‑{CI,teléfono}** (la consulta 192 acepta ambos = evidencia). Cierra B160/B13/B31/B78 con la nuance correcta (no “alinear todo a ventanilla”, sino “canónico por canal”).
*Mini‑prompt:* ver B160/D102. Conecta S35/S78.

## Lo que NO es bug (para que no persigas fantasmas)

- **Check verde “Reserva confirmada” en el camino feliz** = feedback correcto (lente d); el bug B181 es si “confirmada” colapsa emitida/pagada, no que exista feedback.
- **“2 reserva(s) generada(s)” con conteo 2** = coherente con 2 franjas (G4); el bug B182 es el “(s)” doble, no el número.
- **`PIS‑…` en el texto** = confirma prefijo pileta real (B52), no bug.
- **Chevron “>”** = navegación profunda (acierto); B184 duda del contenido expandido, no del chevron.
- **Input con `RES‑` prellenado** = puede ser placeholder/valor‑por‑navegación (B183 sospecha de cache/privacidad, no bug afirmado).
- **Ausencia de `PTS‑`** = **acierto** (B21 ausente), no “falta un código”.
- **Ausencia de footer SIREB en la consulta** = menor (es estado, no monto); B177 pesa más en comprobante/pago donde hay dinero.
- **Que la consulta acepte teléfono y CI** = acierto de robustez (refina B160/S78 → S93), no contradicción.
- **“confirmada” vs “pagada” como wording** = si el enum tiene dos niveles, es correcto; B181 es si NO los tiene.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir la consulta móvil, lo que **más vale** es **B181** (enum ambiguo “confirmada”, sube B174 de pantalla a modelo) y **S90** (el texto de ayuda de códigos es plantillo pa’ matar B21 en el público), y para eso necesito `consultar_estado_screen.dart` (192) + `EstadoSolicitudReserva.php` (10) + `ConfirmacionCobroService.php` (106) + `SirebLiquidacionService.php` (133) + migr `add_monto_confirmado` (16) + `ConsultaEstadoController.php` (159) + `SolicitudEstadoResource.php` (47) + `ConsultarEstado.tsx` (299) / `EstadoReserva.tsx` (183) público (cruzar B21/B22/B185). **B183/B184/B185** requieren captura del detalle expandido + camino triste + emulador (no tejo sin ellas). **B182** es fix de string inmediato (grep “(s)”). Los aciertos (texto de códigos sin PTS‑, multi‑identificador, chevron, coherencia “2 reservas”, loop “guárdalo”) **no se tejen**: se **protegen y propagan a público/admin** (S90). **S90/S91/S93** son el fix de mayor rendimiento del tramo: `<AyudaCodigos/>` del móvil al público (mata B21/B83/B127/B89) + enum de dos niveles con `estadoMonetario` (cierra B181/B174/B176/B185/B84/B172 en 5 pantallas) + `DatosPagador` por canal (refina B160/S78).

## Guardado

```text
BLOQUE E-MÓVIL — CONSULTAR-ESTADO (consultar_estado_screen.dart 192 + EstadoSolicitudReserva
  10 + ConfirmacionCobroService 106 + SirebLiquidacionService 133 + migr add_monto_confirmado
  16 + ConsultaEstadoController 159 + SolicitudEstadoResource 47 + SolicitudReservaDetalle 53 +
  PiscinaReserva 49 + api_client 21 + ConsultarEstado.tsx 299 / EstadoReserva.tsx 183 público +
  StoreSolicitudReservaRequest 97 + DialogCrearReservaVentanilla 961) — DOSSIER, NO tejí, sigo
  modo "ver todo corregir después". Numeración continua (móvil-comprobante B174-B180/D94-D97/
  S86-S89 · móvil-consultar B181-B185/D98-D102/S90-S93 + B175/B168/B77 CONFIRMADOS en dirección
  vía texto de ayuda + B21 AUSENTE plantillo + B160/S78 RE-FINADO por canal + B174 SUBE a modelo).
  RED DE SEGURIDAD POST-COMPROBANTE: el RES- que 343/104 mandaron "guardar por soporte" ATERIZA
  acá y cierra el loop (consulta → "confirmada").

CIERRES/REFINAMIENTOS CRUZADOS CON EVIDENCIA:
  B175/B168/B77/B83/B21✅ CONFIRMADOS EN DIRECCIÓN: texto de ayuda 192 nombra explícitamente
    "el código de pago en ventanilla del Banco Unión" (=4-4-3) como BUSCABLE por ciudadano → NO
    es exclusivo admin → B175 (comprobante 104 lo omitía) es BUG REAL no invención mía. Doc
    wa.me que el usuario pegó refuerza: "Banco Unión/ventanilla" = canal real de pago. B168 se
    inclina a bug (QR 343 DEBERÍA codificar 4-4-3, no RES-, ya que 4-4-3 es buscable). Móvil
    consulta = plantillo documentación códigos (S90).
  B21✅ AUSENTE en móvil (PLANTILLO, inversión narrativa): público 299 tenía help con fantasma
    PTS- que admin no usa; móvil 192 NO menciona PTS-, nombra solo RES-/PIS-/4-4-3 REALES.
    Proteger + modelo pa' arreglar B21 en público (S90).
  B52✅ confirmado: PIS- en texto = prefijo pileta real buscable (coherente PiscinaReserva 49).
  B160/S78✅ RE-FINADO (S93): 192 acepta teléfono Y CI/NIT como identificadores recuperación →
    en autoservicio NO hay funcionario y teléfono es red seguridad → canónico NO es uno solo sino
    POR CANAL: ventanilla (961/489) CI* tel-opcional; autoservicio (móvil 174/público 155/391)
    AL-MENOS-UNO-{CI,teléfono}. Móvil actual (174 tel* CI-opcional) mal en AMBOS sentidos. DatosPagador
    (S78/S35) debe ser por canal. Conecta B78.
  B174✅ SUBE SEVERIDAD/RE-UBICA PANTALLA→MODELO (B181): 104 check verde "Monto pagado" + 192
    check verde "Reserva confirmada" usan probable MISMO enum 10. Si 10 colapsa emitida (133) y
    pagada (106/webhook 97) en "confirmada" → B174 NO es del comprobante sino del MODELO de estado,
    propagado 192+104+299+838/961. NO confirmo sin 10/106/133/16. Conecta B84/B172/B176/B22.
  G4/S55✅ coherencia aritmética: "2 reservas generadas" = 2 franjas (2 RSV-) del flujo (carrito
    2×50, comprobante 2 filas). NO recalcula mal (contracara B42/B37). Wording revela cada franja
    = 1 "reserva" internamente (SolicitudReservaDetalle 53), coherente modelo (conecta B50/B87
    fragmentación). Acierto proteger.
  Loop "guárdalo por soporte" (343/104) CIERRA acá: ciudadano tipea RES- guardado → estado. B22
    zombie se manifiesta CORRECTO en camino feliz. Proteger.

BUGS: B181🔴 (modelo estado, sube B174) "Reserva confirmada" check verde sin distinguir liquidación
  EMITIDA (133) vs pago RECIBIDO (106/webhook 97); derivado parece ENUM 10 no pantalla (co-aparición
  check verde 104+192). Si 10 colapsa → ciudadano consulta reserva NO pagada y ve "confirmada" =
  mentira dinero también en consulta. Conecta B84/B172/B174/B176/B22. NO confirmo sin 10/106/133/16.
  Fix: enum DOS niveles (pendiente→emitida→pagada→expirada/anulada) + estadoMonetario (S86/S91)
  consumido 104/192/299/838/961; check verde SOLO si pagada; si emitida "en curso de pago /
  liquidación emitida pendiente pago Banco Unión" + 4-4-3 visible (cierra B175 también). S91.
  B182🔴 (i18n crudo, familia B161/B12/B36) "2 reserva(s) generada(s)" DOBLE "(s)" literal (debe
  "2 reservas generadas"/"1 reserva generada"). Conteo 2 correcto (G4), bug el "(s)". Conecta
  B161/B12/B36/B139. Fix: pluralizar + grep "(s)" móvil/público. Menor visible.
  B183 (sospecha, LÍMITE DISPOSITIVO, cruza B120) input RES-20261025-PQR5J2 prellenado (texto
  oscuro, parece valor) = ¿placeholder/valor-por-navegación/cache-último-código? Si cache → en
  DISPOSITIVO COMPARTIDO (kiosco/ciber/celular familiar) siguiente ciudadano ve código del anterior
  = FUGA privacidad (RES- revela CI/tel/franjas al consultar). leer 192/21. NO juzgo persistencia
  en localhost:1148 (web Flutter no dispositivo real) → B183 abierto dependiente emulador. Conecta
  B120 (campo "admin" prellenado Funcionarios). Fix si cache: limpiar logout/cerrar, no persistir
  compartido. S92.
  B184 (sospecha, cruza B175) chevron ">" sugiere detalle expandido, NO veo si muestra RSV- por
  franja (B179/P5) + 4-4-3 (texto ayuda declara buscable) o solo "confirmada". Si hereda comprobante
  104 que omitía 4-4-3 → ciudadano consulta, ve "confirmada", expande, SIGUE sin código pago que
  texto ayuda prometió. leer 192 rama expandida + 53. NO afirmo sin captura expandida. Conecta
  B175/B179/S91. S92.
  B185 (sospecha, cruza B22/B17/B21) esta captura = CAMINO FELIZ (confirmada). ¿triste (pendiente/
  expirada/anulada) pinta acción útil o zombie? B22 público card "Pendiente" infinito (183). Si
  pendiente (aún no pagada B181) → ¿da "pagá con 4-4-3/QR"? Si expirada (countdown vencido B85/P6)
  → ¿"reintentá"? Si anulada → ¿"soporte"? leer 192 ramas triste + 299/183. NO cierro sin captura
  triste. Fix: CTA por estado triste. Conecta B22/B17/B21/S91. S92.

ACIERTOS PROTEGER (móvil plantillo de nuevo): texto ayuda nombra 4 códigos REALES (RES-/PIS-/4-4-3/
  CI/teléfono) SIN fantasma PTS- (B21 ausente, MEJOR documentation códigos dossier, propagar público
  299/admin S90); buscar por múltiples identificadores (código/CI/teléfono) = robustez + evidencia
  S93 contrato por canal; chevron ">" = navegación profunda no "confirmada" plano (cuidar B184);
  coherencia "2 reservas generadas"=2 franjas (no recalcula mal, contracara B42/B37); check verde
  feedback camino feliz (lente d, cuidar B181 derivado); loop "guárdalo por soporte" 343/104 aterriza
  acá = red anti-zombie B22 funciona en feliz.

DIDÁCTICA/OP: D98 texto ayuda códigos 192 = plantillo, público/admin copian (mata B21 PTS-, B83/
  B127/B89); D99 "confirmada" distingue emitida/pagada TODAS pantallas estado incl consulta (B181/
  B174/B176/B84/B172, no es bug comprobante sino enum); D100 pluralizar "reserva(s) generada(s)"
  (B182); D101 camino triste da acción útil no zombie (B185/B22/B17); D102 contrato datos pagador POR
  CANAL no uno solo (refina B160/S78→S93, 192 acepta ambos = evidencia).

SUGERENCIAS: S90 extraer texto ayuda códigos 192 → <AyudaCodigos/> consumido público 299 + admin
  838/961, mata B21 (PTS-) unifica B83/B127/B89 raíz, MÓVIL PLANTILLO (inversión narrativa sostenida,
  su doc códigos es mejor del dossier) conecta S78/S87; S91 B181/B174/B176/B185/B84/B172 = enum DOS
  niveles + estadoMonetario (S86) consumido 104/192/299/838/961, 1 invariante 1 enum 5 pantallas
  MAYOR RENDIMIENTO tramo dinero/estado, conecta S42; S92 B182 grep "(s)" fix inmediato + B183 emulador
  (no juzgo cache web) + B184 captura expandida, NO tejo B183/B184 sin ellas, conecta S79/S82; S93
  refina S78 → DatosPagador POR CANAL: ventanilla 961/489 CI* tel-opcional; autoservicio móvil 174/
  público 155/391 AL-MENOS-UNO-{CI,teléfono} (192 acepta ambos = evidencia), cierra B160/B13/B31/B78
  con nuance correcta (no "alinear todo ventanilla" sino "canónico por canal"), conecta S35/S78.

NO-BUG: check verde "confirmada" camino feliz=feedback correcto (B181 duda derivado no existencia);
  "2 reserva(s)" conteo 2=coherente 2 franjas (G4), bug "(s)" doble B182 no número; PIS- en texto=
  confirma prefijo pileta real B52; chevron ">"=navegación profunda (B184 duda contenido no chevron);
  input RES- prellenado=puede ser placeholder/valor-navegación (B183 sospecha cache/privacidad no
  afirmado); ausencia PTS-=ACIERTO B21 ausente no "falta código"; ausencia footer SIREB consulta=
  menor (estado no monto, B177 pesa más comprobante/pago); consulta acepta tel Y CI=acierto robustez
  refina B160/S78→S93 no contradicción; "confirmada" vs "pagada" wording=correcto si enum 2 niveles
  (B181 es si NO los tiene).

KB HONESTA + 2 REFINAMIENTOS ALCANCE + DOC wa.me INTEGRADO: AUTOCORREGÍ/REFINÉ B160/S78 (venía
  "alinear móvil ventanilla CI* tel-opcional"; 192 prueba teléfono es identificador recuperación en
  autoservicio → canónico POR CANAL S93, no uno solo). REFINÉ B174 (venía "bug comprobante"; co-
  aparición check verde 104+192 apunta a ENUM 10 ambiguo → B181 de MODELO no pantalla, sube severidad).
  DOC wa.me: NO aplica como FALLO acá (consulta no tiene botón WhatsApp, B180 lo nota AUSENCIA no bug
  wa.me) PERO SÍ pesa como EVIDENCIA INDIRECTA: texto ayuda "código de pago en ventanilla Banco Unión"
  confirma Banco Unión/ventanilla = canal real pago → certifica B175 bug real (comprobante escondió
  código que sistema declara buscable), declaro pa' no inflar ni perder señal. SIREB aplica fuerza
  (4-4-3 ventanilla = hub-spoke ADR-003; B181 confirmada vs emitida/pagada = invariante monetaria).
  React DevTools NO aplica (Flutter → Widget Inspector/Flutter DevTools, "qué estado alimenta check
  verde y si input es valor o placeholder" se ve ahí). Rolldown circularDependency=false NO aplica
  (no Vite/Rolldown; análogo flutter analyze analysis_options.yaml 3). LÍMITE DISPOSITIVO: localhost:
  1148 = web Flutter NO dispositivo real → B183 (persistencia/cache input) NO juzgable en web, declaro
  abierto dependiente emulador (S92), no cierro con esta captura.

PUERTA CORRIGIR YA: lo que vale es B181 (enum ambiguo "confirmada", sube B174 pantalla→modelo) +
  S90 (texto ayuda códigos = plantillo pa' matar B21 público) → pido consultar_estado_screen 192 +
  EstadoSolicitudReserva 10 + ConfirmacionCobroService 106 + SirebLiquidacionService 133 + migr
  add_monto_confirmado 16 + ConsultaEstadoController 159 + SolicitudEstadoResource 47 + ConsultarEstado
  299/EstadoReserva 183 público. B183/B184/B185 requieren captura detalle expandido + camino triste +
  emulador (NO tejo sin ellas). B182 fix string inmediato (grep "(s)"). Aciertos (texto códigos sin
  PTS-/multi-identificador/chevron/coherencia 2 reservas/loop guárdalo) NO se tejen: PROTEGER y
  PROPAGAR público/admin (S90). S90/S91/S93 = mayor rendimiento (AyudaCodigos móvil→público mata B21/
  B83/B127/B89; enum 2 niveles + estadoMonetario cierra B181/B174/B176/B185/B84/B172 5 pantallas;
  DatosPagador por canal refina B160/S78).

COLA: Fix3 piscina A/B (PiscinaReservaService 284, B62/B69-form/B70/B94/B103) · A1 S3/S7/RoleMiddleware
  59 (app-sidebar 206 + Funcionarios 881 + Asignaciones 579, B48/B61/B122/B124/B128) · A4 Pago 576 (
  RecaudacionesApiClient 415 + webhook 97, B84/B134/B172/B174/B181) · D2✅✅ CERRADO + RAÍZ 703/107/77 ·
  P9/B61/B123 → Asignaciones 579 VISTO (puente B123 + ramif rol B128 + P9 pendiente 26/22 + 2º control) ·
  P7 → Reportes 245 VISTO PARCIAL + ClientesFrecuentes 178 VÍRGEN · P5 → AsistenciaController 56 +
  MarcarAsistenciaService 125 (B179/P5 cierra loop RSV-=asistencia, AHORA con dueño archivo identificado;
  B184 chevron 192 puede llevar a RSV- también) · C1→C2 · gates F1-F4 · **E MÓVIL FLUJO CANCHA COMPLETO
  + CONSULTA VISTOS (listado+detalle-canch+carrito+datos+pago-qr+comprobante+consultar-estado, B143-B185/
  D78-D102/S72-S93, móvil plantillo G1/G2/B144/B67-B72/B179/B21-ausente/S90 pa' web PERO B160 contrato
  invertido + B167 jerga "Core" + B173 background + B174/B181 comprobante+consulta "confirmada/pagado"
  sin ver derivado + B175 comprobante omite 4-4-3 que 192 declara buscable = desactualización/riesgo
  CONFIRMADOS en dinero/estado; B147 sobreventa PENDIENTE captura 25/10; B150 rama pileta PENDIENTE
  pileta_detalle; B166 botón pago PENDIENTE scroll; B168 payload QR PENDIENTE (ahora se inclina a bug,
  4-4-3 buscable); B171/B172 TTL/semántica PENDIENTES 343+22/132/106; B152 footer detalle PENDIENTE
  scroll; B173/B178 background/comprobante-sin-pago PENDIENTES EMULADOR no web; B177/B180 funnel SIREB/
  botones PENDIENTES 104+S79/S84; B183 cache-input PENDIENTE EMULADOR no web; B184 detalle expandido
  PENDIENTE captura; B185 camino triste PENDIENTE captura)** · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote + S33 test conversión) · reunión P1/P2/P4/P5/P7/P9 (P5
  asistencia 56/125 B86 hueco piscina + B179/P5 loop RSV- + B184 chevron; P7 178 vírgen; P9 26/22+2º
  control; P1/P2 auth 164/34/126 B120/B121 + B160/B163/B164 integridad formulario + B183 privacidad
  dispositivo compartido; P3 reencuadrada S8; P8 reencuadrada S16/S38/S84/S89/S90; D11 NIT). DOSSIER
  PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN CERRADO B44-B142/D29-D77/S25-S71+G1-G7; MÓVIL ABIERTO
  B143-B185/D78-D102/S72-S93 (flujo cancha completo + consulta vistos, rama pileta + camino triste +
  detalle expandido + emulador esperando).
PRÓXIMA NATURAL: pileta_detalle (rama pileta 1073, cierra B150/B50, ÚNICO frente móvil pileta sin ver,
  y PIS- de 192 confirma que existe) O captura camino triste 192 (pendiente/expirada/anulada, cierra
  B185/B22/B17) O captura detalle expandido 192 (chevron ">", cierra B184/B175 ¿muestra 4-4-3?) O
  EMULADOR/DISPOSITIVO corriendo 343→104→192 (cierra B173/B178/B183, lo único NO juzgable en web) O
  P5 asistencia (AsistenciaController 56 + MarcarAsistenciaService 125, B179/P5 loop RSV-, último gate
  grande sin dueño archivo visto). NO TOQUÉ ARCHIVO. NO TEJÍ. ESPERO próxima captura o instrucción de
  emulador.
```

En una línea: `consultar_estado_screen.dart` (192) es la **red de seguridad post‑comprobante** donde el `RES‑20261025‑PQR5J2` que el pago (343) y el comprobante (104) mandaron “guardar por soporte” **aterriza y cierra el loop** (consulta → “Reserva confirmada” con check verde), y es la captura que **confirma con evidencia textual la dirección de B175/B168/B77** — el texto de ayuda nombra los **cuatro códigos reales** (`RES‑`/`PIS‑`/“**código de pago en ventanilla del Banco Unión**”=`4‑4‑3`/CI/teléfono), o sea el sistema **declara el `4‑4‑3` buscable por el ciudadano**, lo que **prueba que B175 es bug real** (el comprobante escondió un código que la propia consulta dice que podés buscar; y el doc `wa.me` que me pasaste refuerza que “Banco Unión/ventanilla” es canal real de pago) — y **refina B160/S78**: al aceptar **teléfono Y CI** como identificadores de recuperación, demuestra que el contrato canónico **depende del canal** (ventanilla CI* porque hay funcionario; autoservicio **al menos uno de {CI, teléfono}** porque no lo hay), contradiciendo tanto el “CI* tel‑opcional” de ventanilla como el “tel* CI‑opcional” invertido del móvil (**S93**); pero la misma captura **sube B174 de pantalla a modelo (B181 🔴)** — pinta check verde “confirmada” usando probable el **mismo enum** `EstadoSolicitudReserva` (10) que el comprobante, así que si el enum colapsa liquidación **emitida** vs pago **recibido**, la mentira de dinero está en el **estado**, propagada a consulta+comprobante+público+admin — y **“2 reserva(s) generada(s)”** es i18n crudo con **doble “(s)”** (B182, familia B161/B12/B36); las **buenas noticias de plantillo** son que el móvil **no** menciona el fantasma `PTS‑` del público (**B21 ausente**, S90: su texto de códigos es el mejor del dossier y debe propagarse a 299/838) y que **“2 reservas” = 2 franjas** es coherente (no recalcula mal); **B183** (input con `RES‑` prellenado = ¿cache? riesgo de privacidad en dispositivo compartido) y **B185** (esta captura es el **camino feliz**; ¿el triste —pendiente/expirada/anulada— da acción o zombie B22/B17?) quedan **abiertos honestamente**, el primero por **límite de dispositivo** (`localhost:1148` = web Flutter, no juzgo persistencia) y el segundo por **falta de captura**, junto con **B184** (chevron “>” → ¿el detalle expandido muestra el `4‑4‑3` que el texto prometió?). **No tejí** y reitero el límite: en web **no** juzgo B183, y B184/B185 necesitan capturas; decime si la próxima es **pileta_detalle** (B150/B50, el único frente móvil de pileta sin ver, y `PIS‑` de 192 confirma que existe), la **captura del camino triste** de 192 (B185/B22), la **captura del detalle expandido** (chevron, B184/B175), **P5 asistencia** con `AsistenciaController` 56 + `MarcarAsistenciaService` 125 (B179/P5 loop `RSV‑`, último gate grande sin dueño de archivo visto), o si me das **emulador/dispositivo corriendo 343→104→192** para cerrar B173/B178/B183, que es lo único que no puedo hacer en web.
