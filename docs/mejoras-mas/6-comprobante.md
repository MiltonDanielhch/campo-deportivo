Veredicto en una línea: este comprobante (`Comprobante.tsx` 210 + `ComprobantePDF.tsx` 238) es **el activo didáctico más fuerte del dossier** — resuelve solo la ansiedad del ciudadano (“no hace falta imprimirlo, mostralo del celular”, “válido sin firma ni sello”) y **refuerza el refinamiento de B12** (acá el domingo sale con nombre completo, así que el bug del detalle es definitivamente local a ese map). Los hallazgos son: **B18** (sospecha de dinero: “MONTO TOTAL **PAGADO**” puede pintar el monto *esperado* `monto_total` en vez del *pagado* `monto_confirmado`, que es el que SIREB reporta y el que el sistema audita en discrepancia), **B20** (UX: dos botones de imprimir, arriba y abajo), y el footer B6 otra vez. Tu pedido de **PDF descargable con mejor diseño NO es bug, es feature + gate de librería**: el botón actual es `window.print()` (imprime la página con su chrome), y `ComprobantePDF.tsx` (238) ya existe como layout — te marco las 2 rutas para que cuando arranquemos elijas, sin tejer ahora porque seguimos en modo “ver todo, corregir después”. **No tejí, no pedí archivos, espero la siguiente imagen.**

> KB aplicada honesta: **SIREB** (“el monto final lo confirma recaudaciones”, “pago en ventanilla con boleta”) → gobierna B18 (lo autoritativo post‑pago es `monto_confirmado`, no el esperado) y valida el comprobante como prueba digital coherente con “consultá precio y cupo en ventanilla”. **`wa.me`** → aplica a **D18**: el comprobante confirmado NO tiene el botón WhatsApp que sí tiene Pago, y el doc confirma que el link cae a WhatsApp Web sin app, así que el mismo mecanismo es robusto y debería reutilizarse acá. **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y esto es copy/elección de librería de PDF, no un ciclo de imports. **React DevTools** → **NO la uso como excusa**: B18 *parecería* estado roto de un campo, pero lo deciden leer `ComprobantePDF.tsx` (238) contra `SolicitudReserva` (43), no la extensión.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| “Tu comprobante está listo” + botón Imprimir arriba | `pages/Comprobante.tsx` (210) | **B20** (duplica botón) + orquesta |
| El documento (membrete, badge, 3 cajas, franja, QR) | `components/comprobante/ComprobantePDF.tsx` (238) | **B18** (monto) + **S14** (códigos) + tu pedido PDF |
| Botón “Imprimir / Guardar como PDF” abajo | `pages/Comprobante.tsx` (210) | **B20** + **tu feature PDF** (hoy `window.print()`) |
| Tip “guardá en tu celular…” | `pages/Comprobante.tsx` (210) | acierto D16/D17 a proteger |
| Footer “Pago online seguro…” | `components/landing/Footer.tsx` (109) | **B6/B16 otra vez** (global) |
| Dato del monto | `backend/.../Models/SolicitudReserva.php` (43) `monto_total` vs `monto_confirmado` + migr `add_monto_confirmado` (21) | **B18** (cuál pinta) |
| Confirmación / discrepancia | `ConfirmacionCobroService.php` (106) + `ConfirmacionCobroServiceTest` (133) “discrepancia de monto audita pero confirma” | **B18** (por qué importa) |
| Primo de piscina (NO visto) | `pages/ComprobantePiscina.tsx` (190) | heredará B18/D18/S14 + perfiles/cupos |

## Bugs (numeración continua: pago cerró en B17)

