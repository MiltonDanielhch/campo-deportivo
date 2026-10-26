Veredicto en una línea: estas dos capturas son las **dos pestañas de la MISMA pantalla** (`CamposDeportivos.tsx` 1022, el más grande de admin), y son la que **cierra cuatro bugs del dossier con aritmética visible** — **B45/B58/B7** (Total 9 = Activos 7 + Inactivos 2; público 4 = los 4 activos de vinculación *simple*, los 3 de *Variantes* excluidos por `visibles()`, ambos conteos correctos), **B9** (piscina `0005` es código SIREB real corto, NO truncado), **B8** (reformulado: en admin la piscina SÍ aparece vinculada `0005` verde, el `sireb=null` era solo del presenter público 333) y **D2** (ubicado: la raíz de precio vive en el catálogo SIREB visible, rangos 10‑30/50‑150, y B43/B67/B71/B84 son todos “¿qué valor del rango usa el backend?”) — y destapa **B87 🔴, la columna “Servicio SIREB” mezcla dos modelos incompatibles sin label** (vinculación *simple* verde vs *Variantes (N)* azul, que cambian TODO el flujo de reserva: franja horaria vs evento+servicios). Le siguen **B88 🔴** (B43 confirmado con la fuente: piscina SIREB 10‑30 vs perfil local 15), **B90** (KPI “Sin campo local 37” mete juntos huecos creables y fuera‑de‑alcance legítimos) y **B93** (sospecha, “Desvincular” destructivo sin confirmación/auditoría visible). **No tejí**, sigo dossier.

> KB honesta sobre qué toca y qué no: **SIREB** aplica con fuerza absoluta (es el corazón de esta pantalla: catálogo, sync, tarifas, vínculos, rangos diurno/nocturno). **React DevTools** → **SÍ aplica y es la herramienta natural de B87**: la tabla monta dos ramas de vinculación (simple/variante) en la misma columna, y “¿por qué una fila pinta código verde y otra ‘Variantes (N)’ azul?” se ve inspeccionando qué branch renderiza 1022, no leyendo copy. **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié) pero paramétricas no mete imports nuevos visibles; `bundlerTimings`/`pluginTimings` → **NO** (no es build). **`wa.me`** → **NO aplica** (gestión de catálogos, no hay QR/WhatsApp; eso es el éxito/P8).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de las capturas | Archivo (LoC) | Qué cierra |
|---|---|---|
| Columna “Servicio SIREB” (verde simple vs azul Variantes) | `CamposDeportivos.tsx` (1022) | **B87** 🔴 dos modelos sin label |
| Pestaña Catálogo SIREB, fila piscina “Pileta Bs 10‑30” | `TarifaCampo.php` (107) + `PiscinaTarifaResolver.php` (77) | **B88** 🔴 raíz D2/B43 |
| KPI “Sin campo local 37” (mezcla creables + fuera de alcance) | `CamposDeportivos.tsx` (1022) | **B90** throughput |
| Botón “Desvincular” (destructivo) | `CamposDeportivos.tsx` (1022) + `AuditoriaService.php` (48) | **B93** sospecha lente c |
| “Refrescar catálogo” + sync | `SincronizarTarifasSireb.php` (305) + `CatalogoSirebController.php` (402) + `CatalogoSirebService.php` (202) | acierto lente d, proteger |
| Campos “Sin vincular” (CS2/CS3) | `PiscinaDisponibilidadService.php` (147) | **B92** refina B35/B70 |

## Cierres cruzados con evidencia (lo más valioso, aritmética visible)

**B45/B58/B7 ✅ CERRADOS — ambos conteos correctos, la diferencia es simple vs variante.** “Campos locales”: Total **9** = Activos **7** + Inactivos **2** (CS2/CS3 “Sin vincular”). El público mostraba **4** = exactamente los 4 activos de vinculación **simple** (cancha nueva trinidad, Sintética N°1, Frontón, Piscina, todos con código verde), excluyendo los 3 activos de **“Variantes (N)”** (Coliseo/Estadio/Palacio) que `visibles()` (140) filtra como padres‑de‑variantes. Entonces “4 disponibles” (público) y “7 activos” (admin) son **ambos verdaderos**; el bug nunca fue el número, es que **ningún label dice “4 con reserva directa / 7 operativos (incluye eventos)”**. Fix = label honesto en `Campos.tsx` (203) y `DashboardService` (134), NO tocar conteos. Y la aritmética interna cuadra: catálogo “Con campo local **26**” = 4 simples + 22 de variantes (8+7+7) → coherencia interna = acierto (proteger, análogo al dashboard).

