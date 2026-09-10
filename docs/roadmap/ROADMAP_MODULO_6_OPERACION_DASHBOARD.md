# 📁 ROADMAP_MODULO_6_OPERACION_DASHBOARD.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 10–12 horas (sin cambios) · **Bloquea:** Módulo 7 (seguridad, concurrencia y publicación)

> **Nota de renumeración:** este módulo se llamaba "Módulo 7" en la versión
> anterior del roadmap. Al desaparecer por completo la Épica E de
> contingencia (antes Módulo 6), ese número queda libre y este módulo lo
> toma — no hay ningún "Módulo 6" de contingencia que buscar en ningún otro
> lado, se retiró del roadmap por completo.

> **Objetivo del Módulo:** Implementar la Épica F del Documento 3 v3
> (HU-F1 a HU-F4) — la primera vez que alguien del GAD Beni mira el sistema
> completo "desde arriba". Al cerrar este módulo: el Funcionario de Control
> puede verificar en sitio que quien está en la cancha tiene una reserva
> válida, el Administrador y Gerencia pueden ver el mapa de ocupación
> global, y Gerencia puede consultar ingresos, horas pico y clientes
> frecuentes.

> **Alcance del cambio en este módulo:** ninguna de las cuatro historias de
> esta épica depende del Core de Recaudaciones — todas leen datos que
> Canchas ya tiene por su cuenta (`reservas`, `solicitudes_reserva`). El
> único punto realmente nuevo es una nota explícita, ya adoptada en el
> Documento 3 v3, sobre a quién le corresponde el reporte de ingresos
> consolidados.

---

## 🗺️ Mapa del Módulo

```
Módulo 6
├── Fase 6.1 → Backend: ocupación en tiempo real y verificación de código
├── Fase 6.2 → Backend: mapa global de ocupación
├── Fase 6.3 → Backend: reportes gerenciales (ingresos, horas pico, clientes frecuentes)
├── Fase 6.4 → Panel Web: pantalla de ocupación para Funcionario de Control
├── Fase 6.5 → Panel Web: mapa global y dashboard gerencial
└── Fase 6.6 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 6.1 — Backend: Ocupación en Tiempo Real y Verificación de Código

Implementa HU-F1. El objetivo real detrás de esta historia no es solo "ver
una lista" — es que el Funcionario de Control, parado frente a una cancha,
pueda confirmar en segundos si la persona que tiene enfrente reservó de
verdad.

## Un límite que ya se decidió en el Documento 1 v3, ahora se aplica en código

La regla de negocio original es explícita: un funcionario de control
**solo** ve los campos que tiene asignados. Esto se aplica del lado del
servidor, no solo en la interfaz — de lo contrario, cualquiera con el token
de un funcionario_control podría consultar la ocupación de canchas que no
le corresponden simplemente cambiando un ID en la URL.

---

## Tareas de la Fase 6.1

```
[ ] Crear OcupacionService
    → app/Services/OcupacionService.php
    → misCampos(Funcionario $funcionario, Carbon $fecha): obtiene los
      campos asignados a ese funcionario y, para cada uno, las franjas
      de ese día con su estado, consultando solicitud_reserva_detalle
      (mismo criterio que el DisponibilidadService del Módulo 3, con el
      conjunto de estados activos reducido a 'pendiente'/'confirmada').
      Muestra además el codigo_reserva de las franjas confirmadas. No
      expone nombre_pagador ni telefono_pagador — el propósito es
      verificar que existe una reserva válida, no ver datos de contacto.
    → verificarCodigo(string $codigoReserva, Funcionario $funcionario):
      busca la reserva por código; si el funcionario tiene rol
      funcionario_control, valida además que el campo esté entre sus
      asignados — si no lo está, responde igual que si el código no
      existiera. Un admin_parametricas puede verificar cualquier código
      sin esa restricción.

[ ] Crear OcupacionController
    → GET /api/v1/ocupacion/mis-campos?fecha=YYYY-MM-DD (auth:sanctum,
      accesible para funcionario_control y admin_parametricas).
    → GET /api/v1/ocupacion/verificar/{codigo_reserva} (misma protección).

[ ] Tests
    → Un funcionario_control con 2 campos asignados solo recibe la
      ocupación de esos 2.
    → Verificar un código de una reserva de un campo NO asignado
      responde como "no encontrado".
    → Un admin_parametricas puede verificar cualquier código.
    → La respuesta nunca incluye datos de contacto del solicitante.

[ ] Commit de la fase
    → Mensaje: "feat(ocupacion): ocupación filtrada por asignación y verificación de código de reserva"
```

---

# FASE 6.2 — Backend: Mapa Global de Ocupación

Implementa HU-F4. Exclusiva de `admin_parametricas` y `gerencia` — sin el
filtro de asignación.

---

## Tareas de la Fase 6.2

```
[ ] Extender OcupacionService
    → mapaGlobal(Carbon $fecha): todos los campos activos con un
      resumen de su ocupación del día (franjas libres, ocupadas y
      bloqueadas temporalmente ahora mismo) más sus coordenadas.

