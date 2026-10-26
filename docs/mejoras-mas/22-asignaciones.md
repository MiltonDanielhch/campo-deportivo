Veredicto en una línea: esta es `Asignaciones.tsx` (579) + `AsignacionFuncionarioController.php` (59) + `AsignacionFuncionarioService.php` (102) + `AsignacionFuncionario.php` (26) + migr `create_asignaciones_funcionario` (22) + `AsignacionFuncionarioTest.php` (171), el frente que **cierra el mecanismo de B123/B61 con evidencia** (el vínculo funcionario↔cancha EXISTE y no es trivial: 2 de 7 asignados) y destapa **B125 🔴, el selector “Funcionario de control” muestra el UUID crudo `01a13aa6-…` en vez del nombre**, cuando la tarjeta de abajo del MISMO componente ya resuelve y pinta “Control de Desarrollo” — o sea el `Select` renderiza `value` y no `label`, el peor caso de la familia B122/B104/B83 (técnico vs humano) y que no escala (con 2 funcionarios se tolera, con 20 es inusable). Le siguen **B128** (B61 confirmado con matiz de rol: la ocupación no ramifica por rol, así que el admin ve los 7 con el subtítulo “campos asignados” que le miente), y dos aciertos operativos fuertes: **la pileta se supervisa igual que una cancha** (supervisión agnóstica de modalidad, la contracara correcta de B50) y **el chip de tipo es inofensivo acá** porque vigilar el lugar físico no depende de la modalidad (el límite exacto donde B115 NO aplica). **P9 sigue abierto honestamente**: esta pantalla es por-funcionario y hay un solo control, así que no puede mostrar la colisión “dos controles misma cancha”; necesita la migr 22 + un 2º control. **No tejí**, sigo dossier.

> KB honesta de esta captura, con los defaults de la doc que me pasaste: **SIREB** → **NO aplica** (asignación de supervisión = sistema local, no hay sync SIREB de vínculos funcionario‑cancha; a diferencia de tarifas/paramétricas), lo declaro para no inflar la KB. **`wa.me`** → **NO aplica** (no hay botón WhatsApp acá; el doc confirma que el link cae a WhatsApp Web sin app, pero eso solo pesa en el éxito/P8, no en esta pantalla). **Rolldown `checks.circularDependency`** → default **`false`**, así que **aunque hubiera un ciclo entre `services/asignacionesService` y `pages/Asignaciones` el build NO lo avisaría**; pero no buildié web‑admin en este turno y esta pantalla no mete imports nuevos visibles, así que lo vigilo solo al tejer. **`checks.bundlerTimings`** → default **`true`** (el deprecated `pluginTimings` cede ante él), pero de nuevo no es build, no aplica. **React DevTools** → **SÍ aplica y es la herramienta natural de B125**: un `Select` que muestra el UUID es un `label` de opción faltante o mal mapeado en el árbol de 579, se ve inspeccionando el componente, no leyendo copy.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Select “Funcionario de control” = UUID crudo | `Asignaciones.tsx` (579) | **B125** 🔴 value vs label |
| Tarjeta resumen “2 de 7 · 29% · 2 asignados / 5 sin asignar” | 579 + `AsignacionFuncionarioService.php` (102) | **acierto** coherencia (proteger) |
| Grid de 7 campos con chip de tipo + “Asignado/Sin asignar” | 579 + `AsignacionFuncionario.php` (26) + migr 22 | **B128** (matiz rol) + **acierto** pileta uniforme |
| “Asignar todos / Quitar todos” | 579 | **B126** sospecha (bulk sin confirm, lente c) |
| Códicos locales `SIR-…` vs `CV-001/EGM-001/PAL-001` | 579 + migr campos 26 | **B127** (refina B89, 3ª pantalla) |
| El vínculo que esta pantalla ESCRIBE | `AsignacionFuncionarioController.php` (59) + migr 22 + model 26 | **B123** (destino del puente existe) + **P9** (exclusividad aún no) |
| Quien lo CONSUME (ocupación) | `OcupacionService.php` (492) + `OcupacionController.php` (126) + `app-sidebar.tsx` (206) | **B128/B61/B48** (ramificar por rol) |

