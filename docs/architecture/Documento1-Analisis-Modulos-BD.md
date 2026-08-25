# Documento 1: Análisis de Módulos y Estructura de Base de Datos

**Sistema de Administración del Alquiler de Campos Deportivos**

**Gobierno Autónomo Departamental del Beni — Secretaría Departamental de Deportes**

*Versión 2 — revisada. Incorpora correcciones sobre identificación de solicitantes, reservas multi-franja, máquina de estados y parámetros operativos.*

---

## 1. Decisiones de arquitectura confirmadas

1. **Persistencia diferida (Principio de cero reservas huérfanas)**:
Ninguna reserva se almacena como "confirmada" en las tablas definitivas del sistema si no existe un pago exitoso validado por la pasarela. Para evitar bloqueos indebidos de canchas, el proceso de reserva utiliza una **Orden de Pago temporal con tiempo de expiración corto (10 a 15 minutos, configurable — ver punto 8)**. Si el ciudadano no paga dentro de ese lapso, la orden expira y el horario permanece visible y disponible para otros usuarios en tiempo real.

2. **Integración con pasarela de pago agnóstica y redundante**:
El backend no se acopla a un único proveedor de cobro QR (Banco Unión, SINTESIS/PagosNet, etc.). Se implementa el patrón de arquitectura *Strategy* con un mecanismo de **Circuit Breaker**: si el proveedor principal falla o supera el tiempo de espera (*timeout*), el sistema reintenta automáticamente con el proveedor secundario de forma transparente para la aplicación móvil. **Esta redundancia es parte del flujo normal de pago desde la primera integración real con una pasarela — no es una funcionalidad de contingencia a implementar más adelante.** El sistema nunca debe quedar operando en producción acoplado a un solo proveedor.

3. **Mecanismo de verificación de pago dual (Webhook + Active Polling)**:
La confirmación del cobro no depende únicamente de la recepción pasiva del callback/webhook del banco. Durante la ventana vigente de la orden de pago, el backend ejecuta consultas activas (*active polling*) periódicas contra la API de la pasarela para verificar si la transacción cambió a estado exitoso. Ambas vías (webhook y polling) deben ser **idempotentes**: si la misma transacción se reporta más de una vez (comportamiento normal de las pasarelas cuando no reciben confirmación de recepción a tiempo), el sistema no debe procesarla dos veces.

4. **App Móvil Pública anónima, con identificación ligera del solicitante**:
La aplicación móvil en Flutter (orientada a los deportistas) no requiere inicio de sesión, usuario ni contraseña — se elimina toda fricción de registro. La responsabilidad de autenticar la identidad del pagador recae exclusivamente en la entidad bancaria desde la cual el ciudadano escanea el código QR.
Sin embargo, al momento de generar la orden de pago, la app **sí captura datos mínimos de contacto e identificación** (nombre completo o razón social, teléfono, y opcionalmente CI/NIT). Esto no constituye una cuenta ni requiere contraseña; es un formulario breve, necesario para tres cosas que el sistema debe garantizar:
   - Poder generar el **reporte de clientes/asociaciones frecuentes** exigido por la Secretaría.
   - Tener un canal de contacto para reenviar el comprobante digital o notificar el resultado de una verificación manual de contingencia.
   - Permitir que el propio ciudadano **consulte el estado de su orden** más adelante usando un código corto de seguimiento (ver punto 9), sin necesidad de cuenta.

5. **Acceso y control por asignación de funcionario**:
El panel web administrativo utiliza autenticación JWT/Sanctum. Los **Funcionarios de Control** (encargados en cancha) no pueden ver la recaudación global ni la información de otros escenarios; el sistema filtra automáticamente sus vistas y reportes en tiempo real para mostrar **únicamente los campos deportivos que tienen asignados**. El Administrador de Paramétricas y Gerencia, en cambio, tienen visibilidad global de todos los campos, incluyendo el mapa de ocupación en tiempo real completo.

6. **Manejo de contingencia ante caída total de pasarelas (Nivel 3)**:
Si los proveedores de QR están inoperativos, el sistema habilita temporalmente un flujo de contingencia excepcional: el ciudadano sube la foto de su comprobante de transferencia bancaria manual, la orden entra en estado `pendiente_verificacion` bloqueando el horario por un máximo de 2 horas, y el **Administrador de Paramétricas** aprueba o rechaza manualmente la reserva tras verificar el extracto bancario. Si el comprobante es **rechazado**, la orden pasa a un estado terminal de rechazo y el horario se libera de inmediato. Ningún funcionario de control recibe efectivo en sitio bajo ninguna circunstancia.

