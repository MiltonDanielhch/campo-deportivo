Veredicto en una línea: este es `ComprobantePiscina.tsx` (190), el último frente de página de la pública, y cruza con lo que ya vi del backend de piscina destapa **el bug de dinero más grave de todo el dossier**: **B43 🔴🔴 — el ciudadano ve “Bs 15.00” al elegir el perfil (imagen anterior) pero el comprobante dice “Bs 10.00”**, una discrepancia real de 5 por persona, y tiene causa con línea: el `PiscinaDisponibilidadService` (147) muestra el `precio_unitario` del **perfil** (15, local) pero usa la **tarifa SIREB** (10) solo como gate, mientras la reserva congela la tarifa SIREB (10) → lo que ves ≠ lo que te cobran ≠ lo que dice el comprobante. Le siguen **B36** (fecha ISO cruda UTC `2026-10-25T04:00:00.000000Z` en un comprobante oficial), **B42** (“2 personas Bs 10.00” pinta el unitario como si fuera el total de la línea), **B37** (monto sin fuente SIREB, análogo B18/B30 en piscina) y **B39** (sospecha: sin QR ni “Mostrá el QR al personal”, rompe la asistencia que cancha sí da). Con esto la **pública queda cerrada** (todas las páginas vistas). **No tejí, sigo modo dossier.**

> KB honesta: **SIREB** aplica con fuerza y es la clave de B43/B37 (“el precio lo fija el catálogo oficial, no el GAD” → la pantalla debe mostrar el SIREB, no el precio local del perfil; y “monto final confirma recaudaciones” → el comprobante confirmado debe pintar el confirmado, no el estimado). **Rolldown `checks.circularDependency` default `false`** y **`bundlerTimings`/`pluginTimings`** → **NO aplican**: no buildié web‑public y B36/B42/B43 son formato/lectura/dato, no un ciclo de imports ni telemetría de build. **React DevTools** → **NO como excusa**: B36 *parecería* estado roto de un campo, pero lo decide leer `ComprobantePiscina.tsx` (190); la extensión **sí** será herramienta al depurar el formateo de fecha *cuando arranquemos*. **`wa.me`** → **NO aplica** (no hay WhatsApp acá; de hecho B39 es la *ausencia* del bloque QR/WhatsApp que cancha sí tiene, y `wa.me` era para P8 en `ConsultarEstado`/`Pago`).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| “2026-10-25T04:00:00.000000Z” | `ComprobantePiscina.tsx` (190) | **B36** fecha cruda UTC |
| “Monto pagado Bs 20.00” + fila “2 personas Bs 10.00” | `ComprobantePiscina.tsx` (190) | **B42** unitario como total + **B37** fuente/monto |
| “Mayores de 12 … Bs 10.00” vs “Bs 15.00” de la pantalla previa | `backend/.../Piscina/PiscinaDisponibilidadService.php` (147) + `PiscinaReservaService.php` (284) | **B43** el desalineamiento (147 muestra perfil, 284 congela SIREB) |
| Badge “confirmada” negro, sin QR | `ComprobantePiscina.tsx` (190) vs `ComprobantePDF.tsx` (238) | **B38** badge + **B39** sin QR |
| Footer “Pago online…” | `components/landing/Footer.tsx` (109) | **B6** décima vez |

## Bugs (numeración continua: piscina ciudadana cerró en B35)

