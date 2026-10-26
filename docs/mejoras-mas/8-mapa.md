Veredicto en una línea: este es el mismo catálogo (`Campos.tsx` 203 + `FiltrosCampos.tsx` 353) con el toggle **Mapa** activo, o sea `MapaCampos.tsx` (151), y **no es un frente nuevo de bugs: hereda casi todo de la vista Lista** — el badge “4 disponibles” (B7), el placeholder con coma suelta y el select “Todos” sin rótulo (B10) y el footer mentiroso (B6, octava vez) están **idénticos acá**, así que esos fixes son **uno solo en el componente compartido**, no por vista. Lo propio del mapa son 2 sospechas de UX (B24 marcadores encimados sin clustering, B25 pin “abierto” sin popup accionable) y **un dato que me ahorra adivinar**: la atribución “Leaflet | © OpenStreetMap” confirma que es **Leaflet+OSM** (coherente con ADR‑005), no Mapbox/Google. Y a tu pregunta “¿me falta algo de la pública?”: **sí, falta, y lo que falta es lo más riesgoso** — la **piscina ciudadana** entera (`PiscinaDetalle.tsx` 391 + `ComprobantePiscina.tsx` 190 + `services/piscinaService.ts` 22 + `types/piscina.ts` 75), que vengo marcando desde el catálogo (D3/B8/D5) y **nunca vi**. **No tejí, sigo modo dossier.**

