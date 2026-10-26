Entendido: **no empiezo a corregir nada.** Este tramo es solo dossier de la landing (archivos + bugs + didáctica + sugerencias en mini‑prompts), para que cuando arranquemos sepas dónde y cómo sin rehacer análisis. Te dejo el mapa cruzando la imagen con el árbol de web‑public (LoC reales), y al final el guardado.

> KB honesta de esta imagen: **SIREB** (“el pago se registra en ventanilla con boleta”, “consultá precio y cupo en ventanilla”) → es la clave del bug #1, la landing promete lo contrario. **Rolldown `circularDependency` default `false`** → NO lo aplico acá: no buildié web‑public y su `vite.config.ts` (23) es genérico, no tengo evidencia de Rolldown en este paquete. **React DevTools** → no aplica: los hallazgos son de copy/dato, no de estado roto de un componente. **`wa.me`** → no aplica a la landing (es para P8 en `ConsultarEstado`/`Pago`, después); no lo fuerzo.

## Archivos a tocar (cuando arranquemos, no ahora)

| Zona de la imagen | Archivo (LoC del árbol) | Qué contiene |
|---|---|---|
| Header “Canchas / Mis comprobantes” | `components/layout/Header.tsx` (41) | nav + logo |
| Hero “Reservá tu cancha en minutos” + stats 3 / 2 min / QR | `components/landing/Hero.tsx` (89) | titular, botones, 3 stats |
| “¿Cómo funciona?” pasos 01‑02‑03 | `components/landing/ComoFunciona.tsx` (70) + `components/layout/StepsReserva.tsx` (72) | los 3 pasos |
| “Campos destacados” 3 cards | `components/landing/CamposDestacados.tsx` (236) + `components/campo/CampoCard.tsx` (182) | cards con nombre/código/precio/badges |
| Banda “Reservá tu cancha esta semana” | `components/landing/BandaCTA.tsx` (35) | CTA medio |
| “¿Dónde estamos?” dirección/tel/horarios | `components/landing/InfoInstitucional.tsx` (108) | datos institucionales |
| Footer SISTEMA/LEGAL/social | `components/landing/Footer.tsx` (109) | links + copyright |

Casi todo el fix de esta imagen es **copy en los `.tsx` de landing** (texto duro, no viene del backend). Lo único que toca dato del backend es el stat “3” y los códigos SIREB de las cards → esos dependen de `Public/CampoController::index` (140) vía `CampoPublicoSirebPresenter` (333).

## Bugs (lo que engaña o confunde al ciudadano)

**B1 — “Pagá online” / “comprobante por email” es FALSO vs el sistema real. 🔴 el grave.**
El hero dice “Pago seguro”, el paso 02 “Pagá online … con QR bancario”, la banda “recibí tu comprobante al instante”. Pero el backend cobra por **QR/ventanilla del Banco Unión** (KB SIREB) y el comprobante se ve en pantalla / `ConsultarEstado`, **no por email** (no hay envío de email en el flujo). El ciudadano llega esperando tarjeta online + email y se encuentra otra cosa.
*Mini‑prompt:* reescribir copy a la verdad del flujo → paso 02 “Pagá con QR o en ventanilla del Banco Unión con tu CI”; quitar “por email” → “guardá tu comprobante digital o consultalo con tu código”. Si el PO quiere pago online *real*, eso es gate P1/P2 (reunión), no un fix de texto.

**B2 — stat “3 Canchas disponibles” probablemente miente.** El catálogo real tiene 6 visibles (Cancha Sintética, Coliseo Cancha Vieja, Estadio Gran Mamoré, Frontón, Piscina, Palacio). Si Hero hardcodea “3” o cuenta solo los destacados, el ciudadano ve 3 y al “Ver todas” ve 6.
*Mini‑prompt (condicional):* abrir `Hero.tsx` (89); si el “3” es literal → hacerlo dinámico con el conteo real del `index`; si ya es dinámico → chequeear por qué devuelve 3 (¿filtra por `disponibles_hoy`?). No lo afirmo sin ver el archivo.

