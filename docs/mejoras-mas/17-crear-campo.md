Veredicto en una línea: tenés razón en las dos observaciones, pero con un matiz que cambia el bug — este diálogo (`CampoFormDialog.tsx` 862, la acción “+ Crear campo simple” de la pestaña Catálogo SIREB) **sí crea el CONTENEDOR de los 3 modelos** (cancha, piscina y evento son todos “campo deportivo” con un `Tipo de campo`), lo que **NO** crea acá es el **paso 2 específico**: los **turnos/perfiles** de piscina (viven en `Turnos.tsx` 164 / `CrearTurnoDialog` 249 / `CrearTurnosLoteDialog` 156) y las **variantes/servicios** de evento tipo Mamoré/Coliseo/Palacio con energía trifásica/limpieza/escenario (viven en `ArancelVarianteController` 297 / la acción “Gestionar variantes”). Entonces el bug **no es que falten acá** (por diseño el contenedor y el modelo van separados), es **B94 🔴: no hay puente/guía después de guardar** — el funcionario crea el campo piscina y queda a ciegas sobre “ahora andá a Turnos a crear los perfiles”, que es justo el hueco que produce B92 (campo activo sin turnos = invisible en silencio) y alimenta Fix3/B70. Le siguen dos sospechas de integridad (B95 el “Rango válido Beni” que solo informa, B96 el municipio dependiente que quizá no se limpia al cambiar provincia) y un cierre cruzado bueno: **B12 confirmado local al público** (acá los 7 días tienen su label, el domingo perdido era solo del front). **No tejí**, sigo dossier.

> KB honesta de esta captura: **SIREB** aplica con fuerza y es el corazón del diálogo — el subtítulo “el cobro lo define SIREB” y los campos lock “Espeja al servicio SIREB · se corrige en recaudaciones, no acá” son la invariante declarada en la cara del funcionario (refina B76/B88/B9). **React DevTools** → **SÍ aplica y es la herramienta natural de B96**: el municipio dependiente de provincia y el checkbox “Activo” por día son estado del componente; “¿al cambiar provincia se limpia municipio?” se ve inspeccionando el árbol de 862, no leyendo copy. **Rolldown `checks.circularDependency` default `false`** → **SÍ aplica** (web‑admin sí lo buildié) pero este diálogo no mete imports nuevos visibles; `bundlerTimings`/`pluginTimings` → **NO** (no es build). **`wa.me`** → **NO aplica** (alta de campo, no hay QR/WhatsApp; eso es el éxito/P8).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la captura | Archivo (LoC) | Qué cierra |
|---|---|---|
| “Nuevo campo deportivo” + subtítulo “el cobro lo define SIREB” | `CampoFormDialog.tsx` (862) | **B94** sin puente al paso 2; acierto invariante SIREB |
| Código/Nombre lock SIREB (“se corrige en recaudaciones, no acá”) | 862 + `CampoPublicoSirebPresenter` (333) | refina **B8/B9** (el contenedor espeja, no se edita acá) |
| Ubicación: Provincia→Municipio dependiente + Lat/Long + “Rango válido Beni” | 862 + migr `add_ubicacion_a_campos_deportivos` (22) + `StoreCampoDeportivoRequest` (96) | **B95/B96** sospechas integridad; conecta **B10** (provincia que agregué al buscador) |
| Foto “max 4 MB · se muestra en web pública y app móvil” | 862 + migr `add_imagen_url` (19) + `StoreCampoDeportivoRequest` (97) | **B98** sospecha (¿back valida tamaño?) |
| Horarios 7 días con checkbox Activo + “Igualar todos al lunes” | 862 + `HorarioAtencion` (18) + `HorarioAtencionDTO` (10) | **cierra B12** (acá el día SÍ tiene label) |
| `Tipo de campo` (define si es cancha/piscina/evento) | `ModalidadReservaCampo` (8) + `TipoCampo` (18) | **B94** el contenedor no sabe qué paso 2 sigue |
| Puentes que FALTAN (no están en este diálogo) | `Turnos.tsx` (164) / `CrearTurnoDialog` (249) / `CrearTurnosLoteDialog` (156) para piscina; `ArancelVarianteController` (297) / `ArancelVariante` (56) / `SolicitudReservaServicio` (43) para evento | **B94** el paso 2 vive en otro frente sin guía |