> KB honesta de esta imagen: **SIREB** aplica (franja de sincronización con timestamp = D6 a proteger; footer B6 octava vez; y pesa para la piscina que falta, porque el presentador le pone `sireb=null` → B8). **Rolldown `checks.circularDependency` default `false`** → **NO lo aplico**: no buildié web‑public y B24/B25 son UX de marcadores, no un ciclo de imports que el bundler callaría. **React DevTools** → **NO la uso como excusa ahora**: B25 (pin abierto sin popup) *parecería* estado roto de un componente de mapa, pero lo decide leer `MapaCampos.tsx` (151); la extensión **sí** será la herramienta natural *cuando arranquemos* a depurar el popup de Leaflet‑en‑React (inspeccionar el árbol de markers), pero no reemplaza leer el archivo. **`wa.me`** → no aplica (no hay WhatsApp en el mapa).

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC) | Qué contiene |
|---|---|---|
| Toggle Lista/**Mapa** (Mapa activo) | `pages/Campos.tsx` (203) | orquesta ambas vistas; **B26** (comparte B7/B10) |
| Barra buscar + 3 selects (idénticos a Lista) | `components/campo/FiltrosCampos.tsx` (353) | **B10 heredado** (coma suelta + “Todos” sin rótulo) |
| El lienzo Leaflet + 4 pins + zoom ± | `components/campo/MapaCampos.tsx` (151) | **B24/B25/B27** (clustering, popup, zoom) + **D23** (¿respeta filtros?) |
| Badge “4 canchas · 4 disponibles” | `pages/Campos.tsx` (203) | **B7 heredado** |
| Franja “Precios oficiales … SIREB … 5:05:57 p.m.” | `pages/Campos.tsx` (203) | **D6 protegido** (igual que Lista) |
| Footer “Pago online seguro…” | `components/landing/Footer.tsx` (109) | **B6 octava vez** (global) |
| Librería de mapas (decisión tomada) | `docs/adr/ADR-005-libreria-de-mapas.md` (48) | confirma Leaflet (la atribución lo corrobora) |

## Bugs (numeración continua: consultar cerró en B23)

**B24 (sospecha, NO confirmada) — los 4 marcadores se enciman sobre Trinidad; no veo clustering.** Las 4 canchas están geográficamente cerca (Trinidad/Cercado; una en San Javier según la Lista), y a este zoom los 4 pins verdes caen casi en el mismo punto del centro. El ciudadano no puede distinguir cuál es cuál ni “tocar” una sin hacer zoom manual. Leaflet tiene `leaflet.markercluster` (agrupa “4” en un círculo); **no confirmo si está activo o no** sin ver el archivo — puede que el clustering esté y la captura muestre 4 porque a ese zoom ya se separaron, o que no haya. 
*Mini‑prompt (verificar):* leer `MapaCampos.tsx` (151) → ¿importa/usar `leaflet.markercluster` o `react-leaflet-cluster`? Si no, evaluar agregarlo (es plugin, no reescribir, coherente con ADR‑005). Si sí está y aun así se enciman → el `zoom`/`bounds` inicial está mal (B27).

**B25 (sospecha, NO confirmada) — hay un pin “abierto” (gota con anillo blanco) pero no se ve popup con nombre/precio/CTA.** El marcador más abajo parece seleccionado/expandido, mas **no aparece la tarjeta** con “cancha nueva trinidad · Bs 50 · Ver disponibilidad”. Si al tocar un pin no sale una mini‑card accionable, el mapa **muestra dónde pero no permite reservar desde ahí** → el ciudadano vuelve a Lista, y el mapa queda como adorno. No lo afirmo: puede que el popup exista y la captura lo tome cerrado, o que el “anillo” sea solo el estilo de hover. 
*Mini‑prompt (verificar):* leer `MapaCampos.tsx` (151) → ¿el `<Marker>`/`<Popup>` de Leaflet monta contenido (reusando `CampoCard.tsx` 182 o una variante compacta)? Si no, ese es el hueco de D22.

**B26 (no es bug nuevo, es alcance) — B7 y B10 son del componente compartido, no de la vista Lista.** El badge “4 disponibles” (B7) y el placeholder “nombre, dirección,” con coma suelta + el select “Todos” sin rótulo (B10) aparecen **idénticos en Mapa**. Conclusión útil para el PO: esos fixes van **una sola vez** en `Campos.tsx` (203)/`FiltrosCampos.tsx` (353) y arreglan **ambas** vistas; no hay que duplicar el trabajo por toggle. Igual que B6 (footer), que ya es la octava vez.

**B27 (sospecha, NO confirmada) — zoom/bounds inicial: mapa “de la ciudad” vs mapa “de las canchas”.** El encuadre muestra toda Trinidad + aeroparque + Puerto Almácén + universidad, con los 4 pins diminutos en el centro. Si el `zoom` inicial es fijo (o no hace `fitBounds` a los marcadores), el ciudadano ve un mapa urbano en vez de “acá están tus canchas”. Conecta con B24 (si hiciera `fitBounds` al grupo, separaría los pins). 
*Mini‑prompt (verificar):* leer `MapaCampos.tsx` (151) → ¿`center`/`zoom` hardcodeados o `fitBounds(markers)`? ¿reacciona a los filtros (D23)?

**B28 (DATO CONFIRMADO, no bug) — la atribución “Leaflet | © OpenStreetMap” fija la librería.** Esto me ahorra adivinar: el mapa es **Leaflet+OSM**, coherente con ADR‑005 (48 LoC). Implicación práctica: B24 se resuelve con el plugin de clustering de Leaflet (no con reescritura), y B25 con `<Popup>` de `react-leaflet`. **La atribución OSM es obligación de licencia → proteger, no quitarla** al “mejorar” el mapa.

## Didáctica ciudadano (continuo: consultar cerró en D21)

**D22 — el mapa cumple “dónde están” pero, sin popup accionable (B25), no cumple “reservá desde acá”.** La promesa del toggle Lista/Mapa es dos formas de llegar a la misma acción; si el mapa solo ubica y hay que volver a Lista para reservar, su valor didáctico es bajo. El flujo ideal: **tocá pin → mini‑card (nombre+precio+“Ver disponibilidad”) → detalle**. 
*Mini‑prompt:* cerrar B25 (popup con `CampoCard.tsx` 182 compacta) para que el mapa sea accionable, no decorativo. Conecta con D8 del catálogo (toggle bien planteado).

**D23 (verificar) — ¿el mapa respeta los filtros de la barra?** Si el ciudadano elige un municipio en el select “Todos” (B10, sospecha = provincia/municipio) y el mapa sigue mostrando los 4 pins de Trinidad, hay **desconexión filtro↔mapa**: la barra filtra la Lista pero el mapa pinta todo. No confirmo sin ver si `MapaCampos.tsx` (151) recibe los filtros como props/estado. 
*Mini‑prompt (verificar):* leer `MapaCampos.tsx` (151) → ¿filtra `campos` por los selects antes de pintar markers, o pinta el `index` completo? Si no filtra, alinearlo con Lista (mismo estado de filtro en `Campos.tsx` 203).

## Sugerencias IA acotadas (continuo: consultar cerró en S17)

**S18 — o el mapa se vuelve accionable (B25+D22) o se lo marca como vista secundaria.** Con ADR‑005 la decisión de *tener* mapa está tomada (no la cuestiono); la decisión pendiente es *para qué*. Si el PO lo quiere como exploración geográfica, basta con clustering (B24) + fitBounds (B27). Si lo quiere como vía de reserva, necesita popup accionable (B25). Son alcances distintos; elegir uno evita medio‑implementar los dos. 
*Mini‑prompt:* decidir con el PO “mapa = explorar” vs “mapa = reservar”, y tejer solo lo que esa elección pida.

**S19 — color de pin por estado, alineado con S8 (paridad estado→color).** Hoy los 4 pins son **verde idéntico** → una cancha en `mantenimiento` (estado que el backend sí soporta, `CampoDeportivoTest` “cambiar estado a mantenimiento”) se vería igual que una activa. Conecta con B8 (piscina sin badge oficial) y S8 (unificar mapa estado→color en token compartido público/admin/móvil). 
*Mini‑prompt (verificar):* leer `MapaCampos.tsx` (151) → ¿el icono del pin depende de `campo.estado` o está hardcodeado verde? Si hardcodeado, derivarlo del mismo token de S8.

## Lo que NO es bug (para que no persigas fantasmas)

- **Que las canchas estén todas en Trinidad/Cercado y se vean juntas** = dato geográfico real del GAD Beni, no bug; la respuesta de UX es clustering (B24), no “movelas”.
- **La atribución “Leaflet | © OpenStreetMap contributors”** = correcta y **obligatoria por licencia** (B28); protegerla al mejorar el mapa.
- **La franja SIREB con timestamp (5:05:57 p.m.)** = mismo D6 protegido, idéntico a Lista.
- **El toggle Mapa activo resaltado** = acierto D8 del catálogo funcionando; confirma que Lista y Mapa comparten barra/filtros (bien, de ahí B26).
- **El zoom ±** = control estándar de Leaflet, normal.

## Respuesta a “¿me falta algo de la pública?” — SÍ, y lo que falta es lo más riesgoso

Crucé la imagen con el árbol de `web-public/src` y te marco **qué frentes públicos ya cubrió el dossier y cuáles NO**, con LoC, para que decidas si seguimos o cerramos pública acá:

| Estado | Frente web‑public (LoC) | Dónde lo vi |
|---|---|---|
| ✅ visto | Landing (Hero 89, ComoFunciona 70, CamposDestacados 236, BandaCTA 35, InfoInstitucional 108, Footer 109) | turno landing |
| ✅ visto | Catálogo Lista + **Mapa** (Campos 203, FiltrosCampos 353, CampoCard 182, **MapaCampos 151**) | turnos catálogo + **este** |
| ✅ visto | Detalle cancha (CampoDetalle 560, GrillaDisponibilidad 238, SelectorFechas 121) | turno detalle |
| ✅ visto | Paso 2 (Reserva 216, FormularioSolicitante 155, ResumenCarrito 124, StepsReserva 72, carritoStore 32) | turno paso2 |
| ✅ visto | Pago (Pago 576, CuentaRegresiva 81) | turno pago |
| ✅ visto | Comprobante (Comprobante 210, ComprobantePDF 238) | turno comprobante |
| ✅ visto | Consultar estado (ConsultarEstado 299, EstadoReserva 183) | turno consultar |
| ❌ **NO visto** | **Piscina ciudadana**: `PiscinaDetalle.tsx` (391) + `ComprobantePiscina.tsx` (190) + `services/piscinaService.ts` (22) + `types/piscina.ts` (75) | — (vengo marcándolo desde D3/B8/D5) |
| ❌ **NO visto** | `MedioDePago.tsx` (201) — el bloque “¿Estás en el celular?”/métodos que vi en la imagen de Pago pero sin confirmar archivo/lógica (S12/S16 quieren extraerlo) | — |
| ❌ **NO visto** | `BarraCarrito.tsx` (48) — barra flotante de carrito (conecta S11 recalcular, B15 total) | — |
| ❌ **NO visto** | Infra: `router.tsx` (39) (¿existe ruta de piscina/comprobante‑piscina?), `lib/api.ts` (20) (¿traduce 409/422 de D13/B23?), `types/campo.ts` (87) + `types/reserva.ts` (25) (shape del presenter → B8/B21) | — |

**Mi recomendación de orden para lo que falta:** (1) **piscina ciudadana primero** — es el frente de mayor riesgo/valor, 0 validado con el ciudadano, y es donde B8 (piscina “no oficial” por `sireb=null`) y D3/D5 (flujo por turnos/perfiles, no franjas) aterrizan de verdad; (2) luego `MedioDePago.tsx` (201) + `BarraCarrito.tsx` (48), porque son los 2 bloques que S12/S16/S11 quieren **reutilizar/extraer**, así que verlos cierra el “hacer componente compartido”; (3) al final `router.tsx`/`api.ts`/`types` (infra, se ve de paso y confirma si la ruta de piscina siquiera existe). Si preferís cerrar pública acá y pasar a otra capa (admin/móvil/backend), también es válido — pero te dejo claro que **la piscina pública es el hueco más grande**, no un detalle.

## Balance del dossier web‑public (para que veas el panorama antes de decidir)

Llevamos **B1–B28** (con refinamientos de B12/B15/B17/B18), **D1–D23**, **S1–S19**. Lo que más duele, en orden de riesgo de dinero/engaño: **B1/B6/B16** (el footer y la landing prometen “pago online” cuando SIREB cobra por ventanilla/QR — fix global en `Footer.tsx` 109), **B13** (teléfono obligatorio en front vs opcional en back — contrato roto), **B18** (comprobante puede pintar monto esperado como “PAGADO”), **B21** (el help de búsqueda promete un formato `PTS-` que el core no emite), y **B7** (“4 disponibles” sin fecha). El resto es pulido/UX (colores, popups, copy). Ninguno requiere tejer ahora; todos tienen mini‑prompt con archivo y LoC.

## Guardado

```text
MAPA PÚBLICO (MapaCampos.tsx 151, vista Mapa de Campos.tsx 203) — DOSSIER, NO tejí,
  sigo modo "ver todo corregir después". Numeración continua (…consultar B21-B23/
  D19-D21/S16-S17 · mapa B24-B28/D22-D23/S18-S19). CIERE PARCIAL de web-public:
  el mapa NO es frente nuevo de bugs, HEREDA B7/B10/B6 de la vista Lista (componente
  compartido Campos 203/FiltrosCampos 353) → fix uno solo, no por toggle (B26 alcance).
HALLAZGOS PROPIOS DEL MAPA: B24 sospecha (no confirmada) 4 pins encimados sobre
  Trinidad sin clustering visible → leer MapaCampos 151 por leaflet.markercluster;
  B25 sospecha pin "abierto" (gota+anillo) SIN popup accionable (nombre/precio/CTA)
  → mapa decora pero no deja reservar, leer <Marker>/<Popup> + reuso CampoCard 182;
  B27 sospecha zoom/bounds fijo "mapa de la ciudad" vs fitBounds(markers); B28 DATO
  CONFIRMADO atribución "Leaflet|©OpenStreetMap" fija librería (coherente ADR-005 48)
  → B24=plugin Leaflet no reescritura, B25=<Popup> react-leaflet, y la atribución OSM
  es obligación de licencia PROTEGER no quitar al mejorar.
DIDÁCTICA: D22 mapa cumple "dónde" pero sin popup (B25) no cumple "reservá desde acá"
  → flujo ideal tocá pin→mini-card→detalle; D23 verificar (no confirmado) si el mapa
  respeta los filtros de la barra (select "Todos" sin rótulo = B10, sospecha prov/muni)
  → leer si MapaCampos 151 filtra campos antes de pintar markers o pinta index completo.
SUGERENCIAS: S18 decidir con PO "mapa=explorar" (B24+B27) vs "mapa=reservar" (B25),
  no medio-implementar los dos; S19 color de pin por estado (hoy 4 verdes iguales,
  mantenimiento indistinguible) alineado S8 paridad estado→color, verificar hardcode.
NO-BUG: canchas todas en Trinidad=dato geo real (respuesta=clustering no movelas);
  atribución Leaflet/OSM=obligatoria proteger; franja SIREB timestamp=D6 protegido;
  toggle Mapa activo=D8 funcionando (comparte filtros→B26); zoom±=normal.
RESPUESTA "¿FALTA ALGO DE LA PÚBLICA?" — SÍ, y es lo más riesgoso. Crucé imagen con
  árbol web-public/src. VISTOS: landing, catálogo Lista+Mapa, detalle, paso2, pago,
  comprobante, consultar. NO VISTOS (4 grupos): (1) PISCINA CIUDADANA PiscinaDetalle
  391 + ComprobantePiscina 190 + services/piscinaService 22 + types/piscina 75 [hueco
  más grande, 0 validado, donde B8 sireb=null y D3/D5 flujo turnos/perfiles aterrizan];
  (2) MedioDePago 201 (bloque "¿Estás en el celular?"/métodos, S12/S16 quieren extraer);
  (3) BarraCarrito 48 (S11 recalcular, B15 total); (4) infra router.tsx 39 (¿ruta de
  piscina existe?), lib/api.ts 20 (¿traduce 409/422 D13/B23?), types/campo 87 +
  types/reserva 25 (shape presenter→B8/B21). ORDEN RECOMENDADO: piscina→MedioDePago+
  BarraCarrito→infra. Alternativa válida: cerrar pública acá y pasar a otra capa, pero
  piscina pública es hueco no detalle.
BALANCE DOSSIER web-public: B1-B28 (+refinamientos B12/B15/B17/B18), D1-D23, S1-S19.
  Mayores riesgos dinero/engaño: B1/B6/B16 (footer+landing "pago online" vs SIREB
  ventanilla/QR, fix global Footer 109), B13 (teléfono obligatorio front vs opcional
  back, contrato roto), B18 (comprobante monto esperado como "PAGADO"), B21 (help
  promete formato PTS- que core no emite), B7 ("4 disponibles" sin fecha). Resto=pulido.
KB HONESTA: SIREB aplica (D6 franja timestamp; B6 octava vez; pesa pa' piscina que
  falta por sireb=null=B8). Rolldown circularDependency=false NO (no buildié web-public,
  es UX marcadores). React DevTools NO como excusa (B25 parece estado roto de marker
  pero lo decide leer MapaCampos 151; la extensión SÍ será herramienta natural CUANDO
  arranquemos a depurar popup Leaflet-en-React, no reemplaza el archivo). wa.me NO aplica.
ESPERANDO: instrucción — seguir con piscina ciudadana (PiscinaDetalle 391 etc.) o
  cerrar pública y pasar a otra capa. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59; S13 link SIREB) · D2 Bug C (TarifaCampo 107 NUNCA llegó) ·
  C1→C2 · gates F1-F4 · E mobile (piscina real piscina_datos_screen 260 +
  piscina_service 145; P3-paridad campo_detalle_screen 1073 + bloque_disponibilidad
  85; bloque recuperar QR S16 también móvil) · higiene (zombis, A3, D5, AgregarVariante
  Dialog, migr timerange piscina, tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden
  8→3→7→4→1+2; P3 reencuadrada S8 paridad; P8 reencuadrada S16 = ya hecho en Pago,
  llevar a ConsultarEstado+Comprobante; D11 campo NIT ya prepara P1/P2).
```

En una línea: el mapa **no abre un frente nuevo de bugs, hereda B7/B10/B6 de la vista Lista** porque comparte `Campos.tsx` (203)/`FiltrosCampos.tsx` (353), así que esos fixes son uno solo y arreglan las dos vistas (B26); lo propio son **B24** (4 pins encimados sin clustering, sospecha), **B25** (pin “abierto” sin popup accionable → el mapa ubica pero no deja reservar, sospecha) y **B27** (zoom fijo “de la ciudad”, sospecha), más el **dato confirmado B28**: la atribución “Leaflet | © OpenStreetMap” fija la librería (coherente con ADR‑005), lo que significa que B24 se resuelve con el plugin de clustering de Leaflet y B25 con `<Popup>`, y que la atribución OSM es obligación de licencia a proteger; en didáctica, el mapa cumple “dónde están” pero sin popup no cumple “reservá desde acá” (D22) y hay que verificar si respeta los filtros de la barra (D23). Y a tu “¿me falta algo de la pública?”: **sí — la piscina ciudadana entera** (`PiscinaDetalle.tsx` 391 + `ComprobantePiscina.tsx` 190 + `services/piscinaService.ts` 22 + `types/piscina.ts` 75), que es el hueco más grande y 0 validado, más `MedioDePago.tsx` (201), `BarraCarrito.tsx` (48) y la infra (`router.tsx` 39 / `lib/api.ts` 20 / `types` 87+25); mi recomendación es **piscina primero**, pero si preferís cerrar pública acá y pasar a otra capa también vale — decime si seguimos con piscina o cerramos, sin haber tejido nada.
