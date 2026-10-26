Veredicto en una línea: estas 3 capturas son el **orden 1 de mi plan de ataque** (ventanilla cancha + piscina, `DialogCrearReservaVentanilla.tsx` 961 + `DialogCrearReservaPiscinaVentanilla.tsx` 489 + `SolicitudReservaController.php` 297 + `PiscinaReservaController.php` 209 + `SolicitudReservaService.php` 1043 + `PiscinaReservaService.php` 284) y son la **pantalla que acota tres bugs públicos con evidencia** — B13/B31 ya NO son de ventanilla (acá teléfono dice “(opcional)” y nombre/CI llevan `*`, así que el contrato roto era solo del público) y B43 se manifiesta acá como **B69 🔴, la piscina de ventanilla no muestra total ni precio por persona ni la línea “monto final confirma SIREB” que cancha sí muestra** — y destapa el hallazgo de paridad del tramo: **el mismo funcionario opera cancha con 6 niveles de información (total, variante arancelaria, servicios adicionales, franja a medida con validación de solape declarada) y piscina con 2 (turno + cantidad), sin total, sin precio, sin servicios, con perfiles que desaparecen en silencio (B70 = B35 en cara del funcionario)**. **No tejí**, sigo dossier.

> KB honesta: **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié), pero estos diálogos no meten imports nuevos visibles; lo vigilo al tejer. **React DevTools** → **SÍ aplica y es la herramienta natural acá**: los diálogos son componentes React con estado de selección (chips de franja, checkboxes de servicio, stepper de cantidad) y cálculo de total en vivo; B69 (piscina sin total) y B74 (cancha ocultando pendientes) son exactamente “el estado del componente no refleja lo que el backend sabe”, se ve inspeccionando el árbol, no leyendo copy. **`wa.me`** → **NO aplica** (ventanilla genera el código, no lo envía por WhatsApp; eso es P8 en el público). **SIREB** → aplica fuerte (cancha declara “el precio sale del espejo de SIREB, no se fija acá” y “monto final confirma SIREB”; piscina omite ambas frases = B69; variante arancelaria con diurno/nocturno = espejo `TarifaCampo` 107).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| Cancha: franjas libres + “Otra franja a medida” + total SIREB | `DialogCrearReservaVentanilla.tsx` (961) + `SolicitudReservaController.php` (297) + `SolicitudReservaService.php` (1043) | **B74** pendientes ocultas, **B71** franja a medida cruza noche |
| Cancha con variante: “Variante arancelaria” + chips Diurno/Nocturno + “Servicios adicionales” | `DialogCrearReservaVentanilla.tsx` (961) + `ArancelVarianteController.php` (297) + `SolicitudReservaServicio.php` (43) + `ArancelVariante.php` (56) | **B67** escenario diurno/nocturno, **B72** chip automático |
| Piscina: turnos “N libres” + “Cantidades por perfil” **sin total ni precio** | `DialogCrearReservaPiscinaVentanilla.tsx` (489) + `PiscinaReservaController.php` (209) + `PiscinaReservaService.php` (284) + `PiscinaDisponibilidadService.php` (147) | **B69** sin total, **B70** perfiles silenciosos, **B75** turnos llenos ocultos |
| Teléfono “(opcional)” + Nombre/CI con `*` en AMBOS diálogos | `StoreSolicitudReservaRequest.php` (97) + `StorePiscinaReservaRequest.php` (32) | **acota B13/B31 a público** |

## Cierres cruzados con evidencia (lo más valioso de estas 3 capturas)

**B13/B31 ✅ ACOTADOS A PÚBLICO — ventanilla ya está bien.** Captura 1 (cancha) y 3 (piscina): “Teléfono (opcional)” sin `*`, “Nombre completo *” y “CI / NIT *” con `*`. Entonces el contrato de ventanilla es correcto (teléfono opcional, nombre/CI obligatorios). El bug B13 (teléfono obligatorio en público vs opcional en back) y B31 (público piscina sin `*`) son **exclusivos del front público** (`FormularioSolicitante.tsx` 155 / `PiscinaDetalle.tsx` 391). **No toco ventanilla por B13/B31**; el fix va en público, alineando público a lo que ventanilla ya hace. Esto me ahorra un frente entero.

