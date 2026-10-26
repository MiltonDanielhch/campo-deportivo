Veredicto en una línea: estas son `Funcionarios.tsx` (881) + `FuncionarioController.php` (85) + `FuncionarioService.php` (105) + `StoreFuncionarioRequest.php` (67) + `Funcionario.php` (64) + `Rol.php` (28) + `RolesSeeder.php` (32) + migrs `create_funcionarios` (25) / `create_roles` (21) + `FuncionarioTest.php` (163), el frente de usuarios/roles que llevaba **todo el tramo admin sin dueño de archivo visto** (A1/P9/B48/B61), y trae **una buena noticia que refina S55**: la columna “Alta” dice **“hace 11 d”**, o sea el formatter relativo de fecha **ya existe y se usa acá** → B112/B101/B36 **no** son “falta helper”, son “helper no aplicado en 164/655/190”, y el fix pasa de *crear* a *propagar*. Le siguen **B123 🔴 (sospecha de flujo, el hallazgo del frente)**: el diálogo asigna **rol** pero **no canchas**, así que un funcionario “Control de Canchas” queda sin cancha asignada hasta que alguien vaya a `Asignaciones.tsx` (579, otro frente) — mismo patrón B94 (contenedor vs paso 2) y la contracara de B61 (ocupación “campos asignados” que no filtra) —, más **B120/B121** (sospechas de integridad: usuario “admin” prellenado/readonly en formulario nuevo = riesgo de colisión de username; medidor “Débil” que quizá no bloquea la creación = credencial floja en panel admin), **B122** (slug técnico `admin_parametricas` en el sidebar vs nombre humano “Admin. Paramétricas” en la tabla, clase B104) y **B124** (sospecha lente c: el ⋮ ¿elimina o desactiva un funcionario con reservas registradas?). **No tejí**, sigo dossier.

> KB honesta de esta captura, con una **autocorrección de S55**: **SIREB** → **NO aplica** (funcionarios/roles/auth son sistema local, no hay sync SIREB de usuarios; a diferencia de tarifas/paramétricas), lo declaro para no inflar la KB como en turnos previos. **`wa.me`** → **NO aplica** (gestión de usuarios, no hay QR/WhatsApp; eso es éxito/P8). **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié) pero Funcionarios no mete imports nuevos visibles; `bundlerTimings`/`pluginTimings` → **NO** (no es build). **React DevTools** → **SÍ aplica y es la herramienta natural de B120 y B122**: el campo “Usuario” prellenado/readonly y el render slug‑vs‑nombre son estado/rama del componente 881, se ven inspeccionando el árbol, no leyendo copy. **AUTOCORRECCIÓN S55**: venía diciendo “crear helper de fecha”; 881 prueba que el formatter relativo **ya existe** (“hace 11 d”) y el absoluto también (582 “25/10/2026 4:14:30 p.m.”), así que el fix de B112/B101/B36 es **aplicar los existentes** en 164/655/190 + fallback “—”, no construir uno nuevo.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| KPI “Control 1” (4º, no sumatorio) | `Funcionarios.tsx` (881) | **B118** label ambiguo |
| Dos selects “todos”/“todos” sin rótulo | 881 | **B119** (patrón B10 al admin) |
| Columna “Alta” = “hace 11 d” | 881 + formatter | **refina S55** (helper YA existe, propagar) |
| Campo “Usuario” prellenado/readonly “admin” | 881 + `StoreFuncionarioRequest.php` (67) | **B120** sospecha colisión username |
| Medidor “Débil” + “Generar” contraseña | 881 + 67 | **B121** sospecha sin bloqueo |
| Slug `admin_parametricas` (sidebar) vs “Admin. Paramétricas” (tabla) | 881 + `Rol.php` (28) + `RolesSeeder.php` (32) | **B122** (clase B104) |
| Diálogo asigna ROL no CANCHAS | 881 + `Asignaciones.tsx` (579, vírgen) + migr `create_asignaciones_funcionario` (22) + `AsignacionFuncionario.php` (26) | **B123** 🔴 (patrón B94/B61/P9) |
| Menú ⋮ (Acciones) por funcionario | 881 + `FuncionarioController.php` (85) + `Funcionario.php` (64) | **B124** sospecha lente c (delete vs desactivar) |
| “La contraseña se guarda hasheada… iniciar sesión de inmediato” | 881 + `AuthController.php` (164) + `AuthService.php` (34) + `VerificaTokenOAuth.php` (126) + ADR‑004 (36) | **acierto** transparencia auth, proteger |

