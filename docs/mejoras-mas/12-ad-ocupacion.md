Veredicto en una línea: esta es Ocupación (`pages/ocupacion/MisCampos.tsx` 543 + `OcupacionController.php` 126 + `OcupacionService.php` 492 + `OcupacionTest.php` 529) y es la captura que **cierra dos bugs del dossier público con evidencia cruzada** — **B52** (el input “Verificar código” enumera los formatos reales `RES-/RSV-/PIS-/K699-…`, así que el `PTS-` que prometía el help público en **B21** es un fantasma que el admin no usa) y **B58** (el admin pinta **7** campos y el público **4**, confirmando que **B45** no es error de conteo sino de label: ambos números son correctos, ninguno lo explica) — y destapa **el bug más grave del tramo admin: B50 🔴, la piscina aparece DOS veces con dos modelos incompatibles** (arriba como franja binaria “Libre” de cancha, abajo como turnos/perfiles “2/10, 0/10”), que es B43/B8/B47 aterrizando en la cara del funcionario. Le siguen **B61 🔴** (el subtítulo dice “campos asignados” pero pinta los 7 del sistema = P9/permisos) y **B60 🔴** (ninguna franja “En cobro”, las pendientes invisibles otra vez, B46). **No tejí** — sigo modo dossier; al final la puerta de “corregir ya” con los 4 archivos.

> KB honesta, coherente con la corrección que anuncié: **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié), pero Ocupación no mete imports nuevos visibles; lo vigilo al tejer. **React DevTools** → **SÍ aplica** (las grillas de franjas y la sección piscina son componentes React; si “Libre” no refresca o la sección de abajo no se sincroniza con la grilla de arriba —que es justo B50—, la extensión es la herramienta natural, no el copy). **`wa.me`** → **NO aplica** (verificar código es manual, no hay QR/WhatsApp acá). **SIREB** → aplica (piscina doble modelo = B43/B8; los formatos de código del input = lo que emite el core, cierra B21).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| Subtítulo “Control en sitio de campos **asignados**” vs 7 pintados | `OcupacionService.php` (492) + `OcupacionController.php` (126) | **B61** filtro por asignación (P9) |
| Grilla de 7 campos con franjas “Libre/Ocupada” | `OcupacionService.php` (492) + `MisCampos.tsx` (543) | **B50** piscina mezclada, **B60** pendientes |
| Sección “Piscina — Turnos y cupos por perfil” (2/10, 0/10) | `MisCampos.tsx` (543) + `PiscinaDisponibilidadService.php` (147) | **B50** doble modelo, **B51** ambigüedad 2/10 |
| Input “RES-/RSV-/PIS-/ventanilla K699-…” | `MisCampos.tsx` (543) | **B52** confirma formatos reales (cierra B21) |
| “Ocupada RSV-20261025-1Q8CEA” con código visible | `OcupacionService.php` (492) | **B56** B4 NO aplica al admin (aclaración) |
| Fecha “25/10/2026” + “Actualizado hace un momento” | `OcupacionService.php` (492) | **B53** B44 se propaga |
| 7 campos vs 4 del público | `OcupacionService.php` (492) vs `CampoController::visibles` (140) | **B58** confirma B45 |

## Bugs (numeración continua: dashboard cerró en B48/D31/S27)

**B50 🔴 (EL GRAVE, paridad de modelo, lente b) — la piscina aparece DOS veces con dos modelos incompatibles.** Arriba, “H. PISCINA OLIMPICA” está en la grilla de franjas horarias (08:00–19:00, todas “Libre”) **como si fuera cancha**; abajo hay una sección separada “Piscina — Turnos y cupos por perfil del día” que muestra “10:00‑12:00 Mayores de 12 **2/10**” y “13:00‑15:00 Institución **0/10**”. Contradicción interna visible: **arriba dice “Libre” a las 10:00, abajo dice que a las 10:00‑12:00 ya hay 2 mayores reservados**. El mismo recurso, dos verdades. Con línea del árbol: `OcupacionService` (492) probablemente itera **todos** los campos activos y les pinta grilla horaria **sin distinguir `modalidad_reserva`**, y aparte monta la sección piscina → doble conteo. Es B43/B8/B47/B7 convergiendo en la pantalla del funcionario, donde duele más (él opera con eso).
*Mini‑prompt (verificar):* leer `OcupacionService.php` (492) → ¿filtra la grilla de franjas por `modalidad_reserva != turno_por_perfil`? Si no, la piscina **no debe** estar en la grilla binaria; dejarla solo en la sección de turnos/perfiles. Fix de backend (492), no de front.

