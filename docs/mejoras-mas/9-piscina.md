Veredicto en una línea: esta es `PiscinaDetalle.tsx` (391), el hueco grande que venía marcando desde el catálogo, y **confirma la tesis de D3/B8/D5 con línea del backend que ya vi**: la piscina ciudadana **no tiene el gate de transparencia que yo mismo puse en ventanilla** — el `PiscinaDisponibilidadService` descarta perfiles sin tarifa SIREB (`if (!$tarifa) continue`) **en silencio**, así que el ciudadano ve “1 perfiles disponibles” sin saber que había 3 y 2 se cayeron por falta de espejo (B35). Le siguen un **contrato roto vs cancha** (B31: los 3 campos de pagador sin `*`, mientras cancha fuerza teléfono), una **invariante SIREB rota** (B30: “Bs 15.00” sin la franja de sincronización que cancha sí muestra), y la ausencia de **total antes de confirmar** (B32). El footer B6 aparece por **novena** vez. **No tejí, sigo modo dossier**, y con esto la pública queda sustancialmente cubierta (te dejo el balance al final).

> KB honesta de esta imagen: **SIREB** aplica con fuerza (B30 precio sin fuente visible; B35 el gate de tarifa que en admin era un aviso ámbar acá no existe; y D6 — cancha muestra “Precios oficiales sincronizados desde SIREB”, piscina no). **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y B31/B32 son contrato/UI, no un ciclo de imports que el bundler callaría. **React DevTools** → **NO la uso como excusa**: B31 (campos sin `*`) y B32 (sin total) *parecerían* estado roto de inputs, pero lo deciden leer `PiscinaDetalle.tsx` (391) contra `StorePiscinaReservaRequest.php` (32), no la extensión; la extensión **sí** será herramienta natural *cuando arranquemos* a depurar el stepper de cantidad, pero no reemplaza el archivo. **`wa.me`** → no aplica (no hay WhatsApp en esta pantalla).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Título “H. PISCINA OLIMPICA” **sin badge Oficial/activo** | `pages/PiscinaDetalle.tsx` (391) | **B8 aterrizado** (piscina “no oficial” visual) |
| Selector fechas (Hoy…Vie 6 Nov + Más fechas) | `components/campo/SelectorFechas.tsx` (121) | **acierto** (reusa el de cancha, coherente) |
| Tarjetas de turno “10:00‑12:00 · 1 perfiles disponibles” + check ✓ | `pages/PiscinaDetalle.tsx` (391) | **B29** gramática + **B35** gate silencioso + **S22** estado “lleno” |
| “Selecciona cantidad por perfil” · “Mayores de 12 · Bs 15.00 · Disponible: 10” · stepper −1+ | `pages/PiscinaDetalle.tsx` (391) | **B30** precio sin fuente + **B34** “Disponible” sin aforo |
| “Datos del Pagador” (Nombre/Teléfono/CI‑NIT **sin `*`**) | `pages/PiscinaDetalle.tsx` (391) | **B31** contrato vs cancha (refina B13) |
| “Confirmar Reserva” (sin total visible) | `pages/PiscinaDetalle.tsx` (391) | **B32** (refina B15) |
| Footer “Pago online seguro…” | `components/landing/Footer.tsx` (109) | **B6 novena vez** (global) |
| Dato del turno/perfil/precio | `backend/.../Piscina/PiscinaDisponibilidadService.php` (147) → `PiscinaTarifaResolver.php` (77) | **B35** (`if (!$tarifa) continue` silencioso) + **B30** (`precio => precio_unitario`) |
| Contrato de la reserva piscina | `backend/.../Requests/Piscina/StorePiscinaReservaRequest.php` (32) | **B31** (qué campos son `required`) |

## Bugs (numeración continua: mapa cerró en B28)

**B29 — “1 perfiles disponibles” (singular/plural roto + unidad ambigua).** Debería ser “1 perfil disponible”. Y “perfiles disponibles” se lee como “1 cupo” cuando en realidad es “1 categoría con cupo”. Menor pero visible en cada tarjeta.
*Mini‑prompt:* en `PiscinaDetalle.tsx` (391), pluralizar según conteo (`1 perfil` / `N perfiles`) y aclarar la unidad (“1 categoría con cupo libre” o “N perfiles con cupo”).