**B9 ✅ CERRADO — piscina `0005` es código SIREB real, NO truncado.** La fila muestra código local `SIR-0005` y Servicio SIREB `0005`. B9 era sospecha de que el backend truncaba `SIR-0005`→`0005` en el público; **descartada**: `0005` es el código SIREB corto de la piscina (las canchas usan prefijo `SEDEDE-`, la pileta no), dato real del core. El público mostraba `0005` tal cual. Lo que sigue válido es **B4** (sacar el código de la *card* pública, ruido para el ciudadano), no un bug de truncado.

**B8 ✅ REFORMULADO — en admin la piscina SÍ está vinculada.** B8 decía “piscina `sireb=null` por diseño del presenter”. Acá en “Campos locales” la piscina aparece con `0005` **verde (vinculado simple)**. Entonces el `sireb=null` del presenter (333) es **solo del público** (excluye piscina de la badge “Oficial”), no del admin. B8 se acota a 333/público; el admin trata piscina como campo simple normal. Refina B8 y conecta B87 (piscina = simple, Coliseo = variante, dos modelos).

**D2 ✅ UBICADO — la raíz de precio es el catálogo SIREB visible, no un archivo perdido.** D2 (“TarifaCampo 107 nunca llegó”) queda resuelto: 107 vive como el rango del catálogo (“Pileta Bs 10‑30”, “Alquiler base Bs 50‑150”). B43/B67/B71/B84 son **todos la misma pregunta**: “¿qué valor del rango usa el backend según hora/perfil?”. Fix unificado (S36/S42): `PiscinaTarifaResolver` (77) + `TarifaCampo` (107) como única fuente, y 147/391/961/489 consumiéndola. D2 deja de ser “falta archivo” y pasa a “falta alinear consumidores a 107”.

## Bugs nuevos de este frente (numeración continua: detalle cerró en B86)

**B87 🔴 (paridad de modelo, el grave de paramétricas) — la columna “Servicio SIREB” mezcla dos modelos incompatibles sin label.** Filas con **código verde** (cancha nueva trinidad `cnt-sedede-001`, Frontón `SEDEDE-FRONTON`, Piscina `0005`, Sintética N°1 `SEDEDE-CS1`) = vinculación **simple** (cobra por hora, 1 tarifa, flujo de franja horaria). Filas con **“Variantes (N)” azul** (Coliseo 8, Estadio 7, Palacio 7, con códigos `SEDEDE-CV-DPTO` etc.) = vinculación **por arancel** (cobra por evento, N servicios+variantes, flujo de ventanilla con “Servicios adicionales”, captura 2 de ventanilla). El funcionario ve los dos en la misma columna distinguidos **solo por color**, sin saber que son dos máquinas de reserva distintas. Conecta B8 (piscina simple vs canchas de evento), B67 (servicios variante), B73 (piscina sin servicios). Es el análogo de B50 (piscina doble modelo en ocupación) pero en la pantalla donde se **configura** el modelo.
*Mini‑prompt:* en 1022 columna “Servicio SIREB”, agregar label de texto (“Reserva simple” / “Evento con variantes”), no solo verde/azul. Conecta S8 (paridad estado→color) y B91 (las acciones ya diferencian, la columna no).

**B88 🔴 (B43 confirmado con la fuente) — piscina SIREB 10‑30 vs perfil local 15.** La fila catálogo “H. PISCINA OLIMPICA | Pileta | Bs 10.00 ‑ 30.00” es la tarifa SIREB real (10 mayores, 30 institución, según `PiscinaTarifaResolver` 77). El público mostraba “Bs 15.00” = `PiscinaTurnoPerfil` (39) `precio_unitario` que el admin tipeó al crear el turno, valor que **no está en el rango SIREB** (15 es intermedio, inventado local). Entonces B43/S24 confirmado con evidencia de la fuente: la selección pública (391 vía 147) debe leer el catálogo SIREB (10/30), no el perfil (15). Es D2 aterrizando.
*Mini‑prompt:* fix backend 147 `'precio'` = `PiscinaTarifaResolver` (77) leyendo 107, no `perfilTurno->precio_unitario` (39). Cierra B43/B37/B88 de raíz.