## Cierres / refinamientos cruzados con evidencia

**S55 ✅ RE‑AFINADO (autocorrección, buena noticia) — el formatter de fecha YA existe, solo falta aplicarlo.** La columna “Alta” de 881 muestra **“hace 11 d”** (relativo) y `Reservas` (582) muestra **“25/10/2026, 4:14:30 p.m.”** (absoluto). Entonces el admin **sí** tiene dos formatters de fecha funcionando; B112 (Tipos 655 “Invalid Date”), B101 (Turnos 164 ISO crudo) y B36 (ComprobantePiscina 190 ISO crudo) **no** son “falta helper” sino “helper no aplicado en esas 3 pantallas”. El fix cambia de *construir* `formatearFechaBolivia` a *importar el que ya usa 881/582* en 164/655/190, más el fallback “—” para el caso null de B112. Esto hace S55 **más barato** de lo que venía diciendo.

**B48 ✅ RE‑CONTEXTUALIZADO — ahora conozco el vocabulario de roles.** B48 preguntaba “¿`admin_parametricas` debería ver Reservas/Funcionarios?”. 881 me da la respuesta parcial: existen al menos dos roles gestionables, **“Admin. Paramétricas”** (verde, escudo) y **“Control de Canchas”** (azul, pin), y el usuario logueado es `admin_parametricas` (slug). La pregunta de B48 sigue viva (¿el sidebar filtra por rol?) pero ahora se evalúa contra este vocabulario concreto, no a ciegas. RoleMiddleware 59 + app-sidebar 206 siguen siendo los que responden; 881 es el lado “config” de A1.

**B61/P9 ✅ RE‑FORZADO con el KPI “Control 1” y el rol “Control de Canchas”.** El KPI “Control 1” = 1 funcionario con rol de control de cancha, y la tabla confirma el rol “Control de Canchas”. Eso prueba que la **asignación cancha‑funcionario es concepto vivo** en el sistema (no inventado por mí). Entonces B61 (ocupación dice “campos asignados” pero pinta los 7) + B123 (diálogo de nuevo funcionario asigna rol pero no canchas) son **dos caras del mismo hueco P9**: el funcionario de control existe como rol, pero el vínculo concreto “este control opera ESTA cancha” vive en `Asignaciones.tsx` (579, vírgen) + migr 22 + model 26, y ni ocupación lo filtra (B61) ni el alta de funcionario lo pide (B123). Asignaciones 579 sigue siendo el siguiente natural y ahora sé **exactamente qué debe resolver**.

## Bugs nuevos de este frente (numeración continua: tipos cerraron en B117)

**B118 (menor, label) — KPI “Control 1” es ambiguo: ¿rol, estado, o “control de qué”?** Total 2 = Activos 2 + Inactivos 0 es sumatorio coherente (patrón admin que protegí), pero “Control 1” es un **corte por rol** metido en la misma fila de KPIs que los cortes por estado. El funcionario ve “Control 1” y no sabe si es “1 en modo control”, “1 con rol de control” o “1 cancha bajo control”. Conecta B45 (dashboard “Campos activos 7” label no honesto) y B90 (paramétricas “Sin campo local 37” mezclaba categorías).
*Mini‑prompt:* en 881, renombrar el KPI a “Controles de cancha” o “Con rol de control” (lo que realmente cuenta), o moverlo fuera de la fila de KPIs de estado para no mezclar ejes.