**B43 🔴🔴 (EL ESTRELLA, dinero real) — precio 15 en selección vs 10 en comprobante.** Hecho visible cruzando tus dos imágenes: la pantalla de `PiscinaDetalle.tsx` (391) mostraba “Mayores de 12 · **Bs 15.00**”, y este comprobante muestra la misma reserva a **Bs 10.00** por persona. Causa con línea del backend que ya vi: el `PiscinaDisponibilidadService` (147) resuelve la tarifa SIREB (`$tarifa = resolver(...)`) **solo para decidir si el perfil aparece** (el gate silencioso B35), pero el `'precio'` que devuelve al front es `(float)$perfilTurno->precio_unitario` → el precio **del perfil** (15, lo que el admin tipeó al crear el turno). En cambio la reserva que se cobra y se congela en `PiscinaReservaDetalle.precio_unitario` (68) sale de la **tarifa SIREB** resuelta (10 = “Bañista mayores de 12 diurno” del sync que viste). Resultado: el ciudadano elige viendo 15, paga 10×2=20, y el comprobante dice 10 → **tres números distintos para lo mismo**, y el que ve al reservar (15) no es el que le cobran (10). Es más grave que B18 (que era sospecha de `monto_total` vs `monto_confirmado`): acá la discrepancia es **real y ya ocurrió en tus capturas**. KB SIREB: el precio autoritativo es el del catálogo, así que la pantalla de selección debería mostrar el SIREB (10), no el local del perfil (15).
*Mini‑prompt (fix de backend, no de front):* en `PiscinaDisponibilidadService.php` (147), cambiar `'precio' => (float)$perfilTurno->precio_unitario` por el precio de la tarifa SIREB ya resuelta (`$tarifa->precio_por_hora` o el campo equivalente de `TarifaCampo` 107), para que **lo que ve el ciudadano == lo que se cobra == lo que dice el comprobante**. Verificar contra `PiscinaReservaService.php` (284) de dónde congela el 10 al crear la reserva, y alinear ambos lados al SIREB. Conecta B30/B35 (mismo service, misma raíz: usa la tarifa como gate pero muestra el perfil).

**B36 🔴 — fecha ISO cruda UTC en un comprobante oficial.** El lado derecho de la fila de detalle dice `2026-10-25T04:00:00.000000Z`. Doble problema: (a) el front pinta el string **sin formatear** (microsegundos + `Z`), mientras el comprobante de cancha (`ComprobantePDF.tsx` 238) pinta “Domingo 25 de octubre de 2026” bonito → el front de piscina **no reusa** el formateador de cancha (opuesto de B12, que ya había refinado como local); (b) el `04:00:00Z` es medianoche La Paz (UTC‑4), así que la fecha 25/10 es correcta en local, pero pintarla con `Z` confunde y si el front hace `new Date().toLocaleDateString()` en otra zona puede mostrar 24/10. Con línea: `PiscinaReservaDetalle.php` (68) tiene cast `'fecha' => 'date'`, así que el string con hora+`Z` que se ve **no** viene del cast del modelo → viene del resource/controller que alimenta 190 sin pasar por el cast, o el front leyendo otro campo.
*Mini‑prompt (verificar):* leer `ComprobantePiscina.tsx` (190) → qué campo pinta ahí y por qué no pasa por el cast `date`; formatear con el mismo helper que `ComprobantePDF.tsx` (238) y resolver timezone (mostrar fecha local Bolivia, no el `Z`).

**B42 🔴 — “2 personas Bs 10.00” pinta el unitario como si fuera el total de la línea.** Con línea de `PiscinaReservaDetalle.php` (68): el detalle tiene `cantidad` (2) y `precio_unitario` (10) pero **no** tiene campo subtotal/monto (el total vive en la cabecera `PiscinaReserva` 49 = 20). La fila pinta “2 personas” + “Bs 10.00”, y el ciudadano lee “2 personas por 10”, pero arriba dice “Monto pagado Bs 20.00”. Aritméticamente 2×10=20 cuadra, pero **visualmente la fila miente**: el 10 es el unitario, no el costo de la línea.
*Mini‑prompt:* en `ComprobantePiscina.tsx` (190), pintar “2 × Bs 10.00 = Bs 20.00” (o el subtotal de la línea), no el unitario suelto. Conecta D28.