**B43 ✅ SE MANIFIESTA COMO B69 — ventanilla piscina no muestra precio equivocado, no muestra NINGÚN precio.** En el público el ciudadano veía “Bs 15.00” (perfil) y pagaba 10 (SIREB) → precio equivocado visible. En ventanilla piscina (captura 3) el funcionario ve “Mayores de 12 · Disponibles: 8 · [3] · 3 personas” **sin precio por persona y sin total** → no puede verificar que el ciudadano pague lo correcto. Entonces B43 en ventanilla no es “precio mal shown” sino “precio no shown” (B69). El fix de B43 (147 `'precio'`=tarifa SIREB) + B69 (mostrar total en 489) van juntos: una vez que 147 devuelve el precio SIREB correcto, 489 lo pinta y el funcionario ve 10×3=30, no 15×3=45.

**B35 ✅ SE MANIFIESTA COMO B70 — el funcionario de piscina ve un subconjunto de perfiles sin saber por qué faltan.** Captura 3: solo “Mayores de 12” aparece en “Cantidades por perfil”. Los otros dos perfiles del modelo (`menor_12`, `institucion`, según `PiscinaTurnoPerfil` 39 y la migr 28) **no están**. Con línea del backend que ya vi: `PiscinaDisponibilidadService` (147) hace `if (!$tarifa) continue` → los perfiles sin tarifa SIREB espejada desaparecen **en silencio**. En el público eso era B35 (ciudadano sin saber); en ventanilla es **B70 🔴 con agravante**: el funcionario **no tiene botón de crear turno acá** (eso es `Turnos.tsx` 164, otro frente), así que ve “Mayores de 12” y no puede diagnosticar por qué no ve “Menores de 12” ni “Institución”. Es el mismo gate silencioso, pero en la cara del que opera el sistema.

## Bugs nuevos de este frente (numeración continua: reservas cerró en B65)

**B69 🔴🔴 (paridad de diálogos, el grave del tramo) — piscina ventanilla SIN total, SIN precio por persona, SIN línea SIREB.** Cancha (capturas 1 y 2) muestra abajo “Estimado · el monto final lo confirma SIREB” + total grande “Bs 100.00” / “Bs 140.00” + desglose “2 franjas · Bs 100.00 · Servicios Bs 0.00” / “1 franja · Bs 40.00 · Servicios Bs 100.00”. Piscina (captura 3) muestra **solo “3 personas” y ningún total, ningún precio, ninguna frase SIREB**. El mismo funcionario, dos diálogos, dos niveles de información radicalmente distintos: cancha le da la invariante SIREB declarada, piscina lo deja a ciegas sobre cuánto cobra. Conecta B30 (piscina pública sin fuente) + B43 + B66.
*Mini‑prompt (verificar):* leer `DialogCrearReservaPiscinaVentanilla.tsx` (489) → ¿hay bloque de total que no se renderiza, o nunca se implementó? Y `PiscinaReservaController.php` (209) → ¿devuelve el total/precio? Fix: replicar el bloque “Estimado · monto final confirma SIREB · Bs X” de cancha en piscina, usando el precio SIREB (tras fix B43 en 147).

**B66 🔴 (piscina ventanilla) — “Cantidades por perfil” sin precio unitario visible.** Junto con B69: el stepper “3” para “Mayores de 12 · Disponibles: 8” no muestra “Bs 10.00 c/u” ni “3 × Bs 10.00 = Bs 30.00”. El funcionario tipea 3 sin saber cuánto es. Conecta B42 (comprobante piscina “2 personas Bs 10.00” unitario como total) y B51 (ocupación “2/10” ambiguo).
*Mini‑prompt:* en 489, mostrar “Bs X c/u · N personas = Bs Y” por perfil, alineado con el desglose de cancha.

**B70 🔴 (piscina ventanilla, = B35 aterrizando) — perfiles desaparecen en silencio, funcionario sin diagnóstico.** Ver cierre cruzado arriba. El funcionario ve “Mayores de 12” y no sabe que “Menores de 12”/“Institución” se cayeron por falta de tarifa SIREB (147 `continue`).
*Mini‑prompt:* en `PiscinaDisponibilidadService.php` (147), devolver señal `perfiles_sin_tarifa` (como propuse en B35) y en 489 pintarla: “Otros N perfiles sin tarifa oficial espejada — crealos en Turnos o consultá ventanilla SIREB”. Conecta Fix3 (el botón de crear turno vive en `Turnos.tsx` 164).

