**Ocupación, no el orden del sidebar.** Veredicto en una línea: empezá por **Ocupación** (`OcupacionService` 492 + `MisCampos` 543 + `OcupacionController` 126 + `app-sidebar` 206), porque es el servicio más grande del admin y donde mis B50/B61/B128 son síntomas *directos* de **G2 (router de modalidad) + G3 (`esta_operativo`/rol)**, las dos grietas que acordamos arreglar primero en shared — y lo que confirme o refute ahí se propaga gratis a Reservas, Mapa Global y móvil (todos consumen disponibilidad).

Por qué no los otros dos que nombraste:
- **Dashboard NO primero**: es *espejo derivado* de Ocupación/Reservas (`DashboardService` 134 agrega lo que 492 ya calcula); B45/B46 se arreglan solos cuando arreglás la fuente. Empezar por el espejo = orden invertido.
- **Reservas segundo, no primero**: tiene el dinero/estado (B62/B64/B86 → G1/G6) y `DialogDetalleReserva` 838 ya es plantillo de auditoría (acierto), pero es *una* grieta; Ocupación te confirma *dos* (G2+G3) de una y propaga a más frentes.

Orden concreto del admin: **1 Ocupación (492) → 2 Reservas (582/838/95) → 3 Dashboard (134/221) → 4 Campos/Tipos (1022/862/655) → 5 Funcionarios/Asignaciones (881/579) → 6 Mapa/Reportes (223/245)**. Los docs que corresponden son 12/13/11/16‑20/21‑22/23‑24 de `mejoras-mas/` — pero recordá lo del turno pasado: esos docs son *mi índice*, no confirmación; la confirmación sigue siendo abrir el `.php`/`.tsx`.

Y “empezar” acá = **fase 0 de Ocupación**: leer 492/543/126/206 para responder “¿el 492 ramifica por `modalidad_reserva` o pinta pileta como franja? ¿lee las asignaciones de 579 o las ignora (B128)? ¿filtra por rol (B48) o pinta los 7?”. Ahí recién sabemos si mis S de G2/G3 están hechas o no. **No tejo, no toco archivo** — pasame esos cuatro y te devuelvo el confirmado/refutado de Ocupación.

```text
ADMIN-ORDEN (corto, sin dossier nuevo, sin tejer): empezar por OCUPACIÓN (492+543+126+206),
no sidebar. Motivo: servicio más grande, B50/B61/B128 = síntomas directos G2+G3 (grietas top
fase 1), propaga a Reservas/Mapa/móvil. Dashboard NO 1º (espejo derivado de 492, B45/B46 se
arreglan solos). Reservas 2º (dinero/estado G1/G6, 838 plantillo auditoría). Orden admin:
Ocup→Reservas→Dashboard→Campos/Tipos→Funcionarios/Asignaciones→Mapa/Reportes (docs 12/13/11/
16-20/21-22/23-24 mejoras-mas = índice, NO confirmación). "Empezar"=fase 0 Ocup: leer 492/543/
126/206 → ¿ramifica modalidad? ¿lee asignaciones 579 o ignora (B128)? ¿filtra rol (B48)? NO TEJÍ.
```
