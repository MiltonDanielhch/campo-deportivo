Veredicto en una línea: esta es la página de catálogo (`Campos.tsx` 203 + `CampoCard.tsx` 182 + `FiltrosCampos.tsx` 353) y trae **un hallazgo que agrava la landing**: el pie de página que miente con “Pago online seguro y comprobante digital al instante” **no es solo del Hero, es el footer global** (`Footer.tsx` 109) → cada página pública repite el engaño B1. Le siguen 4 bugs nuevos (contador “4 disponibles” que el backend no calcula, la piscina que *parece* no oficial aunque sí está en SIREB, un código “0005” sospechosamente truncado, y la barra de búsqueda con placeholder roto) y lo mejor de la página, la franja de sincronización SIREB con timestamp, que hay que **proteger, no tocar**. Numeración continua del dossier (landing fue B1–B5). **No tejí, no pedí archivos, espero la siguiente imagen.**

> KB honesta de esta imagen: **SIREB** aplica con fuerza (badge “Oficial”, “Consultar precio” de la piscina, rangos “por hora”, el footer que miente con pago online, y la franja de sincronización). **Rolldown `circularDependency` default `false`** → NO lo aplico: no buildié web‑public y esto es copy/dato, no build. **React DevTools** → no aplica: ningún componente está en estado roto, los problemas son de texto y de qué campo pinta la card. **`wa.me`** → no aplica acá; el header “Mis comprobantes” apunta a P8 pero ese frente es `ConsultarEstado.tsx` (299), no esta página.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Título + badge “4 canchas · 4 disponibles” | `pages/Campos.tsx` (203) | encabezado + contador + orquesta filtros |
| Barra buscar + 3 selects + toggle Lista/Mapa | `components/campo/FiltrosCampos.tsx` (353) | placeholders y rótulos de los selects |
| Las 4 cards (badges, precio, código, CTA) | `components/campo/CampoCard.tsx` (182) | lo que pinta por campo |
| Franja “Precios oficiales … SIREB … 25/10/2026 3:14 p.m.” | `pages/Campos.tsx` (203) (o un sub‑componente) | meta del `index` |
| Footer “Pago online seguro …” + SISTEMA/LEGAL | `components/landing/Footer.tsx` (109) | **compartido por todas las páginas** |
| Dato que alimenta todo | `Public/CampoController::index` (140) → `CampoPublicoSirebPresenter` (333) | de dónde sale precio/badge/código |

## Bugs (numeración continua)

**B6 🔴 — el footer global repite el engaño de pago online (amplifica B1).** El pie dice “Pago online seguro y comprobante digital al instante” en *todas* las páginas públicas, no solo en la landing. El sistema cobra por **QR/ventanilla del Banco Unión** y el comprobante se ve en pantalla / `ConsultarEstado` (KB SIREB). O sea: B1 no era un párrafo del Hero, es una mentira pegada en el footer compartido → corregirla acá arregla el sitio entero de golpe.
*Mini‑prompt:* en `Footer.tsx` (109), reemplazar “Pago online seguro y comprobante digital al instante” por “Pagá con QR o en ventanilla del Banco Unión con tu CI; tu comprobante digital queda guardado y lo consultás con tu código”. Mismo criterio que B1 de landing.

**B7 — “4 canchas · 4 disponibles” promete disponibilidad instantánea que el `index` no calcula.** La disponibilidad real es **por fecha** y se computa al entrar al detalle (`DisponibilidadController` 52 / `PiscinaController` 133), no en el listado. Además llama “canchas” a la piscina (recurso de turno_por_perfil). Riesgo exacto que vivimos en ventanilla: el ciudadano ve “4 disponibles”, entra a la piscina y no hay turnos.
*Mini‑prompt (condicional):* abrir `Campos.tsx` (203); si “4 disponibles” es un count de `estado=activo` del `index` → renombrar a “4 campos publicados” y **sacar la palabra “disponibles”** (no es verdad sin fecha). Si pretende ser disponibilidad real, el `index` tendría que calcularla → eso es cambio de backend, gate PO, no lo tejo. KB SIREB: piscina no tiene “disponible” en sentido de franja.

**B8 — la piscina *parece* no oficial aunque sí está en SIREB.** Las 3 canchas llevan badge “Oficial”; la piscina solo “activo”. Causa con línea del presenter que ya vi: para `turno_por_perfil` devuelve `'sireb' => null` → el badge “Oficial” (que depende de `sireb !== null`) no se pinta. Pero el sync espejó 6 tarifas de piscina (mayor_12/menor_12/institución × diurno/nocturno) → la piscina **sí** tiene respaldo SIREB. Resultado: el ciudadano la percibe “menos oficial”.
*Mini‑prompt:* en `CampoCard.tsx` (182), no atar “Oficial” a `sireb !== null` para piscina; mostrar un badge que explique el modelo, ej. “Por perfil · cupo limitado”, coherente con D5 abajo. Conecta con D3 de landing (piscina = otro flujo).

