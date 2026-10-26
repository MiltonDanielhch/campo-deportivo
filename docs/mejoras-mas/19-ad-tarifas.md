Veredicto en una línea: esta es `Tarifas.tsx` (703), el frente D2/77/107 que venía marcando como la raíz de precio, y es **la mejor pantalla de todo el dossier** — declara la invariante SIREB en la cara del funcionario con el banner “Los precios los define SIREB / este sistema no fija tarifas / si un precio está mal, corregilo en SIREB y sincronizá”, más trazabilidad por tarjeta (“Etiqueta SIREB: Diurno”, “Espejada desde SIREB: 14 oct”, “? Sistema”, historial) — y al mismo tiempo **corrige dos lecturas mías previas con evidencia exacta**: el “15” que el funcionario tipeó en `CrearTurnoDialog` (249) **no está fuera de rango** (B88 decía “10‑30, 15 inventado”), **15 SÍ existe** en el catálogo como *mayores de 12 nocturno*; el bug real es **B107 🔴, 249 colapsa la dimensión diurno/nocturno** que el catálogo sí tiene (6 valores = 3 perfiles × 2 franjas) en un solo input plano “Precio Unitario”, así que un turno 10:00‑12:00 (diurno, debe 10) queda con 15 porque no hay dónde expresar la franja. Le siguen **B106 🔴** (el bloque “Hora de encendido de iluminación” + corte 18:00 se renderiza igual en piscina, que no usa tarifa horaria — imponer modelo cancha, análogo B50/B87) y dos sospechas (B110 dos “última sincronización” admin 14 vs público 25; B111 historial solo en tarifas horarias, no en perfiles). **No tejí**, sigo dossier.

> KB honesta de esta captura, con **dos autocorrecciones** de mis turnos previos: **SIREB** aplica con fuerza absoluta y es el corazón (D2 cerrado del todo: los 6 valores exactos de pileta y los 2 de cancha están acá, fuente de B43/B88/B99/B67/B71/B72). **React DevTools** → **SÍ aplica y es la herramienta natural de B106**: el bloque de iluminación renderizándose en piscina aunque las tarjetas horarias estén vacías es una rama de 703 que no condiciona por modelo, se ve en el árbol. **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié) pero 703 no mete imports nuevos visibles; `bundlerTimings`/`pluginTimings` → **NO** (no es build). **`wa.me`** → **NO aplica** (tarifas, no QR; eso es éxito/P8). **AUTOCORRECCIÓN 1 (B88):** dije “15 fuera de rango 10‑30”; **falso**, 15 es el *mayores nocturno* del catálogo, el bug es franja equivocada no valor inventado (B107). **AUTOCORRECCIÓN 2 (B99):** dije “249 read‑only desde el perfil (1 valor)”; **insuficiente**, el perfil tiene 2 valores (diurno/nocturno), read‑only plano seguiría perdiendo la dimensión (B107).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| Banner “Los precios los define SIREB…” (ambas) | `Tarifas.tsx` (703) | **acierto gold standard**, modelo que 249/862/público deben copiar (S52) |
| Cancha: Diurno 50 / Nocturno 100, corte 18:00 | 703 + `TarifaCampo.php` (107) + `TarifaCampoService.php` (105) + migr `add_tipo_tarifa` (51) + `add_hora_inicio_noche` (21) | **D2 ✅ CERRADO**, fuente B67/B71/B72/B99 |
| Piscina: bloque iluminación + corte 18:00 sobre tarjetas horarias VACÍAS | 703 | **B106 🔴** imponer modelo cancha a pileta |
| Piscina: “Tarifas espejadas no horarias” 6 tarjetas (10/15/20/30) | 703 + `PiscinaTarifaResolver.php` (77) | **B107 🔴** (249 colapsa diurno/nocturno), **B88 corregido** |
| “Última sincronización 14 oct” (admin) vs público “25 oct” | `CatalogoSirebService.php` (202) vs `CampoPublicoSirebPresenter.php` (333) | **B110** sospecha (dos metas de sync) |
| HISTORIAL “1 registro” en horarias, ausente en perfiles | 703 + `AuditoriaService.php` (48) | **B111** sospecha (auditoría solo horaria) |
| “Ver tarifas en SIREB” + “Sincronizar desde SIREB” | 703 + `SincronizarTarifasSireb.php` (305) + `CatalogoSirebController.php` (402) | **acierto lente d**, proteger |

