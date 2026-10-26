Veredicto en una línea: te respondo las dos cosas por separado y sin relleno — **(1) dónde está cada archivo que falta**, con la ruta literal del árbol de la auditoría que pegaste (para que lo abras y me lo pases, o para que yo sepa dónde mirar), agrupado por “qué captura necesito para cerrar qué”; y **(2) mi síntesis de IA cruzando las 2 capas (y la tercera que no vi)**: saqué **7 grietas de diseño G1‑G7** que **no salen de mirar una pantalla aislada** sino de tener el mapa completo, cada una con un fix estructural que mata N bugs de un golpe — la más grande es **G2: el backend sabe que hay 3 modelos de reserva pero la UI trata todo como cancha y la pileta/evento son excepciones que se cuelan**, que es el padre de B50/B87/B106/B115/B132/B135. **No tejí**, sigo dossier; esto es análisis, no cambio de código.

> KB honesta de este turno (que es meta, no de una pantalla): **`wa.me`** → el doc que pegaste confirma que sin app cae a *“use WhatsApp Web”*, así que el botón “Enviar por WhatsApp” del éxito (961/489) es **robusto por construcción**, no bug — y eso solo pesa en P8/S16/S38 (el frente de QR/WhatsApp), **no** en reportes/mapa/admin general; lo declaro para no inflar la KB como en turnos previos. **SIREB** → aplica como eje de G1 (la invariante está en el backend como código y reimplementada 6 veces en la UI). **Rolldown `checks.circularDependency` default `false`** y **`bundlerTimings` default `true`** → **NO aplican** (no buildié nada este turno, es síntesis). **React DevTools** → **SÍ aplica** cuando arranquemos a tejer G1‑G7, porque varias son “qué prop/ramas monta el componente”, se ven en el árbol.

---

## Parte 1 — dónde buscar lo que falta (rutas literales del árbol)

Te lo agrupo por **qué necesito de vos** (captura del admin/móvil o archivo de código) y **qué gate cierra**. Marcado con 📱 = requiere acción tuya (correr scheduler, crear 2º control, ver el móvil), no basta con leer el archivo.

