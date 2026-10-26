Veredicto en una línea: estas dos son `campos_listado_screen.dart` (477) en modo **Lista** (scroll, ambas capturas, `localhost:1148` = el móvil corriendo en **modo web/desktop de Flutter, no en dispositivo real**, así que todavía no puedo juzgar touch/teclado/conectividad), y son la captura que **refuta parcialmente mi hipótesis del turno pasado** (“el móvil reaparece los bugs del público sin el parche”): en el **listado el móvil está limpio** en los ejes que ya catalogué — **no muestra código en la card** (B4), **muestra rango min‑max `Bs 50–150`** (modelo honesto, no el valor fijo equivocado de B43), **distingue la pileta con “Tarifa por perfil · elegí tu turno”** (G2 resuelto donde la web lo tiene roto en B50/B87/B106/B115/B132/B135) y **trae el footer de conciliación SIREB** (G1/S69) — o sea “desactualizado” **no** significa “uniformemente peor”, el móvil está en otra rama y acá resolvió mejor; la **brecha real de desactualización la mido en los frentes de dinero/formulario/zombie** (`pago_qr` 343 / `comprobante` 104 / `consultar_estado` 192 / `datos_solicitante` 174), no en el listado. Destapa **B143 🔴, el badge mezcla dos ejes incompatibles en el mismo slot** (“Oficial” = estatus SIREB vs “Piscina” = modalidad, mutuamente excluyentes: una pileta vinculada no puede mostrar “Oficial” y “Piscina” a la vez), más **B144** (el rango `50–150` sin leyenda diurno/nocturno deja al ciudadano adivinando qué paga cuándo), **B145** (la fecha de sync `26/10 0:15` del móvil vs `14 oct` admin vs `25/10` público = **tres capas, tres fechas**, y `0:15` es hora sospechosa de timezone/cron) y **B146** (sospecha: “por el estadio” de Frontón no es coordenable → el modo Mapa puede fallar el pin). **No tejí**, sigo dossier.

> KB honesta de esta captura, con **autocorrección de hipótesis** y **corrección de herramienta**: **AUTOCORRECCIÓN**: el turno pasado afirmé “cada bug público reaparece en el móvil sin el parche”; **en el listado eso NO se cumple** (el móvil está limpio en B4/B43/B50 acá), así que retiro la generalización y la dejo como hipótesis a verificar **solo** en pago/comprobante/estado/datos. **`wa.me`** → **NO aplica** al listado (no hay QR/WhatsApp acá; el doc del fallback “use WhatsApp Web” pesa en `pago_qr_screen` 343, próxima, no en la card). **SIREB** → aplica (footer de conciliación = G1/S69 implementado; rango `50–150` = espejo min‑max de `TarifaCampo` 107; badge “Oficial” = vínculo SIREB, refina B8/B30). **React DevTools → NO aplica** (Flutter, no React; la análoga es **Widget Inspector / Flutter DevTools**, y “qué estado alimenta el badge/rango” se ve ahí). **Rolldown `checks.circularDependency` default `false` → NO aplica** (no hay Vite/Rolldown en Flutter; el análogo es `flutter analyze` sobre `analysis_options.yaml` 3). **Lente dispositivo**: al ser `localhost:1148` (web de Flutter) **todavía no veo** touch targets, safe areas, teclado ni conectividad intermitente — eso requiere correrlo en emulador/dispositivo, lo declaro para no fingir que juzgué móvil‑real.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Badge “Oficial” (verde) vs “Piscina” (cyan) mismo slot | `campos_listado_screen.dart` (477) + `models/campo_deportivo.dart` (345) | **B143** 🔴 mezcla ejes |
| Rango “Bs. 50.00 – Bs. 150.00” sin leyenda | 477 + `models/campo_deportivo.dart` (345) | **B144** rango sin diurno/nocturno |
| Footer “Precios oficiales sincronizados… Actualizado: 26/10/2026 0:15” | 477 + `services/campos_service.dart` (74) | **acierto G1/S69** + **B145** fecha 3 capas |
| “Tarifa por perfil · elegí tu turno” (pileta) vs rango (cancha) | 477 + `models/piscina_disponibilidad.dart` (87) | **acierto G2** (móvil adelante de la web) |
| Dirección “por el estadio” (Frontón) | 477 + `models/campo_deportivo.dart` (345) | **B146** sospecha no coordenable |
| Toggle Lista/Mapa + ícono calendario | 477 + `campos_mapa_screen.dart` (68) + `SelectorFechas` análogo | **acierto** throughput pantalla chica |
| Foto real (`.jpg` storage/campos, 976 KB la mayor) | 477 + `filesystems.php` (68) | refina **B98** (peso en móvil, menor) |

