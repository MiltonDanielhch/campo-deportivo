# Documento 2: Estructura de Base de Datos (Diccionario de Datos)

**Sistema de Administración del Alquiler de Campos Deportivos**

**Gobierno Autónomo Departamental del Beni — Secretaría Departamental de Deportes**

*Versión 2 — revisada. Incorpora soporte para reservas multi-franja (campeonatos), restricciones de integridad a nivel de base de datos, tabla de proveedores de pasarela, log de intentos, parámetros configurables y estándar de zona horaria.*

---

## Convenciones generales del modelo

* **Motor**: PostgreSQL 14+.
* **Claves primarias**: Todas las tablas utilizan `uuid` generado mediante `gen_random_uuid()`.
* **Montos monetarios**: Todos los precios y cobros se almacenan en bolivianos (`BOB`) usando el tipo `numeric(12,2)`.
* **Coordenadas**: Representadas con `numeric(10,8)` para latitud y `numeric(11,8)` para longitud (precisión sub-métrica sin requerir PostGIS obligatorio).
* **Zonas horarias**: **Todas** las columnas que registran momentos de eventos usan `timestamptz`, sin excepción. La sesión de base de datos y del backend operan en zona horaria `America/La_Paz`.
* **Trazabilidad sin cuenta**: las entidades públicas (`ordenes_pago`, `reservas`) llevan un código corto y único, independiente del `uuid` interno, para que el ciudadano anónimo pueda consultar su estado.

---

## 1. roles

Define los perfiles de acceso al panel web administrativo.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del rol. |
| nombre | varchar(50), único | No | Nombre del perfil (seed inicial: `admin_parametricas`, `funcionario_control`, `gerencia`). No se restringe a enum para permitir agregar roles futuros (ej. `auditor`) sin migración. |
| descripcion | varchar(200) | Sí | Breve descripción de las atribuciones del rol. |
| permisos | jsonb | Sí | Matriz de capacidades detalladas dentro del perfil. |

---

## 2. funcionarios

Usuarios internos del sistema web administrativo (personal del GAD Beni).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del funcionario. |
| nombre_completo | varchar(200) | No | Nombres y apellidos completos. |
| ci | varchar(20) | No, único | Cédula de identidad. |
| usuario | varchar(50) | No, único | Identificador de inicio de sesión (*login*). |
| password_hash | varchar(255) | No | Contraseña encriptada (Bcrypt / Argon2). |
| rol_id | uuid (FK → roles.id) | No | Perfil de acceso asignado. |
| estado | enum: `activo`, `inactivo` | No | Estado de acceso al sistema. |
| creado_en | timestamptz | No | Fecha y hora de registro en el sistema. |

---

## 3. tipos_campo

Categorización de las disciplinas o tipos de escenarios deportivos (ej. Fútbol 11, Ráquet, Futsal, Piscina Olímpica, Coliseo).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del tipo de campo. |
| nombre | varchar(100) | No, único | Nombre de la categoría deportiva. |
| descripcion | text | Sí | Descripción o detalles técnicos de la disciplina. |
| estado | enum: `activo`, `inactivo` | No | Habilita o inhabilita el tipo de campo en el catálogo. |

---

## 4. campos_deportivos

Catálogo físico de escenarios y canchas deportivas administrados por la Secretaría Departamental de Deportes.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del campo deportivo. |
| tipo_campo_id | uuid (FK → tipos_campo.id) | No | Categoría o disciplina deportiva asociada. |
| codigo | varchar(30) | No, único | Código interno de inventario o sigla institucional. |
| nombre | varchar(150) | No | Nombre público del escenario (Ej. "Cancha N° 1 - Palacio de los Deportes"). |
| direccion | text | No | Dirección física o referencia de ubicación. |
| latitud | numeric(10,8) | No | Coordenada geográfica de latitud para el mapa. |
| longitud | numeric(11,8) | No | Coordenada geográfica de longitud para el mapa. |
| estado | enum: `activo`, `mantenimiento`, `inactivo` | No | `activo`: disponible para reserva. `mantenimiento`: bloqueado temporalmente. |
| creado_en | timestamptz | No | Fecha de alta en el sistema. |

