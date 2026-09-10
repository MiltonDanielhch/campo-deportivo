### Guion del demo (en este orden)

**A. Web-admin como `admin`/`secret` (5 min)**
1. Login como admin
2. Crear un tipo de campo (ej: "Vóley")
3. Crear un campo nuevo con los 7 horarios de atención
4. Fijar tarifa (explicar el versionado: "la anterior se cierra automáticamente")
5. Crear funcionario tipo `funcionario_control`
6. Asignarle 2 campos (mencionar que el tercero no se marca por regla de negocio)

**B. Logout y login como `control`/`control123` (1 min)**
- Mostrar que solo ve Dashboard y Reservas (menú restringido)
- Intentar acceder a `/api/v1/campos-deportivos` → 403 (explicar el middleware)

**C. App móvil sin login (5 min) — ÉPICA C**
1. Lista de campos (mostrar la Cancha Techada y el otro)
2. Toggle a mapa: marcadores en Trinidad (explicar OSM sin API key)
3. Poner un campo en mantenimiento desde web-admin → refresco de la app → aparece con chip naranja
4. Poner uno en inactivo → desaparece del todo
5. Entrar al detalle de un campo activo
6. Selector horizontal de fechas (Hoy, Mañana, etc.)
7. Grilla con bloques de 1h: libres en verde, ocupada en rojo (si tenés una prueba insertada), bloqueada temporal en naranja
8. Seleccionar bloques libres → barra inferior con total en Bs → botón "Continuar" (explicar que es el punto de entrada al flujo de reserva del próximo módulo)

**D. Mencionar lo que NO se ve pero está ahí**
- Auditoría en cambios de estado (tabla `auditoria` con antes/después)
- Índice único parcial `uq_tarifa_activa` que impide dos tarifas activas a nivel BD (defensa en profundidad)
- Trigger `trg_sync_estado_solicitud` que replica el estado de la cabecera en los detalles (para la restricción `EXCLUDE` anti-doble-reserva)

---

## ❓ 2. Decisiones pendientes que necesitás validar

### 🎯 Tamaño del bloque de alquiler
> "Hoy los alquileres son en bloques fijos de 1 hora. ¿Está bien o se permiten fracciones (30 min, 1h30, etc.)? Si se permiten, ¿con qué granularidad mínima?"

**Impacto:** si son fracciones, tengo que rehacer el `DisponibilidadService` y la lógica del índice `EXCLUDE`. Si son fijos, ya está hecho.

### 🎯 Ventana máxima de reserva anticipada
> "Hoy permito reservar hasta 60 días por adelantado. ¿Está bien o quieren un plazo distinto (30, 90, sin límite)?"

**Impacto:** cambiar el número en `DisponibilidadController` y en la app móvil (mismo valor en ambos lados).

### 🎯 Tiempo de expiración de una solicitud pendiente
> "Cuando alguien toca un bloque libre y empieza el proceso de cobro, ¿cuántos minutos tiene para pagar antes de que se libere la franja? Hoy tengo hardcodeado 15 minutos."

**Impacto:** pasar este valor a la tabla `parametros_sistema` para que sea configurable sin tocar código.

### 🎯 Integración con el Core de Recaudaciones (lo más importante)
> "¿Ya existe un equipo del Core de Recaudaciones con quien coordinar? ¿Tienen documentación de su API? ¿Hay un entorno de pruebas?"

**Impacto:** el Módulo 4 (Épica D) depende 100% de esto. Sin Core no hay pago real. Si no existe aún, el Módulo 4 queda bloqueado.

### 🎯 Campos obligatorios del pagador
> "Hoy pido: nombre, teléfono, CI/NIT. ¿Falta algún dato obligatorio (email, dirección)? ¿El CI/NIT es siempre obligatorio o puede quedar vacío para extranjeros?"

**Impacto:** ajustar el formulario y el modelo `SolicitudReserva`.

### 🎯 Lógica de cancelación
> "Si un ciudadano paga y luego cancela, ¿hay reembolso? ¿Hay política de cancelación (ej: gratis si es con 24h de anticipación)?"

**Impacto:** define si el Módulo 4 necesita un flujo de reembolsos o no.

---

## 🏢 3. Preguntas de negocio que te dan contexto

Estas no tienen impacto inmediato pero te ayudan a entender el sistema:

1. **"¿Cuántos campos deportivos va a administrar el GAD Beni aproximadamente?"**
   - Si son pocos (<50), la arquitectura actual sobra. Si son cientos, hay que pensar en paginación desde ya.

2. **"¿Hay temporadas altas/bajas con tarifas distintas?"**
   - Si sí, el versionado de tarifas ya lo soporta (se cierra la anterior y se abre una nueva). Solo hay que aclarar si se hace manual o automático.

3. **"¿Los campos pueden tener múltiples tarifas según horario (ej: día/noche)?"**
   - Si sí, hay que extender el modelo `TarifaCampo` con un rango horario. Actualmente hay UNA tarifa vigente por campo.

4. **"¿Quién va a ser el usuario final del panel web-admin? ¿Son funcionarios del GAD o hay concesionarios privados?"**
   - Si hay concesionarios, el modelo de `funcionario` + `asignaciones` hay que extenderlo con roles de "concesionario" que solo vean sus campos.

5. **"¿Hay otros sistemas del GAD Beni que necesiten leer estos datos? (estadísticas, transparencia)"**
   - Si sí, conviene planificar endpoints públicos adicionales con agregaciones (no solo campos crudos).

6. **"¿Cómo se manejan los feriados nacionales?"**
   - Si los campos cierran feriados, hay que modelar una tabla `dias_no_laborables` que el `DisponibilidadService` tenga en cuenta.

---

## ⚠️ 4. Cambios que podría pedir (y su costo real)

### Bajo costo (< 2 horas)
- Cambiar textos, colores, logos de la app móvil
- Agregar campos opcionales al formulario (ej: email)
- Ajustar la ventana de reserva (30, 60, 90 días)
- Cambiar el tamaño del bloque (1h → 30min o 2h) — **solo si no se permite fracciones**
- Modificar el tiempo de expiración de solicitudes

### Costo medio (1-2 días)
- Agregar un rol de "concesionario" que solo vea sus campos asignados
- Tarifas por rango horario (mañana/tarde/noche)
- Bloquear feriados nacionales
- Agregar geolocalización del ciudadano en el mapa

### Costo alto (> 1 semana)
- Cambiar de PostgreSQL a MySQL (hay que rehacer el `EXCLUDE`, el trigger, los índices parciales — **casi todo el Módulo 1**)
- Cambiar de `flutter_map`/OSM a Google Maps con Street View
- Agregar integración con un sistema de facturación propio (además del Core de Recaudaciones)
- Hacer la app móvil offline-first (cache completa con sincronización)

---