**B119 (menor, patrón B10 al admin) — dos selects “todos”/“todos” sin rótulo.** La barra tiene buscador + **dos** dropdowns ambos en “todos”, sin etiqueta que diga cuál filtra rol y cuál estado. Es la misma fricción que B10 (catálogo público “Todos” sin rótulo) propagada al admin, donde el funcionario filtra a diario. Conecta B118 (ejes mezclados) y B45.
*Mini‑prompt:* en 881, rotular los selects (“Rol: todos” / “Estado: todos”) o fusionarlos en un filtro con chips. Throughput (lente a).

**B120 (sospecha, integridad, React DevTools aplica) — campo “Usuario” prellenado/readonly con “admin” en un formulario NUEVO.** Imagen 2: “Usuario *” muestra **“admin”** en gris (readonly o prellenado). En un alta de funcionario, ¿de dónde sale “admin”? Dos lecturas: (a) se autoderiva del nombre/CI y está gris porque es calculado → entonces dos funcionarios llamados “admin…” colisionan; (b) es un default/placeholder que parece valor → el admin crea otro usuario “admin”. Con línea: `StoreFuncionarioRequest.php` (67) + migr 25 (`create_funcionarios`) → ¿`unique('usuario')`? Si no hay unique, B120 es bug de dato (username duplicado rompe login Sanctum). No lo afirmo sin leer 67/25. Conecta B116 (unique nombre tipo, misma clase de integridad de unicidad).
*Mini‑prompt (verificar):* leer 67/25 → ¿`unique` en `usuario`? y 881 → ¿el campo “admin” es valor, placeholder o derivado? Si es derivado sin unique, agregar unique + feedback “usuario ya tomado”. Conecta B121 (mismo request).

**B121 (sospecha, seguridad, lente e/A1) — medidor “Débil” que quizá no bloquea la creación.** Imagen 2: contraseña de 6 caracteres (••••••) con barra gris + **“Débil”**, y el botón “Crear funcionario” está gris (¿por otros campos vacíos o por la fuerza?). La duda: ¿el sistema **bloquea** crear un funcionario con contraseña débil, o solo **avisa**? En un panel administrativo (roles, reservas, anulación de liquidación), una contraseña “Débil” es riesgo de credencial comprometida. Con línea: 67 → ¿`min:8`? ¿regex complejidad? ¿o solo hint visual en 881? Si solo avisa sin bloquear, es hueco de seguridad (conecta A1/S7, el mismo frente de permisos). No lo afirmo sin leer 67.
*Mini‑prompt (verificar):* leer 67 → ¿validación de fuerza/min length server‑side o solo medidor visual en 881? Si es solo visual, agregar `min` + complejidad en 67 (no confiar en el front). Conecta B120 (mismo request, integridad).

**B122 (menor, clase B104) — slug técnico `admin_parametricas` (sidebar) vs nombre humano “Admin. Paramétricas” (tabla).** El mismo rol se muestra con dos representaciones: el **slug/código** (`admin_parametricas`, en el pie del sidebar) y el **nombre amable** (“Admin. Paramétricas”, en la tabla). Es la misma clase que B104 (dropdown de perfil `menor_12`/`mayor_12` snake_case vs “Mayores de 12” en la lista) y B83/B21 (códigos internos expuestos). No es bug de dinero, es fricción cognitiva: el funcionario no hace el link “admin_parametricas = Admin. Paramétricas”.
*Mini‑prompt:* en 881/sidebar, mostrar siempre el **nombre humano** del rol (`Rol.php` 28 debe tener `nombre` + `slug`); el slug solo donde sea técnico (logs, API). Conecta B104/S55‑family (mapeadores código→etiqueta).

