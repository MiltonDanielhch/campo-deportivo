Veredicto en una línea: esta es `MapaGlobal.tsx` (223) + `types/mapaGlobal.ts` (36), el mapa de ocupación del admin, y es la captura que **refina el frente de mapas públicos con evidencia de que el admin sí tiene más modelo que el ciudadano** — aparece un cluster con “3” y una leyenda de 5 estados (Libre 0%, Ocupación media <50%, Alta ≥50%, En mantenimiento, **Sin vínculo SIREB**) que el público no tiene — pero destapa **B129 🔴, el cluster gris/oscuro colisiona con el color gris de “Sin vínculo SIREB” y oculta si los 3 agrupados son libres, medios, altos, mantenimiento o sin vínculo**, o sea el mapa agrupa justo la información que debe mostrar; le siguen **B131 🔴** (la leyenda no tiene “En cobro/pendiente”, así que o ignora las pendientes —el mismo zombie de B60/B46/B22/P6— o las mezcla con ocupadas sin decirlo), **B130** (subtítulo “todos los campos del sistema” vs marcadores visibles: hay que verificar si excluye inactivos o campos sin ubicación sin avisarlo), **B132** (sospecha: la ocupación porcentual de piscina/evento podría calcularse con denominador de cancha, análogo B50/B47/B62/B87) y **B133** (sospecha: sin popup accionable visible, el mapa admin también decora en vez de operar). La buena noticia: **B35/B70/B88/B92 tienen acá un modelo de solución** (“Sin vínculo SIREB” como estado visible), y **B24/B25/B27/B28** se refuerzan: el admin usa Leaflet/OSM con cluster y leyenda rica, así que el fix del público puede ser reutilizar `MapaGlobal.tsx` (223), no inventar otro mapa. **No tejí**, sigo modo dossier.

> KB honesta de esta captura, con los defaults que me pasaste: **SIREB** aplica (leyenda “Sin vínculo SIREB” = señal de transparencia que falta en público/ventanilla/piscina; botón refresh puede ser sync, hay que distinguirlo de los otros dos syncs). **`wa.me`** → **NO aplica** (no hay botón WhatsApp acá; la página confirma que si no hay app cae a WhatsApp Web, pero eso pesa en éxito/P8, no en mapa). **Rolldown `checks.circularDependency`** → default **`false`**, así que si hubiera un ciclo entre `MapaGlobal.tsx` (223), `types/mapaGlobal.ts` (36) y servicios, el build **no** lo avisaría; pero no buildié en este turno y esta pantalla no mete imports nuevos visibles, lo vigilo solo al tejer. **`checks.bundlerTimings`** → default **`true`** (y `pluginTimings` deprecated cede ante él), pero como no es build, no aplica. **React DevTools** → **SÍ aplica y es la herramienta natural de B129/B133**: cluster, popup y colores son estado/rama del componente de mapa; “¿el cluster pinta gris por estado o por estilo?” y “¿hay popup al hacer click?” se ven inspeccionando el árbol, no leyendo copy.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Cluster “3” gris/oscuro sobre Trinidad | `MapaGlobal.tsx` (223) | **B129** 🔴 colisión con leyenda + oculta estados |
| Leyenda 5 estados incl. “Sin vínculo SIREB” | `MapaGlobal.tsx` (223) + `types/mapaGlobal.ts` (36) | **acierto** pa’ B35/B70/B88/B92; **B131** falta pendiente |
| Subtítulo “Ocupación de todos los campos del sistema” | `MapaGlobal.tsx` (223) + `OcupacionService.php` (492) | **B130** label vs filtros reales |
| Pins verdes “Libre (0%)” en fecha 25/10 | `OcupacionService.php` (492) vs `MisCampos.tsx` (543) | **B131** posible desacuerdo con 17:00 Ocupada |
| Piscina/evento en mismo mapa porcentual | `OcupacionService.php` (492) + `PiscinaDisponibilidadService.php` (147) | **B132** denominador por modalidad |
| Click en pin/cluster sin popup visible | `MapaGlobal.tsx` (223) | **B133** mapa decorativo |
| Botón refresh junto a fecha | `MapaGlobal.tsx` (223) + syncs 305/415/202 | **B134** ambigüedad de sync |
| Atribución Leaflet | OpenStreetMap | **B28** confirmar librería/licencia, proteger |
| Contraste público | `MapaCampos.tsx` (151) | **B24/B25/B27** reutilizar admin pa’ público |