## Confirmación de tus dos observaciones (con línea, y el matiz)

**Observación 1 — “no existe para crear piscina”: CIERTO en el fondo, con matiz.** El campo piscina (H. PISCINA OLIMPICA, tipo Natación) **sí** se crea como contenedor en este diálogo (por eso en la pantalla anterior aparecía vinculado `0005` verde). Lo que **no** se crea acá son los **turnos y perfiles** (“10:00‑12:00 · Mayores de 12 · cupo 10”), que viven en `Turnos.tsx` (164) + `CrearTurnoDialog` (249) + `CrearTurnosLoteDialog` (156). Entonces: contenedor acá, modelo allá. El hueco es que **no hay puente** (B94).

**Observación 2 — “crear Mamoré y los que tienen trifásica/limpieza/variantes”: CIERTO, mismo patrón.** Estadio Gran Mamoré / Coliseo / Palacio **sí** se crean como contenedor acá (tipo Fútbol), pero sus **variantes arancelarias y servicios adicionales** (energía trifásica, limpieza post‑evento, escenario, con códigos `SEDEDE-*-EVENTO-*`) se gestionan en `ArancelVarianteController` (297) vía la acción “Gestionar variantes” de la pantalla anterior. Contenedor acá, modelo allá, sin puente (B94).

**El matiz que convierte tu observación en bug:** los 3 modelos comparten el contenedor (esta pantalla) pero divergen en el paso 2, y **nada en 862 le dice al funcionario “guardaste un campo de tipo Natación → ahora creá sus turnos en Turnos” / “guardaste un campo de evento → ahora gestioná sus variantes”**. Eso es B94, y es la causa upstream de B92 (campo activo sin turnos/variantes = invisible en silencio en el público, B35/B70) y de Fix3.

## Bugs nuevos de este frente (numeración continua: paramétricas cerró en B93)

**B94 🔴 (flujo/paridad de modelos, el hallazgo de tu observación) — el diálogo crea el contenedor pero no guía al paso 2.** `CampoFormDialog.tsx` (862) termina en “Crear campo” y cierra, sin importar el `Tipo de campo` elegido. Si el tipo implica turnos (piscina) o variantes (evento), el funcionario queda sin señal de que el campo **aún no es reservable** hasta completar el paso 2 en otro frente. Conecta B87 (los 3 modelos que la columna mezclaba sin label), B92 (campo activo sin modelo = invisible), Fix3, B70.
*Mini‑prompt (verificar):* leer 862 → ¿al guardar, redirige/ofrece “Ir a crear turnos” o “Ir a gestionar variantes” según el tipo? Si no, agregar el puente post‑guardado (o al menos un toast “campo creado, ahora configurá sus turnos/variantes”). No hace falta fusionar los diálogos (por diseño van separados), solo el puente. Conecta S46.

**B95 (sospecha, lente c) — “Rango válido Beni: latitud -18.0 a -9.6 · longitud -67.5 a -57.4” solo informa, ¿valida?** El texto declara el rango pero no sé si el front bloquea un lat/long fuera de él o solo lo muestra como ayuda. Si solo informa, un coordenada mal tipeada (ej. -14.8432 está bien, pero -4.0 pasaría) llega al back y rompe el mapa público (B24/B27, `MapaCampos` 151) y el móvil. Con línea: `StoreCampoDeportivoRequest` (96) + la migr `add_ubicacion` (22).
*Mini‑prompt (verificar):* leer 96/22 → ¿hay `between` en lat/long con el rango Beni? Si no, agregar validación back (no confiar solo en el hint del front). Conecta S48.

**B96 (sospecha, React DevTools aplica) — municipio dependiente de provincia: ¿se limpia al cambiar provincia?** “Municipio * — Elegí primero la provincia” implica cascada provincia→municipio. Si el funcionario elige provincia A + municipio X, luego cambia a provincia B, ¿el municipio X (que pertenece a A) se limpia o queda colgando un municipio de otra provincia? Estado del componente en 862.
*Mini‑prompt (verificar):* inspeccionar 862 (React DevTools) → ¿el `onChange` de provincia resetea municipio? Si no, bug de dato (municipio de otra provincia guardado). Conecta B10 (la provincia que agregué al buscador público depende de que este dato esté limpio).