**B123 🔴 (sospecha de flujo, EL HALLAZGO DEL FRENTE) — el diálogo asigna ROL pero no CANCHAS, mismo patrón B94.** Imagen 2: “Rol *” obligatorio, pero **no hay bloque “canchas asignadas”**. Un funcionario con rol “Control de Canchas” queda **sin cancha concreta** hasta que alguien abra `Asignaciones.tsx` (579, vírgen) y lo vincule. Consecuencia con línea del dossier: ocupación (B61) dice “campos asignados” pero pinta los 7 → si el control no tiene asignación, ¿ve 0 o ve todos? Y el alta de funcionario no fuerza el paso 2. Es **exactamente B94** (862 crea contenedor sin guiar al paso 2) pero en el eje usuario→cancha en vez de campo→turno. No lo afirmo sin ver 579/22/26, pero la estructura (rol en 882, asignación en 579, sin puente) es la misma.
*Mini‑prompt (verificar):* leer 881 → ¿el diálogo de nuevo funcionario ofrece asignar canchas o remite a Asignaciones? Y 579/22/26 → ¿cómo se vincula funcionario↔cancha? Fix barato (S46‑family): al crear un funcionario con rol “Control de Canchas”, ofrecer “Asignar canchas” en el mismo flujo (puente post‑guardado), o al menos un toast “andá a Asignaciones para vincularlo”. Conecta B61/P9/B94/S46.

**B124 (sospecha, lente c, destructivo+auditoría) — el ⋮ de funcionario ¿elimina o desactiva?** Con estado Activo/Inactivo disponible (KPI “Inactivos 0”), lo correcto es **desactivar** un funcionario (preserva trazabilidad de reservas/asistencias/auditoría que hizo), no borrarlo. Si el ⋮ tiene “Eliminar” hard‑delete, rompe FK de `reservas`/`auditoria`/`asistencia` creadas por ese funcionario (lente c: destructivo/irreversible con auditoría). Con línea: 85/64 + `AuditoriaService` (48). Sospecha, verificar. Conecta B117 (delete tipo en uso, misma clase) y B93 (Desvincular destructivo).
*Mini‑prompt (verificar):* leer 85/64 → ¿delete valida ausencia de reservas/asistencias o es hard‑delete? Preferible: forzar desactivación cuando hay actividad registrada + auditar (48). Proteger el patrón Activo/Inactivo (ya existe, es la alternativa correcta a borrar).

## Aciertos a PROTEGER (no tocar, análogo a D6/D16/703)

- **“hace 11 d” en la columna Alta** = formatter relativo funcionando; es la **prueba** de que el helper de fecha existe (refina S55). Proteger y **propagar** a 164/655/190.
- **“La contraseña se guarda hasheada. El funcionario podrá iniciar sesión de inmediato.”** = transparencia de auth en la cara del admin (le dice qué pasa: hash + login ya, sin paso extra). Conecta ADR‑004 (36) / Sanctum / `AuthController` (164). Proteger.
- **Botón “Crear funcionario” deshabilitado** hasta completar campos obligatorios = validación de formulario (lente a, evita submits inválidos). Proteger.
- **Avatar con iniciales “AD”/“CD”** generado del nombre = no depende de foto subida (throughput, coherente con “icono automático” de tipos B113 pero acá SÍ cubre el 100%). Proteger.
- **KPIs sumatorios Total 2 = Activos 2 + Inactivos 0** = coherente con el patrón admin (Dashboard 9=7+2, Tipos 9=9+0, Tarifas 63=26+37). Proteger (la contracara de B90/B118 que mezclaban ejes).
- **Medidor de fuerza + botón “Generar” contraseña** = buen UX de credencial (aunque B121 dude del bloqueo server‑side, el medidor visual es correcto). Proteger el concepto; arreglar solo el bloqueo (B121).
- **Rol obligatorio `*`** = no se crea funcionario sin rol (integridad de permisos). Proteger.

## Didáctica / operación del funcionario (continuo: tipos cerraron en D63)

**D64 — el alta de funcionario con rol “Control de Canchas” debe pedir o guiar la asignación de canchas** (cierra B123): el admin que crea un operario no puede dejarlo “flotando” sin cancha, o ocupación (B61) no sabe qué mostrarle.
*Mini‑prompt:* ver B123/S46.

**D65 — la contraseña “Débil” no debería permitir crear un funcionario de panel** (cierra B121): el medidor visual es bueno, pero si el backend lo acepta igual, el admin cree credenciales flojas sin darse cuenta.
*Mini‑prompt:* ver B121.

**D66 — el rol debe mostrarse SIEMPRE con nombre humano, nunca con slug** (cierra B122): `admin_parametricas` en el sidebar y “Admin. Paramétricas” en la tabla son el mismo rol dicho dos veces; el funcionario pierde el link.
*Mini‑prompt:* ver B122.