| Para cerrar | Abrime esto (ruta exacta) | LoC | Qué decide |
|---|---|---|---|
| **Fix3 + B62 + B69‑form + B70 + B103** (el corazón de pileta, el gate de dinero que queda) | `backend/app/Services/Piscina/PiscinaReservaService.php` | 284 | de dónde congela el precio al crear la reserva (raíz B43/B88) y qué pasa al editar perfiles con reservas activas (Fix3) |
| idem, el análogo de cobro | `backend/app/Services/Piscina/PiscinaConfirmacionCobroService.php` | 82 | si pileta tiene el mismo ciclo liquidación/pago que cancha (pa’ montar los 5 bloques de B86) |
| idem, los modelos | `backend/app/Models/PiscinaReserva.php` (49) + `PiscinaReservaDetail.php`→`PiscinaReservaDetalle.php` (68) | 49+68 | campos `monto_total` vs `monto_confirmado` (B37) y `cantidad`/`precio_unitario` sin subtotal (B42) |
| **B135 + B138 + B141 + P7 del todo** (¿el reporte excluye pileta? ¿mide conversión?) | `backend/app/Services/ReportesService.php` (135) + `backend/tests/Feature/Api/V1/ReportesTest.php` (571) + `web-admin/src/types/reportes.ts` (45) | 135+571+45 | si la query de ingresos une `solicitudes_reserva`+`piscina_reservas` o solo cancha; si el DTO trae pendientes/expiradas |
| **P7** (el otro reporte gerencial, vírgen) | 📸 captura de `web-admin/src/pages/reportes/ClientesFrecuentes.tsx` (178) corriendo | 178 | mismo lente que 245: ¿conversión? ¿fuente SIREB? |
| **A1 + B48 + B61 + B128** (permisos/roles, el gate que lleva TODO el tramo sin dueño) | `backend/app/Http/Middleware/RoleMiddleware.php` (59) + `web-admin/src/components/app-sidebar.tsx` (206) + 📸 captura de `Funcionarios.tsx` (881) con un 2º rol logueado | 59+206 | qué rol accede a qué ruta, y si el sidebar filtra ítems por rol o pinta todo |
| **P9** (exclusividad funcionario↔cancha) | `backend/database/migrations/2026_09_01_101135_create_asignaciones_funcionario_table.php` (22) + `backend/app/Models/AsignacionFuncionario.php` (26) + 📱 creá un 2º control y asignale la MISMA cancha | 22+26 | si el índice unique es `(func,campo)` → dos controles SÍ pueden tener la misma cancha (el riesgo P9 vive ahí). **Esta pantalla sola no lo cierra** (es por‑funcionario), necesito el 2º control |
| **P5** (asistencia, el último gate grande sin dueño) | `backend/app/Http/Controllers/Api/V1/Admin/AsistenciaController.php` (56) + `backend/app/Services/MarcarAsistenciaService.php` (125) + `backend/app/Http/Requests/StoreAsistenciaRequest.php` (26) + `backend/tests/Feature/Api/V1/Admin/AsistenciaReservaTest.php` (315) + 📸 captura de `MisCampos.tsx` (543) con el botón “Marcar asistencia” | 56+125+26+315 | si hay guarda de franja pasada / asistencia retroactiva (fraude) y si pileta tiene asistencia (B86 la omite) |
| **A4 + B84 + B134** (semántica pagada/Pagado, syncs) | `backend/app/Integrations/Recaudaciones/RecaudacionesApiClient.php` (415) + `backend/app/DTOs/EstadoCobroDTO.php` (23) + `backend/app/Services/ConfirmacionCobroService.php` (106) + `backend/app/Http/Controllers/Api/V1/WebhookRecaudacionesController.php` (97) | 415+23+106+97 | si “Estado: pagada” es liquidación y “Pagado” es pago (desambiguar UI) o es bug de sync |
| **B13/B31/B76/B18** (contrato de campos + wording “confirmado”) | `backend/app/Http/Requests/StoreSolicitudReservaRequest.php` (97) + `StorePiscinaReservaRequest.php` (32) + `web-public/src/components/reserva/FormularioSolicitante.tsx` (155) | 97+32+155 | teléfono required vs opcional, `*` en nombre/CI, y qué campo pinta “confirmado” |
| **B43/B88/B99/B67/B71/B72** (raíz de precio, ya ubicada en 703) | `backend/app/Models/TarifaCampo.php` (107) + `backend/app/Services/Piscina/PiscinaTarifaResolver.php` (77) + `web-admin/src/components/piscina/CrearTurnoDialog.tsx` (249) | 107+77+249 | confirmar que 249 es input libre y 77 expone los 6 valores (diurno/nocturno × perfil) |
| **B94/B123** (puente post‑guardado) | `web-admin/src/components/parametricas/CampoFormDialog.tsx` (862) + `web-admin/src/pages/usuarios/Asignaciones.tsx` (579) | 862+579 | si al guardar ofrece “Ir a turnos/variantes/asignaciones” según tipo/rol |
| **B8/B110** (presenter pileta + fecha de sync) | `backend/app/Services/CampoPublicoSirebPresenter.php` (333) + `backend/app/Services/CatalogoSirebService.php` (202) | 333+202 | si pileta tiene análogo limpio del presenter (hoy `sireb=null` solo en público) y de dónde sale “última actualización” |
| **B84/B86/B111** (auditoría transversal) | `backend/app/Models/Auditoria.php` (29) + `backend/app/Services/AuditoriaService.php` (48) + `web-admin/src/components/reservas/DialogDetalleReserva.tsx` (838) | 29+48+838 | qué registra la auditoría y por qué 838 la muestra en cancha y no en pileta |
| **B22/B17/B21** (zombie + códigos) | `web-public/src/pages/ConsultarEstado.tsx` (299) + `web-public/src/components/comprobante/EstadoReserva.tsx` (183) | 299+183 | el help `PTS-` fantasma y el card “Pendiente” infinito |
| **P6** 📱 (scheduler muerto) | `backend/routes/console.php` (9) — ya lo vi, está declarado | 9 | **no se cierra leyendo**: hay que correr `php artisan schedule:work` (dev) o poner el cron (prod). Los zombies de 4 y 7 días en Reservas lo prueban |
| **E móvil** 📱 (la 3ª capa, NUNCA la vi corriendo) | `mobile/lib/screens/piscina_datos_screen.dart` (260) + `mobile/lib/services/piscina_service.dart` (145) + `mobile/lib/screens/campo_detalle_screen.dart` (1073) + `mobile/lib/models/bloque_disponibilidad.dart` (85) + `mobile/lib/models/piscina_disponibilidad.dart` (87) | 260+145+1073+85+87 | **hipótesis G7**: el móvil reimplementa otra vez la vista del mismo dato → divergencia garantizada. Necesito capturas del móvil corriendo, no solo el código |