**B98 (sospecha, lente c) — foto “max 4 MB”: ¿el back valida el tamaño o solo el front?** El hint dice “JPG, PNG o WebP · max 4 MB”. Si solo el front lo muestra, un upload de 5 MB pasa al back y llena `storage/campos` (las fotos .jpg que vi, alguna de 976 KB). Con línea: `StoreCampoDeportivoRequest` (97) + `filesystems` (68).
*Mini‑prompt (verificar):* leer 97 → ¿hay `max:4096` en la regla de imagen? Si no, agregar. Menor, pero es integridad de storage.

**B12 ✅ CERRADO (confirmación cruzada) — los 7 días SÍ tienen label acá.** La tabla de horarios muestra Lunes/Martes/Miércoles/Jueves/Viernes… con su nombre en cada fila y checkbox “Activo”. El público (`CampoDetalle.tsx` 560) perdía el label del domingo. Entonces **B12 queda confirmado como local al front público**, no al dato ni al admin: el admin mapea los 7 días bien, el público no. Fix B12 acotado a 560, no a 862 ni a `HorarioAtencion` (18).

## Aciertos a PROTEGER (no tocar, análogo a D6/D16)

- **Subtítulo “el cobro lo define SIREB” + campos lock “Espeja al servicio SIREB · se corrige en recaudaciones, no acá”** = la invariante SIREB declarada en la cara del funcionario en el punto exacto donde podría tentar tipear un precio. **Refina B76/B88/B9** y es el modelo que el público debería copiar. Proteger.
- **Código lock “Traído del catálogo SIREB · no editable”** = el funcionario no inventa códigos (cierra B21/B83 en la fuente: los códigos los trae el catálogo, no se tipean). Proteger.
- **“Rango válido Beni” declarado** = aunque sea solo hint (B95), **decirle al funcionario el rango** es didácticamente correcto; el fix B95 es agregar la validación back, no quitar el hint. Proteger el hint.
- **“Se muestra en la web pública y la app móvil”** (foto) = conecta el admin con los dos consumidores (E mobile, frente vírgen). Proteger.
- **Horarios con “Desmarcar los días sin servicio” + “7 de 7 días” + “Igualar todos al lunes”** = throughput (lente a): el funcionario configura 7 días en 1 clic si son iguales. Proteger (y es la prueba de B12 cerrado).

## Didáctica / operación del funcionario (continuo: paramétricas cerró en D49)

**D50 — después de guardar un campo de tipo piscina/evento, el sistema debe decir qué sigue** (cierra B94): “Campo creado. Ahora creá sus turnos en Turnos / gestioná sus variantes en Variantes”. Sin eso, el funcionario crea un campo que no es reservable y no sabe por qué (B92).
*Mini‑prompt:* ver B94/S46.

**D51 — el hint de rango Beni + foto 4 MB deben estar respaldados por validación back** (cierra B95/B98): el funcionario que tipea mal una coordenada no debe descubrirlo recién cuando el mapa público se rompe.
*Mini‑prompt:* ver B95/B98/S48.

**D52 — la cascada provincia→municipio debe ser limpia** (cierra B96): cambiar provincia no puede dejar un municipio huérfano de otra provincia, porque eso ensucia el buscador público (B10) y el mapa (B24).
*Mini‑prompt:* ver B96.

## Sugerencias IA acotadas (continuo: paramétricas cerró en S45)

**S46 — el puente post‑guardado es el fix de menor costo para B94** (no fusionar diálogos, solo guiar): al cerrar 862 con éxito, si el tipo implica paso 2, ofrecer el link. Conecta S37 (B70 quería “crear turno/sincronizar” junto al gate silencioso) — el mismo puente sirve a los dos.
*Mini‑prompt:* ver B94.

**S47 — los 3 modelos comparten contenedor (862) y divergen en paso 2 (Turnos/Variantes): declarar el `ModalidadReservaCampo` (8) como el discriminador visible** en 862, para que el funcionario sepa en qué paso está del flujo. Conecta B87/B50/S45 (el token de modalidad que ocupación/ventanilla también deberían consumir).
*Mini‑prompt:* ver B94 + S45.