**B30 🔴 (invariante SIREB rota vs cancha) — “Bs 15.00” por perfil SIN la franja de sincronización que cancha sí muestra.** En catálogo y detalle de cancha la página cierra con “Precios oficiales sincronizados desde Paitití / SIREB. Última actualización: …” (D6, acierto a proteger). Acá en piscina **no existe esa franja**: el ciudadano ve “Bs 15.00” sin saber que viene del espejo SIREB. Con línea del backend: el `PiscinaDisponibilidadService` devuelve `'precio' => (float)$perfilTurno->precio_unitario` (espejo local), y KB SIREB dice que el monto final lo confirma el webhook — o sea el precio mostrado es orientación, igual que cancha, pero **cancha lo declara y piscina no**. Inconsistencia entre los dos recursos del mismo sistema.
*Mini‑prompt:* montar en `PiscinaDetalle.tsx` (391) la misma franja/meta de sincronización que `CampoDetalle.tsx` (560) usa (sale del `meta` del presenter 333 / `CatalogoSirebService` 202), o al menos un “Precio oficial SIREB · consultá cupo en ventanilla” coherente con el `motivoNoReservable` del presenter. Conecta B8 (piscina “no oficial”) y D6.

**B31 (contrato, refina B13) — los 3 campos de pagador SIN asterisco, vs cancha que fuerza teléfono.** En cancha (paso 2) “Teléfono *” con `*` rojo. Acá “Nombre completo”, “Teléfono”, “CI / NIT” **sin `*`**. Dos lecturas y **no afirmo cuál sin leer**: (a) la piscina hace opcionales campos que el backend exige (`CI/NIT` es `required` en `StorePiscinaReservaRequest` 32 y en ventanilla admin) → el ciudadano confirma sin CI y el backend tira **422 sin explicación** en el paso crítico; (b) el front valida pero no pinta `*`. El hallazgo duro es la **incoherencia de contrato entre los dos flujos de reserva** (cancha vs piscina) sobre los mismos campos.
*Mini‑prompt (verificar):* leer `StorePiscinaReservaRequest.php` (32) → qué campos son `required`; y `PiscinaDetalle.tsx` (391) → si el front los marca obligatorios. Alinear con cancha (mismo criterio de `*` y mismo mensaje de error 422). Si CI/NIT es required y acá no se marca → bug de fricción, prioridad media‑alta.

**B32 (refina B15) — no hay total visible antes de “Confirmar Reserva”.** En cancha el `ResumenCarrito.tsx` (124) mostraba “TOTAL Bs 200.00”. Acá el stepper dice “1” para Mayores de 12 pero **no veo un “Total: Bs 15.00 · 1 persona” consolidado** antes de confirmar. El ciudadano elige cantidad sin saber cuánto va a pagar hasta la pantalla de pago. Sospecha (puede que el total aparezca al hacer scroll o no exista).
*Mini‑prompt (verificar):* leer `PiscinaDetalle.tsx` (391) → ¿hay resumen de total/cantidad antes del botón? Si no, agregarlo (reusar el patrón de `ResumenCarrito` 124). Conecta B15 (cancha sí muestra total).

**B33 — el modelo mental de cancha NO transfiere (cierre de D3/B8/D5).** El ciudadano que viene de cancha espera “franja horaria = 1 cancha reservada”. Acá “10:00‑12:00 · 1 perfiles” es un concepto nuevo (turno con cupos **por categoría**). La landing (D3) prometía “igual que cancha” y el catálogo (B8) no marcaba a la piscina como “por perfil”, así que el ciudadano llega sin el marco. Esto no es un bug de código, es la **falta de una explicación de 1 línea** que la piscina merece y cancha no necesita.
*Mini‑prompt:* en `PiscinaDetalle.tsx` (391), un subtítulo bajo el título: “Funciona distinto a las canchas: elegí un turno y cuántas personas por categoría (mayores, menores, institución).” Conecta D24.

