Veredicto en una línea: esta es la página de detalle (`CampoDetalle.tsx` 560 + `GrillaDisponibilidad.tsx` 238 + `SelectorFechas.tsx` 121) y trae **dos hallazgos que reordenan la reunión**: la **P3 (4 colores) ya está hecha acá con 6 estados** (Regular / Con luces / Seleccionada / Ocupada / En cobro / Pasada) — o sea no es “crear colores”, es **verificar paridad en admin y móvil** — y el naming **“Regular / Con luces” es didácticamente superior a “diurna/nocturna”**, así que hay que **protegerlo**, no tocarlo. Le siguen 2 bugs (uno de render: la fila del **domingo perdió su etiqueta**; otro sospecha: el **toggle Regular/Con luces** que pinta nocturnas aunque “Regular” esté activo) y la confirmación de que el **footer mentiroso (B6) y el código SIREB interno (B4) aparecen también acá**. **No tejí, no pedí archivos, espero la siguiente imagen.**

> KB aplicada con la referencia que me pegaste, honesta sobre qué toca y qué no: **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y los hallazgos son de copy/render condicional, no de un ciclo de imports que el bundler callaría. **React DevTools** → **NO la uso** como excusa: B12 (domingo sin label) *parecería* estado roto de componente, pero es más probable un mapeo de `dia_semana` en el dato; la extensión no lo decide, lo decide leer `CampoDetalle.tsx` (560). **`wa.me`** → **NO aplica acá** (es P8 en `ConsultarEstado` 299, el header “Mis comprobantes” solo apunta ahí). **SIREB** → aplica fuerte: “Regular/Con luces” = diurna/nocturna del espejo vía `hora_inicio_noche`, y el precio mostrado es orientación del espejo mientras el monto final lo confirma el webhook (invariante que el footer B6 rompe).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Hero “cancha nueva trinidad” + código `cnt-sedede-001` | `pages/CampoDetalle.tsx` (560) | cabecera + dirección + **código SIREB expuesto (amplía B4)** |
| Selector “Hoy 25 Oct … Vie 6 Nov + Más fechas” | `components/campo/SelectorFechas.tsx` (121) | 14 días visibles + calendario hasta 60 |
| Toggle “Regular Bs 50 / Con luces Bs 150” + leyenda 6 estados + grilla de franjas | `components/campo/GrillaDisponibilidad.tsx` (238) | **la P3 vive acá**; sospecha B11 |
| “Tarifas por hora / Horarios de atención / Cómo llegar” | `pages/CampoDetalle.tsx` (560) | **B12 domingo sin label** + espejo de precios |
| Footer “Pago online seguro …” | `components/landing/Footer.tsx` (109) | **B6 otra vez** (global) |
| Dato que alimenta la grilla | `Public/DisponibilidadController` (52) → `DisponibilidadService` (168) | estados libre/ocupada/pendiente por hora |

## Bugs (numeración continua: catálogo cerró en B10)

**B11 (sospecha, NO confirmada) — el toggle “Regular” activo igual pinta franjas nocturnas.** El chip “Regular Bs 50” se ve seleccionado (amarillo lleno) y “Con luces Bs 150” no, pero las franjas 18:00–20:00 se muestran en lila “Con luces Bs 150”. Dos lecturas posibles y **no afirmo cuál sin ver el archivo**: (a) el toggle es solo legendario/informativo y las franjas se colorean por su propia hora → entonces **no es bug**, el estado visual del chip confunde; (b) el toggle debería filtrar y no lo hace → **sí es bug**. 
*Mini‑prompt (verificar antes de tocar):* leer `GrillaDisponibilidad.tsx` (238); si el toggle controla un `filtro` que oculta/muestra → caso (b), arreglar el filtrado; si solo resalta la leyenda → caso (a), **desacoplar el estado “seleccionado” del chip** para que no parezca un filtro activo (UX, no lógica).