**S48 — B95/B96/B98 son la misma clase: hints de front sin validación back** (rango Beni, cascada municipio, tamaño foto). Unificar en `StoreCampoDeportivoRequest` (96/97) las tres reglas. Lente c (integridad).
*Mini‑prompt:* al tejer, agregar `between` lat/long, reset de municipio en el `onChange` (o validación back de pertenencia), y `max` de imagen, en 96/97.

## Lo que NO es bug (para que no persigas fantasmas)

- **Que piscina/variantes no se creen en este diálogo** = por diseño (contenedor vs modelo separados); el bug es la falta de puente (B94), no que estén en otro frente. Tus observaciones son correctas en el hecho, el matiz es el puente.
- **Lat/Long -14,8432 / -64,9012** = coordenadas válidas de Trinidad dentro del rango Beni (‑18 a ‑9.6 / ‑67.5 a ‑57.4), no bug; el bug sería si el front no validara un valor fuera (B95).
- **“Cancha Sintética N° 2” en Nombre lock** = espejo SIREB correcto (CS2 estaba “Sin vincular” en la pantalla anterior, este diálogo lo vincula); no es el minúscula‑ruido de B5 (ese era dato espejado, acá es el nombre oficial).
- **“7 de 7 días” todos 08:00‑20:00** = configuración válida (todos los días mismo horario); el botón “Igualar todos al lunes” existe justo para eso. No bug.
- **Foto “Sin foto” placeholder** = opcional, normal.

## La puerta de “corregir ya” (no tejo hasta confirmar)

Sigo dossier. Si querés arrancar a corregir este diálogo, lo que **más vale** es **B94** (el puente, que cierra tu observación y alimenta B92/Fix3/B70) y **B95/B96/B98** (integridad de ubicación/foto, lente c), y para eso necesito `CampoFormDialog.tsx` (862) + `ModalidadReservaCampo` (8) + `StoreCampoDeportivoRequest` (96/97) + la migr `add_ubicacion_a_campos_deportivos` (22). B12 **no se teje acá** (es fix del público 560, ya acotado). **S46/S48** son el fix de mayor rendimiento: el puente post‑guardado + las 3 validaciones back cierran B94/B95/B96/B98 y desactivan B92 aguas abajo.

## Guardado