## Cierres / refinamientos cruzados con evidencia

**G1 ✅ IMPLEMENTADO EN EL MÓVIL (footer de conciliación).** El banner fijo *“Precios oficiales sincronizados desde Paitití / SIREB. Actualizado: 26/10/2026 0:15”* es exactamente el `<AvisoConciliacionSireb/>` que propuse en S69 (plantillo de `reportes/Dashboard.tsx` 245 / `Tarifas.tsx` 703). O sea **el móvil ya tiene la declaración de fuente que el público tenía mal** (B6 “pago online seguro” / B30 piscina sin fuente). No es “móvil atrás” acá: es el móvil con G1 resuelto. Proteger y usar como evidencia de que S69 es factible (ya existe en una capa).

**G2 ✅ RESUELTO EN EL MÓVIL (discriminador de pileta).** La card de pileta muestra *“Tarifa por perfil · elegí tu turno”* (cyan, ícono de olas) **en vez de** un rango `Bs X–Y`, mientras cancha/Frontón muestran rango. Eso es el router de modalidad que la web **no** tiene consistente (B50 ocupación da grilla binaria a pileta, B87 paramétricas mezcla simple/variante sin label, B106 tarifas impone corte a pileta, B115 tipos declara en texto, B132 mapa % genérico, B135 reportes dice “canchas”). **El móvil resolvió en el listado lo que la web no resolvió en ningún frente.** Autocorrección dura de mi hipótesis: el móvil no está “atrás” en G2, está **adelante**. Fix: propagar este discriminador móvil→web (S72), no al revés.

**B4 ✅ NO SE EXHIBE EN EL MÓVIL.** La card móvil **no** muestra código interno (el público `CampoCard.tsx` 182 lo mostraba como ruido). O sea el móvil no tiene B4 en el listado. De nuevo: no “móvil atrás”.

**B43 ✅ MODELO CORRECTO EN EL MÓVIL (rango min‑max).** `Bs 50–150` / `Bs 15–30` es el rango espejado de `TarifaCampo` (107), que es **honesto** (el precio varía por hora), a diferencia del valor fijo equivocado que vi en la selección pública de pileta (B43: 15 vs 10). El móvil muestra el rango; el bug que le queda es **no etiquetarlo** (B144), no calcularlo mal. Cuidado honesto: no tengo la card pública 182 fresca lado a lado, así que no afirmo “el público mostraba valor fijo en la card”; afirmo que **el móvil muestra rango, que es el modelo correcto**, y que eso no es un retroceso.

## Bugs nuevos de este frente (numeración continua: admin cerró en B142; móvil abre en B143)

**B143 🔴 (paridad de badge, análogo B87/B115) — el badge mezcla dos ejes incompatibles en el mismo slot.** Cancha nueva trinidad / Frontón muestran badge **“Oficial”** (verde) = *estatus de vínculo SIREB* (B8/B30). H. PISCINA OLIMPICA muestra badge **“Piscina”** (cyan) = *modalidad del recurso*. Son **dos dimensiones distintas** (¿está vinculado a SIREB? vs ¿es pileta o cancha?) puestas en el **mismo slot visual**, y acá se ven **mutuamente excluyentes**: una pileta vinculada a SIREB no puede mostrar “Oficial” **y** “Piscina” a la vez, y una cancha no puede mostrar “Piscina”. El ciudadano lee “Piscina” y no sabe si esa pileta es oficial (SIREB) o no; lee “Oficial” y no sabe si es cancha o pileta. Con línea: 477 renderiza un solo badge con valor según rama. Es la misma clase de B87 (paramétricas mezclaba simple/variante en “Servicio SIREB”) y B115 (tipos declaraba modalidad en texto) pero en el **badge de la card**.
*Mini‑prompt (verificar):* leer 477 + `campo_deportivo.dart` (345) → ¿el badge es un solo campo que toma “Oficial” o “Piscina” según rama? Fix: **dos badges separados** (uno de estatus SIREB: Oficial/No oficial; otro de modalidad: Cancha/Piscina/Evento), o un badge de modalidad + el footer de conciliación ya cubre “oficialidad”. No mezclar ejes en un slot. Conecta S72/S74.