## Cierres / refinamientos cruzados con evidencia

**B123 ✅ MECANISMO CERRADO (destino existe, falta el link).** El turno de Funcionarios dejó B123 como “el diálogo asigna rol pero no canchas → queda flotando”. Esta pantalla **es** el destino: acá se asignan las canchas al control (2 de 7 visibles). Entonces B123 se reduce de “falta el paso 2” a “falta el **puente** 881→579”: al crear un funcionario con rol “Control de Canchas”, el post‑guardado debería abrir esta pantalla pre‑seleccionada (o un toast “asignale canchas”). El tercer caso del patrón S46/S60 (campo→turno en B94, usuario→cancha acá). No hay hueco de funcionalidad, hay hueco de enlace.

**B61 ✅ CONFIRMADO con matiz de rol (B128).** Antes B61 era sospecha (“ocupación dice ‘campos asignados’ pero pinta 7”). Ahora sé que el vínculo **existe y es no‑trivial** (Control de Desarrollo tiene 2 de 7), así que la ocupación **sí** tiene datos para filtrar y no lo hace. El matiz que afino: el logueado en la captura de ocupación era `admin_parametricas`, no un control — entonces el bug no es “no filtra”, es **“no ramifica por rol”**: un control debería ver sus 2, un admin debería ver los 7 **pero con el subtítulo corregido a “todos (vista admin)”**. Hoy el admin ve 7 con el subtítulo “asignados” que le miente. Raíz en `OcupacionService` (492)/`Controller` (126) + `app-sidebar` (206), mismo eje que B48. Confirmado como bug de etiquetado/rol, no de conteo.

**B89 ✅ RE‑FORZADO (3ª pantalla).** Los códigos locales otra vez inconsistentes: `SIR-CNT-SEDEDE-001`/`SIR-SEDEDE-CS1`/`SIR-SEDEDE-FRONTON`/`SIR-0005` con prefijo, vs `CV-001`/`EGM-001`/`PAL-001` sin él. Ya no es rareza de paramétricas: aparece en ocupación, en detalle y acá. B89 pasa de “menor convención” a “inconsistencia real en 3 frentes”, aunque sigue siendo cosmético (el vínculo SIREB vero está en la columna “Servicio SIREB”, no en el código local).

## Bugs nuevos de este frente (numeración continua: funcionarios cerraron en B124)

**B125 🔴 (UX/escalabilidad, React DevTools aplica) — el selector muestra el UUID crudo, no el nombre.** El `Select` “Funcionario de control” tiene como valor visible `01a13aa6-2efe-7306-a298-802adb8bfd48`. Pero la tarjeta de abajo, en el MISMO componente, ya resolvió la selección y pinta “Control de Desarrollo / funcionario_control / CI 111111 · @control”. O sea el dato humano está disponible en el estado del componente; el `Select` simplemente renderiza `value` (UUID) en vez de `label` (nombre) — bug clásico de Radix/shadcn `SelectItem` sin `children`/label, o de un `value` puesto como texto. Con 2 funcionarios se tolera por accidente (el admin reconoce el orden), pero **no escala**: con 20 controles el admin tendría que memorizar UUIDs para elegir a quién asignar canchas. Es el peor miembro de la familia B122 (slug)/B104 (snake_case)/B83 (códigos): acá ni siquiera es un slug legible, es un UUID.
*Mini‑prompt (verificar):* leer 579 → el `Select`/`SelectItem` del funcionario, ¿pasa `label`/`children` con el nombre o solo `value` con el UUID? Fix: renderizar el nombre (y el @usuario como secundario) en la opción seleccionada y en la lista. Conecta S61.

**B126 (sospecha menor, lente c) — “Asignar todos / Quitar todos” sin confirmación visible.** Dos acciones bulk que reescriben las 7 asignaciones de un toque. Son reversibles (bajo riesgo), pero “Quitar todos” borra de golpe el trabajo de asignación sin diálogo. Con línea: 579. Sospecha, no afirmo sin ver si hay `AlertDialog` (el `alert-dialog.tsx` 190 existe en el árbol de admin).
*Mini‑prompt (verificar):* leer 579 → ¿“Quitar todos”/“Asignar todos” confirman? Si no, un confirm liviano en “Quitar todos” (reversible pero destructivo‑de‑esfuerzo). Menor, pulido.

