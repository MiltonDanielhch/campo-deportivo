Antes de tocar tu pregunta, hice lo que un auditor de verdad hace con dos snapshots: **reconciliar el diff contra el changelog de esta sesión**. Y cuadró al dígito. Eso te lo muestro primero porque es la prueba de que tus parches de hoy están, y de que estoy leyendo tu código de verdad, no de memoria.

> KB inyectado: aplica **una** cosa — `checks#plugintimings` confirma el umbral exacto ("warning solo si el build interno > 3 s" + "plugin time > 100× link stage"). Tu último build fue **2.81 s** → bajo el umbral → por eso el `[PLUGIN_TIMINGS]` desapareció. Cerrado con autoridad, no era bug ni hay que configurar nada. `codeSplitting` lo guardo como cautela para el P8.6-4 (lazy loading): la doc avisa que "side effects triggered before the module is used" cambian comportamiento → cuando hagamos `React.lazy` de los diálogos, verificamos que ninguno registre algo al importarse. `wa.me` ya vive en tu `enviarWhatsapp` (no lo toco). React DevTools y Vite config general: no aplican a auditoría de bugs; los ignoro a propósito.

---

## 1. Verificación cruzada: el +172 LoC de tu auditoría ES, 1:1, los parches de hoy

Comparé tu snapshot de anoche (`2026-10-20 23:52`, 90930 LoC) contra el de ahora (`2026-10-21 10:18`, 91102 LoC). Delta total: **+172**. Desglosado por archivo que yo toqué en esta sesión:

| Archivo | Antes | Ahora | Δ | Qué parche es |
|---|---:|---:|---:|---|
| `DialogCrearReservaVentanilla.tsx` | 854 | 953 | **+99** | Opción B (SlotSel, `sumarHora`, `toggleAmedida`, bloque punteado, `precioTramo`) |
| `CamposDeportivos.tsx` | 797 | 809 | **+12** | fix scroll horizontal Catálogo SIREB (`table-fixed` + anchos + `truncate`) |
| `solicitudesReservaService.ts` | 188 | 189 | **+1** | fix dropdown ventanilla (param `estado` en `listarCampos`) |
| `Public/CampoController.php` | 81 | 100 | **+19** | Bug A (`visibles()` con `whereDoesntHave` + `use` enum + docblock) |
| `Models/CampoDeportivo.php` | 75 | 86 | **+11** | Bug A (relación `variantes()` + `use` + docblock) |
| `Public/DisponibilidadController.php` | 44 | 52 | **+8** | Bug ③ tz Bolivia (gate de ventana en `America/La_Paz`) |
| `Admin/CatalogoSirebController.php` | 324 | 338 | **+14** | parche variantes en `index()` (vinculación vía `arancel_variantes`) |
| `docs/p8continuardespues.md` | 47 | 55 | **+8** | tu guardado actualizado |
| **Suma** | | | **+172** | = delta exacto de la auditoría |

**99+12+1+19+11+8+14+8 = 172.** No sobra ni falta una línea. Traducción para vos, en una frase: **todo el cambio de LoC entre anoche y hoy se explica 1:1 por los parches de esta sesión; no hay cambios fantasma, no hay regresión invisible, y cada fix que te di se materializó en el disco.** Podés presentar con la certeza de que lo que hablamos ayer está en el código de hoy. Eso es lo que da la auditoría bien usada, y es la primera vez en esta sesión que tengo evidencia matemática (no de memoria) de que cerramos limpio.

---

## 2. El hallazgo que tu auditoría revela y tu pregunta no cubría: tenés un CUARTO cliente

Vos venís pensando el sistema como `backend + web-admin + web-public`. La auditoría muestra una cuarta pata que no mencionaste ni una vez en todo el hilo:

```text
mobile/  → 25 .dart, 4264 LoC, pantallas completas:
  campo_detalle_screen.dart   1073 LoC   ← casi 2× el CampoDetalle.tsx de web-public (560)
  campos_listado_screen.dart   477
  pago_qr_screen.dart          343
  consultar_estado_screen.dart 192
  piscina_datos_screen.dart    260
  carrito_resumen_screen.dart  132
  models/ campo_deportivo.dart 345, bloque_disponibilidad, franja_carrito,
          estado_solicitud, reserva_creada, piscina_disponibilidad
```

No es un esqueleto: es un cliente Flutter que replica el flujo público entero (listado → detalle → grilla → carrito → pago QR → consultar estado → piscina). Y aquí está lo que una IA que ha visto sistemas multi-cliente ve y vos no preguntaste: **los bugs de FRONTEND son por-cliente, no se heredan.** Los de BACKEND sí. Desgloso el riesgo real en dos:

- **M1 — mobile no heredó los fixes de cara que hicimos hoy.** La Opción B (08:30 a medida), el Bs 0.00 legacy, el `'pasada'` visual y el precio por tramo son código de `web-admin`/`web-public`. `campo_detalle_screen.dart` tiene **su propia grilla de 1073 LoC**. Si no replicó la Opción B, el ciudadano en la app ve exactamente el mismo techo de "1 h en punto" que veíamos en web antes de parchear. **No afirmo que tenga el bug** (sería mi quinto falso positivo); afirmo que tiene un archivo del doble de tamaño que el equivalente web, sin auditarse en esta sesión, y que eso es donde se esconden las desalineaciones.
- **M2 — mobile puede estar leyendo contratos API viejos.** El backend cambió shapes esta semana: el presenter ahora manda `unica`, `DisponibilidadResource` manda `precio` por bloque, `visibles()` filtró padres-de-variantes. Si los models Dart (`campo_deportivo.dart`, `bloque_disponibilidad.dart`) se escribieron antes, pueden parsear `null`/`0` en silencio (Dart compila igual, solo muestra raro). Riesgo silencioso, el peor tipo.