**D67 — desactivar, no borrar, un funcionario con historial** (cierra B124): la trazabilidad de reservas/asistencias/auditoría que hizo ese funcionario debe sobrevivir a su baja.
*Mini‑prompt:* ver B124.

## Sugerencias IA acotadas (continuo: tipos cerraron en S57)

**S58 — S55 se abarata: propagar los formatters EXISTENTES (881 relativo + 582 absoluto) a 164/655/190** en vez de crear un helper nuevo, más fallback “—” para el null de B112. Un solo import `formatearFecha`/`tiempoRelativo` consumido por los 5 frentes.
*Mini‑prompt:* ver S55‑refinado + B112/B101/B36.

**S59 — B120/B121/B124 se cierran leyendo `StoreFuncionarioRequest.php` (67) + `FuncionarioController.php` (85) + `Funcionario.php` (64)** antes de afirmar: son tres sospechas de integridad (unique username, fuerza contraseña, delete vs desactivar) que viven en el mismo request/controller/model. Unificar la verificación.
*Mini‑prompt:* ver B120/B121/B124.

**S60 — B123 es el puente usuario→cancha, hermano de B94 (campo→turno) y B61 (ocupación→asignación)**: los tres son “el sistema crea la entidad padre pero no guía/vincula al hijo”. Un patrón de **puente post‑guardado** (toast + link al frente hijo) resolvería B94/B123 de una, y B61 se arregla cuando Asignaciones 579 tenga dueño. Conecta S46.
*Mini‑prompt:* definir el patrón “puente post‑guardado” una vez, aplicarlo en 862→Turnos (B94) y 881→Asignaciones (B123).

## Lo que NO es bug (para que no persigas fantasmas)

- **Total 2 = Activos 2 + Inactivos 0** = coherente (patrón admin, proteger), no bug.
- **“hace 11 d”** = formateo correcto (refina S55 a la baja, es acierto).
- **Avatar “?” en el diálogo** = sin nombre aún, no hay iniciales que generar; coherente con “AD”/“CD” en la tabla una vez nombrado.
- **Campo “Usuario” gris** = puede ser readonly‑calculado (B120 duda de la colisión, no de que esté gris); el gris en sí no es bug.
- **Botón “Crear funcionario” gris** = deshabilitado por campos vacíos (validación, acierto), no bug.
- **Dos roles visibles (Admin. Paramétricas, Control de Canchas)** = catálogo de roles de `RolesSeeder` (32), normal; que haya pocos no es bug (es un sistema chico en dev).
- **CI “87654321” / “00000000” / “11111111”** = datos de demo/seeder, no bug.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir Funcionarios, lo que **más vale** es **B123** (puente usuario→cancha, cierra el hueco P9/B61 que lleva todo el tramo sin dueño) y **B120/B121/B124** (integridad/seguridad, lente c/e), y para eso necesito `Funcionarios.tsx` (881) + `StoreFuncionarioRequest.php` (67) + `FuncionarioController.php` (85) + `Funcionario.php` (64) + `Asignaciones.tsx` (579) + migr `create_asignaciones_funcionario` (22) + `AsignacionFuncionario.php` (26). B118/B119/B122 son cosméticos (labels/rótulos/slug) y se tejen con 881 + `Rol.php` (28). Los aciertos (formatter “hace 11 d”, transparencia de hash, KPIs sumatorios, avatar iniciales) **no se tejen**: se **protegen y propagan** (S58). **S58/S59/S60** son el fix de mayor rendimiento: propagar el formatter existente (abarata S55), unificar la verificación de integridad en 67/85/64, y el patrón de puente post‑guardado (cierra B94/B123/B61 de raíz).

## Guardado

