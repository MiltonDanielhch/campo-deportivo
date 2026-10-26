Veredicto en una línea: esta es `campo_detalle_screen.dart` (1073) en la rama **cancha** (cancha nueva trinidad, Mañana 26/10), y es la captura que **resuelve B144 en el móvil y ubica la fuente de B67/B71/B72**: el listado comprimía el precio en “Bs 50–150” sin leyenda (B144), pero el detalle lo **desglosa en dos cards con el corte explícito** — “Regular · Bs 50.00/h · **Antes 18:00**” (sol) y “Con luces · Bs 150.00/h · **Desde 18:00**” (bombita) —, o sea el móvil le muestra al ciudadano la regla diurno/nocturno (`hora_inicio_noche`, migr 21 / `Tarifas.tsx` 703) que **el público no tiene y la web solo tiene en admin**; y **re‑confirma G2** (cancha = grilla horaria de franjas, coherente con el listado que distinguía pileta). La **autocorrección** de mi B144 del turno pasado: no era que el móvil no supiera la dimensión, es que el **listado la comprime y el detalle la expande** (buen diseño). Las dos sospechas graves quedan **abiertas honestamente**: **B147 🔴** (¿la grilla pinta las franjas **ocupadas**? — **no confirmable acá** porque la captura está en 26/10 y en Reservas admin **no hay** reservas de esta cancha el 26/10, así que “todo libre” es correcto; necesito la captura en **Hoy 25/10**, donde sí hay `RES-…UK67YC` confirmada y “17:00 Ocupada” en Ocupación) y **B152** (¿el detalle **perdió el footer de conciliación SIREB** que el listado tenía? — se corta en 15:00‑16:00, verificar scroll). Le siguen **B148** (wording de capa “Con luces” móvil vs “Con iluminación/Nocturno” web 703, menor), **B149** (selector “Hoy/Mañana” relativo = G4/S55 re‑confirmado, pero “Más fechas” a verificar) y **B150** (sospecha: ¿la rama **pileta** de 1073 ramifica a perfiles o le pinta grilla horaria igual = B50 en móvil?). **No tejí**, sigo dossier.

> KB honesta de esta captura, con **autocorrección** y **límite de dispositivo**: **AUTOCORRECCIÓN de B144**: el turno pasado marqué “rango sin leyenda” como bug del móvil; esta captura prueba que el **detalle sí desglosa** Regular/Con luces + corte 18:00, así que B144 era del **listado** (compresión legítima) y el detalle lo resuelve — retiro B144 como bug del móvil y lo dejo como **acierto de diseño** (listado comprime, detalle expande). **`wa.me`** → **NO aplica** (detalle de cancha, no hay QR/WhatsApp; el doc del fallback “use WhatsApp Web” pesa en `pago_qr_screen` 343, próxima). **SIREB** → aplica (las dos cards de tarifa son el espejo `TarifaCampo` 107 con corte 18:00 = `hora_inicio_noche` migr 21; el footer de conciliación que el listado tenía y acá podría faltar = G1/S69/B145). **React DevTools → NO aplica** (Flutter; la análoga es **Widget Inspector / Flutter DevTools**, y “qué estado alimenta el ícono sol/bombita de cada franja” se ve ahí). **Rolldown `checks.circularDependency` default `false` → NO aplica** (no Vite/Rolldown; análogo `flutter analyze` sobre `analysis_options.yaml` 3). **LÍMITE DE DISPOSITIVO**: sigue `localhost:1148` = **web/desktop de Flutter, no dispositivo real**, así que **todavía no juzgo** touch targets de las franjas (¿son tocables con dedo o solo hover?), safe areas, ni scroll táctil del selector de fechas; eso requiere emulador/dispositivo — lo declaro para no fingir móvil‑real.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| 2 cards “Regular/Con luces” + corte 18:00 | `campo_detalle_screen.dart` (1073) + `TarifaCampo.php` (107) + migr `add_hora_inicio_noche` (21) | **B144 resuelto** + **fuente B67/B71/B72** (S75) |
| Grilla de franjas 08‑16 todas sol/Bs 50 | 1073 + `models/bloque_disponibilidad.dart` (85) + `services/disponibilidad_service.dart` (35) | **B147** 🔴 sospecha ocupadas (verificar 25/10) |
| Ícono sol/bombita por franja (coherencia visual) | 1073 + 85 | **acierto** (proteger, prueba regla diurno/nocturno por franja) |
| Selector “Hoy/Mañana/Mar…/Más fechas” | 1073 + `SelectorFechas.tsx` (121) análogo público | **G4/S55 re‑confirmado** + **B149** (Más fechas) |
| Footer conciliación SIREB (¿presente en scroll?) | 1073 + `campos_service.dart` (74) | **B152** sospecha (¿perdió el footer del listado?) |
| Rama pileta del mismo 1073 (no en esta captura) | 1073 + `models/piscina_disponibilidad.dart` (87) | **B150** sospecha (¿ramifica perfiles o grilla horaria = B50?) |
| Wording “Con luces” vs “Con iluminación/Nocturno” | 1073 + `Tarifas.tsx` (703) | **B148** (wording de capa, menor) |