**B37 🔴 — “Monto pagado Bs 20.00” sin fuente SIREB + sospecha `monto_total` vs `monto_confirmado` (análogo B18/B30 en piscina).** Igual que B30 en la pantalla de detalle: el comprobante de piscina no declara que el precio sale del espejo SIREB. Y el label “Monto **pagado**” en un comprobante confirmado debería ser lo que SIREB reportó (`monto_confirmado`), no lo estimado al crear (`monto_total`) — no confirmo los campos de `PiscinaReserva.php` (49) sin leerlo, pero es el mismo patrón de B18. **Actualizo el refinamiento de B18/B30: se propagan a piscina**, así que el fix de “declarar fuente SIREB” y “pintar monto_confirmado” debe aplicarse a **ambos** comprobantes (238 y 190), no solo cancha.
*Mini‑prompt:* verificar `PiscinaReserva.php` (49) → ¿tiene `monto_confirmado`? Si sí, pintar ese en “Monto pagado”; agregar la franja/meta SIREB como en cancha (D6).

**B39 🔴 (sospecha alta) — sin QR ni “Mostrá el QR al personal”, rompe la asistencia.** El comprobante de cancha (`ComprobantePDF.tsx` 238) tiene QR + “Presentá este comprobante al llegar / Mostrá el código QR y tu documento al personal” + “válido sin firma ni sello” (los aciertos D16/D17 que protegí). Acá en piscina **no veo QR ni ese bloque**. Con línea: la asistencia se marca por código (`OcupacionTest` “verificar codigo”, `MarcarAsistenciaService` 125 / `AsistenciaController` 56); no confirmo si `PiscinaReservaController.php` (209) expone un QR/verificación para piscina. Sospecha: el ciudadano de piscina llega a la pileta **sin qué mostrar** y el funcionario **sin qué escanear**.
*Mini‑prompt (verificar):* leer `ComprobantePiscina.tsx` (190) + `PiscinaReservaController.php` (209) → ¿hay QR/endpoint de verificación de piscina? Si no, es hueco de operación, no solo de copy. Conecta D27.

**B38 — badge “confirmada” negro en minúscula vs verde “CONFIRMADO” con check de cancha.** Inconsistencia visual entre los dos comprobantes del mismo sistema; el ciudadano siente dos apps distintas. Conecta S20/S23.
*Mini‑prompt:* alinear el badge de 190 al estilo de 238 (verde + check + mayúsculas).

**B40 (menor, verificar) — ¿itera perfiles o pinta uno?** Con línea de `PiscinaReservaDetalle` (68): cada (reserva,turno,perfil) es una fila, así que si el ciudadano reservó 2 mayores + 1 menor, el comprobante debería listar **2 filas**. Acá se ve una sola “Mayores de 12” — correcto si solo reservó mayores, pero no confirmo si el front itera `detalles` o pinta el primero.
*Mini‑prompt:* verificar que 190 mapee todos los detalles, no el `[0]`.

**B41 (no es bug nuevo, alcance) — B6 décima vez.** Footer “pago online” otra vez → reconfirma fix global `Footer.tsx` (109).

## Didáctica ciudadano (continuo: piscina ciudadana cerró en D26)

**D27 — el comprobante de piscina debería reusar el layout/lenguaje de `ComprobantePDF.tsx` (238).** Hoy 190 parece un template más pobre (sin QR, sin membrete institucional visible, badge negro, fecha cruda). El ciudadano merece el mismo “documento oficial” en los dos recursos.
*Mini‑prompt:* extraer el layout de 238 a componente compartido y montarlo en 190 (cierra B36/B38/B39 de una).

**D28 — mostrar unitario × cantidad = subtotal en cada fila (cierra B42).** Que el ciudadano vea la aritmética, no un número suelto que parece total.
*Mini‑prompt:* ver B42.

## Sugerencias IA acotadas (continuo: piscina ciudadana cerró en S22)

**S23 — unificar los dos comprobantes (cancha 238 / piscina 190) en un template compartido** con mismo lenguaje (“válido sin firma ni sello”, “Mostrá el QR”), mismo badge verde, misma franja SIREB y misma fecha formateada. Es el fix de B36/B37/B38/B39 en un solo golpe y protege los aciertos D16/D17 de cancha llevándolos a piscina.
*Mini‑prompt:* ver D27.