[ ] Agregar la ruta
    → GET /api/v1/ocupacion/mapa-global?fecha=YYYY-MM-DD (auth:sanctum +
      role:admin_parametricas,gerencia).

[ ] Tests
    → El resumen de un campo con una reserva confirmada ahora mismo lo
      marca como ocupado.
    → Un funcionario_control no puede acceder a esta ruta (403).

[ ] Commit de la fase
    → Mensaje: "feat(ocupacion): mapa global de ocupación para administración y gerencia"
```

---

# FASE 6.3 — Backend: Reportes Gerenciales

Implementa HU-F2 y HU-F3. Se apoyan directamente en los índices que ya se
crearon en el Módulo 1 (Anexo B del Documento 2 v3) —
`idx_reservas_campo_fecha` para ingresos por campo y fecha, e
`idx_solicitudes_pagador` para clientes frecuentes—. Vale la pena confirmar
con `EXPLAIN ANALYZE` que efectivamente se usan.

## Ingresos: la vista de Canchas, no necesariamente la del Core

`ingresosPorCampo()` suma `reservas.monto_pagado` — un dato que Canchas
calcula y controla por completo, sin depender del Core. Esto responde una
pregunta operativa legítima ("¿cuánto factura cada cancha, cuántas
reservas hubo"), pero **no reemplaza** el panel financiero del Core, que es
quien concilia el dinero efectivamente recibido. Se construye igual, sin
esperar nada del otro equipo, con la nota pendiente ya documentada en el
Documento 1 v3 (punto abierto 5) de si conviene reconciliar ambas vistas
más adelante.

## Clientes frecuentes: agrupar por la clave estable, no por el nombre

```php
$clientesFrecuentes = SolicitudReserva::query()
    ->where('estado', EstadoSolicitudReserva::Confirmada)
    ->whereBetween('creado_en', [$desde, $hasta])
    ->selectRaw('COALESCE(ci_nit_pagador, telefono_pagador) as clave_agrupacion')
    ->selectRaw('COUNT(*) as total_reservas')
    ->selectRaw('SUM(monto_total) as monto_total_gastado')
    ->selectRaw('(ARRAY_AGG(nombre_pagador ORDER BY creado_en DESC))[1] as nombre_mas_reciente')
    ->groupBy('clave_agrupacion')
    ->orderByDesc('total_reservas')
    ->get();
```

---

## Tareas de la Fase 6.3

```
[ ] Crear ReportesService
    → app/Services/ReportesService.php con tres métodos:
        • ingresosPorCampo(Carbon $desde, Carbon $hasta, ?string $campoId):
          suma de reservas.monto_pagado agrupado por campo_id.
        • histogramaHorasPico(Carbon $desde, Carbon $hasta): conteo de
          reservas agrupado por la hora de hora_inicio.
        • clientesFrecuentes(Carbon $desde, Carbon $hasta): tal como se
          muestra arriba.

[ ] Crear ReportesController
    → GET /api/v1/reportes/ingresos?desde=...&hasta=...&campo_id=...
    → GET /api/v1/reportes/horas-pico?desde=...&hasta=...
    → GET /api/v1/reportes/clientes-frecuentes?desde=...&hasta=...
    → Los tres bajo auth:sanctum + role:admin_parametricas,gerencia.

[ ] Verificar el uso de los índices del Módulo 1
    → EXPLAIN ANALYZE sobre cada consulta, confirmando Index Scan sobre
      idx_reservas_campo_fecha e idx_solicitudes_pagador.

[ ] Tests
    → Ingresos por campo suma correctamente solo las reservas dentro
      del rango de fechas solicitado.
    → Dos solicitudes confirmadas con el mismo ci_nit_pagador pero
      nombre_pagador escrito distinto se agrupan como un solo cliente
      frecuente con 2 reservas.
    → Una solicitud en cualquier estado distinto de 'confirmada' no se
      cuenta en ninguno de los tres reportes.

[ ] Commit de la fase
    → Mensaje: "feat(reportes): ingresos, horas pico y clientes frecuentes"
```

---

# FASE 6.4 — Panel Web: Pantalla de Ocupación para Funcionario de Control

## Resolviendo un ítem de sidebar que quedó sin dueño

El layout construido en el Módulo 0.8 incluía un ítem de sidebar
**"Reservas"**, pensado en su momento sin una historia de usuario concreta
detrás. En vez de inventar una pantalla nueva sin respaldo en el backlog,
se resuelve igual que se resolvió "Horarios" en el Módulo 2: el ítem
"Reservas" del sidebar pasa a ser la pantalla de **Ocupación** (HU-F1), que
es exactamente lo que ese enlace estaba anticipando —una vista de qué está
reservado y cuándo—. Se renombra el ítem del menú en esta fase.

Sigue siendo panel web, no una app móvil autenticada nueva — misma decisión
de alcance que ya se tomó antes: el funcionario abre esta pantalla desde el
navegador de su celular en la cancha, diseñada mobile-first, sin ampliar el
alcance del proyecto con un cuarto cliente.

---

## Tareas de la Fase 6.4

```
[ ] Crear los tipos TypeScript y el servicio de API
    → types/ocupacion.ts y services/ocupacionService.ts, consumiendo
      los endpoints de la Fase 6.1.