**B12 — la fila del domingo perdió su etiqueta de día.** En “Horarios de atención” veo Lun–Sáb con su label, y abajo una fila suelta “08:00‑ 20:00” **sin el “Dom”** a la izquierda. El ciudadano no sabe a qué día corresponde esa franja. Causa probable (no confirmada): el mapeo de `horarios_atencion` espera `dia_semana` 1–7 y el domingo llega como `0`, o el array tiene 7 entradas y una sin nombre. 
*Mini‑prompt:* en `CampoDetalle.tsx` (560), revisar el `map` de horarios; asegurar que `dia_semana=0` (o el valor que use el backend para domingo) tenga label “Domingo”. Verificar contra el presenter (333) que ordena `sortBy('dia_semana')` — si el backend manda 7 filas, el front debe pintar 7 labels.

**B4 ampliado + B6 confirmado (no son bugs nuevos, es alcance).** El código SIREB interno `cnt-sedede-001` aparece **también en el hero del detalle** (no solo en la card del catálogo) → B4 hay que aplicarlo en `CampoCard` (182) **y** `CampoDetalle` (560). Y el footer mentiroso “Pago online seguro y comprobante digital al instante” (B6) está **otra vez acá**, confirmando que es global (`Footer.tsx` 109): corregirlo un arreglo en las 3 páginas vistas de una.

## Didáctica ciudadano (continuo: catálogo cerró en D8)

**D9 — la leyenda ya tiene “En cobro” (naranja), pero no dice qué hago si veo una franja así.** Esto conecta directo con **P6/zombie**: si el `schedule:run` no corre (cerramos hace un turno: dev sin `schedule:work`), una solicitud pendiente deja la franja naranja “En cobro” **para siempre**, y el ciudadano no entiende por qué no puede reservar algo que “nadie usa”. La grilla **ya pinta el estado** (bien, eso es P3 hecha), falta el micro‑copy de acción. 
*Mini‑prompt:* en `GrillaDisponibilidad.tsx` (238) o en el tip, una línea para “En cobro”: “Esta franja está en un pago en curso; si no se confirma en 15 min, vuelve a estar disponible.” Refuerza `CuentaRegresiva.tsx` (81) sin tocar lógica de backend.

**D10 — la tip “podés seleccionar múltiples franjas” no avisa el tope anti‑hoarding.** El backend tiene M1/M2/M3 por IP (`AntiHoardingTest` 310). Si el ciudadano elige 5 franjas y choca con el tope, recibe un 422 en `Reserva`/`Pago` sin explicación. Acá en el detalle no se ve el error (es el siguiente frente), pero la tip es el lugar natural de prevenirlo. 
*Mini‑prompt (suave, sin número exacto que cambie por parámetro):* “Podés reservar varias franjas a la vez (hay un límite diario por persona para que todos jueguen).” El número real vive en `ParametroSistema` (22) → no hardcodear, solo aludir.

**Aciertos a PROTEGER (no tocar, análogo a D6 del catálogo):**
- El naming **“Regular / Con luces”** en vez de “diurna/nocturna” es más claro para el ciudadano y sigue siendo fiel al espejo SIREB (`hora_inicio_noche`). 
- **“Tarifas por hora: Regular Antes de 18:00 / Con iluminación Desde 18:00”** explica el *porqué* del precio — exactamente la invariante KB SIREB (“el precio lo fija el catálogo, no el GAD”) hecha UI. 
- Mostrar las franjas de la mañana como **“Pasada”** en gris (en vez de esconderlas) es honesto y enseña que el sistema sabe la hora actual. 
- El selector con 14 días + “Más fechas” es coherente con la ventana de 60 días del backend (`DisponibilidadControllerTest` “fecha mas de 60 dias en futuro devuelve 422”). 
*Mini‑prompt:* nada; solo **no “mejorar” estos cuatro** al aplicar B11/B12/D9/D10.