## Cierres / refinamientos cruzados con evidencia

**B144 ✅ RESUELTO EN EL MÓVIL (autocorrección) — el detalle expande lo que el listado comprime.** Listado: “Bs 50–150” sin leyenda (yo lo marqué B144). Detalle (esta captura): **“Regular Bs 50/h Antes 18:00” + “Con luces Bs 150/h Desde 18:00”**. O sea el móvil **sí** tiene la leyenda diurno/nocturno; el listado la comprimía por espacio (decisión de diseño legítima en pantalla chica), el detalle la despliega. **Retiro B144 como bug del móvil** y lo convierto en **acierto de progresividad** (listado→detalle). Conecta S72 (móvil plantillo): el público (`CampoDetalle.tsx` 560) **no** tiene este desglose con corte 18:00 visible al ciudadano — el móvil sí.

**B67/B71/B72 ✅ FUENTE UBICADA en el móvil — el corte 18:00 que el ciudadano ve es `hora_inicio_noche`.** Las dos cards dicen “Antes 18:00” / “Desde 18:00”, que es exactamente `hora_inicio_noche` (migr 21) / el corte de `Tarifas.tsx` (703). En la web esto era **sospecha de dinero** (B67: escenario diurno 250/nocturno 400, ¿cuál aplica el backend?; B71: franja a medida 17:30‑18:30 cruza 18:00, ¿cobra diurno o nocturno?; B72: chip diurno/nocturno automático o libre). El móvil **resuelve la pregunta en la UI del ciudadano**: cada franja lleva el ícono sol (<18:00, Bs 50) o bombita (≥18:00, Bs 150), así que la regla es **por `hora_inicio` de la franja contra el corte 18:00**. Eso me da el helper que la web debe consumir (S75 = S36/S44/S53 aterrizando con evidencia móvil).
*Mini‑prompt (verificar):* leer 1073 + 85 → ¿el ícono/precio de cada franja se deriva de `hora_inicio >= hora_inicio_noche`? Si sí, ese es el `resolverDiaNoche` canónico; propagarlo a 961/489/43/297 (B67/B71/B72) y a 147/391 (B43). Conecta S75.

**G2 ✅ RE‑CONFIRMADO en el detalle — cancha = grilla horaria.** La rama cancha de 1073 pinta **frajas horarias** (08‑09, 09‑10…), coherente con el listado que distinguía pileta (“Tarifa por perfil”). El móvil mantiene el router de modalidad en las dos pantallas. Refuerza S72 (móvil plantillo pa’ la web, donde G2 está roto en 6 frentes B50/B87/B106/B115/B132/B135).

**G4/S55 ✅ RE‑CONFIRMADO (2ª capa) — el selector móvil usa etiquetas relativas.** “Hoy 25/10” / “Mañana 26/10” / “Mar 27/10”… = el formatter relativo (“hoy/mañana”) que G4/S55 pedía propagar, **ya existe en el móvil** (igual que admin 881 “hace 11 d”). O sea el helper relativo está en **2 capas** (admin + móvil), falta solo en público 190 y admin 164/655. Refuerza S58 (propagar, no crear) y **autocorrige** mi B145 del turno pasado: el móvil muestra fechas **locales correctas** (Hoy 25/10 coherente con el reloj Windows 25/10 de capturas previas), así que la sospecha de timezone `0:15` (B145) es del **footer de sync**, no del selector de fechas.