**Lo que NO puedo cerrar aunque me pases el archivo** (te lo digo para que no esperes magia): **P6** (requiere correr el scheduler), **P9** (requiere un 2º control con la misma cancha), **E móvil** (requiere ver la app corriendo, no leer Dart), y **B62** (la expiración 75% es señal; la causa la confirmo leyendo 284, pero si es ruido de seed —los UUIDv7 de la misma tanda que noté en Asignaciones— no es bug de flujo). Todo lo demás de la tabla se cierra con el archivo + una captura.

---

## Parte 2 — mi síntesis de IA: las 7 grietas que solo se ven teniendo las 2 (3) capas

Hasta ahora fui reactivo: veo pantalla → cruzo con backend → saco bug (B1‑B142). Con el mapa completo en la cabeza, lo que sigue **no es un bug más**, es la **estructura que genera los bugs**. Te las dejo como **G1‑G7**, cada una con la evidencia cruzada (qué vi en público + qué vi en admin + qué dice el backend), el **fix estructural único**, y **qué mata de un golpe**. Al final, el ranking de “si arreglás 3, destrabás 40”.

### G1 — La invariante SIREB vive en el backend como código, pero la UI la reimplementa 6 veces con 6 wordings, y 4 la rompen
**Evidencia cruzada.** Backend: el sync (305/202), el webhook (97), el polling (jobs), `monto_confirmado` (migr 16), `ConfirmacionCobroService` (106) — la invariante **está implementada correctamente**. UI: footer público dice “pago online seguro” (B6, miente); comprobantes pintan `monto_total` como “PAGADO” (B18/B37); éxito ventanilla titula “Monto confirmado por SIREB” en una **pendiente** (B76); tarifas admin (703) y reportes (245) **sí** la declaran bien (banner “los precios los define SIREB” / “conciliación oficial = Paitití/SIREB”). O sea: **2 de 6 frentes la hacen bien, 4 la rompen, y los 2 buenos son los últimos que vi**.
**Fix estructural.** Una capa de presentación de “verdad monetaria”: un hook `useVerdadSireb(reserva)` que devuelva `{monto_operativo, monto_oficial, estado_conciliacion, aviso}` y que **todos** los frentes consuman, más un componente `<AvisoConciliacionSireb/>` (el banner de 245, que es el mejor copy del dossier) montado en los 6. No tocar la lógica de sync (415/106/82), solo dejar de reimplementar el wording.
**Mata de un golpe:** B6, B16, B18, B37, B76, B79, B84, B135 (nivel copy). Es S52+S69 convergiendo a UNA cosa que yo venía tratando como dos sugerencias separadas.

### G2 — El backend sabe que hay 3 modelos de reserva; la UI trata todo como cancha y pileta/evento son excepciones que se cuelan
**Evidencia cruzada.** Backend: `ModalidadReservaCampo` (8) enum + migr 21 + tres familias de tablas (`SolicitudReservaDetalle` 53 franja / `PiscinaTurno`+`PiscinaTurnoPerfil` 43/39 cupo‑perfil / `ArancelVariante`+`SolicitudReservaServicio` 56/43 evento). UI: ocupación pinta pileta como franja binaria (B50), paramétricas mezcla simple/variante sin label (B87), tarifas impone el corte de iluminación a pileta (B106), tipos declara el modelo en **texto descriptivo** no en flag (B115), mapa calcula % con denominador genérico (B132), reportes dice “canchas” excluyendo pileta (B135), ventanilla pileta a ciegas de total/precio (B69). **El discriminador existe en el back y NO existe como concepto en el front.**
**Fix estructural.** Hacer `modalidad_reserva` **ciudadano de primera clase en la UI**: un token/const que cada componente lea y ramifique (un “router de modalidad”), en vez de que cada pantalla infiera mal o ignore. El chip de tipo de Asignaciones (B115) es el límite sano: ahí el tipo es solo etiqueta de lugar y está bien; el peligro es cuando el tipo se lee como modalidad para decidir flujo.
**Mata de un golpe:** B50, B87, B106, B115, B132, B135, B69, B73, B47. Es S45+S47+S53+S66+S71 convergiendo. **Esta es la grieta más grande del sistema**: no es 8 bugs, es 1 decisión de arquitectura que faltó y se manifiesta 8 veces.