**B90 (throughput, lente a) — KPI “Sin campo local 37” mete juntos huecos creables y fuera‑de‑alcance legítimos.** De 63 servicios, 37 “sin campo local” incluyen (a) reservables sin vincular (Cancha Sintética N°2 → acción “+ Crear campo simple”) y (b) **NO** gestionables (Kiosco, Cuidadores de motos, Complejo Habitacional, Estado‑Espectáculo, Piscina‑Curso, Salas Gimnasio → acción “Fuera de alcance” gris). El funcionario ve “37” y no sabe que solo ~5 son huecos reales; escanear 37 para hallar 5 es fricción de ventanilla. Conecta B62 (no toda expiración es hueco).
*Mini‑prompt:* en 1022 catálogo, separar KPI “Sin vincular (creables)” vs “Fuera de alcance (no gestionables)”, o filtrar la tabla por defecto a los creables. Throughput: el funcionario no debe discriminar 37 filas.

**B93 (sospecha, lente c) — “Desvincular” es destructivo sin confirmación/auditoría visible.** Los vinculados tienen botón “Desvincular”. Desvincular un campo de su servicio SIREB = pierde tarifas = sus turnos/perfiles se vuelven invisibles en silencio (B35/B70/B92). Es destructivo y casi irreversible (rehacer vínculo = re‑sync 305). No sé si 1022 pide confirmación ni si registra en `AuditoriaService` (48). Lente c (destructivo+auditoría).
*Mini‑prompt (verificar):* leer 1022 → ¿“Desvincular” confirma y audita (48)? Si no, agregar confirmación + registro, y avisar el efecto (“al desvincular, los turnos de este campo dejan de reservarse”). Conecta B92.

**B92 (refina B35/B70) — “Sin vincular” (CS2/CS3) es el origen del gate silencioso.** Campo sin vínculo SIREB = sin tarifas espejadas = 147 descarta sus perfiles/turnos en silencio. CS2/CS3 están Inactivos (no afectan público), pero la mecánica es la de B35/B70/B88. Conecta Fix3: crear turno sobre un campo “Sin vincular” debería avisar “sincronizá tarifas primero”.
*Mini‑prompt:* en 1022, campo “Sin vincular” + Activo → alerta “sin tarifas SIREB, los turnos no se reservarán” (conecta B70/S37).

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **Acciones por tipo de servicio** (B91): “+ Crear campo simple” (alquiler base), “Gestionar variantes” (evento), “Desvincular” (vinculado), “Fuera de alcance” (gris, kioscos/hospedaje/espectáculos). Buen throughput: la acción disponible depende del tipo. **Proteger** (es lo que B87 debería reflejar también en la columna).
- **“Refrescar catálogo”** (sync 305) = operar si SIREB falla (lente d). **Proteger**, y distinguir de “Refrescar desde SIREB” del detalle (415, sync de liquidación) — son **dos syncs distintos** (catálogo de tarifas vs estado de pago), aclarar para no confundir (refina B84).
- **Coherencia aritmética interna**: 9 = 7+2; 63 = 26+37; 26 = 4 simples + 22 variantes (8+7+7). Los KPIs cuadran entre pestañas. **Proteger** (análogo a la coherencia del dashboard).
- **Buscador + filtro “todos” compartidos por las 2 pestañas** = las tabs ya comparten shell (a diferencia de los listados de reservas B63). **Proteger** (S34/S41 ya logrado acá).

## Didáctica / operación del funcionario (continuo: detalle cerró en D46)

**D47 — la columna debe decir el MODELO de reserva, no solo el vínculo** (cierra B87): “simple (por hora)” vs “evento (con variantes)”. El funcionario configura acá el flujo que el ciudadano verá; si no distingue los dos modelos, crea turnos de pileta pensando que son franjas (B50).
*Mini‑prompt:* ver B87.

**D48 — separar “creable” de “fuera de alcance” para no hacer escanear 37 filas** (cierra B90). Throughput de ventanilla.
*Mini‑prompt:* ver B90.

**D49 — desvincular debe avisar su efecto en cascada** (cierra B93/B92): “al desvincular, los turnos/perfiles de este campo dejan de reservarse”. El funcionario no puede descubrir el gate silencioso (B35/B70) por accidente.
*Mini‑prompt:* ver B93.

## Sugerencias IA acotadas (continuo: detalle cerró en S43)