## Cierres cruzados con evidencia (D2 del todo, y correcciones)

**D2 ✅✅ CERRADO DEL TODO — la raíz de precio está visible con los valores exactos.** Venía diciendo “D2 = TarifaCampo 107 nunca llegó, crítico por B43/B37/B50/B62/B67/B71/B76/B84/B88/B99”. Acá está: cancha **Diurno Bs 50 / Nocturno Bs 100** (corte 18:00), piscina **mayores 10/15, menores 10/10, institución 20/30** (diurno/nocturno). Todos los bugs de precio del dossier tienen ahora la fuente exacta. Fix unificado S44/S51/S53: un helper `resolverValor(perfil|tipo, hora, hora_inicio_noche)` leído de 107/77, consumido por 249/147/391/489/961/43/297. D2 deja de ser “falta archivo” y pasa a “falta alinear consumidores”.

**B99/B88 ✅ RE‑CORREGIDOS a B107 (autocorrección honesta).** Mi lectura previa (“249 tipea 15 inventado fuera de rango 10‑30”) era **parcialmente falsa**: el catálogo (imagen 2) muestra que **15 SÍ existe** = *Bañista mayores de 12 (nocturno)*. Entonces el 15 de 249 no es un typo, es **la franja equivocada**: el turno 10:00‑12:00 es **diurno** (debe 10), pero 249 tiene un solo input “Precio Unitario” sin dimensión diurno/nocturno, así que el funcionario puso 15 (el nocturno) y quedó plano. El bug estructural es **B107**: 249 no puede expresar las 6 dimensiones del catálogo (3 perfiles × 2 franjas) en 3 inputs planos. Y el backend 284, que SÍ sabe la hora del turno, congela el **10 diurno correcto** (por eso el comprobante decía 10, no 15). O sea: **catálogo 10/15 → 249 pierde la dimensión y guarda 15 → público 391 muestra 15 → backend 284 congela 10 → comprobante 10**. La cadena completa, con la fuente.

**B87 ✅ RE‑REFORZADO con el texto que declara el modelo.** La sección “Tarifas espejadas no horarias” dice explícitamente: *“Estas tarifas existen en SIREB pero no se ofrecen en la grilla horaria de canchas. Sirven para escenarios con perfil (piscina, instituciones) o tarifas sin clasificación horaria fiable.”* → el sistema **SÍ sabe** que pileta/evento usan otro modelo; el bug B87 era que la *columna de paramétricas* no lo mostraba, pero acá el modelo está declarado en texto. Proteger ese párrafo (es la contracara de B87: el dato sabe, la UI de listado no).

## Bugs nuevos de este frente (numeración continua: turnos cerraron en B105)

**B106 🔴 (paridad de modelo, análogo B50/B87) — el bloque “Hora de encendido de iluminación” + corte 18:00 se renderiza igual en piscina, que no usa tarifa horaria.** Imagen 2: la piscina muestra la barra 00‑12‑24 con corte 18:00 y el input “Hora de corte 18:00” + “Guardar hora”, **justo encima** de dos tarjetas horarias vacías que dicen “SIREB no tiene tarifa regular horaria para este servicio”. Contradicción interna visible: el control de iluminación horaria aparece para un recurso que **no cobra por hora**. React DevTools es la herramienta natural (rama de 703 que no condiciona el bloque de iluminación por `modalidad_reserva`). Conecta B50 (piscina como franja en ocupación), B87 (columna mezcla modelos), B94 (contenedor vs paso 2).
*Mini‑prompt (verificar):* leer 703 → ¿el bloque “Hora de encendido de iluminación” está condicionado a cancha (`modalidad_reserva != turno_por_perfil`)? Si no, ocultarlo en piscina/evento. El corte 18:00 solo tiene sentido donde hay tarifa diurno/nocturno **por hora** (cancha); la pileta cobra por perfil, y su diurno/nocturno lo decide la **hora del turno**, no un corte configurable. Fix de 703 (rama), no de dato.