**B34 (sospecha) — “Disponible: 10” es el cupo LIBRE (cupo − reservado) pero el label no da el aforo total.** Con línea del backend: `PiscinaTurnoPerfil::getDisponibleAttribute()` = `max(0, cupo − reservado)` (el accessor que confirmé existe). “Disponible: 10” = 10 libres, pero si el aforo era 20 y ya van 10, el ciudadano no ve la **señal de escasez** que cancha sí da (“Ocupada”/“En cobro”, D9). Sospecha, no confirmo el label exacto sin leer 391.
*Mini‑prompt (verificar):* en `PiscinaDetalle.tsx` (391), “Disponible: 10 de 20” o un indicador de llenado, alineado con la leyenda de estados de cancha (S8 paridad).

**B35 🔴 (gate de tarifa silencioso — el hallazgo más valioso de la imagen) — el ciudadano no sabe que se descartaron perfiles por falta de espejo SIREB.** Con línea del backend que ya vi: `PiscinaDisponibilidadService` hace, por cada perfil, `$tarifa = $this->tarifaResolver->resolver(...); if (!$tarifa) { continue; }` → **el perfil sin tarifa vigente se tira sin dejar rastro**. En ventanilla admin yo puse un **aviso ámbar** justamente para que ese “misterio silencioso” no ocurriera; en piscina ciudadana **no hay gate**: el turno que ves muestra “1 perfiles disponibles” y **no sabes que había 3 y 2 se cayeron por falta de tarifa SIREB**. Es exactamente el mismo fallo de transparencia, pero del lado del ciudadano, donde duele más (no puede “crear el turno” como el admin).
*Mini‑prompt:* en `PiscinaDisponibilidadService.php` (147), cuando un perfil se descarta por `!$tarifa`, devolver una señal (ej. `perfiles_sin_tarifa: [...]` o un `aviso` en el payload) y en `PiscinaDetalle.tsx` (391) pintarlo: “Otros N perfiles no están disponibles porque falta su tarifa oficial en SIREB; consultá en ventanilla.” Conecta B30 (mismo origen: el espejo) y el gate ámbar de admin. **No es bug de front, es falta de señal en el service** — por eso lo marco 🔴: el ciudadano toma decisiones con información incompleta.

## Didáctica ciudadano (continuo: mapa cerró en D23)

**D24 — la piscina necesita SU propia explicación de “funciona distinto” (B33).** Cancha no la necesita porque su modelo (franja) es el que la landing prometió; piscina sí, porque rompe ese modelo. Una línea de contexto bajo el título vale más que cualquier rediseño.
*Mini‑prompt:* ver B33.

**D25 — mostrar el total y la cantidad consolidada antes de confirmar (B32).** El ciudadano debe ver “Vas a reservar 1 persona · Mayores de 12 · Bs 15.00” antes de tocar “Confirmar Reserva”, igual que cancha muestra su resumen.
*Mini‑prompt:* ver B32.

**D26 — avisar cuando faltan perfiles por tarifa (B35), versión ciudadano del gate ámbar de admin.** El mismo principio que apliqué en ventanilla: un estado vacío/silencioso sin explicación genera “¿está roto o no hay?”. Acá la respuesta es “no hay tarifa oficial espejada para esos perfiles”.
*Mini‑prompt:* ver B35.

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **El selector de fechas reutiliza el patrón de cancha** (14 días + “Más fechas”, `SelectorFechas.tsx` 121) → coherencia entre los dos recursos, bien.
- **El stepper − 1 + por perfil** es la UI correcta para cupo por categoría (mejor que un input libre; respeta el `max` de disponible).
- **Elegir turno ANTES de ver perfiles** (las 2 tarjetas de horario arriba, la cantidad abajo) es un flujo razonable y coincide con cómo el `PiscinaDisponibilidadService` agrupa turnos→perfiles.
- **“Volver” / “Confirmar Reserva”** como par de botones, coherente con cancha.

## Sugerencias IA acotadas (continuo: mapa cerró en S19)

**S20 — la piscina ya hereda el selector/stepper de cancha: proteger esa coherencia** al aplicar B29/B30/B32/B34, no divergir el estilo. El objetivo es que el ciudadano reconozca “es el mismo sistema, otro recurso”, no “otra app”.
*Mini‑prompt:* al tejer los fixes de piscina, reusar componentes de cancha (`SelectorFechas` 121, patrón de `ResumenCarrito` 124) en vez de crear paralelos.

