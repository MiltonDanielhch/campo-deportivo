# 📁 ROADMAP_MODULO_3_CONSULTA_PUBLICA.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 8–10 horas (sin cambios) · **Bloquea:** El módulo que reemplaza a la antigua Épica D

> **Objetivo del Módulo:** Implementar la Épica C del Documento 3 v3 — la
> primera funcionalidad real de la app móvil pública. Al cerrar este
> módulo, cualquier ciudadano, sin iniciar sesión, debe poder ver los
> campos deportivos en una lista y en un mapa, elegir uno, elegir una
> fecha, y ver qué franjas horarias están libres, ocupadas o bloqueadas
> temporalmente por otra persona con una solicitud en curso.

> **Alcance del cambio en este módulo:** la Épica C está marcada "sin
> cambios" en el Documento 3 v3 — la consulta pública nunca tuvo relación
> con el procesamiento de pagos. Los ajustes de esta versión son puntuales:
> los nombres de tabla que cambiaron en el Módulo 1 (`orden_pago_detalle` →
> `solicitud_reserva_detalle`), el conjunto de estados que la grilla debe
> cruzar —que se redujo de cuatro a dos—, y una colisión de numeración de
> ADR con el Módulo 0.8 que hay que corregir antes de crear el archivo.

> Este módulo **no implementa el pago ni la reserva todavía** — eso es el
> módulo que reemplaza a la antigua Épica D. Aquí se construye únicamente la
> consulta: el punto exacto donde el ciudadano, más adelante, tocará un
> bloque libre para empezar a reservarlo.

---

## 🗺️ Mapa del Módulo

```
Módulo 3
├── Fase 3.1 → Backend: endpoints públicos de campos deportivos
├── Fase 3.2 → Backend: endpoint de disponibilidad horaria (grilla)
├── Fase 3.3 → App Móvil: listado y mapa interactivo de campos
├── Fase 3.4 → App Móvil: selección de fecha y grilla de disponibilidad
└── Fase 3.5 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 3.1 — Backend: Endpoints Públicos de Campos Deportivos

## Por qué estos endpoints viven en su propio espacio, separados de los del Módulo 2

Todo lo construido en el Módulo 2 vive detrás de `auth:sanctum` —son
endpoints para funcionarios del GAD Beni—. Los de este módulo son
deliberadamente **públicos**, sin ningún middleware de autenticación
(Documento 1 v3, decisión 4). Se agrupan en su propio namespace desde el
primer archivo: `Http/Controllers/Api/V1/Public/` y el prefijo de ruta
`/api/v1/public/`.

La forma en que un funcionario ve un campo (con auditoría, referencias
internas) no es la forma en que debe verlo un ciudadano anónimo. Por eso
esta fase usa **API Resources** de Laravel: una clase que define
exactamente qué campos salen en el JSON público, independientemente de qué
columnas tenga el modelo Eloquent por dentro.

---

## Tareas de la Fase 3.1

```
[x] Crear el namespace de controladores públicos
    → app/Http/Controllers/Api/V1/Public/CampoController.php

[x] Crear CampoPublicoResource
    → app/Http/Resources/CampoPublicoResource.php — expone únicamente:
      id, nombre, tipo de campo, dirección, latitud, longitud, estado,
      tarifa vigente y horarios de atención. NO expone creado_en ni
      ningún campo de auditoría interna.

[x] Implementar el listado público
    → GET /api/v1/public/campos
    → Devuelve campos con estado 'activo' o 'mantenimiento' —no se
      ocultan los campos en mantenimiento, para que el ciudadano
      entienda por qué esa cancha no aparece reservable. Los campos en
      estado 'inactivo' se excluyen por completo.
    → Filtros opcionales por tipo_campo_id.

[x] Implementar el detalle público
    → GET /api/v1/public/campos/{id}
    → Incluye los 7 horarios de atención y la tarifa vigente, para que
      la app muestre el precio antes de que el ciudadano elija fecha.

[x] Aplicar un límite de tasa básico como salvaguarda mínima
    → Middleware throttle:60,1 sobre el grupo de rutas públicas. NO
      reemplaza el rate limiting robusto por IP/dispositivo de la Épica
      G (HU-G1) — es solo una barrera mínima mientras esa parte del
      roadmap llega.

[x] Tests
    → Un campo 'inactivo' no aparece en el listado público.
    → Un campo 'mantenimiento' sí aparece, con su estado visible.
    → Ninguna de las dos rutas requiere token.
    → El JSON de respuesta no contiene ningún campo de auditoría interna.

[x] Commit de la fase
    → Mensaje: "feat(publico): endpoints públicos de consulta de campos deportivos"