**B61 🔴 (permisos, lente e / P9) — subtítulo “campos asignados” vs 7 campos pintados (todos).** El encabezado dice “Control en sitio de campos **asignados**”, pero la grilla muestra los **7** del sistema (Piscina, Coliseo, Palacio, Estadio, Frontón, Sintética, cancha nueva trinidad). Si el funcionario está asignado a 1 cancha (`AsignacionFuncionario` 26), debería ver 1, no 7. O el filtro por asignación **no aplica**, o el subtítulo **miente**. Conecta directo con **P9** (¿2 funcionarios misma cancha? aquí: ¿1 funcionario ve todas las canchas?) y **B48** (rol vs visibilidad).
*Mini‑prompt (verificar):* leer `OcupacionController.php` (126) + `OcupacionService.php` (492) → ¿reciben el `funcionario_id` y filtran por `asignaciones_funcionario`? Si no filtran, el subtítulo debe decir “todos los campos” o aplicar el filtro. **No tejo la exclusividad de P9** (eso es la migr 22, que sigue sin llegar), pero esta captura prueba que la ocupación **no** respeta asignaciones.

**B60 🔴 (operar si SIREB falla, lente d) — ninguna franja “En cobro”; las pendientes invisibles otra vez.** La grilla solo muestra “Libre” y “Ocupada”. El público (D9/S8) tiene estado “En cobro” naranja para pendiente; acá el funcionario **no ve** una franja pendiente distinto de libre. Si hay un zombie (P6/B17/B22), el funcionario no sabe que esa franja está bloqueada en cobro y podría intentar venderla. Conecta **B46** (dashboard oculta pendientes) y **B22** (card infinita): el mismo zombie, invisible en la pantalla de control.
*Mini‑prompt (verificar):* leer `OcupacionService.php` (492) → ¿trae `pendiente` y el front (`MisCampos.tsx` 543) lo pinta como “Libre” o lo omite? Debería pintar “En cobro” (naranja), alineado con la leyenda del público (S8 paridad cross‑capa).

**B51 (ambigüedad) — “2/10” y “0/10” no dicen si es reservado/cupo o disponible/cupo.** Con línea de `PiscinaTurnoPerfil` (39): `getDisponibleAttribute = max(0, cupo − reservado)`. El comprobante de piscina (B42) mostraba “2 personas”, así que “2/10” **probablemente** = 2 reservados de 10 (coherente), pero el label no lo dice. Conecta **B34** (público “Disponible: 10” sin aforo) y **B42**.
*Mini‑prompt:* en `MisCampos.tsx` (543), etiquetar “2 reservados / 10” o “8 libres / 10”, no “2/10” a secas.

**B52 ✅ (CIERRE CRUZADO de B21/S14/B41, no es bug nuevo — es evidencia) — el input enumera los formatos REALES y el `PTS-` del público era fantasma.** El placeholder dice “RES-… / RSV-… / PIS-… / código de ventanilla (ej. **K699‑86PZ‑J7GL**)”. Estos son los 4 formatos que el core emite (RES=cabecera cancha, RSV=franja cancha, PIS=cabecera piscina, K699‑…=ventanilla). El help del **público** (B21) prometía **`PTS-YYYYMMDD-XXXXXX`**, que **NO aparece acá** → el front público inventó un formato que el admin real no usa. **B21 queda cerrado con evidencia cruzada**: el fix es alinear el help público a `RES-/RSV-/PIS-/ventanilla`, no al revés. Y **S14/B41** (códigos flotando) se ordenan: son 4, no 5, y el `PTS-` se elimina.
*Mini‑prompt:* en `ConsultarEstado.tsx` (299), reemplazar el ejemplo `PTS-` del help por los formatos que confirma este input (B52). No tocar el admin, está bien.

**B58 ✅ (CIERRE CRUZADO de B45, no es bug nuevo — es evidencia) — 7 admin vs 4 público, ambos correctos, el label miente.** El admin pinta **7** campos (incluye Coliseo Cancha Vieja, Palacio de los Deportes, Estadio Gran Mamoré); el público mostraba **4** (cancha nueva trinidad, sintética, frontón, piscina). Los 3 de diferencia son excluidos por `visibles()` (CampoController 140: padres‑de‑variantes / `sin_reserva_online`). Entonces **B45 se resuelve**: el “7 activos” del dashboard y el “4 disponibles” del público son **ambos correctos** para su audiencia; el bug NO es el conteo, es que **ninguno de los dos labels explica qué incluye** (el funcionario cree que ve todos los operativos; el ciudadano cree que hay 4). 
*Mini‑prompt:* en `DashboardService.php` (134) y `Campos.tsx` (203), hacer los labels honestos (“7 registrados / N operativos” y “4 publicados”), no cambiar los números.