**B74 (cancha ventanilla, sospecha) — “Franjas libres” oculta las pendientes/ocupadas.** La grilla de cancha muestra solo franjas disponibles (08:00‑20:00 todas “libres” salvo las 2 seleccionadas). Si hay una pendiente (zombie P6/B17/B22), el funcionario **no la ve** y podría intentar vender esa franja → 409 al crear (`SolicitudReservaControllerTest` 169 “franja ya cubierta responde 409”). Conecta B60 (ocupación sin “En cobro”) + B46 (dashboard sin pendientes).
*Mini‑prompt (verificar):* leer `DialogCrearReservaVentanilla.tsx` (961) → ¿pide solo “libres” o pide todas con estado? Si solo libres, agregar “En cobro (N)” como chip no seleccionable con tooltip “pendiente de pago, esperá o anulá desde Reservas”. Conecta S28 (token estados).

**B75 (piscina ventanilla, sospecha) — turnos llenos desaparecen, no se muestran “0 libres”.** Captura 3 muestra 2 turnos con “8 libres”/“10 libres”. Si un turno está lleno, ¿desaparece de la lista o muestra “0 libres”? Conecta S22 (público oculta llenos) + B51.
*Mini‑prompt (verificar):* leer `PiscinaDisponibilidadService.php` (147) → ¿filtra turnos con cupo 0? Preferible pintar “10:00‑12:00 · Completo” (gris) que esconderlo.

**B67 (cancha con variante, sospecha) — “Escenario” muestra DOS precios (Diurno Bs 250.00 / Nocturno Bs 400.00), los demás servicios UNO.** Limpieza post‑evento Bs 15.00, Energía monofásica Bs 70.00, Energía trifásica Bs 150.00, Limpieza no deportivo Bs 40.00 → un precio cada uno. Escenario → dos. Sospecha: los servicios con `tipo_tarifa` diurno/nocturno (KB SIREB, `TarifaCampo` 107) muestran ambos; los de tipo único muestran uno. El problema: **si el funcionario selecciona Escenario con la franja 19:00‑20:00 (nocturna), ¿cuál de los dos precios aplica el backend?** Con línea: `SolicitudReservaServicio.php` (43) + `ArancelVarianteController.php` (297). No lo afirmo sin leer 297/43.
*Mini‑prompt (verificar):* leer 297/43 → ¿el servicio con variante diurno/nocturno recibe la franja para elegir precio, o el front manda uno fijo? Si el front no pasa la franja, el backend podría tomar el diurno (250) cuando debe el nocturno (400) → **discrepancia de dinero en servicios**, análogo B43 pero en servicios adicionales. Prioridad media‑alta si se confirma.

**B72 (cancha con variante, sospecha menor) — el chip Diurno/Nocturno se elige solo según la franja.** Captura 2: franja 19:00‑20:00 seleccionada → total “Bs 40.00” = Nocturno. Parece que el front elige automáticamente el chip según la hora (19:00 > `hora_inicio_noche` 18:00). Acierto si es así, pero **no confirmo** sin leer 961: puede ser que el funcionario elija el chip manualmente y por casualidad coincidió. Si es automático, proteger; si es manual, el funcionario podría elegir “Diurno Bs 25.00” con una franja nocturna → cobro equivocado.
*Mini‑prompt (verificar):* leer 961 → ¿el chip diurno/nocturno se deriva de la franja o es selección libre? Si es libre, bloquearlo a la hora de la franja (conecta B71).