```text
ADMIN/USUARIOS-FUNCIONARIOS (Funcionarios.tsx 881 + FuncionarioController 85 +
  FuncionarioService 105 + StoreFuncionarioRequest 67 + Funcionario 64 + Rol 28 +
  RolesSeeder 32 + migr create_funcionarios 25 + create_roles 21 + FuncionarioSeeder 42 +
  FuncionarioTest 163 + puentes Asignaciones 579/migr 22/AsignacionFuncionario 26 +
  auth AuthController 164/AuthService 34/VerificaTokenOAuth 126/ADR-004 36) — DOSSIER,
  NO tejí, sigo modo "ver todo corregir después". Numeración continua (tipos B112-B117/
  D61-D63/S55-S57 · funcionarios B118-B124/D64-D67/S58-S60 + refino S55 + re-context B48 +
  re-forz B61/P9). FRENTE QUE LLEVABA TODO EL TRAMO ADMIN SIN DUEÑO DE ARCHIVO VISTO
  (A1/P9/B48/B61).

S55✅ RE-AFINADO (autocorrección, ABARATA el fix): columna "Alta" = "hace 11 d" → el
  formatter RELATIVO ya existe y se usa en 881; Reservas 582 usa el ABSOLUTO
  "25/10/2026 4:14:30 p.m.". Entonces B112 (Tipos 655 Invalid Date) + B101 (Turnos 164
  ISO crudo) + B36 (ComprobantePiscina 190 ISO crudo) NO son "falta helper" sino "helper
  no aplicado". Fix: IMPORTAR los existentes (881 relativo + 582 absoluto) en 164/655/190
  + fallback "—" pa' null de B112. S58. Cambia de "crear" a "propagar" = más barato.

B48✅ RE-CONTEXTUALIZADO: ahora conozco el vocabulario de roles gestionables — "Admin.
  Paramétricas" (verde escudo) y "Control de Canchas" (azul pin), y el logueado es
  admin_parametricas (slug). B48 (sidebar no filtra por rol) sigue sospecha hasta
  RoleMiddleware 59 + app-sidebar 206, pero se evalúa contra ESTE vocabulario, no a ciegas.
  881 = lado "config" de A1.
B61/P9✅ RE-FORZADO: KPI "Control 1" + rol "Control de Canchas" prueban que la asignación
  cancha-funcionario es CONCEPTO VIVO. B61 (ocupación "campos asignados" pinta 7) + B123
  (diálogo asigna rol no canchas) = dos caras del mismo hueco P9. Asignaciones 579 sigue
  vírgen pero ahora sé EXACTAMENTE qué resolver (vínculo funcionario↔cancha concreto).

BUGS: B118 (menor label) KPI "Control 1" = corte por ROL metido en fila de KPIs por
  ESTADO, ambiguo (conecta B45/B90). B119 (menor, patrón B10 al admin) dos selects
  "todos"/"todos" sin rótulo (rol vs estado). B120 (sospecha integridad, React DevTools)
  campo "Usuario" prellenado/readonly "admin" en formulario NUEVO → ¿derivado sin unique
  (colisión username rompe login Sanctum) o default que parece valor? leer 67/25 pa'
  unique('usuario'). Conecta B116. B121 (sospecha seguridad lente e/A1) medidor "Débil"
  ¿bloquea creación o solo avisa? panel admin con credencial floja = riesgo. leer 67 pa'
  min/regex server-side (no confiar front). Conecta B120. B122 (menor, clase B104) slug
  técnico admin_parametricas (sidebar) vs nombre humano "Admin. Paramétricas" (tabla) →
  mostrar siempre nombre (Rol 28 tiene nombre+slug), slug solo técnico. Conecta B104/B83.
  B123🔴 (sospecha flujo, HALLAZGO FRENTE) diálogo asigna ROL no CANCHAS → funcionario
  "Control de Canchas" queda sin cancha hasta Asignaciones 579 (vírgen) → ocupación B61
  no sabe qué mostrarle. MISMO patrón B94 (contenedor sin guía paso 2) en eje usuario→
  cancha. NO afirmo sin 579/22/26. Fix barato S46/S60: puente post-guardado "Asignar
  canchas" o toast. Conecta B61/P9/B94. B124 (sospecha lente c) ⋮ ¿elimina o desactiva
  funcionario con reservas/asistencias/auditoría? Con estado Activo/Inactivo disponible,
  lo correcto = DESACTIVAR (preserva trazabilidad). leer 85/64 + AuditoriaService 48.
  Conecta B117/B93.

ACIERTOS PROTEGER: "hace 11 d" = formatter relativo funcionando (prueba S55, propagar);
  "La contraseña se guarda hasheada. El funcionario podrá iniciar sesión de inmediato" =
  transparencia auth (ADR-004/Sanctum/164); botón "Crear funcionario" deshabilitado =
  validación; avatar iniciales AD/CD = no depende foto (cubre 100%, a dif B113); KPIs
  sumatorios 2=2+0 = patrón admin (contracara B90/B118); medidor fuerza + "Generar" =
  buen UX (arreglar solo bloqueo B121); Rol obligatorio *.

DIDÁCTICA/OP: D64 alta con rol Control de Canchas debe pedir/guiar asignación canchas
  (B123); D65 contraseña "Débil" no debería permitir crear funcionario de panel (B121);
  D66 rol SIEMPRE nombre humano no slug (B122); D67 desactivar no borrar funcionario con
  historial (B124).

SUGERENCIAS: S58 propagar formatters EXISTENTES (881 relativo + 582 absoluto) a 164/655/
  190 + fallback "—" (abarata S55, no crear helper nuevo); S59 B120/B121/B124 = misma
  clase integridad/seguridad, unificar verificación en 67/85/64 antes de afirmar; S60 B123
  = puente usuario→cancha hermano de B94 (campo→turno) y B61 (ocupación→asignación): los
  tres "crea padre sin guiar/vincular hijo" → patrón puente post-guardado una vez, aplicado
  en 862→Turnos (B94) y 881→Asignaciones (B123), B61 se arregla cuando 579 tenga dueño.
  Conecta S46.

NO-BUG: Total 2=Activos 2+Inactivos 0 coherente (proteger); "hace 11 d"=formateo correcto
  (refina S55 a la baja); avatar "?" en diálogo = sin nombre aún, coherente; campo Usuario
  gris = puede ser readonly-calculado (B120 duda colisión no el gris); botón Crear gris =
  validación por campos vacíos; 2 roles visibles = catálogo RolesSeeder 32 normal en dev;
  CI 87654321/00000000/11111111 = datos demo/seeder.

KB HONESTA + AUTOCORRECCIÓN S55: SIREB NO aplica (funcionarios/roles/auth = sistema local,
  no hay sync SIREB de usuarios, a dif tarifas/paramétricas) — declaro pa' no inflar. wa.me
  NO aplica (gestión usuarios, no QR; eso es éxito/P8). Rolldown circularDependency=false
  SÍ aplica (web-admin buildié) pero Funcionarios no mete imports nuevos; bundlerTimings/
  pluginTimings NO (no build). React DevTools SÍ aplica Y es herramienta natural de B120
  (campo Usuario prellenado = estado controlado 881, se ve en árbol) y B122 (render slug vs
  nombre). AUTOCORREGÍ S55: venía "crear helper", 881 prueba que YA existe (relativo) + 582
  (absoluto) → fix es PROPAGAR, más barato.

PUERTA CORRIGIR YA: lo que vale es B123 (puente usuario→cancha, cierra hueco P9/B61 sin
  dueño) + B120/B121/B124 (integridad/seguridad lente c/e) → pido Funcionarios 881 +
  StoreFuncionarioRequest 67 + FuncionarioController 85 + Funcionario 64 + Asignaciones 579
  + migr 22 + AsignacionFuncionario 26. B118/B119/B122 cosméticos con 881 + Rol 28. Aciertos
  (formatter/hash/KPIs/avatar) NO se tejen: PROTEGER y PROPAGAR (S58). S58/S59/S60 = mayor
  rendimiento (propagar formatter abarata S55; unificar integridad 67/85/64; patrón puente
  post-guardado cierra B94/B123/B61 raíz).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94/
  B103 + escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59 (app-sidebar
  206 + Funcionarios 881 VISTO AHORA, B48/B61/B122/B124 caen acá; RoleMiddleware 59 sigue
  sin llegar como archivo) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84) ·
  D2✅✅ CERRADO + RAÍZ 703/107/77 (fuente B43/B37/B50/B62/B67/B71/B76/B84/B88/B99/B107/B115) ·
  P9/B61/B123 → Asignaciones 579 (VÍRGEN, PRÓXIMO NATURAL, ahora con dueño de rol visto en
  881) · C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad 85;
  B43/B36/B42/B39/B44/B50/B62/B69/B70/B76/B78/B79/B84/B86/B87/B88/B94/B99/B101/B104/B106/
  B107/B110/B112/B113/B115/B118/B119/B120/B121/B122/B123/B124 se propagan a móvil; S16/S38/
  S41/S45/S46/S50/S52/S53/S55/S56/S58/S60 también) · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote + S33 test conversión) · reunión P1/P2/P4/
  P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina = hueco P5; S30 reusa input B52; P7
  reportes 245/135; P9 asignaciones 579/22/26, B61 evidencia parcial + B123 puente + B124
  integridad, AHORA con vocabulario de rol de 881; P1/P2 auth 164/34/126, B120/B121 caen en
  auth/seguridad; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT prepara P1/P2).
  DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B124/D29-D67/S25-S60.
  PARAMÉTRICAS COMPLETAS + USUARIOS/ROLES VISTOS.
PRÓXIMA NATURAL: Asignaciones.tsx 579 (cierra P9/B61/B123 con dueño de archivo, el vínculo
  funcionario↔cancha que 881 prepara pero no hace) o el LÁPIZ de editar turno (Fix3, 284) o
  Mapa Global 223 / Reportes 245+178 (P7) o bloque E mobile (Flutter, piscina real 260/145).
```