**B127 (refina B89, no es bug nuevo) — códigos locales inconsistentes visibles por 3ª vez.** Ver cierre cruzado. Fix global de convención (o aceptar la dualidad y documentarla), no de esta pantalla.

## P9 — sigue abierto, y digo por qué esta pantalla NO lo cierra

P9 = “¿dos funcionarios de control pueden supervisar la MISMA cancha?” (riesgo de fraude/duplicación de vigilancia). Esta pantalla es **por‑funcionario** (eliges UN control y le asignás canchas), así que estructuralmente **no puede** mostrar la colisión inversa (muchos controles → una cancha). Además hay **un solo control** en el sistema (el select solo tiene a Control de Desarrollo), así que ni siquiera hay con qué comparar. Lo que esta pantalla prueba es la otra dirección (un control → muchas canchas, permitido, 2 de 7), que es correcta y no es el riesgo de P9. **P9 queda pendiente de `AsignacionFuncionario.php` (26) + migr 22**: si el índice unique es `(funcionario_id, campo_id)` entonces un control no repite cancha (fine) pero **dos controles sí pueden tener la misma cancha** (el riesgo P9 vive ahí). No lo cierro con esta captura; lo cierro leyendo 26/22 y creando un 2º control. Honestidad ante todo: no afirmo que P9 sea bug, afirmo que esta pantalla no tiene la evidencia para resolverlo.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **La pileta (H. PISCINA OLIMPICA, chip “Natación”) aparece en el grid como campo supervisable normal, “Sin asignar”** = la supervisión es **agnóstica de modalidad**: un control vigila el lugar físico sin importar si cobra por franja (cancha), por perfil (pileta) o por evento (Coliseo). Esto es la **contracara correcta de B50** (ocupación erróneamente le dio grilla binaria de cancha a la pileta): acá NO se comete ese error, la pileta se trata igual para supervisar. **Proteger** — es el modelo mental que B50/B87/B106 violan en otros frentes.
- **El chip de tipo (Múltiple/Futsal/Fútbol/Frontón/Natación) es inofensivo acá** porque vigilar no depende de la modalidad. Este es el **límite exacto donde B115 NO aplica**: B115 era peligro cuando el *tipo* se leía como *modalidad* para decidir flujo (862/249/492); acá el tipo es solo etiqueta de identificación del lugar, y está bien. Marcar la frontera evita “arreglar” algo que no está roto.
- **Progreso “29%” = 2/7** (28.57 redondeado) y **“2 asignados / 5 sin asignar” = 7** = coherencia aritmética interna (patrón admin que protegí en Dashboard/Tipos/Tarifas). **Proteger.**
- **Buscador “por nombre, código o dirección” y las tarjetas muestran los 3** (nombre + código SIR‑…/CV‑… + dirección) = la promesa del search se cumple en la UI. **Proteger.**
- **“Asignar todos / Quitar todos”** = throughput de ventanilla (lente a): asignar 7 de un toque es lo correcto para un control que cubre todo el complejo. **Proteger el concepto** (solo añadir confirm en “Quitar todos”, B126).
- **La tarjeta resumen con avatar + nombre + @usuario + CI + barra** = contexto rico del funcionario antes de asignar. **Proteger.**

## Didáctica / operación del funcionario (continuo: funcionarios cerraron en D67)

**D68 — el selector debe mostrar el nombre del control, jamás el UUID** (cierra B125): el admin que asigna canchas piensa en personas, no en identificadores.
*Mini‑prompt:* ver B125/S61.

**D69 — la ocupación debe ramificar por rol: control ve sus asignadas, admin ve todas etiquetadas “todos”** (cierra B128/B61/B48): el subtítulo “campos asignados” no puede mentirle al admin.
*Mini‑prompt:* ver B128/S62.

**D70 — al crear un control, aterrizar (o apuntar) a esta pantalla** (cierra B123): el puente 881→579 es el último eslabón del patrón “crea padre sin guiar al hijo”.
*Mini‑prompt:* ver B123/S63.