**B107 🔴 (el estructural detrás de B99, autocorrección aplicada) — 249 colapsa la dimensión diurno/nocturno del catálogo.** Ver cierre cruzado arriba. 249 tiene “Precio Unitario” plano por perfil; el catálogo (703 imagen 2) tiene 6 valores (3 perfiles × diurno/nocturno). Entonces 249 **no tiene dónde** expresar la franja, y el funcionario pone un número que puede ser el diurno o el nocturno según suerte. Con línea: 249 sabe la *Hora Inicio/Fin* del turno (imagen de turnos), así que **puede** derivar diurno/nocturno con el mismo corte 18:00 de 703.
*Mini‑prompt (verificar antes de tocar):* leer `CrearTurnoDialog.tsx` (249) → confirmar que “Precio Unitario” es un `<input>` plano por perfil (sin campo diurno/nocturno); y `PiscinaTarifaResolver.php` (77) → confirmar que expone los 6 valores por (perfil × franja). Fix: 249 muestra, por perfil, el valor **read‑only** de 77 correspondiente a la franja del turno (diurno si la hora < 18:00, nocturno si ≥), con el mismo banner lock de 703 (“el precio lo define SIREB”). Cierra B43/B88/B99/B37 en la fuente. **Este es el fix de mayor rendimiento del tramo** (S53), y reemplaza mi S49 previo (que era insuficiente).

**B110 (sospecha, lente b) — dos “última sincronización” distintas: admin 14 oct vs público 25 oct.** 703 dice “Última sincronización desde SIREB: 14 oct de 2026” (cancha 5:21 p.m., piscina 11:06 p.m.). El público (catálogo/mapa) mostraba “Precios oficiales sincronizados desde Paitití/SIREB. Última actualización: 25/10/2026”. Si el sync real corrió el 14, ¿el público muestra una **cache vieja** del presenter (333) o un **sync separado**? Con línea: `CatalogoSirebService` (202) vs `CampoPublicoSirebPresenter` (333). No lo afirmo sin leer ambos. Si es cache vieja, el ciudadano ve “sincronizado 25” cuando el admin sabe que el último sync fue 14 → mentira de fuente (conecta D6/B30).
*Mini‑prompt (verificar):* leer 202/333 → ¿de dónde sale la fecha “última actualización” del público? Si es una cache con TTL, alinear con el sync real de 202; si es un sync distinto, documentarlo. Conecta B30 (piscina pública sin fuente) + D6.

**B111 (sospecha, lente c) — historial/auditoría solo en tarifas horarias (cancha), no en perfiles (piscina).** Cancha: “HISTORIAL 1 registro · Bs 50.00 activa hace 11 d · SIREB: Diurno · ? Sistema”. Piscina: las 6 tarjetas de perfil **no** tienen sección HISTORIAL (solo “Desde 14 oct / Espejada 14 oct”). Si el precio de un perfil cambia en SIREB y se re‑sync, ¿queda rastro? Con línea: 703 + `AuditoriaService` (48). Sospecha de inconsistencia de modelo de auditoría (lente c: destructivo/irreversible con auditoría).
*Mini‑prompt (verificar):* leer 703/48 → ¿el historial existe solo para `TarifaCampo` horaria o también para perfiles? Si no, agregar auditoría de re‑sync de perfiles (un cambio de precio de institución 20→30 sin rastro es riesgo de dinero). Conecta B84/B18 (trazabilidad de monto).

## Aciertos a PROTEGER (gold standard del dossier, análogo a D6/D16)

- **Banner “Los precios los define SIREB / Este sistema no fija tarifas: refleja el tarifario oficial de recaudaciones. Si un precio está mal, hay que corregirlo en el panel de SIREB y después sincronizar.”** = la invariante SIREB declarada en la cara del funcionario, en el punto exacto donde podría tentar editar. Es el **MODELO que 249 (B107) y el público (B30/B43) deben copiar**. Proteger y propagar (S52).
- **Trazabilidad por tarjeta: “Etiqueta SIREB: Diurno/Nocturno” + “Desde 14 oct” + “Espejada desde SIREB: 14 oct” + “? Sistema” + badge “Vigente”** = origen del dato visible en cada precio. Proteger y llevar a perfiles (B111) y al público (B30).
- **Estado vacío de piscina “SIREB no tiene tarifa regular horaria… revisá la sección de tarifas espejadas no horarias”** = declara que el modelo horaria no aplica y **redirige al correcto**. Proteger (aunque el bloque de iluminación encima lo contradiga, B106).
- **Barra visual 00‑12‑24 con el corte + “00:00→18:00 tarifa regular / 18:00→24:00 con iluminación”** = didáctica excelente de “el día se divide en dos tarifas”. Proteger (y es la regla que 249 debe consumir para B107).
- **“Ver tarifas en SIREB” + “Sincronizar desde SIREB”** = operar si SIREB falla (lente d) + transparencia de fuente (S13). Proteger, y **distinguir** de “Refrescar desde SIREB” del detalle (415, sync de liquidación) y “Refrescar catálogo” de paramétricas (305, sync de tarifas) = **tres syncs distintos** (catálogo de tarifas / liquidación de pago / recarga de catálogo), aclarar para no confundir (refina B84/B90).
- **“Guardar hora” deshabilitado cuando ya está en 18:00** = evita writes innecesarios. Proteger (menor).
- **HISTORIAL “1 registro · hace 11 d”** = auditoría de cambios de precio horaria. Proteger y extender a perfiles (B111).