```text
ADMIN/PARAMÉTRICAS-CAMPO-ALTA (CampoFormDialog.tsx 862, acción "+ Crear campo simple"
  de Catálogo SIREB + ModalidadReservaCampo 8 + StoreCampoDeportivoRequest 96/97 +
  migr add_ubicacion 22 + add_imagen_url 19 + HorarioAtencion 18 + HorarioAtencionDTO 10
  + puentes FALTANTES Turnos 164/CrearTurnoDialog 249/CrearTurnosLoteDialog 156 y
  ArancelVarianteController 297/ArancelVariante 56/SolicitudReservaServicio 43) —
  DOSSIER, NO tejí, sigo modo "ver todo corregir después". Numeración continua
  (paramétricas-listado B87-B93/D47-D49/S44-S45 · campo-alta B94-B98/D50-D52/S46-S48 +
  cierre B12).

CONFIRMACIÓN DE LAS 2 OBSERVACIONES DEL USUARIO (con matiz que convierte en bug):
  Obs1 "no existe para crear piscina" CIERTO en fondo: campo piscina SÍ se crea acá como
  CONTENEDOR (tipo Natación, por eso aparecía vinculado 0005 verde), lo que NO se crea son
  los TURNOS/PERFILLES (viven Turnos 164/CrearTurnoDialog 249/Lote 156).
  Obs2 "crear Mamoré y variantes trifásica/limpieza" CIERTO, mismo patrón: campo evento SÍ
  se crea acá (contenedor tipo Fútbol), las VARIANTES/SERVICIOS (SEDEDE-*-EVENTO-*) se
  gestionan en ArancelVarianteController 297 vía "Gestionar variantes".
  MATIZ = B94🔴: los 3 modelos comparten contenedor (862) y divergen en paso 2
  (Turnos/Variantes), y NADA en 862 guía al funcionario al paso 2 post-guardado → campo
  creado pero no reservable en silencio = causa upstream B92 (campo activo sin modelo =
  invisible público B35/B70) y alimenta Fix3. El bug NO es que falten acá (por diseño van
  separados), es la falta de puente.

BUGS: B94🔴 diálogo crea contenedor no guía paso 2 (conecta B87/B92/Fix3/B70/S37/S46).
  B95 (sospecha lente c) "Rango válido Beni lat -18/-9.6 long -67.5/-57.4" solo informa,
  ¿valida back? leer 96/22 pa' between lat/long (si no, coordenada mala rompe mapa público
  B24/B27 y móvil). B96 (sospecha React DevTools aplica) municipio dependiente provincia
  ("Elegí primero la provincia") → ¿se limpia al cambiar provincia? si no, municipio
  huérfano de otra provincia ensucia buscador público B10 + mapa B24. B98 (sospecha lente
  c) foto "max 4 MB" ¿back valida o solo hint front? leer 97 pa' max imagen (storage/campos
  ya tiene .jpg de 976KB). B12✅ CERRADO: los 7 días SÍ tienen label acá (Lun..Dom con
  checkbox Activo) → B12 confirmado LOCAL al público 560, NO se teje acá.

ACIERTOS PROTEGER: subtítulo "el cobro lo define SIREB" + lock "Espeja al servicio SIREB ·
  se corrige en recaudaciones, no acá" (invariante SIREB en cara del funcionario, refina
  B76/B88/B9, modelo que público debe copiar); código lock "Traído del catálogo SIREB · no
  editable" (cierra B21/B83 en la fuente, códigos no se tipean); hint rango Beni (decirlo
  es correcto, B95 es AGREGAR validación no quitar hint); "Se muestra en web pública y app
  móvil" (conecta E mobile); horarios "Desmarcar días sin servicio" + "7 de 7" + "Igualar
  todos al lunes" (throughput lente a, y prueba B12 cerrado).

DIDÁCTICA/OP: D50 post-guardar tipo piscina/evento decir qué sigue (B94/S46); D51 hints
  rango/foto respaldados por validación back (B95/B98/S48); D52 cascada provincia→municipio
  limpia (B96, cuida B10/B24).

SUGERENCIAS: S46 puente post-guardado = fix menor costo pa' B94 (no fusionar diálogos, solo
  guiar; mismo puente sirve B70/S37); S47 declarar ModalidadReservaCampo 8 como discriminador
  visible en 862 (conecta B87/B50/S45 token compartido ocupación/ventanilla); S48 B95/B96/B98
  = misma clase hints-front-sin-validación-back → unificar en 96/97 (between lat/long, reset
  municipio, max imagen). Lente c integridad.

NO-BUG: piscina/variantes no se crean acá = por diseño (contenedor vs modelo), bug es puente
  B94 no ausencia; lat/long -14.84/-64.90 = válidas Trinidad dentro rango Beni; "Cancha
  Sintética N° 2" lock = espejo SIREB correcto (no el minúscula-ruido B5); "7 de 7 días"
  08-20 = config válida (botón Igualar existe pa' eso); foto "Sin foto" = opcional normal.

KB HONESTA: SIREB aplica corazón (subtítulo "cobro lo define SIREB" + lock espejo =
  invariante declarada, refina B76/B88/B9). React DevTools SÍ aplica Y es herramienta
  natural de B96 (cascada provincia→municipio + checkbox Activo son estado del componente,
  se ve en árbol 862). Rolldown circularDependency=false SÍ aplica (web-admin buildié) pero
  862 no mete imports nuevos; bundlerTimings/pluginTimings NO (no build). wa.me NO aplica
  (alta de campo, no QR; eso es éxito/P8).

PUERTA CORRIGIR YA: lo que vale es B94 (puente, cierra obs usuario + alimenta B92/Fix3/B70)
  + B95/B96/B98 (integridad ubicación/foto lente c) → pido CampoFormDialog 862 +
  ModalidadReservaCampo 8 + StoreCampoDeportivoRequest 96/97 + migr add_ubicacion 22. B12
  NO se teje acá (público 560). S46/S48 = mayor rendimiento (puente + 3 validaciones back
  cierran B94/B95/B96/B98 y desactivan B92 aguas abajo).

COLA REORDENADA: Fix3 piscina A/B (PiscinaReservaService 284, dueño B62/B69-form/B70, AHORA
  también B94 puente) · A1 S3/S7/RoleMiddleware 59 (app-sidebar 206 + Funcionarios 881,
  B48/B61) · A4 Pago 576 (RecaudacionesApiClient 415 + webhook 97, B84) · D2✅ UBICADO
  (TarifaCampo 107 + sync 305, raíz B43/B37/B50/B62/B67/B71/B76/B84/B88) · C1→C2 · gates
  F1-F4 · E mobile (piscina real 260/145; P3-paridad 1073/85; B43/B36/B42/B39/B44/B50/B62/
  B69/B70/B76/B78/B79/B84/B86/B87/B88/B94 se propagan a móvil; S16/S38/S41/S45/S46 también) ·
  higiene (zombis, A3, D5, AgregarVarianteDialog, migr timerange piscina, tests HTTP lote +
  S33 test conversión) · reunión P1/P2/P4/P5/P7/P9 (P5 asistencia 56/125, B86 ausente piscina
  = hueco P5; S30 reusa input B52; P7 reportes 245/135; P9 asignaciones 579/22/26, B61
  evidencia parcial; P1/P2 auth 164/34/126; P3 reencuadrada S8; P8 reencuadrada S16/S38;
  D11 NIT prepara P1/P2). DOSSIER PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO
  B44-B98/D29-D52/S25-S48.
PRÓXIMA NATURAL: Turnos.tsx 164 (el paso 2 de piscina que B94 señala, cerrar Fix3/B70/B92)
  o Tarifas.tsx 703 (D2/107/77 raíz de precio B88) o Funcionarios 881 + Asignaciones 579
  (A1/P9/B48/B61 permisos) o CampoFormDialog edición (el mismo 862 en modo editar, pa' ver
  si el puente B94 aplica también al editar).
```