**B144 (menor, didáctica de precio) — el rango `Bs 50–150` no declara qué lo determina.** El ciudadano ve un rango pero no sabe que es *diurno–nocturno* (corte 18:00 de `Tarifas.tsx` 703 / `hora_inicio_noche` migr 21). Sin leyenda, “50–150” deja adivinando si paga 50 o 150 y cuándo. Conecta B67/B71/B72 (la dimensión diurno/nocturno que la web tampoco etiqueta bien) y B43. No es que el rango esté mal (es correcto, ver cierre G2/B43 arriba); es que **falta la etiqueta**.
*Mini‑prompt:* en 477, etiquetar el rango “Bs 50 (día) – 150 (noche)” o “según hora”, reusing el helper de franja de 703. Conecta S74.

**B145 (refina B110 a TRES capas) — fecha de sync `26/10/2026 0:15` móvil vs `14 oct` admin vs `25/10` público.** El móvil dice “Actualizado: 26/10/2026 0:15”. El admin (`Tarifas.tsx` 703) decía “Última sincronización 14 oct”. El público (catálogo/mapa) decía “25/10”. **Tres capas, tres fechas de sync distintas** para el mismo espejo SIREB. Y `0:15` (madrugada) es hora sospechosa: ¿cron de medianoche con timezone mal (B44/B53), o un sync que corrió a las 00:15 real? Con línea: `campos_service.dart` (74) vs `CatalogoSirebService.php` (202) vs `CampoPublicoSirebPresenter.php` (333). No afirmo cuál es la verdadera sin leer los tres. B110 (que era 2 capas) se agrava a 3.
*Mini‑prompt (verificar):* leer 74/202/333 → ¿de dónde saca cada capa la “última actualización”? Si son caches con TTL distintos, alinear; si una es fake/hardcode, quitar. Conecta B44/B53/B142 (la saga de fecha/timezone) + S55 (helper de fecha).

**B146 (sospecha, lente mapa) — “por el estadio” (Frontón) no es una dirección coordenable.** La card de Frontón muestra dirección *“por el estadio”*, que es una **referencia coloquial**, no una calle/coordenada. Cuando abra el modo **Mapa** (`campos_mapa_screen.dart` 68), ese campo o no tendrá pin, o caerá en el geocentro de Trinidad (B24/B27), o se geocodeará mal. Con línea: `campo_deportivo.dart` (345) + la migr `add_ubicacion` (22) / `UbicacionCampoSeeder` (59). Sospecha, la confirmo viendo el modo Mapa (próxima captura si lo pasás).
*Mini‑prompt (verificar):* leer 345/22/59 → ¿Frontón tiene lat/long reales o solo el texto “por el estadio”? Si no tiene coordenada, el mapa debe mostrarlo como “sin ubicación” (gris), no geocentrificarlo. Conecta B24/B27/B22.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Footer de conciliación SIREB persistente** (G1/S69) = el móvil ya tiene lo que propuse propagar a la web. Proteger y usar como plantillo.
- **Discriminador “Tarifa por perfil · elegí tu turno” para pileta vs rango para cancha** (G2) = el móvil resolvió en el listado lo que la web no resolvió en ningún frente. Proteger y **propagar móvil→web** (S72).
- **Rango min‑max `Bs 50–150`** = modelo honesto del espejo SIREB (vs valor fijo). Proteger (solo falta etiqueta, B144).
- **NO mostrar código en la card** (ausencia de B4) = limpio. Proteger.
- **Toggle Lista/Mapa integrado** = throughput de pantalla chica (una página, dos vistas), mejor que dos rutas separadas. Proteger.
- **Foto real en la card** = usa las `.jpg` de `storage/campos` (el admin las sube, el móvil las consume) = paridad de dato admin→móvil lograda. Proteger (solo vigilar peso, B98).