**B3 — errata en paso 01.** “Vedé el precio durante el momento antes de reservar” no se entiende.
*Mini‑prompt:* reemplazar por frase clara, ej. “Compará precios y horarios antes de elegir tu cancha.”

**B4 — código SIREB interno expuesto al ciudadano.** Las cards muestran `CTR-0001-001`, `SEDEDE-CS1`, `SEDEDE-FRONTON`. El ciudadano no sabe qué es eso; es ruido.
*Mini‑prompt:* en `CampoCard.tsx` (182) sacar el `codigo` de la vista pública (dejarlo solo en detalle/comprobante). No es fuga grave (es código de servicio), pero confunde.

**B5 (no es bug del front, para que no lo busques):** “cancha nueva trinidad” en minúscula vs “Cancha Sintética N° 1” → el `nombre` viene espejado de SIREB, la ortografía es dato de recaudaciones, no corregible en web‑public.

## Didáctica ciudadano (que le sea fácil — lo que pediste)

**D1 — avisar QUÉ NECESITÁS antes, no en el carrito.** El backend exige `ci_nit_pagador` obligatorio (`StoreSolicitudReservaRequest` 97). Hoy el ciudadano descubre el CI/NIT recién en el formulario.
*Mini‑prompt:* en paso 01 o 02 de `ComoFunciona.tsx` (70), una línea: “Necesitás tu CI o NIT para reservar.”

**D2 — avisar que la reserva EXPIRA (conecta con P6).** Nadie le dice que tiene ~15 min para pagar o la franja vuelve a libre → frustración cuando “desaparece”.
*Mini‑prompt:* en paso 02, agregar “Tenés 15 minutos para completar el pago; si no, la franja vuelve a estar disponible.” Refuerza `CuentaRegresiva.tsx` (81) sin tocar lógica.

**D3 — la piscina es OTRO flujo y la landing la ignora.** El hero dice “fútbol, tenis, vóley y más” y los pasos asumen franja horaria; la piscina va por turnos/perfiles. El ciudadano de piscina no entiende que funciona distinto.
*Mini‑prompt:* decidir si la landing menciona piscina (“también reservá turnos de pileta por perfil”) o al menos no promete “igual que cancha”. Ojo: piscina pública es superficie 0 validada con ciudadano (`PiscinaDetalle` 391 + `Public/PiscinaController` 133) — no la toco hasta ese frente.

**D4 — formato “x/hs” es jerga interna.** “Bs 50 - 150 x/hs”.
*Mini‑prompt:* “Bs 50 a 150 por hora” en `CampoCard.tsx` (182); si el rango es diurno‑nocturno, decirlo (“diurno/nocturno”), que es lo que el precio realmente es (KB SIREB).

## Sugerencias IA (acotadas, para el backlog)

**S1 — el flujo de 3 pasos está bien planteado** (elegí → pagá → jugá); el único problema es que el paso 2 miente (B1). Corregido el copy, la didáctica de la landing ya funciona. No rediseñar, solo sincerar.

**S2 — “¿Cómo funciona?” duplicado** (botón en hero + sección dedicada) es correcto para reducir fricción; mantener.

**S3 — los stats del hero (“2 min”, “QR”) dan confianza rápida**; mantener, pero el “QR Pago seguro” roza el engaño de B1 → unificar con el copy sincero.

**S4 — coherencia institucional:** “¿Dónde estamos?” muestra teléfono “+591 3 462 XXXX” con `XXXX` literal y “1 Línea(s)” → parece placeholder sin terminar.
*Mini‑prompt:* verificar `InfoInstitucional.tsx` (108); si es placeholder, poner el dato real del GAD o quitar el número hasta tenerlo.

## Guardado