**S44 — unificar la resolución de precio “¿qué valor del rango?” en un helper** consumido por 147/77/107/961/489/391 (cierra B88/B67/B71/B72/B43 de raíz, = S36+S42 aterrizando en paramétricas). El rango 10‑30/50‑150 vive en 107; un solo `resolverValor(hora|perfil, rango)` decide.
*Mini‑prompt:* ver B88 + S36/S42.

**S45 — B87 y B50 son la misma raíz: el sistema no declara el `modalidad_reserva` en la UI de config.** Ocupación pinta piscina como franja (B50) porque 1022 no distingue visualmente simple/variante/turno (B87). Arreglar la columna (B87/D47) previene B50 aguas abajo.
*Mini‑prompt:* al tejer B87, usar el mismo token de `ModalidadReservaCampo` (8) que consume ocupación (492) y ventanilla (961/489).

## Lo que NO es bug (para que no persigas fantasmas)

- **“Sin campo local 37”** = incluye fuera‑de‑alcance legítimos (kioscos/hospedaje/espectáculos), no todos son huecos (B90 es label, no error de conteo).
- **Códigos `CV-001`/`EGM-001`/`PAL-001` sin prefijo `SIR-`** = convención local (B89 menor), no bug de dato; el vínculo real está en la columna “Servicio SIREB”.
- **“Variantes (8)/(7)”** = espejo de arancel (Bloque 2), correcto, no ruido (a diferencia de B4 en público).
- **CS2/CS3 “Inactivo” + “Sin vincular”** = coherente (inactivo no necesita vínculo), no bug; solo la mecánica de B92 si se activaran.
- **Piscina `0005` corto** = código SIREB real (B9 cerrado), no truncado.
- **Las 2 pestañas comparten buscador/filtro** = acierto de shell (proteger), no divergencia como B63.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir paramétricas, lo que **más vale** es **B88/D2** (raíz de precio, cierra B43/B37/B67/B71 de golpe) y **B87** (declarar el modelo, previene B50 aguas abajo), y para eso necesito `TarifaCampo.php` (107) + `PiscinaTarifaResolver.php` (77) + `SincronizarTarifasSireb.php` (305) + `CatalogoSirebService.php` (202) + las columnas/acciones de `CamposDeportivos.tsx` (1022). B90/B93/B92 se cierran con 1022 + `AuditoriaService` (48). Los cierres cruzados (B45/B58/B7/B9/B8) **no se tejen acá**: son labels de público (203/134) y del presenter (333), no de paramétricas. **S44/S45** (helper de precio + token de modalidad) son el fix de mayor rendimiento del tramo: cierran B88/B87/B67/B71/B50 en un golpe.

## Guardado