En una línea: tus dos observaciones son correctas en el hecho — este diálogo **no crea los turnos de piscina ni las variantes/servicios de evento tipo Mamoré** (energía trifásica, limpieza, escenario) — pero el matiz que las convierte en bug es que **sí crea el contenedor de los 3 modelos** (cancha, piscina y evento son todos “campo deportivo” con un tipo), y lo que falta es el **puente**: **B94 🔴**, nada en `CampoFormDialog.tsx` (862) le dice al funcionario “guardaste un campo de tipo Natación → ahora creá sus turnos en `Turnos.tsx` (164)” o “guardaste un campo de evento → ahora gestioná sus variantes en `ArancelVarianteController` (297)”, y ese hueco es la causa upstream de **B92** (campo activo sin modelo = invisible en silencio, B35/B70) y alimenta **Fix3**; le siguen dos sospechas de integridad (**B95** el “Rango válido Beni” que quizá solo informa sin validar en back, **B96** el municipio dependiente de provincia que quizá no se limpia al cambiar, con React DevTools como herramienta natural) y **B98** (foto “max 4 MB” ¿valida el back?), más el cierre cruzado bueno de **B12** (acá los 7 días tienen su label, así que el domingo perdido es confirmado local al público 560); los aciertos a proteger son el subtítulo “el cobro lo define SIREB” y los campos lock “se corrige en recaudaciones, no acá” (la invariante SIREB declarada en la cara del funcionario, refina B76/B88/B9) y el código lock “Traído del catálogo SIREB · no editable” (cierra B21/B83 en la fuente). **No tejí**: si querés corregir, lo que vale es B94 + B95/B96/B98 y necesito `CampoFormDialog.tsx` (862) + `ModalidadReservaCampo` (8) + `StoreCampoDeportivoRequest` (96/97) + la migr `add_ubicacion` (22), con **S46/S48** (puente post‑guardado + las 3 validaciones back) como el fix de mayor rendimiento; decime si sigo dossier (próxima natural: `Turnos.tsx` 164, el paso 2 de piscina que B94 señala, o `Tarifas.tsx` 703 para la raíz de precio B88) o arranco con esos archivos.