**S24 — el `'precio'` de `PiscinaDisponibilidadService` (147) debe ser el de la tarifa SIREB, no el del perfil (cierra B43).** Esto es fix de **backend**, no de front: la pantalla de selección debe mostrar el mismo número que se cobra y que figura en el comprobante. KB SIREB: el catálogo fija el precio.
*Mini‑prompt:* ver B43.

## Lo que NO es bug (para que no persigas fantasmas)

- **Bs 20.00 total = 2 × 10** aritméticamente correcto; el bug no es el número, es que la fila muestre el unitario (B42) y que la selección mostrara 15 (B43).
- **“daniel hipamo” / “23425245” / “532545234”** = datos tipeados en la demo.
- **“PIS-20261025-9B4YLC”** = prefijo de cabecera de piscina; análogo a B21/S14: es **otro formato más** flotando (`PIS-` vs `RES-` vs `RSV-` vs `PTS-` vs `M4YY-`/`K699-`), a unificar en el frente de seguridad/códigos, no bug de esta pantalla.
- **Footer “pago online”** = B6 global, ya contado diez veces.

## Balance final: pública CERRADA

Crucé todas las imágenes contra el árbol de `web-public/src`. **Todas las páginas están cubiertas**: landing, catálogo (Lista+Mapa), detalle cancha, paso 2, pago, comprobante cancha, consultar estado, piscina ciudadana, **comprobante piscina (este)**. Los únicos frentes que faltan son **bloques compartidos/infra de menor riesgo individual**: `MedioDePago.tsx` (201) y `BarraCarrito.tsx` (48) — que son los bloques que S12/S16/S11 quieren **extraer/reusar**, verlos cierra el “hacer componente compartido” más que agregar bugs —, e infra (`router.tsx` 39 confirma si la ruta de piscina existe; `lib/api.ts` 20 si traduce 409/422 de B23/D13; `types` 87/25/75 el shape del presenter → B8/B21).

**Totales del dossier público: B1‑B43 (con refinamientos B12/B15/B17/B18/B30 propagados a piscina), D1‑D28, S1‑S24.** El ranking de riesgo de dinero/engaño, actualizado con este turno: **B43 🔴🔴** (discrepancia real 15 vs 10, el peor), **B1/B6/B16** (footer+landing “pago online” vs SIREB ventanilla/QR, fix global `Footer.tsx` 109, ya diez veces), **B35** (gate de tarifa silencioso en piscina ciudadana), **B13/B31** (contrato de campos cancha vs piscina), **B18/B37** (comprobantes monto esperado como “pagado” + sin fuente, ahora en los dos), **B36/B42** (fecha cruda + unitario como total en el comprobante de piscina), **B21** (help promete formato `PTS-` que el core no emite), **B30** (piscina sin declarar fuente SIREB), **B7** (“4 disponibles” sin fecha). El resto es pulido/UX.

## Guardado

