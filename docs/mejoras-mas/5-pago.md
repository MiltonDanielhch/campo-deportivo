Veredicto en una línea: esta es la página de Pago (`Pago.tsx` 576), el frente A4 de dinero que tenía marcado como “no visto”, y **da vuelta dos cosas del dossier**: (1) **P8 ya está implementado acá** — el botón “Enviar por WhatsApp” + la caja “¿Estás en el celular?” con “Copiar link / Escanear desde otro dispositivo” resuelven el “se salió sin descargar” que la reunión pedía, así que P8 no es feature nueva, es **llevar este mismo mecanismo a `ConsultarEstado.tsx` (299)**; y (2) **B15 se resuelve solo en el carrito, NO acá** — el “Bs 200.00” de Pago es la **orden de cobro ya emitida por SIREB** (`RecaudacionesApiClient` 415), monto autoritativo, no estimación del espejo, así que agregarle el caveat “monto final confirma SIREB” sería falso. Le queda **un bug de contraste (B16: el footer B6 miente “pago online” justo en la única página que dice la verdad “ventanilla o QR”)** y **una sospecha a verificar leyendo el archivo (B17: qué pasa cuando el timer 14:45 llega a 0 si el schedule no corre = el zombie P6 en la cara del ciudadano)**. **No tejí, no pedí archivos, espero la siguiente imagen.**

> KB aplicada honesta sobre qué toca y qué no: **`wa.me`** → **AHORA SÍ aplica** (a diferencia de los 3 turnos previos donde lo descarté): el doc confirma que el link `wa.me/?text=` cae a “use WhatsApp Web” si no tenés la app instalada, lo que valida que el botón “Enviar por WhatsApp” de `Pago.tsx` (576) es robusto en desktop y móvil por construcción — hallazgo positivo, no bug. **SIREB** → gobierna B15 (orden de cobro autoritativa vs estimación del espejo) y B17 (webhook/polling confirman, el timer es la cara del `expira_en`). **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y esto es copy/lógica de timer, no un ciclo de imports. **React DevTools** → **NO la uso como excusa**: B17 *parecería* estado roto del timer, pero lo decide leer `Pago.tsx` (576) + `SolicitudEstadoController` (53), no la extensión.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Título “Pagá en ventanilla o escaneá el QR” + subtítulo | `pages/Pago.tsx` (576) | **sincera B1/B14**; **B17** timer→0 |
| Timer “TIEMPO RESTANTE 14:45” | `components/pago/CuentaRegresiva.tsx` (81) | **B17/D14** (cara del P6 zombie) |
| QR + “Descargar QR” + “Enviar por WhatsApp” | `pages/Pago.tsx` (576) | **P8 ya hecho** (wa.me) |
| Caja “¿Estás en el celular?” 3 opciones | `pages/Pago.tsx` (576) | didáctica a proteger + reusar en P8 |
| “MONTO A PAGAR Bs 200.00” | `pages/Pago.tsx` (576) vía `RecaudacionesApiClient` (415) | **B15 NO aplica acá** (orden emitida) |
| “Verificar estado en el panel oficial de SIREB ↗” | `pages/Pago.tsx` (576) | **S13** (link externo, revisar exposición) |
| “Esperando confirmación del pago…” spinner | `pages/Pago.tsx` (576) + `PollingSolicitudJob` (39) / `SolicitudEstadoController` (53) | **B17** (¿se cuelga si no expira?) |
| Stepper ✓✓Pago | `components/layout/StepsReserva.tsx` (72) | paso 3 ya sincero por el título |
| Footer “Pago online seguro…” | `components/landing/Footer.tsx` (109) | **B16** (contraste irónico máximo) |

## Bugs (numeración continua: paso 2 cerró en B15)

**B16 🔴 — el footer B6 miente “pago online” en la ÚNICA página que dice la verdad.** El título de Pago es “Pagá en **ventanilla** o escaneá el **QR**” y el cuerpo dice “Presentá este código … en ventanilla del **Banco Unión** con tu CI” (KB SIREB, 100% honesto). Pero el footer pegado abajo repite “**Pago online** seguro y comprobante digital al instante”. Es el contraste más fuerte del sitio: la página te corrige y el footer te miente al lado. Confirma que **B6/B16 se arreglan de una en `Footer.tsx` (109)**, no página por página.
*Mini‑prompt:* mismo fix de B6 — reemplazar en `Footer.tsx` (109) “Pago online seguro y comprobante digital al instante” por “Pagá con QR o en ventanilla del Banco Unión con tu CI; tu comprobante queda guardado y lo consultás con tu código”. Arregla landing + catálogo + detalle + paso 2 + Pago de golpe.