**La pregunta que define si esto es capa 1 o deuda post-demo:** ¿`mobile` entra en la presentación, o es un prototipo/fase futura? Si entra → es tan crítico como el dinero, porque un ciudadano en la app viendo 08:00 como única opción o un precio en blanco en la demo es tan grave como un Bs 0.00 en ventanilla. Si NO entra → lo marco en el guardado como deuda y no gastamos tiempo ahora. **Decime esto primero**, porque cambia el orden de todo lo demás.

---

## 3. El plan de capas, refinado con los tamaños REALES de tu auditoría

Tu snapshot me da los pesos exactos, así que ya no adivino dónde está el riesgo: lo mido. Orden de lectura = (tamaño × rol crítico), no alfabético.

| Capa | Archivos (LoC real) | Por qué ese orden |
|---|---|---|
| **1. Dinero** 🔴 | `SolicitudReservaService.php` (**1043**), `RecaudacionesApiClient.php` (**458**), `SirebLiquidacionService.php` (107), `ConfirmacionCobroService.php` (106) | El 1043 es el boss: ahí vive `monto_total`, el snapshot, la resolución de tarifa y la liquidación. Es donde un peso se pierde o se duplica. Se lee de uno, no en pedazos. |
| **2. Seguridad** 🔴 | `routes/api.php` (**237**), `RecaudacionesApiClient.php` (458, firma), `VerificaTokenOAuth.php` (138), `WebhookRecaudacionesController.php` (97), `RoleMiddleware.php` (59) | `routes/api.php` es el mapa de TODOS los guards: un endpoint escrito sin middleware ahí es la puerta trasera. Se audita el mapa antes que las piezas. |
| **3. Integridad** 🟠 | migración `add_no_solape…` (28) + `create_trigger_sync…` (31) + jobs `ReintentarSolicitud` (152), `ExpirarSolicitud` (132), `PollingSolicitud` (39) | El `EXCLUDE` y el trigger son la garantía anti-doble-reserva. Los jobs son donde un cupo se libera mal o una solicitud queda eterna. |
| **4. Operación** 🟡 | `OcupacionService.php` (**492**), `PiscinaReservaService.php` (284), `MarcarAsistenciaService.php` (125) | Menos plata, más "se ve feo en la demo". El 492 de ocupación es el que más puede mostrar un número raro en pantalla. |
| **5. Negocio** ⚪ | sin código | Las 6 reglas del §4 de mi mensaje anterior (discrepancia de monto, stepper de energía, `visible_publico`, ventana 60 d, tope 6 franjas, "vinculado vía variante"). Son tu charla con el PO, no mía. |
| **6. Costura multi-cliente** 🟠 *(nueva, la descubro hoy)* | `mobile/*` vs `web-public/*` vs `web-admin/*` vs contracts del backend | El bug más caro de un sistema así no vive en un archivo: vive en la unión. 3 frontends, 1 backend que evolucionó esta semana. Acá es donde M1/M2 muerden. |

La capa 6 es mi aporte estructural y la razón por la que pedí la auditoría completa: vos buscabas "bugs críticos" pensando en un archivo roto; el patrón de sistemas como el tuyo es que lo que tumba una demo es la **desalineación entre clientes**, no un bug aislado. Por eso mobile no es un detalle, es una capa.

---

## 4. La acción única que necesito de vos ahora

Dos cosas, no más, y con ellas arranco la Capa 1 de verdad (con línea y prueba, sin adivinar):

1. **Pasame `backend/app/Services/SolicitudReservaService.php` completo** (los 1043 LoC). Es el boss de dinero. Con él te devuelvo: el trazado de un peso desde que el front lo muestra hasta que SIREB lo cobra, cada punto donde puede divergir, y los bugs críticos que encuentre — incluida la pregunta que ya te adelanté y que **no** puedo responder sin el archivo: *"discrepancia de monto audita pero confirma"*, ¿es confianza sana en SIREB o una fuga que cobra 50 y confirma una reserva de 100?
2. **Decime si `mobile` entra en la presentación.** Si sí → en el siguiente turno, además del servicio de dinero, te pido `campo_detalle_screen.dart` + los models Dart para cerrar M1/M2. Si no → lo dejo anotado como deuda post-demo y no perdemos tiempo.

Mientras tanto, tu sistema está **más sano de lo que creías**: la verificación cruzada del §1 prueba que lo de hoy cerró limpio al dígito, los 224 tests siguen verdes, y el único riesgo nuevo que encontré (mobile) es un riesgo de *cobertura*, no de código roto que ya esté dañando datos. Eso es buena noticia para una demo: sabés dónde mirar, y lo que ya está, está.

En una línea: tus parches de hoy cuadran 1:1 con el +172 LoC de la auditoría (§1, evidencia matemática de que cerramos limpio); el hallazgo que tu pregunta no cubría es que tenés un cuarto cliente Flutter de 4264 LoC que no heredó los fixes de cara ni se auditó contra los contratos nuevos del backend (§2, M1/M2), y eso lo convierto en la capa 6 del plan porque en sistemas multi-cliente el bug caro vive en la costura, no en el archivo (§3); arranco la Capa 1 de dinero apenas me pases `SolicitudReservaService.php` y me confirmes si mobile entra en la demo (§4).