## Bugs nuevos de este frente (numeración continua: móvil‑listado cerró en B146)

**B147 🔴 (sospecha ALTA, gate de sobreventa, NO confirmable acá) — ¿la grilla pinta las franjas ocupadas/pendientes, o las muestra disponibles igual?** Las 8 franjas visibles (08‑16) están **todas amarillas disponibles con “Bs 50.00”**. **Honestidad dura:** la captura está en **Mañana 26/10**, y cruzando con Reservas admin, **NO hay ninguna reserva de cancha nueva trinidad el 26/10** (las fechas son 25,24,23,22,21,20,19), así que “todo libre mañana” es **correcto** — **no puedo afirmar B147 con esta captura**. La sospecha vive en **¿qué pasa en Hoy 25/10?**, donde sí hay `RES-…UK67YC` confirmada (25/10 4:14) y “cancha nueva trinidad 17:00 Ocupada RSV‑…” en Ocupación (`MisCampos.tsx` 543). Si al cambiar a 25/10 la grilla **sigue** mostrando 17:00 como disponible (amarilla, Bs 50), entonces el ciudadano intenta reservar una franja ocupada → choca con **409** (`SolicitudReservaControllerTest` 169 “franja ya cubierta responde 409”) o, peor, el gate de no‑solape (`add_no_solape_horario` 28) no corre en móvil. Conecta **B50** (ocupación sin “En cobro”), **B60**, **B22/B17** (zombie), **B51**.
*Mini‑prompt (verificar, requiere captura en 25/10):* leer 1073 + `bloque_disponibilidad.dart` (85) + `disponibilidad_service.dart` (35) → ¿el modelo de franja trae estado (libre/ocupada/pendiente) o solo horario+precio? Si solo horario+precio, la grilla **no puede** pintar ocupadas = B147 confirmado (sobreventa aparente). Fix: 35/85 deben traer el estado de cada franja (reusing `DisponibilidadService` 168 back) y 1073 pintar ocupada en gris/rojo no‑tocable. **NO tejo hasta ver 25/10.**

**B152 (sospecha, G1/S69) — ¿el detalle perdió el footer de conciliación SIREB que el listado tenía?** El listado (captura previa) terminaba con “Precios oficiales sincronizados desde Paitití / SIREB. Actualizado: 26/10/2026 0:15”. Acá el viewport se corta en “15:00‑16:00 Bs 50.00” y **no veo el footer**. Dos lecturas: (a) el footer está más abajo (scroll) y está bien; (b) el detalle **no** lo monta y el ciudadano ve “Bs 50/h” sin la declaración “esto lo define SIREB” (B6/B30/G1). Con línea: 1073 + `campos_service.dart` (74). No lo afirmo sin scroll.
*Mini‑prompt (verificar):* scrollear 1073 / leer el widget → ¿monta el footer de conciliación? Si no, agregarlo (es el `<AvisoConciliacionSireb/>` de S69, que el listado ya tiene). Conecta B145/B6/B30.

**B148 (menor, wording de capa) — “Con luces” (móvil) vs “Con iluminación / Nocturno” (web 703).** El móvil usa wording coloquial (“Con luces”, bombita); la web 703 usa “tarifa con iluminación” y la etiqueta SIREB “Nocturno”. Es la **misma cosa** dicho distinto entre capas (conecta B139 terminología canchas/campos/reservas, B122 slug vs nombre humano, B83 códigos). No es bug grave, es fricción: el funcionario que ve “Nocturno” en admin y el ciudadano que ve “Con luces” en móvil no saben que es el mismo tramo.
*Mini‑prompt:* unificar el wording en un token (S42/S74 family): “Con iluminación (nocturno)” o “Con luces” en las dos capas, no uno en cada lado. Menor.