[ ] Crear la pantalla de ocupación
    → pages/ocupacion/MisCampos.tsx — con componentes de shadcn/ui
      (Card, Table), diseñada mobile-first. Lista los campos asignados
      al funcionario autenticado, con la grilla del día resaltando la
      franja que corresponde a la hora actual.
    → Refresco automático cada 30–60 segundos.

[ ] Crear el buscador de verificación de código
    → Un Input prominente en la misma pantalla: ingresar un código de
      reserva y ver de inmediato si es válido para ese campo y momento.

[ ] Renombrar el ítem de sidebar
    → "Reservas" (Módulo 0.8) pasa a llamarse "Ocupación" y apunta a
      esta pantalla real, visible para funcionario_control y
      admin_parametricas.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): pantalla de ocupación y verificación para funcionario de control"
```

---

# FASE 6.5 — Panel Web: Mapa Global y Dashboard Gerencial

## Elección de librerías: coherencia con lo ya decidido

Para el mapa, se extiende la decisión del **ADR-005** (Módulo 3): Leaflet
con OpenStreetMap vía `react-leaflet`, en vez de introducir una segunda
dependencia de pago solo para el panel web. Para los gráficos, se
recomienda `recharts` — liviana, ampliamente usada con React, cubre los
tres tipos de visualización que pide HU-F2. Igual que con la elección de
mapas, no amerita un ADR propio.

---

## Tareas de la Fase 6.5

```
[ ] Instalar react-leaflet y recharts
    → npm install react-leaflet leaflet recharts (más los tipos de
      TypeScript correspondientes).

[ ] Crear la pantalla de mapa global
    → pages/ocupacion/MapaGlobal.tsx — un marcador por campo activo,
      coloreado según su nivel de ocupación actual, visible para
      admin_parametricas y gerencia.

[ ] (Opcional, mejora de UX) Actualizar el formulario de campos del Módulo 2
    → Reemplazar el par de inputs numéricos de latitud/longitud por un
      selector visual sobre el mismo mapa de Leaflet.

[ ] Crear la pantalla de dashboard gerencial
    → pages/reportes/Dashboard.tsx — selector de rango de fechas,
      gráfico de barras de ingresos por campo, serie temporal de
      ingresos, histograma de horas pico. Incluir una nota visible en
      la UI aclarando que estas cifras son la vista operativa de
      Canchas, no el reporte financiero consolidado del Core.

[ ] Crear la pantalla de clientes frecuentes
    → pages/reportes/ClientesFrecuentes.tsx — tabla ordenada por
      cantidad de reservas, con el nombre más reciente, la clave de
      agrupación usada, y el monto total gastado en el período.

[ ] Habilitar el ítem "Dashboard" del sidebar (Módulo 0.8)
    → Reemplazar el placeholder por la ruta real, visible para
      admin_parametricas y gerencia.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): mapa global y dashboard gerencial de reportes"
```

---

# FASE 6.6 — Smoke Test Final y Commit de Cierre

---

## Checklist de cierre del Módulo 6

```
[ ] Con datos de prueba de módulos anteriores, el dashboard muestra
    ingresos por campo y el histograma de horas pico coherentes con
    esos datos.

[ ] Dos reservas confirmadas con el mismo ci_nit_pagador pero
    nombre_pagador escrito de forma distinta aparecen como un solo
    cliente frecuente.

[ ] Un funcionario_control de prueba, con 2 campos asignados, entra al
    panel desde un navegador de celular y ve únicamente esos 2 campos
    en la pantalla de Ocupación (antes "Reservas" en el sidebar).

[ ] Ese mismo funcionario verifica un código de reserva válido de uno
    de sus campos asignados; al intentar verificar un código de un
    campo que NO tiene asignado, el sistema responde como si no existiera.

[ ] Un admin_parametricas ve el mapa global con todos los campos.

[ ] EXPLAIN ANALYZE sobre las tres consultas de reportes confirma uso
    de los índices del Módulo 1.

[ ] El sidebar ya no tiene ningún ítem apuntando a un placeholder —
    "Ocupación" y "Dashboard" quedaron habilitados en este módulo,
    sumados a "Campos" y "Funcionarios" del Módulo 2.

[ ] Los pipelines de CI de backend y web-admin pasan en verde sobre un
    Pull Request que incluya todo el módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 6 - operación en sitio y dashboard gerencial"
    → Tag sugerido: v0.9.0-operacion-dashboard
```

> **Siguiente módulo:** el Módulo 7 implementa la Épica G —rate limiting
> contra abuso en la app anónima, pruebas de concurrencia formales y
> repetibles (la versión definitiva de las pruebas manuales ya hechas en
> los Módulos 4 y 5), y el checklist de publicación en Google Play Store—.
> Es el módulo de cierre de todo el proyecto.