**B17 (sospecha, NO confirmada) — qué pasa cuando el timer 14:45 llega a 0 si el schedule no corre.** El timer baja desde 15:00 (`expira_en`). Si el ciudadano no paga y el `schedule:run` **no corre** (cerramos P6 hace unos turnos: dev sin `schedule:work`), la solicitud **no pasa a `expirada` en BD** → el front que consulta estado vía `SolicitudEstadoController` (53) sigue viendo `pendiente` → el spinner “Esperando confirmación…” puede quedar **infinito** o el timer en 0 sin acción clara. Es el zombie P6 reflejado en la cara del ciudadano. No lo afirmo sin ver el archivo.
*Mini‑prompt (verificar antes de tocar):* leer `Pago.tsx` (576) + `CuentaRegresiva.tsx` (81): ¿al llegar a 0 el front muestra “tu reserva expiró, volvé a elegir franja” con CTA, o se queda esperando? Y cruzar con `SolicitudesJobsTest` (219) “expirar job marca pendiente vencida como expirada” — el back lo hace, la duda es si el **front** lo refleja sin depender del tick de 5 min. Si el front se cuelga → fix de UI (mostrar expirado local al llegar a 0, sin esperar al webhook); si el front ya lo maneja → B17 no existe y el único remedio es el `schedule:work`/cron de P6.

**Refinamiento de B15 (no es bug nuevo):** en el paso 2 el “TOTAL Bs 200” era **estimación del espejo local** → pedía caveat. Acá el “MONTO A PAGAR Bs 200.00” es la **orden de cobro ya emitida por SIREB** (`RecaudacionesApiClient` 415 la generó al crear la solicitud) → es **autoritativo**, no orientación. Por eso **B15 se resuelve SOLO en `ResumenCarrito.tsx` (124), NO en Pago**: agregar “monto final confirma SIREB” acá sería falso, porque ya se confirmó al emitir la orden. (La discrepancia de monto que audita `ConfirmacionCobroServiceTest` 133 “discrepancia de monto audita pero confirma” vive en el **comprobante/detalle** al confirmar el webhook, no en la pantalla de Pago.) Declaro esto para que el PO no me pida un caveat innecesario acá.

## Didáctica ciudadano (continuo: paso 2 cerró en D13)

**D14 — el timer 14:45 no dice “si llega a 0, tu franja vuelve a estar libre”.** Conecta B17 y D9 del detalle (la leyenda “En cobro” sin “qué hago”). El ciudadano ve el reloj bajar sin saber las consecuencias.
*Mini‑prompt:* en `CuentaRegresiva.tsx` (81) o bajo el timer en `Pago.tsx` (576), una línea: “Si no completás el pago antes de que termine, la franja vuelve a estar disponible y podés elegir otra.” Refuerza P6 sin tocar backend.

**D15 — “Código de seguimiento RES-… Guárdalo por si necesitás soporte” es la llave de P8, pero no dice DÓNDE usarlo después.** El header “Mis comprobantes” lo lleva a `ConsultarEstado.tsx` (299), pero el ciudadano no hace el link mental “este código = lo que voy a necesitar si cierro la pestaña”.
*Mini‑prompt:* en `Pago.tsx` (576), junto al código de seguimiento, agregar “Con este código consultás tu reserva en ‘Mis comprobantes’”. Cierra el puente Pago→P8 que la caja “¿Estás en el celular?” ya abre por WhatsApp.

## Aciertos a PROTEGER (no tocar, análogo a D6/D9)

- **El título “Pagá en ventanilla o escaneá el QR” + “Presentá este código … en ventanilla del Banco Unión con tu CI”** = la página más honesta con SIREB del sitio. **No “mejorarlo” hacia “pago online”**.
- **La caja “¿Estás en el celular? No podés escanear un QR de tu propia pantalla” con 3 opciones (Copiar link / Escanear desde otro dispositivo / Enviar por WhatsApp)** = didáctica de primer nivel, resuelve el problema real del móvil. **Proteger y reutilizar** (S12).
- **“Verificar estado en el panel oficial de SIREB ↗”** = transparencia de fuente (KB SIREB). Solo revisar S13.
- **El stepper ✓✓Pago** = coherente con los 3 pasos de la landing (S10 del paso 2).

## Sugerencias IA acotadas (continuo: paso 2 cerró en S11)