## Cierres / refinamientos cruzados con evidencia

**B24/B25/B27/B28 ✅ REFORZADOS — el admin tiene más mapa que el público.** Acá hay un cluster con “3” (típico `leaflet.markercluster`) y una leyenda de estados; en el público (`MapaCampos.tsx` 151) vimos pins encimados y sin popup claro. Si se confirma que 223 usa clustering y 151 no, el fix de B24 no es “agregar cluster al público desde cero”, es **reutilizar la config de `MapaGlobal.tsx` (223)** en `MapaCampos.tsx` (151). B28 se vuelve a confirmar: atribución “Leaflet | © OpenStreetMap contributors” = misma librería, coherente con ADR‑005, y la atribución es obligación de licencia a proteger.

**B35/B70/B88/B92 ✅ TIENEN MODELO DE SOLUCIÓN ACÁ — “Sin vínculo SIREB” como estado visible.** La leyenda incluye gris “Sin vínculo SIREB”. Eso es exactamente la señal que falta en `PiscinaDisponibilidadService` (147) gate silencioso, en ventanilla piscina (B70), en paramétricas (B87/B92) y en público (B30/B35). No afirmo que el mapa ya pinte correctamente los campos sin vínculo (puede estar confundido con el cluster, B129), pero **el vocabulario visual ya existe en admin**. Fix: propagar ese estado a 147/391/489/249/1022, no inventarlo.

**B60/B46/B22/P6 ✅ REFORZADO — la leyenda del mapa tampoco tiene “En cobro/pendiente”.** Ocupación grid (B60) solo tenía Libre/Ocupada; Dashboard (B46) ocultaba pendientes; ConsultarEstado (B22) mostraba pendiente infinito; acá el mapa tiene 5 estados pero **ninguno es pendiente/en cobro**. Entonces o el mapa ignora las solicitudes pendientes (mismo zombie invisible), o las cuenta como ocupadas/medias sin decirlo. Hay evidencia cruzada fuerte: en `MisCampos.tsx` (543) vimos “cancha nueva trinidad 17:00 Ocupada RSV‑…” el 25/10; este mapa con fecha 25/10 muestra pins verdes “Libre (0%)”. Si alguno de esos verdes es cancha nueva trinidad, hay desacuerdo. No lo afirmo sin popup/identificación, pero B131 es sospecha alta.

**B87/B50/B106/B115 ✅ MATIZADO — mapa agnóstico de modalidad está bien para ubicación, mal para ocupación porcentual.** Que piscina/evento/cancha aparezcan como puntos en un mapa es correcto (supervisión/ubicación agnóstica, como Asignaciones B123). El problema es si el **porcentaje de ocupación** se calcula igual para los tres: cancha = franjas horarias, piscina = cupos por perfil, evento = variantes/servicios. Si 492 usa un denominador genérico de “franjas”, B132 reproduce B50/B47/B62/B87 en el mapa. Si usa denominadores por modalidad, es acierto. Hay que verificar.

## Bugs nuevos de este frente (numeración continua: asignaciones cerró en B128)

**B129 🔴 (cluster/leyenda, React DevTools aplica) — el cluster gris/oscuro colisiona con “Sin vínculo SIREB” y oculta la mezcla de estados.** El mapa muestra un círculo oscuro con “3” junto a pins verdes. La leyenda usa gris para “Sin vínculo SIREB”. Dos riesgos: (a) si el cluster se pinta gris por estilo y no por estado, el funcionario puede leer “3 campos sin vínculo SIREB” cuando en realidad son 3 campos agrupados con estados distintos; (b) si el cluster sí representa un estado, está escondiendo si dentro hay Libre/Media/Alta/Mantenimiento/Sin vínculo. En cualquier caso, **agrupar 3 campos sin mostrar desglose pierde la información operativa del mapa**. Conecta B24/B25 (público), B60 (estados incompletos), B87 (modelos mezclados sin label).
*Mini‑prompt (verificar):* leer `MapaGlobal.tsx` (223) → ¿el icono del cluster usa color de estado o color neutro? ¿al hacer click hace spiderfy/popup con desglose? Fix: cluster con color neutro distinto del gris “Sin vínculo” y tooltip/popup que diga “3 campos: 1 libre, 1 media, 1 sin vínculo” o similar. No usar el mismo gris de leyenda para clusters.

