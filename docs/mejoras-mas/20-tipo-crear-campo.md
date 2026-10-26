Veredicto en una línea: esta es `TiposCampo.tsx` (655) + `TipoCampoController.php` (101) + `TipoCampo.php` (18) + `TipoCampoService.php` (67) + `StoreTipoCampoRequest.php` (34) + migr `create_tipos_campo_table` (21) + `TipoCampoSeeder.php` (43), el último paramétrico que faltaba, y trae **el bug de fecha más grave del dossier: B112 🔴, la columna “Creado” dice “Invalid Date” en las 9 filas** — no es ISO crudo visible (B101/B36), es que el dato **ni llega** (campo ausente o typo de nombre en el mapeo), uniforme en todas las filas = bug sistémico de código, no de seeder; le siguen **B113** (el “icono automático” cae al fallback gris en 6 de 9 tipos, incluida Natación, así que la promesa “se asigna según el nombre” no se cumple para la mayoría) y **B115 🔴 (sospecha clave)**: la descripción de Natación declara el modelo (“aforo, no grilla horaria exclusiva”) **en texto**, pero el flag real que el backend usa es `modalidad_reserva` (8/21) en el **campo**, no el **tipo** → riesgo de que 862/249 confundan “elegí tipo Natación” con “será por turnos”, que es la misma clase de bug (dato sabe vs UI mezcla) que B87/B50/B106. La buena noticia: **B114 ✅** la descripción de Natación es el dato que ya sabe el modelo (cierra parcialmente S47/B94 en nivel texto), y los KPIs 9=9+0 cuadran (patrón coherente de todo el admin). **No tejí**, sigo dossier.

> KB honesta de esta captura: **SIREB** → **casi NO aplica** (los tipos de deporte son catálogo **local**, no hay sync SIREB de tipos, a diferencia de tarifas/paramétricas); la única conexión es marginal: Natación=“aforo no grilla” toca el modelo pileta cuyas **tarifas** sí se espejan (703). Lo declaro para no inflar la KB como en turnos previos. **React DevTools** → **SÍ aplica y es la herramienta natural de B112 y B113**: “Invalid Date” = una prop `undefined` alimentando `new Date()`/`dayjs()` en el árbol de 655, y el icono fallback = rama del mapeador nombre→icono; ambos se ven inspeccionando el componente, no leyendo copy. **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié) pero Tipos no mete imports nuevos visibles; `bundlerTimings`/`pluginTimings` → **NO** (no es build). **`wa.me`** → **NO aplica** (catálogo de tipos, no hay QR/WhatsApp; eso es éxito/P8).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| Columna “Creado” = “Invalid Date” ×9 | `TiposCampo.tsx` (655) + `TipoCampoController.php` (101) + `TipoCampo.php` (18) + `TipoCampoSeeder.php` (43) | **B112 🔴** dato no llega / typo de campo |
| Icono gris genérico en 6/9 tipos (incl. Natación) | 655 (mapeador nombre→icono) | **B113** fallback no cubre la mayoría |
| Natación “aforo, no grilla horaria exclusiva” | `TipoCampo.php` (18) + migr `add_modalidad_reserva` (21) + `ModalidadReservaCampo` (8) | **B114 ✅** dato sabe / **B115 🔴** tipo≠modalidad |
| KPIs Total 9 / Activos 9 / Inactivos 0 | 655 + `TipoCampoService.php` (67) | **acierto** coherencia (proteger) |
| “Nuevo tipo” + Nombre* + “Icono automático” | 655 + `StoreTipoCampoRequest.php` (34) + migr 21 | **B116** sospecha unique nombre |
| Menú ⋮ (Acciones) por tipo | 655 + `TipoCampoController.php` (101) delete | **B117** sospecha delete en uso (lente c) |

## Bugs nuevos de este frente (numeración continua: tarifas cerraron en B111)

