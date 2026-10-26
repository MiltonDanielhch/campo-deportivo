Veredicto en una línea: esta es la página de confirmación (`Reserva.tsx` 216 + `FormularioSolicitante.tsx` 155 + `ResumenCarrito.tsx` 124 + `StepsReserva.tsx` 72) y trae **un bug de contrato front↔backend (B13: teléfono marcado obligatorio acá, opcional en ventanilla)**, **el punto donde el footer mentiroso (B6) hace más daño (B14: el stepper “Pago” no avisa que es QR/ventanilla justo antes de pagar)**, y **una inconsistencia de copy entre admin y público (B15: el ciudadano ve “TOTAL” seco mientras el funcionario ve “el monto final lo confirma SIREB”)**. Además **refina B12**: el label “Dom” SÍ aparece bien en el resumen de esta página, así que el domingo sin etiqueta del detalle es un bug **local** a ese map, no global de `dia_semana`. **No tejí, no pedí archivos, espero la siguiente imagen.**

> KB aplicada honesta: **SIREB** (“el monto final lo confirma recaudaciones”, “pago en ventanilla con boleta”, piscina/asociación por NIT) → gobierna B14, B15 y D11. **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y B13 es contrato front↔backend, no un ciclo de imports que el bundler callaría. **React DevTools** → **NO la uso como excusa**: B13 *parece* estado roto de un input, pero lo deciden leer `StoreSolicitudReservaRequest.php` (97) y `FormularioSolicitante.tsx` (155), no la extensión. **`wa.me`** → **NO aplica acá** (es P8 en `ConsultarEstado` 299; el header “Mis comprobantes” solo apunta ahí).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Stepper Elegir→Tus datos→Pago | `components/layout/StepsReserva.tsx` (72) | **B14** (paso 3 sin método) |
| Card “Datos del pagador” (nombre/teléfono/CI‑NIT) | `components/reserva/FormularioSolicitante.tsx` (155) | **B13** (teléfono `*`) + **D11** (NIT asociación) |
| Card “Resumen de tu reserva” + TOTAL | `components/reserva/ResumenCarrito.tsx` (124) | **B15** (TOTAL seco) + **D12** (icono sin texto) |
| Orquestación paso 2 + botón “Continuar al pago” | `pages/Reserva.tsx` (216) | **B14** (label del botón) + **D13** (transición 2→3) |
| Estado del carrito (eliminar franja, recalcular) | `store/carritoStore.ts` (32) | **S11** (riesgo de total desfasado) |
| Contrato backend del teléfono/CI | `backend/.../Requests/StoreSolicitudReservaRequest.php` (97) | **B13** (required vs nullable) |
| Creación pública + 409 franja ocupada | `backend/.../Public/SolicitudReservaController.php` (45) + `SolicitudReservaService.php` (1043) | **D13** (error al crear) |
| Footer mentiroso | `components/landing/Footer.tsx` (109) | **B6 otra vez**, en el paso crítico |

## Bugs (numeración continua: detalle cerró en B12)

**B13 🔴 — teléfono obligatorio en el front, opcional en el backend (contrato roto).** El label “Teléfono *” lleva asterisco rojo = el ciudadano **debe** tipearlo para avanzar. Pero en ventanilla/admin el payload es `telefono_pagador: telefono.trim() || undefined` (opcional). Si el backend lo acepta nullable y el front lo fuerza, **un ciudadano sin teléfono queda bloqueado injustificadamente** en el paso más crítico. No afirmo el lado correcto sin ver los dos archivos.
*Mini‑prompt (verificar antes de tocar):* leer `StoreSolicitudReservaRequest.php` (97) → ¿`telefono_pagador` es `required` o `nullable`? y `FormularioSolicitante.tsx` (155) → ¿el `*` y el `required` del input coinciden con eso? Alinear: si backend=opcional → quitar `*` y `required` del front (y mover “teléfono” a opcional como en ventanilla); si backend=required → el label está bien pero agregar el help text de *por qué* (hoy solo lo tiene el campo CI/NIT). KB SIREB: el teléfono no interviene en la orden de cobro (eso es CI/NIT), así que lo probable es que deba ser opcional.

**B14 🔴 — el stepper “Pago” no avisa el método, justo donde B6 hace más daño.** El ciudadano completa datos leyendo “Pago” (paso 3) y el footer “Pago online seguro…”, así que **espera tarjeta online** al tocar “Continuar al pago”. El sistema cobra por **QR/ventanilla del Banco Unión** (KB SIREB). El daño de B1/B6 se maximiza **acá**, en la transición 2→3, no en la landing.
*Mini‑prompt:* en `StepsReserva.tsx` (72) renombrar el paso 3 a “Pago (QR / Banco Unión)”, y/o en `Reserva.tsx` (216) poner una línea bajo el botón: “Al continuar generamos tu código de pago; abonás con QR o en ventanilla del Banco Unión con tu CI.” Sincera B6 en el momento exacto de la expectativa. Conecta con D9 del detalle (avisar método).