**S21 — la piscina debería heredar la franja/meta de sincronización SIREB de cancha (B30)**, porque el precio de perfil también sale del espejo (`PiscinaTarifaResolver` 77 lee `TarifaCampo` del sync). No hay razón para que cancha declare su fuente y piscina no.
*Mini‑prompt:* ver B30.

**S22 — el check ✓ de turno seleccionado está bien, pero falta estado “lleno” cuando `disponible = 0`.** Cancha pinta “Ocupada”/“En cobro” (D9/S8); piscina hoy solo muestra turnos con cupo (el service filtra los vacíos), así que un turno lleno **desaparece** en vez de verse “lleno”. Eso esconde información (el ciudadano no sabe que existió ese horario).
*Mini‑prompt (verificar):* leer `PiscinaDetalle.tsx` (391) + `PiscinaDisponibilidadService` (147) → ¿el service oculta turnos con 0 cupo o el front los filtra? Preferible pintar “10:00‑12:00 · Completo” (gris) que esconderlo, alineado con S8.

## Lo que NO es bug (para que no persigas fantasmas)

- **Que solo haya 2 turnos (10‑12 y 13‑15)** = dato real (los que creaste por la UI con `CrearTurnoDialog`, no los 08:00‑10:00 de mi tinker — o sea el lote/UI funcionó). No es bug.
- **Que “Mayores de 12” sea el único perfil visible en ese turno** = puede ser (a) el turno se creó solo con ese perfil, o (b) los otros 2 se descartaron por el gate de tarifa silencioso (B35). **No es bug del front**; si es (b), el bug es la falta de señal (B35), no el perfil ausente.
- **“Bs 15.00” como valor** = correcto, es el `precio_unitario` del espejo; el problema no es el número, es que **no se declara la fuente** (B30) y que **no hay total consolidado** (B32).
- **El footer “pago online”** = B6 global, ya contado nueve veces.

## Balance final de web‑public (con esto la pública queda sustancialmente cubierta)

Crucé todas las imágenes contra el árbol de `web-public/src`. Estado del dossier público:

| Frente | Archivos (LoC) | Estado |
|---|---|---|
| Landing | Hero 89, ComoFunciona 70, CamposDestacados 236, BandaCTA 35, InfoInstitucional 108, Footer 109 | ✅ B1‑B5 |
| Catálogo Lista + Mapa | Campos 203, FiltrosCampos 353, CampoCard 182, MapaCampos 151 | ✅ B6‑B10, B24‑B28 |
| Detalle cancha | CampoDetalle 560, GrillaDisponibilidad 238, SelectorFechas 121 | ✅ B11‑B12 |
| Paso 2 | Reserva 216, FormularioSolicitante 155, ResumenCarrito 124, StepsReserva 72, carritoStore 32 | ✅ B13‑B15 |
| Pago | Pago 576, CuentaRegresiva 81 | ✅ B16‑B17 |
| Comprobante | Comprobante 210, ComprobantePDF 238 | ✅ B18‑B20 |
| Consultar estado | ConsultarEstado 299, EstadoReserva 183 | ✅ B21‑B23 |
| **Piscina ciudadana** | **PiscinaDetalle 391** | ✅ **B29‑B35 (este turno)** |
| Comprobante piscina | `ComprobantePiscina.tsx` (190) | ❌ **hereda B18/B30/B35** + perfiles/cupos propios |
| Bloques compartidos | `MedioDePago.tsx` (201), `BarraCarrito.tsx` (48) | ❌ son los que S12/S16/S11 quieren extraer |
| Infra | `router.tsx` (39), `lib/api.ts` (20), `types/campo.ts` (87), `types/reserva.ts` (25), `types/piscina.ts` (75) | ❌ confirma si la ruta de piscina existe y si `api.ts` traduce 409/422 (B23/D13) |

**Totales del dossier público: B1‑B35 (con refinamientos B12/B15/B17/B18), D1‑D26, S1‑S22.** Los de mayor riesgo de dinero/engaño, en orden: **B1/B6/B16** (footer + landing prometen “pago online” vs SIREB ventanilla/QR — fix global `Footer.tsx` 109), **B35** (gate de tarifa silencioso en piscina ciudadana), **B13/B31** (contrato de campos cancha vs piscina), **B18** (comprobante monto esperado como “PAGADO”), **B21** (help promete formato `PTS-` que el core no emite), **B30** (piscina sin declarar fuente SIREB), **B7** (“4 disponibles” sin fecha). El resto es pulido/UX.