7. **Reservas multi-franja bajo una sola orden de pago (casos de campeonato)**:
El sistema debe soportar que una misma orden de pago cubra **más de una franja horaria y/o más de una fecha** (por ejemplo, 7 días de uso continuo para un campeonato, reservados con anticipación). El modelo adoptado es: **una orden de pago puede generar una o varias reservas**, todas ligadas al mismo pago y al mismo monto total (suma de las tarifas de cada franja incluida). Si el pago no se confirma, **ninguna** de las franjas solicitadas queda bloqueada — se liberan todas juntas al expirar la orden.

8. **Parámetros operativos configurables, no hardcodeados**:
Valores como el tiempo de expiración de la orden de pago, el plazo máximo del modo de contingencia (2 horas) y el umbral de fallos consecutivos que abre el Circuit Breaker se gestionan desde una configuración editable por el Administrador de Paramétricas, con auditoría de cada cambio — no como constantes fijas en el código, ya que la operación real puede exigir ajustarlos sin un nuevo despliegue.

9. **Estándar de fecha/hora único: `timestamptz` en zona horaria America/La_Paz**:
Todas las columnas que registran momentos de eventos del sistema (creación de orden, expiración, confirmación de pago, revisión de contingencia) usan tipo `timestamptz`, para evitar desfases de horario entre entornos que puedan alterar el momento real en que una orden expira y un horario se libera.

---

## 2. Principios de diseño de la base de datos

* **Motor**: PostgreSQL.
* **Claves primarias**: Uso estándar de `uuid` en todas las tablas para garantizar identificadores únicos, seguros y no secuenciales.
* **Mapeo georreferenciado**: Los campos deportivos incluyen coordenadas geográficas (`latitud`, `longitud`) para alimentar el mapa interactivo en la app móvil y en el backoffice.
* **Versionado estricto de tarifas**: Las tarifas por hora de los campos nunca se sobrescriben. Cada cambio de precio cierra la vigencia de la fila actual (`vigente_hasta = NOW()`) y crea una nueva fila. Las reservas antiguas conservan el monto exacto que tenían asignado al momento de emitirse la orden.
* **Moneda única**: Todos los valores monetarios se expresan en bolivianos (`BOB`), utilizando el tipo de dato `numeric(12,2)`.
* **Prevención de dobles reservas (Race Conditions)**: Se aplican **restricciones a nivel de base de datos** (índice único parcial o restricción `EXCLUDE` sobre rangos de tiempo) sobre la combinación `(campo_id, fecha_reserva, hora_inicio)` para las órdenes activas, además del bloqueo transaccional a nivel de aplicación. Depender solo del lock aplicativo no es suficiente ante concurrencia real; la barrera final debe estar en la base de datos.
* **Idempotencia ante integraciones externas**: El identificador de transacción reportado por cada pasarela de pago es único por proveedor. Un mismo webhook recibido más de una vez no debe generar procesamiento duplicado.
* **Trazabilidad pública sin cuenta**: Cada orden de pago y cada reserva confirmada tienen un código corto y único (`codigo_orden`, `codigo_reserva`) que el ciudadano puede usar para consultar el estado de su trámite sin necesidad de iniciar sesión.
* **Bitácora de auditoría transversal**: Toda aprobación manual de contingencia, cambio de tarifa, cambio de parámetros del sistema o modificación de usuarios queda registrada con el usuario responsable, timestamp y payload de datos previos y nuevos.

---

## 3. Descripción de módulos del sistema

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Móvil (Flutter) - Pública                       │
│  - Consulta de Campos y Mapa     - Selección de Horarios Libres        │
│  - Solicitud de QR de Pago       - Recepción de Confirmación/Ticket    │
│  - Consulta de estado por código de seguimiento                        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                      Backend API (Laravel Core)                        │
│  - Motor Transaccional de Ordenes - Job Scheduler de Expiraciones      │
│  - Pasarela Redundante (Strategy) - Control de Ocupación Real-time     │
│  - Log de Intentos por Proveedor  - Parámetros Operativos Configurables│
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                    Web Administrative (React/Vite)                     │
│  - Paramétricas y Tarifas         - Asignación de Campos a Control     │
│  - Validaciones de Contingencia   - Dashboard Gerencial y Reportes     │
│  - Mapa de Ocupación Global (Admin/Gerencia)                           │
└────────────────────────────────────────────────────────────────────────┘

