# Documento 2: Estructura de Base de Datos (Diccionario de Datos)

**Sistema de Administración del Alquiler de Campos Deportivos**

**Gobierno Autónomo Departamental del Beni — Secretaría Departamental de Deportes**

*Versión 3 — revisada para la arquitectura Hub & Spoke. Reemplaza a la
Versión 2. Pasa de 16 a 12 tablas: `proveedores_pasarela`,
`pagos_confirmados`, `pagos_contingencia` e `intentos_pasarela` se eliminan
por completo (esa responsabilidad vive ahora en el Core de Recaudaciones).
`ordenes_pago` y `orden_pago_detalle` se renombran a `solicitudes_reserva` y
`solicitud_reserva_detalle`. El detalle de qué cambió y por qué está en
`analisis-actualizacion-documento2-hub-spoke.md`; este documento incorpora
esos cambios directamente.*

---

## Convenciones generales del modelo

* **Motor**: PostgreSQL 14+.
* **Claves primarias**: Todas las tablas utilizan `uuid` generado mediante
  `gen_random_uuid()`.
* **Aislamiento de datos financieros**: esta base de datos no almacena
  ningún dato financiero de fondo (montos efectivamente conciliados por un
  banco, medios de pago, identificadores de transacción bancaria). El único
  dato monetario que Canchas conserva es el que ella misma calcula
  (`monto_total`, `tarifa_aplicada`) para informarle al Core de
  Recaudaciones cuánto cobrar, y —opcionalmente— el monto que el Core
  confirma haber cobrado, a modo de referencia.
* **Montos monetarios**: Todos los precios y montos se almacenan en
  bolivianos (`BOB`) usando el tipo `numeric(12,2)`.
* **Coordenadas**: `numeric(10,8)` para latitud y `numeric(11,8)` para
  longitud (precisión sub-métrica sin requerir PostGIS obligatorio).
* **Zonas horarias**: Todas las columnas que registran momentos de eventos
  usan `timestamptz`, sin excepción. La sesión de base de datos y del
  backend operan en zona horaria `America/La_Paz`.
* **Trazabilidad sin cuenta**: `solicitudes_reserva` y `reservas` llevan un
  código corto y único, independiente del `uuid` interno, para que el
  ciudadano anónimo pueda consultar su estado.

---

## 1. roles

Define los perfiles de acceso al panel web administrativo.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del rol. |
| nombre | varchar(50), único | No | Nombre del perfil (seed inicial: `admin_parametricas`, `funcionario_control`, `gerencia`). No se restringe a enum para permitir agregar roles futuros sin migración. |
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

Categorización de las disciplinas o tipos de escenarios deportivos.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del tipo de campo. |
| nombre | varchar(100) | No, único | Nombre de la categoría deportiva. |
| descripcion | text | Sí | Descripción o detalles técnicos de la disciplina. |
| estado | enum: `activo`, `inactivo` | No | Habilita o inhabilita el tipo de campo en el catálogo. |

---

## 4. campos_deportivos

Catálogo físico de escenarios y canchas deportivas.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del campo deportivo. |
| tipo_campo_id | uuid (FK → tipos_campo.id) | No | Categoría o disciplina deportiva asociada. |
| codigo | varchar(30) | No, único | Código interno de inventario o sigla institucional. |
| nombre | varchar(150) | No | Nombre público del escenario. |
| direccion | text | No | Dirección física o referencia de ubicación. |
| latitud | numeric(10,8) | No | Coordenada geográfica de latitud. |
| longitud | numeric(11,8) | No | Coordenada geográfica de longitud. |
| estado | enum: `activo`, `mantenimiento`, `inactivo` | No | `activo`: disponible para reserva. `mantenimiento`: bloqueado temporalmente. |
| creado_en | timestamptz | No | Fecha de alta en el sistema. |

---

## 5. horarios_atencion

Horario de atención de cada campo, con granularidad por día de la semana.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del horario. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo al que aplica. |
| dia_semana | smallint | No | Día de la semana (`0`=Domingo … `6`=Sábado). |
| hora_apertura | time | No | Hora de inicio de atención ese día. |
| hora_cierre | time | No | Hora de cierre de atención ese día. |

**Restricción:** `UNIQUE (campo_id, dia_semana)`.