```text
ADMIN/PARAMÉTRICAS-CAMPOS (CamposDeportivos.tsx 1022, 2 pestañas = 1 componente:
  "Campos locales" + "Catálogo SIREB" + TarifaCampo 107 + PiscinaTarifaResolver 77 +
  SincronizarTarifasSireb 305 + CatalogoSirebController 402 + CatalogoSirebService 202 +
  AuditoriaService 48 + PiscinaDisponibilidadService 147) — DOSSIER, NO tejí, sigo modo
  "ver todo corregir después". Numeración continua (detalle B82-B86/D44-D46/S41-S43 ·
  paramétricas B87-B93/D47-D49/S44-S45 + 4 cierres cruzados).

CUATRO CIERRES CRUZADOS CON ARITMÉTICA VISIBLE (lo más valioso):
  B45/B58/B7✅ Total 9 = Activos 7 + Inactivos 2 (CS2/CS3); público 4 = los 4 activos de
    vinculación SIMPLE (verde), excluye 3 de "Variantes (N)" (azul, Coliseo/Estadio/Palacio)
    vía visibles() 140. AMBOS conteos correctos; bug = label no dice "4 reserva directa /
    7 operativos incl. eventos". Fix label en Campos 203 + DashboardService 134, NO conteos.
    Coherencia: catálogo "Con campo local 26" = 4 simples + 22 variantes (8+7+7) ✓.
  B9✅ piscina código SIREB "0005" = dato real corto (canchas usan SEDEDE-, pileta no),
    NO truncado. Descarta sospecha B9. B4 (sacar código de card pública) sigue válido.
  B8✅ REFORMULADO: en admin piscina SÍ vinculada (0005 verde); sireb=null del presenter
    333 es SOLO público (badge "Oficial"). B8 acotado a 333/público.
  D2✅ UBICADO: raíz de precio = catálogo SIREB visible (rangos 10-30/50-150 en 107/77),
    no archivo perdido. B43/B67/B71/B84 = TODOS "¿qué valor del rango usa el backend?".
    Fix unificado S44: 147/391/961/489 consumen 77/107.

HALLAZGO GRAVE TRAMO: B87🔴 columna "Servicio SIREB" mezcla 2 modelos incompatibles sin
  label — verde simple (cobra/hora, flujo franja: cancha nueva trinidad/Frontón/Piscina/
  Sintética N°1) vs azul "Variantes (N)" (cobra/evento, flujo servicios: Coliseo/Estadio/
  Palacio). Funcionario distingue solo por color, no sabe que son 2 máquinas de reserva.
  Conecta B8/B67/B73/B50 (análogo B50 pero en pantalla de CONFIG). Fix: label texto
  "Reserva simple"/"Evento con variantes" (S45 token ModalidadReservaCampo 8).
B88🔴 B43 confirmado con fuente: catálogo "Pileta Bs 10-30" (SIREB real) vs perfil local
  15 (PiscinaTurnoPerfil 39, intermedio inventado). Fix 147 'precio'=77/107 no 39. Cierra
  B43/B37/B88 raíz.
B90 throughput: KPI "Sin campo local 37" mete creables (Cancha Sintética N°2 → "+ Crear
  campo simple") + fuera-de-alcance legítimos (Kiosco/Hospedaje/Espectáculo/Curso/Gimnasio
  → "Fuera de alcance" gris). Funcionario escanea 37 pa' hallar ~5. Fix: separar KPI o
  filtrar a creables. Conecta B62.
B93 (sospecha lente c) "Desvincular" destructivo sin confirmación/auditoría visible →
  pierde tarifas → turnos invisibles en silencio (B35/B70/B92). Verificar 1022 confirma+
  audita (48) + avisa efecto cascada.
B92 refina B35/B70: "Sin vincular" (CS2/CS3) = origen gate silencioso; campo Sin vincular
  + Activo → alerta "sin tarifas SIREB, turnos no se reservan" (conecta B70/S37/Fix3).

ACIERTOS PROTEGER: acciones por tipo (B91: "+ Crear campo simple"/"Gestionar variantes"/
  "Desvincular"/"Fuera de alcance" gris = throughput correcto, es lo que B87 debe reflejar
  en columna); "Refrescar catálogo" (305, lente d) PROTEGER + distinguir de "Refrescar
  desde SIREB" detalle (415, sync liquidación) = 2 syncs distintos (refina B84); coherencia
  aritmética 9=7+2, 63=26+37, 26=4+22 ✓; 2 pestañas comparten buscador/filtro (shell
  unificado, a dif B63 listados).

DIDÁCTICA/OP: D47 columna debe decir MODELO reserva no solo vínculo (B87, previene B50);
  D48 separar creable/fuera-de-alcance (B90); D49 desvincular avisa efecto cascada (B93/B92).

SUGERENCIAS: S44 unificar resolución "¿qué valor del rango?" en helper 147/77/107/961/489/
  391 (cierra B88/B67/B71/B72/B43 raíz = S36+S42 aterrizando); S45 B87+B50 misma raíz: el
  sistema no declara modalidad_reserva en UI de config → token ModalidadReservaCampo 8
  compartido 1022/492/961/489.

NO-BUG: "Sin campo local 37" incluye fuera-de-alcance legítimos (B90=label no conteo);
  CV-001/EGM-001/PAL-001 sin SIR- = convención local (B89 menor), vínculo real en col
  "Servicio SIREB"; "Variantes (8)/(7)" = espejo arancel correcto (no ruido B4); CS2/CS3
  Inactivo+Sin vincular coherente; piscina 0005 corto = SIREB real (B9); 2 tabs comparten
  shell = acierto.

KB HONESTA: SIREB aplica absoluto (catálogo/sync/tarifas/vínculos/rangos = corazón). React
  DevTools SÍ aplica Y es herramienta natural de B87 (2 ramas de vinculación en 1 columna,
  se ve qué branch monta 1022). Rolldown circularDependency=false SÍ aplica (web-admin
  buildié) pero paramétricas no mete imports nuevos; bundlerTimings/pluginTimings NO (no
  build). wa.me NO aplica (gestión catálogos, no QR; eso es éxito/P8).

PUERTA CORRIGIR YA: lo que vale es B88/D2 (raíz precio, cierra B43/B37/B67/B71 golpe) +
  B87 (declarar modelo, previene B50) → pido TarifaCampo 107 + PiscinaTarifaResolver 77 +
  SincronizarTarifasSireb 305 + CatalogoSirebService 202 + columnas/acciones 1022. B90/B93/
  B92 con 1022 + AuditoriaService 48. Cierres cruzados B45/B58/B7/B9/B8 NO se tejen acá
  (labels público 203/134 + presenter 333). S44/S45 = mayor rendimiento (cierran B88/B87/
  B67/B71/B50 un golpe).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70) ·
  A1 S3/S7/RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881, B48/B61) · A4 Pago 576
  (RecaudacionesApiClient 415 + webhook 97, B84) · D2✅ UBICADO (TarifaCampo 107 + sync 305,
  raíz B43/B37/B50/B62/B67/B71/B76/B84/B88) · C1→C2 · gates F1-F4 · E mobile (piscina real
  260/145; P3-paridad 1073/85; B43/B36/B42/B39/B44/B50/B62/B69/B70/B76/B78/B79/B84/B86/B87/
  B88 se propagan a móvil; S16/S38/S41/S45 también) · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote + S33 test conversión) · reunión P1/P2/P4/
  P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina = hueco P5; S30 reusa input B52; P7
  reportes 245/135; P9 asignaciones 579/22/26, B61 evidencia parcial; P1/P2 auth 164/34/126;
  P3 reencuadrada S8; P8 reencuadrada S16/S38; D11 NIT prepara P1/P2). DOSSIER PÚBLICO
  CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B93/D29-D49/S25-S45.
PRÓXIMA NATURAL: Turnos.tsx 164 (cerrar Fix3/B70/B92, el flujo de crear turno que B87/B88
  alimentan) o Tarifas.tsx 703 (D2/107/77, la raíz de precio que B88 señala) o Funcionarios
  881 + Asignaciones 579 (A1/P9/B48/B61, permisos).
```