**B53 (sospecha, refina B44) — fecha “25/10/2026” otra vez.** Dashboard decía 24, público 25, real 26, y **acá ocupación dice 25**. Si es el mismo día real, B44/B36 (timezone) se propaga a ocupación. **No lo afirmo**: puede ser fecha de captura. La distinción la da leer `OcupacionService` (492).
*Mini‑prompt:* ver B44/S25 (helper “hoy Bolivia” compartido; ocupación es el 3er consumidor).

**B56 (aclaración de lente, NO bug) — el funcionario SÍ debe ver el código `RSV-20261025-1Q8CEA` en la franja ocupada.** Lo que en el público era ruido (B4, código interno en la card), acá es **herramienta**: el funcionario lo usa para “Verificar código de reserva” (asistencia, `OcupacionController` verificar codigo). Entonces **B4 NO aplica al admin**: no sacar el código de la ocupación. Aclaración para que el PO no me pida “limpiar códigos” acá.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **“Verificar código de reserva” con los 4 formatos listados** (B52) = el admin sabe exactamente qué aceptar; es la fuente de verdad de formatos. Proteger.
- **La franja ocupada muestra el código de franja `RSV-…`** (B56) = herramienta de asistencia. Proteger.
- **La sección “Piscina — Turnos y cupos por perfil” abajo** es el modelo **correcto** de piscina (perfiles/cupos); el bug es que **además** la pinten arriba como cancha (B50), no que exista abajo. Proteger la sección de abajo, eliminar la de arriba.
- **Horarios distintos por campo** (Palacio/Estadio hasta 21:00, otros hasta 19:00) = dato de `horarios_atencion`, **no bug** (B55).

## Didáctica / operación del funcionario (continuo: dashboard cerró en D31)

**D32 — la ocupación debe distinguir 3 estados, no 2 (Libre/Ocupada):** Libre / **En cobro** (pendiente, B60) / Ocupada (confirmada). Hoy el funcionario no ve la cola de pendientes y puede intentar vender una franja bloqueada. Conecta B46/B22/P6.
*Mini‑prompt:* ver B60.

**D33 — la piscina en ocupación debe verse UNA sola vez, con su modelo real (B50).** El funcionario de pileta opera cupos por perfil, no franjas binarias; verla dos veces con verdades contradictorias (“Libre” arriba, “2/10” abajo) lo hace desconfiar del sistema.
*Mini‑prompt:* ver B50.

**D34 — el subtítulo “campos asignados” debe cumplir su promesa o corregirse (B61).** Si el funcionario ve 7 canchas cuando está asignado a 1, pierde la noción de “lo mío”. O filtra, o renombra.
*Mini‑prompt:* ver B61.

## Sugerencias IA acotadas (continuo: dashboard cerró en S27)

**S28 — unificar la leyenda de estados cancha/piscina en un token compartido admin↔público (cierra B60/B50/S8 cross‑capa).** El público ya tiene 6 estados (Regular/Con luces/Seleccionada/Ocupada/En cobro/Pasada, D9); el admin solo 2. Que ambos lean del mismo mapa estado→color.
*Mini‑prompt:* al tejer, extraer el token de estados y consumirlo en `MisCampos.tsx` (543) y `GrillaDisponibilidad.tsx` (238).

**S29 — la sección piscina de ocupación debería reusar `PiscinaDisponibilidadService` (147), no una query paralela (cierra B50/B51).** Si abajo pinta “2/10” con una lógica distinta a la del público (“Disponible: 10”, B34), son dos fuentes del mismo cupo. Unificar en 147.
*Mini‑prompt:* verificar que 543 llame 147 (o el controller 126 lo haga) y no duplique el cálculo de cupo.

**S30 — “Verificar código” (B52) es el punto natural de asistencia retroactiva (P5).** El funcionario tipea el código y ve la franja; ahí debería poder marcar asistencia si el ciudadano llegó sin QR (conecta `MarcarAsistenciaService` 125, frente P5). Anotar, no tejer.
*Mini‑prompt:* cuando abramos P5 (asistencia), reusar este input como entrada de verificación.