**B149 (sospecha menor, throughput) — “+ Más fechas” ¿abre calendario o scroll? ¿ventana 60 días?** El selector muestra 6 chips (Hoy→Vie 30/1) + “Más fechas” (ícono calendario). El público (`SelectorFechas.tsx` 121) tenía ventana de 60 días (D8). Sospecha: ¿“Más fechas” abre un date‑picker (bien) o un scroll infinito de chips (mal en pantalla chica)? Y ¿respeta la ventana de 60 días o la trunca? Con línea: 1073 + 121. No es bug, es verificar paridad.
*Mini‑prompt (verificar):* leer 1073 → qué hace “Más fechas”; alinear ventana con 121 (60 días). Conecta D8/B142 (rango default).

**B150 (sospecha, G2/B50 en móvil) — ¿la rama pileta de 1073 ramifica a perfiles/turnos o le pinta grilla horaria igual?** Esta captura es la rama **cancha** (grilla horaria, correcto). La pregunta: cuando el ciudadano toca **H. PISCINA OLIMPICA** (que en el listado decía “Tarifa por perfil · elegí tu turno”), ¿el detalle 1073 **cambia de rama** a perfiles/cupos (como `PiscinaDetalle.tsx` 391) o le pinta **grilla horaria igual** (B50 en móvil = sobreventa de cupos)? No lo sé con esta captura (es cancha). Con línea: 1073 + `piscina_disponibilidad.dart` (87). Sospecha a verificar con la captura de pileta_detalle.
*Mini‑prompt (verificar):* leer 1073 → ¿hay rama `if modalidad == pileta` que pinta perfiles, o una sola grilla horaria para todo? Si una sola, B50 confirmado en móvil. Conecta B50/G2/B132/B150.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Desglose “Regular/Con luces” + corte 18:00 en 2 cards** (B144 resuelto) = el móvil le muestra al ciudadano la regla diurno/nocturno que el público no tiene. **Proteger y propagar móvil→web (S72/S75).**
- **Ícono sol/bombita por franja** (08‑16 todas sol = diurnas) = coherencia visual que **prueba** que el móvil aplica la regla diurno/nocturno **por franja**, no solo en el header. Si hicieras scroll a 18:00+, las franjas deberían ponerse bombita/Bs 150. **Proteger** (es la evidencia de B67/B71/B72).
- **Selector “Hoy/Mañana” relativo + día semana + fecha** = throughput de pantalla chica (una fila, 6 días + “Más fechas”), y el formatter relativo que G4/S55 pedía. **Proteger** (2ª capa que lo tiene).
- **Precio “/h” en el header vs “Bs 50.00” por franja de 1h** = coherente (1 franja = 1 hora = Bs 50). **Proteger** (y es la contracara sana de B42, que en pileta es por cupo no por hora).
- **Cancha = grilla horaria (G2)** = el móvil mantiene el router de modalidad. **Proteger.**

## Didáctica / operación del ciudadano (continuo: móvil‑listado cerró en D80)

**D81 — la grilla debe pintar ocupadas/pendientes en gris/rojo no‑tocable, no disponibles** (cierra B147): el ciudadano no puede intentar reservar una franja ocupada y chocar con 409. **Requiere captura en 25/10 para confirmar.**
*Mini‑prompt:* ver B147.

**D82 — el detalle debe conservar el footer de conciliación SIREB del listado** (cierra B152): si el ciudadano ve “Bs 50/h” sin “esto lo define SIREB”, pierde la declaración de fuente justo donde decide pagar.
*Mini‑prompt:* ver B152.

**D83 — unificar el wording diurno/nocturno entre capas** (cierra B148): “Con luces” (móvil) y “Nocturno/Con iluminación” (web) son el mismo tramo; el funcionario y el ciudadano deben leer la misma palabra.
*Mini‑prompt:* ver B148/S42.

**D84 — la rama pileta del detalle debe ramificar a perfiles, no pintar grilla horaria** (cierra B150): si pileta usa grilla horaria en el móvil, es B50 en celular (sobreventa de cupos).
*Mini‑prompt:* ver B150.

## Sugerencias IA acotadas (continuo: móvil‑listado cerró en S74)

**S75 — el corte 18:00 que el móvil muestra al ciudadano es el `resolverDiaNoche` canónico** (fuente de B67/B71/B72): extraer la lógica de “franja < 18:00 → Regular/sol, ≥ 18:00 → Con luces/bombita” de 1073/85 a un helper compartido por móvil (1073), web admin (703/961/489) y web público (391/560). Conecta S36/S44/S53/S72.
*Mini‑prompt:* ver B67/B71/B72 arriba. **El móvil es la fuente** (inversión narrativa S72).