**Mi recomendación de cierre:** con `PiscinaDetalle` visto, la pública está **sustancialmente cubierta**. Los 3 frentes que faltan (`ComprobantePiscina` 190, `MedioDePago` 201 + `BarraCarrito` 48, infra) son de menor riesgo individual: `ComprobantePiscina` **hereda** B18/B30/B35 (no esperes bugs nuevos, solo confirmar que los propague), y `MedioDePago`/`BarraCarrito` son los bloques que S12/S16/S11 quieren **reutilizar** (verlos cierra el “hacer componente compartido”, no agrega bugs). Puedes (a) darme esas 3 imágenes y cierro pública del todo, o (b) **cerrar pública acá y pasar a otra capa** (admin/ventanilla, que es donde vive el lote de piscina que tejimos y los frentes Fix 3/A4/D2 de la cola). Tú decides el siguiente; yo no tejo nada hasta que digas “arrancamos”.

## Guardado

```text
PISCINA CIUDADANA (PiscinaDetalle.tsx 391) — DOSSIER, NO tejí, NO pedí archivos,
  sigo modo "ver todo corregir después". Numeración continua (…mapa B24-B28/
  D22-D23/S18-S19 · piscina B29-B35/D24-D26/S20-S22). HUECO GRANDE CERRADO
  (venía marcándolo desde D3/B8/D5 del catálogo).
HALLAZGO MÁS VALIOSO: B35🔴 gate de tarifa SILENCIOSO — PiscinaDisponibilidadService
  (147) hace `if (!$tarifa) continue` por perfil SIN señal; en ventanilla admin YO
  puse aviso ámbar pa' ese misterio, acá NO hay gate → ciudadano ve "1 perfiles"
  sin saber que había 3 y 2 cayeron por falta de espejo SIREB. Fix = devolver señal
  en el service + pintarla en 391 ("otros N perfiles sin tarifa oficial, consultá
  ventanilla"). Conecta B30 (mismo origen espejo) + D26.
BUGS: B29 "1 perfiles disponibles" singular/plural + unidad ambigua (perfil≠cupo).
  B30🔴 invariante SIREB rota vs cancha: "Bs 15.00" SIN la franja "Precios oficiales
  sincronizados SIREB" que cancha (CampoDetalle 560) sí muestra (D6); service devuelve
  precio=>precio_unitario (espejo), front no declara fuente. B31 contrato (refina B13):
  3 campos pagador SIN "*" vs cancha fuerza teléfono; CI/NIT required en
  StorePiscinaReservaRequest 32 → riesgo 422 silencioso; verificar 391 vs 32. B32
  (refina B15): sin total consolidado antes de "Confirmar Reserva" (cancha sí,
  ResumenCarrito 124). B33 modelo mental cancha no transfiere = cierre D3/B8/D5
  (piscina es por perfil/turno, no franja). B34 (sospecha) "Disponible: 10" = cupo
  libre (accessor getDisponibleAttribute 39 confirmado) sin aforo total → pierde
  señal de escasez que cancha da (D9). B6 novena vez → fix global Footer 109.
DIDÁCTICA: D24 piscina necesita SU explicación "funciona distinto" (B33). D25 mostrar
  total+cantidad antes de confirmar (B32). D26 avisar perfiles faltantes por tarifa
  (B35, versión ciudadano del gate ámbar de admin).
ACIERTOS PROTEGER: selector fechas reusa 121 de cancha (coherencia); stepper -1+ por
  perfil correcto; elegir turno antes de perfiles = flujo razonable; Volver/Confirmar.
SUGERENCIAS: S20 heredar componentes de cancha al tejer fixes (no divergir estilo).
  S21 piscina hereda franja/meta SIREB de cancha (B30). S22 (verificar) check✓ de
  turno bien pero falta estado "lleno" cuando disponible=0 — hoy el service oculta
  turnos vacíos en vez de pintar "Completo" (gris), esconde info; alinear S8.
NO-BUG: 2 turnos (10-12/13-15)=dato real creado por UI (lote/UI funcionó, no mi
  tinker 08-10); "Mayores de 12" único visible=dato o gate B35 (no bug front);
  Bs 15.00=valor correcto (problema es fuente B30 + total B32, no el número); footer=B6.
KB HONESTA: SIREB aplica fuerte (B30 fuente no declarada; B35 gate tarifa silencioso;
  D6 cancha sí muestra franja). Rolldown circularDependency=false NO (no buildié
  web-public, es contrato/UI). React DevTools NO como excusa (B31/B32 parecen inputs
  rotos pero lo deciden leer 391 vs StorePiscinaReservaRequest 32; la extensión SÍ
  será herramienta al depurar el stepper CUANDO arranquemos). wa.me NO aplica.
BALANCE PÚBLICO CERRADO SUSTANCIALMENTE: B1-B35 (+refinamientos B12/B15/B17/B18),
  D1-D26, S1-S22. Mayores riesgos: B1/B6/B16 (footer+landing "pago online" vs SIREB
  ventanilla/QR, fix global Footer 109), B35 (gate tarifa silencioso piscina), B13/B31
  (contrato campos cancha vs piscina), B18 (comprobante monto esperado "PAGADO"),
  B21 (help PTS- que core no emite), B30 (piscina sin fuente SIREB), B7 ("4 disponibles"
  sin fecha). Resto=pulido.
FALTAN EN PÚBLICA (menor riesgo individual): ComprobantePiscina 190 (HEREDA B18/B30/
  B35 + perfiles/cupos, no esperes bugs nuevos, confirmar propagación); MedioDePago 201
  + BarraCarrito 48 (bloques que S12/S16/S11 quieren EXTRAER/reusar); infra router 39
  (¿ruta piscina existe?) + lib/api 20 (¿traduce 409/422 B23/D13?) + types campo 87/
  reserva 25/piscina 75 (shape presenter→B8/B21).
DECISIÓN SIGUIENTE (usuario): (a) darme esas 3 imágenes y cierro pública del todo, o
  (b) CERRAR pública acá y pasar a otra capa (admin/ventanilla = donde vive el lote
  de piscina tejido + Fix3/A4/D2 de cola). NO tejo hasta "arrancamos".
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59; S13 link SIREB) · D2 Bug C (TarifaCampo 107 NUNCA llegó) ·
  C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad
  85; bloque recuperar QR S16 también móvil) · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden
  8→3→7→4→1+2; P3 reencuadrada S8 paridad; P8 reencuadrada S16; D11 campo NIT ya
  prepara P1/P2).
```