**B130 (label/throughput, lente b) — “todos los campos del sistema” puede no ser todos.** El subtítulo promete todos, pero el mapa muestra cluster 3 + 2 pins = 5 ubicaciones visibles, mientras paramétricas tenía Total 9 / Activos 7 / Inactivos 2. Posibles explicaciones no bug: solo campos activos, solo campos con `ubicacion` sembrada (`UbicacionCampoSeeder` 59 / migr `add_ubicacion` 22), o varios campos compartiendo coordenadas. Pero si excluye inactivos o sin ubicación sin avisar, el label miente como B45/B58/B61/B90. No lo afirmo sin leer 223/492.
*Mini‑prompt (verificar):* leer `MapaGlobal.tsx` (223) + `OcupacionService.php` (492) → ¿filtra por estado activo? ¿por tener lat/long? ¿incluye inactivos/sin vínculo? Si excluye algo, cambiar subtítulo a “Campos activos con ubicación” o agregar chip “mostrar inactivos/sin ubicación”. Conecta B45/B58/B61/B90.

**B131 🔴 (pendientes invisibles / desacuerdo con ocupación) — la leyenda no tiene “En cobro/pendiente”, y puede contradecir la grilla de ocupación del mismo día.** La leyenda tiene Libre 0%, media <50%, alta ≥50%, mantenimiento, sin vínculo. Falta pendiente/en cobro. Evidencia cruzada: en `MisCampos.tsx` (543) la fecha 25/10 mostraba “cancha nueva trinidad 17:00 Ocupada RSV‑…”; este mapa, también 25/10, muestra pins verdes “Libre (0%)”. Si el pin verde es ese campo, hay contradicción; si no, igual falta saber si las pendientes cuentan. Conecta B60/B46/B22/P6/B62.
*Mini‑prompt (verificar):* leer `OcupacionService.php` (492) → ¿el porcentaje de ocupación incluye `pendiente`/`en_cobro` o solo `confirmada`? Y `MapaGlobal.tsx` (223) → ¿hay estado pendiente en el tipo? Fix: agregar “En cobro” a leyenda/popup o declarar “Ocupación = reservas confirmadas; pendientes se ven en Reservas”. No dejar pendiente invisible en el mapa de control.

**B132 (sospecha, paridad de modelo) — ocupación porcentual de piscina/evento podría usar denominador de cancha.** El mapa pinta “Ocupación media (<50%)” para todos los puntos. Cancha tiene sentido con franjas horarias; piscina tiene cupos por perfil (`PiscinaTurnoPerfil` 39, 147); evento tiene variantes/servicios (`ArancelVariante` 56, 297). Si 492 calcula “ocupación” como franjas ocupadas / franjas totales para piscina, reproduce B50/B47/B62/B87. Si calcula por perfiles/cupos o por evento, es acierto. No lo afirmo sin leer 492/223.
*Mini‑prompt (verificar):* leer `OcupacionService.php` (492) + `types/mapaGlobal.ts` (36) → ¿hay rama por `modalidad_reserva` para calcular porcentaje? Fix: denominador por modalidad; en popup mostrar “Piscina: 8/20 cupos” no “50% de franjas”. Conecta B43/B47/B50/B62/B87/B115.

**B133 (sospecha, mapa decorativo) — no se ve popup/tooltip accionable al pin/cluster.** El público tenía B25 (pin abierto sin popup). Acá, para admin, el popup no necesita CTA de reserva, pero sí necesita operación: nombre del campo, estado, ocupación %, reservas pendientes/confirmadas, sin vínculo SIREB, mantenimiento. Si click no muestra nada, el mapa solo ubica pero no deja controlar. Conecta B25/D22/B129.
*Mini‑prompt (verificar):* leer `MapaGlobal.tsx` (223) → ¿`<Marker>`/`<Popup>` o click handler? Si no, agregar popup operativo reutilizando datos de 492/36. Conecta S65.