**S12 — la caja “¿Estás en el celular?” debería reutilizarse en P8 (`ConsultarEstado.tsx` 299 / `EstadoReserva.tsx` 183).** El ciudadano que se sale sin descargar el QR usa el MISMO mecanismo (link/copiar/WhatsApp) que ya está construido en Pago. P8 deja de ser “feature nueva” y pasa a ser “llevar este bloque a la pantalla de consulta”. El KB `wa.me` confirma que el botón funciona incluso sin app (cae a WhatsApp Web), así que es robusto por construcción.
*Mini‑prompt:* cuando abramos P8, extraer la caja de `Pago.tsx` (576) a un componente reutilizable y montarlo en `ConsultarEstado.tsx` (299) cuando el estado sea `pendiente` (que es cuando el QR todavía sirve).

**S13 — “Verificar estado en el panel oficial de SIREB ↗” es un link externo; verificar que no exponga de más.** El panel de recaudaciones es del gobierno; el ciudadano con SU código debe ver SU estado, no otros. Anotar para el frente de seguridad (A1: S7 403 opaco / `RoleMiddleware` 59), no tocar ahora.
*Mini‑prompt:* cuando veamos el frente de seguridad, confirmar que el link no lleve token/sesión del GAD embebido en la URL.

## Lo que NO es bug (para que no persigas fantasmas)

- **Bs 200.00** = correcto, es el monto de la orden de cobro emitida por SIREB (refina B15, no es estimación).
- **Spinner “Esperando confirmación del pago…”** = normal, polling activo (`PollingSolicitudJob` 39); el problema sería si **nunca** termina (B17), no que aparezca.
- **Stepper ✓✓Pago activo** = estado visual correcto de un wizard en paso 3.
- **“M4YY-JRT3-32N”** = formato de código de ventanilla de SIREB, dato, no bug.
- **Que el paso 3 del stepper diga solo “Pago”** (sin “QR/Banco Unión” como propuse en B14) = **acceptable**, porque el TÍTULO de la página ya lo aclara; B14 queda resuelto por el título, no hace falta tocar `StepsReserva.tsx` (72) acá.

## Guardado