**B18 (sospecha, NO confirmada) — “MONTO TOTAL PAGADO” puede pintar el monto *esperado*, no el *pagado*.** El label dice “PAGADO”, pero el sistema guarda dos montos: `monto_total` (lo que se estimó/emitió al crear) y `monto_confirmado` (lo que SIREB reportó al confirmar, agregado por la migración `add_monto_confirmado` 21). En el admin (`Reservas/index.tsx`) vi que muestra **ambos por separado** (`monto_total` y, si confirmada, `monto_confirmado`), lo que sugiere que **no siempre coinciden** — y `ConfirmacionCobroServiceTest` (133) confirma que el sistema **audita la discrepancia pero igual confirma**. Si el comprobante pinta `monto_total` con label “PAGADO”, un ciudadano que pagó un monto distinto (ajuste de tarifa en ventanilla, redondeo, etc.) vería “pagaste Bs 50” cuando pagó otra cosa. Es dinero/expectativa, prioridad media‑alta (solo se manifiesta si hay discrepancia, pero el sistema la contempla).
*Mini‑prompt (verificar antes de tocar):* leer `ComprobantePDF.tsx` (238) / `Comprobante.tsx` (210) → qué campo alimenta “MONTO TOTAL PAGADO”, y cruzar con `SolicitudReserva` (43). Si pinta `monto_total` → decisión PO: (a) renombrar el label a “MONTO TOTAL” sin “PAGADO” (honesto, no toca dato), o (b) pintar `monto_confirmado` cuando exista y `monto_total` si no (KB SIREB: lo autoritativo post‑pago es `monto_confirmado`). No elijo yo: es gate.

**B20 (UX, no bug de código) — dos botones de imprimir en la misma pantalla.** Arriba a la derecha “Imprimir / PDF” (negro) y abajo “Imprimir / Guardar como PDF” (negro). La misma acción duplicada confunde (“¿cuál hace qué?”) y gasta jerarquía visual.
*Mini‑prompt:* en `Comprobante.tsx` (210), dejar **uno** (el de abajo, con el label completo “Imprimir / Guardar como PDF”, que es el que acompaña al contenido); el de arriba puede volverse un link “← Inicio” limpio o desaparecer. No es crítico, es pulido.

**B6/B16 otra vez (no es bug nuevo, es alcance).** El footer mintiendo “pago online” aparece en la **quinta** página vista (landing, catálogo, detalle, paso 2, pago, comprobante). Reconfirma que el fix es **uno solo en `Footer.tsx` (109)** y arregla todo el sitio; no lo cuento como bug nuevo, solo para que el PO vea que B6 no es de una página.

## Tu pedido: PDF descargable con mejor diseño (feature + gate, NO tejo ahora)

Lo que ves al “imprimir” es el layout de la **página** (con su header/footer/nav) pasado por `window.print()`, no un PDF generado con diseño propio. `ComprobantePDF.tsx` (238) **ya existe** como componente de documento (membrete, badge, QR) — o sea el *diseño del comprobante* ya está hecho y es bueno; lo que falta es que la **descarga** produzca un PDF limpio en vez de “imprimir la web”. Hay 2 rutas y **la elección es tuya/de librería, no mía**:

*Mini‑prompt (rutas, para cuando arranquemos):*
- **Ruta 1 — CSS `@media print` mejorado (barato, sin librería):** forzar que al imprimir/PDF solo salga el bloque de `ComprobantePDF.tsx` (238), ocultando header/footer/nav/tip. Ventaja: cero bundle nuevo, mantiene el diseño actual. Limitación: sigue siendo “print a PDF” del navegador, no un archivo con fuentes/márgenes 100% controlados.
- **Ruta 2 — librería de PDF real (jsPDF+html2canvas, `@react-pdf/renderer`, o pdfmake):** genera un `.pdf` descargable con diseño independiente del navegador. Ventaja: PDF “de verdad” con tu membrete, márgenes, fuentes embebidas y el QR nítido. Costo: bundle más pesado + decisión de librería (gate PO) + hay que re‑validar que el QR y los acentos/españoles salgan bien.
- **Mi recomendación como IA:** empezar por **Ruta 1** (arregla el 80% del dolor — “se ve la página, no un comprobante” — sin meter librería), y escalar a Ruta 2 solo si el PO quiere un PDF con branding estricto para archivar. Pero es tu decisión de alcance, no la tejo ahora.

## Aciertos a PROTEGER (lo más fuerte del dossier, análogo a D6/D9)