**B134 (menor, sync ambiguo) — botón refresh sin label claro entre tres syncs distintos.** Junto a la fecha hay un icono de refrescar. En el dossier ya hay tres syncs: “Sincronizar tarifas”/catálogo (305/202), “Refrescar desde SIREB” de liquidación (415/106), y este refresh de mapa/ocupación. Si el icono no dice qué refresca, el funcionario puede creer que sincroniza SIREB cuando solo recarga ocupación, o viceversa. Conecta B90/B110/B84/S54.
*Mini‑prompt:* en 223, tooltip “Actualizar ocupación del día” (o lo que haga), y no confundir con sync SIREB. Si además debe refrescar vínculo SIREB, separar acciones.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Leyenda rica de 5 estados** (Libre/media/alta/mantenimiento/sin vínculo) = mucho más clara que ocupación grid (2 estados) y público (badge ambiguo). Proteger y propagar.
- **“Sin vínculo SIREB” como estado visible** = señal de transparencia que falta en B35/B70/B88/B92. Proteger (arreglando B129 para que no se confunda con cluster).
- **Cluster aparente con conteo “3”** = si se confirma, es el fix de B24 para público. Proteger y reutilizar en `MapaCampos.tsx` (151).
- **Atribución “Leaflet | © OpenStreetMap contributors”** = licencia OSM obligatoria, B28. Proteger, no quitar al mejorar.
- **Fecha + refresh** = control temporal del mapa; proteger, solo aclarar refresh (B134).
- **Subtítulo “Ocupación de todos los campos del sistema”** es más honesto que “campos asignados” de ocupación si realmente muestra todos; verificar B130, pero la intención de label explícito es acierto.

## Didáctica / operación del funcionario (continuo: asignaciones cerró en D70)

**D71 — el cluster debe desglosar estados, no solo contar campos** (cierra B129/B133): “3 campos: 1 libre, 1 media, 1 sin vínculo” o spiderfy con colores individuales.
*Mini‑prompt:* ver B129/B133.

**D72 — la leyenda debe incluir pendientes o declarar que no las cuenta** (cierra B131): el funcionario no puede usar un mapa de ocupación que esconde la cola de cobro.
*Mini‑prompt:* ver B131/S66.

**D73 — el porcentaje de ocupación debe significar lo mismo por modalidad** (cierra B132): cancha por franjas, piscina por cupos/perfiles, evento por reservas/servicios; o mostrar métricas distintas etiquetadas.
*Mini‑prompt:* ver B132/S66.

**D74 — el mapa público debe heredar el mapa admin, no al revés** (cierra B24/B25/B27 con evidencia): si 223 ya tiene cluster/leyenda/popup, 151 debe reutilizarlo.
*Mini‑prompt:* ver S65.

## Sugerencias IA acotadas (continuo: asignaciones cerró en S64)

**S65 — extraer el mapa admin (`MapaGlobal.tsx` 223) a componente compartido o al menos reutilizar su clustering/leyenda/popup en `MapaCampos.tsx` (151).** El público no necesita reserva desde mapa, pero sí necesita no encimar pins y entender estados. Hoy admin tiene más modelo que ciudadano.
*Mini‑prompt:* crear `components/mapa/MapaCamposBase.tsx` o importar config de cluster/leyenda de 223 en 151. Cierra B24/B25/B27/B133 de raíz.

**S66 — un token de estados de ocupación cross‑capa** consumido por mapa admin (223), ocupación grid (543), público (238/203/391), dashboard (134) y móvil. Incluya Libre/En cobro/Ocupada/Mantenimiento/Sin vínculo y, si aplica, Media/Alta por porcentaje. Conecta B60/B46/B22/B131/B132/S8/S28.
*Mini‑prompt:* definir en `types/mapaGlobal.ts` (36) o un `constants/estadosOcupacion.ts` y propagar.

**S67 — B134 se arregla con tooltips/labels de sync**: “Actualizar ocupación”, “Sincronizar tarifas SIREB”, “Refrescar liquidación SIREB”. Tres acciones, tres nombres. Conecta B90/B110/B84/S54.
*Mini‑prompt:* ver B134.

**S68 — B130 se cierra con un chip de filtro visible**: si el mapa excluye inactivos/sin ubicación, mostrar “Mostrando 7 activos con ubicación” o toggle “incluye inactivos”. Conecta B45/B58/B61/B90.
*Mini‑prompt:* ver B130.

## Lo que NO es bug (para que no persigas fantasmas)