```

### Módulo A — Paramétricas e Infraestructura Deportiva

Administra el catálogo de canchas, piscinas y espacios deportivos administrados por la Secretaría. Permite categorizar por tipo de deporte, definir geolocalización, configurar horarios de atención y gestionar el historial versionado de tarifas por hora.

### Módulo B — Gestión de Usuarios y Asignación de Control

Gestiona las cuentas de acceso del personal del GAD Beni (`admin_parametricas`, `funcionario_control`, `gerencia`). Incluye la matriz de asignación física que vincula a cada funcionario de control con las canchas específicas donde realizará la verificación de reservas.

### Módulo C — Consulta Pública y Disponibilidad (App Móvil)

Expone la información pública del sistema sin autenticación. Permite a los ciudadanos navegar por los campos, ver su ubicación en un mapa interactivo y consultar la grilla horaria en tiempo real (distinguiendo slots libres, ocupados con pago confirmado y bloqueados temporalmente por órdenes en proceso). Incluye también la consulta de estado de una orden o reserva mediante su código corto.

### Módulo D — Núcleo Transaccional (Reserva, Pago QR y Expiración)

Módulo crítico del sistema. Gestiona la creación de la `orden_pago` (que puede cubrir una o varias franjas horarias, ver decisión #7), solicita el cobro a la pasarela activa mediante failover automático, bloquea temporalmente el/los horario(s) y escucha la confirmación bancaria (vía webhook o polling). Si se confirma, escribe el/los registro(s) definitivo(s) en la tabla `reservas`. Si expira o se cancela, libera todas las franjas solicitadas automáticamente.

### Módulo E — Contingencia de Pagos (Nivel 3)

Canal de excepción que se activa ante caídas comprobadas de la red de pagos QR (es decir, cuando el failover automático del Módulo D ya falló contra ambos proveedores). Permite la recepción de comprobantes de transferencia manual, el bloqueo temporal por un período extendido (hasta 2 horas), y la interfaz de aprobación o rechazo para el administrador.

### Módulo F — Monitoreo, Control en Sitio y Reportes Gerenciales

Provee la pantalla de ocupación en tiempo real para el funcionario en cancha (filtrada por sus asignaciones), el mapa de ocupación global para Administrador/Gerencia, y el tablero gerencial (ingresos por campo, tendencias de alquiler, histogramas de ocupación y clientes/asociaciones frecuentes).

### Módulo G — Configuración Operativa y Observabilidad de Pagos

Módulo transversal, de uso interno del equipo técnico y del Administrador de Paramétricas. Centraliza los parámetros operativos configurables (tiempo de expiración de orden, plazo de contingencia, umbral del Circuit Breaker) y el registro histórico de intentos de cobro por proveedor (éxitos, timeouts, aperturas/cierres del circuito), necesario para auditar el comportamiento de la redundancia de pasarelas y alimentar las alertas al equipo de TI.

---

## 4. Máquina de estados de la transacción

```
                         ┌────────────────────────┐
                         │  orden_pago: PENDIENTE  │
                         │ (incluye reintento auto-│
                         │  mático de proveedor y  │
                         │  polling activo)        │
                         └────────────┬────────────┘
                                      │
        ┌───────────────┬────────────┼────────────────┬───────────────┐
        │ (Timeout       │ (Pago      │ (Ambas          │ (Ciudadano    │
        │  vencido)      │  confirmado│  pasarelas       │  cancela)     │
        │                │  webhook o │  caídas)         │               │
        │                │  polling)  │                  │               │
        ▼                ▼            ▼                  ▼
 ┌─────────────┐  ┌─────────────┐  ┌────────────────────┐  ┌─────────────┐
 │  EXPIRADA   │  │ CONFIRMADA  │  │ PENDIENTE_          │  │ CANCELADA   │
 └─────────────┘  └──────┬──────┘  │ VERIFICACION        │  └─────────────┘
                         │         └──────────┬───────────┘
                         │              ┌──────┴──────┐
                         │      (Aprobado)│    (Rechazado)
                         │              ▼             ▼
                         │      ┌─────────────┐ ┌─────────────┐
                         │      │ CONFIRMADA  │ │ RECHAZADA   │
                         │      └──────┬──────┘ └─────────────┘
                         ▼             ▼
                  ┌──────────────────────────┐
                  │ Genera una o varias filas │
                  │  definitivas en RESERVAS  │
                  │  (una por franja incluida)│
                  └──────────────────────────┘