## Sugerencias IA acotadas (continuo: funcionarios cerraron en S60)

**S61 — B125 se arregla pasando `label`/`children` al `SelectItem`** (React DevTools confirma que es label faltante, no dato ausente: la tarjeta de abajo ya tiene el nombre en el mismo árbol). Un fix de una línea en 579.
*Mini‑prompt:* ver B125.

**S62 — unificar la fuente del vínculo en `AsignacionFuncionario` (26)** consumida por **esta pantalla (579, escribe)** y **ocupación (492, lee)**: hoy 579 escribe asignaciones que 492 ignora (B128). Un solo repositorio (service 102) leído por ambos, con la rama de rol en 492/126. Conecta B48/RoleMiddleware 59 (A1).
*Mini‑prompt:* ver B128.

**S63 — B123 = tercera instancia del patrón puente post‑guardado** (S46/S60): 862→Turnos (B94), 881→Asignaciones (B123). Definir el patrón una vez (toast + link con la entidad pre‑cargada) y aplicarlo en los dos.
*Mini‑prompt:* ver B123/D70.

**S64 — P9 se cierra leyendo 26/22 + creando un 2º control**, no con esta pantalla; anotar como pendiente de archivo (migr 22 sigue sin llegar como contenido, solo la veo referenciada).
*Mini‑prompt:* ver P9 arriba.

## Lo que NO es bug (para que no persigas fantasmas)

- **Pileta en el grid de supervisión** = acierto (supervisión agnóstica de modalidad), no el error B50.
- **Chip de tipo en las tarjetas** = inofensivo acá (frontera de B115), no bug.
- **29% / 2+5=7** = coherencia correcta, no bug.
- **Que el control asignado sea una cancha simple (Sintética) + un evento (Coliseo)** = cruzar modalidades en la supervisión es correcto (vigilás el lugar, no el modelo de cobro).
- **UUID `01a13aa6-…` compartido de prefijo con el UUID de la pileta en el breadcrumb de Turnos** = ambos son UUIDv7 generados en la misma tanda (mismo seeder/sesión dev), observación que refuerza “datos de demo” (y por tanto una hipótesis menor para B62: parte del 75% expirado podría ser ruido de seed, no flujo roto — baja confianza, no lo afirmo).
- **CI 111111 / @control** = dato de demo coherente con Funcionarios.
- **Select con un solo option** = hay un solo control en el sistema, normal en dev, no bug (pero esconde que B125 no escala, que sí lo es).

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir Asignaciones, lo que **más vale** es **B125** (UUID en el selector, fix de una línea, React DevTools lo confirma) y **B128** (ramificar ocupación por rol reusing el vínculo que esta pantalla escribe), y para eso necesito `Asignaciones.tsx` (579) + `OcupacionService.php` (492) + `OcupacionController.php` (126) + `app-sidebar.tsx` (206) + `AsignacionFuncionario.php` (26) + migr 22. **P9** queda pendiente de 26/22 + un 2º control (S64). **B126/B127** son pulido. Los aciertos (pileta uniforme, chip de tipo, progreso, buscador, bulk) **no se tejen**: se **protegen**. **S61/S62/S63** son el fix de mayor rendimiento: label del select + fuente única del vínculo leída por ocupación + patrón puente post‑guardado.

## Guardado