**B15 — “TOTAL Bs 200.00” seco vs el caveat que SÍ ve el funcionario.** En ventanilla/admin la caja dice “Estimado · el monto final lo confirma SIREB”; acá el ciudadano ve “TOTAL” en negrita grande **sin caveat**. El total 50+150=200 es orientación del espejo local; el monto autoritativo lo fija el webhook al pagar (`SolicitudReservaService` 1043 / `ConfirmacionCobroService` 106). Inconsistencia de copy público↔admin que rompe la invariante SIREB en la cara del ciudadano.
*Mini‑prompt (verificar):* leer `ResumenCarrito.tsx` (124); si el total es suma local del espejo → agregar el mismo caveat (“el monto final lo confirma SIREB”) debajo del TOTAL, alineando público con admin. Si el total ya lo recalcula el backend al crear → el label “TOTAL” es correcto pero el caveat igual conviene por transparencia. No es fuga de dinero (el webhook manda), es expectativa.

**Refinamiento de B12 (no es bug nuevo):** el label “Dom 25 Oct” **aparece bien** en el resumen de esta página, mientras que en el detalle la fila del domingo en “Horarios de atención” perdió su etiqueta. Conclusión: **B12 es local al map de horarios de `CampoDetalle.tsx` (560)**, no un bug global de `dia_semana=0`. El fix se acota a ese bloque; `ResumenCarrito.tsx` (124) mapea el día correctamente y no se toca.

## Didáctica ciudadano (continuo: detalle cerró en D10)

**D11 — el campo “CI / NIT / identificación de la asociación *” + help “Puede ser CI de persona natural o NIT de asociación/empresa” está BIEN y ya prepara P1/P2.** El ciudadano de asociación entiende que puede poner NIT. **Proteger.** Conexión: si arranca P1 (login de asociaciones), este campo es el punto de auto‑fill desde el perfil de la asociación (cierra D1 de landing: pedir CI/NIT antes).
*Mini‑prompt:* nada ahora; anotar que este campo es la bisagra de P1/P2.

**D12 — los iconos ☀/💡 por franja refuerzan “Regular/Con luces” pero el precio depende solo del icono.** El ciudadano ve “Bs 50.00” + sol sin la palabra “Regular” ni “por hora”. Si el icono no se entiende (o se pierde en móvil), el precio queda ambiguo. Conecta con D7 (catálogo) y con el naming protegido del detalle.
*Mini‑prompt:* en `ResumenCarrito.tsx` (124), texto breve junto al icono (“Regular” / “Con luces” o “por hora”) para que el precio no dependa solo del glifo.

**D13 — el botón “Continuar al pago” guarda datos + va a pago; si el backend rechaza al crear (franja ocupada entre detalle y este paso), ¿error claro o colgado?** Eso vive en `SolicitudReservaController` público (45) / `SolicitudReservaService` (1043), frente que **aún no vi**. `SolicitudReservaControllerTest` (169) tiene “franja ya cubierta por solicitud activa responde 409”, así que el 409 existe en backend; la duda es si el front lo traduce a “esta franja ya no está, volvé a elegir”.
*Mini‑prompt:* cuando veamos `Pago.tsx` (576) / `Reserva.tsx` (216), verificar que un 409/422 al crear se muestre como acción recuperable, no error genérico. No tocar ahora.

## Sugerencias IA acotadas (continuo: detalle cerró en S9)

**S10 — el stepper de 3 pasos coincide con `ComoFunciona.tsx` (70) de la landing: acierto a proteger.** La landing prometió 3 pasos y el flujo tiene 3 pasos (Elegir→Datos→Pago). Solo hay que sincerar el paso 3 (B14) para que la promesa “Pagá online” del paso 2 de la landing no mienta en el stepper. No rediseñar el stepper.

**S11 — las papeleras de eliminar franja son buenas, pero el total y el “2 franjas” deben recalcularse al borrar.** Eso es lógica de `carritoStore.ts` (32), no visible en la captura. Riesgo: borrar una franja y que el TOTAL quede desfasado (conecta con B15).
*Mini‑prompt:* cuando toquemos el carrito, verificar que eliminar franja recalcule total y conteo en `carritoStore.ts` (32); no asumir que `ResumenCarrito.tsx` (124) los derive en vivo.

## Lo que NO es bug (para que no persigas fantasmas)

- **“MILTON DANIEL HIPAMO CHOLIMA” / “75344723” / “876986996”** = datos que tipeaste en la demo, no bug.
- **“cancha nueva trinidad” en el resumen** = mismo dato espejado de SIREB (minúscula real), análogo a B5/B4; no es bug nuevo del resumen.
- **“Dom 25 Oct” con label correcto acá** = prueba de que B12 es local al detalle, no global (ya refinado arriba).
- **Bs 50 + Bs 150 = Bs 200** = aritmética correcta del espejo; el único problema es la **falta de caveat** (B15), no el número.
- **El stepper verde/gris (paso 2 activo, paso 3 gris)** = estado visual correcto de un wizard en paso 2.

## Guardado