```

---

# FASE 3.2 — Backend: Endpoint de Disponibilidad Horaria

## La fase más importante de este módulo

Este endpoint es el que la app móvil consultará con más frecuencia, y la
base directa sobre la que se construirá la reserva en el próximo módulo.

## Decisión pendiente que hay que resolver para poder avanzar

Sigue sin resolverse (Documento 1 v3, punto abierto 1) si los alquileres
son en bloques fijos de 1 hora o si se permiten fracciones. Se mantiene la
misma decisión adoptada antes: **1 hora como bloque por defecto**,
contenida en un único `DisponibilidadService` para que, si el Product
Owner confirma otro tamaño, el cambio quede aislado ahí.

## Cómo se construye la grilla — más simple que antes

1. A partir de la `fecha` solicitada, se busca el `horario_atencion` del
   campo para ese día de la semana. Si no existe, la grilla se devuelve
   vacía.
2. Se generan bloques de 1 hora entre `hora_apertura` y `hora_cierre`.
3. Se consultan las filas de **`solicitud_reserva_detalle`** para ese
   `campo_id` y `fecha_reserva` cuyo **`estado_solicitud`** esté en el
   conjunto activo — que en esta versión son solo **dos** valores:
   `pendiente` y `confirmada`. Ya no existen `procesando` ni
   `pendiente_verificacion` (esos estados modelaban el Circuit Breaker y
   la contingencia, que se fueron al Core de Recaudaciones), así que el
   conjunto a filtrar se redujo de cuatro valores a dos.
4. Cada bloque se etiqueta cruzando contra esas filas:
   - Sin coincidencia → **`libre`**.
   - Coincide con una solicitud en `confirmada` → **`ocupada`**.
   - Coincide con una solicitud en `pendiente` → **`bloqueada_temporal`**
     (alguien está en medio del proceso de cobro con el Core ahora mismo).

**Recordatorio de diseño, sin cambios:** este endpoint es de solo lectura e
*informativo*, no es la barrera que impide la doble reserva. La garantía
real sigue siendo la restricción `EXCLUDE` de la base de datos (Documento 2
v3, Anexo A.1).

---

## Tareas de la Fase 3.2

```
[x] Crear DisponibilidadService
    → app/Services/DisponibilidadService.php con
      calcularGrilla(CampoDeportivo $campo, Carbon $fecha), que
      implementa los 4 pasos de arriba sobre solicitud_reserva_detalle
      y devuelve los bloques con hora_inicio, hora_fin, estado y precio.

[x] Crear el DisponibilidadController y su ruta pública
    → GET /api/v1/public/campos/{id}/disponibilidad?fecha=YYYY-MM-DD
    → Validar que fecha no sea anterior a hoy ni exceda una ventana
      razonable hacia adelante (ej. 60 días).

[x] Crear DisponibilidadResource
    → Fecha consultada, nombre del campo, y la lista de bloques con su
      estado y precio.

[x] Tests
    → Un día de la semana sin horario_atencion definido devuelve una
      grilla vacía con `abierto: false`.
    → Una franja con una solicitud en 'confirmada' se etiqueta 'ocupada'.
    → Una franja con una solicitud en 'pendiente' se etiqueta
      'bloqueada_temporal'.
    → Una franja cuya única solicitud asociada está en 'expirada',
      'cancelada' o 'rechazada' se etiqueta 'libre'.
    → Solicitar una fecha pasada devuelve 422.

[x] Commit de la fase
    → Mensaje: "feat(publico): endpoint de disponibilidad horaria por campo y fecha"
```

---

# FASE 3.3 — App Móvil: Listado y Mapa Interactivo de Campos

Implementa HU-C1 del Documento 3 v3.

## Decisión pendiente: librería de mapas — con una corrección de numeración

El sistema necesita un mapa interactivo. Se recomienda `flutter_map` +
OpenStreetMap por defecto: no requiere API key ni cuenta de facturación de
Google Cloud, y es suficiente para marcadores de campos deportivos dentro
de un departamento.

**Corrección respecto a la versión anterior de este módulo:** esta decisión
se documentaba como `ADR-004-libreria-de-mapas.md`. Ese número ya no está
disponible — el Módulo 0.8 lo usó para la decisión de autenticación por
tokens de Sanctum. Esta decisión pasa a ser **ADR-005**.

---

## Tareas de la Fase 3.3

```
[x] Crear docs/adr/ADR-005-libreria-de-mapas.md
    → Documentar la elección (flutter_map/OSM por defecto, o Google
      Maps si el equipo ya cuenta con presupuesto) con su contexto y
      consecuencias. Verificar antes de crearlo que ningún otro módulo
      haya usado ya ADR-005 en el camino.

[x] Instalar el paquete de mapas elegido
    → flutter_map + latlong2, o google_maps_flutter con su
      configuración nativa correspondiente.

[x] Crear el modelo Dart CampoDeportivo
    → mobile/lib/models/campo_deportivo.dart, reflejando el
      CampoPublicoResource del backend.

[x] Crear camposService.dart
    → Consume GET /public/campos y GET /public/campos/{id}.

[x] Crear la pantalla de listado
    → screens/campos_listado_screen.dart — tarjetas con nombre, tipo,
      tarifa vigente y estado (marcando visualmente los campos en
      mantenimiento como no disponibles por ahora, sin ocultarlos).