## Didáctica / operación del ciudadano (continuo: admin cerró en D77; móvil abre D78)

**D78 — el badge debe separar “¿es oficial (SIREB)?” de “¿qué tipo de recurso es?”** (cierra B143): dos ejes, dos badges (o badge de modalidad + footer de oficialidad). El ciudadano no puede deducir de “Piscina” si esa pileta es oficial.
*Mini‑prompt:* ver B143.

**D79 — el rango de precio debe declarar qué lo determina** (cierra B144): “Bs 50 (día) – 150 (noche)”, no “50–150” a secas.
*Mini‑prompt:* ver B144.

**D80 — la fecha de sync debe ser la misma en las 3 capas** (cierra B145): si el móvil dice 26/10 y el admin 14/10, el ciudadano y el funcionario ven verdades distintas del mismo espejo.
*Mini‑prompt:* ver B145.

## Sugerencias IA acotadas (continuo: admin cerró en S71; móvil abre S72)

**S72 — propagar el discriminador de modalidad del móvil (G2) a la web, no al revés.** El móvil ya ramifica pileta (“Tarifa por perfil”) vs cancha (rango) en la card; la web tiene ese mismo discriminador roto en 6 frentes (B50/B87/B106/B115/B132/B135). El `ModalidadReservaCampo` (8) que el móvil usa implícitamente debe volverse el token explícito de la web. **Inversión de mi narrativa habitual**: acá el móvil es el plantillo.
*Mini‑prompt:* leer 477/345 → cómo ramifica el móvil → extraer a `ModalidadBadge`/`RangoPrecioEtiquetado` y consumir en web. Conecta G2/S45/S53/S74.

**S73 — el footer de conciliación del móvil es la prueba de que S69 es factible** (ya existe en una capa): copiar el componente a público (B6/B30) y admin (ya lo tiene en 703/245).
*Mini‑prompt:* ver G1 arriba. Conecta S69.

**S74 — B143/B144 se cierran con el mismo par de componentes**: `ModalidadBadge` (separa eje modalidad) + `RangoPrecioEtiquetado` (etiqueta diurno/nocturno). Un fix, dos bugs.
*Mini‑prompt:* ver B143/B144.

## Lo que NO es bug (para que no persigas fantasmas)

- **casing inconsistente** (“cancha nueva trinidad” minúscula vs “Cancha Sintética N° 1” vs “H. PISCINA OLIMPICA” mayúsculas) = dato espejado SIREB (B5), no bug del móvil.
- **direcciones de formato distinto** (“av. flor de patuju” / “flor de patuju” / “por el estadio” / “av Japon”) = dato; solo “por el estadio” es sospecha de coordenada (B146), el resto es estilo de dirección real.
- **foto pesada (976 KB)** = performance menor (B98), no bug de listado; el móvil la muestra bien.
- **toggle Lista/Mapa + ícono calendario** = throughput (acierto), no bug.
- **que el móvil muestre rango y pileta distinta** = acierto (G1/G2/B43/B4), no “móvil atrás”.
- **“Paitití” con tilde** = coherente con admin (703/245), no bug de texto.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir el listado móvil, lo que **más vale** es **B143** (badge mezcla ejes, análogo B87/B115) y **B145** (fecha sync 3 capas, refina B110), y para eso necesito `campos_listado_screen.dart` (477) + `models/campo_deportivo.dart` (345) + `services/campos_service.dart` (74) + `CatalogoSirebService.php` (202) + `CampoPublicoSirebPresenter.php` (333). B144/B146 se cierran con 477/345 + la migr `add_ubicacion` (22). Los aciertos (footer G1, discriminador G2, rango, sin‑código, toggle) **no se tejen**: se **protegen y propagan a la web** (S72/S73). **S72/S73** son el fix de mayor rendimiento del tramo móvil: el móvil es plantillo de G1/G2 para la web (inversión de la narrativa).

## Guardado