## Sugerencias IA acotadas (continuo: catálogo cerró en S7)

**S8 — P3 de la reunión no es “crear 4 colores”, es “paridad”.** La grilla pública ya tiene 6 estados. El trabajo real de P3 es **verificar que el admin/ventanilla (`DialogCrearReservaVentanilla.tsx` 961, `MisCampos.tsx` 543) y el móvil (`campo_detalle_screen.dart` 1073, `bloque_disponibilidad.dart` 85) usen la MISMA paleta y los mismos 6 estados**. Si el admin pinta “pendiente” distinto a “En cobro”, el funcionario y el ciudadano ven mundos diferentes. 
*Mini‑prompt:* cuando toquemos P3, abrir los 3 frentes (público/admin/móvil) y unificar el mapa estado→color en un solo lugar (idealmente un token/const compartido, no 3 copias).

**S9 — el toggle de tarifa podría ser también un filtro de “solo diurnas / solo nocturnas” si el PO lo quiere.** Hoy (según B11) o es legendario o filtra mal. Si el ciudadano quiere ver *solo* las franjas con luces, un filtro activo sería útil; si no, mejor quitarle el estado “seleccionado” para que no prometa lo que no hace (cierra B11 caso (a)). 
*Mini‑prompt:* decidir con el PO: ¿filtro o leyenda? y alinear el chip a esa decisión.

## Lo que NO es bug (para que no persigas fantasmas)

- **“cancha nueva trinidad” en minúscula** y **“av. flor de patuju”** = dato espejado de SIREB/BD, no corregible en web‑public (igual que B5 de landing).
- **El precio Bs 50 / Bs 150 mostrado** = orientación del espejo local (`tarifas_campo` vía presenter 333), **correcto**; el monto final lo confirma el webhook al pagar. Lo que rompe la invariante no es el precio, es el **footer B6** (“pago online”).
- **Que no haya franjas “Ocupada” ni “En cobro” hoy** = normal, nadie reservó en esta cancha hoy; la leyenda las contempla para cuando existan.
- **Las 6 de la mañana en “Pasada”** = correcto, el front calcula por hora actual (son ~15:14 según el sync del catálogo).

## Guardado