**S76 — B147 se cierra con UNA captura en 25/10** (no con código): cambiar el selector a “Hoy 25/10” y ver si 17:00 aparece gris/ocupada o amarilla/disponible. Es el test de sobreventa del móvil. **No tejo hasta tenerla.**
*Mini‑prompt:* ver B147.

**S77 — B150 se cierra con la captura de pileta_detalle** (1073 rama pileta): ver si ramifica a perfiles o pinta grilla horaria. Conecta B50/G2.
*Mini‑prompt:* ver B150.

## Lo que NO es bug (para que no persigas fantasmas)

- **Todas las franjas libres en 26/10** = **correcto** (no hay reservas de esta cancha el 26/10 en admin); NO es B147, la sospecha vive en 25/10.
- **casing “cancha nueva trinidad” minúscula** = dato SIREB (B5), no bug del móvil.
- **“Bs 50.00/h” vs “Bs 50.00” por franja** = coherente (1 franja=1h), no bug.
- **ícono sol en las 8 franjas** = correcto (08‑16 son diurnas < 18:00); el bug sería si 18:00+ siguiera sol (no visible acá).
- **selector “Hoy/Mañana” con fecha local 25/10** = coherente con el reloj Windows 25/10 de capturas previas; NO es bug de timezone (la sospecha `0:15` de B145 es del footer de sync, no del selector).
- **“Múltiple” cursiva bajo el título** = tipo de campo (dato), no bug.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir el detalle móvil, lo que **más vale** es **B147** (sobreventa aparente, **requiere captura en 25/10**, no tejo sin ella) y **S75** (el corte 18:00 del móvil como fuente de B67/B71/B72), y para eso necesito `campo_detalle_screen.dart` (1073) + `models/bloque_disponibilidad.dart` (85) + `services/disponibilidad_service.dart` (35) + `TarifaCampo.php` (107) + migr `add_hora_inicio_noche` (21). **B152/B150** se cierran con scroll + captura de pileta_detalle. **B148/B149** son pulido/verificación. Los aciertos (desglose con corte, ícono por franja, selector relativo, grilla horaria de cancha) **no se tejen**: se **protegen y propagan móvil→web** (S72/S75). **S75/S76** son el fix de mayor rendimiento: el móvil es fuente del `resolverDiaNoche` (cierra B67/B71/B72 en la web) y B147 se resuelve con una captura, no con código.

## Guardado