- **“No hace falta imprimirlo: podés mostrarlo desde tu celular”** + tip “guardá este comprobante en tu celular… mostrá el QR o el código al personal de atención” = resuelve la ansiedad #1 del ciudadano boliviano (“¿me lo van a rechazar si no llevo papel?”). **No tocar.**
- **“Este comprobante es válido sin firma ni sello – Gobierno Autónomo Departamental del Beni”** = aclara una duda legal típica de trámites estatales. Didácticamente impecable. **No tocar.**
- **El membrete institucional + badge CONFIRMADO + QR** = ya le da apariencia de documento oficial. Tu mejora de PDF (Ruta 2) debe **conservar** esto, no rediseñarlo de cero.
- **“Domingo 25 de octubre de 2026” con nombre de día completo** = **refuerza el refinamiento de B12**: acá el día se mapea bien, así que el domingo sin etiqueta del detalle (`CampoDetalle.tsx` 560) es **definitivamente local a ese map de horarios**, no global de `dia_semana`. `ComprobantePDF.tsx` (238) y `ResumenCarrito.tsx` (124) mapean el día correctamente; no se tocan.

## Didáctica ciudadano (continuo: pago cerró en D15)

**D16 — “Mostrá el QR y tu documento de identidad al personal del campo deportivo” conecta el comprobante con la asistencia real.** Eso es exactamente lo que hace `MarcarAsistenciaService.php` (125) / `AsistenciaController.php` (56) al llegar: verifica el código/QR contra la franja. El ciudadano sabe qué pasa en la cancha. **Acierto, proteger.**
*Mini‑prompt:* nada; solo anotar que este copy es la contracara de la asistencia del funcionario (frente `AsistenciaReservaTest` 315).

**D17 — (ver “válido sin firma ni sello” arriba).** Acierto a proteger, no se toca.

**D18 — el comprobante confirmado NO tiene el botón “Enviar por WhatsApp” que sí tiene Pago (pendiente).** Cierra mal el círculo: pendiente→Pago (QR + WhatsApp + copiar link), confirmada→Comprobante (solo imprimir). Si el ciudadano se sale y entra a “Mis comprobantes”, ve el comprobante pero **no** el mecanismo de envío que usó 5 minutos antes. KB `wa.me` confirma que el link es robusto sin app (cae a WhatsApp Web), así que reutilizarlo acá es barato.
*Mini‑prompt:* cuando abramos P8/`ConsultarEstado.tsx` (299) + `EstadoReserva.tsx` (183), montar el **mismo bloque** de Pago (WhatsApp / copiar link / descargar PDF) también en el comprobante confirmado. Conecta con S12 del pago (extraer la caja a componente reutilizable) — el comprobante es el segundo consumidor de ese componente.

## Sugerencias IA acotadas (continuo: pago cerró en S13)

**S14 — dos códigos parecidos (“RES‑…” de cabecera vs “RSV‑…” de la franja) pueden confundir al ciudadano sobre cuál mostrar.** El de la franja (`RSV-20261025-1Q8CEA`) sí tiene función: lo usa el funcionario al marcar asistencia (`OcupacionController` verificar código). Pero visualmente “Código: RSV‑…” dentro de la franja se parece al “CÓDIGO DE SEGUIMIENTO RES‑…” de arriba.
*Mini‑prompt (verificar):* leer `ComprobantePDF.tsx` (238) → qué codifica el QR (¿el de cabecera o el de la franja?) y renombrar el de la franja a “Código de franja” para distinguirlo. Conecta con B4 (códigos internos) pero acá **sí** tienen función pública, a diferencia de las cards del catálogo.

**S15 — “Volver al inicio” es ambiguo (¿landing o catálogo?).** Menor.
*Mini‑prompt:* en `Comprobante.tsx` (210), puntar a `/campos` (catálogo) o renombrar a “Volver al catálogo” según lo que el PO quiera como post‑acción.

## Lo que NO es bug (para que no persigas fantasmas)