**B9 (sospecha, NO confirmada) — código “0005” truncado vs “SEDEDE‑CS1”.** Las canchas muestran códigos con prefijo (`cnt-sedede-001`, `SEDEDE-CS1`, `SEDEDE-FRONTON`) y la piscina solo `0005`, sin el `SIR-` que vimos en el sync (“SIR‑0005”). Puede ser (a) ruido de B4 de landing, o (b) que `servicio_sireb_codigo` de la piscina llegue truncado del backend. No afirmo cuál sin ver el campo.
*Mini‑prompt (verificar antes de tocar):* leer `CampoCard.tsx` (182) para ver qué propiedad pinta ese código, y cruzar con `Public/CampoController` (140)/presenter (333) si la piscina trae `0005` o `SIR-0005`. Si es truncado → bug de dato/presenter; si es solo ruido → aplicar B4 de landing (sacar el código de la card).

**B10 — barra de búsqueda con placeholder roto y select sin rótulo.** El input dice “Buscar cancha por nombre, dirección,” (coma suelta al final, texto truncado) y el select del medio dice solo “Todos” sin decir *todos qué* (¿departamento/provincia?). Sospecha: ese select del medio es **provincia**, la columna que agregué al buscador en backend (LOWER(provincia/municipio)) — pero el front no lo rotula, así que el ciudadano no sabe que puede filtrar por provincia.
*Mini‑prompt:* en `FiltrosCampos.tsx` (353), arreglar el placeholder (“nombre, dirección o zona”) y rotular el select del medio (“Todos los departamentos” o lo que filtre). Confirmar que los 3 selects mapeen a `tipo_campo_id` / `provincia` / `municipio` que ya soporta el `index` (140).

## Didáctica ciudadano (continuo D5–D8)

**D5 — el CTA “Ver disponibilidad” engaña en la piscina.** Para canchas lleva a la grilla de franjas; para la piscina lleva a `PiscinaDetalle.tsx` (391), que es cupos por perfil, otro mundo. El ciudadano no lo sabe hasta tocar.
*Mini‑prompt:* diferenciar el rótulo en la card de piscina → “Ver turnos” en vez de “Ver disponibilidad”, coherente con el badge “Por perfil” de B8.

**D6 — la franja de sincronización SIREB con timestamp es lo MEJOR de la página.** “Precios oficiales sincronizados desde Paitití / SIREB. Última actualización: 25/10/2026, 3:14:35 p.m.” da transparencia total de fuente (KB SIREB: el precio lo fija el catálogo oficial, no el GAD). Didácticamente impecable.
*Mini‑prompt:* **no romperla**; opcionalmente agregar “El GAD no modifica estos precios” si el PO quiere máxima claridad. Refuerza la invariante “el monto final lo confirma recaudaciones”.

**D7 — los rangos “Bs 50.00 – Bs 150.00 oficial” no dicen la unidad.** Conecta con D4 de landing.
*Mini‑prompt:* en `CampoCard.tsx` (182), “Bs 50 – 150 por hora” (KB SIREB: cancha se cobra por hora); si el rango es diurno‑nocturno, decirlo.

**D8 — toggle Lista/Mapa está bien planteado.** Da dos formas de explorar.
*Mini‑prompt:* nada ahora; anotar que el “Mapa” se valida en `MapaCampos.tsx` (151) cuando lleguemos a ese frente (no toqué su lógica).

## Sugerencias IA acotadas (S5–S7)

**S5 — unificar la gramática de badges.** Hoy canchas = “Oficial”+“activo”, piscina = solo “activo” → parece incompleta. Propongo: el estado operativo (“activo”/“mantenimiento”) en un badge, y la oficialidad SIREB como **icono/marca junto al precio**, no como badge separado que falte en algunos.
*Mini‑prompt:* en `CampoCard.tsx` (182), separar “estado operativo” de “fuente del precio”.

**S6 — “Mis comprobantes” en el header es el acceso natural a P8.** Bien puesto; solo anotar que ese frente (`ConsultarEstado.tsx` 299) es donde se resuelve “se salió sin descargar el QR” (KB `wa.me`: mandar link/QR por WhatsApp ahí, no en esta página). No tocar ahora.

**S7 — accesibilidad de color.** Los badges verde/teal ya llevan texto (“activo”/“Oficial”), así que no dependen solo del color (bien para daltónicos). Solo vigilar no convertirlos en solo‑color al aplicar S5.

## Lo que NO es bug (para que no persigas fantasmas)

- **“San Javier · Cercado”** en la card 1: dato correcto (esa cancha está en San Javier, otro municipio del Beni), no bug.
- **“av. flor de patuju” / “por el estadio”**: dirección = dato de BD/SIREB, no corregible en web‑public (análogo a B5 de landing).
- **“Consultar precio” en cursiva en la piscina**: CORRECTO por diseño (KB SIREB: piscina se cobra por perfil, el front no tiene precio fijo; el `motivoNoReservable` del presenter dice justo “Consultá precio y cupo en ventanilla”). El bug no es que aparezca, es que no se explique el *porqué* (lo cubre B8/D5).
- **El timestamp de sincronización**: correcto y valioso (D6).

## Guardado