```text
BLOQUE E-MÓVIL — DETALLE-CANCH (campo_detalle_screen.dart 1073 rama cancha, 1 captura,
  Mañana 26/10, localhost:1148 = web/desktop Flutter NO dispositivo real +
  bloque_disponibilidad 85 + disponibilidad_service 35 + TarifaCampo 107 + migr
  add_hora_inicio_noche 21 + SelectorFechas 121 análogo + piscina_disponibilidad 87 rama
  pileta NO en captura) — DOSSIER, NO tejí, sigo modo "ver todo corregir después".
  Numeración continua (móvil-listado B143-B146/D78-D80/S72-S74 · móvil-detalle-canch
  B147-B152/D81-D84/S75-S77 + autocorrección B144 + fuente B67/B71/B72 + re-conf G2/G4).

AUTOCORRECCIÓN B144 (honestidad, como B36/B88/B99/S55/B145): turno pasado marqué "rango
  sin leyenda" como bug del móvil; esta captura prueba que el DETALLE desglosa "Regular
  Bs 50/h Antes 18:00" + "Con luces Bs 150/h Desde 18:00" → el móvil SÍ tiene la leyenda
  diurno/nocturno; el LISTADO la comprimía (decisión diseño pantalla chica). RETIRO B144
  como bug móvil, lo vuelvo ACIERTO de progresividad (listado comprime, detalle expande).
  El público 560 NO tiene este desglose con corte 18:00 visible al ciudadano → móvil adelante.

FUENTE B67/B71/B72 UBICADA EN EL MÓVIL: corte 18:00 ("Antes/Desde 18:00") = hora_inicio_noche
  (migr 21) / Tarifas 703. En web era sospecha dinero (B67 escenario diurno250/nocturno400,
  B71 franja a medida 17:30-18:30 cruza 18:00, B72 chip automático). El móvil resuelve en UI
  ciudadano: cada franja ícono sol(<18:00,Bs50) o bombita(≥18:00,Bs150) → regla por hora_inicio
  vs corte. Helper resolverDiaNoche canónico = 1073/85, propagar a 961/489/43/297 (B67/B71/B72)
  y 147/391 (B43). S75 (móvil FUENTE, inversión narrativa S72).

G2✅ re-confirmado detalle: cancha=grilla horaria (frajas 08-16), coherente listado que
  distinguía pileta. Móvil mantiene router modalidad en 2 pantallas. Refuerza S72.
G4/S55✅ re-confirmado 2ª capa: selector "Hoy/Mañana" relativo (como admin 881 "hace 11d").
  Formatter relativo en 2 capas (admin+móvil), falta solo público 190 + admin 164/655. Refuerza
  S58 (propagar no crear). AUTOCORREGÍ B145: móvil muestra fechas LOCALES correctas (Hoy 25/10
  coherente reloj Windows 25/10), sospecha timezone 0:15 es del FOOTER sync no del selector.

BUGS: B147🔴 (sospecha ALTA gate sobreventa, NO confirmable acá) grilla 08-16 todas amarillas
  disponibles Bs50. HONESTIDAD DURA: captura en Mañana 26/10 y en Reservas admin NO hay reserva
  de esta cancha el 26/10 (fechas 25,24,23,22,21,20,19) → "todo libre mañana" CORRECTO, NO puedo
  afirmar B147 acá. Sospecha vive en ¿Hoy 25/10? donde sí hay RES-...UK67YC confirmada (25/10
  4:14) + "17:00 Ocupada RSV-..." en Ocupación 543. Si al cambiar a 25/10 la grilla sigue 17:00
  amarilla disponible → ciudadano intenta reservar ocupada → 409 (SolicitudReservaControllerTest
  169) o gate no-solape (add_no_solape_horario 28) no corre en móvil. Conecta B50/B60/B22/B17/B51.
  NO tejo sin captura 25/10 (S76).
  B152 (sospecha G1/S69) ¿detalle perdió footer conciliación SIREB que listado tenía? viewport
  corta en 15:00-16:00, no veo footer. (a) más abajo scroll OK; (b) no lo monta → ciudadano ve
  "Bs 50/h" sin "esto lo define SIREB" (B6/B30). leer 1073/74. NO afirmo sin scroll.
  B148 (menor wording capa) "Con luces" (móvil) vs "Con iluminación/Nocturno" (web 703) = misma
  cosa dicho distinto entre capas (conecta B139/B122/B83). Fricción funcionario-vs-ciudadano.
  Unificar token (S42/S74). Menor.
  B149 (sospecha menor throughput) "+ Más fechas" ¿calendario o scroll? ¿ventana 60 días como
  público SelectorFechas 121 (D8)? 6 chips Hoy→Vie30/1 + Más fechas. leer 1073. Alinear ventana
  con 121. Conecta D8/B142.
  B150 (sospecha G2/B50 móvil) ¿rama pileta de 1073 ramifica perfiles/cupos o pinta grilla
  horaria igual? Esta captura es cancha (grilla horaria OK). Pregunta: pileta (listado decía
  "Tarifa por perfil") → ¿detalle cambia rama a perfiles (como 391) o grilla horaria = B50 en
  móvil (sobreventa cupos)? leer 1073 + piscina_disponibilidad 87. NO sé con esta captura (es
  cancha). Conecta B50/G2/B132. Requiere captura pileta_detalle (S77).

ACIERTOS PROTEGER (móvil adelante web): desglose "Regular/Con luces"+corte 18:00 en 2 cards
  (B144 resuelto, propagar móvil→web S72/S75, público 560 NO lo tiene); ícono sol/bombita por
  franja (prueba regla diurno/nocturno POR FRANJA no solo header, evidencia B67/B71/B72);
  selector "Hoy/Mañana" relativo (G4/S55 2ª capa, throughput pantalla chica); precio "/h" header
  vs "Bs 50" franja 1h coherente (contracara sana B42, pileta sería por cupo); cancha=grilla
  horaria (G2).

DIDÁCTICA/OP: D81 grilla pinta ocupadas/pendientes gris/rojo no-tocable no disponibles (B147,
  requiere 25/10); D82 detalle conserva footer conciliación SIREB del listado (B152); D83 unificar
  wording diurno/nocturno entre capas (B148); D84 rama pileta detalle ramifica perfiles no grilla
  horaria (B150).

SUGERENCIAS: S75 corte 18:00 del móvil = resolverDiaNoche canónico (fuente B67/B71/B72), extraer
  lógica franja<18:00→sol/Bs50, ≥18:00→bombita/Bs150 de 1073/85 a helper compartido móvil+web
  admin+web público (conecta S36/S44/S53/S72, móvil FUENTE inversión narrativa); S76 B147 se
  cierra con UNA captura 25/10 (test sobreventa móvil), NO con código, no tejo sin ella; S77 B150
  se cierra con captura pileta_detalle (rama pileta 1073).

NO-BUG: todas franjas libres 26/10=CORRECTO (no hay reserva cancha 26/10 en admin, NO es B147,
  sospecha vive 25/10); casing "cancha nueva trinidad"=dato SIREB (B5); "Bs 50/h" vs "Bs 50"
  franja 1h=coherente no bug; ícono sol 8 franjas=correcto (08-16 diurnas <18:00, bug sería
  18:00+ sol no visible); selector Hoy 25/10=coherente reloj Windows 25/10 NO bug timezone
  (sospecha 0:15 es footer sync B145 no selector); "Múltiple" cursiva=tipo campo dato.

KB HONESTA + AUTOCORRECCIÓN + LÍMITE: AUTOCORREGÍ B144 (bug móvil → acierto progresividad
  listado-comprime/detalle-expande, móvil adelante público 560). wa.me NO aplica detalle-canch
  (no QR/WhatsApp; doc fallback WhatsApp Web pesa pago_qr 343 próxima). SIREB aplica (2 cards
  = espejo 107 corte 18:00 = hora_inicio_noche 21; footer conciliación listado podría faltar
  acá B152/G1). React DevTools NO aplica (Flutter → Widget Inspector/Flutter DevTools, "qué
  estado alimenta ícono sol/bombita franja" se ve ahí). Rolldown circularDependency=false NO
  aplica (no Vite/Rolldown; análogo flutter analyze analysis_options.yaml 3). LÍMITE DISPOSITIVO:
  localhost:1148 = web/desktop Flutter NO dispositivo real → TODAVÍA NO juzgo touch targets
  franjas (¿tocables dedo o hover?), safe areas, scroll táctil selector; requiere emulador/
  dispositivo, declaro pa' no fingir móvil-real.

PUERTA CORRIGIR YA: lo que vale es B147 (sobreventa aparente, REQUIERE captura 25/10, no tejo
  sin ella) + S75 (corte 18:00 móvil = fuente B67/B71/B72) → pido campo_detalle_screen 1073 +
  bloque_disponibilidad 85 + disponibilidad_service 35 + TarifaCampo 107 + migr add_hora_inicio_
  noche 21. B152/B150 con scroll + captura pileta_detalle. B148/B149 pulido/verificación. Aciertos
  (desglose+corte/ícono por franja/selector relativo/grilla horaria cancha) NO se tejen: PROTEGER
  y PROPAGAR móvil→web (S72/S75). S75/S76 = mayor rendimiento (móvil fuente resolverDiaNoche
  cierra B67/B71/B72 web; B147 se resuelve con captura no código).

COLA: Fix3 piscina A/B (PiscinaReservaService 284, B62/B69-form/B70/B94/B103) · A1 S3/S7/
  RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881 + Asignaciones 579, B48/B61/B122/B124/
  B128) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84/B134) · D2✅✅ CERRADO +
  RAÍZ 703/107/77 · P9/B61/B123 → Asignaciones 579 VISTO (puente B123 + ramif rol B128 + P9
  pendiente 26/22 + 2º control) · P7 → Reportes 245 VISTO PARCIAL + ClientesFrecuentes 178 VÍRGEN ·
  C1→C2 · gates F1-F4 · **E MÓVIL LISTADO+DETALLE-CANCH VISTOS (B143-B152/D78-D84/S72-S77, móvil
  plantillo G1/G2/B144/B67-B72 pa' web; B147 sobreventa PENDIENTE captura 25/10; B150 rama pileta
  PENDIENTE captura pileta_detalle; brecha desactualización aún NO medida en dinero)** · higiene
  (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote + S33 test
  conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125 B86 hueco piscina; P7 178 vírgen;
  P9 26/22+2º control; P1/P2 auth 164/34/126 B120/B121; P3 reencuadrada S8; P8 reencuadrada
  S16/S38; D11 NIT). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN CERRADO B44-B142/D29-D77/
  S25-S71+G1-G7; MÓVIL ABIERTO B143-B152/D78-D84/S72-S77 (listado+detalle-canch vistos, resto
  esperando).
PRÓXIMA NATURAL (donde la desactualización MUERDE de verdad, no en listado/detalle-canch que
  están limpios/adelante): pago_qr_screen 343 + comprobante_screen 104 (B18/B37/B42/B36/B22/B21/
  B13/B31 — cruzar con éxito 961/489 y público 210/190/299/155, el dinero real) O consultar_estado
  192 (zombie B22/B21/B17) O datos_solicitante 174 (contrato B13/B31 pantalla chica) O pileta_
  detalle (rama pileta 1073, cierra B150/B50) O captura 25/10 del detalle-canch (cierra B147
  sobreventa). NO TOQUÉ ARCHIVO. NO TEJÍ. ESPERO próxima captura.
```