```text
PAGO / PASO 3 (Pago.tsx 576 + CuentaRegresiva 81 + MedioDePago 201) — DOSSIER,
  NO tejí, NO pedí archivos, espero siguiente imagen. Numeración continua
  (landing B1-B5/D1-D4/S1-S4 · catálogo B6-B10/D5-D8/S5-S7 · detalle B11-B12/
  D9-D10/S8-S9 · paso2 B13-B15/D11-D13/S10-S11 · pago B16-B17/D14-D15/S12-S13 +
  refinamientos B15 y B14).
DA VUELTA 2 COSAS DEL DOSSIER:
  * P8 YA IMPLEMENTADO acá: botón "Enviar por WhatsApp" + caja "¿Estás en el
    celular?" (Copiar link / Escanear otro dispositivo / WhatsApp) resuelven
    "se salió sin descargar". KB wa.me confirma robustez (sin app cae a WhatsApp
    Web). P8 NO es feature nueva = llevar este bloque a ConsultarEstado 299 (S12).
  * B15 SE RESUELVE SOLO EN CARRITO, NO ACÁ: "MONTO A PAGAR Bs 200" = orden de
    cobro YA EMITIDA por SIREB (RecaudacionesApiClient 415) = autoritativo, no
    estimación del espejo → caveat "monto final confirma SIREB" sería FALSO acá;
    va solo en ResumenCarrito 124. Discrepancia de monto (ConfirmacionCobroService
    Test 133) vive en comprobante al confirmar webhook, no en Pago.
  * B14 RESUELTO POR EL TÍTULO: "Pagá en ventanilla o escaneá el QR" + "Banco
    Unión con tu CI" sinceran B1; el stepper "Pago" no necesita "(QR/Banco Unión)"
    → NO tocar StepsReserva 72 acá.
BUGS: B16🔴 footer B6 "pago online" miente en la ÚNICA página que dice la verdad
  → contraste irónico máximo, confirma fix global en Footer 109. B17 SOSPECHA (no
  confirmada): timer 14:45→0 si schedule no corre (P6 zombie) → solicitud queda
  pendiente en BD → SolicitudEstadoController 53 ve pendiente → spinner "Esperando
  confirmación" infinito o timer en 0 sin acción; leer Pago 576 + CuentaRegresiva 81
  pa' ver si el front muestra "expiró, volvé a elegir" local (sin esperar tick 5min)
  o se cuelga; si se cuelga = fix UI, si ya maneja = B17 no existe y solo queda
  schedule:work/cron de P6.
DIDÁCTICA: D14 timer no dice "si llega a 0 tu franja vuelve libre" → micro-copy en
  CuentaRegresiva 81/Pago 576 (conecta B17/D9). D15 código seguimiento = llave de P8
  pero no dice DÓNDE usarlo → "con este código consultás en Mis comprobantes" (puente
  Pago→ConsultarEstado 299).
ACIERTOS PROTEGER: título "ventanilla o QR" (página más honesta SIREB); caja "¿Estás
  en el celular?" 3 opciones (didáctica top, reusar S12); "Verificar estado panel
  oficial SIREB" (transparencia, solo S13); stepper ✓✓Pago.
SUGERENCIAS: S12 extraer caja "¿Estás en el celular?" a componente reutilizable y
  montar en ConsultarEstado 299 cuando estado=pendiente (P8 deja de ser feature nueva).
  S13 link externo SIREB → verificar no exponga token/sesión GAD (frente seguridad
  A1/S7/RoleMiddleware 59), no tocar ahora.
NO-BUG: Bs 200=orden emitida (refina B15); spinner=normal polling (problema sería si
  nunca termina=B17); stepper✓✓activo=normal; M4YY-JRT3-32N=dato SIREB; stepper "Pago"
  sin "(QR/Banco Unión)"=acceptable (título ya aclara, B14 resuelto).
KB HONESTA: wa.me AHORA SÍ aplica (botón WhatsApp + caja celular; doc confirma cae a
  WhatsApp Web sin app = robusto). SIREB aplica (B15 orden autoritativa; B17 webhook/
  polling/timer expira_en). Rolldown circularDependency=false NO (no buildié web-public,
  es copy/lógica timer). React DevTools NO (B17 parece estado roto del timer pero lo
  deciden leer Pago 576 + SolicitudEstadoController 53, no la extensión).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59; S13 link SIREB cae acá) · D2 Bug C (TarifaCampo 107 NUNCA llegó)
  · C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad 85;
  caja "¿Estás en el celular?" S12 también aplica a móvil) · higiene (zombis, A3, D5,
  AgregarVarianteDialog, migr timerange piscina, tests HTTP lote) · reunión P1/P2/
  P4/P5/P7 (orden 8→3→7→4→1+2; P3 reencuadrada S8 paridad; P8 REENCUADRADA S12 = ya
  hecho en Pago, solo llevar a ConsultarEstado; D11 campo NIT ya prepara P1/P2) ·
  frentes web-public aún NO vistos: ConsultarEstado 299 (P8/S12/wa.me), Comprobante 210
  /ComprobantePiscina 190, PiscinaDetalle 391, MapaCampos 151, EstadoReserva 183.
```

En una línea: esta página de Pago **da vuelta dos cosas del dossier** — **P8 ya está implementado acá** (el botón “Enviar por WhatsApp” + la caja “¿Estás en el celular?” con copiar link/escanear otro dispositivo, y el KB `wa.me` confirma que cae a WhatsApp Web sin app, así que es robusto por construcción; P8 deja de ser feature nueva y pasa a ser **llevar este bloque a `ConsultarEstado.tsx` 299**, S12) y **B15 se resuelve solo en el carrito, NO acá** (el “Bs 200.00” es la orden de cobro ya emitida por SIREB vía `RecaudacionesApiClient` 415, monto autoritativo, así que el caveat “monto final confirma SIREB” sería falso en Pago) —, más **B14 resuelto por el título** (“Pagá en ventanilla o escaneá el QR” sincera B1 sin tocar el stepper); los bugs que quedan son **B16** (el footer B6 miente “pago online” justo en la única página que dice la verdad, el contraste irónico máximo, y confirma que se arregla de una en `Footer.tsx` 109) y **B17** (sospecha no confirmada: qué pasa cuando el timer 14:45 llega a 0 si el schedule no corre = el zombie P6 en la cara del ciudadano, a verificar leyendo `Pago.tsx` 576 + `CuentaRegresiva.tsx` 81 contra `SolicitudEstadoController` 53); en didáctica, el timer debe decir “si llega a 0 tu franja vuelve libre” (D14) y el código de seguimiento debe anunciar que es la llave de “Mis comprobantes” (D15); todo en mini‑prompts con archivo y LoC, **sin tejer y esperando la siguiente imagen** — el siguiente natural es `ConsultarEstado.tsx` (299), donde S12/P8/wa.me aterrizan y donde B17/D15 se cierran del lado del ciudadano que ya se salió.