> **Cambio respecto a la v1**: `hora_apertura`/`hora_cierre` se retiraron de esta tabla y pasaron a la nueva tabla `horarios_atencion`, para permitir horarios distintos por día de la semana (ver punto abierto #4 del Documento 1).

---

## 5. horarios_atencion *(nueva)*

Horario de atención de cada campo, con granularidad por día de la semana. Para un campo con el mismo horario todos los días, se insertan 7 filas idénticas (una por día).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del horario. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo al que aplica. |
| dia_semana | smallint | No | Día de la semana (`0`=Domingo … `6`=Sábado). |
| hora_apertura | time | No | Hora de inicio de atención ese día. |
| hora_cierre | time | No | Hora de cierre de atención ese día. |

**Restricción:** `UNIQUE (campo_id, dia_semana)` — un solo horario por campo y día.

---

## 6. tarifas_campo

Historial versionado de precios por hora de alquiler de cada campo. Nunca se edita una tarifa vigente; se cierra su rango de tiempo y se crea un nuevo registro.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la tarifa. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo al que aplica la tarifa. |
| precio_por_hora | numeric(12,2) | No | Costo del alquiler por hora en bolivianos (BOB). |
| vigente_desde | timestamptz | No | Fecha/hora desde la cual aplica la tarifa. |
| vigente_hasta | timestamptz | Sí | Fecha/hora de fin de vigencia. `NULL` = Tarifa activa actualmente. |
| creado_por | uuid (FK → funcionarios.id) | No | Funcionario de paramétricas que fijó la tarifa. |

---

## 7. asignaciones_funcionario

Matriz de vinculación entre un Funcionario de Control y las canchas específicas que debe supervisar en sitio.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la asignación. |
| funcionario_id | uuid (FK → funcionarios.id) | No | Funcionario con rol `funcionario_control`. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo que tiene asignado para supervisar. |
| asignado_en | timestamptz | No | Fecha y hora en que se otorgó la asignación. |

---

## 8. ordenes_pago

**Cabecera** de la entidad transaccional temporal. Representa un único cobro/pago (un único QR), que puede cubrir una o varias franjas horarias (ver tabla `orden_pago_detalle`). Bloquea provisionalmente todos los horarios incluidos durante la ventana de pago.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador interno de la orden. |
| codigo_orden | varchar(40) | No, único | Código público único asignado a la orden, enviado al QR y usado por el ciudadano para consultar su estado. |
| monto_total | numeric(12,2) | No | Suma de las tarifas de todas las franjas incluidas en la orden. |
| nombre_pagador | varchar(150) | No | Nombre completo o razón social (asociación/club) ingresado por el ciudadano. |
| telefono_pagador | varchar(20) | No | Teléfono de contacto — obligatorio, es el único canal para reenviar comprobantes o notificar resultados de contingencia. |
| ci_nit_pagador | varchar(20) | Sí | CI o NIT, opcional. Mejora la precisión del reporte de clientes/asociaciones frecuentes frente a agrupar solo por nombre. |
| estado | enum: `pendiente`, `procesando`, `confirmada`, `expirada`, `pendiente_verificacion`, `rechazada`, `cancelada` | No | Estado del ciclo de vida transaccional (ver máquina de estados del Documento 1). |
| creado_en | timestamptz | No | Momento exacto de generación de la orden. |
| expira_en | timestamptz | No | Momento exacto en que expira la orden si no se confirma el pago (`creado_en + parámetro configurable`, ver `parametros_sistema`). |

---

## 9. orden_pago_detalle *(nueva)*

Cada fila representa **una franja horaria específica** (campo + fecha + hora) incluida dentro de una orden de pago. Resuelve el caso de reservas multi-día/multi-franja bajo un solo cobro (ej. campeonatos).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la franja solicitada. |
| orden_pago_id | uuid (FK → ordenes_pago.id) | No | Orden de pago a la que pertenece esta franja. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo solicitado para esta franja. |
| fecha_reserva | date | No | Fecha en la que se utilizará el campo. |
| hora_inicio | time | No | Hora de inicio del bloque. |
| hora_fin | time | No | Hora de finalización del bloque. |
| tarifa_aplicada | numeric(12,2) | No | Precio congelado de esta franja específica, según la tarifa vigente al emitir la orden. |
| rango_horario | tstzrange, `GENERATED ALWAYS AS (tstzrange((fecha_reserva + hora_inicio) AT TIME ZONE 'America/La_Paz', (fecha_reserva + hora_fin) AT TIME ZONE 'America/La_Paz')) STORED` | No | Rango de tiempo calculado automáticamente, usado por la restricción de no-solape (ver Anexo A). |
| estado_orden | enum (igual al de `ordenes_pago.estado`) | No | **Denormalizado** desde `ordenes_pago.estado`, mantenido por trigger. Necesario porque una restricción `EXCLUDE`/índice parcial no puede evaluar una condición de otra tabla — ver Anexo A. |

---

## 10. reservas

Registro **definitivo** del alquiler de una franja. Existe una fila por cada franja de `orden_pago_detalle` cuya orden fue confirmada.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la reserva confirmada. |
| orden_pago_id | uuid (FK → ordenes_pago.id) | No | Orden de pago que originó esta reserva (una orden puede tener varias reservas). |
| orden_pago_detalle_id | uuid (FK → orden_pago_detalle.id) | No, único | Franja específica que fue confirmada (relación 1 a 1 con el detalle de origen). |
| codigo_reserva | varchar(20) | No, único | Código alfanumérico corto impreso en el comprobante digital del ciudadano. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo alquilado (denormalizado para consultas rápidas). |
| fecha_reserva | date | No | Fecha del alquiler (denormalizado). |
| hora_inicio | time | No | Hora de inicio del uso (denormalizado). |
| hora_fin | time | No | Hora de fin del uso (denormalizado). |
| monto_pagado | numeric(12,2) | No | Monto correspondiente a esta franja (igual a `tarifa_aplicada` del detalle de origen). |
| confirmado_en | timestamptz | No | Momento exacto de la recepción de la confirmación de pago. |

---

## 11. proveedores_pasarela *(nueva, reemplaza el enum)*

Catálogo de entidades financieras habilitadas para procesar cobros QR. Reemplaza el enum cerrado `SINTESIS/BANCO_UNION/OTRO` de la v1 para no requerir una migración cada vez que se integra un nuevo proveedor — consistente con el patrón *Strategy* ya definido en la arquitectura.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del proveedor. |
| codigo | varchar(30) | No, único | Código corto interno (Ej. `SINTESIS`, `BANCO_UNION`). |
| nombre | varchar(100) | No | Nombre comercial del proveedor. |
| orden_prioridad | smallint | No | Orden en que el Circuit Breaker intenta los proveedores (1 = principal). |
| activo | boolean | No | Si está `false`, el sistema nunca lo intenta (ej. mientras se gestiona un contrato). |

---

## 12. pagos_confirmados

Información técnica y de auditoría provista por la pasarela bancaria externa cuando el pago QR resulta exitoso. Relación 1 a 1 con `ordenes_pago` (el pago cubre el monto total de la orden completa, aunque esta tenga varias franjas).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| orden_pago_id | uuid (PK, FK → ordenes_pago.id) | No | Orden de pago que fue procesada. |
| proveedor_pasarela_id | uuid (FK → proveedores_pasarela.id) | No | Entidad que procesó la transacción. |
| transaccion_externa_id | varchar(100) | No | ID de transacción o número de autorización devuelto por el banco. |
| medio_pago | varchar(30) | No | Canal utilizado (Ej. `QR_SIMPLE`, `TRANSFERENCIA_DIRECTA`). |
| estado_pasarela | varchar(50) | Sí | Estado crudo devuelto en el payload por la pasarela. |
| monto_confirmado | numeric(12,2) | No | Monto que la pasarela confirma haber recibido — se compara contra `ordenes_pago.monto_total` para detectar discrepancias en la conciliación. |
| payload_respuesta | jsonb | Sí | Estructura JSON completa con la respuesta del webhook/callback para auditoría. |
| fecha_transaccion | timestamptz | No | Timestamp de confirmación reportado por la pasarela. |

**Restricción crítica:** `UNIQUE (proveedor_pasarela_id, transaccion_externa_id)` — evita que un webhook duplicado (comportamiento normal de las pasarelas cuando no reciben confirmación de recepción a tiempo) procese la misma transacción dos veces.

---

## 13. pagos_contingencia

Registro transaccional exclusivo para el **Nivel 3 de Contingencia** (activado ante caídas totales comprobadas de las pasarelas QR).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la solicitud de contingencia. |
| orden_pago_id | uuid (FK → ordenes_pago.id) | No, único | Orden de pago asociada. |
| comprobante_url | varchar(300) | No | Enlace a la imagen o PDF del comprobante de transferencia bancaria subido. |
| monto_declarado | numeric(12,2) | No | Monto que el ciudadano declara haber transferido, según el comprobante — se contrasta contra `ordenes_pago.monto_total` y el extracto bancario oficial. |
| estado_revision | enum: `pendiente`, `aprobado`, `rechazado` | No | Estado de evaluación por parte de la administración. |
| revisado_por | uuid (FK → funcionarios.id) | Sí | Funcionario administrativo que validó el extracto bancario. |
| revisado_en | timestamptz | Sí | Momento de aprobación o rechazo manual. |
| nota_observacion | text | Sí | Justificación o motivo en caso de rechazo del comprobante. |

---

## 14. intentos_pasarela *(nueva)*

Log histórico de cada intento de cobro contra un proveedor, necesario para auditar el comportamiento del Circuit Breaker y alimentar el monitoreo/alertas al equipo de TI (documento de inicio, sección 7.4).

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del intento. |
| orden_pago_id | uuid (FK → ordenes_pago.id) | No | Orden para la cual se intentó el cobro. |
| proveedor_pasarela_id | uuid (FK → proveedores_pasarela.id) | No | Proveedor contra el que se intentó. |
| resultado | enum: `exitoso`, `timeout`, `error`, `circuito_abierto` | No | Resultado del intento. `circuito_abierto` indica que ni siquiera se llegó a invocar al proveedor porque el Circuit Breaker ya lo tenía marcado como caído. |
| latencia_ms | integer | Sí | Tiempo de respuesta, útil para detectar degradación antes de una caída total. |
| detalle | jsonb | Sí | Respuesta cruda o mensaje de error, para diagnóstico. |
| creado_en | timestamptz | No | Momento del intento. |

---

## 15. parametros_sistema *(nueva)*

Configuración operativa editable sin necesidad de despliegue de código. Cada cambio queda registrado en `auditoria`.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| clave | varchar(100) (PK) | No | Identificador del parámetro (Ej. `orden_pago_expiracion_minutos`). |
| valor | varchar(255) | No | Valor actual, interpretado según el tipo esperado por el backend. |
| descripcion | text | Sí | Explicación del efecto del parámetro. |
| actualizado_por | uuid (FK → funcionarios.id) | Sí | Último funcionario que lo modificó. |
| actualizado_en | timestamptz | No | Fecha del último cambio. |

**Valores semilla sugeridos:**

| clave | valor | descripción |
| --- | --- | --- |
| `orden_pago_expiracion_minutos` | `15` | Minutos de vigencia de una orden de pago antes de expirar. |
| `contingencia_plazo_horas` | `2` | Horas máximas que una orden puede permanecer en `pendiente_verificacion`. |
| `circuit_breaker_umbral_fallos` | `3` | Fallos consecutivos que abren el circuito hacia un proveedor. |
| `circuit_breaker_cooldown_segundos` | `60` | Tiempo de espera antes de reintentar un proveedor con el circuito abierto. |

---

## 16. auditoria

Bitácora transversal para registrar operaciones críticas, cambios de configuración, aprobaciones manuales y modificaciones de usuarios.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del evento de auditoría. |
| tabla | varchar(50) | No | Nombre de la tabla afectada (Ej. `tarifas_campo`, `pagos_contingencia`, `parametros_sistema`). |
| registro_id | varchar(100) | No | Identificador del registro modificado (`uuid` o, en el caso de `parametros_sistema`, la `clave`). |
| accion | varchar(50) | No | Tipo de operación ejecutada (Ej. `crear`, `actualizar`, `inhabilitar`, `aprobar_contingencia`, `rechazar_contingencia`). Se deja como texto libre validado en backend, no como enum cerrado, para no requerir migración al agregar nuevas acciones. |
| usuario_id | uuid (FK → funcionarios.id) | Sí | Funcionario autor de la acción (`NULL` si fue ejecutada por un proceso/job automático). |
| datos_anteriores | jsonb | Sí | Snapshot JSON de los datos previos a la modificación. |
| datos_nuevos | jsonb | Sí | Snapshot JSON de los datos resultantes de la modificación. |
| fecha | timestamptz | No | Momento exacto en que se registró el evento. |

---

## Esquema gráfico de relaciones

```
tipos_campo 1───────N campos_deportivos 1───────N tarifas_campo
                          │            │
                          │ 1          │ 1
                          │            │
                          │ N          │ N
                asignaciones_funcionario   horarios_atencion
                          │ N
                          │
                          │ 1
   roles 1───────N funcionarios

campos_deportivos 1───────N orden_pago_detalle N───────1 ordenes_pago
                                    │                        │
                                    │ 1                      ├─ 1───N intentos_pasarela ─N───1 proveedores_pasarela
                                    │ 0/1                     │
                                 reservas                     ├─ 1───0/1 pagos_confirmados ─N───1 proveedores_pasarela
                                                               │
                                                               └─ 1───0/1 pagos_contingencia

parametros_sistema (independiente, consultada por el motor transaccional)
```

---

## Anexo A — Restricciones críticas de integridad (DDL)

Estas restricciones son necesarias porque el lock a nivel de aplicación, por sí solo, no es una barrera suficiente ante escrituras concurrentes reales.

**A.1 — Evitar doble reserva del mismo horario**

Como `estado` vive en `ordenes_pago` pero la franja vive en `orden_pago_detalle`, la restricción `EXCLUDE` necesita el estado disponible en la misma fila — de ahí la columna denormalizada `estado_orden`, sincronizada por trigger:

```sql
-- Trigger que mantiene sincronizado orden_pago_detalle.estado_orden
CREATE OR REPLACE FUNCTION sync_estado_orden() RETURNS trigger AS $$
BEGIN
  UPDATE orden_pago_detalle
     SET estado_orden = NEW.estado
   WHERE orden_pago_id = NEW.id;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_estado_orden
AFTER UPDATE OF estado ON ordenes_pago
FOR EACH ROW EXECUTE FUNCTION sync_estado_orden();

-- Restricción de no-solape: ningún campo puede tener dos franjas activas que se crucen en el tiempo
ALTER TABLE orden_pago_detalle
  ADD CONSTRAINT no_solape_horario
  EXCLUDE USING gist (
    campo_id WITH =,
    rango_horario WITH &&
  )
  WHERE (estado_orden IN ('pendiente','procesando','confirmada','pendiente_verificacion'));
```

**A.2 — Evitar doble procesamiento de un mismo pago (webhook duplicado)**

```sql
ALTER TABLE pagos_confirmados
  ADD CONSTRAINT uq_transaccion_externa
  UNIQUE (proveedor_pasarela_id, transaccion_externa_id);
```

El endpoint del webhook debe ser idempotente: si la transacción ya existe, responder `200 OK` sin reprocesar.

---

## Anexo B — Índices recomendados de rendimiento

```sql
-- Job de expiración de órdenes (se ejecuta cada pocos segundos/minutos)
CREATE INDEX idx_ordenes_pendientes_expiracion
  ON ordenes_pago (estado, expira_en)
  WHERE estado IN ('pendiente', 'procesando');

-- Pantalla de ocupación en tiempo real y reportes por fecha/campo
CREATE INDEX idx_reservas_campo_fecha
  ON reservas (campo_id, fecha_reserva);

-- Grilla de disponibilidad pública (consulta más frecuente de la app móvil)
CREATE INDEX idx_detalle_campo_fecha
  ON orden_pago_detalle (campo_id, fecha_reserva)
  WHERE estado_orden IN ('pendiente','procesando','confirmada','pendiente_verificacion');

-- Mapa interactivo
CREATE INDEX idx_campos_lat_lng
  ON campos_deportivos (latitud, longitud);

-- Reporte de ocupación filtrado por funcionario
CREATE INDEX idx_asignaciones_funcionario
  ON asignaciones_funcionario (funcionario_id);

-- Reporte de clientes/asociaciones frecuentes
CREATE INDEX idx_ordenes_pagador
  ON ordenes_pago (ci_nit_pagador, telefono_pagador);

-- Log de intentos, para el dashboard de monitoreo del Circuit Breaker
CREATE INDEX idx_intentos_pasarela_proveedor_fecha
  ON intentos_pasarela (proveedor_pasarela_id, creado_en);
```