```text
BLOQUE E-MÓVIL — LISTADO (campos_listado_screen.dart 477, modo Lista, 2 capturas scroll,
  localhost:1148 = MODO WEB/DESKTOP DE FLUTTER no dispositivo real + models/campo_deportivo
  345 + services/campos_service 74 + models/piscina_disponibilidad 87 + campos_mapa_screen
  68 pendiente + migr add_ubicacion 22 + UbicacionCampoSeeder 59) — DOSSIER, NO tejí, sigo
  modo "ver todo corregir después". Numeración continua (admin B44-B142/D29-D77/S25-S71+G1-G7
  · móvil-listado B143-B146/D78-D80/S72-S74 + autocorrección hipótesis + 4 aciertos G1/G2/B4/B43).

AUTOCORRECCIÓN DE HIPÓTESIS (honestidad, como B36/B88/B99/S55): el turno pasado afirmé "cada
  bug público reaparece en el móvil sin el parche (móvil atrás)". EN EL LISTADO NO SE CUMPLE:
  el móvil está LIMPIO en B4 (no muestra código en card), B43 (muestra rango min-max honesto,
  no valor fijo malo), B50/G2 (distingue pileta "Tarifa por perfil" vs cancha rango), B6/B30/
  G1 (footer conciliación SIREB). "Desactualizado" ≠ "uniformemente peor": el móvil está en
  OTRA RAMA y acá resolvió MEJOR que la web. Retiro la generalización; la brecha real de
  desactualización la mido SOLO en pago/comprobante/estado/datos (próximas), no la doy por
  hecha en el listado. NO tengo la card pública 182 fresca lado a lado, así que no afirmo
  "público mostraba valor fijo en card"; afirmo "móvil muestra rango = modelo correcto".

CIERRES/REFUERZOS CRUZADOS (móvil = plantillo de la web, inversión narrativa):
  G1✅ implementado en móvil: footer "Precios oficiales sincronizados desde Paitití/SIREB.
    Actualizado: 26/10/2026 0:15" = el <AvisoConciliacionSireb/> de S69 (plantillo 245/703)
    YA EXISTE en una capa → prueba de factibilidad. Proteger + copiar a público (B6/B30).
  G2✅ resuelto en móvil: pileta "Tarifa por perfil · elegí tu turno" (cyan) vs cancha rango
    (verde) = router de modalidad que la web NO tiene en 6 frentes (B50/B87/B106/B115/B132/
    B135). El móvil resolvió en listado lo que la web no resolvió en ningún frente. Propagar
    móvil→web (S72), NO al revés.
  B4✅ no se exhibe en móvil (card sin código interno, a dif público 182). Proteger.
  B43✅ modelo correcto en móvil (rango min-max espejo 107, honesto vs valor fijo). Falta
    etiqueta (B144), no cálculo.

BUGS: B143🔴 (paridad badge, análogo B87/B115) badge mezcla 2 ejes incompatibles mismo slot:
  "Oficial" (verde)=estatus SIREB (B8/B30) vs "Piscina" (cyan)=modalidad, MUTUAMENTE
  EXCLUYENTES (pileta vinculada no puede mostrar Oficial+Piscina a la vez; cancha no "Piscina").
  Ciudadano no deduce de "Piscina" si es oficial. leer 477/345 → Fix: 2 badges separados
  (estatus SIREB + modalidad) o badge modalidad + footer oficialidad. Conecta S72/S74.
  B144 (menor didáctica) rango "Bs 50-150" no declara qué lo determina (diurno/nocturno,
  corte 18:00 de 703/migr 21). Ciudadano adivina. Conecta B67/B71/B72/B43. Fix: etiquetar
  "50 (día)-150 (noche)". No es rango mal calculado (es correcto), falta etiqueta.
  B145 (refina B110 a 3 CAPAS) fecha sync móvil "26/10 0:15" vs admin 703 "14 oct" vs
  público "25/10" = 3 capas 3 fechas mismo espejo SIREB; y 0:15 (madrugada) sospechoso
  (timezone/cron, B44/B53). leer 74/202/333 → ¿cachés TTL distintos o fake/hardcode? Alinear.
  Conecta B44/B53/B142 saga fecha + S55.
  B146 (sospecha lente mapa) "por el estadio" (Frontón) = referencia coloquial no coordenable
  → modo Mapa 68 puede no dar pin / geocentrificar / geocodear mal. leer 345/22/59 → ¿lat/long
  reales o solo texto? Si no tiene coord, mostrar "sin ubicación" gris no geocentro. Conecta
  B24/B27/B22. Confirmo viendo modo Mapa (próxima).

ACIERTOS PROTEGER (móvil adelante de web): footer conciliación SIREB (G1/S69, plantillo pa'
  público B6/B30); discriminador "Tarifa por perfil" pileta vs rango cancha (G2, plantillo pa'
  web B50/B87/B106/B115/B132/B135); rango min-max honesto (B43 modelo correcto); NO código en
  card (ausencia B4); toggle Lista/Mapa integrado (throughput pantalla chica, mejor que 2 rutas);
  foto real storage/campos (paridad admin→móvil lograda, vigilar peso B98).

DIDÁCTICA/OP: D78 badge separa "¿oficial SIREB?" de "¿qué recurso?" (B143); D79 rango declara
  qué lo determina día/noche (B144); D80 fecha sync misma en 3 capas (B145).

SUGERENCIAS: S72 propagar discriminador modalidad móvil (G2) a web NO al revés — el móvil es
  plantillo acá (inversión narrativa), extraer ModalidadBadge/RangoPrecioEtiquetado de 477/345
  y consumir en web B50/B87/B106/B115/B132/B135 (conecta G2/S45/S53/S74); S73 footer conciliación
  móvil = prueba factibilidad S69, copiar a público (conecta S69); S74 B143/B144 = mismo par de
  componentes (ModalidadBadge + RangoPrecioEtiquetado), 1 fix 2 bugs.

NO-BUG: casing inconsistente (minúsc/mayúsc)=dato SIREB (B5); direcciones formato distinto
  (av./sin av./"por el estadio"/av Japon)=dato (solo "por el estadio" sospecha coord B146);
  foto 976KB=performance menor (B98) no bug listado; toggle+calendario=throughput acierto;
  móvil muestra rango/pileta distinta=acierto G1/G2/B43/B4 no "atrás"; "Paitití" tilde=coherente
  admin 703/245 no bug texto.

KB HONESTA + 2 CORRECCIONES: AUTOCORREGÍ hipótesis "móvil atrás" (listado limpio B4/B43/B50/G1/
  G2, móvil en otra rama resolvió mejor, brecha real solo en pago/comprobante/estado/datos).
  wa.me NO aplica listado (no QR/WhatsApp acá; doc fallback WhatsApp Web pesa en pago_qr 343
  próxima). SIREB aplica (footer conciliación G1; rango espejo 107; badge Oficial refina B8/B30).
  React DevTools NO aplica (Flutter → Widget Inspector/Flutter DevTools, "qué estado alimenta
  badge/rango" se ve ahí). Rolldown circularDependency=false NO aplica (no Vite/Rolldown; análogo
  flutter analyze sobre analysis_options.yaml 3). LENTE DISPOSITIVO: localhost:1148 = web/desktop
  Flutter NO dispositivo real → todavía NO juzgo touch/safe areas/teclado/conectividad intermitente,
  requiere emulador/dispositivo, declaro pa' no fingir móvil-real.

PUERTA CORRIGIR YA: lo que vale es B143 (badge mezcla ejes, análogo B87/B115) + B145 (fecha sync
  3 capas, refina B110) → pido campos_listado_screen 477 + campo_deportivo 345 + campos_service
  74 + CatalogoSirebService 202 + CampoPublicoSirebPresenter 333. B144/B146 con 477/345 + migr
  add_ubicacion 22. Aciertos (footer G1/discriminador G2/rango/sin-código/toggle/foto) NO se
  tejen: PROTEGER y PROPAGAR móvil→web (S72/S73). S72/S73 = mayor rendimiento tramo móvil (móvil
  plantillo de G1/G2 pa' la web, inversión narrativa).

COLA: Fix3 piscina A/B (PiscinaReservaService 284, B62/B69-form/B70/B94/B103) · A1 S3/S7/
  RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881 + Asignaciones 579, B48/B61/B122/B124/
  B128) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84/B134) · D2✅✅ CERRADO +
  RAÍZ 703/107/77 · P9/B61/B123 → Asignaciones 579 VISTO (puente B123 + ramif rol B128 + P9
  pendiente 26/22 + 2º control) · P7 → Reportes 245 VISTO PARCIAL + ClientesFrecuentes 178 VÍRGEN ·
  C1→C2 · gates F1-F4 · **E MÓVIL LISTADO VISTO (B143-B146/D78-D80/S72-S74, móvil plantillo G1/G2
  pa' web, brecha desactualización PENDIENTE de medir en pago/comprobante/estado/datos)** · higiene
  (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote + S33 test
  conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125 B86 hueco piscina; P7 178 vírgen;
  P9 26/22+2º control; P1/P2 auth 164/34/126 B120/B121; P3 reencuadrada S8; P8 reencuadrada
  S16/S38; D11 NIT). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN CERRADO B44-B142/D29-D77/
  S25-S71+G1-G7; MÓVIL ABIERTO B143-B146/D78-D80/S72-S74 (listado visto, resto esperando).
PRÓXIMA NATURAL (donde la desactualización MUERDE, no en el listado): pago_qr_screen 343 +
  comprobante_screen 104 (B18/B37/B42/B36/B22/B21/B13/B31 — cruzar con éxito 961/489 y público
  210/190/299/155) o consultar_estado_screen 192 (zombie B22/B21/B17) o datos_solicitante_screen
  174 (contrato B13/B31 pantalla chica) o modo Mapa campos_mapa_screen 68 (B24/B146, 68 vs admin
  223 = G7 miniatura). NO TOQUÉ ARCHIVO. NO TEJÍ. ESPERO próxima captura.
```