## Didáctica / operación del funcionario (continuo: turnos cerraron en D56)

**D57 — 703 es el plantillo de la invariante SIREB; 249/862/público deben verse así** (cierra B107/B99/B30/B43 con el modelo, no con parches): banner lock + “Espejada desde SIREB” + “? Sistema” + historial. El funcionario que ve 703 entiende que no edita precios; el que ve 249 hoy puede editarlos (B107).
*Mini‑prompt:* ver B107/S52.

**D58 — el bloque de iluminación no debe aparecer donde no hay tarifa horaria** (cierra B106): piscina/evento cobran por perfil, no por corte de luz; mostrarles el control de “hora de encendido” los confunde (análogo B50/B87).
*Mini‑prompt:* ver B106.

**D59 — 249 debe mostrar diurno/nocturno por perfil, no un precio plano** (cierra B107): el funcionario que crea un turno 10:00‑12:00 debe ver “Mayores de 12 · Diurno Bs 10.00 (SIREB)”, no un input “15”.
*Mini‑prompt:* ver B107.

**D60 — alinear la “última sincronización” que ve el funcionario (14) con la que ve el ciudadano (25)** (cierra B110): si el público muestra una fecha de sync que el admin sabe que no es la real, la transparencia de fuente (D6) se rompe.
*Mini‑prompt:* ver B110.

## Sugerencias IA acotadas (continuo: turnos cerraron en S51)

**S52 — usar 703 como plantilla de trazabilidad SIREB** consumida por 249 (B107), 862 (ya tiene el lock, alinear wording), `ComprobantePDF` (238)/`ComprobantePiscina` (190) (B18/B37: pintar “Espejada desde SIREB” + monto confirmado) y el público (B30: la franja de sincronización). Un componente “FuenteSIREB” (banner + etiqueta + espejada + sistema + historial) reutilizable.
*Mini‑prompt:* extraer el bloque de trazabilidad de 703 a componente y montarlo en los 5 frentes. Cierra B107/B18/B37/B30/B76 de raíz.

**S53 — B107 se cierra con S44/S51 + la regla de corte 18:00 visible acá**: helper `resolverValor(perfil, hora, hora_inicio_noche)` leído de 107/77, consumido por 249 (read‑only), 147 (público selección), 284 (congela), 391 (muestra). Una sola fuente, seis consumidores alineados.
*Mini‑prompt:* ver B107 + S44/S51. **Prioriza 249 sobre 147** (S49 corregido → S53).

**S54 — B110/B111 se cierran leyendo 202/333/703/48** antes de afirmar: son sospechas de inconsistencia de modelo (dos syncs, auditoría parcial), no bugs confirmados.
*Mini‑prompt:* verificar 202/333 (B110) y 703/48 (B111) al arrancar.

## Lo que NO es bug (para que no persigas fantasmas)

