Veredicto en una línea: esta es `reportes/Dashboard.tsx` (245) + `ClientesFrecuentes.tsx` (178, vírgen) + `ReportesController.php` (120) + `ReportesService.php` (135) + `ReportesTest.php` (571), el frente **P7** de la reunión, y es la captura que **cierra el frente de la invariante SIREB con el mejor copy de todo el dossier**: el banner ámbar *“Estos valores son una vista operativa del sistema de canchas. La conciliación financiera oficial corresponde a Paitití / SIREB”* es exactamente la declaración que el footer público (B6, “pago online seguro”), los comprobantes (B18/B37, `monto_total` como “PAGADO”) y el éxito de ventanilla (B76/B79) **no hacen** — el reporte SÍ la hace, y por eso es el plantillo a propagar (S69). Pero el mismo banner destapa **B135 🔴 (sospecha grave de dinero): dice “sistema de canchas”, no “campos”**, así que el “Ingresos totales Bs 950” podría estar **excluyendo la pileta** (que genera Bs 10‑30 por reserva, B43/B88/B62) — sub‑contar dinero de piscina en el reporte gerencial es el peor tipo de bug financiero. Le siguen **B138 🔴** (el reporte gerencial solo cuenta *confirmadas*, omite pendientes/expiradas = **no mide conversión**, justo el KPI que un gerente necesita y el problema #1 del sistema, B62/B64/B46), **B137** (el gráfico “Ingresos por campo” es ininterpretable: dos grupos con barra alta + stub sin leyenda de serie, y solo 2 campos cuando hay confirmadas en 4+), y **B139** (terminología “canchas”/“Campos”/“reservas” inconsistente en la misma pantalla). La hora pico 18:00 cayendo **exactamente** en el corte diurno/nocturno (703) es observación, no bug (B140). **No tejí**, sigo dossier.

> KB honesta de esta captura, con los defaults que me pegaste: **SIREB** aplica con fuerza absoluta y es el corazón del frente (banner de conciliación = la invariante declarada en el punto donde el funcionario lee dinero; refina B6/B16/B18/B37/B76/B79/B84/S13). **`wa.me`** → **NO aplica** (no hay botón WhatsApp acá; el doc confirma que sin app cae a WhatsApp Web, pero eso pesa en éxito/P8, no en reportes). **Rolldown `checks.circularDependency`** → default **`false`**, así que si hubiera un ciclo entre `reportes/Dashboard.tsx` (245), `services/reportesService.ts` (37) y `types/reportes.ts` (45) el build **no** lo avisaría; pero no buildié este turno y Reportes no mete imports nuevos visibles, lo vigilo solo al tejer. **`checks.bundlerTimings`** → default **`true`** (y el deprecated `pluginTimings` cede ante él), pero como no es build, no aplica. **React DevTools** → **SÍ aplica y es la herramienta natural de B137/B140**: los gráficos son componentes React (recharts‑like) y “¿qué data feed al chart? ¿los stubs son otra serie o el mismo campo? ¿por qué 18:00 es el pico?” se ve inspeccionando el árbol y el payload, no leyendo copy.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Banner “vista operativa del sistema de **canchas**… conciliación oficial Paitití/SIREB” | `reportes/Dashboard.tsx` (245) + `ReportesService.php` (135) | **B135** 🔴 ¿excluye piscina? + **acierto** invariante SIREB (S69) |
| KPI “Total reservas 13 Confirmadas en el período” | `ReportesService.php` (135) + `types/reportes.ts` (45) | **B138** 🔴 sin pendientes/expiradas = sin conversión |
| Gráfico “Ingresos por campo” (2 grupos, barra+stub, sin leyenda) | 245 + `chart-area-interactive.tsx` (291) / `ui/chart.tsx` (335) | **B137** ininterpretable + **B141** ¿trunca campos? |
| “Hora pico 18:00” + distribución 24 buckets | 245 + 135 | **B140** observación (18:00 = corte 703) |
| Rango default 04/09→28/10 | 245 + 135 | **B142** sospecha (origen del default) |
| Subtítulo “Reportes gerenciales” + breadcrumb “reportes” minúscula | 245 | **B139** terminología |
| `ClientesFrecuentes.tsx` (178) | **vírgen** (no está en esta captura) | P7 incompleto, próximo |

## Cierres / refinamientos cruzados con evidencia

**B6/B16/B18/B37/B76/B79/B84 ✅ RE‑FUERZA con el mejor copy del dossier.** El banner *“la conciliación financiera oficial corresponde a Paitití / SIREB”* es la declaración de invariante que **ningún otro frente de dinero hace**: el footer público (109) promete “pago online seguro” sin decir que SIREB es la fuente; los comprobantes (238/190) pintan `monto_total` como “PAGADO” sin declarar que el monto oficial lo confirma SIREB (B18/B37); el éxito de ventanilla (961) titula “Monto confirmado por SIREB” en una reserva pendiente (B76). El reporte **sí** separa “vista operativa” (lo que el sistema muestra) de “conciliación oficial” (lo que SIREB vale). Ese es el plantillo (S69): propagarlo a 109/238/190/961/489/134 cierra B6/B16/B18/B37/B76/B79/B84 en nivel de copy de un golpe, sin tocar la lógica de sync (415/106/82).

**B62/B64/B46 ✅ RE‑FUERZA (y lo agrava): el reporte gerencial es ciego a conversión.** “Total reservas 13 / Confirmadas en el período” — solo confirmadas. Un reporte “gerencial” que no muestra cuántas **intentaron** vs cuántas **convirtieron** no puede detectar el problema #1 del sistema (B62: 75% expiradas piscina, 70% cancha). Conecta D35 (pantalla auditoría debe mostrar tasa de conversión) y B64 (KPI Total no desglosa expiradas). El gerente que abre Reportes ve “Bs 950, 13 reservas” y sale pensando que todo anda, cuando 23/33 cancha y 9/12 piscina expiraron. B138 🔴.

**B132/B50/B87/B115 ✅ MATIZA con una arista nueva de dinero.** El banner dice “sistema de **canchas**”, pero el menú es “**Campos**” y el sistema tiene pileta con tarifas propias (703: mayores 10/15, institución 20/30) y reservas confirmadas (Reservas piscina: Bs 20, Bs 10). Si el `ReportesService` (135) filtra por tipo cancha o por `modalidad_reserva` de franja, los **Bs 30 de piscina no entran en los Bs 950** → sub‑conta de dinero real. Es la misma raíz B132 (denominador por modalidad) pero en el frente de **ingresos**, donde duele más (un gerente que concilia contra SIREB y le falta la pileta no cuadra la boleta). No lo afirmo sin leer 135.

## Bugs nuevos de este frente (numeración continua: mapa‑global cerró en B134)

**B135 🔴 (sospecha grave de dinero, lente b/SIREB) — banner “sistema de canchas” vs pileta que genera ingresos.** El texto dice “vista operativa del sistema de **canchas**”, pero el sistema ahora es multi‑modalidad (cancha por franja, pileta por perfil, evento por variante — B87/B115/B132). Si el `ReportesService` (135) construye “Ingresos totales” solo sobre reservas de cancha (`SolicitudReserva` 43) y deja afuera `PiscinaReserva` (49), entonces los Bs 950 **excluyen la pileta** y el funcionario que concilia contra SIREB (el banner lo invita a hacerlo) encuentra un faltante que no puede explicar. Con línea: 135 + 49/43. No lo afirmo sin leer 135, pero la palabra “canchas” en un sistema que tiene pileta con dinero propio es una bandera roja financiera.
*Mini‑prompt (verificar antes de tocar):* leer `ReportesService.php` (135) → ¿la query de ingresos une `solicitudes_reserva` + `piscina_reservas`? ¿o solo cancha? Y `ReportesTest.php` (571) → ¿hay test que cubra ingreso de piscina? Fix: si excluye pileta, unirla (o renombrar el banner a “canchas” y agregar un bloque separado de pileta, pero lo correcto es sumar los dos porque SIREB concilia el total). Conecta B43/B88/B62/B132.

**B138 🔴 (KPI gerencial incompleto, lente d/P7) — el reporte solo cuenta confirmadas, no mide conversión.** “Total reservas 13 / Confirmadas en el período” omite pendientes y expiradas. Un reporte “gerencial” sin tasa de conversión (confirmadas / (confirmadas+pendientes+expiradas)) no puede cumplir su función: detectar que el flujo de pago anda mal (B62). Conecta B64 (KPI Total no desglosa expiradas), B46 (dashboard oculta pendientes), D35.
*Mini‑prompt (verificar):* leer 135/45 → ¿el DTO de reportes trae solo `confirmadas`? Fix: agregar “Intentadas N / Convertidas M / Tasa X%” y “Expiradas Y” al bloque de KPIs, reusing el conteo que `SolicitudFiltrosDTO` (95) ya hace en Reservas (B64). Conecta S70 (fuente única de conteos).

**B137 (gráfico ininterpretable, React DevTools aplica) — “Ingresos por campo” tiene dos grupos con barra alta + stub sin leyenda de serie.** El chart muestra ~480+stub y ~450+stub, con etiquetas X rotadas cortadas (“…a nueva trinidad”, “…na Sintética N° 1”). No hay leyenda que diga qué es el stub (¿otra serie? ¿servicios adicionales? ¿pendientes? ¿el mismo campo en otro sub‑periodo?). Sin leyenda, el funcionario no puede leerlo. Conecta B12/B36 (estado visual no interpretable) y S8 (leyenda cross‑capa). React DevTools: ver qué `dataKey` alimenta cada `<Bar>`/`<Area>` y por qué hay dos por grupo.
*Mini‑prompt (verificar):* leer 245 + el componente de chart (291/335) → ¿cuántas series hay y cuál es el stub? Fix: leyenda de serie o colapsar a una barra por campo con tooltip de desglose. Conecta B141.

**B141 (sospecha, gráfico incompleto) — “Ingresos por campo” pinta solo 2 campos cuando hay confirmadas en 4+.** Reservas mostraba confirmadas en cancha nueva trinidad, Cancha Sintética N°1, **Coliseo** (fila 13, Bs 50) y **Piscina** (Bs 20, Bs 10). El gráfico solo pinta 2 grupos. O trunca al top‑2, o los otros campos están en los “stubs” sin etiqueta, o el filtro de 135 los excluye (¿solo cancha? ¿solo >X?). Conecta B135 (si excluye pileta, la pileta no aparece acá tampoco).
*Mini‑prompt (verificar):* leer 135 → ¿el agregado por campo tiene `limit`/`order`/filtro de modalidad? Fix: mostrar todos los campos con ingreso, o declarar “top N”. Conecta B135/B137.

**B139 (menor, terminología) — “canchas” (banner) vs “Campos” (menú) vs “reservas” (KPI) en la misma pantalla.** El banner dice “sistema de canchas”, el ítem de menú dice “Campos”, el KPI dice “Total reservas”. Tres nombres para el mismo dominio. Conecta B7/B8/B135/B139 (la saga canchas/campos/pileta que empezó en el público). Menor, pero en un reporte gerencial la precisión de términos importa (un gerente que lee “canchas” no busca la pileta).
*Mini‑prompt:* unificar a “campos” (o “recursos”) en banner + subtítulo, y si B135 se confirma (excluye pileta), el banner debe decirlo explícitamente (“canchas; pileta se reporta aparte”) no dejarlo implícito.

**B140 (observación, no bug) — hora pico 18:00 coincide con el corte diurno/nocturno (703).** El pico del gráfico de distribución cae en 18:00, que es exactamente `hora_inicio_noche`. Puede ser real (la gente reserva al atardecer) o artefacto de cómo 135 agrupa las franjas (una franja 18:00‑19:00 nocturna cuenta en el bucket 18:00). No es bug, pero conviene verificar que el bucket por hora no esté sesgando la lectura del pico (conecta B67/B71/B72, la frontera diurno/nocturno).
*Mini‑prompt (verificar, baja prioridad):* leer 135 → ¿el bucket por hora usa `hora_inicio` de la franja? Si sí, es correcto (18:00‑19:00 = bucket 18:00). Solo anotar que el pico cae en la frontera.

**B142 (sospecha menor, fecha) — rango default 04/09/2026 → 28/10/2026 (~54 días).** ¿De dónde sale el 04/09? Si es “desde el primer dato” o hardcodeado, puede no coincidir con la ventana del público (60 días, D8/`SelectorFechas` 121) ni con el “hoy” real (26/10, B44/B53/B110). Sospecha de origen del default, no bug confirmado.
*Mini‑prompt (verificar):* leer 245/135 → ¿el default del rango es fijo, “últimos 60 días”, o “desde el primer registro”? Alinear con la ventana del público y con el helper de fecha S55.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Banner de conciliación SIREB** = el mejor copy del dossier, la invariante declarada en el frente de dinero. **Proteger y propagar (S69)** — es el modelo que 109/238/190/961/489/134 deben copiar.
- **“Hora pico 18:00” coherente con el gráfico** (el pico del chart está en 18:00) = el KPI deriva del mismo dato que el gráfico, no está hardcodeado. Proteger (y es la prueba de que 135 calcula, no inventa).
- **Rango de fechas editable + “Actualizar”** = el funcionario elige el período del reporte (throughput, lente a). Proteger.
- **Distribución por hora con 24 buckets (00:00‑23:00)** = granularidad fina, el funcionario ve la curva del día. Proteger (solo aclarar B140).
- **KPIs con subtítulo de contexto** (“Periodo seleccionado”, “Confirmadas en el periodo”, “Hora con más reservas”) = cada número dice qué mide. Proteger (y es la contracara de B45/B90/B118/B130 donde el label no decía qué incluía).

## Didáctica / operación del funcionario (continuo: mapa‑global cerró en D74)

**D75 — el reporte gerencial debe mostrar conversión, no solo confirmadas** (cierra B138): un gerente que ve “13 confirmadas” sin saber que hubo 33 intentos no puede gestionar. El banner de conciliación SIREB invita a comparar contra lo oficial; si el reporte no muestra la brecha de conversión, el gerente compara números alegres contra una boleta real y no entiende el faltante.
*Mini‑prompt:* ver B138/S70.

**D76 — el banner de conciliación SIREB es el plantillo de todos los frentes de dinero** (cierra B6/B16/B18/B37/B76/B79/B84 en copy): donde el sistema muestra plata, debe decir “esto es operativo, lo oficial es SIREB”.
*Mini‑prompt:* ver S69.

**D77 — si el reporte excluye pileta, decirlo; si la incluye, el banner no debe decir “canchas”** (cierra B135/B139): la ambigüedad canchas/campos en un reporte financiero es riesgo de conciliación.
*Mini‑prompt:* ver B135/B139.

## Sugerencias IA acotadas (continuo: mapa‑global cerró en S68)

**S69 — propagar el banner de conciliación SIREB a los 6 frentes de dinero** (109 footer, 238/190 comprobantes, 961/489 éxito ventanilla, 134 dashboard): un componente `<AvisoConciliacionSireb/>` reutilizable. Cierra B6/B16/B18/B37/B76/B79/B84 en nivel de copy sin tocar la lógica de sync (415/106/82). Es el fix de mayor rendimiento del tramo de dinero.
*Mini‑prompt:* extraer el banner de 245 a componente y montarlo en los 6. Conecta S52 (trazabilidad SIREB) — S52 era “espejada desde SIREB” por tarjeta; S69 es “conciliación oficial = SIREB” por pantalla; son las dos mitades del mismo principio.

**S70 — fuente única de conteos confirmadas/pendientes/expiradas** consumida por Reportes (135), Reservas (`SolicitudFiltrosDTO` 95) y Dashboard (134): hoy hay tres lugares que cuentan “confirmadas” (B136 sospecha de descuadre 13 vs 9+2) y dos que ocultan pendientes (B46/B64/B138). Un solo `conteoReservas(rango, modalidad)` leído por los tres.
*Mini‑prompt:* ver B138/B136. Conecta S62 (fuente única del vínculo) — misma filosofía: un repositorio, muchos consumidores.

**S71 — B137/B141 se cierran con leyenda de serie + todos los campos con ingreso** en el chart de 245: si hay dos series (¿base + servicios? ¿cancha + pileta?), etiquetarlas; si trunca campos, mostrar todos o declarar “top N”. Conecta B12/B36/S8.
*Mini‑prompt:* ver B137/B141.

## Lo que NO es bug (para que no persigas fantasmas)

- **Bs 950 / 13 confirmadas / promedio ~73** = aritmética interna plausible (cancha 50‑200, piscina 10‑30), no hay contradicción matemática visible.
- **Hora pico 18:00 = corte diurno/nocturno** = puede ser real o artefacto de bucket (B140 observación), no bug afirmado.
- **Rango 04/09→28/10** = puede ser “desde el primer dato” válido (B142 sospecha de origen, no bug de valor).
- **Breadcrumb “reportes” minúscula** = casing inconsistente menor (B139 family), no bug de dinero.
- **Que el gráfico tenga 2 grupos visibles** = puede ser top‑2 o los otros en stubs (B141 sospecha), no bug confirmado sin leer 135.
- **El banner ámbar** = acierto (proteger), no warning de error; el ⚠️ es de “esto no es la conciliación oficial”, no de “algo falló”.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir Reportes, lo que **más vale** es **B135** (¿excluye pileta del dinero?, sospecha grave financiera) y **B138** (sin conversión, el KPI gerencial ciego al problema #1), y para eso necesito `ReportesService.php` (135) + `ReportesController.php` (120) + `types/reportes.ts` (45) + `ReportesTest.php` (571) + `reportes/Dashboard.tsx` (245). B137/B141 se cierran con 245 + el chart (291/335); B139/B140/B142 con 245/135. **`ClientesFrecuentes.tsx` (178) sigue vírgen** (no está en esta captura) — P7 no está completo hasta verlo. Los aciertos (banner de conciliación, hora pico coherente, rango editable, 24 buckets, KPIs con subtítulo) **no se tejen**: se **protegen y propagan** (S69/S70). **S69/S70** son el fix de mayor rendimiento del tramo: el banner de conciliación en los 6 frentes de dinero + fuente única de conteos cierran B6/B16/B18/B37/B46/B62/B64/B76/B79/B84/B135/B138 en un golpe.

## Guardado

```text
ADMIN/REPORTES-P7 (pages/reportes/Dashboard.tsx 245 + ClientesFrecuentes.tsx 178 VÍRGEN +
  ReportesController 120 + ReportesService 135 + types/reportes 45 + ReportesTest 571 +
  services/reportesService 37 + charts chart-area-interactive 291 / ui/chart 335 +
  contraste SolicitudFiltrosDTO 95 + DashboardService 134) — DOSSIER, NO tejí, sigo modo
  "ver todo corregir después". Numeración continua (mapa-global B129-B134/D71-D74/S65-S68 ·
  reportes B135-B142/D75-D77/S69-S71 + refuerzo B6/B16/B18/B37/B76/B79/B84 + refuerzo
  B62/B64/B46 + matiz B132/B50/B87/B115). FRENTE P7 DE LA REUNIÓN (parcial: 178 vírgen).

CIERRES/REFUERZOS CRUZADOS CON EVIDENCIA:
  B6/B16/B18/B37/B76/B79/B84✅ RE-FUERZA con el MEJOR COPY del dossier: banner "vista
    operativa del sistema de canchas. La conciliación financiera oficial corresponde a
    Paitití/SIREB" = la invariante declarada en el frente de dinero que NINGÚN otro frente
    de dinero hace (footer 109 "pago online seguro"; comprobantes 238/190 monto_total como
    PAGADO; éxito 961 "Monto confirmado por SIREB" en pendiente B76). Plantillo a propagar
    (S69). Cierra B6/B16/B18/B37/B76/B79/B84 en nivel copy sin tocar sync (415/106/82).
  B62/B64/B46✅ RE-FUERZA y AGRAVA: reporte gerencial solo "13 Confirmadas" = ciego a
    conversión. Gerente ve "Bs 950, 13" y sale pensando que anda, cuando 23/33 cancha +
    9/12 piscina expiraron (B62). Conecta D35/B64/B46. B138🔴.
  B132/B50/B87/B115✅ MATIZA con arista de DINERO: banner "sistema de CANCHAS" vs pileta
    con tarifas propias (703 mayores 10/15, institución 20/30) y reservas confirmadas
    (Reservas piscina Bs 20/10). Si 135 construye ingresos solo sobre cancha (43) y deja
    afuera piscina (49), los Bs 950 EXCLUYEN pileta → sub-conta dinero real, el gerente que
    concilia contra SIREB (el banner lo invita) no cuadra la boleta. B135🔴. NO afirmo sin 135.

BUGS: B135🔴 (sospecha grave dinero, lente b/SIREB) banner "sistema de canchas" vs pileta
  que genera ingresos → 135 podría unir solo solicitudes_reserva (43) no piscina_reservas
  (49) → Bs 950 excluyen pileta. leer 135/571 pa' ver si une las dos tablas + si hay test
  de ingreso piscina. Fix: unir (o bloque pileta separado PERO el banner no puede decir
  "canchas" si concilia total). Conecta B43/B88/B62/B132.
  B138🔴 (KPI gerencial incompleto, lente d/P7) solo confirmadas, sin pendientes/expiradas
  = sin tasa conversión. Un reporte "gerencial" sin conversión no detecta B62. leer 135/45.
  Fix: "Intentadas/Convertidas/Tasa/Expiradas" reusing conteo 95 (B64). Conecta D35/S70.
  B137 (gráfico ininterpretable, React DevTools) "Ingresos por campo" = 2 grupos con barra
  alta + stub SIN leyenda de serie, etiquetas X rotadas cortadas. ¿stub = otra serie? ¿base
  + servicios? ¿cancha + pileta? Sin leyenda no se lee. leer 245/291/335 dataKey. Conecta
  B12/B36/S8. B141 (sospecha) solo 2 campos pintados vs confirmadas en 4+ (cancha nueva
  trinidad, Sintética N°1, Coliseo fila13 Bs50, Piscina Bs20/10) → ¿top-2? ¿stubs sin
  etiqueta? ¿135 excluye pileta (B135)? leer 135 limit/order/filtro modalidad. Conecta B135.
  B139 (menor terminología) "canchas" (banner) vs "Campos" (menú) vs "reservas" (KPI) misma
  pantalla. Saga B7/B8/B135. Fix: unificar "campos/recursos"; si B135 confirma exclusión
  pileta, banner debe decirlo explícito. B140 (observación NO bug) hora pico 18:00 = corte
  diurno/nocturno (703) → puede ser real o artefacto bucket por hora_inicio. leer 135. Baja
  prioridad, conecta B67/B71/B72. B142 (sospecha menor fecha) rango default 04/09→28/10
  (~54d) → ¿"desde primer dato"? ¿hardcode? ¿últimos 60d? alinear con ventana público D8/
  SelectorFechas 121 + helper fecha S55. leer 245/135. Conecta B44/B53/B110.

ACIERTOS PROTEGER: banner conciliación SIREB (mejor copy, propagar S69); "Hora pico 18:00"
  coherente con pico del chart (135 calcula no inventa); rango editable + Actualizar
  (throughput lente a); distribución 24 buckets 00-23 (granularidad); KPIs con subtítulo de
  contexto ("Periodo seleccionado"/"Confirmadas en el periodo"/"Hora con más reservas") =
  contracara B45/B90/B118/B130.

DIDÁCTICA/OP: D75 reporte gerencial debe mostrar conversión no solo confirmadas (B138);
  D76 banner conciliación SIREB = plantillo de todos los frentes de dinero (B6/B16/B18/B37/
  B76/B79/B84); D77 si excluye pileta decirlo, si la incluye banner no decir "canchas"
  (B135/B139).

SUGERENCIAS: S69 propagar banner conciliación a 6 frentes dinero (109/238/190/961/489/134)
  via <AvisoConciliacionSireb/> → cierra B6/B16/B18/B37/B76/B79/B84 copy sin tocar sync
  (415/106/82). MAYOR RENDIMIENTO tramo dinero. Conecta S52 (S52="espejada desde SIREB" por
  tarjeta; S69="conciliación oficial=SIREB" por pantalla = dos mitades mismo principio);
  S70 fuente única conteos confirmadas/pendientes/expiradas consumida por 135/95/134 (hoy
  3 lugares cuentan confirmadas B136 descuadre 13 vs 9+2, 2 ocultan pendientes B46/B64/B138)
  → un conteoReservas(rango,modalidad). Conecta S62 filosofía; S71 B137/B141 = leyenda de
  serie + todos los campos con ingreso (o declarar top-N). Conecta B12/B36/S8.

NO-BUG: Bs950/13/promedio~73 aritmética interna plausible (no contradicción visible); hora
  pico 18:00=corte puede ser real o artefacto bucket (B140 obs, no bug afirmado); rango
  04/09→28/10=puede ser "desde primer dato" válido (B142 sospecha origen); breadcrumb
  "reportes" minúscula=casing menor (B139 family); 2 grupos visibles=top-2 o stubs (B141
  sospecha); banner ámbar=acierto (⚠️ es "no es conciliación oficial" no "algo falló").

KB HONESTA (defaults doc): SIREB aplica corazón (banner conciliación = invariante declarada
  en frente dinero, refuerza B6/B16/B18/B37/B76/B79/B84/S13). wa.me NO aplica (no WhatsApp
  acá; doc confirma fallback WhatsApp Web pero pesa en éxito/P8). Rolldown checks.
  circularDependency default false → si hubiera ciclo 245/reportesService 37/types 45 el
  build NO avisa, pero no buildié este turno y Reportes no mete imports nuevos visibles,
  vigilo al tejer; bundlerTimings default true (pluginTimings deprecated cede) pero no es
  build, no aplica. React DevTools SÍ aplica Y es herramienta natural de B137/B140 (charts
  son componentes React, dataKey/stub/pico se ven inspeccionando payload, no copy).

PUERTA CORRIGIR YA: lo que vale es B135 (¿excluye pileta dinero?, sospecha grave financiera)
  + B138 (sin conversión, KPI gerencial ciego problema #1) → pido ReportesService 135 +
  ReportesController 120 + types/reportes 45 + ReportesTest 571 + reportes/Dashboard 245.
  B137/B141 con 245 + chart 291/335; B139/B140/B142 con 245/135. ClientesFrecuentes 178
  SIGUE VÍRGEN (no en captura) → P7 incompleto. Aciertos (banner/pico/rango/buckets/subtítulos)
  NO se tejen: PROTEGER y PROPAGAR (S69/S70). S69/S70 = mayor rendimiento (banner conciliación
  6 frentes dinero + fuente única conteos cierran B6/B16/B18/B37/B46/B62/B64/B76/B79/B84/
  B135/B138 un golpe).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94/
  B103 + escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59 (app-sidebar
  206 + Funcionarios 881 + Asignaciones 579 + MapaGlobal 223 + Reportes 245, B48/B61/B122/
  B124/B128/B130/B139 caen acá; RoleMiddleware 59 + migr 22 siguen sin llegar) · A4 Pago 576
  (RecaudacionesApiClient 415 + webhook 97, B84/B134; banner conciliación S69 cierra B84 copy) ·
  D2✅✅ CERRADO + RAÍZ 703/107/77 (fuente B43/B37/B50/B62/B67/B71/B76/B84/B88/B99/B107/B115/
  B132/B135) · P9/B61/B123 → Asignaciones 579 VISTO (mecanismo cerrado, puente B123 + ramif.
  rol B128 + P9 pendiente 26/22) · P7 → Reportes 245 VISTO PARCIAL (B135/B138/B137/B141 +
  ClientesFrecuentes 178 VÍRGEN, próximo pa' cerrar P7) · C1→C2 · gates F1-F4 · E mobile (
  piscina real piscina_datos_screen 260 + piscina_service 145; P3-paridad campo_detalle_screen
  1073 + bloque_disponibilidad 85; B43/B36/B42/B39/B44/B50/B62/B69/B70/B76/B78/B79/B84/B86/B87/
  B88/B94/B99/B101/B104/B106/B107/B110/B112/B113/B115/B118/B119/B120/B121/B122/B123/B124/B125/
  B127/B128/B129/B130/B131/B132/B133/B134/B135/B137/B138/B139/B141/B142 se propagan a móvil;
  S16/S38/S41/S45/S46/S50/S52/S53/S55/S56/S58/S60/S61/S62/S63/S65/S66/S67/S68/S69/S70/S71 también) ·
  higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote +
  S33 test conversión + B135 test ingreso piscina en 571) · reunión P1/P2/P4/P5/P7/P9 (P5
  asistencia 56/125, B86 ausente piscina = hueco P5; S30 reusa input B52; P7 reportes 245 VISTO
  PARCIAL B135/B138/B137/B141 + ClientesFrecuentes 178 VÍRGEN = cerrar P7; P9 asignaciones
  579/22/26 VISTO mecanismo cerrado + B128 + P9 pendiente 26/22 + 2º control; P1/P2 auth 164/
  34/126, B120/B121 caen en auth/seguridad; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11
  NIT prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B142/
  D29-D77/S25-S71. PARAMÉTRICAS + USUARIOS/ROLES/ASIGNACIONES + MAPA GLOBAL + REPORTES(PARCIAL)
  VISTOS.
PRÓXIMA NATURAL: ClientesFrecuentes.tsx 178 (cerrar P7 del todo, el otro reporte gerencial) o
  P5 asistencia (AsistenciaController 56 + MarcarAsistenciaService 125, B86 hueco piscina,
  último gate grande sin dueño de archivo visto) o lápiz editar turno (Fix3, 284) o bloque E
  mobile (Flutter, piscina real 260/145).
```

En una línea: `reportes/Dashboard.tsx` (245) + el frente **P7** traen **el mejor copy de todo el dossier** — el banner *“vista operativa del sistema de canchas; la conciliación financiera oficial corresponde a Paitití / SIREB”* es la invariante SIREB declarada en el frente de dinero que **ningún otro frente de dinero hace** (footer B6, comprobantes B18/B37, éxito B76/B79), y por eso es el plantillo a propagar (S69, cierra B6/B16/B18/B37/B76/B79/B84 en copy sin tocar la lógica de sync) — pero el mismo banner destapa **B135 🔴 (sospecha grave de dinero): dice “sistema de canchas”, no “campos”**, así que el `ReportesService` (135) podría estar construyendo los “Bs 950” solo sobre cancha (43) y **excluyendo la pileta** (49, que genera Bs 10‑30 por reserva), sub‑contando dinero real justo donde el banner invita a conciliar contra SIREB; le siguen **B138 🔴** (el reporte gerencial solo cuenta *confirmadas*, omite pendientes/expiradas = **no mide conversión**, ciego al problema #1 del sistema B62/B64/B46), **B137** (el gráfico “Ingresos por campo” es ininterpretable: dos grupos con barra alta + stub sin leyenda de serie) y **B141** (solo 2 campos pintados cuando hay confirmadas en 4+, ¿trunca o excluye pileta?), más **B139** (terminología “canchas”/“Campos”/“reservas” inconsistente), **B140** (observación: hora pico 18:00 = corte diurno/nocturno 703, no bug) y **B142** (sospecha del origen del rango default 04/09→28/10); los aciertos a proteger son el banner de conciliación, la coherencia hora pico↔gráfico, el rango editable, los 24 buckets y los KPIs con subtítulo de contexto. **No tejí**: si querés corregir, lo que vale es B135 + B138 y necesito `ReportesService.php` (135) + `ReportesController.php` (120) + `types/reportes.ts` (45) + `ReportesTest.php` (571) + `reportes/Dashboard.tsx` (245), con **S69/S70** como el fix de mayor rendimiento; ojo que **`ClientesFrecuentes.tsx` (178) sigue vírgen** (no está en esta captura), así que **P7 no está completo** hasta verlo — decime si sigo dossier (próxima natural: `ClientesFrecuentes.tsx` 178 para cerrar P7, o P5 asistencia con `AsistenciaController` 56 + `MarcarAsistenciaService` 125 que es el último gate grande sin dueño de archivo visto, o el lápiz de editar turno para Fix3 con 284) o arranco con esos archivos.