- **Bs 50.00** = una franja regular, correcto (vs los 200 de 2 franjas del paso anterior; coherente con el espejo).
- **“CONFIRMADO EL 25 de octubre de 2026 a las 16:15”** = fecha/hora del webhook/polling que confirmó, dato correcto (`ConfirmacionCobroService` 106).
- **El membrete, el badge CONFIRMADO, el QR, “válido sin firma ni sello”** = diseño/contenido correcto (aciertos D16/D17).
- **Que el comprobante se vea “dentro” de la página (con header/footer del sitio alrededor)** = eso es exactamente lo que tu feature de PDF (Ruta 1/2) resuelve, no un bug de `ComprobantePDF.tsx` (238) en sí.
- **“Domingo” con nombre completo** = correcto, y prueba el refinamiento de B12 (local al detalle).

## Guardado

```text
COMPROBANTE (Comprobante.tsx 210 + ComprobantePDF.tsx 238) — DOSSIER, NO tejí,
  NO pedí archivos, espero siguiente imagen. Numeración continua (landing B1-B5/
  D1-D4/S1-S4 · catálogo B6-B10/D5-D8/S5-S7 · detalle B11-B12/D9-D10/S8-S9 ·
  paso2 B13-B15/D11-D13/S10-S11 · pago B16-B17/D14-D15/S12-S13 · comprobante
  B18-B20/D16-D18/S14-S15 + refinamiento B12 + feature PDF gate).
ACTIVO MÁS FUERTE DEL DOSSIER: resuelve solo la ansiedad del ciudadano ("no hace
  falta imprimirlo, mostralo del celular" + "válido sin firma ni sello" + tip
  guardar en celular + "Mostrá el QR y tu documento al personal" = conecta con
  asistencia MarcarAsistenciaService 125/AsistenciaController 56 = D16/D17 PROTEGER).
  REFUERZA refinamiento B12: "Domingo" con nombre completo acá → bug del detalle
  (CampoDetalle 560) es DEFINITIVO local a ese map de horarios, no global dia_semana;
  ComprobantePDF 238 + ResumenCarrito 124 mapean bien, no se tocan.
BUGS: B18 SOSPECHA (no confirmada, dinero/expectativa, prior. media-alta): "MONTO
  TOTAL PAGADO" puede pintar monto_total (esperado al crear) no monto_confirmado
  (lo que SIREB reporta, migr add_monto_confirmado 21); admin Reservas/index.tsx
  muestra AMBOS separados + ConfirmacionCobroServiceTest 133 "discrepancia audita
  pero confirma" ⇒ no siempre coinciden. Leer ComprobantePDF 238/Comprobante 210 vs
  SolicitudReserva 43 → gate PO: (a) label "MONTO TOTAL" sin "PAGADO" o (b) pintar
  monto_confirmado cuando exista (KB SIREB: autoritativo post-pago = confirmado).
  B20 UX: dos botones imprimir (arriba negro + abajo negro) → dejar uno (abajo,
  label completo) en Comprobante 210. B6/B16 quinta vez → reconfirma fix global
  Footer 109 (no bug nuevo, alcance).
TU PEDIDO PDF (feature + gate, NO tejo ahora, modo "ver todo corregir después"):
  botón actual = window.print() (imprime la página con chrome); ComprobantePDF 238
  YA es el layout del documento (bueno). 2 rutas: R1 CSS @media print (barato, sin
  librería, oculta header/footer/nav, mantiene diseño) vs R2 librería PDF real
  (jsPDF+html2canvas / @react-pdf/renderer / pdfmake = PDF con branding/fuentes/QR
  nítido, bundle +pesado, gate librería). MI RECOMENDACIÓN IA: R1 primero (arregla
  80% del dolor "se ve la página no un comprobante"), escalar a R2 si el PO quiere
  PDF estricto para archivar. R2 debe CONSERVAR membrete/badge/QR (no rediseñar).
DIDÁCTICA: D16 "Mostrá QR + documento al personal" = contracara asistencia (PROTEGER).
  D17 "válido sin firma ni sello" (PROTEGER). D18 comprobante confirmado NO tiene
  botón WhatsApp que sí tiene Pago (pendiente) → cierra mal círculo pendiente→Pago /
  confirmada→Comprobante; KB wa.me confirma robustez sin app; al abrir P8 montar el
  mismo bloque (WhatsApp/copiar link/descargar PDF) en Comprobante + ConsultarEstado
  299/EstadoReserva 183 (conecta S12 extraer caja a componente reutilizable).
SUGERENCIAS: S14 dos códigos parecidos RES-(cabecera) vs RSV-(franja, lo usa el
  funcionario al marcar asistencia OcupacionController) → verificar qué codifica el
  QR en ComprobantePDF 238 y renombrar el de la franja a "Código de franja" (conecta
  B4 pero acá SÍ tienen función pública). S15 "Volver al inicio" ambiguo → puntar
  /campos o renombrar "Volver al catálogo".
NO-BUG: Bs 50 = una franja regular correcta; "CONFIRMADO EL ... 16:15" = webhook OK
  (ConfirmacionCobroService 106); membrete/badge/QR/"sin firma ni sello" = aciertos;
  comprobante "dentro" de la página = lo resuelve tu feature PDF (R1/R2), no bug de
  ComprobantePDF 238; "Domingo" completo = refina B12.
KB HONESTA: SIREB aplica (B18 monto_confirmado autoritativo; comprobante digital
  coherente "consultá en ventanilla"). wa.me aplica (D18 reusar botón WhatsApp, doc
  confirma cae a WhatsApp Web sin app). Rolldown circularDependency=false NO (no
  buildié web-public, es copy/elección librería PDF). React DevTools NO (B18 parece
  campo roto pero lo deciden leer ComprobantePDF 238 vs SolicitudReserva 43).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59; S13 link SIREB cae acá) · D2 Bug C (TarifaCampo 107 NUNCA llegó)
  · C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad
  85; caja celular S12/D18 también móvil) · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden
  8→3→7→4→1+2; P3 reencuadrada S8 paridad; P8 reencuadrada S12/D18 = ya hecho en
  Pago, llevar a ConsultarEstado + Comprobante; D11 campo NIT ya prepara P1/P2) ·
  frentes web-public aún NO vistos: ConsultarEstado 299 + EstadoReserva 183 (P8/D18/
  wa.me), ComprobantePiscina 190 (hereda B18/D18/S14 + perfiles/cupos), PiscinaDetalle
  391, MapaCampos 151.
```