### G3 — “Operativo” no es un booleano, es una condición derivada que nadie calcula → el gate silencioso
**Evidencia cruzada.** Un campo está “Activo” (booleano) pero **no es reservable** si le faltan tarifas SIREB (147 `continue` silencioso, B35/B70/B92) o turnos/variantes (B94/B123, crea padre sin guiar al hijo). El funcionario crea un campo “activo” que en realidad no funciona, y ni ocupación ni el alta se lo dicen. Backend: no hay ningún `esta_operativo` derivado. UI: el estado Activo/Inactivo (881/655/1022) se muestra como si fuera suficiencia.
**Fix estructural.** Un campo **derivado** `esta_operativo = activo ∧ tiene_tarifas_sireb ∧ (modalidad? tiene_turnos_o_variantes)` + un checklist “qué le falta para ser reservable” que aparezca en ocupación, paramétricas y el alta. Y el patrón de **puente post‑guardado** (S46/S60/S63): al guardar una padre con hijos obligatorios, abrir/preparar el hijo.
**Mata de un golpe:** B35, B70, B92, B94, B123, B105 (solape), y desactiva el 75% de B62 si la causa es “campos creados no operativos”. Es la raíz de por qué el ciudadano ve perfiles desaparecer (B35) y el funcionario no sabe por qué (B70).

### G4 — El formatter de fecha YA existe y funciona; el bug no es “falta helper”, es que nadie lo centralizó y 3 frentes reimplementan mal
**Evidencia cruzada.** Admin 881 pinta “hace 11 d” (relativo, funciona); admin 582 pinta “25/10/2026 4:14:30 p.m.” (absoluto, funciona); 703/245 formatean bien. Pero público 190 pinta ISO crudo (B36), admin 164 pinta ISO crudo (B101), admin 655 pinta “Invalid Date” (B112, el peor: el dato ni llega). **Me autocorregí hace dos turnos**: venía diciendo “crear helper”; la evidencia de 881/582 prueba que **ya existe** y el fix es propagar, no construir.
**Fix estructural.** Un módulo `lib/fecha.ts` en web‑admin **y** web‑public exportando `formatoLargo`/`tiempoRelativo`/`hoyBolivia`, que **nunca** devuelva “Invalid Date” (fallback “—”), consumido por 190/164/655; y en backend un cast/accessor global de timezone `America/La_Paz`.
**Mata de un golpe:** B36, B101, B112, B44, B53, B110, B142 (nivel formato). Es S25+S50+S55+S58 convergiendo, y es **barato** porque el código ya está escrito.

### G5 — 5 formatos de código, 4 labels, ninguna UI los unifica → y el QR/WhatsApp (P8) hereda el desorden
**Evidencia cruzada.** Backend emite: `RES-` (cabecera cancha), `RSV-` (franja cancha), `PIS-` (cabecera pileta), `4‑4‑3` ventanilla (`GZ2B-`/`ZK23-`/`K699-`, B52), y el público **inventa** `PTS-` que el admin no usa (B21). Labels en UI: “Código público” / “Código de seguimiento” / “Código de pago en ventanilla” / “…(SIREB)” / “Código” a secas (B83, 4 labels pa’ lo mismo). El éxito de ventanilla (961/489) tiene “Descargar QR” + “Enviar por WhatsApp” (B52/wa.me, robusto por el fallback del doc), pero el público **no** tiene ese bloque (P8/D18/S16).
**Fix estructural.** Un módulo `CodigoReserva` en backend = única fuente de “qué formato emito y cómo lo llamo”; y un `<CodigoEtiquetado codigo={x}/>` en front que renderice cualquiera de los 5 con el label correcto. El bloque de éxito (S38) se construye **sobre** `CodigoEtiquetado`, y así P8/QR/WhatsApp caen solos.
**Mata de un golpe:** B21, B52, B77, B83, B89, B127, y cierra P8/S16/D18/S38. El `wa.me` del doc aplica **acá y solo acá** (donde hay botón WhatsApp), no en el resto del admin.