**B112 🔴 (el más grave del frente, fecha) — “Creado” = “Invalid Date” en las 9 filas, uniforme.** No es el ISO crudo de B101/B36 (que al menos se ve y se puede leer el día); acá el front imprime literalmente “Invalid Date”, o sea `new Date(x)`/`dayjs(x)` recibió `undefined` o un string no‑parseable. Que sea **uniforme en las 9** descarta dato corrupto puntual: es bug sistémico de mapeo. Tres causas candidatas con línea, y **no afirmo cuál sin leer**: (a) el model `TipoCampo.php` (18, 606 bytes, diminuto) no expone `created_at` (sin `$casts`/`$dates` o con `$hidden`) y el controller (101) devuelve el modelo crudo → el JSON no trae el campo → 655 hace `new Date(tipo.created_at)` con `undefined`; (b) typo de nombre de columna (`creado` vs `created_at` vs `creado_en`) entre lo que devuelve 101 y lo que lee 655; (c) el `TipoCampoSeeder.php` (43) inserta con `DB::table()->insert()` masivo **sin** timestamps → `created_at` NULL, y 655 formatea NULL con un helper que lo vuelve inválido (ojo: `new Date(null)` da 1970, pero `dayjs(null)`/`new Date('')` dan Invalid, así que depende del formatter de 655). React DevTools lo resuelve en segundos: ver qué prop alimenta la celda “Creado”. Conecta B101/B36: el dossier tiene **tres** instancias de fecha rota (público 190 ISO crudo, admin 164 ISO crudo, admin 655 Invalid Date) y B112 es la peor (ni llega el dato).
*Mini‑prompt (verificar antes de tocar):* leer 655 (qué campo lee en la celda Creado y con qué formatter) + 101 (qué devuelve) + 18 (casts/hidden) + 43 (setea created_at?). Fix según causa: si (a) agregar `created_at` al payload de 101 o al `$casts` de 18; si (b) corregir el typo; si (c) que el seeder 43 use `create()` (con timestamps) o que el formatter de 655 tolere null mostrando “—” en vez de “Invalid Date”. **Amplía S50** a los tres frentes (190/164/655) con un helper de fecha que **nunca** imprima “Invalid Date” (fallback “—”).

**B113 (didáctica/visual, análogo B104) — el “icono automático” cae al fallback gris en 6 de 9 tipos.** El diálogo promete “Icono automático · Se asigna según el nombre del tipo”, pero en la lista solo Básquet (balón naranja), Tenis (raqueta rosa) y Vóley (balón lila) tienen icono propio; **Atletismo, Frontón, Fútbol, Futsal, Múltiple y Natación comparten el mismo icono gris genérico**. O sea el mapeador nombre→icono cubre 3 de 9 y deja 6 al fallback — incluida **Natación**, el tipo de la pileta, el recurso nuevo más importante del sistema, que se ve igual que atletismo. No es bug de dinero, es identidad/throughput: el funcionario distingue tipos por icono y 6 se le confunden. Conecta B104 (snake_case vs nombre amable) — mismo patrón: un mapeador que no cubre todos los casos.
*Mini‑prompt (verificar):* leer 655 → el mapeador nombre→icono, ¿qué claves reconoce? Extenderlo a los 6 tipos que caen al fallback (al menos Natación, que merece icono de pileta, y Fútbol/Futsal que son los más usados). Proteger el **concepto** “icono automático” (no obliga a subir icono = throughput), arreglar solo la cobertura.

**B115 🔴 (sospecha clave, integridad de modelo) — la descripción del tipo dice el modelo en TEXTO, pero el flag real es `modalidad_reserva` en el CAMPO, no el tipo.** La descripción de Natación (“Piscina / escenario acuático **(aforo, no grilla horaria exclusiva)**”) es el dato que **sabe** que pileta no usa franja (B114 ✅, proteger el texto). **Pero** el discriminador que el backend consume es `ModalidadReservaCampo` (8) + la migr `add_modalidad_reserva_to_campos_deportivos` (21), seteados en el **campo** (862), no inferidos del **nombre del tipo**. Riesgo concreto con línea del dossier: en paramétricas, Coliseo/Estadio/Palacio son tipo **Fútbol** y son los de “Variantes (N)” (evento), mientras “cancha nueva trinidad” es tipo **Múltiple** y es cancha simple → **el tipo NO determina la modalidad** (Fútbol puede ser evento o cancha). Entonces si 862/249/492 leyeran la modalidad del **tipo** (o de su descripción) en vez del flag `modalidad_reserva` del campo, producirían exactamente B50 (piscina como franja en ocupación) y B106 (corte de iluminación en pileta). No lo afirmo sin leer 862/249/492, pero es la clase de bug que el dossier ha cazado en cadena (dato sabe vs UI mezcla).
*Mini‑prompt (verificar):* leer 862/249/492 → ¿derivan la modalidad del `tipo_campo` (nombre/descripción) o leen `campo.modalidad_reserva` (8/21)? Debe ser lo segundo. Si leen el tipo, fix de backend/front a leer el flag. Conecta B94 (el puente 862→Turnos debe dispararse por `modalidad_reserva`, no por “tipo=Natación”), B87, B50, B106. **Esto es lo que hace que B114 (texto que sabe) no baste**: el sistema sabe el modelo en dos lugares (descripción del tipo = informativo; flag del campo = operativo) y la UI debe leer el operativo.