## Lo que NO es bug (para que no persigas fantasmas)

- **“Libre” en casi todas las franjas** = normal, hay 1 reserva hoy (coherente con dashboard “1 confirmada”); no es que el sistema no cargue.
- **Palacio/Estadio con franjas 20:00‑21:00** = dato de `horarios_atencion` por campo (B55), no bug.
- **“cancha nueva trinidad” en minúscula** = dato SIREB (B5), no bug.
- **El código `RSV-…` visible en la franja ocupada** = herramienta del funcionario (B56), no ruido.
- **Botón “Verificar” sin resultado** = no se tipeó código, normal.
- **7 campos en ocupación** = correcto para el admin (B58); el bug es el label, no el número.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo modo dossier. Si querés arrancar a corregir Ocupación, me faltan los 4 archivos que deciden B50/B60/B61/B53: `OcupacionService.php` (492) + `OcupacionController.php` (126) + `OcupacionTest.php` (529) + `MisCampos.tsx` (543). Con esos tejo B50 (sacar piscina de la grilla binaria), B60 (pintar “En cobro”), B61 (filtrar por asignación o renombrar subtítulo) y B51/B53 con línea, en un commit verde (suite 226 + `npm run build`). **B52/B58/B56 no se tejen**: son cierres cruzados de bugs públicos (B21/B45) y aclaraciones de lente, no cambios de ocupación.

## Guardado

```text
ADMIN/OCUPACIÓN (pages/ocupacion/MisCampos.tsx 543 + OcupacionController 126 +
  OcupacionService 492 + OcupacionTest 529) — DOSSIER, NO tejí, NO pedí archivos,
  sigo modo "ver todo corregir después". Numeración continua (dashboard B44-B48/
  D29-D31/S25-S27 · ocupación B49-B61/D32-D34/S28-S30).
DOS CIERRES CRUZADOS CON EVIDENCIA (admin↔público, lo más valioso de la captura):
  B52✅ cierra B21/S14/B41: input "Verificar código" enumera formatos REALES
  RES-/RSV-/PIS-/ventanilla K699-86PZ-J7GL → el "PTS-" del help público (B21) es
  FANTASMA que el admin no usa; fix = alinear ConsultarEstado 299 a ESTOS formatos,
  NO tocar admin. B58✅ cierra B45: admin pinta 7 (incluye Coliseo/Palacio/Estadio),
  público 4 → ambos CORRECTOS (visibles() 140 excluye padres-variantes/sin_online);
  el bug es label no honesto en AMBOS lados (DashboardService 134 + Campos 203), no
  el conteo.
HALLAZGO GRAVE: B50🔴 piscina DOS veces con modelos incompatibles — arriba grilla
  binaria cancha "Libre 08-19", abajo sección "Turnos y cupos por perfil" "2/10,
  0/10"; contradicción visible (Libre a las 10 vs 2 mayores reservados a las 10-12).
  OcupacionService 492 itera TODOS los campos sin distinguir modalidad_reserva →
  piscina no debe estar en grilla binaria, dejarla solo en sección turnos (fix 492).
  Es B43/B8/B47/B7 convergiendo en cara del funcionario.
OTROS: B61🔴 subtítulo "campos ASIGNADOS" vs 7 pintados (todos) → filtro por
  asignacion_funcionario (26) NO aplica o subtítulo miente; conecta P9 (migr 22 sigue
  sin llegar, no tejo exclusividad) + B48. B60🔴 ninguna franja "En cobro" → pendientes
  invisibles (B46/B22/P6), funcionario puede vender franja bloqueada; pintar "En cobro"
  naranja alineado S8. B51 "2/10" ambiguo reservado/cupo vs disponible/cupo (PiscinaTurno
  Perfil 39 getDisponibleAttribute; conecta B34/B42). B53 fecha 25/10 (B44/B36 se
  propaga a ocupación, 3er consumidor del helper S25; sospecha, puede ser captura).
B56 ACLARACIÓN LENTE: código RSV- visible en franja ocupada = HERRAMIENTA funcionario
  (verificar asistencia OcupacionController), B4 NO aplica al admin, NO sacar.
ACIERTOS PROTEGER: input "Verificar código" con 4 formatos (B52, fuente de verdad);
  sección piscina abajo con perfiles/cupos (modelo CORRECTO, el bug es duplicarla arriba);
  horarios distintos por campo (Palacio/Estadio 20-21 = datos horarios_atencion, B55).
DIDÁCTICA/OP: D32 ocupación debe distinguir 3 estados Libre/En cobro/Ocupada (B60);
  D33 piscina UNA vez con modelo real (B50); D34 subtítulo "asignados" cumplir o
  renombrar (B61).
SUGERENCIAS: S28 unificar token estados admin↔público (B60/B50/S8 cross-capa, MisCampos
  543 + GrillaDisponibilidad 238); S29 sección piscina reusa PiscinaDisponibilidadService
  147 no query paralela (B50/B51); S30 "Verificar código" (B52) = punto natural asistencia
  retroactiva P5 (MarcarAsistenciaService 125), anotar no tejer.
NO-BUG: "Libre" casi todo=normal (1 reserva hoy, coherente dashboard); Palacio/Estadio
  20-21=datos (B55); minúsculas=dato SIREB (B5); RSV- visible=herramienta (B56);
  botón Verificar sin resultado=no tipeó; 7 campos=correcto admin (B58).
KB HONESTA: Rolldown circularDependency=false SÍ aplica (web-admin buildié) pero
  ocupación no mete imports nuevos visibles; React DevTools SÍ aplica (grillas/sección
  piscina son componentes, B50 desincronización es la herramienta); wa.me NO aplica
  (verificar código manual, no QR); SIREB aplica (B50 piscina doble modelo=B43/B8;
  B52 formatos los emite el core).
PUERTA CORRIGIR YA: pido OcupacionService 492 + Controller 126 + Test 529 + MisCampos
  543 pa' tejer B50/B60/B61/B51/B53 commit verde (226+build). B52/B58/B56 NO se tejen
  (cierres cruzados públicos / aclaraciones lente).
COLA REORDENADA: Fix3 piscina A/B (Turnos 164 + PiscinaReservaService 284) · A1 S3/S7/
  RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881, B48/B61 caen acá) · A4 Pago 576
  (RecaudacionesApiClient 415 + webhook 97) · D2 Bug C TarifaCampo 107 (Tarifas 703 +
  sync 305, crítico x B43/B37/B50) · C1→C2 · gates F1-F4 · E mobile (piscina real 260/
  145; P3-paridad 1073/85; B43/B36/B42/B39/B44/B50 se propagan a móvil; S16 también) ·
  higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP
  lote) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, S30 reusa input B52; P7
  reportes 245/135; P9 asignaciones 579/22/26, B61 evidencia parcial; P1/P2 auth 164/
  34/126; P3 reencuadrada S8; P8 reencuadrada S16; D11 NIT prepara P1/P2). DOSSIER
  PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B61/D29-D34/S25-S30.
```