```text
ADMIN/USUARIOS-ASIGNACIONES (Asignaciones.tsx 579 + AsignacionFuncionarioController 59 +
  AsignacionFuncionarioService 102 + AsignacionFuncionario 26 + migr create_asignaciones_
  funcionario 22 + AsignacionFuncionarioTest 171 + consumidores OcupacionService 492 +
  OcupacionController 126 + app-sidebar 206) — DOSSIER, NO tejí, sigo modo "ver todo
  corregir después". Numeración continua (funcionarios B118-B124/D64-D67/S58-S60 ·
  asignaciones B125-B128/D68-D70/S61-S64 + cierre B123 + confirm B61 + refino B89 +
  P9 queda abierto honesto). FRENTE QUE CIERRA EL MECANISMO P9/B61/B123 (vínculo existe).

CIERRES CRUZADOS CON EVIDENCIA:
  B123✅ MECANISMO CERRADO (destino existe, falta link): esta pantalla ES el paso-2 de
    Funcionarios (acá se asignan canchas al control, 2 de 7 visibles). B123 pasa de "falta
    el paso 2" a "falta el puente 881→579" (post-guardado abre esta pre-seleccionada). 3ª
    instancia patrón S46/S60 (campo→turno B94, usuario→cancha acá). No hay hueco de
    funcionalidad, hay hueco de enlace.
  B61✅ CONFIRMADO con matiz rol (B128): el vínculo EXISTE y es no-trivial (Control de
    Desarrollo 2 de 7), así que ocupación TIENE datos pa' filtrar y no lo hace. Matiz: el
    logueado en ocupación era admin_parametricas (no control) → bug no es "no filtra" sino
    "no ramifica por rol": control ve sus 2, admin ve 7 PERO subtítulo "asignados" le
    miente (debe decir "todos (vista admin)"). Raíz 492/126 + sidebar 206 (eje B48).
    Confirmado bug etiquetado/rol, no conteo.
  B89✅ RE-FORZADO (3ª pantalla): códigos locales inconsistentes SIR-... vs CV-001/EGM-001/
    PAL-001 otra vez (ocupación+detalle+acá). Pasa de rareza paramétricas a inconsistencia
    real 3 frentes, aunque cosmético (vínculo SIREB vero en col "Servicio SIREB").

P9 — SIGUE ABIERTO HONESTO: esta pantalla es por-funcionario (eliges UN control y le
  asignás canchas) → estructuralmente NO puede mostrar la colisión inversa (muchos
  controles → una cancha). Además hay UN solo control (select solo tiene Control de
  Desarrollo) → ni con qué comparar. Lo que prueba es la otra dirección (1 control →
  muchas canchas, permitido, correcto, no es el riesgo P9). P9 queda pendiente de
  AsignacionFuncionario 26 + migr 22: si unique=(func,campo) entonces 2 controles SÍ
  pueden tener la misma cancha (riesgo P9 vive ahí). NO cierro con esta captura; cierro
  leyendo 26/22 + creando 2º control. NO afirmo bug, afirmo falta de evidencia.

BUGS: B125🔴 (React DevTools) select "Funcionario de control" muestra UUID crudo
  01a13aa6-2efe-7306-a298-802adb8bfd48, PERO la tarjeta de abajo del MISMO componente ya
  resolvió y pinta "Control de Desarrollo/funcionario_control/CI 111111/@control" → el
  Select renderiza value(UUID) no label(nombre), bug Radix/shadcn SelectItem sin children.
  Peor miembro familia B122(slug)/B104(snake)/B83(códigos): ni slug legible, UUID. Con 2
  se tolera por orden, NO escala (20 controles = memorizar UUIDs). Fix 1 línea 579.
  B126 (sospecha menor lente c) "Asignar todos/Quitar todos" bulk sin confirm visible
  (reversible, bajo riesgo; "Quitar todos" borra esfuerzo de golpe). leer 579 pa' AlertDialog
  (alert-dialog 190 existe en árbol). Pulido.
  B127 = refino B89 (no bug nuevo), 3ª pantalla.

ACIERTOS PROTEGER: pileta (chip Natación) en grid como campo supervisable normal =
  supervisión AGNÓSTICA de modalidad (un control vigila el lugar sin importar franja/perfil/
  evento) = CONTRACARA CORRECTA de B50 (acá NO se comete el error de dar grilla binaria a
  pileta); chip de tipo inofensivo acá = FRONTERA exacta donde B115 NO aplica (B115 peligra
  cuando tipo se lee como modalidad pa' decidir flujo 862/249/492; acá tipo = etiqueta de
  identificación del lugar, bien); progreso 29%=2/7 + "2 asignados/5 sin asignar"=7 coherente
  (patrón admin); buscador nombre/código/dirección y tarjetas muestran los 3 (promesa cumple);
  "Asignar todos/Quitar todos" = throughput lente a (proteger concepto, solo confirm en
  Quitar todos B126); tarjeta resumen avatar+nombre+@+CI+barra = contexto rico.

DIDÁCTICA/OP: D68 selector muestra nombre jamas UUID (B125); D69 ocupación ramifica por rol
  control→asignadas / admin→todos etiquetado (B128/B61/B48); D70 al crear control aterrizar/
  apuntar a esta pantalla (B123 puente).

SUGERENCIAS: S61 B125 = pasar label/children al SelectItem (React DevTools confirma label
  faltante, dato ya está en el árbol: la tarjeta de abajo lo tiene); S62 unificar fuente del
  vínculo en AsignacionFuncionario 26 escrita por 579 y LEÍDA por ocupación 492 (hoy 492
  ignora lo que 579 escribe = B128), rama de rol en 492/126, conecta B48/RoleMiddleware 59;
  S63 B123 = 3ª instancia patrón puente post-guardado (S46/S60): 862→Turnos (B94) + 881→
  Asignaciones (B123), definir una vez (toast+link entidad pre-cargada); S64 P9 se cierra
  leyendo 26/22 + 2º control, NO con esta pantalla (migr 22 sigue sin llegar como contenido).

NO-BUG: pileta en grid=acierto (supervisión agnóstica modalidad, no error B50); chip tipo=
  inofensivo acá (frontera B115); 29%/2+5=7 coherente; control con cancha simple+evento=
  cruzar modalidades en supervisión es correcto (vigilás lugar no modelo cobro); UUID
  01a13aa6-... compartido prefijo con UUID pileta breadcrumb Turnos = ambos UUIDv7 misma
  tanda (seeder/sesión dev) → refuerza "datos demo" e hipótesis MENOR pa' B62 (parte del
  75% expirado podría ser ruido seed, no flujo roto — BAJA confianza, no afirmo); CI 111111/
  @control=demo; select 1 option=hay 1 solo control en dev, normal (pero esconde que B125
  no escala, que sí lo es).

KB HONESTA (defaults doc): SIREB NO aplica (asignación supervisión=sistema local, no sync
  SIREB de vínculos func-cancha, a dif tarifas/paramétricas) — declaro pa' no inflar. wa.me
  NO aplica (no hay botón WhatsApp acá; doc confirma link cae a WhatsApp Web sin app pero
  eso pesa solo en éxito/P8). Rolldown checks.circularDependency default FALSE → aunque
  hubiera ciclo services/asignacionesService↔pages/Asignaciones el build NO lo avisaría,
  pero no buildié este turno y 579 no mete imports nuevos visibles, vigilo al tejer;
  bundlerTimings default TRUE (pluginTimings deprecated cede ante él) pero no es build, no
  aplica. React DevTools SÍ aplica Y es herramienta natural de B125 (Select muestra UUID =
  label faltante en árbol 579, se ve inspeccionando, no copy).

PUERTA CORRIGIR YA: lo que vale es B125 (UUID selector, fix 1 línea, DevTools confirma) +
  B128 (ramificar ocupación por rol reusing vínculo que 579 escribe) → pido Asignaciones 579
  + OcupacionService 492 + OcupacionController 126 + app-sidebar 206 + AsignacionFuncionario
  26 + migr 22. P9 pendiente 26/22 + 2º control (S64). B126/B127 pulido. Aciertos (pileta
  uniforme/chip tipo/progreso/buscador/bulk) NO se tejen: PROTEGER. S61/S62/S63 = mayor
  rendimiento (label select + fuente única vínculo leída por ocupación + patrón puente).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94/
  B103 + escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59 (app-sidebar
  206 + Funcionarios 881 + Asignaciones 579 VISTOS, B48/B61/B122/B124/B128 caen acá;
  RoleMiddleware 59 + migr 22 siguen sin llegar como archivo/contenido) · A4 Pago 576 (
  RecaudacionesApiClient 415 + webhook 97, B84) · D2✅✅ CERRADO + RAÍZ 703/107/77 (fuente
  B43/B37/B50/B62/B67/B71/B76/B84/B88/B99/B107/B115) · P9/B61/B123 → Asignaciones 579 VISTO
  AHORA (mecanismo cerrado, puente B123 + ramificación rol B128 + P9 pendiente 26/22) ·
  C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 + piscina_service
  145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad 85; B43/B36/B42/B39/B44/
  B50/B62/B69/B70/B76/B78/B79/B84/B86/B87/B88/B94/B99/B101/B104/B106/B107/B110/B112/B113/
  B115/B118/B119/B120/B121/B122/B123/B124/B125/B127/B128 se propagan a móvil; S16/S38/S41/
  S45/S46/S50/S52/S53/S55/S56/S58/S60/S61/S62/S63 también) · higiene (zombis, A3, D5,
  AgregarVarianteDialog, migr timerange piscina, tests HTTP lote + S33 test conversión) ·
  reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina = hueco P5; S30 reusa
  input B52; P7 reportes 245/135; P9 asignaciones 579/22/26 VISTO, mecanismo cerrado + B128
  ramificación rol + P9 exclusividad pendiente 26/22 + 2º control; P1/P2 auth 164/34/126,
  B120/B121 caen en auth/seguridad; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT
  prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B128/
  D29-D70/S25-S64. PARAMÉTRICAS COMPLETAS + USUARIOS/ROLES/ASIGNACIONES VISTOS.
PRÓXIMA NATURAL: el LÁPIZ de editar turno (Fix3, 284 — lápiz fila "2/10" de 164, cerrar qué
  pasa al editar perfiles con reservas activas, el gate de dinero que queda) o Reportes.tsx
  245 + ClientesFrecuentes 178 (P7, cierre de la reunión) o Mapa Global 223 (ocupación admin)
  o P5 asistencia (AsistenciaController 56 + MarcarAsistenciaService 125, B86 hueco piscina)
  o bloque E mobile (Flutter, piscina real 260/145).
```