### G6 — La auditoría está en el backend pero la UI la expone a pedazos y la omite en pileta y en operaciones destructivas
**Evidencia cruzada.** Backend: `Auditoria` (29) + `AuditoriaService` (48) registran. UI: detalle cancha (838) muestra “Historial de auditoría” + “Ver datos registrados” (acierto); detalle pileta (838 rama pileta) **no** muestra ninguno de los 5 bloques operativos (B86); tarifas muestran historial en horarias pero no en perfiles (B111); desvincular/quitar‑todos/eliminar/editar‑turno‑con‑reservas no piden confirm ni dejan rastro visible (B93/B126/B117/B124/B105).
**Fix estructural.** La auditoría como **capa transversal**: cada acción destructiva o de estado pasa por un `<ConfirmacionConAuditoria/>` que muestra qué va a cambiar y deja rastro; y los 5 bloques de 838 (auditoría/estado SIREB/refrescar/asistencia/link) se montan también en pileta.
**Mata de un golpe:** B86, B93, B111, B117, B124, B126, B105, y unifica con el acierto de 838. Conecta P5 (asistencia) y lente c (destructivo+auditoría).

### G7 — No hay contrato de presentación compartido entre las 3 capas (público / admin / móvil); cada una arma su vista del mismo dato → divergencia garantizada
**Evidencia cruzada (y honestidad: el móvil NUNCA lo vi corriendo).** Backend tiene `CampoPublicoSirebPresenter` (333) para cancha pública, pero **pileta no tiene análogo limpio** (B8, `sireb=null` solo en público). Público y admin ya divergen en todo G1‑G6 (B43 precio, B50 modelo, B69 total, B86 bloques, B101 fecha, B135 dinero). El árbol muestra que el móvil (`campo_detalle_screen` 1073, `piscina_datos_screen` 260, `pago_qr_screen` 343, `comprobante_screen` 104…) **reimplementa otra vez** la vista del mismo recurso con sus propios models (`bloque_disponibilidad` 85, `piscina_disponibilidad` 87). **Hipótesis fuerte, no afirmación**: si público y admin ya divergen con 2 capas, con 3 la divergencia se multiplica, y cada bug de G1‑G6 tiene un tercer consumidor (móvil) que probablemente lo replica.
**Fix estructural.** El backend expone **un presenter por recurso** (extender 333 a pileta/evento, o un `RecursoPresenter` único) y **las 3 capas consumen el mismo JSON**, no 3 queries distintas. Esto es lo que convierte “arreglar 40 bugs” en “arreglar 1 contrato”.
**Mata de un golpe:** la **clase entera** de bugs de paridad (B43/B50/B69/B86/B101/B135 + sus réplicas móviles), y es el único fix que escala a 3 capas. **Requiere capturas del móvil corriendo para confirmarlo** (por eso está en la tabla de la Parte 1 con 📱 y “no lo cierro leyendo Dart”).

---

## Ranking: si arreglás 3, destrabás ~40

No te dejo las 7 al mismo nivel; hay un orden de apalancamiento claro:

1. **G2 (router de modalidad en la UI)** — destraba B50/B87/B106/B115/B132/B135/B69/B73/B47. Es la grieta que más bugs genera porque **cada pantalla reinventa “esto es cancha o pileta o evento” y se equivoca**. Fix: leer `modalidad_reserva` (8) en un token compartido.
2. **G1 (capa de verdad monetaria + banner de conciliación)** — destraba B6/B16/B18/B37/B76/B79/B84/B135. El backend ya tiene la invariante; solo falta dejar de reimplementar el wording en 6 lados. Fix: `useVerdadSireb` + `<AvisoConciliacionSireb/>` (el banner de 245, que ya escribieron bien).
3. **G3 (estado derivado `esta_operativo` + puente post‑guardado)** — destraba B35/B70/B92/B94/B123 y posiblemente el 75% de B62. Es lo que hace que el sistema “funcione en demo pero no en producción”: campos activos que no son reservables en silencio.

G4 (fecha) es **la más barata** (el código ya existe, solo propagar) — hacela cuando quieras un quick win. G5 (códigos) y G6 (auditoría) son transversales y destraban P8/P5/lente c. G7 (contrato 3 capas) es **la inversión de arquitectura** que evita que esto vuelva a pasar con el móvil, pero necesita ver el móvil corriendo.

**Lo que NO es mejora y no te vendo como tal:** no te digo “refactoricemos todo”. Las 7 grietas son **7 decisiones que faltaron**, cada una con un fix acotado (un hook, un token, un componente, un presenter). El resto del dossier (B1‑B142) se arregla **solo** cuando estas 7 están puestas, porque la mayoría son síntomas de una de ellas. Arreglar B50/B87/B106/B115/B132/B135 uno por uno es tratar 6 fiebres de la misma infección (G2); arreglar G2 mata las 6.