```

**Notas sobre la máquina de estados:**
- El failover automático de proveedor (Nivel 1) y el polling activo (Nivel 2) ocurren **dentro** del estado `PENDIENTE` — no son estados propios de la orden. Solo se escala a `PENDIENTE_VERIFICACION` cuando el sistema detecta que **ambos** proveedores están inoperativos (Nivel 3).
- `EXPIRADA` (venció el plazo sin pago) y `CANCELADA` (el ciudadano abandonó el proceso voluntariamente) se distinguen para no mezclar abandono voluntario con fallas de tiempo en los reportes gerenciales.
- `RECHAZADA` es el estado terminal cuando el Administrador de Paramétricas invalida un comprobante de contingencia; el horario se libera de inmediato.

---

## 5. Relaciones (Resumen de arquitectura)

* **`tipos_campo`** `1 ── N` **`campos_deportivos`**: Cada escenario pertenece a una categoría deportiva.
* **`campos_deportivos`** `1 ── N` **`tarifas_campo`**: Historial de precios por hora.
* **`campos_deportivos`** `1 ── N` **`horarios_atencion`**: Horario de apertura/cierre configurable por campo (evaluar si se requiere granularidad por día de la semana).
* **`funcionarios`** `N ── 1` **`roles`**: Perfiles de acceso al sistema web.
* **`funcionarios`** `1 ── N` **`asignaciones_funcionario`** `N ── 1` **`campos_deportivos`**: Vinculación de un funcionario de control con las canchas que supervisa.
* **`campos_deportivos`** `1 ── N` **`ordenes_pago`**: Cada orden de cobro iniciada pertenece a uno o más campos específicos.
* **`ordenes_pago`** `1 ── N` **`reservas`**: Una orden confirmada puede generar una o varias reservas (franjas horarias/fechas incluidas en esa orden).
* **`ordenes_pago`** `1 ── 0/1` **`pagos_confirmados`**: Información técnica de la pasarela bancaria, con identificador de transacción único por proveedor.
* **`ordenes_pago`** `1 ── 0/1` **`pagos_contingencia`**: Datos del comprobante manual en caso de falla de pasarelas, con resultado de aprobación o rechazo.
* **`ordenes_pago`** `1 ── N` **`intentos_pasarela`**: Historial de cada intento de cobro por proveedor (éxito, timeout, apertura de circuito).
* **`parametros_sistema`**: Tabla de configuración global (clave/valor), sin relación directa a otras entidades, consultada por el motor transaccional y el Circuit Breaker.

---

## 6. Puntos abiertos antes de pasar al diseño DDL / Migraciones

1. **Definición de franjas horarias**: Confirmar si los alquileres se realizan únicamente en bloques cerrados de 1 hora (ej. 14:00 a 15:00) o si se permiten bloques fraccionados de 30 minutos o alquileres continuos multi-hora.
2. **Políticas de devolución o reprogramación**: El sistema asume por defecto que no existen devoluciones automáticas una vez confirmado el pago. Se debe ratificar si se incluirá un flujo administrativo fuera de sistema para reprogramaciones por motivos climáticos.
3. **Plazo exacto de expiración de la orden de pago**: Se ha tomado un estándar de 15 minutos, ahora configurable (ver decisión #8). Confirmar si la pasarela bancaria impone un tiempo menor (ej. 10 minutos) que deba usarse como valor por defecto.
4. **Horarios de atención variables por día de la semana**: Confirmar si todos los campos operan con el mismo horario los 7 días, o si se necesita definir horarios distintos para fines de semana/feriados.
5. **Umbral concreto del Circuit Breaker**: Definir el número exacto de fallos consecutivos que abre el circuito hacia un proveedor caído, y el tiempo de "enfriamiento" antes de reintentarlo.
6. **Reglas de conciliación ante monto no coincidente**: Definir el procedimiento si el monto confirmado por la pasarela no coincide exactamente con el monto solicitado por el sistema (¿se rechaza automáticamente, o se marca para revisión manual?).