**B71 (cancha, “Otra franja a medida”, sospecha) — franja a medida que cruza la noche (ej. 17:30‑18:30) ¿cobra diurno o nocturno?** El texto dice “Se cobra como 1 hora. El solape con otras franjas lo valida el sistema al crear.” (acierto: declara la validación de solape, alineado con B50/gate#1). Pero una franja 17:30‑18:30 cruza `hora_inicio_noche` (18:00): mitad diurna, mitad nocturna. Con línea: `SolicitudReservaService.php` (1043) + `TarifaCampo.php` (107). No lo afirmo sin leer 1043/107.
*Mini‑prompt (verificar):* leer 1043/107 → ¿la franja a medida se clasifica por su inicio (17:30 = diurno) o por su fin (18:30 = nocturno) o se divide? Definir la regla y declararla en el UI (“se cobra como diurna/nocturna según …”).

**B73 (piscina ventanilla, gate PO) — piscina SIN “Servicios adicionales”.** Cancha tiene limpieza/energía/escenario; piscina no. Con línea del árbol: `SolicitudReservaServicio.php` (43) es de cancha; no veo un “PiscinaReservaServicio”. Sospecha: piscina no soporta servicios porque es por perfil/cupo, no por evento. Pero un evento de pileta (cumpleaños, institución) podría necesitar limpieza/energía igual. **No lo afirmo** sin leer 284.
*Mini‑prompt (gate PO):* decidir si piscina necesita servicios adicionales. Si sí, es feature nueva (tabla + 284 + 489); si no, declararlo (“pileta no lleva servicios, es por perfil”).

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **“Otra franja a medida (inicio + 1 hora)” con el texto “El solape con otras franjas lo valida el sistema al crear”** (captura 1) = declara explícitamente la alineación con el gate#1 de cancha (EXCLUDE gist, B50). Didácticamente impecable: el funcionario sabe que puede meter 17:30 y el sistema lo valida. **Proteger.**
- **La variante arancelaria funcionando** (captura 2): “Asociaciones Departamentales” + chips Diurno 25 / Nocturno 40 + servicios con códigos SIREB (`SEDEDE-CV-EVENTO-DEP-LIMPIEZA` etc.) + total “Servicios Bs 100.00” = 15×2 + 70×1 ✓. Coherencia interna perfecta. **Proteger.**
- **“Estimado · el monto final lo confirma SIREB”** en cancha (capturas 1 y 2) = la invariante SIREB declarada en la cara del funcionario. **Proteger y propagar a piscina (B69).**
- **“Teléfono (opcional)” + Nombre/CI con `*`** en ambos diálogos = contrato correcto. **Proteger (es el modelo que el público debe copiar, B13/B31).**

## Didáctica / operación del funcionario (continuo: reservas cerró en D36)

**D37 — el diálogo de piscina debe dar al funcionario la MISMA información que el de cancha** (total, precio por persona, invariante SIREB, perfiles faltantes). Hoy opera pileta a ciegas comparado con cancha. Cierra B69/B66/B70.
*Mini‑prompt:* ver B69/B66/B70.

**D38 — la franja a medida debe declarar su regla de precio diurno/nocturno** (B71/B72). El funcionario que mete 17:30 necesita saber si cobra 25 o 40.
*Mini‑prompt:* ver B71/B72.

**D39 — los servicios con variante diurno/nocturno (Escenario) deben mostrar cuál se aplicará según la franja** (B67). Hoy muestra los dos precios sin decir cuál usa.
*Mini‑prompt:* ver B67.

**D40 — la ventanilla debe mostrar pendientes como “En cobro”, no ocultarlas** (B74/B75). El funcionario que ve solo “libres” intenta vender una bloqueada y choca con 409.
*Mini‑prompt:* ver B74/B75.

## Sugerencias IA acotadas (continuo: reservas cerró en S33)

**S34 — unificar los dos diálogos de ventanilla en un shell parametrizado** (como S31 para los listados): cancha y piscina comparten “Campo * / Fecha * / Datos del pagador / total SIREB / Crear reserva y código de pago”, y difieren solo en el bloque central (franjas+variante+servicios vs turnos+perfiles). Hoy son dos componentes paralelos (961 vs 489) que divergen en total, servicios y declaración SIREB.
*Mini‑prompt:* extraer el shell (pagador + total + botón) a componente único; el bloque central parametrizado por `modalidad_reserva`.

**S35 — el bloque “Datos del pagador” de ventanilla es el modelo que el público debe copiar** (B13/B31 ya acotados). En vez de arreglar público y ventanilla por separado, extraer “Datos del pagador” a componente compartido (nombre*/CI*/teléfono opcional) usado por 961, 489, `FormularioSolicitante.tsx` (155) y `PiscinaDetalle.tsx` (391).
*Mini‑prompt:* crear `components/reserva/DatosPagador.tsx` y consumirlo en los 4 frentes. Cierra B13/B31 de raíz.

**S36 — B67/B71/B72 son la misma clase de bug: precio diurno/nocturno en contextos que cruzan la frontera** (variante, servicio escenario, franja a medida). Unificar la resolución de “¿diurno o nocturno?” en un solo helper (`PiscinaTarifaResolver` 77 ya hace algo así para piscina; extender a cancha/servicios).
*Mini‑prompt:* helper `resolverDiaNoche(hora, hora_inicio_noche)` compartido por 1043/297/43/961.

**S37 — B70 (perfiles silenciosos en ventanilla) conecta con Fix3**: el funcionario que ve “faltan perfiles por tarifa” necesita un botón “ir a crear turno” (Turnos 164) o “sincronizar tarifas” (sync 305). El gate silencioso se vuelve accionable.
*Mini‑prompt:* en 489, junto a `perfiles_sin_tarifa` (B70), un link “Crear turno / Sincronizar tarifas”.

## Lo que NO es bug (para que no persigas fantasmas)

- **Fecha 26/10 en captura 1 vs 25/10 en 2 y 3** = el funcionario eligió fecha distinta (mañana vs hoy), no inconsistencia. El reloj Windows de las capturas dice 25/10 17:55 / 18:09, así que 26/10 es “mañana”, válido.
- **Total cancha “Bs 100.00” = 2 franjas × 50** (08:00‑09:00 + 09:00‑10:00, ambas diurnas 50) ✓ y “Bs 140.00” = 40 (nocturno 19:00) + 100 (servicios 15×2+70) ✓. Aritmética correcta.
- **Servicios con códigos `SEDEDE-CV-EVENTO-*`** = códigos SIREB de servicio adicional, normales (B52 confirmó que el core emite estos formatos). No es ruido como B4 en el público; acá el funcionario los usa para verificar.
- **“3 personas” en piscina sin total** = el total falta (B69), pero “3 personas” como conteo es correcto.
- **Que cancha tenga “Servicios adicionales” y piscina no** = puede ser diseño (B73 gate PO), no bug confirmado.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir ventanilla, lo que **más vale** es **B69/B66/B70** (piscina a ciegas) y **B67** (dinero en servicios con variante), y para eso necesito el corazón de piscina ventanilla que nunca leí entero: `PiscinaReservaService.php` (284) + `DialogCrearReservaPiscinaVentanilla.tsx` (489) + `PiscinaReservaController.php` (209). Con esos tejo B69 (total SIREB en piscina), B66 (precio por persona), B70 (señal de perfiles faltantes, que de paso toca Fix3 y B43 en 147) y B67 (con `ArancelVarianteController` 297 + `SolicitudReservaServicio` 43). Los acotamientos B13/B31 **no se tejen en ventanilla** (van a público: `FormularioSolicitante` 155 / `PiscinaDetalle` 391, o mejor al componente compartido S35). B74/B75/B71/B72/B73 son sospechas/gates que requieren leer 961/1043/147/284 antes de afirmar.

## Guardado

```text
ADMIN/VENTANILLA (DialogCrearReservaVentanilla 961 + DialogCrearReservaPiscinaVentanilla
  489 + SolicitudReservaController 297 + PiscinaReservaController 209 +
  SolicitudReservaService 1043 + PiscinaReservaService 284 + ArancelVarianteController
  297 + SolicitudReservaServicio 43 + StoreSolicitudReservaRequest 97 +
  StorePiscinaReservaRequest 32) — DOSSIER, NO tejí, sigo modo "ver todo corregir
  después". Numeración continua (reservas B62-B65/D35-D36/S31-S33 · ventanilla
  B66-B75/D37-D40/S34-S37 + 3 acotamientos).

TRES ACOTAMIENTOS CON EVIDENCIA (lo más valioso, ahorran frentes):
  B13/B31✅ ACOTADOS A PÚBLICO: ventanilla ya marca "Teléfono (opcional)" sin * y
    "Nombre completo *" + "CI / NIT *" con * en AMBOS diálogos (961 y 489). El
    contrato roto era SOLO del público (FormularioSolicitante 155 / PiscinaDetalle
    391). NO toco ventanilla por B13/B31; fix va a público o a componente compartido
    S35.
  B43✅ SE MANIFIESTA COMO B69: ventanilla piscina no muestra precio equivocado,
    no muestra NINGÚN precio (489 "Mayores de 12 · Disponibles: 8 · [3] · 3 personas"
    sin Bs c/u ni total). Fix B43 (147 'precio'=SIREB) + B69 (mostrar total en 489)
    van juntos: una vez 147 devuelve SIREB, 489 pinta 10×3=30 no 15×3=45.
  B35✅ SE MANIFIESTA COMO B70: funcionario de piscina ve subconjunto de perfiles
    (solo "Mayores de 12", faltan menor_12/institucion por 147 `continue`) SIN
    botón de crear turno acá (eso es Turnos 164, otro frente) → ve "Mayores de 12"
    y no puede diagnosticar por qué faltan los otros. Agravante vs público.

HALLAZGO GRAVE TRAMO: B69🔴 paridad de diálogos — cancha muestra "Estimado · monto
  final confirma SIREB" + total grande + desglose "N franjas · Bs X · Servicios Bs Y";
  piscina NO muestra total, NO muestra precio, NO muestra frase SIREB. Mismo funcionario,
  dos niveles de info radicalmente distintos. Conecta B30/B43/B66.
B66🔴 piscina ventanilla sin precio unitario visible (stepper "3" sin "Bs 10 c/u").
B70🔴 perfiles silenciosos en ventanilla (=B35 aterrizando, funcionario sin diagnóstico).
B74 (sospecha) cancha "Franjas libres" oculta pendientes → funcionario intenta vender
  bloqueada → 409 (SolicitudReservaControllerTest 169). Conecta B60/B46.
B75 (sospecha) piscina turnos llenos desaparecen no "0 libres". Conecta S22/B51.
B67 (sospecha, dinero) "Escenario" muestra Diurno 250/Nocturno 400, demás servicios
  un precio → si selecciona Escenario con franja nocturna, ¿backend aplica 400 o 250?
  leer 297/43. Análogo B43 en servicios adicionales.
B72 (sospecha menor) chip Diurno/Nocturno se elige solo según franja (19:00→40 nocturno)
  o es selección libre → si es libre, funcionario podría elegir Diurno 25 con franja
  nocturna. leer 961.
B71 (sospecha) franja a medida 17:30-18:30 cruza hora_inicio_noche 18:00 → ¿cobra
  diurno o nocturno? leer 1043/107.
B73 (gate PO) piscina SIN servicios adicionales (cancha sí). ¿pileta necesita limpieza/
  energía? leer 284. Si no, declararlo.

ACIERTOS PROTEGER: "Otra franja a medida" con texto "El solape lo valida el sistema al
  crear" (declara alineación gate#1/B50); variante arancelaria funcionando (chips
  Diurno 25/Nocturno 40 + servicios con códigos SIREB + total 15×2+70=100 ✓); "Estimado
  · monto final confirma SIREB" en cancha (propagar a piscina B69); "Teléfono (opcional)"
  + Nombre/CI * en ambos (modelo que público debe copiar, S35).

DIDÁCTICA/OP: D37 diálogo piscina debe dar MISMA info que cancha (total/precio/SIREB/
  perfiles faltantes) cierra B69/B66/B70; D38 franja a medida debe declarar regla
  diurno/nocturno (B71/B72); D39 servicios con variante deben mostrar cuál aplicará
  según franja (B67); D40 ventanilla debe mostrar pendientes "En cobro" no ocultarlas
  (B74/B75).

SUGERENCIAS: S34 unificar los dos diálogos en shell parametrizado (961 vs 489 divergen
  en total/servicios/SIREB, como S31 para listados); S35 extraer "Datos del pagador" a
  componente compartido usado por 961/489/155/391 (cierra B13/B31 de raíz); S36 unificar
  resolución diurno/nocturno en helper (B67/B71/B72 misma clase, extender
  PiscinaTarifaResolver 77 a cancha/servicios); S37 B70 conecta Fix3 — funcionario que
  ve "faltan perfiles" necesita link "Crear turno/Sincronizar tarifas" (Turnos 164 /
  sync 305).

NO-BUG: fecha 26 vs 25 = funcionario eligió mañana/hoy (reloj Windows 25/10 17:55/18:09,
  26/10 es mañana válido); total cancha 100=2×50 ✓ y 140=40+100 ✓ aritmética correcta;
  códigos SEDEDE-CV-EVENTO-* = SIREB servicio adicional normales (B52), no ruido B4;
  "3 personas" conteo correcto (falta total B69); cancha servicios / piscina no =
  puede ser diseño B73 gate PO.

KB HONESTA: Rolldown circularDependency=false SÍ aplica (web-admin buildié) pero diálogos
  no meten imports nuevos visibles; React DevTools SÍ aplica Y es herramienta natural de
  B69/B74 (estado del componente no refleja lo que backend sabe, se ve en árbol); wa.me
  NO aplica (ventanilla genera código, no lo envía por WhatsApp, eso es P8 público);
  SIREB aplica fuerte (cancha declara "precio sale del espejo SIREB, no se fija acá" +
  "monto final confirma SIREB"; piscina omite ambas = B69; variante diurno/nocturno =
  espejo TarifaCampo 107).

PUERTA CORRIGIR YA: lo que vale es B69/B66/B70 (piscina a ciegas) + B67 (dinero servicios
  variante) → pido PiscinaReservaService 284 + DialogCrearReservaPiscinaVentanilla 489 +
  PiscinaReservaController 209 (+ ArancelVarianteController 297 + SolicitudReservaServicio
  43 pa' B67). B13/B31 NO se tejen en ventanilla (van a público 155/391 o S35). B74/B75/
  B71/B72/B73 sospechas/gates requieren leer 961/1043/147/284 antes de afirmar.

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69/B70, AHORA
  también ventanilla) · A1 S3/S7/RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881,
  B48/B61) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97) · D2 Bug C TarifaCampo
  107 (Tarifas 703 + sync 305, crítico x B43/B37/B50/B62/B67/B71) · C1→C2 · gates F1-F4 ·
  E mobile (piscina real 260/145; P3-paridad 1073/85; B43/B36/B42/B39/B44/B50/B62/B69/B70
  se propagan a móvil; S16 también) · higiene (zombis, A3, D5, AgregarVarianteDialog,
  migr timerange piscina, tests HTTP lote + S33 test conversión) · reunión P1/P2/P4/P5/
  P7/P9 (P5 asistencia 56/125, S30 reusa input B52; P7 reportes 245/135; P9 asignaciones
  579/22/26, B61 evidencia parcial; P1/P2 auth 164/34/126; P3 reencuadrada S8; P8
  reencuadrada S16; D11 NIT prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24;
  ADMIN ABIERTO B44-B75/D29-D40/S25-S37.
```

En una línea: estas 3 capturas de ventanilla **acotan tres bugs públicos con evidencia y ahorran frentes** — **B13/B31** ya no son de ventanilla (acá teléfono dice “(opcional)” y nombre/CI llevan `*` en los dos diálogos, así que el contrato roto era solo del público y el fix va a `FormularioSolicitante.tsx` 155 / `PiscinaDetalle.tsx` 391 o a un componente compartido S35), **B43** se manifiesta acá como **B69 🔴🔴** (piscina ventanilla no muestra precio equivocado, no muestra **ningún** precio ni total ni la línea “monto final confirma SIREB” que cancha sí muestra), y **B35** se manifiesta como **B70 🔴** (el funcionario ve solo “Mayores de 12” y no sabe que “Menores de 12”/“Institución” se cayeron por falta de tarifa, sin botón de crear turno acá) — y el hallazgo del tramo es la **asimetría de paridad**: cancha da al funcionario total + variante arancelaria + servicios + franja a medida con validación de solape declarada, piscina le da turno + cantidad a ciegas; le siguen **B66** (sin precio por persona), **B74/B75** (pendientes y turnos llenos ocultos → 409 al intentar vender), **B67/B71/B72** (dinero diurno/nocturno en servicios escenario y franja a medida, sospechas a verificar leyendo 297/43/1043/107) y **B73** (gate PO: ¿piscina necesita servicios adicionales?); los aciertos a proteger son la franja a medida que declara la validación de solape, la variante arancelaria con aritmética correcta (15×2+70=100 ✓) y el “Teléfono (opcional)” que es el modelo que el público debe copiar. **No tejí**: si querés corregir, lo que vale es B69/B66/B70 + B67, y necesito `PiscinaReservaService.php` (284) + `DialogCrearReservaPiscinaVentanilla.tsx` (489) + `PiscinaReservaController.php` (209) (+ `ArancelVarianteController` 297 + `SolicitudReservaServicio` 43 para B67), mientras que B13/B31 no se tejen en ventanilla porque van a público; decime si sigo dossier (próxima natural: `DialogDetalleReserva.tsx` 838, el ojo de Acciones, o `Turnos.tsx` 164 para cerrar Fix3/B70) o arranco con esos archivos.