```text
COMPROBANTE PISCINA (ComprobantePiscina.tsx 190) — DOSSIER, NO tejí, NO pedí
  archivos, sigo modo "ver todo corregir después". Numeración continua (…piscina
  ciudadana B29-B35/D24-D26/S20-S22 · comprobante piscina B36-B43/D27-D28/S23-S24).
  ÚLTIMA PÁGINA de web-public → PÚBLICA CERRADA.
B43 🔴🔴 EL ESTRELLA (dinero real, ya ocurrió en capturas): pantalla selección
  PiscinaDetalle 391 mostró "Mayores de 12 Bs 15.00" (precio_unitario del PERFIL,
  PiscinaTurnoPerfil 39, local del admin) PERO comprobante 190 muestra Bs 10.00
  (tarifa SIREB resuelta, sync "mayores de 12 diurno 10", congelada en
  PiscinaReservaDetalle 68 por PiscinaReservaService 284). Causa con línea:
  PiscinaDisponibilidadService 147 usa la tarifa SIREB ($tarifa=resolver) SOLO como
  gate (B35) pero devuelve 'precio'=>perfilTurno->precio_unitario (15) → lo que ve
  (15) ≠ lo que cobra (10) ≠ comprobante (10). Fix BACKEND: 147 'precio' => tarifa
  SIREB, alinear con 284. KB SIREB: catálogo fija precio. Más grave que B18 (acá
  discrepancia REAL visible, no sospecha).
B36 🔴 fecha ISO cruda UTC "2026-10-25T04:00:00.000000Z" en comprobante oficial;
  04:00Z=medianoche La Paz (fecha 25/10 ok local pero pinta Z); front 190 NO reusa
  formateador de cancha 238 ("Domingo 25 de octubre") = opuesto B12; cast 'date' de
  PiscinaReservaDetalle 68 no explica el string con Z → viene de resource/controller
  sin cast; verificar 190 + timezone.
B42 🔴 "2 personas Bs 10.00" = pinta precio_unitario (10) como si fuera total de
  línea; detalle 68 NO tiene subtotal (total en cabecera PiscinaReserva 49=20);
  aritmética 2×10=20 cuadra arriba pero la fila miente → "2 × Bs 10.00 = Bs 20.00".
B37 🔴 "Monto pagado" sin fuente SIREB + sospecha monto_total vs monto_confirmado
  (análogo B18/B30 en piscina) → REFINAMIENTO: B18/B30 se PROPAGAN a piscina, fix
  en AMBOS comprobantes (238+190); verificar PiscinaReserva 49 campos.
B39 🔴 sospecha alta: SIN QR ni "Mostrá el QR al personal" (cancha 238 sí, D16/D17)
  → ciudadano de piscina llega sin qué mostrar / funcionario sin qué escanear;
  verificar 190 + PiscinaReservaController 209 (¿endpoint verificación piscina?).
B38 badge "confirmada" negro minúscula vs verde "CONFIRMADO"+check de 238 (S20/S23).
B40 (menor) verificar 190 itera TODOS los detalles (PiscinaReservaDetalle 68 = 1
  fila por perfil) o pinta [0]. B41 = B6 décima vez → fix global Footer 109.
DIDÁCTICA: D27 190 reusa layout/lenguaje de 238 (cierra B36/B38/B39 de una). D28
  unitario×cantidad=subtotal en fila (cierra B42).
SUGERENCIAS: S23 unificar los dos comprobantes en template compartido (mismo QR/
  badge/franja SIREB/fecha) = fix B36/B37/B38/B39 en un golpe, protege D16/D17.
  S24 'precio' de PiscinaDisponibilidadService 147 = tarifa SIREB no perfil (cierra
  B43, fix BACKEND).
NO-BUG: Bs 20=2×10 correcto (bug es fila B42 + selección B43, no el número); datos
  tipeados=demo; "PIS-..."=otro prefijo flotando (B21/S14, unificar en frente
  códigos/seguridad, no bug de 190); footer=B6.
REFINAMIENTOS ACTUALIZADOS: B12 (cancha formatea bien, piscina cruda B36 = opuesto,
  local al front de piscina); B18/B30 PROPAGADOS a piscina (B37/B43); B35 y B43 son
  las dos caras del mismo desalineamiento en 147 (gate SIREB vs precio perfil).
KB HONESTA: SIREB aplica fuerte (B43/B37 precio autoritativo; B36 timezone toca qué
  día se cobró). Rolldown circularDependency=false + bundlerTimings/pluginTimings NO
  (no buildié web-public, es formato/dato). React DevTools NO como excusa (B36 parece
  campo roto pero lo decide leer 190; herramienta SÍ al depurar formateo CUANDO
  arranquemos). wa.me NO aplica (B39 es AUSENCIA del bloque QR; wa.me era P8 en
  ConsultarEstado/Pago).
PÚBLICA CERRADA: todas las páginas vistas. Faltan solo bloques/infra menor riesgo:
  MedioDePago 201 + BarraCarrito 48 (bloques a EXTRAER pa' S12/S16/S11), router 39
  (¿ruta piscina existe?), lib/api 20 (¿traduce 409/422 B23/D13?), types 87/25/75
  (shape presenter→B8/B21).
TOTALES DOSSIER PÚBLICO: B1-B43 (+refinamientos B12/B15/B17/B18/B30 propagados),
  D1-D28, S1-S24. RANKING DINERO/ENGAÑO: B43🔴🔴 (15 vs 10 real), B1/B6/B16 (footer
  "pago online" vs SIREB, fix global Footer 109, 10 veces), B35 (gate tarifa silencioso),
  B13/B31 (contrato campos), B18/B37 (comprobantes monto+fuente, ahora ambos), B36/B42
  (fecha cruda+unitario como total), B21 (help PTS-), B30 (piscina sin fuente), B7.
DECISIÓN SIGUIENTE (usuario): (a) darme MedioDePago 201 + BarraCarrito 48 + infra y
  cierro pública del todo, o (b) CERRAR pública acá y pasar a ADMIN/VENTANILLA (donde
  vive el lote de piscina tejido + Fix3/A4/D2 de cola) o a otra capa. NO tejo hasta
  "arrancamos".
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59; S13 link SIREB; B21/S14 unificar códigos PIS-/RES-/RSV-/PTS-/
  M4YY- cae acá) · D2 Bug C (TarifaCampo 107 NUNCA llegó — AHORA crítico: B43/B37
  dependen del campo precio de 107, pedirlo al arrancar) · C1→C2 · gates F1-F4 ·
  E mobile (piscina real piscina_datos_screen 260 + piscina_service 145; P3-paridad
  campo_detalle_screen 1073 + bloque_disponibilidad 85; B43/B36/B42/B39 se propagan
  a móvil pa' piscina; bloque recuperar QR S16 también móvil) · higiene (zombis, A3,
  D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote) · reunión
  P1/P2/P4/P5/P7 (orden 8→3→7→4→1+2; P3 reencuadrada S8; P8 reencuadrada S16; D11
  campo NIT ya prepara P1/P2).
```