---

## 6. tarifas_campo

Historial versionado de precios por hora de alquiler de cada campo.

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

Matriz de vinculación entre un Funcionario de Control y las canchas que
debe supervisar en sitio.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la asignación. |
| funcionario_id | uuid (FK → funcionarios.id) | No | Funcionario con rol `funcionario_control`. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo que tiene asignado para supervisar. |
| asignado_en | timestamptz | No | Fecha y hora en que se otorgó la asignación. |

---

## 8. solicitudes_reserva

**Cabecera** de la solicitud de reserva. Representa la intención de un
ciudadano de reservar una o varias franjas horarias, y el pedido de cobro
correspondiente hecho al Core de Recaudaciones. No contiene ningún dato
financiero de fondo — solo lo necesario para pedirle el cobro al Core y
relacionar su confirmación con esta solicitud.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador interno de la solicitud. |
| codigo_seguimiento | varchar(40) | No, único | Código público único, usado por el ciudadano para consultar el estado de su solicitud sin necesidad de cuenta. |
| monto_total | numeric(12,2) | No | Suma de las tarifas de todas las franjas incluidas — el monto que Canchas le informa al Core que debe cobrar. |
| nombre_pagador | varchar(150) | No | Nombre completo o razón social ingresado por el ciudadano. |
| telefono_pagador | varchar(20) | No | Teléfono de contacto — obligatorio, único canal para reenviar información o notificar resultados. |
| ci_nit_pagador | varchar(20) | Sí | CI o NIT, opcional. Mejora la agrupación del reporte de clientes frecuentes frente a agrupar solo por nombre. |
| referencia_recaudaciones | varchar(100) | Sí, único | Identificador que el Core de Recaudaciones devuelve al generar el cobro. Nulo hasta que esa llamada tiene éxito. Es la clave con la que Canchas relaciona el webhook de confirmación del Core con esta solicitud. *(Formato exacto pendiente de confirmar con el equipo del Core — ver punto abierto.)* |
| monto_confirmado | numeric(12,2) | Sí | Monto que el Core informa haber cobrado, si su webhook lo incluye. Nulo mientras la solicitud no esté confirmada. Existe solo como referencia de auditoría —Canchas no reconcilia activamente contra este valor, esa es tarea del Core—, pero deja un rastro por si algún día hace falta detectar una discrepancia. |
| estado | enum: `pendiente`, `confirmada`, `expirada`, `cancelada`, `rechazada` | No | Estado del ciclo de vida de la solicitud (ver Documento 1 v3, sección 4). |
| creado_en | timestamptz | No | Momento exacto de generación de la solicitud. |
| expira_en | timestamptz | No | Momento exacto en que expira la solicitud si el Core no confirma el pago a tiempo (`creado_en + parámetro configurable`). |

---

## 9. solicitud_reserva_detalle

Cada fila representa **una franja horaria específica** (campo + fecha +
hora) incluida dentro de una solicitud de reserva. Sostiene el caso de
reservas multi-día/multi-franja (campeonatos) bajo una sola solicitud de
cobro.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la franja solicitada. |
| solicitud_reserva_id | uuid (FK → solicitudes_reserva.id) | No | Solicitud a la que pertenece esta franja. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo solicitado para esta franja. |
| fecha_reserva | date | No | Fecha en la que se utilizará el campo. |
| hora_inicio | time | No | Hora de inicio del bloque. |
| hora_fin | time | No | Hora de finalización del bloque. |
| tarifa_aplicada | numeric(12,2) | No | Precio congelado de esta franja específica, según la tarifa vigente al emitir la solicitud. |
| rango_horario | tstzrange, `GENERATED ALWAYS AS (tstzrange((fecha_reserva + hora_inicio) AT TIME ZONE 'America/La_Paz', (fecha_reserva + hora_fin) AT TIME ZONE 'America/La_Paz')) STORED` | No | Rango de tiempo calculado automáticamente, usado por la restricción de no-solape (Anexo A.1). |
| estado_solicitud | varchar(30) | No | **Denormalizado** desde `solicitudes_reserva.estado`, mantenido por trigger — necesario porque una restricción `EXCLUDE` no puede evaluar una condición de otra tabla (ver Anexo A.1). |

---

## 10. reservas