En una línea: `Asignaciones.tsx` (579) **cierra el mecanismo de B123/B61 con evidencia** — el vínculo funcionario↔cancha existe y no es trivial (2 de 7 asignados), así que B123 se reduce a “falta el puente 881→579” (tercera instancia del patrón post‑guardado S46/S60) y **B61 queda confirmado con matiz de rol (B128)**: la ocupación no ramifica por rol, entonces el admin ve los 7 con el subtítulo “campos asignados” que le miente (un control debería ver sus 2, un admin los 7 etiquetados “todos”), raíz en `OcupacionService` (492) + `app-sidebar` (206), mismo eje que B48 — y destapa **B125 🔴**, el selector “Funcionario de control” mostrando el **UUID crudo `01a13aa6-…`** cuando la tarjeta de abajo del MISMO componente ya pinta “Control de Desarrollo”, o sea un `Select` que renderiza `value` y no `label` (React DevTools lo confirma como label faltante), el peor caso de la familia B122/B104/B83 y que no escala; más **B126** (bulk “Asignar/Quitar todos” sin confirm, sospecha menor) y **B127** (B89 reforzado por 3ª vez). Los dos aciertos operativos fuertes son que **la pileta se supervisa igual que una cancha** (supervisión agnóstica de modalidad, la contracara correcta de B50) y que **el chip de tipo es inofensivo acá** (la frontera exacta donde B115 NO aplica, porque vigilar el lugar no depende del modelo de cobro). **P9 sigue abierto honestamente**: esta pantalla es por‑funcionario y hay un solo control, así que no puede mostrar la colisión “dos controles misma cancha”; necesita `AsignacionFuncionario` (26) + migr 22 + un 2º control. **No tejí**: si querés corregir, lo que vale es B125 + B128 y necesito `Asignaciones.tsx` (579) + `OcupacionService.php` (492) + `OcupacionController.php` (126) + `app-sidebar.tsx` (206) + `AsignacionFuncionario.php` (26) + migr 22, con **S61/S62/S63** como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: el lápiz de editar turno para Fix3 con 284, o `Reportes.tsx` 245 + `ClientesFrecuentes.tsx` 178 para cerrar P7, o P5 asistencia con `AsistenciaController` 56 + `MarcarAsistenciaService` 125) o arranco con esos archivos.