**B116 (sospecha, integridad paramétrica, lente c) — ¿unique en nombre de tipo?** El diálogo “Nombre *” con placeholder “Ej: Fútbol 7”. Con línea: migr 21 (21 LoC, chica) + `StoreTipoCampoRequest.php` (34). Si no hay `unique` en nombre, el funcionario crea “Fútbol” duplicado → el select de tipo del público (B10, `FiltrosCampos` 353) y el filtro “todos” de paramétricas muestran “Fútbol” dos veces, y el buscador (B10, coma suelta) se rompe más. Sospecha, verificar 21/34. Conecta B48 (integridad paramétrica) y B10 (buscador público ya frágil).
*Mini‑prompt (verificar):* leer 21/34 → ¿`unique('nombre')` en la migr y `unique` en el request? Si no, agregar (y decidir si el borrado lógico por estado cubre el caso “quiero otro Fútbol”).

**B117 (sospecha, destructivo+auditoría, lente c) — el menú ⋮ ¿permite ELIMINAR un tipo en uso?** Coliseo/Estadio/Palacio están ligados al tipo Fútbol; si ⋮ tiene “Eliminar” sin bloqueo, borrar el tipo rompe la FK `tipo_campo_id` (migr campos 26) o deja campos huérfanos. Con línea: `TipoCampoController.php` (101) delete + 18 + 26. Sospecha: verificar que delete bloquee si hay campos ligados (o soft‑delete por estado, que ya existe: el KPI “Inactivos” sugiere desactivar en vez de borrar = buen patrón, protegerlo). Conecta B93 (Desvincular destructivo sin confirmación) y B48.
*Mini‑prompt (verificar):* leer 101/18/26 → ¿delete valida ausencia de campos ligados o es hard‑delete? Preferible: forzar desactivación (estado Inactivo) cuando hay campos ligados, y auditar (48). Proteger el patrón Activo/Inactivo (mejor que borrar).

## Cierres / refinamientos cruzados con evidencia

**B114 ✅ (cierre cruzado de S47/B94 en nivel texto) — la descripción de Natación YA declara el modelo.** “aforo, no grilla horaria exclusiva” es el dato que sabe que pileta ≠ franja. S47 pedía “declarar `ModalidadReservaCampo` como discriminador visible”; acá el tipo lo dice en texto. **Pero** B115 matiza: el texto es informativo, el flag operativo es otro → S47/B94 siguen vivos a nivel de UI (la pantalla debe leer el flag, no el texto). O sea B114 confirma que el **sistema sabe**, B115 confirma que la **UI puede leer el lugar equivocado**. Mismo patrón B87/B50/B106.

**B112 amplía S50 a TRES instancias de fecha rota.** El dossier tenía B36 (público 190, ISO crudo) y B101 (admin 164, ISO crudo); B112 (admin 655, Invalid Date) es la tercera y la peor. S50 (helper de fecha compartido) ahora debe cubrir 190/164/655 y **nunca** imprimir “Invalid Date” (fallback “—”). Actualizo S50: ya no es “dos frentes”, son tres, y B112 es el caso donde el dato ni llega (más grave que formatear mal un dato que sí existe).