```text
DETALLE CANCHA (CampoDetalle 560 + GrillaDisponibilidad 238 + SelectorFechas 121)
  — DOSSIER, NO tejí, NO pedí archivos, espero siguiente imagen. Numeración continua
  (landing B1-B5/D1-D4/S1-S4 · catálogo B6-B10/D5-D8/S5-S7 · detalle B11-B12/D9-D10/S8-S9).
HALLAZGOS QUE REORDENAN LA REUNIÓN:
  * P3 (4 colores) YA HECHA acá con 6 estados (Regular/Con luces/Seleccionada/Ocupada/
    En cobro/Pasada) → P3 NO es "crear colores", es PARIDAD admin(DiaLogoVentanilla 961
    +MisCampos 543)+móvil(campo_detalle_screen 1073+bloque_disponibilidad 85) = S8.
  * naming "Regular/Con luces" + "Antes/Desde 18:00" = didácticamente SUPERIOR a
    diurna/nocturna y fiel al espejo SIREB (hora_inicio_noche) → PROTEGER, no tocar.
  * franjas "Pasada" en gris (no escondidas) + selector 14d+"Más fechas" coherente con
    ventana 60d (DisponibilidadControllerTest) → aciertos a proteger.
BUGS: B11 SOSPECHA (no confirmada) toggle "Regular" activo igual pinta nocturnas lila
  → leer GrillaDisponibilidad 238: si filtra mal = bug; si es legendario = desacoplar
  estado "seleccionado" del chip (UX). B12 fila DOMINGO sin label en "Horarios de
  atención" → probable mapeo dia_semana=0 sin nombre en CampoDetalle 560, verificar
  contra presenter 333 sortBy(dia_semana) (7 filas = 7 labels). B4 AMPLIADO: código
  SIREB cnt-sedede-001 también en hero detalle (no solo card) → aplicar en CampoCard
  182 Y CampoDetalle 560. B6 CONFIRMADO global otra vez (Footer 109).
DIDÁCTICA: D9 leyenda ya tiene "En cobro" pero no dice "qué hago" → micro-copy conecta
  P6/zombie (schedule no corre = naranja para siempre), refuerza CuentaRegresiva 81 sin
  tocar backend. D10 tip "múltiples franjas" no avisa tope anti-hoarding (M1/M2/M3 por
  IP, AntiHoardingTest 310, número en ParametroSistema 22 → no hardcodear, aludir).
SUGERENCIAS: S8 unificar mapa estado→color en token/const compartido (no 3 copias
  público/admin/móvil). S9 decidir con PO si el toggle es filtro o leyenda y alinear chip.
NO-BUG: minúsculas/dirección=dato SIREB; precio Bs50/150=orientación espejo correcta
  (lo que miente es footer B6, no el precio); sin Ocupada/En cobro hoy=normal; mañanas
  "Pasada"=front calcula hora actual, correcto.
KB HONESTA: SIREB aplica (Regular/Con luces=diurna/nocturna espejo; invariante monto
  final=webhook rota por footer B6). Rolldown circularDependency=false NO (no buildié
  web-public, es copy/render). React DevTools NO (B12 parece estado roto pero es mapeo
  de dato, lo decide leer CampoDetalle 560, no la extensión). wa.me NO acá (P8 en
  ConsultarEstado 299, header "Mis comprobantes" solo apunta).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco, RoleMiddleware
  59) · A4 Pago.tsx 576 (dinero, web-public, NO visto aún) · D2 Bug C (TarifaCampo 107
  NUNCA llegó) · C1→C2 · gates F1-F4 · E mobile (piscina real: piscina_datos_screen 260
  + piscina_service 145; P3-paridad móvil: campo_detalle_screen 1073 + bloque_disponibilidad
  85) · higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests
  HTTP lote) · reunión P1/P2/P4/P5/P7 (orden 8→3→7→4→1+2; P3 reencuadrada a S8 paridad) ·
  frentes web-public aún NO vistos: Reserva 216, Pago 576, ConsultarEstado 299 (P8/wa.me),
  PiscinaDetalle 391, Comprobante 210/ComprobantePiscina 190, MapaCampos 151.
```

En una línea: este detalle me reordena la reunión en dos puntos — la **P3 de “4 colores” ya está hecha acá con 6 estados**, así que el trabajo real es **paridad admin+móvil (S8)**, no crear colores; y el naming **“Regular / Con luces” + “Antes/Desde 18:00” es didácticamente mejor que diurna/nocturna y fiel al espejo SIREB, hay que protegerlo no tocarlo**; los bugs son **B12** (la fila del **domingo perdió su etiqueta** en “Horarios de atención”, a verificar el mapeo `dia_semana` en `CampoDetalle` 560) y **B11** (sospecha no confirmada: el **toggle “Regular” activo igual pinta nocturnas**, a leer `GrillaDisponibilidad` 238 para saber si filtra mal o solo es legendario), más la confirmación de que el **footer mentiroso (B6) y el código SIREB interno (B4) aparecen también acá**; en didáctica lo de mayor valor es **decirle al ciudadano qué hacer si ve una franja “En cobro” (D9, conecta con el zombie P6)** y **aludir al tope de múltiples franjas (D10, anti‑hoarding)**; todo en mini‑prompts con archivo y LoC para el dossier, **sin tejer y esperando la siguiente imagen** — los frentes que aún no vi (`Pago.tsx` 576 con el A4 de dinero, `Reserva` 216, `ConsultarEstado` 299 para P8/`wa.me`, `PiscinaDetalle` 391) siguen marcados en la cola para cuando los pases.