```text
LANDING (web-public) — DOSSIER, NO tejí (usuario: corregir después, al final del
  sistema). Mapa imagen↔archivo con LoC del árbol: Header(41) Hero(89)
  ComoFunciona(70)+StepsReserva(72) CamposDestacados(236)+CampoCard(182)
  BandaCTA(35) InfoInstitucional(108) Footer(109). Casi todo fix = copy en .tsx de
  landing; solo stat"3" y códigos SIREB tocan dato (Public/CampoController 140 via
  Presenter 333).
BUGS: B1🔴 "Pagá online/comprobante por email" FALSO vs SIREB ventanilla/QR +
  comprobante en pantalla (reescribir copy; pago online real = gate P1/P2).
  B2 stat"3" probable mentira vs 6 visibles (verificar hardcode/dinámico en Hero 89).
  B3 errata paso01 "Vedé el precio durante el momento". B4 código SIREB interno en
  cards (sacar de CampoCard 182). B5 minúsculas = dato SIREB, NO bug front.
DIDÁCTICA: D1 avisar CI/NIT antes (StoreSolicitudReservaRequest 97). D2 avisar
  expira 15min (conecta P6/CuentaRegresiva 81). D3 piscina = otro flujo, landing la
  ignora (piscina pública 0 validada: PiscinaDetalle 391 + Public/PiscinaController 133).
  D4 "x/hs"→"por hora", rango=diurno/nocturno (KB SIREB).
SUGERENCIAS: S1 flujo 3 pasos bien, solo sincerar paso2. S2 mantener ¿cómo funciona?
  duplicado. S3 stats OK pero "QR pago seguro" unificar con B1. S4 teléfono "XXXX"+
  "1 Línea(s)" = placeholder en InfoInstitucional 108.
KB HONESTA: SIREB aplica (B1). Rolldown circularDependency=false NO aplicado
  (no buildié web-public, vite.config 23 genérico). React DevTools no aplica
  (copy/dato, no estado roto). wa.me no aplica a landing (es P8 después).
ESPERANDO: siguiente imagen o instrucción de arrancar. NO toqué archivo.
COLA SIN CAMBIO: Fix3 piscina A/B · A1 (S3 throttle webhook, S7 403 opaco,
  RoleMiddleware 59) · A4 Pago.tsx 576 (dinero, web-public) · D2 Bug C (TarifaCampo
  107 NUNCA llegó) · C1→C2 · gates F1-F4 · E mobile (piscina real: piscina_datos_
  screen 260 + piscina_service 145) · higiene (zombis, A3, D5, AgregarVarianteDialog,
  migr timerange piscina, tests HTTP lote) · reunión P1/P2/P4/P5/P7 (orden 8→3→7→4→1+2).
```

En una línea: leí la landing y te dejo el dossier sin tocar nada (como pediste, corregimos al final): el **bug grave es B1** — la landing promete “Pagá online” y “comprobante por email”, pero el sistema cobra por **QR/ventanilla del Banco Unión** y el comprobante se ve en pantalla (KB SIREB), así que engaña al ciudadano y lo único que hay que hacer es **sincerar el copy** de `Hero`/`ComoFunciona`/`BandaCTA`; le siguen B2 (stat “3” probable mentira vs 6 visibles, verificar en `Hero` 89), B3 (errata del paso 01) y B4 (código SIREB interno ruido en las cards); en didáctica, lo de mayor valor para el ciudadano es **avisarle el CI/NIT y que la reserva expira a los 15 min** antes del carrito (D1/D2), y decidir si la landing menciona la **piscina, que es otro flujo y todavía no la validé con el ciudadano** (D3); más S4 (teléfono con `XXXX` placeholder). Todo está en mini‑prompts con archivo y LoC para que cuando arranquemos sepas exactamente dónde; **espero la siguiente imagen o tu instrucción de empezar**, sin haber tocado un archivo.