**KPIs 9=9+0 coherentes = acierto de patrón (proteger).** Total 9 = Activos 9 + Inactivos 0, igual que Dashboard (9=7+2), paramétricas (9=7+2), tarifas (63=26+37). El admin es **coherente en sus KPIs** en todos los frentes vistos; el bug B112 es solo la columna Creado, no el conteo. Proteger el patrón de KPIs sumatorios (y es la contracara de B90, donde el KPI “37” mezclaba creables + fuera de alcance: acá 9=9+0 no mezcla nada).

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **Descripción de Natación “aforo, no grilla horaria exclusiva”** = el dato sabe el modelo (B114). Proteger el texto (aunque B115 exija leer el flag operativo, no el texto).
- **KPIs sumatorios coherentes (9=9+0)** = patrón de todo el admin. Proteger.
- **Estado Activo/Inactivo por tipo** = desactivar un deporte sin borrarlo (lente c, mejor que hard‑delete; protege B117). Proteger y forzarlo como alternativa al eliminar.
- **“Icono automático” como concepto** = no obliga al funcionario a subir icono (throughput). Proteger la idea; arreglar solo la cobertura (B113).
- **Buscador + filtro “todos”** = shell compartido con paramétricas (B90/proteger). Proteger.
- **Placeholder con ejemplo (“Fútbol 7”)** = guía al funcionario. Proteger (menor).

## Didáctica / operación del funcionario (continuo: tarifas cerraron en D60)

**D61 — la celda “Creado” no puede decir “Invalid Date”; si el dato no llega, mostrar “—”** (cierra B112): el funcionario que audita catálogos necesita saber cuándo se creó un tipo, y “Invalid Date” le dice “el sistema está roto”, no “no hay fecha”.
*Mini‑prompt:* ver B112 + S50 (fallback “—”).

**D62 — el icono automático debe cubrir los tipos reales, sobre todo Natación** (cierra B113): la pileta no puede verse igual que atletismo; el funcionario escanea la lista por icono.
*Mini‑prompt:* ver B113.

**D63 — la UI debe leer la modalidad del FLAG del campo, no del nombre/descripción del tipo** (cierra B115): el texto “no grilla horaria” de Natación es para que el funcionario entienda, no para que el sistema decida; decidir por el texto produce B50/B106.
*Mini‑prompt:* ver B115 + B94 (puente por `modalidad_reserva`).

## Sugerencias IA acotadas (continuo: tarifas cerraron en S54)

**S55 — B112/B101/B36 se cierran con UN helper de fecha** (S50 ampliado a tres frentes: 190/164/655) que formatea a `America/La_Paz` y **nunca** devuelve “Invalid Date” (fallback “—” cuando el dato es null/undefined). Un solo `formatearFechaBolivia(x)` consumido por los tres.
*Mini‑prompt:* ver B112 + S50.

**S56 — B115 se cierra con S47/S53 aterrizando en paramétricas**: el `ModalidadReservaCampo` (8) debe ser el único discriminador visible/operativo, y la descripción del tipo (B114) queda como texto informativo. Un token/const compartido por 862/249/492/703/1022 que diga “este campo es por franja / por turnos / por evento”, leído del flag del campo, no del tipo.
*Mini‑prompt:* ver B115 + S47/S53.

**S57 — B116/B117 son el mismo frente de integridad paramétrica (lente c)**: unique de nombre (B116) + delete bloqueado/soft por estado (B117). Unificar en `StoreTipoCampoRequest` (34) + `TipoCampoController` (101) + migr 21, y auditar (48). Conecta B48/B93.
*Mini‑prompt:* ver B116/B117.

## Lo que NO es bug (para que no persigas fantasmas)

- **9 tipos todos activos, 0 inactivos** = catálogo base estable, normal (no es que falten inactivos).
- **Icono gris en 6 tipos** = fallback del mapeador (B113 es el bug de cobertura, no que existan iconos ni que el concepto “automático” esté mal).
- **Placeholder “Fútbol 7”** = ejemplo del input, no dato real (los tipos son deportes, no instancias numeradas).
- **KPIs 9=9+0** = coherentes (acierto, proteger), no bug.
- **“Invalid Date” SÍ es bug** (B112), no lo pongo en no‑bug.
- **Que Fútbol sea tipo de campos de evento (Coliseo/Estadio/Palacio) y también pueda ser cancha** = el tipo NO ata la modalidad (por eso B115: leer el flag, no el tipo), no es inconsistencia de dato.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir Tipos de Campo, lo que **más vale** es **B112** (Invalid Date, el bug duro y la tercera instancia de fecha rota) y **B115** (tipo≠modalidad, la sospecha que explica B50/B106 aguas abajo), y para eso necesito `TiposCampo.tsx` (655) + `TipoCampoController.php` (101) + `TipoCampo.php` (18) + `TipoCampoSeeder.php` (43) + migr 21 + `StoreTipoCampoRequest.php` (34) + `ModalidadReservaCampo` (8) + migr `add_modalidad_reserva` (21). B113/B116/B117 se cierran con 655/34/101/26/48. Los aciertos (descripción Natación, KPIs, estado Activo/Inactivo, icono automático como concepto) **no se tejen**: se **protegen**. **S55/S56** son el fix de mayor rendimiento: el helper de fecha (cierra B112/B101/B36) + el token de modalidad leído del flag (cierra B115/B94/B87/B50/B106).

