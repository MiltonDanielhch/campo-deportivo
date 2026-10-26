Veredicto en una línea: esta es `ConsultarEstado.tsx` (299) + `EstadoReserva.tsx` (183), el frente P8 que venía marcando desde Pago, y **tu pregunta (“cómo buscar el QR si se salió”) ya tiene respuesta en el copy de esta misma página**: el subtítulo promete “descargar el comprobante o **volver a pagar**”, el input acepta **CI/NIT o teléfono** (no hay que recordar el `RES-`), y el botón “Ir a pagar ahora” reabre el QR. Lo que **falta** (mi S12 aterrizado) es que el QR + el bloque WhatsApp/copiar-link se muestren **en línea acá**, sin obligar a navegar a Pago. Le siguen 3 hallazgos: **B21** (el help de búsqueda describe un formato de código de ventanilla `PTS-…` que la pantalla de Pago **no** emite — emite `M4YY-JRT3-32N` —, contradicción directa), **B22** (refina B17: el front **sí** hace polling automático, lo cual es bueno, pero si el `schedule` no corre la solicitud queda `pendiente` en BD y el polling devuelve `pendiente` → la card “Pendiente” con “Vence 3:35” se queda así para siempre aunque el reloj pasó), y el footer B6 por séptima vez. **No tejí, no pedí archivos, sigo en modo dossier.**

> KB honesta: **`wa.me`** → **AHORA SÍ aplica de lleno**, porque tu pregunta es literalmente “buscar el QR para pagar”: el bloque WhatsApp/copiar-link de Pago (576) es la respuesta, y el doc confirma que el link cae a WhatsApp Web sin app, así que reutilizarlo en línea acá es robusto. **SIREB** → gobierna B21 (los códigos de ventanilla/seguimiento los emite el core, el help no puede inventar un formato) y el monto en pendiente (orden emitida, B18 **no** aplica acá). **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y esto es copy/lógica de polling, no un ciclo de imports. **React DevTools** → **NO la uso como excusa**: B22 *parecería* estado roto del spinner, pero lo deciden leer `Pago`/`ConsultarEstado` contra `PollingSolicitudJob` (39) y `SolicitudEstadoController` (53), no la extensión.

## Respuesta a tu pregunta: cómo se busca el QR si el usuario se salió

Con línea del árbol, el mecanismo **ya existe en 3 piezas**, y la mejora es pegarlas:

1. **Recuperar la reserva sin recordar el código.** El input de `ConsultarEstado.tsx` (299) acepta **código de seguimiento (`RES-…`), código de ventanilla, CI/NIT o teléfono** (lo dice el subtítulo y el help). O sea: el ciudadano que cerró la pestaña escribe su **CI o teléfono** y el backend (`ConsultaEstadoController` 159 / `SolicitudEstadoController` 53) le trae la reserva. **No necesita el `RES-` exacto.** Esto es lo más fuerte de la página y hay que protegerlo.
2. **Volver al QR.** El botón **“Ir a pagar ahora”** de la card pendiente lleva a `Pago.tsx` (576), que **regenera y muestra el QR** con el mismo código. Entonces el QR **no se pierde** al salirse: se recupera por CI/teléfono → “Ir a pagar ahora” → QR.
3. **Lo que falta (S12/S16):** hoy el QR **no se ve en línea** en `EstadoReserva.tsx` (183); hay que navegar a Pago. La mejora concreta es **montar el bloque de Pago (QR + “Enviar por WhatsApp” + “Copiar link” + “Descargar”) directamente en la card “Pendiente de pago” de esta página**, extrayéndolo a un componente reutilizable. Así el ciudadano que se salió y entra a “Mis comprobantes” ve el QR **sin ir y volver**. Eso es exactamente P8 cerrado.

