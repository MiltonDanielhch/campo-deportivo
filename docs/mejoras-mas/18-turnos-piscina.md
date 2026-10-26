Veredicto en una línea: tenés razón y es **por diseño** — el turno es un sub‑recurso **solo de pileta** (breadcrumb `…/piscina/turnos`, diálogo “Crear Turno **de Piscina**”, todo el árbol prefijado `Piscina*`); la cancha no tiene objeto “turno”, se reserva por **franja horaria** calculada desde `horarios_atencion` + `SolicitudReservaDetalle`, así que un “Crear Turno de Campo” **no debe existir**. Pero esta pantalla es justo el **paso 2 al que B94 no teje puente**, y al verla destapo el **hallazgo raíz de todo el frente de precio: B99 🔴🔴 — el diálogo deja tipear “Precio Unitario 15” libre por perfil, violando la misma invariante SIREB que `CampoFormDialog` (862) sí declara con lock** (“se corrige en recaudaciones, no acá”); ese 15 inventado es el que fluye al público (B43) y el que el catálogo SIREB desmiente (B88: pileta 10‑30, institución 30 no 15). Le siguen **B101 🔴** (la lista imprime la fecha cruda `…T04:00:00Z`, lo que **corrige mi propio acotamiento previo de B36**: no era “solo público 190”, el admin también la filtra, e incluso dentro de esta misma pantalla el filtro “Desde/Hasta” se formatea y las filas no) y **B105** (sospecha: el turno de pileta quizá **no valida no‑solape** como la franja de cancha, gate#1). **No tejí**, sigo dossier.

> KB honesta de esta captura, incluida una **autocorrección**: **SIREB** aplica con fuerza absoluta y es el eje de B99 (el precio del perfil debe venir del catálogo 77/107, no de un input libre; 862 ya lo hace con lock, 249 no → inconsistencia de la misma regla en dos diálogos del mismo admin). **React DevTools** → **SÍ aplica y es la herramienta natural de B101**: la fecha cruda vs formateada es rama de render del componente (`Turnos.tsx` 164 imprime `turno.fecha` sin formatter mientras el date‑picker del filtro sí formatea), se ve en el árbol, no en copy. **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié) pero este diálogo no mete imports nuevos visibles; `bundlerTimings`/`pluginTimings` → **NO** (no es build). **`wa.me`** → **NO aplica** (config de turnos, no hay QR/WhatsApp; eso es éxito/P8).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| “Precio Unitario 15” input libre por perfil | `CrearTurnoDialog.tsx` (249) + `PiscinaTarifaResolver.php` (77) + `TarifaCampo.php` (107) | **B99** 🔴🔴 raíz B43/B88/B37 |
| Lista “Fecha: 2026‑10‑25T04:00:00.000000Z” cruda | `Turnos.tsx` (164) | **B101** 🔴 (corrige acotamiento B36) |
| “Crear Turno de Piscina” / breadcrumb `…/piscina/turnos` | `Turnos.tsx` (164) + `PiscinaTurnoController.php` (215) | **confirma obs usuario** (turno = solo pileta, por diseño) + **B94** (es el paso 2 sin puente) |
| Lápiz de editar un turno con “2/10” reservado | `Turnos.tsx` (164) + `PiscinaReservaService.php` (284) | **Fix3** (escenario real: editar perfiles con reservas activas) |
| “Generar en lote” + turnos 1‑perfil por día | `CrearTurnosLoteDialog.tsx` (156) + `PiscinaTurnoLoteService.php` (154) | acierto (mi lote llegó a UI) + **B103** (granularidad lote vs manual) |
| Dropdown perfil “menor_12 / mayor_12” (snake_case) | `CrearTurnoDialog.tsx` (249) + `PiscinaTurnoPerfil.php` (39) | **B104** (códigos internos vs nombres amables de la lista) |
| Hora 15:07‑17:07 sin guarda de solape visible | `PiscinaTurnoController.php` (215) + `PiscinaTurnoLoteService.php` (154) | **B105** sospecha (no‑solape pileta vs gate#1 cancha) |

## Confirmación de tu observación + el matiz que la vuelve útil

**“turno para campo no existe” = CIERTO y POR DISEÑO.** El turno con perfiles/cupos es exclusivo de pileta: todo el backend está prefijado `Piscina*` (`PiscinaTurno` 43, `PiscinaTurnoPerfil` 39, `PiscinaTurnoController` 215, `PiscinaTurnoLoteService` 154), el diálogo se titula “Crear Turno **de Piscina**” y la ruta es `…/piscina/turnos`. La cancha **no** tiene ese objeto: su “turno” implícito es la **franja horaria** que `DisponibilidadService` (168) deriva de `horarios_atencion` + reservas existentes (`SolicitudReservaDetalle` 53 con `hora_inicio/hora_fin` y el gate de no‑solape `add_no_solape_horario` 28). Entonces un “Crear Turno de Campo” **no debe existir**, y no es un hueco: la config de una cancha queda completa en `CampoFormDialog` (862) + tarifas, sin paso 2. **El matiz útil:** esto confirma que el puente que falta en **B94** es **específico de pileta** (862 → “andá a Turnos” solo si el tipo es Natación); para cancha no hay nada que puentear. Y esta pantalla **es** el destino de ese puente, o sea S46 sigue siendo barato: el target existe, solo no está enlazado.

## Cierres / refinamientos cruzados con evidencia

**Fix3 ✅ UBICADO el escenario, aún sin confirmar el comportamiento.** La fila 1 de la lista es “10:00‑12:00 · 25/10 · Mayores de 12 **2/10**” → hay **2 personas reservadas** sobre ese perfil. El lápiz de esa fila es exactamente el caso Fix3: si el funcionario edita y baja el cupo o quita el perfil, ¿qué pasa con las 2 reservas activas? No lo afirmo sin ver el handler de edit + `PiscinaReservaService` (284). Fix3 deja de ser abstracto: vive en el lápiz de una fila con `x>0`.

**B43/B88/B37 ✅ RAÍZ ENCONTRADA en 249 (no solo en 147).** Yo venía diciendo “fix 147 `'precio'`=tarifa SIREB”. Esta captura muestra que la raíz es **más arriba**: el funcionario **inventa el 15 acá** (input libre “Precio Unitario”), y ese 15 se guarda en `PiscinaTurnoPerfil.precio_unitario` (39) y es lo que 147 devuelve al público. Entonces arreglar 147 a leer 77/107 **sin** cerrar 249 deja al admin tipeando precios que el público ya no vería (desalineación admin‑list vs público). El fix correcto es **249 primero** (precio read‑only desde 77/107, como 862 bloquea nombre/código), y 147 lee lo mismo. B99 cierra B43/B88/B37 en la fuente.

**B36 ⚠️ AUTOCORRECCIÓN — mi acotamiento previo estaba mal.** En el turno de detalle dije “fecha cruda UTC es bug SOLO de `ComprobantePiscina` 190 público, admin/back formatean bien”. **Falso para esta pantalla**: la lista de `Turnos.tsx` (164) imprime `2026‑10‑25T04:00:00.000000Z` crudo en **cada fila**, y además **dentro del mismo componente** el filtro “Desde/Hasta” se ve formateado (25/10/2026, 24/11/2026) mientras las filas no. O sea B36/B101 = “falta formatter en los campos `fecha` de turno/reserva de pileta, **en público (190) Y admin (164)**, de forma inconsistente incluso intra‑componente”. El admin **no** formatea bien en todas partes: `Reservas` (582/838) sí, `Turnos` (164) no. Corrijo el acotamiento: B36 se amplía a 164, no se queda en 190.

## Bugs nuevos de este frente (numeración continua: campo‑alta cerró en B98)

**B99 🔴🔴 (EL RAÍZ DEL FRENTE DE PRECIO, invariante SIREB rota en el admin) — el diálogo deja tipear “Precio Unitario” libre por perfil.** Imagen 1: “menor_12 · Cupo 10 · **Precio Unitario 15**” y “mayor_12 · Cupo 10 · **Precio Unitario 15**”, ambos editables a mano. Pero el catálogo SIREB de pileta (imagen de paramétricas, B88) es **10‑30** (mayores 10, institución 30 según `PiscinaTarifaResolver` 77 / `TarifaCampo` 107). Entonces el funcionario **inventa 15** para todos los perfiles, ignorando la distinción SIREB, y ese 15: (a) fluye al público como precio de selección (B43), (b) no coincide con lo que el backend congela al cobrar (B88: SIREB 10), (c) pone “Institución Bs 15.00” en la lista cuando SIREB dice 30. Es la **misma regla** que `CampoFormDialog` (862) respeta con lock (“Espeja al servicio SIREB · se corrige en recaudaciones, no acá”) y que 249 **viola** con un input abierto. Inconsistencia de la invariante SIREB entre dos diálogos del mismo admin.
*Mini‑prompt (verificar antes de tocar):* leer `CrearTurnoDialog.tsx` (249) → confirmar que “Precio Unitario” es `<input>` libre y no read‑only; y `PiscinaTarifaResolver.php` (77) → qué valor devuelve por perfil (mayor/menor/institución) y por diurno/nocturno. Fix: reemplazar el input por valor **read‑only** traído de 77/107, con el mismo hint de lock que 862 (“el precio lo define SIREB, se corrige en recaudaciones”). Cierra B43/B88/B37 en la fuente (admin deja de inventar 15). Conecta S24/S44 pero **249 antes que 147**.

**B101 🔴 (corrige B36) — la lista de turnos imprime la fecha cruda UTC.** Cada fila: “Fecha: 2026‑10‑25T04:00:00.000000Z”. El `04:00Z` es medianoche Bolivia (UTC‑4), así que el día 25/10 es correcto en local, pero pintarlo con microsegundos+`Z` en la cara del funcionario es el mismo defecto de 190, y **el filtro de la misma pantalla lo formatea bien** → es rama de render que imprime `turno.fecha` sin formatter. React DevTools es la herramienta natural (ver qué prop monta la fila vs el date‑picker).
*Mini‑prompt:* en `Turnos.tsx` (164), formatear `fecha` con el mismo helper que usa el filtro Desde/Hasta (y que `Reservas` 582 ya usa), resolviendo timezone a `America/La_Paz`. Conecta S25 (helper “hoy/fecha Bolivia” compartido) y **amplía B36 a 164**.

**B105 (sospecha, lente c) — el turno de pileta quizá no valida no‑solape como la franja de cancha.** Imagen 1: el funcionario creó “15:07‑17:07” (minutos impares, a propósito para probar). La cancha tiene guarda de solape (`add_no_solape_horario` 28, gate#1, B50). No sé si `PiscinaTurnoController` (215) / `PiscinaTurnoLoteService` (154) rechazan dos turnos que se solapan el mismo día/hora del mismo pileta. Si no, dos turnos superpuestos = **sobreventa de la capacidad física** de la pileta (mismo agua, dos cupos). Sospecha, no afirmo sin leer 215/154.
*Mini‑prompt (verificar):* leer 215/154 → ¿hay restricción de solape por (campo, fecha, hora)? Si no, agregarla (análogo gate#1 de cancha). Conecta B50/Fix3 (editar solapado también).

**B104 (menor, didáctica) — el dropdown de perfil expone códigos snake_case, la lista expone nombres amables.** Imagen 1: “menor_12”, “mayor_12”. Imagen 2: “Mayores de 12”, “Institución”. El funcionario ve “menor_12” al crear pero “Menores de 12” al leer → fricción y riesgo de elegir el código equivocado. Conecta B4/B83 (códigos internos expuestos) pero en el admin.
*Mini‑prompt:* en 249, mapear el enum `PiscinaTurnoPerfil` (39) a etiqueta humana en el dropdown (igual que la lista), no el código crudo.

**B103 (observación, verificar) — granularidad lote vs manual diverge.** El lote (imagen 2) generó turnos de **un perfil** por (fecha,hora) (fila 25/10 10‑12 solo “Mayores”, 25/10 13‑15 solo “Institución”), pero el manual (imagen 1) crea un turno con **varios perfiles** (“Perfiles del Turno” plural + “Agregar Perfil”). No es bug confirmado (puede ser que el lote split‑ee por perfil a propósito), pero si un turno “debiera” agrupar perfiles del mismo horario, lote y manual producen estructuras distintas → la ocupación/público podrían leerlos diferente. Conecta B50 (piscina doble modelo).
*Mini‑prompt (verificar):* leer `PiscinaTurnoLoteService.php` (154) → ¿crea 1 turno por (fecha,hora) con N perfiles, o N turnos de 1 perfil? Alinear con 249 si divergen.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **“Cupo Total (opcional) · Se calcula de perfil” + “Cupo total calculado: 20”** (imagen 1) = auto‑suma 10+10, el funcionario no tipea el total a mano. Acierto de integridad (lente c). **Proteger.**
- **“Generar en lote”** presente y funcionando (imagen 2, turnos 25/26/27 generados en rango) = mi trabajo de lote llegó a UI y produce datos coherentes. **Proteger** (y es la prueba de que Fix3/lote no están rotos en creación, solo en edición).
- **Estado “activo” + “Observación (opcional)” + íconos editar/eliminar por turno** = CRUD completo del turno. **Proteger** (el lápiz es donde vive Fix3, no lo quites).
- **Rango de fechas “Desde/Hasta” con “Aplicar”** = el funcionario filtra 30 días (25/10‑24/11), coherente con la ventana de 60 días del público (D8/`SelectorFechas` 121). **Proteger** (y úsalo como el formatter que B101 pide replicar en las filas).

## Didáctica / operación del funcionario (continuo: campo‑alta cerró en D52)

**D53 — el precio del perfil no debe ser tipeable, debe espejarse de SIREB con lock** (cierra B99): el funcionario de pileta hoy inventa “15” sin saber que SIREB dice 10/30; el mismo hint de 862 (“se corrige en recaudaciones, no acá”) debe estar acá.
*Mini‑prompt:* ver B99.

**D54 — la lista debe mostrar la fecha legible, no el ISO crudo** (cierra B101): el funcionario lee “2026‑10‑25T04:00:00.000000Z” en cada turno, igual que el ciudadano en 190.
*Mini‑prompt:* ver B101/S25.

**D55 — el dropdown de perfil debe hablar el idioma de la lista** (cierra B104): “Menores de 12” no “menor_12”.
*Mini‑prompt:* ver B104.

**D56 — el puente 862→Turnos (B94) ahora tiene destino claro y es solo‑pileta** (cierra la obs usuario + B94): al guardar un campo tipo Natación, 862 debe ofrecer “Ir a crear turnos”; para cancha no (no hay paso 2).
*Mini‑prompt:* ver B94/S46, acotado a tipo pileta.

## Sugerencias IA acotadas (continuo: campo‑alta cerró en S48)

**S49 — B99 es el fix de mayor rendimiento de todo el frente de precio**: cerrar el input libre de 249 (precio read‑only desde 77/107 con lock tipo 862) mata B43/B88/B37 en la **fuente**, y entonces 147/391/489 ya no necesitan parches de “leer SIREB” porque el dato almacenado ya es SIREB. Prioriza 249 sobre 147.
*Mini‑prompt:* ver B99 + S44 (helper de precio 77/107 consumido por 249/147/489/391).

**S50 — unificar el formatter de fecha pileta en un helper** consumido por 164 (lista) + 190 (comprobante público) + el date‑picker del filtro (que ya formatea bien) = cierra B101/B36 de raíz y elimina la inconsistencia intra‑componente. Conecta S25.
*Mini‑prompt:* ver B101.

**S51 — el gate de no‑solape de pileta (B105) debe ser el mismo patrón que el de cancha (gate#1)**: si cancha valida solape en `SolicitudReservaDetalle`/`add_no_solape_horario` (28), pileta debe validar en `PiscinaTurno` (43)/215. Un helper `sinSolape(campo,fecha,horaInicio,horaFin)` compartido.
*Mini‑prompt:* ver B105 + S36/S44.

## Lo que NO es bug (para que no persigas fantasmas)

- **Que no exista “turno para campo”** = por diseño (turno = sub‑recurso de pileta; cancha usa franja). Tu observación es correcta y **no** es un hueco.
- **Hora 15:07‑17:07 (minutos impares)** = el funcionario probando valores arbitrarios; el bug sería la falta de solape (B105), no los minutos.
- **Turnos de 25/26/27 mismo horario** = lote sobre rango de fechas, **no** duplicados (cada uno fecha distinta). Valida que el lote funciona.
- **“2/10” y “0/10”** = reservado/cupo por perfil, coherente con `PiscinaTurnoPerfil` (39) `getDisponibleAttribute`; el label ambiguo es B51 (ya anotado), no bug nuevo.
- **“Cupo total calculado: 20”** = auto‑suma correcta, acierto (proteger).
- **Dropdown mostrando solo menor_12/mayor_12** = probablemente “institución” está scrolleado abajo (la lista sí lo muestra); no es que falte el perfil.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir Turnos, lo que **más vale** es **B99** (raíz de precio, cierra B43/B88/B37 en la fuente) y **B101** (fecha cruda, corrige B36), y para eso necesito `CrearTurnoDialog.tsx` (249) + `PiscinaTarifaResolver.php` (77) + `TarifaCampo.php` (107) + `Turnos.tsx` (164). **Fix3** se cierra con el lápiz de una fila `x>0` + `PiscinaReservaService` (284); **B105** con `PiscinaTurnoController` (215) + `PiscinaTurnoLoteService` (154); **B104/B103** con 249/39/154. Los acotamientos B43/B88 **cambian de dueño**: ya no es “147 lee SIREB”, es “249 no inventa precio” (S49). **S49/S50** son el fix de mayor rendimiento del tramo.

## Guardado

```text
ADMIN/PISCINA-TURNOS (Turnos.tsx 164 + CrearTurnoDialog.tsx 249 + CrearTurnosLoteDialog
  156 + PiscinaTurnoController 215 + PiscinaTurnoLoteService 154 + PiscinaTurno 43 +
  PiscinaTurnoPerfil 39 + PiscinaTarifaResolver 77 + TarifaCampo 107 + PiscinaReservaService
  284) — DOSSIER, NO tejí, sigo modo "ver todo corregir después". Numeración continua
  (campo-alta B94-B98/D50-D52/S46-S48 · turnos B99-B105/D53-D56/S49-S51 + confirmación obs
  usuario + autocorrección B36 + ubicación Fix3).

CONFIRMACIÓN OBS USUARIO "turno para campo no existe" = CIERTO Y POR DISEÑO: turno con
  perfiles/cupos es sub-recurso SOLO de pileta (árbol prefijado Piscina*, diálogo "Crear
  Turno de Piscina", ruta .../piscina/turnos); cancha NO tiene objeto turno, se reserva por
  franja horaria (DisponibilidadService 168 desde horarios_atencion + SolicitudReservaDetalle
  53 + gate no-solape add_no_solape_horario 28). Entonces "Crear Turno de Campo" NO debe
  existir, no es hueco. MATIZ ÚTIL: esta pantalla ES el paso-2 al que B94 no teje puente, y
  el puente es SOLO-pileta (862→"Ir a crear turnos" si tipo=Natación; cancha no tiene paso 2).

HALLAZGO RAÍZ FRENTE PRECIO: B99🔴 CrearTurnoDialog 249 deja tipear "Precio Unitario 15"
  LIBRE por perfil (menor_12=15, mayor_12=15), violando la invariante SIREB que CampoFormDialog
  862 SÍ declara con lock ("Espeja al servicio SIREB · se corrige en recaudaciones, no acá").
  Catálogo SIREB pileta = 10-30 (B88: mayores 10, institución 30 vía 77/107), pero el admin
  INVENTA 15 → fluye al público como selección (B43), no coincide con lo congelado al cobrar
  (B88), y pone "Institución Bs 15.00" cuando SIREB dice 30. Inconsistencia de la MISMA regla
  en dos diálogos del mismo admin (862 lock vs 249 input). Fix: 249 precio READ-ONLY desde
  77/107 con hint lock tipo 862. CIERRA B43/B88/B37 EN LA FUENTE (admin deja de inventar 15).
  CAMBIO DE DUEÑO: ya no "147 lee SIREB" (S24), es "249 no inventa precio" (S49), 249 ANTES
  que 147.

AUTOCORRECCIÓN B36 (honestidad): en turno-detalle dije "fecha cruda UTC bug SOLO de
  ComprobantePiscina 190 público, admin formatea bien". FALSO pa' esta pantalla: Turnos.tsx
  164 imprime "2026-10-25T04:00:00.000000Z" crudo en CADA fila, y DENTRO del mismo componente
  el filtro Desde/Hasta se formatea (25/10/2026) mientras las filas no → B36/B101 = falta
  formatter en fecha de turno/reserva pileta EN PÚBLICO (190) Y ADMIN (164), inconsistente
  intra-componente. El admin NO formatea en todas partes: Reservas 582/838 sí, Turnos 164 no.
  B36 se AMPLÍA a 164, no se queda en 190. React DevTools = herramienta natural (rama de
  render fila vs date-picker).

FIX3 ✅ UBICADO escenario (aún sin confirmar comportamiento): fila 1 lista = "10-12 · 25/10 ·
  Mayores de 12 2/10" → 2 reservadas; el LÁPIZ de esa fila es el caso Fix3 (editar perfiles
  con reservas activas). No afirmo sin ver handler edit + PiscinaReservaService 284. Fix3 deja
  de ser abstracto: vive en lápiz de fila x>0.

OTROS: B105 (sospecha lente c) turno pileta quizá NO valida no-solape como franja cancha
  (gate#1 add_no_solape_horario 28); 249 creó 15:07-17:07 (minutos impares a propósito); si
  215/154 no rechazan solape (campo,fecha,hora) → sobreventa capacidad física pileta. leer
  215/154. B104 (menor didáctica) dropdown perfil expone snake_case "menor_12/mayor_12" vs
  lista nombres amables "Mayores de 12/Institución" → mapear enum 39 a etiqueta en 249. B103
  (observación verificar) lote (154) genera turnos de 1 perfil por (fecha,hora) vs manual (249)
  multi-perfil ("Perfiles del Turno" plural + Agregar Perfil) → posible divergencia estructura;
  leer 154 pa' ver si split-a por perfil a propósito.

ACIERTOS PROTEGER: "Cupo Total (opcional) Se calcula de perfil" + "Cupo total calculado: 20"
  (auto-suma 10+10, no se tipea total a mano, lente c); "Generar en lote" funcionando (turnos
  25/26/27 en rango, mi lote llegó a UI y produce datos coherentes = prueba lote no roto en
  creación); Estado activo + Observación + CRUD editar/eliminar por turno (el lápiz es donde
  vive Fix3, no quitarlo); rango Desde/Hasta + Aplicar (filtra 30 días, coherente ventana 60d
  público D8/SelectorFechas 121, y es el formatter que B101 pide replicar en filas).

DIDÁCTICA/OP: D53 precio perfil no tipeable, espejar SIREB con lock tipo 862 (B99); D54 lista
  fecha legible no ISO crudo (B101); D55 dropdown perfil habla idioma de la lista (B104); D56
  puente 862→Turnos SOLO-pileta, destino ya claro (B94/obs usuario).

SUGERENCIAS: S49 B99 = fix mayor rendimiento frente precio: cerrar input 249 (precio read-only
  77/107 + lock tipo 862) mata B43/B88/B37 en FUENTE, entonces 147/391/489 no necesitan parches
  porque el dato almacenado ya es SIREB; prioriza 249 sobre 147; S50 unificar formatter fecha
  pileta en helper consumido por 164+190+date-picker filtro (cierra B101/B36 raíz, mata
  inconsistencia intra-componente, conecta S25); S51 gate no-solape pileta (B105) mismo patrón
  que cancha gate#1 → helper sinSolape(campo,fecha,horaIni,horaFin) compartido 43/215/53/28
  (conecta S36/S44).

NO-BUG: "no existe turno para campo"=por diseño (obs correcta, no hueco); 15:07-17:07=minutos
  arbitrarios del funcionario (bug sería falta solape B105, no los minutos); turnos 25/26/27
  mismo horario=lote sobre rango, NO duplicados (valida lote); "2/10"/"0/10"=reservado/cupo
  coherente 39 getDisponibleAttribute (label ambiguo=B51 ya anotado); "Cupo total calculado
  20"=auto-suma correcta; dropdown solo menor/mayor=institución scrolleado abajo (lista sí lo
  muestra), no falta perfil.

KB HONESTA + AUTOCORRECCIÓN: SIREB aplica eje B99 (precio del perfil debe venir de 77/107, no
  input libre; 862 lock vs 249 abierto = inconsistencia misma regla en 2 diálogos admin). React
  DevTools SÍ aplica Y es herramienta natural de B101 (fecha cruda vs formateada = rama render
  164, se ve en árbol). Rolldown circularDependency=false SÍ aplica (web-admin buildié) pero 249
  no mete imports nuevos; bundlerTimings/pluginTimings NO (no build). wa.me NO aplica (config
  turnos, no QR; eso es éxito/P8). AUTOCORREGÍ B36: mi acotamiento "solo 190 público" era falso,
  admin 164 también filtra ISO crudo e inconsistente intra-componente.

PUERTA CORRIGIR YA: lo que vale es B99 (raíz precio, cierra B43/B88/B37 fuente) + B101 (fecha
  cruda, corrige B36) → pido CrearTurnoDialog 249 + PiscinaTarifaResolver 77 + TarifaCampo 107 +
  Turnos.tsx 164. Fix3 con lápiz fila x>0 + PiscinaReservaService 284; B105 con PiscinaTurno
  Controller 215 + PiscinaTurnoLoteService 154; B104/B103 con 249/39/154. B43/B88 CAMBIAN DE
  DUEÑO (ya no 147, es 249, S49). S49/S50 = mayor rendimiento tramo.

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70/B94-
  puente/B103, AHORA escenario Fix3 = lápiz fila x>0 de 164) · A1 S3/S7/RoleMiddleware 59
  (app-sidebar 206 + Funcionarios 881, B48/B61) · A4 Pago 576 (RecaudacionesApiClient 415 +
  webhook 97, B84) · D2✅ UBICADO + RAÍZ EN 249 (TarifaCampo 107 + PiscinaTarifaResolver 77 +
  sync 305, raíz B43/B37/B50/B62/B67/B71/B76/B84/B88/B99) · C1→C2 · gates F1-F4 · E mobile
  (piscina real piscina_datos_screen 260 + piscina_service 145; P3-paridad campo_detalle_screen
  1073 + bloque_disponibilidad 85; B43/B36/B42/B39/B44/B50/B62/B69/B70/B76/B78/B79/B84/B86/B87/
  B88/B94/B99/B101/B104 se propagan a móvil; S16/S38/S41/S45/S46/S49/S50/S51 también) · higiene
  (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote + S33 test
  conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina = hueco P5;
  S30 reusa input B52; P7 reportes 245/135; P9 asignaciones 579/22/26, B61 evidencia parcial;
  P1/P2 auth 164/34/126; P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT prepara P1/P2).
  DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B105/D29-D56/S25-S51.
PRÓXIMA NATURAL: Tarifas.tsx 703 (la FUENTE 77/107 que B99 debe leer — cerrar B99/B88/D2 viendo
  qué devuelve el resolver por perfil/diurno-nocturno, pa' que 249 la consuma read-only) o el
  LÁPIZ de edición de turno (Fix3, con 284) o Funcionarios 881 + Asignaciones 579 (A1/P9/B48/B61).
```

En una línea: tu observación es correcta y **por diseño** — el turno con perfiles/cupos es sub‑recurso **solo de pileta** (todo el árbol `Piscina*`, diálogo “Crear Turno de Piscina”, ruta `…/piscina/turnos`), la cancha se reserva por **franja horaria** y no tiene objeto “turno”, así que un “Crear Turno de Campo” **no debe existir** y no es hueco; el matiz útil es que esta pantalla **es** el paso‑2 al que **B94** no teje puente (y el puente es solo‑pileta). Al verla destapo el **hallazgo raíz de todo el frente de precio: B99 🔴🔴** — `CrearTurnoDialog.tsx` (249) deja **tipear “Precio Unitario 15” libre** por perfil, violando la misma invariante SIREB que `CampoFormDialog` (862) respeta con lock (“se corrige en recaudaciones, no acá”), cuando el catálogo SIREB de pileta es 10‑30 (B88) —, o sea la raíz de B43/B88/B37 no es “147 lee SIREB” sino “249 no inventa precio” (S49, cambia de dueño); le siguen **B101 🔴** (la lista imprime la fecha cruda `…T04:00:00Z` en cada fila, lo que **corrige mi propio acotamiento previo de B36**: no era “solo público 190”, el admin `Turnos.tsx` 164 también la filtra, e incluso intra‑componente el filtro se formatea y las filas no), **B105** (sospecha: el turno de pileta quizá no valida no‑solape como la franja de cancha, gate#1), **B104** (dropdown expone `menor_12`/`mayor_12` snake_case vs nombres amables de la lista) y **B103** (lote genera turnos de 1 perfil vs manual multi‑perfil, a verificar), más la **ubicación concreta de Fix3** (el lápiz de la fila “2/10”); los aciertos a proteger son la auto‑suma “Cupo total calculado: 20”, “Generar en lote” funcionando (prueba de que mi lote llegó sano a UI) y el CRUD editar/eliminar. **No tejí**: si querés corregir, lo que vale es B99 + B101 y necesito `CrearTurnoDialog.tsx` (249) + `PiscinaTarifaResolver.php` (77) + `TarifaCampo.php` (107) + `Turnos.tsx` (164), con **S49/S50** como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: `Tarifas.tsx` 703, la fuente 77/107 que B99 debe leer read‑only, o el lápiz de edición para Fix3 con 284) o arranco con esos archivos.