## Guardado

```text
ADMIN/PARAMÉTRICAS-TIPOS (TiposCampo.tsx 655 + TipoCampoController 101 + TipoCampo 18 +
  TipoCampoService 67 + StoreTipoCampoRequest 34 + migr create_tipos_campo 21 +
  TipoCampoSeeder 43 + ModalidadReservaCampo 8 + migr add_modalidad_reserva 21 +
  AuditoriaService 48) — DOSSIER, NO tejí, sigo modo "ver todo corregir después".
  Numeración continua (tarifas B106-B111/D57-D60/S52-S54 · tipos B112-B117/D61-D63/
  S55-S57 + cierre B114 + ampliación S50). ÚLTIMO PARAMÉTRICO.

B112🔴 (EL DURO DEL FRENTE, fecha) columna "Creado" = "Invalid Date" ×9 UNIFORME → no
  es ISO crudo (B101/B36), es que el dato NI LLEGA (undefined/no-parseable). Uniforme
  en 9 filas descarta dato corrupto puntual = bug sistémico de código. Causas candidatas
  con línea (NO afirmo sin leer): (a) model 18 (606B, diminuto) no expone created_at
  (sin $casts/$hidden) y 101 devuelve modelo crudo → JSON sin campo → 655 new Date(undef);
  (b) typo nombre columna (creado vs created_at vs creado_en) 101↔655; (c) seeder 43
  insert() masivo sin timestamps → NULL, y formatter 655 lo vuelve inválido (ojo new
  Date(null)=1970, pero dayjs(null)/new Date('')=Invalid, depende formatter 655). React
  DevTools resuelve en segundos (qué prop alimenta la celda). AMPLÍA S50 a TRES instancias
  fecha rota: público 190 ISO crudo + admin 164 ISO crudo + admin 655 Invalid Date (B112
  peor, ni llega). Fix helper formatearFechaBolivia con fallback "—" nunca "Invalid Date".

B115🔴 (SOSPECHA CLAVE, integridad modelo) descripción Natación "aforo, no grilla horaria
  exclusiva" declara el modelo en TEXTO (B114✅ dato sabe), PERO flag operativo real es
  modalidad_reserva (8/21) en el CAMPO (862), NO el tipo. Evidencia: Coliseo/Estadio/
  Palacio = tipo Fútbol y son "Variantes (N)" (evento), mientras cancha nueva trinidad =
  tipo Múltiple y es cancha simple → EL TIPO NO DETERMINA MODALIDAD (Fútbol puede ser
  evento o cancha). Riesgo: si 862/249/492 derivan modalidad del tipo/descripción en vez
  del flag → producen B50 (piscina como franja ocupación) + B106 (corte iluminación pileta).
  NO afirmo sin leer 862/249/492. Conecta B94 (puente por modalidad_reserva no por
  tipo=Natación) + B87 + S47/S53/S56. Mismo patrón dossier: dato sabe vs UI lee lugar
  equivocado.

B113 (didáctica/visual, análogo B104) "icono automático · se asigna según nombre" cae al
  fallback gris en 6/9 (Atletismo/Frontón/Fútbol/Futsal/Múltiple/NATACIÓN) vs 3 propios
  (Básquet/Tenis/Vóley). Natación (pileta, recurso nuevo clave) se ve igual que atletismo.
  Mapeador nombre→icono cubre 3/9. No dinero, identidad/throughput. Proteger CONCEPTO
  automático, arreglar cobertura (al menos Natación + Fútbol/Futsal).

B116 (sospecha lente c) ¿unique en nombre tipo? migr 21 (21B) + StoreTipoCampoRequest 34.
  Sin unique → "Fútbol" duplicado → select tipo público (B10/FiltrosCampos 353) muestra
  dos veces + buscador B10 (coma suelta) se rompe más. Conecta B48. verificar 21/34.
B117 (sospecha lente c, destructivo+auditoría) menú ⋮ ¿Elimina tipo en uso? Coliseo/
  Estadio/Palacio ligados a Fútbol → hard-delete rompe FK tipo_campo_id (migr campos 26)
  o huérfanos. leer 101/18/26. Preferible forzar desactivación (estado Inactivo, patrón
  que YA existe = proteger) cuando hay campos ligados + auditar (48). Conecta B93/B48.

B114✅ CIERRE CRUZADO (S47/B94 nivel texto): descripción Natación "aforo no grilla" = el
  dato SABE el modelo. Proteger texto. PERO B115 matiza: texto informativo ≠ flag operativo
  → S47/B94 siguen vivos a nivel UI (leer flag, no texto). Sistema sabe en 2 lugares
  (descripción tipo = informativo; modalidad_reserva campo = operativo), UI debe leer el 2°.

ACIERTOS PROTEGER: descripción Natación declara modelo (B114); KPIs sumatorios coherentes
  9=9+0 (patrón admin: Dashboard 9=7+2, paramétricas 9=7+2, tarifas 63=26+37 — contracara
  B90 que mezclaba); estado Activo/Inactivo = desactivar sin borrar (lente c, protege B117);
  "icono automático" concepto (no obliga subir icono, throughput); buscador+filtro "todos"
  shell compartido (B90); placeholder ejemplo "Fútbol 7".

DIDÁCTICA/OP: D61 celda Creado no puede "Invalid Date", si no llega mostrar "—" (B112/S50);
  D62 icono automático cubra tipos reales sobre todo Natación (B113); D63 UI lea modalidad
  del FLAG campo no del nombre/descripción tipo (B115, previene B50/B106).

SUGERENCIAS: S55 B112/B101/B36 = UN helper formatearFechaBolivia (America/La_Paz, fallback
  "—" nunca Invalid) consumido por 190/164/655 (S50 ampliado a 3); S56 B115 = S47/S53
  aterrizando: ModalidadReservaCampo 8 único discriminador visible/operativo, descripción
  tipo = solo texto; token compartido 862/249/492/703/1022 leído del flag campo; S57 B116/
  B117 = mismo frente integridad paramétrica (unique nombre + delete bloqueado/soft por
  estado + auditar 48), unificar en 34/101/21/26, conecta B48/B93.

NO-BUG: 9 tipos todos activos = catálogo base estable (no faltan inactivos); icono gris 6
  tipos = fallback mapeador (B113 es cobertura, no el concepto); placeholder "Fútbol 7" =
  ejemplo no dato; KPIs 9=9+0 coherentes (acierto); Fútbol tipo de evento Y cancha = el
  tipo no ata modalidad (por eso B115 leer flag), no inconsistencia de dato; "Invalid Date"
  SÍ es bug (B112).

KB HONESTA: SIREB CASI NO aplica (tipos deporte = catálogo LOCAL, no hay sync SIREB de
  tipos, a dif tarifas/paramétricas; única conexión marginal Natación=aforo toca modelo
  pileta cuyas TARIFAS sí se espejan 703) — lo declaro pa' no inflar KB como turnos previos.
  React DevTools SÍ aplica Y es herramienta natural de B112 (prop undefined → new Date/dayjs
  Invalid, se ve en árbol 655) y B113 (mapeador icono = rama render). Rolldown
  circularDependency=false SÍ aplica (web-admin buildié) pero Tipos no mete imports nuevos;
  bundlerTimings/pluginTimings NO (no build). wa.me NO aplica (catálogo tipos, no QR; eso
  es éxito/P8).

PUERTA CORRIGIR YA: lo que vale es B112 (Invalid Date, duro, 3ª instancia fecha) + B115
  (tipo≠modalidad, explica B50/B106 aguas abajo) → pido TiposCampo 655 + TipoCampoController
  101 + TipoCampo 18 + TipoCampoSeeder 43 + migr 21 + StoreTipoCampoRequest 34 +
  ModalidadReservaCampo 8 + migr add_modalidad_reserva 21. B113/B116/B117 con 655/34/101/
  26/48. Aciertos (descripción Natación/KPIs/estado/icono concepto) NO se tejen: PROTEGER.
  S55/S56 = mayor rendimiento (helper fecha cierra B112/B101/B36; token modalidad leído del
  flag cierra B115/B94/B87/B50/B106).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94/
  B103 + escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59 (app-sidebar
  206 + Funcionarios 881 + Asignaciones 579, B48/B61, AHORA PRÓXIMO) · A4 Pago 576 (
  RecaudacionesApiClient 415 + webhook 97, B84) · D2✅✅ CERRADO + RAÍZ 703/107/77 (fuente
  B43/B37/B50/B62/B67/B71/B76/B84/B88/B99/B107/B115) · C1→C2 · gates F1-F4 · E mobile (
  piscina real piscina_datos_screen 260 + piscina_service 145; P3-paridad campo_detalle_screen
  1073 + bloque_disponibilidad 85; B43/B36/B42/B39/B44/B50/B62/B69/B70/B76/B78/B79/B84/B86/
  B87/B88/B94/B99/B101/B104/B106/B107/B110/B112/B113/B115 se propagan a móvil; S16/S38/S41/
  S45/S46/S50/S52/S53/S55/S56 también) · higiene (zombis, A3, D5, AgregarVarianteDialog,
  migr timerange piscina, tests HTTP lote + S33 test conversión) · reunión P1/P2/P4/P5/P7/P9
  (P5 asistencia 56/125, B86 ausente piscina = hueco P5; S30 reusa input B52; P7 reportes
  245/135; P9 asignaciones 579/22/26, B61 evidencia parcial + B115/B117 integridad paramétrica
  cae en A1/P9; P1/P2 auth 164/34/126; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT
  prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B117/
  D29-D63/S25-S57. PARAMÉTRICAS COMPLETAS (Campos/Tarifas/Tipos vistos).
PRÓXIMA NATURAL: Funcionarios.tsx 881 + Asignaciones.tsx 579 (A1/P9/B48/B61, permisos/
  asignaciones, los gates de permisos que llevan TODO el tramo sin dueño de archivo visto;
  B115/B117 integridad paramétrica también cae acá) o el LÁPIZ de editar turno (Fix3, 284)
  o Mapa Global 223 / Reportes 245+178 (P7) o bloque E mobile (Flutter, piscina real 260/145).
```