Registro **definitivo** del alquiler de una franja. Existe una fila por
cada franja de `solicitud_reserva_detalle` cuya solicitud fue confirmada.
Es, deliberadamente, la única tabla de todo el sistema que representa una
verdad completamente independiente de cómo se pagó — solo dice qué campo,
qué horario, y que está confirmado.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único de la reserva confirmada. |
| solicitud_reserva_id | uuid (FK → solicitudes_reserva.id) | No | Solicitud que originó esta reserva (una solicitud puede tener varias reservas). |
| solicitud_reserva_detalle_id | uuid (FK → solicitud_reserva_detalle.id) | No, único | Franja específica que fue confirmada. **Esta restricción única cumple, además de su propósito original, el rol de barrera de idempotencia ante confirmaciones duplicadas del Core — ver Anexo A.2.** |
| codigo_reserva | varchar(20) | No, único | Código alfanumérico corto impreso en el comprobante digital del ciudadano. |
| campo_id | uuid (FK → campos_deportivos.id) | No | Campo deportivo alquilado (denormalizado). |
| fecha_reserva | date | No | Fecha del alquiler (denormalizado). |
| hora_inicio | time | No | Hora de inicio del uso (denormalizado). |
| hora_fin | time | No | Hora de fin del uso (denormalizado). |
| monto_pagado | numeric(12,2) | No | Monto correspondiente a esta franja (igual a `tarifa_aplicada` del detalle de origen). |
| confirmado_en | timestamptz | No | Momento exacto de la recepción de la confirmación de pago. |

---

## 11. parametros_sistema

Configuración operativa editable sin necesidad de despliegue de código.
Cada cambio queda registrado en `auditoria`. La lista de valores sembrados
es deliberadamente corta — ya no existen parámetros de Circuit Breaker ni de
contingencia, porque ninguno de los dos mecanismos vive en Canchas.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| clave | varchar(100) (PK) | No | Identificador del parámetro. |
| valor | varchar(255) | No | Valor actual, interpretado según el tipo esperado por el backend. |
| descripcion | text | Sí | Explicación del efecto del parámetro. |
| actualizado_por | uuid (FK → funcionarios.id) | Sí | Último funcionario que lo modificó. |
| actualizado_en | timestamptz | No | Fecha del último cambio. |

**Valores semilla:**

| clave | valor | descripción |
| --- | --- | --- |
| `solicitud_reserva_expiracion_minutos` | `15` | Minutos de vigencia de una solicitud antes de expirar si el Core no confirma el pago. |
| `polling_intervalo_segundos` | `20` | Intervalo de consulta activa contra el Core, si se implementa polling además del webhook. *(Se elimina de la siembra si el equipo decide que el webhook del Core es suficiente por sí solo — ver punto abierto.)* |

---

## 12. auditoria

Bitácora transversal para registrar operaciones críticas, cambios de
configuración y modificaciones de usuarios.

| Campo | Tipo | Nulo | Descripción |
| --- | --- | --- | --- |
| id | uuid (PK) | No | Identificador único del evento de auditoría. |
| tabla | varchar(50) | No | Nombre de la tabla afectada. |
| registro_id | varchar(100) | No | Identificador del registro modificado (`uuid` o, en `parametros_sistema`, la `clave`). |
| accion | varchar(50) | No | Tipo de operación ejecutada, texto libre validado en backend. |
| usuario_id | uuid (FK → funcionarios.id) | Sí | Funcionario autor de la acción (`NULL` si fue un proceso/job automático). |
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

campos_deportivos 1───────N solicitud_reserva_detalle N───────1 solicitudes_reserva
                                    │                              │
                                    │ 1                            │ referencia_recaudaciones
                                    │ 0/1                          ▼
                                 reservas              ┌─────────────────────────┐
                                                        │  Core de Recaudaciones   │
                                                        │  (sistema externo,       │
                                                        │  otro repositorio,       │
                                                        │  otra base de datos)     │
                                                        └─────────────────────────┘