*Arista a considerar (S17):* si el ciudadano busca por **CI/teléfono y tiene varias reservas pendientes**, ¿la API devuelve una o lista? Si devuelve lista, la UI necesita un **selector** (“elegí cuál QR”), no pintar un solo QR a ciegas. No lo confirmo sin ver `ConsultaEstadoController` (159); lo anoto para cuando arranquemos P8.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Subtítulo “…descargar el comprobante o volver a pagar” | `pages/ConsultarEstado.tsx` (299) | **D19** (la intención de P8 ya está en el copy) |
| Input “Código, CI/NIT o teléfono” + help con formatos | `pages/ConsultarEstado.tsx` (299) | **B21** (help describe formato que Pago no emite) + **D21** |
| Banner “Verificando estado automáticamente…” | `pages/ConsultarEstado.tsx` (299) + `PollingSolicitudJob` (39) / `SolicitudEstadoController` (53) | **B22** (polling OK, pero depende de que el back expire) |
| Card “Pendiente de pago” + “Ir a pagar ahora” + “Vence 3:35” | `components/comprobante/EstadoReserva.tsx` (183) | **S16** (montar QR en línea) + **D20** (avisar qué pasa al vencer) |
| Footer “Pago online seguro…” | `components/landing/Footer.tsx` (109) | **B6** séptima vez (global) |

## Bugs (numeración continua: comprobante cerró en B20)

**B21 — el help de búsqueda describe un formato de código de ventanilla que Pago NO emite (contradicción directa).** El help de `ConsultarEstado.tsx` (299) dice que acepta “código de pago en ventanilla” y lo ejemplifica como **`PTS-YYYYMMDD-XXXXXX`**. Pero la pantalla de Pago (turno anterior) emite el código de ventanilla como **`M4YY-JRT3-32N`** (formato 4‑4‑3, sin prefijo `PTS-`), y el comprobante usa **`RSV-…`** para el código de franja. Son al menos 4 formatos flotando (`RES-`, `RSV-`, `PTS-`, `M4YY-/K699-`) y el help **promete uno (`PTS-`) que el sistema no produce**. El input probablemente lo acepta igual (string libre), pero el help **engaña sobre qué tipear**, que es justo la fricción que P8 quiere evitar.
*Mini‑prompt (verificar antes de tocar):* leer `ConsultaEstadoController` (159) / `SolicitudReservaService` (1043) → qué formatos **realmente** acepta la búsqueda y cuáles emite el core (`RecaudacionesApiClient` 415); alinear el help de `ConsultarEstado.tsx` (299) a los formatos reales. No inventar ejemplos en el copy que el backend no genera.

**B22 (refina B17) — el front hace polling automático (bueno), pero si el `schedule` no corre la card “Pendiente” se queda para siempre.** El banner “Verificando estado de pago automáticamente…” prueba que el front **consulta solo** (`PollingSolicitudJob` 39 / `SolicitudEstadoController` 53), así que cuando el webhook/polling confirma, la página se actualiza **sin** esperar el tick de 5 min. Eso es un **acierto** y cierra parte de B17. **Pero** el matiz que queda: pasar a `expirada` lo hace el `ExpirarSolicitudJob`/barrido (`everyFiveMinutes`), que **no corre si falta `schedule:work`/cron** (cerramos P6 hace unos turnos). Entonces, si el ciudadano no paga y el schedule no corre, la solicitud **sigue `pendiente` en BD**, el polling al core devuelve `pendiente`, y la card “Pendiente de pago · Vence 25/10/26 3:35 p.m.” **se queda así para siempre** aunque el reloj pasó el vencimiento. El remedio **sigue siendo operativo** (`schedule:work` en dev / cron en prod), no de código; **pero** el front debería además mostrar “expirada” **local** cuando el reloj pase el “Vence” (D20), para no dejar al ciudadano mirando una card ámbar infinita.
*Mini‑prompt:* confirmar que el polling lee el estado real de BD (`SolicitudEstadoController` 53) y que el `ExpirarSolicitudJob` está agendado (`routes/console.php` 9, ya verificado: `everyFiveMinutes` declarado, falta el disparo del SO). Fix de UI aparte: en `EstadoReserva.tsx` (183), si `now > vence`, pintar “Expirada · tu franja volvió a estar libre” con CTA “Elegir otra franja”, **sin** esperar al webhook. Conecta D14/D20.