En una línea: `TiposCampo.tsx` (655) + el resto del paramétrico de tipos traen **el bug de fecha más grave del dossier, B112 🔴** — la columna “Creado” dice **“Invalid Date” en las 9 filas de forma uniforme**, o sea el dato **ni llega** (no es el ISO crudo de B101/B36, es `undefined`/no‑parseable alimentando el formatter), y como es uniforme en las 9 es bug sistémico de código (model 18 sin cast, typo de nombre 101↔655, o seeder 43 sin timestamps), React DevTools lo resuelve en segundos, y **amplía S50 a tres instancias de fecha rota** (190/164/655); le siguen **B115 🔴 (sospecha clave)** — la descripción de Natación declara el modelo en texto (“aforo, no grilla horaria”), pero el flag operativo real es `modalidad_reserva` (8/21) en el **campo**, no el **tipo** (prueba: Coliseo/Estadio/Palacio son tipo Fútbol y son evento, cancha nueva trinidad es Múltiple y es cancha → el tipo no ata la modalidad), así que si 862/249/492 leyeran el tipo producirían B50/B106, mismo patrón “dato sabe vs UI lee lugar equivocado” —, **B113** (el “icono automático” cae al fallback gris en 6/9, incluida Natación, que se ve igual que atletismo), **B116** (sospecha unique de nombre) y **B117** (sospecha delete de tipo en uso, lente c); la buena noticia es **B114 ✅** (la descripción de Natación es el dato que ya sabe el modelo, protege S47/B94 en nivel texto) y los KPIs 9=9+0 cuadran (patrón coherente de todo el admin, contracara de B90). **No tejí**: si querés corregir, lo que vale es B112 + B115 y necesito `TiposCampo.tsx` (655) + `TipoCampoController.php` (101) + `TipoCampo.php` (18) + `TipoCampoSeeder.php` (43) + migr 21 + `StoreTipoCampoRequest.php` (34) + `ModalidadReservaCampo` (8) + migr `add_modalidad_reserva` (21), con **S55/S56** (helper de fecha + token de modalidad leído del flag) como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: `Funcionarios.tsx` 881 + `Asignaciones.tsx` 579 para cerrar A1/P9/B48/B61, los gates de permisos que llevan todo el tramo sin dueño de archivo visto, o el lápiz de editar turno para Fix3 con 284) o arranco con esos archivos.