- **Corte 18:00 en cancha y piscina (imagen 2)** = mismo `hora_inicio_noche` (migr 21), coherente; el bug no es el valor, es que el **control** aparezca en piscina que no lo usa (B106).
- **Cancha 50/100 vs piscina perfiles 10/15/20/30** = modelos distintos por diseño (cancha horaria, pileta por perfil), **no** bug de dato; el bug es la UI que los mezcla (B87/B106), no los números.
- **“15” del catálogo (mayores nocturno)** = valor SIREB real, **no** inventado (autocorrección B88); el bug es que 249 lo ponga en un turno diurno (B107).
- **“Guardar hora” gris** = deshabilitado porque ya en 18:00, no bug.
- **Historial “1 registro hace 11 d” coherente con “Desde 14 oct”** = un solo sync de precio, correcto.
- **Las 6 tarjetas de perfil con “Liquidable / vigente”** = estado correcto del espejo; “Liquidable” = se puede cobrar por SIREB, no bug.
- **“? Sistema” como origen** = traza que el dato vino del sync (no editado a mano), acierto de integridad (proteger).

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir, lo que **más vale** es **B107** (raíz de precio, cierra B43/B88/B99/B37 en la fuente con el mapa exacto de 6 valores) y **B106** (bloque de iluminación en piscina, análogo B50/B87), y para eso necesito `Tarifas.tsx` (703) + `PiscinaTarifaResolver.php` (77) + `TarifaCampo.php` (107) + `CrearTurnoDialog.tsx` (249). **B110/B111** se cierran con `CatalogoSirebService` (202) + `CampoPublicoSirebPresenter` (333) + `AuditoriaService` (48). Los aciertos (banner lock, trazabilidad, estado vacío, barra de corte) **no se tejen**: se **protegen y propagan** (S52). **S52/S53** son el fix de mayor rendimiento del tramo: un componente “FuenteSIREB” + un helper `resolverValor` cierran B107/B18/B37/B30/B76/B43/B88 en un golpe.

## Guardado