- **Que haya cluster** = probable acierto, no bug; el bug es si colisiona con leyenda/oculta estados (B129).
- **Que la leyenda use porcentajes** = claro para ocupación; el problema es faltante pendiente (B131) y denominador por modalidad (B132), no los umbrales 0/<50/≥50 per se.
- **Que piscina/evento aparezcan en el mapa** = correcto para ubicación/supervisión; el problema es si el % se calcula mal (B132), no que estén.
- **Fecha 25/10/2026** = puede ser fecha de captura/selección; B44/B53/B110 siguen sospechas de timezone/sync, no afirmo bug solo por esto.
- **Atribución Leaflet/OSM** = obligatoria, no ruido.
- **Botón refresh** = acierto operativo; el bug es ambigüedad de label (B134), no su existencia.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir Mapa Global, lo que **más vale** es **B129** (cluster/leyenda), **B131** (pendientes invisibles/desacuerdo con ocupación) y **B132** (denominador por modalidad), y para eso necesito `MapaGlobal.tsx` (223) + `types/mapaGlobal.ts` (36) + `OcupacionService.php` (492) + `OcupacionController.php` (126), y para propagar al público `MapaCampos.tsx` (151). B130/B133/B134 se cierran con 223/492/36 + tooltips. Los aciertos (leyenda rica, cluster, atribución, refresh) **no se tejen**: se **protegen y propagan** (S65/S66). **S65/S66** son el fix de mayor rendimiento del tramo: reutilizar mapa admin en público + token de estados cross‑capa cierran B24/B25/B27/B60/B131/B132/B133 en un golpe.

## Guardado