parametros_sistema (independiente, consultada por el motor de solicitudes)
```

---

## Anexo A — Restricciones críticas de integridad (DDL)

### A.1 — Evitar doble reserva del mismo horario

Sin cambios técnicos respecto a la versión anterior — el mismo mecanismo,
sobre la tabla renombrada:

```sql
-- Trigger que mantiene sincronizado solicitud_reserva_detalle.estado_solicitud
CREATE OR REPLACE FUNCTION sync_estado_solicitud() RETURNS trigger AS $$
BEGIN
  UPDATE solicitud_reserva_detalle
     SET estado_solicitud = NEW.estado
   WHERE solicitud_reserva_id = NEW.id;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_estado_solicitud
AFTER UPDATE OF estado ON solicitudes_reserva
FOR EACH ROW EXECUTE FUNCTION sync_estado_solicitud();

-- Restricción de no-solape: ningún campo puede tener dos franjas activas que se crucen en el tiempo
ALTER TABLE solicitud_reserva_detalle
  ADD CONSTRAINT no_solape_horario
  EXCLUDE USING gist (
    campo_id WITH =,
    rango_horario WITH &&
  )
  WHERE (estado_solicitud IN ('pendiente','confirmada'));
```

Requiere la extensión `btree_gist` habilitada (ver Módulo 0.8 / Módulo 1 del
roadmap).

### A.2 — Idempotencia ante confirmación duplicada del Core (sin restricción nueva)

La versión anterior de este documento resolvía la idempotencia del webhook
con `UNIQUE (proveedor_pasarela_id, transaccion_externa_id)` sobre
`pagos_confirmados`. Esa tabla ya no existe. La pregunta que corresponde
hacerse no es "¿qué restricción nueva la reemplaza?", sino "¿sigue habiendo
una garantía equivalente?" — y la respuesta es sí, sin agregar nada:

```sql
-- Ya existe desde la creación de la tabla reservas — no es una migración nueva.
ALTER TABLE reservas
  ADD CONSTRAINT reservas_solicitud_reserva_detalle_id_unique
  UNIQUE (solicitud_reserva_detalle_id);
```

Si el Core envía el mismo webhook de confirmación dos veces —comportamiento
normal cuando no recibe un `200 OK` a tiempo—, el backend de Canchas debe
seguir aplicando una verificación rápida (`estado === 'confirmada'` → no
hacer nada) como primera línea, pero la garantía real, a prueba de dos
confirmaciones llegando casi al mismo tiempo, sigue siendo la base de datos:
la segunda inserción en `reservas` para la misma
`solicitud_reserva_detalle_id` choca contra este `UNIQUE` ya existente y
falla con el código `23505`, capturable exactamente igual que antes.

---

## Anexo B — Índices recomendados de rendimiento

```sql
-- Job de expiración de solicitudes pendientes
CREATE INDEX idx_solicitudes_pendientes_expiracion
  ON solicitudes_reserva (estado, expira_en)
  WHERE estado = 'pendiente';

-- Pantalla de ocupación en tiempo real y reportes por fecha/campo
CREATE INDEX idx_reservas_campo_fecha
  ON reservas (campo_id, fecha_reserva);

-- Grilla de disponibilidad pública (consulta más frecuente de la app móvil)
CREATE INDEX idx_detalle_campo_fecha
  ON solicitud_reserva_detalle (campo_id, fecha_reserva)
  WHERE estado_solicitud IN ('pendiente','confirmada');

-- Mapa interactivo
CREATE INDEX idx_campos_lat_lng
  ON campos_deportivos (latitud, longitud);

-- Reporte de ocupación filtrado por funcionario
CREATE INDEX idx_asignaciones_funcionario
  ON asignaciones_funcionario (funcionario_id);

-- Reporte de clientes/asociaciones frecuentes (si Canchas conserva este reporte — ver Documento 1 v3, punto abierto 5)
CREATE INDEX idx_solicitudes_pagador
  ON solicitudes_reserva (ci_nit_pagador, telefono_pagador);
```

`referencia_recaudaciones` no necesita un índice declarado aparte: al ser
`UNIQUE`, PostgreSQL ya crea el índice correspondiente automáticamente, y
ese es exactamente el que se usa para localizar la solicitud al recibir un
webhook del Core.

*(Eliminado respecto a la v2 — la tabla de origen no existe:)*
```sql
-- YA NO EXISTE
CREATE INDEX idx_intentos_pasarela_proveedor_fecha
  ON intentos_pasarela (proveedor_pasarela_id, creado_en);
```