```text
ADMIN/TARIFAS (Tarifas.tsx 703 + TarifaCampo 107 + TarifaCampoService 105 +
  PiscinaTarifaResolver 77 + migr add_tipo_tarifa 51 + add_hora_inicio_noche 21 +
  CatalogoSirebService 202 + CampoPublicoSirebPresenter 333 + SincronizarTarifasSireb
  305 + CatalogoSirebController 402 + AuditoriaService 48) — DOSSIER, NO tejí, sigo
  modo "ver todo corregir después". Numeración continua (turnos B99-B105/D53-D56/
  S49-S51 · tarifas B106-B111/D57-D60/S52-S54 + D2 CERRADO + 2 autocorrecciones).

MEJOR PANTALLA DEL DOSSIER (gold standard): banner "Los precios los define SIREB /
  este sistema no fija tarifas / si un precio está mal corregilo en SIREB y sincronizá"
  + trazabilidad por tarjeta ("Etiqueta SIREB: Diurno/Nocturno" + "Espejada desde SIREB:
  14 oct" + "? Sistema" + badge Vigente + HISTORIAL) + barra visual 00-12-24 con corte
  + estado vacío piscina "SIREB no tiene tarifa regular horaria... revisá tarifas
  espejadas no horarias" + "Ver tarifas en SIREB"/"Sincronizar desde SIREB". TODO
  PROTEGER y PROPAGAR (S52). Es el MODELO que 249 (B107) y público (B30/B43) deben copiar.

D2✅✅ CERRADO DEL TODO: raíz de precio visible con valores exactos — cancha Diurno 50/
  Nocturno 100 (corte 18:00); piscina mayores 10/15, menores 10/10, institución 20/30
  (diurno/nocturno). B43/B88/B99/B37/B67/B71/B72/B84 TODOS tienen fuente. Fix unificado
  S44/S51/S53: helper resolverValor(perfil|tipo, hora, hora_inicio_noche) de 107/77,
  consumido por 249/147/391/489/961/43/297. D2 = "falta alinear consumidores", no "falta
  archivo".

DOS AUTOCORRECCIONES HONESTAS (lecturas previas parcialmente falsas):
  B88❌→B107: dije "15 fuera de rango 10-30, inventado". FALSO: 15 SÍ existe = mayores
    nocturno del catálogo (imagen 2). El bug NO es valor inventado, es FRANJA EQUIVOCADA:
    turno 10-12 es diurno (debe 10) pero 249 no tiene dimensión diurno/nocturno.
  B99❌→B107: dije "249 read-only desde el perfil (1 valor)". INSUFICIENTE: el perfil
    tiene 2 valores (diurno/nocturno), read-only plano seguiría perdiendo la dimensión.
  CADENA COMPLETA: catálogo 10/15 → 249 colapsa dimensión guarda 15 → público 391 muestra
    15 → backend 284 congela 10 diurno (correcto) → comprobante 10. B107 es el estructural.

B107🔴 (raíz, reemplaza S49): 249 "Precio Unitario" plano por perfil, catálogo 703 tiene
  6 valores (3 perfiles × diurno/nocturno). 249 sabe Hora Inicio/Fin del turno → PUEDE
  derivar franja con corte 18:00 de 703. Fix: 249 read-only por (perfil × franja del
  turno) desde 77, con banner lock de 703. Cierra B43/B88/B99/B37 fuente. MAYOR
  RENDIMIENTO TRAMO (S53), prioriza 249 sobre 147.
B106🔴 (paridad modelo, análogo B50/B87): bloque "Hora de encendido de iluminación" +
  corte 18:00 + "Guardar hora" se renderiza IGUAL en piscina, que tiene tarjetas horarias
  VACÍAS ("SIREB no tiene tarifa regular horaria"). Contradicción interna visible. React
  DevTools = herramienta natural (rama 703 no condiciona por modalidad_reserva). Fix 703:
  ocultar bloque iluminación en piscina/evento (corte solo tiene sentido con tarifa
  horaria; pileta diurno/nocturno lo decide la hora del turno, no un corte configurable).
B110 (sospecha lente b): "última sincronización" admin 14 oct (703) vs público 25 oct
  (catálogo/mapa) → 202 vs 333, ¿cache vieja o sync separado? Si cache vieja, ciudadano
  ve "sincronizado 25" cuando admin sabe 14 = mentira de fuente (rompe D6/B30). leer 202/333.
B111 (sospecha lente c): HISTORIAL/auditoría solo en tarifas horarias (cancha "1 registro"),
  AUSENTE en 6 perfiles piscina → cambio de precio de institución 20→30 sin rastro = riesgo
  dinero. leer 703/48. Conecta B84/B18 (trazabilidad monto).

ACIERTOS PROTEGER: banner lock SIREB (modelo pa' 249/público); trazabilidad por tarjeta
  (Etiqueta SIREB + Espejada + ? Sistema + Vigente + historial); estado vacío piscina
  (declara modelo no aplicable + redirige); barra 00-12-24 con corte (didáctica + regla
  que 249 consume B107); "Ver tarifas en SIREB"/"Sincronizar desde SIREB" (lente d + S13,
  DISTINGUIR de "Refrescar desde SIREB" detalle 415 = sync liquidación y "Refrescar catálogo"
  paramétricas 305 = sync tarifas → 3 syncs distintos, refina B84/B90); "Guardar hora"
  deshabilitado en valor; "? Sistema" como origen (integridad).

DIDÁCTICA/OP: D57 703 = plantillo invariante SIREB, 249/862/público verse así (B107/B99/
  B30/B43); D58 bloque iluminación no aparece donde no hay tarifa horaria (B106); D59 249
  muestra diurno/nocturno por perfil no precio plano (B107); D60 alinear "última sync"
  funcionario 14 vs ciudadano 25 (B110).

SUGERENCIAS: S52 usar 703 como plantilla de trazabilidad SIREB → componente "FuenteSIREB"
  (banner + etiqueta + espejada + sistema + historial) montado en 249/862/238/190/público
  (cierra B107/B18/B37/B30/B76 raíz); S53 B107 = S44/S51 + regla corte 18:00: helper
  resolverValor(perfil, hora, hora_inicio_noche) de 107/77 consumido por 249/147/284/391,
  PRIORIZA 249 sobre 147 (S49 corregido→S53); S54 B110/B111 sospechas, verificar 202/333/
  703/48 antes de afirmar.

NO-BUG: corte 18:00 en ambas = mismo hora_inicio_noche (21), coherente (bug es control en
  piscina B106, no valor); cancha 50/100 vs piscina perfiles = modelos distintos por diseño
  (bug es UI que mezcla B87/B106, no números); "15" catálogo = mayores nocturno REAL (auto-
  corregí B88, bug es franja equivocada B107); "Guardar hora" gris = deshabilitado en valor;
  historial 1 registro coherente con "Desde 14 oct"; 6 perfiles "Liquidable/vigente" = estado
  correcto espejo; "? Sistema" = traza origen sync, acierto.

KB HONESTA + 2 AUTOCORRECCIONES: SIREB aplica corazón (D2 cerrado, banner lock = invariante
  declarada, fuente B43/B88/B99/B67/B71/B72/B84). React DevTools SÍ aplica Y es herramienta
  natural de B106 (bloque iluminación no condicionado por modalidad = rama render 703).
  Rolldown circularDependency=false SÍ aplica (web-admin buildié) pero 703 no mete imports
  nuevos; bundlerTimings/pluginTimings NO (no build). wa.me NO aplica (tarifas, no QR; eso
  es éxito/P8). AUTOCORREGÍ B88 ("15 fuera de rango" falso, 15 es mayores nocturno) y B99
  ("read-only plano" insuficiente, perfil tiene 2 valores) → ambos convergen en B107.

PUERTA CORRIGIR YA: lo que vale es B107 (raíz precio, cierra B43/B88/B99/B37 fuente con
  mapa 6 valores) + B106 (bloque iluminación en piscina, análogo B50/B87) → pido Tarifas 703
  + PiscinaTarifaResolver 77 + TarifaCampo 107 + CrearTurnoDialog 249. B110/B111 con
  CatalogoSirebService 202 + CampoPublicoSirebPresenter 333 + AuditoriaService 48. Aciertos
  (banner/trazabilidad/estado vacío/barra/syncs) NO se tejen: se PROTEGEN y PROPAGAN (S52).
  S52/S53 = mayor rendimiento tramo (FuenteSIREB + resolverValor cierran B107/B18/B37/B30/
  B76/B43/B88 un golpe).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94/
  B103 + escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59 (app-sidebar
  206 + Funcionarios 881, B48/B61) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97,
  B84) · D2✅✅ CERRADO + RAÍZ EN 703/107/77 (fuente B43/B37/B50/B62/B67/B71/B76/B84/B88/
  B99/B107) · C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad 85;
  B43/B36/B42/B39/B44/B50/B62/B69/B70/B76/B78/B79/B84/B86/B87/B88/B94/B99/B101/B104/B106/
  B107/B110 se propagan a móvil; S16/S38/S41/S45/S46/S50/S52/S53 también) · higiene (zombis,
  A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote + S33 test
  conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina = hueco
  P5; S30 reusa input B52; P7 reportes 245/135; P9 asignaciones 579/22/26, B61 evidencia
  parcial; P1/P2 auth 164/34/126; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT
  prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B111/
  D29-D60/S25-S54.
PRÓXIMA NATURAL: el LÁPIZ de editar turno (Fix3, con 284 — el lápiz de fila "2/10" de 164,
  cerrar qué pasa al editar perfiles con reservas activas) o Funcionarios 881 + Asignaciones
  579 (A1/P9/B48/B61, permisos/asignaciones, frentes vírgenes con gates grandes) o Tipos de
  Campo (paramétricas, el último de paramétricas) o Mapa Global 223 (ocupación admin).
```