```text
ADMIN/MAPA-GLOBAL (pages/ocupacion/MapaGlobal.tsx 223 + types/mapaGlobal.ts 36 +
  consumidores/proveedores OcupacionController 126 + OcupacionService 492 + contraste
  public MapaCampos.tsx 151 + UbicacionCampoSeeder 59 + migr add_ubicacion 22 +
  PiscinaDisponibilidadService 147 + ArancelVariante 56/297) — DOSSIER, NO tejí, sigo
  modo "ver todo corregir después". Numeración continua (asignaciones B125-B128/D68-D70/
  S61-S64 · mapa-global B129-B134/D71-D74/S65-S68 + refinamientos B24/B25/B27/B28,
  B35/B70/B88/B92, B60/B46/B22/P6, B87/B50/B106/B115).

REFINAMIENTOS CRUZADOS CON EVIDENCIA:
  B24/B25/B27/B28✅ reforzados: admin muestra cluster "3" + leyenda rica + atribución
    Leaflet/OSM. Si 223 usa clustering y 151 no, fix público = reutilizar 223, no inventar.
    B28 confirmado otra vez (licencia OSM proteger).
  B35/B70/B88/B92✅ tienen modelo de solución: leyenda "Sin vínculo SIREB" gris = señal
    transparente que falta en 147/391/489/249/1022. NO afirmo que ya pinte bien (B129),
    pero vocabulario visual existe en admin. Propagar, no inventar.
  B60/B46/B22/P6✅ reforzado: leyenda mapa tampoco tiene "En cobro/pendiente". Evidencia
    cruzada fuerte: MisCampos 543 mostraba cancha nueva trinidad 17:00 Ocupada RSV-... el
    25/10; MapaGlobal 25/10 muestra pins verdes Libre (0%). Si es mismo campo, contradicción;
    si no, igual falta saber si pendientes cuentan. Sospecha alta B131.
  B87/B50/B106/B115✅ matizado: mapa agnóstico de modalidad está bien para ubicación, pero
    ocupación % debe respetar cancha=franjas, piscina=cupos/perfiles, evento=variantes/
    servicios. Si 492 usa denominador genérico, reproduce B50/B47/B62/B87.

BUGS: B129🔴 cluster gris/oscuro con "3" colisiona con leyenda gris "Sin vínculo SIREB"
  y oculta mezcla de estados (libre/media/alta/mantenimiento/sin vínculo). React DevTools
  aplica: ¿cluster color por estado o estilo? ¿spiderfy/popup desglose? Fix: cluster neutro
  distinto + tooltip/popup "3 campos: 1 libre, 1 media, 1 sin vínculo". Conecta B24/B25/B60/B87.
  B130 label/throughput: subtítulo "todos los campos del sistema" vs cluster 3 + 2 pins = 5
  ubicaciones visibles, paramétricas Total 9/Activos 7/Inactivos 2. Puede filtrar inactivos
  o sin ubicación sin avisar. leer 223/492. Conecta B45/B58/B61/B90.
  B131🔴 leyenda sin "En cobro/pendiente": o ignora pendientes (zombie B60/B46/B22/P6) o las
  mezcla con ocupadas. Posible desacuerdo con ocupación grid mismo día/campo. leer 492/36.
  B132 sospecha paridad modelo: % ocupación piscina/evento podría usar denominador cancha.
  Cancha franjas, piscina cupos/perfiles (147/39), evento variantes/servicios (56/297).
  leer 492/223/36. Conecta B43/B47/B50/B62/B87/B115.
  B133 sospecha mapa decorativo: no se ve popup/tooltip accionable. Admin popup debe mostrar
  campo, estado, ocupación %, pendientes/confirmadas, sin vínculo, mantenimiento. Conecta B25/D22.
  B134 menor sync ambiguo: botón refresh sin label entre 3 syncs (tarifas 305/202, liquidación
  415/106, ocupación mapa). Tooltip "Actualizar ocupación del día". Conecta B90/B110/B84/S54.

ACIERTOS PROTEGER: leyenda rica 5 estados; "Sin vínculo SIREB" visible; cluster aparente con
  conteo; atribución Leaflet/OSM; fecha + refresh; subtítulo explícito "todos" (si B130 se
  verifica honesto). Proteger y propagar a público/otros frentes.

DIDÁCTICA/OP: D71 cluster desglosa estados no solo cuenta (B129/B133); D72 leyenda incluye
  pendientes o declara que no las cuenta (B131); D73 % ocupación significa lo mismo por
  modalidad o métricas etiquetadas (B132); D74 mapa público hereda mapa admin, no al revés
  (B24/B25/B27).

SUGERENCIAS: S65 extraer/reutilizar MapaGlobal 223 (cluster/leyenda/popup) en MapaCampos 151
  público (cierra B24/B25/B27/B133 raíz); S66 token estados ocupación cross-capa consumido
  por 223/543/238/203/391/134/móvil, incl. Libre/En cobro/Ocupada/Mantenimiento/Sin vínculo
  + Media/Alta (conecta B60/B46/B22/B131/B132/S8/S28); S67 tooltips/labels de sync: "Actualizar
  ocupación", "Sincronizar tarifas SIREB", "Refrescar liquidación SIREB" (B134/B90/B110/B84);
  S68 chip/filtro visible si mapa excluye inactivos/sin ubicación: "Mostrando 7 activos con
  ubicación" o toggle (B130/B45/B58/B61/B90).

NO-BUG: haber cluster=probable acierto (bug es colisión/oculta estados B129); leyenda % =
  claro (problema faltante pendiente B131 y denominador B132, no umbrales); piscina/evento en
  mapa = correcto ubicación (bug es % mal calculado B132); fecha 25/10=puede ser captura/
  selección (B44/B53/B110 sospechas, no afirmo); atribución Leaflet/OSM=obligatoria; botón
  refresh=acierto (bug label ambiguo B134).

KB HONESTA (defaults doc): SIREB aplica (leyenda Sin vínculo; refresh puede confundirse con
  sync SIREB). wa.me NO aplica (no WhatsApp acá; doc confirma fallback WhatsApp Web pero pesa
  en éxito/P8). Rolldown checks.circularDependency default false → si hubiera ciclo 223/36/
  servicios build no avisa, pero no buildié este turno y no mete imports nuevos visibles;
  bundlerTimings default true (pluginTimings deprecated cede) pero no es build, no aplica.
  React DevTools SÍ aplica Y es herramienta natural de B129/B133 (cluster/popup/color son
  estado/rama del componente mapa, se ve en árbol no copy).

PUERTA CORRIGIR YA: lo que vale es B129 (cluster/leyenda) + B131 (pendientes/desacuerdo) +
  B132 (denominador modalidad) → pido MapaGlobal 223 + types/mapaGlobal 36 + OcupacionService
  492 + OcupacionController 126 + MapaCampos 151 pa' propagar. B130/B133/B134 con 223/492/36 +
  tooltips. Aciertos (leyenda/cluster/attribution/refresh) NO se tejen: PROTEGER y PROPAGAR.
  S65/S66 = mayor rendimiento (reutilizar mapa admin en público + token estados cross-capa
  cierran B24/B25/B27/B60/B131/B132/B133 un golpe).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94/
  B103 + escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59 (app-sidebar
  206 + Funcionarios 881 + Asignaciones 579 + MapaGlobal 223, B48/B61/B122/B124/B128/B130
  caen acá; RoleMiddleware 59 + migr 22 siguen sin llegar como archivo/contenido) · A4 Pago
  576 (RecaudacionesApiClient 415 + webhook 97, B84/B134) · D2✅✅ CERRADO + RAÍZ 703/107/77
  (fuente B43/B37/B50/B62/B67/B71/B76/B84/B88/B99/B107/B115/B132) · P9/B61/B123 → Asignaciones
  579 VISTO (mecanismo cerrado, puente B123 + ramificación rol B128 + P9 pendiente 26/22) ·
  C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 + piscina_service 145;
  P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad 85; B43/B36/B42/B39/B44/B50/
  B62/B69/B70/B76/B78/B79/B84/B86/B87/B88/B94/B99/B101/B104/B106/B107/B110/B112/B113/B115/
  B118/B119/B120/B121/B122/B123/B124/B125/B127/B128/B129/B130/B131/B132/B133/B134 se propagan
  a móvil; S16/S38/S41/S45/S46/S50/S52/S53/S55/S56/S58/S60/S61/S62/S63/S65/S66/S67/S68 también) ·
  higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote +
  S33 test conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina =
  hueco P5; S30 reusa input B52; P7 reportes 245/135; P9 asignaciones 579/22/26 VISTO, mecanismo
  cerrado + B128 ramificación rol + P9 exclusividad pendiente 26/22 + 2º control; P1/P2 auth
  164/34/126, B120/B121 caen en auth/seguridad; P3 reencuadrada S8; P8 reencuadrada S16/S38;
  D11 NIT prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B134/
  D29-D74/S25-S68. PARAMÉTRICAS COMPLETAS + USUARIOS/ROLES/ASIGNACIONES + MAPA GLOBAL VISTOS.
PRÓXIMA NATURAL: Reportes.tsx 245 + ClientesFrecuentes.tsx 178 (P7, cierre reunión) o P5
  asistencia (AsistenciaController 56 + MarcarAsistenciaService 125, B86 hueco piscina) o
  lápiz editar turno (Fix3, 284) o bloque E mobile (Flutter, piscina real 260/145).
```