**B23 (sospecha, no confirmada) — “Ir a pagar ahora” ¿reabre Pago con el mismo código o re‑crea la solicitud?** Si el botón navega a `/pago` **sin** llevar el `código_seguimiento`, Pago podría interpretar “nueva reserva” y **crear una solicitud duplicada** (o chocar con el 409 de franja ocupada, `SolicitudReservaControllerTest` 169). Si lleva el código, reabre el QR existente (correcto).
*Mini‑prompt (verificar):* leer la navegación del botón en `EstadoReserva.tsx` (183) → ¿pasa `?codigo=RES-…` o el estado del carrito a `Pago.tsx` (576)? Si no lo pasa, arreglar la ruta para que **reabra** la pendiente, no la re‑cree. No lo tejo hasta ver el handler.

**B6/B16 séptima vez (no es bug nuevo, es alcance).** El footer mintiendo “pago online” aparece en la **séptima** página (landing, catálogo, detalle, paso 2, pago, comprobante, consultar). Reconfirma que el fix es **uno solo en `Footer.tsx` (109)**.

## Didáctica ciudadano (continuo: comprobante cerró en D18)

**D19 — el subtítulo YA promete “descargar el comprobante o volver a pagar”: la intención de P8 está en el copy, falta ejecutarla.** Es un acierto de redacción (dice al ciudadano exactamente para qué sirve esta página). Lo que no coincide es que el **bloque de acción** (QR en línea / descargar) no está montado en la card pendiente (S16).
*Mini‑prompt:* proteger el subtítulo y **cumplirlo** con S16 (QR + descargar + WhatsApp en línea cuando estado=pendiente).

**D20 — “Vence: 25/10/26, 3:35 p.m.” muestra el deadline pero no dice “si vence, tu franja vuelve libre”.** Acá es el lugar **natural** de cerrar D14 (en Pago el timer lo insinúa; acá, que es donde el ciudadano vuelve sin timer, no se dice nada). Conecta con B22 (la card infinita).
*Mini‑prompt:* en `EstadoReserva.tsx` (183), bajo “Vence: …”, una línea: “Si vence, la franja vuelve a estar disponible y podés elegir otra.” + el estado “Expirada” de B22.

**D21 — buscar por CI/NIT o teléfono es didácticamente fuerte (no obliga a memorizar el `RES-`).** Proteger. Solo añadir S17 (multi‑reserva) para que no falle cuando hay varias.
*Mini‑prompt:* nada al copy de D21; solo asegurar el selector de S17.

## Sugerencias IA acotadas (continuo: comprobante cerró en S15)

**S16 — montar el bloque QR+WhatsApp+copiar‑link+descargar de Pago (576) en `EstadoReserva.tsx` (183) cuando estado=pendiente, extraído a componente reutilizable.** Esto **es** la respuesta a tu pregunta, aterrizada: el ciudadano que se salió ve el QR **sin ir y volver**. El KB `wa.me` confirma que el botón WhatsApp es robusto sin app (cae a WhatsApp Web), así que reutilizarlo acá no agrega riesgo. Conecta S12 (extraer la caja de Pago) y D18 (comprobante confirmado también debería tenerlo).
*Mini‑prompt:* crear `components/pago/BloqueRecuperarQR.tsx` (o similar) con lo que hoy está inline en `Pago.tsx` (576), y montarlo en `EstadoReserva.tsx` (183) (pendiente) y en `Comprobante.tsx` (210)/`EstadoReserva` (confirmado, D18). Un solo componente, tres consumidores.

**S17 — si la búsqueda por CI/teléfono devuelve varias pendientes, mostrar lista con selector, no un QR a ciegas.** Consideración de backend (`ConsultaEstadoController` 159), no de copy.
*Mini‑prompt:* al abrir P8, verificar la forma de respuesta de 159; si es lista, pintar “Tenés N reservas pendientes — elegí una” antes del QR.