---

## Guardado

```text
TURNO META (no es pantalla nueva): (1) rutas literales de lo que falta + (2) síntesis IA
  cruzando capas → 7 grietas de diseño G1-G7. NO tejí, NO cambié código, NO pedí captura
  nueva (usé el árbol de la auditoría que el usuario pegó hace ~6 turnos). Numeración
  continua: G1-G7 son NUEVA categoría (grieta arquitectónica), distinta de B(bug)/
  D(didáctica)/S(sugerencia). Cada G referencia los B/S que unifica.

PARTE 1 — DÓNDE BUSCAR LO QUE FALTA (rutas exactas del árbol, agrupadas por qué necesito):
  Fix3/B62/B69-form/B70/B103 → backend/app/Services/Piscina/PiscinaReservaService.php(284)
  + PiscinaConfirmacionCobroService.php(82) + Models/PiscinaReserva.php(49)+PiscinaReserva
  Detalle.php(68). B135/B138/B141/P7 → Services/ReportesService.php(135)+tests/.../Reportes
  Test.php(571)+web-admin/src/types/reportes.ts(45). P7 vírgen → 📸 pages/reportes/
  ClientesFrecuentes.tsx(178). A1/B48/B61/B128 → Http/Middleware/RoleMiddleware.php(59)+
  web-admin/src/components/app-sidebar.tsx(206)+📸 Funcionarios(881) 2º rol. P9 → migrations/
  2026_09_01_101135_create_asignaciones_funcionario_table.php(22)+Models/AsignacionFuncionario
  .php(26)+📱2º control misma cancha (esta pantalla sola NO cierra P9, es por-funcionario).
  P5 → Controllers/Api/V1/Admin/AsistenciaController.php(56)+Services/MarcarAsistenciaService
  .php(125)+Requests/StoreAsistenciaRequest.php(26)+tests/.../AsistenciaReservaTest.php(315)
  +📸 MisCampos(543) botón asistencia. A4/B84/B134 → Integrations/Recaudaciones/
  RecaudacionesApiClient.php(415)+DTOs/EstadoCobroDTO.php(23)+Services/ConfirmacionCobro
  Service.php(106)+Controllers/Api/V1/WebhookRecaudacionesController.php(97). B13/B31/B76/
  B18 → Requests/StoreSolicitudReservaRequest.php(97)+StorePiscinaReservaRequest.php(32)+
  web-public/.../FormularioSolicitante.tsx(155). B43/B88/B99/B67/B71/B72 → Models/TarifaCampo
  .php(107)+Services/Piscina/PiscinaTarifaResolver.php(77)+web-admin/.../CrearTurnoDialog.tsx
  (249). B94/B123 → web-admin/.../CampoFormDialog.tsx(862)+pages/usuarios/Asignaciones.tsx(579).
  B8/B110 → Services/CampoPublicoSirebPresenter.php(333)+Services/CatalogoSirebService.php(202).
  B84/B86/B111 → Models/Auditoria.php(29)+Services/AuditoriaService.php(48)+web-admin/.../
  DialogDetalleReserva.tsx(838). B22/B17/B21 → web-public/pages/ConsultarEstado.tsx(299)+
  components/comprobante/EstadoReserva.tsx(183). P6 📱 → routes/console.php(9) YA VISTO,
  declarado, NO se cierra leyendo: correr schedule:work/cron (zombies 4y7 días lo prueban).
  E móvil 📱 → mobile/lib/screens/piscina_datos_screen.dart(260)+services/piscina_service.dart
  (145)+screens/campo_detalle_screen.dart(1073)+models/bloque_disponibilidad.dart(85)+models/
  piscina_disponibilidad.dart(87) — NUNCA lo vi corriendo, hipótesis G7, necesito capturas.
  NO CIERRO AUNQUE PASEN ARCHIVO: P6(acción scheduler), P9(2º control), E móvil(ver app
  corriendo), B62(causa puede ser ruido seed UUIDv7 misma tanda, no bug flujo).

PARTE 2 — 7 GRIETAS DE DISEÑO (síntesis IA, lo que NO sale de una pantalla aislada):
  G1 invariante SIREB en back como código pero reimplementada 6x en UI, 4 la rompen (footer
  B6 miente, comprobantes B18/B37 monto_total como PAGADO, éxito B76 "confirmado" en
  pendiente; solo 703/245 la hacen bien). Fix: useVerdadSireb + <AvisoConciliacionSireb/>
  (banner 245 = mejor copy). Mata B6/B16/B18/B37/B76/B79/B84/B135. = S52+S69 unificados.
  G2 (LA MÁS GRANDE) back sabe 3 modelos (ModalidadReservaCampo 8+migr21+3 familias tablas)
  pero UI trata todo como cancha, pileta/evento se cuelan: ocupación B50, paramétricas B87,
  tarifas B106, tipos B115(texto no flag), mapa B132, reportes B135, ventanilla B69. Fix:
  router de modalidad = token compartido que cada componente lee y ramifica. Mata B50/B87/
  B106/B115/B132/B135/B69/B73/B47. = S45+S47+S53+S66+S71 unificados. 1 decisión que faltó
  = 9 bugs.
  G3 "operativo" no es booleano es condición derivada que nadie calcula → gate silencioso:
  campo Activo pero sin tarifas SIREB (147 continue B35/B70/B92) o sin turnos/variantes
  (B94/B123). Fix: esta_operativo derivado + checklist + puente post-guardado (S46/S60/S63).
  Mata B35/B70/B92/B94/B123/B105 y posiblemente 75% B62.
  G4 formatter fecha YA existe (881 relativo "hace 11d" + 582 absoluto funcionan) pero 190/
  164/655 reimplementan mal (ISO crudo/Invalid Date). AUTOCORREGÍ: venía "crear helper",
  evidencia prueba "propagar". Fix: lib/fecha.ts en admin+public (nunca Invalid, fallback
  "—") + cast tz back. Mata B36/B101/B112/B44/B53/B110/B142. = S25+S50+S55+S58. LA MÁS BARATA.
  G5 5 formatos código (RES/RSV/PIS/4-4-3/PTS-fantasma B21) + 4 labels (B83) + éxito tiene
  QR/WhatsApp (wa.me robusto por fallback doc) pero público NO (P8). Fix: CodigoReserva back
  única fuente + <CodigoEtiquetado/> front; bloque éxito (S38) sobre él → P8 cae solo. Mata
  B21/B52/B77/B83/B89/B127 + cierra P8/S16/D18/S38. wa.me aplica SOLO acá.
  G6 auditoría en back (29/48) pero UI la expone a pedazos: 838 cancha sí, pileta no (B86),
  tarifas horarias sí/perfiles no (B111), destructivos sin confirm/rastro (B93/B126/B117/
  B124/B105). Fix: <ConfirmacionConAuditoria/> transversal + 5 bloques 838 también pileta.
  Mata B86/B93/B111/B117/B124/B126/B105 + unifica acierto 838. Conecta P5/lente c.
  G7 (INVERSIÓN ARQUITECTURA, HONESTO: móvil NUNCA lo vi) no hay contrato de presentación
  entre 3 capas: 333 presenter cancha público pero pileta sin análogo limpio (B8), público/
  admin ya divergen en G1-G6, móvil (1073/260/343/104/192 + models 85/87) reimplementa otra
  vez → divergencia x3. Fix: 1 presenter por recurso (extender 333 a pileta/evento) que las
  3 capas consuman. Mata la CLASE entera de paridad + réplicas móviles. Requiere 📱 móvil
  corriendo pa' confirmar (hipótesis fuerte, no afirmación).

RANKING APALANCAMIENTO (arreglás 3, destrabás ~40): 1=G2 router modalidad (9 bugs, la grieta
  que más genera); 2=G1 capa verdad monetaria+banner (8 bugs, back ya tiene invariante);
  3=G3 esta_operativo+puente (6 bugs + quizá 75% B62). G4 = quick win (código ya existe,
  propagar). G5/G6 transversales (P8/P5/lente c). G7 = inversión pa' que no vuelva con móvil.
  NO vendo refactor total: son 7 decisiones que faltaron c/ fix acotado (hook/token/
  componente/presenter), no "reescribamos". B1-B142 son síntomas de una de las 7; arreglar
  G2 mata B50/B87/B106/B115/B132/B135 de una en vez de 6 fiebres de 1 infección.

KB HONESTA TURNO META: wa.me — doc confirma fallback "use WhatsApp Web" sin app → botón
  éxito 961/489 ROBUSTO por construcción, NO bug; aplica SOLO donde hay botón WhatsApp
  (éxito/P8, G5), NO en reportes/mapa/admin general (no inflar como turnos previos). SIREB
  eje G1 (invariante en back como código, 6 reimplementaciones UI). Rolldown circularDependency
  default false + bundlerTimings default true → NO aplican (no buildié, es síntesis). React
  DevTools SÍ aplica al tejer G1-G7 (varias son "qué prop/rama monta el componente").

COLA: sigue igual pero las 7 G reordenan el "qué arreglar primero" por encima de los B
  individuales. Fix3/B62 → 284/82/49/68 (G3). A1/B48/B61/B128 → 59/206 (G2 ramif rol).
  A4/B84/B134 → 415/23/106/97 (G1). D2✅✅ → 107/77/249 (G2/G1). P9 → 22/26+2º control (G3).
  P7 → 135/571/45+178📸 (G1). P5 → 56/125/26/315+543📸 (G6). E móvil → 260/145/1073/85/87📱
  (G7, la única que necesita ver la app). P6 → console.php 9 📱 correr scheduler. DOSSIER
  PÚBLICO CERRADO B1-B43/D1-D28/S1-S24; ADMIN ABIERTO B44-B142/D29-D77/S25-S71 + G1-G7.
  PARAMÉTRICAS+USUARIOS/ROLES/ASIGNACIONES+MAPA GLOBAL+REPORTES(PARCIAL) VISTOS.
PRÓXIMA: o me pasás los archivos de la Parte 1 (empezando por 284+135+59/206, los 3 del
  ranking G2/G1/G3) y arranco a tejer las grietas en commit verde (suite 226+build), o me
  pasás 📸 ClientesFrecuentes 178 + 📱 móvil corriendo y cierro P7/E del todo, o seguimos
  dossier con lo que quede. Tú eliges; no tejo hasta confirmar.
```