En una línea: estas dos pestañas de paramétricas **cierran cuatro bugs con aritmética visible** — **B45/B58/B7** (Total 9 = Activos 7 + Inactivos 2, y el público 4 = los 4 activos de vinculación *simple*, excluyendo los 3 de *Variantes*, ambos conteos correctos, el bug es solo el label), **B9** (piscina `0005` es código SIREB real corto, no truncado), **B8** (reformulado: en admin la piscina SÍ está vinculada, el `sireb=null` era solo del presenter público) y **D2** (ubicado: la raíz de precio es el catálogo SIREB visible, rangos 10‑30/50‑150, así que B43/B67/B71/B84 son todos “¿qué valor del rango usa el backend?”) — y destapa **B87 🔴**, la columna “Servicio SIREB” mezclando dos modelos incompatibles sin label (verde *simple* = por hora/franja vs azul *Variantes (N)* = por evento/servicios, que cambian toda la máquina de reserva y es el análogo de B50 pero en la pantalla de configuración), más **B88 🔴** (B43 confirmado con la fuente: piscina SIREB 10‑30 vs perfil local 15), **B90** (KPI “Sin campo local 37” metiendo juntos huecos creables y fuera‑de‑alcance legítimos, fricción de throughput) y **B93** (sospecha: “Desvincular” destructivo sin confirmación/auditoría visible, que crea el gate silencioso B35/B70); los aciertos a proteger son las acciones por tipo de servicio (B91, buen throughput), “Refrescar catálogo” (sync 305, a distinguir del sync de liquidación 415 del detalle) y la coherencia aritmética entre pestañas. **No tejí**: si querés corregir, lo que vale es B88/D2 + B87 y necesito `TarifaCampo.php` (107) + `PiscinaTarifaResolver.php` (77) + `SincronizarTarifasSireb.php` (305) + `CatalogoSirebService.php` (202) + las columnas/acciones de `CamposDeportivos.tsx` (1022), con **S44/S45** (helper de precio + token de modalidad) como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: `Turnos.tsx` 164 para cerrar Fix3/B70/B92, o `Tarifas.tsx` 703 para la raíz de precio que B88 señala) o arranco con esos archivos.