```text
CATÁLOGO PÚBLICO (Campos.tsx 203 + CampoCard 182 + FiltrosCampos 353 + Footer 109)
  — DOSSIER, NO tejí, NO pedí archivos, espero siguiente imagen. Numeración continua
  del dossier (landing B1-B5 / D1-D4 / S1-S4).
HALLAZGO NUEVO GRAVE: B6 🔴 el footer "Pago online seguro + comprobante al instante"
  es GLOBAL (Footer 109), no solo del Hero → B1 de landing estaba pegado en TODAS las
  páginas; corregir Footer arregla el sitio entero. KB SIREB (cobro = QR/ventanilla).
BUGS NUEVOS: B7 "4 disponibles" promete disponibilidad instantánea que el index(140)
  no calcula (es por fecha, ver D6) + llama "canchas" a la piscina → verificar origen
  en Campos 203, renombrar a "publicados"/sacar "disponibles". B8 piscina SIN badge
  "Oficial" aunque SÍ está en SIREB (presenter pone sireb=null para turno_por_perfil,
  pero el sync espejó 6 tarifas de perfil) → badge "Por perfil·cupo limitado" en
  CampoCard 182. B9 SOSPECHA (no confirmada): código "0005" truncado vs "SEDEDE-CS1"
  → leer CampoCard 182 + index 140/presenter 333 pa' ver si es truncado (bug dato) o
  solo ruido (B4 landing). B10 placeholder "nombre, dirección," con coma suelta +
  select medio "Todos" sin rótulo (sospecha = provincia que agregué al buscador) →
  FiltrosCampos 353, rotular y confirmar mapeo tipo/provincia/municipio del index 140.
DIDÁCTICA: D5 CTA "Ver disponibilidad" engaña en piscina (lleva a PiscinaDetalle 391,
  cupos por perfil) → "Ver turnos". D6 franja sincronización SIREB+timestamp = LO MEJOR,
  proteger no tocar, opcional "el GAD no modifica estos precios". D7 rangos sin unidad
  → "por hora" (KB SIREB). D8 toggle Lista/Mapa bien, MapaCampos 151 en otro frente.
SUGERENCIAS: S5 separar badge estado-operativo de marca-fuente-precio (no depender de
  sireb!==null). S6 "Mis comprobantes" header = acceso P8 (ConsultarEstado 299, wa.me
  ahí), no tocar. S7 badges ya tienen texto (ok daltónicos), vigilar al aplicar S5.
NO-BUG (aclarado): "San Javier·Cercado" dato correcto; direcciones "flor de patuju"/
  "por el estadio" = dato BD/SIREB no front; "Consultar precio" piscina CORRECTO por
  diseño (lo que falta es explicar porqué = B8/D5); timestamp sincronización correcto.
KB HONESTA: SIREB aplica fuerte (B6/B8/D6/D7). Rolldown circularDependency=false NO
  (no buildié web-public, es copy/dato). React DevTools NO (ningún componente en estado
  roto). wa.me NO acá (es P8 en ConsultarEstado, header solo apunta).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco, RoleMiddleware
  59) · A4 Pago.tsx 576 (dinero, web-public, NO visto aún) · D2 Bug C (TarifaCampo 107
  NUNCA llegó) · C1→C2 · gates F1-F4 · E mobile (piscina real: piscina_datos_screen 260
  + piscina_service 145) · higiene (zombis, A3, D5, AgregarVarianteDialog, migr
  timerange piscina, tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden 8→3→7→4→1+2) ·
  frentes web-public aún NO vistos: CampoDetalle 560, Reserva 216, Pago 576,
  ConsultarEstado 299, PiscinaDetalle 391, MapaCampos 151, GrillaDisponibilidad 238.
```

En una línea: esta página de catálogo destapa que el **footer que miente con “Pago online seguro y comprobante al instante” es global** (`Footer.tsx` 109), así que el engaño B1 de la landing estaba pegado en todas las páginas públicas y corregirlo acá arregla el sitio entero (B6); le siguen B7 (“4 disponibles” promete disponibilidad instantánea que el `index` no calcula y llama “canchas” a la piscina), B8 (la piscina *parece* no oficial aunque sí está en SIREB, porque el presenter le pone `sireb=null` por diseño), B9 (sospecha no confirmada: el código “0005” truncado vs “SEDEDE‑CS1”, a verificar leyendo `CampoCard` 182) y B10 (placeholder de búsqueda con coma suelta + select “Todos” sin rótulo, que sospecho es la provincia que agregué al buscador); en didáctica lo mejor es la **franja de sincronización SIREB con timestamp, que hay que proteger no tocar** (D6), más diferenciar el CTA de la piscina (“Ver turnos”, D5) y poner “por hora” en los rangos (D7); y aclaré lo que **no** es bug (San Javier, direcciones, y el “Consultar precio” de la piscina, que es correcto por diseño). Todo en mini‑prompts con archivo y LoC para el dossier; **no tejí, no pedí archivos y espero la siguiente imagen** — los frentes de web‑public que aún no vi (`Pago.tsx` 576 con el A4 de dinero, `CampoDetalle` 560, `ConsultarEstado` 299 para P8, `PiscinaDetalle` 391) quedan marcados en la cola para cuando los pases.