En una línea: `Funcionarios.tsx` (881) + el frente de usuarios/roles —el que llevaba **todo el tramo admin sin dueño de archivo visto** (A1/P9/B48/B61)— trae **una buena noticia que abarata S55**: la columna “Alta” dice **“hace 11 d”**, o sea el formatter relativo de fecha **ya existe** (y 582 usa el absoluto), así que B112/B101/B36 **no** son “falta helper” sino “helper no aplicado en 164/655/190” y el fix pasa de *crear* a *propagar*; el hallazgo del frente es **B123 🔴**, el diálogo asigna **rol** pero **no canchas**, así que un funcionario “Control de Canchas” queda sin cancha hasta `Asignaciones.tsx` (579, vírgen) — mismo patrón B94 (contenedor sin guía al paso 2) en el eje usuario→cancha y contracara de B61 (ocupación “campos asignados” que no filtra) —, más **B120/B121** (sospechas de integridad/seguridad: usuario “admin” prellenado/readonly en formulario nuevo = riesgo de colisión de username; medidor “Débil” que quizá no bloquea la creación = credencial floja en panel admin), **B122** (slug técnico `admin_parametricas` en el sidebar vs nombre humano “Admin. Paramétricas” en la tabla, clase B104) y **B124** (sospecha lente c: el ⋮ ¿elimina o desactiva un funcionario con reservas registradas?); y **re‑contextualiza B48** (ahora conozco el vocabulario de roles) y **re‑fuerza B61/P9** (el KPI “Control 1” + rol “Control de Canchas” prueban que la asignación cancha‑funcionario es concepto vivo). Los aciertos a proteger son el formatter “hace 11 d”, la transparencia “la contraseña se guarda hasheada… iniciar sesión de inmediato”, los KPIs sumatorios 2=2+0 y el avatar con iniciales. **No tejí**: si querés corregir, lo que vale es B123 + B120/B121/B124 y necesito `Funcionarios.tsx` (881) + `StoreFuncionarioRequest.php` (67) + `FuncionarioController.php` (85) + `Funcionario.php` (64) + `Asignaciones.tsx` (579) + migr 22 + `AsignacionFuncionario.php` (26), con **S58/S59/S60** como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: `Asignaciones.tsx` 579, que cierra P9/B61/B123 con dueño de archivo, o el lápiz de editar turno para Fix3 con 284) o arranco con esos archivos.