En una línea: te di **(1)** las rutas literales de todo lo que falta (tabla agrupada por “qué necesito de vos para cerrar qué”, marcando con 📱 lo que requiere acción tuya —correr el scheduler pa’ P6, crear un 2º control pa’ P9, ver el móvil corriendo pa’ E— y aclarando que esas tres **no** se cierran aunque me pases el archivo), y **(2)** mi síntesis de IA cruzando las capas: **7 grietas de diseño G1‑G7** que no salen de mirar una pantalla sino de tener el mapa completo, cada una con un fix estructural acotado (un hook, un token, un componente, un presenter) que mata N bugs de un golpe — la más grande es **G2**, que el backend sabe que hay 3 modelos de reserva (`ModalidadReservaCampo` 8) pero la UI trata todo como cancha y pileta/evento se cuelan, padre de B50/B87/B106/B115/B132/B135/B69/B73/B47; le siguen **G1** (la invariante SIREB vive en el backend como código pero la UI la reimplementa 6 veces y 4 la rompen → `useVerdadSireb` + el banner de conciliación de 245, que es el mejor copy del dossier), **G3** (“operativo” es condición derivada que nadie calcula → gate silencioso B35/B70/B92/B94/B123), **G4** (el formatter de fecha ya existe en 881/582, el bug es que 190/164/655 reimplementan mal → la más barata), **G5** (5 formatos de código, 4 labels, y el QR/WhatsApp del éxito que el público no tiene → `CodigoEtiquetado` cierra P8), **G6** (auditoría transversal que pileta y los destructivos omiten) y **G7** (no hay contrato de presentación entre las 3 capas, el móvil reimplementa otra vez → la inversión de arquitectura, que requiere ver la app corriendo para confirmar). Te dejé el **ranking de apalancamiento**: arreglás G2+G1+G3 y destrabás ~40 bugs, porque el resto son síntomas de una de ellas; y te aclaré que **no te vendo un refactor total**, son 7 decisiones que faltaron con fix acotado cada una. **No tejí** — decime si me pasás los archivos de la Parte 1 (empezando por `PiscinaReservaService` 284 + `ReportesService` 135 + `RoleMiddleware` 59/`app-sidebar` 206, los 3 del ranking) y arranco a tejer las grietas en commit verde, o me pasás 📸 `ClientesFrecuentes` 178 + 📱 el móvil corriendo y cierro P7/E del todo, o seguimos dossier.