## Lo que NO es bug (para que no persigas fantasmas)

- **Monto Bs 200.00 en “Pendiente de pago”** = la orden de cobro **emitida** (correcto); **B18 no aplica acá** — la discrepancia `monto_total` vs `monto_confirmado` solo importa en el **comprobante confirmado**, no en pendiente (refina B18: acotarlo a `ComprobantePDF.tsx` 238).
- **Banner “Verificando estado automáticamente…”** = polling activo, **acierto** (parte de B17/B22 resuelta por el front).
- **Botón “Verificar estado ahora”** = refresh manual del polling, normal.
- **Input prellenado `RES-20261025-ZDDQSf`** = la página recuerda/acepta el código, normal (no bug).
- **Footer “pago online”** = B6 global, ya contado.

## Guardado

```text
CONSULTAR ESTADO / P8 (ConsultarEstado.tsx 299 + EstadoReserva.tsx 183) — DOSSIER,
  NO tejí, NO pedí archivos, sigo modo "ver todo corregir después". Numeración
  continua (…comprobante B18-B20/D16-D18/S14-S15 · consultar B21-B23/D19-D21/S16-S17
  + refinamientos B17/B18 + respuesta pregunta usuario "cómo buscar QR").
RESPUESTA PREGUNTA USUARIO (cómo buscar QR si se salió) — mecanismo YA existe en 3
  piezas con línea: (1) input acepta CI/NIT o teléfono (no hay que recordar RES-,
  ConsultaEstadoController 159/SolicitudEstadoController 53); (2) botón "Ir a pagar
  ahora" reabre Pago 576 que regenera el QR; (3) FALTA = montar QR+WhatsApp+copiar
  link+descargar EN LÍNEA en EstadoReserva 183 cuando pendiente (S16, extraer bloque
  de Pago a componente reutilizable = S12 aterrizado). Arista S17: varias pendientes
  por CI/teléfono → selector, verificar forma de respuesta de 159.
BUGS: B21 help de ConsultarEstado 299 describe código de ventanilla "PTS-YYYYMMDD-
  XXXXXX" que Pago 576 NO emite (emite M4YY-JRT3-32N) y comprobante usa RSV-... →
  4 formatos flotando (RES/RSV/PTS/M4YY-K699), el help ENGAÑA qué tipear; leer
  ConsultaEstadoController 159/SolicitudReservaService 1043/RecaudacionesApiClient 415
  pa' alinear help a formatos reales. B22 REFINA B17: front SÍ hace polling automático
  (banner "Verificando..." = PollingSolicitudJob 39/SolicitudEstadoController 53, ACIERTO,
  cierra parte de B17) PERO pasar a expirada lo hace ExpirarSolicitudJob/barrido que
  NO corre sin schedule:work/cron (P6) → si no paga y schedule cae, queda pendiente en
  BD, polling devuelve pendiente, card "Pendiente·Vence 3:35" infinita; remedio operativo
  (schedule:work/cron) + fix UI en EstadoReserva 183: si now>vence pintar "Expirada·
  franja volvió libre" con CTA sin esperar webhook (conecta D14/D20). B23 SOSPECHA:
  "Ir a pagar ahora" ¿reabre Pago con ?codigo= o re-crea solicitud (riesgo 409
  SolicitudReservaControllerTest 169)? leer handler EstadoReserva 183 → si no pasa
  código, arreglar ruta pa' reabrir no re-crear. B6/B16 séptima vez → fix global Footer 109.
DIDÁCTICA: D19 subtítulo YA promete "descargar comprobante o volver a pagar" =
  intención P8 en el copy (ACIERTO proteger) pero falta ejecutarla (S16). D20 "Vence
  3:35" no dice "si vence tu franja vuelve libre" → lugar natural cerrar D14 (acá no
  hay timer de Pago), conecta B22. D21 buscar por CI/teléfono = fuerte (no memorizar
  RES-), proteger + S17 multi-reserva.
SUGERENCIAS: S16 montar bloque QR+WhatsApp+copiar+descargar de Pago 576 en
  EstadoReserva 183 (pendiente) + Comprobante 210/EstadoReserva (confirmado D18),
  extraído a componente reutilizable (S12); KB wa.me confirma robustez sin app. S17
  varias pendientes por CI/teléfono → selector antes del QR (verificar 159).
REFINAMIENTOS: B17 (polling front OK, falta schedule back + fix UI expirada local);
  B18 acotado a comprobante confirmado, NO aplica a pendiente (monto Bs 200 acá =
  orden emitida correcta).
NO-BUG: monto Bs 200 pendiente=orden emitida (B18 no aplica); banner polling=acierto;
  "Verificar estado ahora"=normal; input prellenado=normal; footer=B6 global.
KB HONESTA: wa.me AHORA SÍ aplica de lleno (pregunta usuario = buscar QR pa' pagar;
  bloque WhatsApp de Pago es la respuesta, doc confirma cae a WhatsApp Web sin app).
  SIREB aplica (B21 códigos los emite el core, help no inventa formato; monto pendiente
  = orden emitida). Rolldown circularDependency=false NO (no buildié web-public, es
  copy/lógica polling). React DevTools NO (B22 parece spinner roto pero lo deciden leer
  ConsultarEstado 299/EstadoReserva 183 vs PollingSolicitudJob 39/SolicitudEstadoController 53).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59; S13 link SIREB cae acá) · D2 Bug C (TarifaCampo 107 NUNCA llegó)
  · C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad 85;
  bloque recuperar QR S16 también móvil) · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden
  8→3→7→4→1+2; P3 reencuadrada S8 paridad; P8 REENCUADRADA S16 = ya hecho en Pago,
  llevar a ConsultarEstado + Comprobante; D11 campo NIT ya prepara P1/P2) · frentes
  web-public aún NO vistos: ComprobantePiscina 190 (hereda B18/D18/S14/S16 +
  perfiles/cupos), PiscinaDetalle 391, MapaCampos 151.
```