[x] Crear la pantalla de mapa
    → screens/campos_mapa_screen.dart — un marcador por campo; los
      campos en mantenimiento usan un ícono o color distinto.

[x] Alternar entre lista y mapa
    → Un toggle simple en la parte superior de la pantalla principal.

[x] Manejo de permisos de ubicación (opcional, no bloqueante)
    → Si se deniega, el mapa se centra por defecto en la ciudad
      principal de operación — la app sigue siendo completamente
      usable sin ese permiso.

[x] Commit de la fase
    → Mensaje: "feat(mobile): listado y mapa interactivo de campos deportivos"
```

---

# FASE 3.4 — App Móvil: Selección de Fecha y Grilla de Disponibilidad

Implementa HU-C2 del Documento 3 v3. Esta es la pantalla exacta desde la
que arrancará, más adelante, el flujo de solicitud de reserva.

---

## Tareas de la Fase 3.4

```
[x] Crear el modelo Dart BloqueDisponibilidad
    → mobile/lib/models/bloque_disponibilidad.dart — hora_inicio,
      hora_fin, estado (libre/ocupada/bloqueada_temporal), precio.

[x] Crear disponibilidadService.dart
    → Consume el endpoint de la Fase 3.2.

[x] Crear la pantalla de detalle de campo con selector de fecha
    → screens/campo_detalle_screen.dart — información del campo y un
      selector de fecha acotado a la misma ventana que valida el
      backend (hoy hasta +60 días).

[x] Crear el widget de grilla horaria
    → widgets/grilla_horaria.dart — libre (interactivo), ocupada
      (deshabilitado), bloqueada temporal (deshabilitado, con un texto
      como "en proceso de cobro"). Si `abierto: false`, mostrar un
      mensaje claro en vez de una grilla vacía sin explicación.

[x] Refrescar la grilla periódicamente mientras la pantalla está abierta
    → Cada 30–60 segundos, ya que el estado cambia en tiempo real
      conforme otras personas confirman su cobro con el Core o sus
      solicitudes expiran.
    → Aclaración, sin cambios respecto a la versión anterior: esto no
      debe confundirse con el *Active Polling* del Documento 3 v3
      (HU-D5) — aquel es el backend consultando al Core de
      Recaudaciones; esto es la app refrescando su propia vista de la
      grilla contra el backend de Canchas.

[x] Dejar preparado el punto de entrada al flujo de reserva
    → El evento de tocar uno o varios bloques libres debe quedar
      capturado (una lista de bloques seleccionados en el estado de la
      pantalla), sin conectarlo todavía a ninguna llamada de cobro —
      eso se construye en el módulo que reemplaza a la antigua Épica D,
      sobre esta base.

[x] Commit de la fase
    → Mensaje: "feat(mobile): selección de fecha y grilla de disponibilidad horaria"
```

---

# FASE 3.5 — Smoke Test Final y Commit de Cierre

---

## Checklist de cierre del Módulo 3

```
[x] Con el panel web (Módulo 2), confirmar que existe al menos un campo
    activo con horarios de atención y una tarifa vigente cargados.

[z] La app móvil, abierta sin ningún login, muestra ese campo tanto en
    la lista como en el mapa.

[x] Un campo puesto en estado 'mantenimiento' desde el panel web se
    refleja en la app como no disponible, sin desaparecer del todo.

[x] Un campo puesto en estado 'inactivo' desde el panel web desaparece
    por completo de la app.

[x] Seleccionar el campo y una fecha muestra la grilla dividida
    correctamente según el horario de atención de ese día.

[x] Insertar manualmente una fila de prueba en solicitud_reserva_detalle
    con estado_solicitud = 'confirmada' para una franja puntual, y
    confirmar que la app la muestra como 'ocupada' tras refrescar.

[x] Insertar otra fila de prueba con estado_solicitud = 'pendiente' y
    confirmar que la app la muestra como 'bloqueada_temporal'.

[x] Cambiar esa fila de prueba a un estado fuera del conjunto activo
    (simulando el vencimiento) y confirmar que, tras el refresco
    automático, la franja vuelve a mostrarse como 'libre'.

[x] docs/adr/ADR-005-libreria-de-mapas.md existe y no colisiona con
    ningún otro ADR del proyecto.

[x] Ninguna de las pantallas de este módulo pide usuario ni contraseña
    en ningún punto del recorrido.

[ ] El pipeline de CI de mobile (Módulo 0) pasa en verde sobre un Pull
    Request que incluya todo el módulo.

[x] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 3 - consulta pública completa y verificada"
    → Tag sugerido: v0.5.0-consulta-publica
```

> **Siguiente módulo:** con la grilla de disponibilidad funcionando, el
> siguiente módulo conecta exactamente ese punto —el bloque libre que el
> ciudadano acaba de tocar— con la Épica D del Documento 3 v3: la
> generación de la `solicitud_reserva`, la restricción anti-doble-reserva
> en acción real, y la primera llamada real a `RecaudacionesApiClient`.