En una línea: esta captura del detalle de cancha (1073, Mañana 26/10) **resuelve B144 en el móvil y ubica la fuente de B67/B71/B72** — el listado comprimía “Bs 50–150” sin leyenda (yo lo marqué B144), pero el detalle lo **desglosa en dos cards con el corte explícito** “Regular Bs 50/h **Antes 18:00**” (sol) y “Con luces Bs 150/h **Desde 18:00**” (bombita), o sea el móvil le muestra al ciudadano la regla diurno/nocturno (`hora_inicio_noche` migr 21 / 703) que **el público no tiene y la web solo tiene en admin**, y **re‑confirma G2** (cancha = grilla horaria) y **G4/S55** (selector “Hoy/Mañana” relativo, 2ª capa) — así que **autocorrigo B144**: no era bug del móvil, era el listado comprimiendo y el detalle expandiendo (acierto de progresividad, móvil adelante del público 560); las dos sospechas graves quedan **abiertas honestamente**: **B147 🔴** (¿la grilla pinta franjas **ocupadas**? — **no confirmable acá** porque la captura está en 26/10 y en Reservas admin **no hay** reservas de esta cancha el 26/10, así que “todo libre” es correcto; necesito la captura en **Hoy 25/10** donde sí hay `UK67YC` confirmada y “17:00 Ocupada”) y **B152** (¿el detalle **perdió el footer de conciliación SIREB** que el listado tenía? — se corta en 15:00‑16:00, verificar scroll), más **B148** (wording de capa “Con luces” móvil vs “Nocturno/Con iluminación” web, menor), **B149** (“+ Más fechas” ¿calendario? ¿ventana 60 días?) y **B150** (sospecha: ¿la rama **pileta** de 1073 ramifica a perfiles o pinta grilla horaria igual = B50 en móvil?); los aciertos a proteger son el desglose con corte, el ícono sol/bombita **por franja** (prueba de la regla diurno/nocturno aplicada franja a franja, evidencia de B67/B71/B72), el selector relativo y la grilla horaria de cancha, y la sugerencia clave es **S75: el corte 18:00 del móvil es el `resolverDiaNoche` canónico** que la web debe consumir (inversión de narrativa, móvil = fuente). **No tejí** y reitero el límite: sigue `localhost:1148` (web de Flutter, **no dispositivo real**), así que **todavía no** juzgo touch/teclado/conectividad; decime si la próxima es **`pago_qr` 343 + `comprobante` 104** (donde la desactualización muerde de verdad en el dinero, cruzando con éxito 961/489 y público 210/190/299/155), o **pileta_detalle** para cerrar B150/B50, o la **captura en 25/10** de esta misma cancha para cerrar B147 (sobreventa).