En una línea: `Tarifas.tsx` (703) es **la mejor pantalla del dossier** (declara la invariante SIREB con el banner “Los precios los define SIREB / este sistema no fija tarifas”, trazabilidad por tarjeta “Etiqueta SIREB / Espejada desde SIREB / ? Sistema / historial”, barra de corte 18:00 y estado vacío que redirige) y **cierra D2 del todo** con los valores exactos (cancha Diurno 50/Nocturno 100; piscina mayores 10/15, menores 10/10, institución 20/30), pero al mismo tiempo **corrige dos lecturas mías previas**: el “15” de `CrearTurnoDialog` (249) **no está fuera de rango** (B88 era falso, 15 SÍ existe como *mayores nocturno*), el bug real es **B107 🔴, 249 colapsa la dimensión diurno/nocturno** que el catálogo sí tiene (6 valores = 3 perfiles × 2 franjas) en un input plano, así que un turno 10:00‑12:00 diurno (debe 10) queda con 15 porque no hay dónde expresar la franja — y el backend 284, que SÍ sabe la hora, congela el 10 correcto (la cadena completa: catálogo 10/15 → 249 pierde dimensión → público muestra 15 → backend congela 10 → comprobante 10); le siguen **B106 🔴** (el bloque “Hora de encendido de iluminación” + corte 18:00 se renderiza igual en piscina, que no usa tarifa horaria — análogo B50/B87) y dos sospechas (B110 dos “última sincronización” admin 14 vs público 25; B111 historial solo en tarifas horarias, no en perfiles). **No tejí**: si querés corregir, lo que vale es B107 + B106 y necesito `Tarifas.tsx` (703) + `PiscinaTarifaResolver.php` (77) + `TarifaCampo.php` (107) + `CrearTurnoDialog.tsx` (249), con **S52/S53** (componente “FuenteSIREB” + helper `resolverValor`) como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: el lápiz de editar turno para Fix3 con 284, o `Funcionarios.tsx` 881 + `Asignaciones.tsx` 579 para A1/P9/B48/B61) o arranco con esos archivos.