En una línea: Ocupación es la captura que **cierra dos bugs públicos con evidencia cruzada** — **B52** (el input “Verificar código” enumera los formatos reales `RES-/RSV-/PIS-/K699-…`, así que el `PTS-` que prometía el help público en **B21** es un fantasma que el admin no usa, y el fix va en `ConsultarEstado.tsx` 299, no acá) y **B58** (el admin pinta 7 y el público 4, confirmando que **B45** no es error de conteo sino de label: ambos números correctos, ninguno lo explica) — y destapa **el bug más grave del tramo admin, B50 🔴: la piscina aparece dos veces con modelos incompatibles** (arriba franja binaria “Libre” de cancha, abajo turnos/perfiles “2/10, 0/10”, contradicción visible “Libre a las 10” vs “2 mayores reservados a las 10‑12”), que es B43/B8/B47 convergiendo en la cara del funcionario, con fix en `OcupacionService.php` (492); le siguen **B61 🔴** (subtítulo “campos asignados” vs 7 pintados = P9/permisos, evidencia parcial de que la ocupación no filtra por asignación) y **B60 🔴** (ninguna franja “En cobro”, las pendientes invisibles otra vez, B46/B22), más **B51** (“2/10” ambiguo) y **B53** (fecha 25 = B44 propagado); y aclaro **B56**: el código `RSV-` visible en la franja ocupada es herramienta del funcionario, así que **B4 no aplica acá**. **No tejí** — si querés corregir Ocupación ya, necesito `OcupacionService.php` (492) + `OcupacionController.php` (126) + `OcupacionTest.php` (529) + `MisCampos.tsx` (543) para tejer B50/B60/B61/B51/B53 en un commit verde, mientras que B52/B58/B56 no se tejen porque son cierres cruzados y aclaraciones de lente; decime si sigo dossier (próxima: ventanilla cancha+piscina, orden 1) o arranco a corregir con esos 4 archivos.