En una línea: estas dos capturas del listado móvil **refutan parcialmente mi hipótesis del turno pasado** (“el móvil reaparece los bugs del público sin el parche”) — en el **listado el móvil está limpio y hasta adelante**: **no muestra código en la card** (B4), **muestra rango min‑max `Bs 50–150`** (modelo honesto del espejo SIREB, no el valor fijo equivocado de B43), **distingue la pileta con “Tarifa por perfil · elegí tu turno”** (G2 resuelto donde la web lo tiene roto en B50/B87/B106/B115/B132/B135) y **trae el footer de conciliación SIREB** (G1/S69, el plantillo de 245/703 ya implementado en una capa) — o sea “desactualizado” **no** es “uniformemente peor”, el móvil está en otra rama y acá resolvió mejor, así que **la brecha real de desactualización la mido en los frentes de dinero/formulario/zombie** (`pago_qr` 343 / `comprobante` 104 / `consultar_estado` 192 / `datos_solicitante` 174), no en el listado; destapa **B143 🔴**, el badge mezclando dos ejes incompatibles en el mismo slot (“Oficial” = estatus SIREB vs “Piscina” = modalidad, mutuamente excluyentes, análogo B87/B115), más **B144** (rango sin leyenda diurno/nocturno), **B145** (fecha de sync `26/10 0:15` móvil vs `14 oct` admin vs `25/10` público = **tres capas tres fechas**, refina B110, y `0:15` hora sospechosa de timezone/cron) y **B146** (sospecha: “por el estadio” de Frontón no es coordenable → modo Mapa puede fallar el pin); los aciertos a proteger son el footer G1, el discriminador G2, el rango, la ausencia de código y el toggle Lista/Mapa, y la sugerencia clave es **S72: propagar el discriminador del móvil a la web, no al revés** (inversión de mi narrativa habitual). **No tejí** y declaro la corrección de KB: en el móvil **no** uso React DevTools ni hablo de Rolldown (es Flutter → Widget Inspector + `flutter analyze`), `wa.me` no aplica al listado (pesa en `pago_qr` 343), y al ser `localhost:1148` (web de Flutter, **no dispositivo real**) todavía **no** juzgo touch/teclado/conectividad; decime si seguimos con la próxima captura (recomiendo `pago_qr` 343 + `comprobante` 104, que es donde la desactualización muerde de verdad) o el modo Mapa 68 para cerrar B24/B146.