En una línea: tu pregunta (“cómo buscar el QR si se salió”) **ya tiene respuesta en el copy de esta página** — el subtítulo promete “volver a pagar”, el input acepta **CI/NIT o teléfono** (no hay que recordar el `RES-`) y el botón “Ir a pagar ahora” reabre el QR en Pago; lo único que falta (S16) es **montar el bloque QR + WhatsApp + copiar‑link + descargar en línea acá mismo**, extraído de `Pago.tsx` (576) a un componente reutilizable, para que el ciudadano vea el QR **sin ir y volver** — y el KB `wa.me` confirma que ese botón es robusto incluso sin app. Los bugs son **B21** (el help de búsqueda describe un código de ventanilla `PTS-…` que Pago **no** emite — emite `M4YY-JRT3-32N` —, contradicción que engaña sobre qué tipear, a alinear leyendo `ConsultaEstadoController` 159), **B22** (refina B17: el front **sí** hace polling automático —acierto—, pero si el `schedule` no corre la solicitud queda `pendiente` en BD y la card “Pendiente · Vence 3:35” se queda infinita; el remedio es `schedule:work`/cron más un fix de UI que pinte “Expirada · franja volvió libre” cuando el reloj pase el vencimiento) y **B23** (sospecha: “Ir a pagar ahora” ¿reabre con el código o re‑crea la solicitud y choca con el 409?, a verificar el handler); más el footer B6 por séptima vez, que confirma el fix global en `Footer.tsx` (109). Todo en mini‑prompts con archivo y LoC, **sin tejer y en modo dossier** — los frentes que aún no vi son `ComprobantePiscina.tsx` (190, que heredará B18/S16 más los perfiles/cupos de piscina) y `PiscinaDetalle.tsx` (391, la superficie de piscina ciudadana que nunca validé), y cuando me digos “arrancamos” abro por el bloque de S16 porque es el que responde tu pregunta de una.