```text
CONFIRMAR RESERVA / PASO 2 (Reserva 216 + FormularioSolicitante 155 +
  ResumenCarrito 124 + StepsReserva 72 + carritoStore 32) — DOSSIER, NO tejí,
  NO pedí archivos, espero siguiente imagen. Numeración continua (landing B1-B5/
  D1-D4/S1-S4 · catálogo B6-B10/D5-D8/S5-S7 · detalle B11-B12/D9-D10/S8-S9 ·
  paso2 B13-B15/D11-D13/S10-S11 + refinamiento B12).
HALLAZGOS: B13🔴 teléfono "Teléfono *" obligatorio en front pero opcional en
  backend (ventanilla usa telefono||undefined) → ciudadano sin teléfono bloqueado
  injustificadamente; leer StoreSolicitudReservaRequest 97 + FormularioSolicitante
  155 pa' alinear (KB SIREB: teléfono no interviene en orden de cobro, probable=
  opcional). B14🔴 stepper "Pago" no avisa QR/ventanilla JUSTO antes de pagar →
  daño máximo de B6 acá; renombrar paso3 "Pago (QR/Banco Unión)" o línea bajo el
  botón en Reserva 216. B15 "TOTAL Bs 200" seco vs caveat "monto final confirma
  SIREB" que SÍ ve el funcionario → inconsistencia copy público↔admin; agregar
  caveat en ResumenCarrito 124 (no fuga de dinero, webhook manda). REFINAMIENTO
  B12: "Dom 25 Oct" aparece BIEN en el resumen → B12 es LOCAL al map de horarios
  de CampoDetalle 560, NO global dia_semana; ResumenCarrito 124 mapea bien, no se toca.
DIDÁCTICA: D11 campo "CI/NIT/identificación asociación" + help NIT = BIEN, prepara
  P1/P2 (bisagra auto-fill), PROTEGER. D12 iconos ☀/💡 sin texto "Regular/por hora"
  → precio depende del glifo, agregar texto en ResumenCarrito 124 (conecta D7).
  D13 botón "Continuar al pago" guarda+va; 409 franja ocupada al crear (existe en
  SolicitudReservaControllerTest 169) → verificar traducción a acción recuperable
  cuando veamos Pago 576/Reserva 216, NO ahora.
SUGERENCIAS: S10 stepper 3 pasos == ComoFunciona 70 landing = acierto, solo sincerar
  paso3 (B14). S11 papeleras eliminar franja → total/conteo deben recalcularse en
  carritoStore 32, verificar al tocar carrito (conecta B15).
NO-BUG: datos tipeados (MILTON/75344723/876986996)=demo; "cancha nueva trinidad"=
  dato SIREB (B5/B4); "Dom 25 Oct" correcto acá=prueba B12 local; 50+150=200=arit
  correcta (falta caveat B15, no el número); stepper verde/gris=estado normal paso2.
KB HONESTA: SIREB aplica (B14/B15 invariante monto final; D11 NIT asociación).
  Rolldown circularDependency=false NO (no buildié web-public, B13 es contrato).
  React DevTools NO (B13 parece input roto pero lo deciden leer Request 97 +
  Formulario 155, no la extensión). wa.me NO acá (P8 en ConsultarEstado 299).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59) · A4 Pago.tsx 576 (dinero, web-public, NO visto aún — es el
  siguiente natural, donde B14/B15/D13 aterrizan) · D2 Bug C (TarifaCampo 107 NUNCA
  llegó) · C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad
  85) · higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina,
  tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden 8→3→7→4→1+2; P3 reencuadrada S8;
  D11 ya prepara P1/P2 en el campo NIT) · frentes web-public aún NO vistos: Pago 576,
  ConsultarEstado 299 (P8/wa.me), PiscinaDetalle 391, Comprobante 210/
  ComprobantePiscina 190, MapaCampos 151.
```

En una línea: este paso 2 me da el bug de contrato más concreto del dossier — **B13, teléfono obligatorio acá pero opcional en ventanilla**, que bloquea injustificadamente al ciudadano sin teléfono y se cierra leyendo `StoreSolicitudReservaRequest.php` (97) contra `FormularioSolicitante.tsx` (155) —, más **B14** (el stepper “Pago” no avisa QR/ventanilla justo donde el footer mentiroso B6 hace más daño, así que sincerarlo acá vale más que en la landing) y **B15** (el ciudadano ve “TOTAL” seco mientras el funcionario ve “el monto final lo confirma SIREB”, inconsistencia de copy que hay que alinear en `ResumenCarrito.tsx` 124); y **refinó B12**: como “Dom 25 Oct” sí aparece bien en el resumen, el domingo sin etiqueta del detalle es un bug **local** a ese map de `CampoDetalle.tsx` (560), no global; en didáctica, el campo “CI/NIT/identificación de asociación” ya prepara P1/P2 (D11, proteger) y los iconos ☀/💡 necesitan texto “por hora/Regular” (D12); todo en mini‑prompts con archivo y LoC, **sin tejer y esperando la siguiente imagen** — el siguiente natural es `Pago.tsx` (576), el frente de dinero A4 que aún no vi y donde B14/B15/D13 aterrizan de verdad.