En una línea: `MapaGlobal.tsx` (223) **refina el frente de mapas con evidencia de que el admin tiene más modelo que el público** — cluster con “3” y leyenda de 5 estados incluyendo **Sin vínculo SIREB** — pero destapa **B129 🔴**, el cluster gris/oscuro colisionando con el gris de “Sin vínculo SIREB” y ocultando la mezcla de estados de los 3 agrupados; le siguen **B131 🔴** (leyenda sin “En cobro/pendiente”, con posible contradicción cruzada contra `MisCampos.tsx` 543 que mostraba 17:00 Ocupada el mismo 25/10), **B130** (subtítulo “todos los campos del sistema” vs 5 ubicaciones visibles frente a 7 activos/9 totales), **B132** (sospecha: % de ocupación de piscina/evento calculado con denominador de cancha), **B133** (sin popup accionable visible) y **B134** (refresh ambiguo entre tres syncs); la buena noticia es que **B35/B70/B88/B92 tienen acá un modelo de solución** (“Sin vínculo SIREB” visible) y **B24/B25/B27/B28** se refuerzan para reutilizar el mapa admin en el público. **No tejí**: si querés corregir, lo que vale es B129/B131/B132 y necesito `MapaGlobal.tsx` (223) + `types/mapaGlobal.ts` (36) + `OcupacionService.php` (492) + `OcupacionController.php` (126) + `MapaCampos.tsx` (151) para propagar, con **S65/S66** como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: `Reportes.tsx` 245 + `ClientesFrecuentes.tsx` 178 para P7, o P5 asistencia con `AsistenciaController` 56 + `MarcarAsistenciaService` 125, o el lápiz de editar turno para Fix3 con 284) o arranco con esos archivos.