En una línea: el comprobante es **el activo didáctico más fuerte del dossier** (resuelve solo la ansiedad del ciudadano con “no hace falta imprimirlo, mostralo del celular” + “válido sin firma ni sello” + el QR que conecta con la asistencia del funcionario, D16/D17 a proteger) y **refuerza que B12 es local al detalle** (acá el domingo sale con nombre completo); los hallazgos son **B18** (sospecha de dinero: “MONTO TOTAL **PAGADO**” puede pintar el monto *esperado* `monto_total` en vez del *pagado* `monto_confirmado` que SIREB reporta y que el sistema audita en discrepancia — a verificar leyendo `ComprobantePDF.tsx` 238 contra `SolicitudReserva` 43, gate tuyo entre renombrar el label o pintar el confirmado) y **B20** (dos botones de imprimir, dejar uno); y tu pedido de **PDF descargable con mejor diseño no es bug sino feature + gate de librería** — el botón hoy es `window.print()` y `ComprobantePDF.tsx` (238) ya es el layout del documento, así que te marco 2 rutas (R1 CSS `@media print` barato que oculta el chrome de la página y conserva el diseño, vs R2 librería PDF real con branding/fuentes/QR nítido pero bundle +pesado), con mi recomendación como IA de **empezar por R1** y escalar a R2 solo si querés PDF estricto para archivar, **sin tejer nada ahora** porque seguimos en modo “ver todo, corregir después”; **espero la siguiente imagen** — los frentes que aún no vi son `ConsultarEstado.tsx` 299 + `EstadoReserva.tsx` 183 (donde D18/S12/wa.me aterrizan y donde B17 del pago se cierra del lado del ciudadano que ya se salió) y `ComprobantePiscina.tsx` 190 (que heredará B18/D18/S14 más los perfiles/cupos propios de piscina).