En una línea: `ComprobantePiscina.tsx` (190) cierra la pública y, cruzado con el backend de piscina que ya vi, destapa **B43 🔴🔴, el bug de dinero más grave del dossier**: tus dos imágenes muestran la misma reserva a **Bs 15.00 al elegir** (precio del perfil, local) y **Bs 10.00 en el comprobante** (tarifa SIREB), porque el `PiscinaDisponibilidadService` (147) usa la tarifa SIREB solo como gate pero devuelve el precio del perfil, mientras la reserva congela la SIREB — lo que ves ≠ lo que te cobran ≠ lo que dice el comprobante, y el fix es de **backend** (S24: alinear el `'precio'` de 147 a la SIREB). Le siguen **B36** (fecha ISO cruda UTC `…T04:00:00Z` en un comprobante oficial, el front de piscina no reusa el formateador de cancha), **B42** (“2 personas Bs 10.00” pinta el unitario como total de línea), **B37** (monto sin fuente SIREB + sospecha `monto_total` vs `monto_confirmado`, que **propaga B18/B30 a los dos comprobantes**) y **B39** (sospecha alta: sin QR ni “Mostrá el QR al personal”, rompe la asistencia que cancha sí da); más B38/B40/B41 (badge negro, iterar perfiles, footer B6 décima vez). Con esto la **pública está cerrada** (B1‑B43 / D1‑D28 / S1‑S24, ranking de dinero encabezado por B43, luego B1/B6/B16, B35, B13/B31, B18/B37, B36/B42, B21, B30, B7), y solo faltan bloques/infra de menor riesgo (`MedioDePago` 201, `BarraCarrito` 48, `router`/`api`/`types`) — ojo que **D2 (`TarifaCampo.php` 107), que nunca llegó, ahora es crítico porque B43/B37 dependen del campo de precio de 107**, así que al arrancar lo pido primero. **Decime si me pasás esos últimos bloques y cierro pública del todo, o cerramos acá y pasamos a admin/ventanilla** (donde vive el lote de piscina y los frentes Fix 3/A4/D2 de la cola), sin haber tejido nada.