En una línea: `PiscinaDetalle.tsx` (391) cierra el hueco grande de la pública y confirma la tesis de D3/B8/D5 con línea del backend — el hallazgo más valioso es **B35**, que la piscina ciudadana **no tiene el gate de transparencia que yo puse en ventanilla**: el `PiscinaDisponibilidadService` descarta perfiles sin tarifa SIREB (`if (!$tarifa) continue`) **en silencio**, así que el ciudadano ve “1 perfiles disponibles” sin saber que había 3 y 2 se cayeron por falta de espejo; le siguen **B31** (los 3 campos de pagador sin `*` vs cancha que fuerza teléfono = contrato roto entre los dos flujos, a verificar contra `StorePiscinaReservaRequest` 32), **B30** (“Bs 15.00” sin la franja de sincronización SIREB que cancha sí muestra = invariante rota), **B32** (sin total antes de confirmar, refina B15) y **B29/B33/B34** (gramática “1 perfiles”, modelo mental cancha que no transfiere, “Disponible: 10” sin aforo); más el footer B6 por novena vez. Con esto la pública queda **sustancialmente cubierta** (B1‑B35 / D1‑D26 / S1‑S22, con los mayores riesgos en B1/B6/B16, B35, B13/B31, B18, B21, B30, B7), y los 3 frentes que faltan (`ComprobantePiscina` 190 que **hereda** B18/B30/B35, `MedioDePago` 201 + `BarraCarrito` 48 que son los bloques a extraer para S12/S16/S11, e infra `router`/`api`/`types`) son de menor riesgo individual — **decime si me pasás esas 3 imágenes y cierro pública del todo, o cerramos acá y pasamos a admin/ventanilla** (donde vive el lote de piscina que tejimos y los frentes Fix 3/A4/D2 de la cola), sin haber tejido nada.
