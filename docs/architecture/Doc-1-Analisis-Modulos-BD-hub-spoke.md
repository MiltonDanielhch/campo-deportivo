# Documento 1: Análisis de Módulos y Estructura de Base de Datos

**Sistema de Administración del Alquiler de Campos Deportivos**

**Gobierno Autónomo Departamental del Beni — Secretaría Departamental de Deportes**

*Versión 3 — revisada para la arquitectura Hub & Spoke. Reemplaza a la
Versión 2. Canchas deja de procesar pagos directamente y pasa a ser un
satélite (spoke) que delega todo cobro al Core de Recaudaciones (hub). Los
cambios respecto a la Versión 2 están documentados en detalle en
`analisis-actualizacion-documento1-hub-spoke.md`; este documento incorpora
esos cambios directamente en el texto.*

---

## 1. Decisiones de arquitectura confirmadas

1. **Arquitectura Hub & Spoke — Canchas delega el cobro al Core de Recaudaciones**:
El Sistema de Campos Deportivos es un **satélite** dentro del ecosistema de
sistemas del GAD Beni. El **Core de Recaudaciones** es el sistema central que registra
clientes, genera liquidaciones, integra pasarelas de pago (AGETIC/SINTESIS),
recibe webhooks bancarios, procesa pagos manuales, concilia y factura — **es
el único sistema del ecosistema que habla con bancos.** Canchas nunca se
integra directamente con una pasarela de pago. Cuando un ciudadano quiere
reservar, el backend de Canchas le pide al Core, mediante una API HTTP
autenticada (token de servicio), que genere el cobro correspondiente y le
devuelva una referencia para ese cobro. Canchas mantiene su propia base de
datos PostgreSQL, completamente separada de la del Core: no comparten base
de datos ni código, únicamente esa API.
*Consecuencia directa:* Canchas no implementa Strategy pattern ni Circuit
Breaker para pasarelas —no hay múltiples proveedores que orquestar desde su
perspectiva—, no almacena un solo dato financiero, y no construye ningún
flujo de contingencia de pago (eso es responsabilidad exclusiva del Core).
A cambio, depende de la disponibilidad del Core para generar cobros nuevos
(ver punto abierto 4, sección 6).

2. **Persistencia diferida (Principio de cero solicitudes huérfanas)**:
Ninguna reserva se almacena como "confirmada" en las tablas definitivas del
sistema si no existe una confirmación de pago recibida del Core de
Recaudaciones. Para evitar bloqueos indebidos de canchas, el proceso de
reserva utiliza una **Solicitud de Reserva temporal con tiempo de expiración
corto** (configurable, 15 minutos por defecto). Si el Core no confirma el
pago dentro de ese lapso, la solicitud expira y el horario permanece visible
y disponible para otros usuarios en tiempo real.

3. **Verificación de confirmación del Core (Webhook + Polling opcional)**:
La confirmación de que un cobro se pagó no depende únicamente de la
recepción pasiva de un webhook que el Core envía a Canchas. Mientras la
solicitud esté vigente, el backend puede además consultar activamente
(*polling*) el estado del cobro contra la API del Core, usando la referencia
recibida al solicitarlo. A diferencia de la arquitectura anterior, aquí solo
existe una relación que verificar —con el Core—, no varias pasarelas
compitiendo entre sí; el mecanismo es más simple, pero el principio de no
depender de una sola vía de confirmación se mantiene.

4. **App Móvil Pública anónima, con identificación ligera del solicitante**:
La aplicación móvil en Flutter no requiere inicio de sesión, usuario ni
contraseña — se elimina toda fricción de registro. Sin embargo, al momento
de generar la solicitud de reserva, la app captura datos mínimos del
solicitante (nombre completo o razón social, teléfono, y opcionalmente
CI/NIT), necesarios para que Canchas pueda contactar al ciudadano y generar
su propio reporte operativo de clientes frecuentes por cancha.
*Punto pendiente de alinear con el equipo del Core:* el Core también
"registra clientes" (CI/NIT) para sus propios fines de facturación. Falta
confirmar entre ambos equipos si esto implica una duplicación de datos que
convenga evitar, o si es aceptable que cada sistema mantenga su propia copia
con fines distintos (Canchas: operativo/uso de infraestructura; Core:
financiero/facturación).

5. **Acceso y control por asignación de funcionario**:
El panel web administrativo utiliza autenticación por tokens de Sanctum
(documentada como ADR-004 en el roadmap de implementación: tokens Bearer en
vez de cookies de sesión SPA, dado que el panel y el backend pueden operar
en orígenes distintos). Los **Funcionarios de Control** no pueden ver la
recaudación global ni la información de otros escenarios; el sistema filtra
automáticamente sus vistas para mostrar únicamente los campos deportivos que
tienen asignados. El Administrador de Paramétricas y Gerencia tienen
visibilidad global.

6. **Reservas multi-franja bajo una sola solicitud (casos de campeonato)**:
El sistema soporta que una misma solicitud de reserva cubra más de una
franja horaria y/o más de una fecha (por ejemplo, 7 días de uso continuo
para un campeonato). Una solicitud puede generar una o varias reservas,
todas ligadas a la misma referencia de cobro ante el Core y al mismo monto
total. Si el pago no se confirma, ninguna de las franjas solicitadas queda
bloqueada — se liberan todas juntas al expirar la solicitud.

7. **Parámetros operativos configurables, no hardcodeados**:
El tiempo de expiración de la solicitud de reserva y, si aplica, el
intervalo de consulta activa (*polling*) contra el Core, se gestionan desde
una configuración editable por el Administrador de Paramétricas, con
auditoría de cada cambio — no como constantes fijas en el código. La lista
de parámetros es deliberadamente corta: ya no existen parámetros de Circuit
Breaker ni de contingencia, porque ninguno de los dos mecanismos vive en
Canchas.

8. **Estándar de fecha/hora único: `timestamptz` en zona horaria America/La_Paz**:
Todas las columnas que registran momentos de eventos del sistema (creación
de solicitud, expiración, confirmación) usan tipo `timestamptz`, para evitar
desfases de horario entre entornos que puedan alterar el momento real en que
una solicitud expira y un horario se libera.

---

## 2. Principios de diseño de la base de datos

* **Motor**: PostgreSQL. La razón es una sola y es puramente de
  concurrencia física, no financiera: Canchas necesita evitar, a nivel de
  base de datos, que dos personas reserven la misma cancha a la misma hora.
  Eso se resuelve con el tipo `tstzrange` y una restricción `EXCLUDE USING
  gist`, una capacidad que MySQL no ofrece de forma nativa equivalente. Aun
  si Canchas no procesara un solo boliviano, seguiría necesitando
  PostgreSQL por esta única razón.
* **Aislamiento de datos financieros**: la base de datos de Canchas no
  almacena ningún dato financiero de fondo (montos efectivamente
  confirmados por un banco, medios de pago, identificadores de transacción
  bancaria) más allá del monto que ella misma calcula para solicitar el
  cobro. Toda esa información vive exclusivamente en el Core de
  Recaudaciones — Canchas solo sabe que pidió un cobro, con qué referencia,
  y si el Core le confirmó o no que se pagó.
* **Claves primarias**: Uso estándar de `uuid` en todas las tablas para
  garantizar identificadores únicos, seguros y no secuenciales.
* **Mapeo georreferenciado**: Los campos deportivos incluyen coordenadas
  geográficas (`latitud`, `longitud`) para alimentar el mapa interactivo en
  la app móvil y en el backoffice.
* **Versionado estricto de tarifas**: Las tarifas por hora de los campos
  nunca se sobrescriben. Cada cambio de precio cierra la vigencia de la fila
  actual (`vigente_hasta = NOW()`) y crea una nueva fila. Las reservas
  antiguas conservan el monto exacto que tenían asignado al momento de
  emitirse la solicitud.
* **Moneda única**: Todos los valores monetarios que Canchas calcula (para
  informarle al Core cuánto cobrar) se expresan en bolivianos (`BOB`),
  utilizando el tipo de dato `numeric(12,2)`.
* **Prevención de dobles reservas (Race Conditions)**: Se aplican
  restricciones a nivel de base de datos (índice único parcial o
  restricción `EXCLUDE` sobre rangos de tiempo) sobre la combinación
  `(campo_id, fecha_reserva, hora_inicio)` para las solicitudes activas,
  además del bloqueo transaccional a nivel de aplicación.
* **Idempotencia ante la confirmación del Core**: La referencia que el Core
  de Recaudaciones entrega al generar un cobro es única por solicitud. Un
  mismo webhook de confirmación recibido más de una vez —comportamiento
  normal si el Core no recibe un `200 OK` a tiempo— no debe generar
  procesamiento duplicado. A diferencia de la arquitectura anterior, esta
  garantía ahora protege una sola relación (con el Core), no varias
  pasarelas simultáneas.
* **Trazabilidad pública sin cuenta**: Cada solicitud de reserva y cada
  reserva confirmada tienen un código corto y único (`codigo_seguimiento`,
  `codigo_reserva`) que el ciudadano puede usar para consultar el estado de
  su trámite sin necesidad de iniciar sesión.
* **Bitácora de auditoría transversal**: Toda aprobación manual (cambio de
  tarifa, cambio de estado de un campo, modificación de usuarios) queda
  registrada con el usuario responsable, timestamp y payload de datos
  previos y nuevos.

---

## 3. Descripción de módulos del sistema

```
┌────────────────────────────────────────────────────────────────────────┐
│                        Móvil (Flutter) - Pública                       │
│  - Consulta de Campos y Mapa     - Selección de Horarios Libres        │
│  - Solicitud de Reserva          - Recepción de Confirmación/Ticket    │
│  - Consulta de estado por código de seguimiento                        │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                Backend de Canchas (Laravel) — Satélite                 │
│  - Motor de Solicitudes de Reserva - Job Scheduler de Expiraciones     │
│  - Control de Ocupación Real-time  - Parámetros Operativos             │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │  API HTTP autenticada
                                    │  (RecaudacionesApiClient)
                                    ▼
                    ┌───────────────────────────────┐
                    │  Core de Recaudaciones (Hub)   │
                    │  — otro equipo, otro repo —    │
                    │  Clientes · Pasarelas · Bancos  │
                    │  Conciliación · Facturación     │
                    └───────────────────────────────┘
                                    ▲
                                    │
┌───────────────────────────────────┴────────────────────────────────────┐
│                    Web Administrative (React/Vite)                     │
│  - Paramétricas y Tarifas         - Asignación de Campos a Control     │
│  - Mapa de Ocupación Global       - Dashboard Gerencial y Reportes     │
└────────────────────────────────────────────────────────────────────────┘
```

### Módulo A — Paramétricas e Infraestructura Deportiva

Administra el catálogo de canchas, piscinas y espacios deportivos
administrados por la Secretaría. Permite categorizar por tipo de deporte,
definir geolocalización, configurar horarios de atención y gestionar el
historial versionado de tarifas por hora. Sin cambios respecto a la
arquitectura anterior — nunca tuvo relación con el procesamiento de pagos.

### Módulo B — Gestión de Usuarios y Asignación de Control

Gestiona las cuentas de acceso del personal del GAD Beni
(`admin_parametricas`, `funcionario_control`, `gerencia`). Incluye la matriz
de asignación física que vincula a cada funcionario de control con las
canchas específicas donde realizará la verificación de reservas. Sin cambios.

### Módulo C — Consulta Pública y Disponibilidad (App Móvil)

Expone la información pública del sistema sin autenticación. Permite a los
ciudadanos navegar por los campos, ver su ubicación en un mapa interactivo y
consultar la grilla horaria en tiempo real. Sin cambios.

### Módulo D — Núcleo de Solicitudes de Reserva

Módulo crítico del sistema, **modificado de fondo respecto a la versión
anterior**. Gestiona la creación de la `solicitud_reserva` (que puede cubrir
una o varias franjas horarias), **solicita el cobro al Core de Recaudaciones
mediante una única llamada de API** —ya no existe el failover entre
proveedores, porque desde la perspectiva de Canchas hay un solo sistema
externo con el que hablar—, bloquea temporalmente el/los horario(s) y
escucha la confirmación que el Core envía (vía webhook o polling). Si se
confirma, escribe el/los registro(s) definitivo(s) en la tabla `reservas`.
Si expira o se cancela, libera todas las franjas solicitadas
automáticamente.

### Módulo E — ~~Contingencia de Pagos~~ *(eliminado)*

Este módulo **ya no existe en la arquitectura de Canchas**. Todo lo que
antes cubría —caída de pasarelas, comprobante manual, aprobación
administrativa— es responsabilidad exclusiva del Core de Recaudaciones,
invisible para Canchas. Si el Core no puede cobrar por sus vías normales,
cómo lo resuelve internamente no es un problema que Canchas necesite
modelar.

### Módulo F — Monitoreo, Control en Sitio y Reportes Gerenciales

Provee la pantalla de ocupación en tiempo real para el funcionario en cancha
(filtrada por sus asignaciones), el mapa de ocupación global para
Administrador/Gerencia, y reportes operativos (tendencias de alquiler,
histogramas de ocupación, clientes/asociaciones frecuentes por cancha).
*Punto a decidir con el equipo del Core (ver sección 6):* si el reporte de
**ingresos consolidados** en bolivianos sigue siendo responsabilidad de
Canchas (que igual tiene el dato, porque calcula `monto_total` para cada
solicitud) o si Gerencia consulta esa cifra directamente en el panel del
Core, que es quien concilia el dinero real recibido.

### Módulo G — Configuración Operativa

**Simplificado radicalmente** respecto a la versión anterior. Ya no existe
un registro histórico de intentos de cobro por proveedor —esa
responsabilidad de observabilidad (Circuit Breaker, múltiples pasarelas) no
aplica más—. Lo que queda es mínimo: los parámetros operativos configurables
(tiempo de expiración de solicitud, intervalo de polling), sin tabla
dedicada de "intentos" — un registro simple de llamadas al Core, si se
necesita para depuración, alcanza con el sistema de logs estándar del
backend.

### Módulo H — Integración con el Core de Recaudaciones *(nuevo)*

Módulo dedicado exclusivamente a la frontera entre Canchas y el Core.
Contiene: el cliente HTTP autenticado (`RecaudacionesApiClient`) que
solicita cobros y consulta su estado; el endpoint que recibe el webhook de
confirmación del Core (con verificación de firma — la contraparte de Canchas
nunca procesa un webhook sin validar que efectivamente proviene del Core); y
el manejo explícito del caso en que el Core no responde (ver punto abierto 4
de la sección 6). Este módulo reemplaza, en superficie de responsabilidad,
a lo que antes cubrían los Módulos D (parte de pasarelas) y E completo.

---

## 4. Máquina de estados de la transacción

```
                    ┌────────────────────────────┐
                    │ solicitud_reserva: PENDIENTE │
                    └──────────────┬──────────────┘
                                   │
      ┌───────────────┬───────────┼───────────────┬──────────────┐
      │ (Timeout       │ (Core     │ (Core informa │ (Ciudadano   │
      │  del lado de   │  confirma │  rechazo)      │  cancela)    │
      │  Canchas)      │  el pago) │                │              │
      ▼                ▼           ▼                ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│  EXPIRADA   │ │ CONFIRMADA  │ │ RECHAZADA   │ │ CANCELADA   │
└─────────────┘ └──────┬──────┘ └─────────────┘ └─────────────┘
                        ▼
              ┌──────────────────────────┐
              │ Genera una o varias filas │
              │  definitivas en RESERVAS  │
              └──────────────────────────┘
```

**Notas sobre la máquina de estados:**
- Cuatro estados terminales, sin ninguna bifurcación interna. Toda la
  complejidad de "qué proveedor, qué intento de cobro, aprobado por quién"
  vive del otro lado de la API del Core, invisible para este diagrama —es
  exactamente la simplificación que la arquitectura Hub & Spoke buscaba
  lograr.
- `EXPIRADA` (venció el plazo sin confirmación) y `CANCELADA` (el ciudadano
  abandonó el proceso voluntariamente) se distinguen para no mezclar
  abandono voluntario con fallas de tiempo en los reportes.
- `RECHAZADA` cubre cualquier caso en que el Core informe que el cobro no
  se pudo completar, sin que Canchas necesite saber el motivo específico
  (tarjeta rechazada, transferencia fallida, lo que sea — es interno al
  Core).

---

## 5. Relaciones (Resumen de arquitectura)

* **`tipos_campo`** `1 ── N` **`campos_deportivos`**: Cada escenario
  pertenece a una categoría deportiva.
* **`campos_deportivos`** `1 ── N` **`tarifas_campo`**: Historial de
  precios por hora.
* **`campos_deportivos`** `1 ── N` **`horarios_atencion`**: Horario de
  apertura/cierre por día de la semana.
* **`funcionarios`** `N ── 1` **`roles`**: Perfiles de acceso al sistema
  web.
* **`funcionarios`** `1 ── N` **`asignaciones_funcionario`** `N ── 1`
  **`campos_deportivos`**: Vinculación de un funcionario de control con las
  canchas que supervisa.
* **`campos_deportivos`** `1 ── N` **`solicitud_reserva_detalle`**: Cada
  franja solicitada pertenece a un campo específico.
* **`solicitudes_reserva`** `1 ── N` **`solicitud_reserva_detalle`**: Una
  solicitud puede cubrir una o varias franjas (campeonatos).
* **`solicitudes_reserva`** `1 ── N` **`reservas`**: Una solicitud
  confirmada puede generar una o varias reservas.
* **`solicitud_reserva_detalle`** `1 ── 0/1` **`reservas`**: Solo existe
  una reserva si la franja terminó siendo parte de una solicitud confirmada.
* **`parametros_sistema`**: Tabla de configuración global (clave/valor),
  sin relación directa a otras entidades.

*Eliminadas respecto a la Versión 2* (las tablas de origen ya no existen):
~~`ordenes_pago 1──0/1 pagos_confirmados`~~, ~~`ordenes_pago 1──0/1
pagos_contingencia`~~, ~~`ordenes_pago 1──N intentos_pasarela`~~.

---

## 6. Puntos abiertos antes de pasar al diseño DDL / Migraciones

1. **Definición de franjas horarias**: Confirmar si los alquileres se
   realizan únicamente en bloques cerrados de 1 hora o si se permiten
   bloques fraccionados de 30 minutos o alquileres continuos multi-hora.
2. **Políticas de devolución o reprogramación**: Confirmar si se incluirá
   un flujo administrativo fuera de sistema para reprogramaciones por
   motivos climáticos, y si una devolución implica coordinar con el Core
   (que es quien maneja el dinero real).
3. **Horarios de atención variables por día de la semana**: Confirmar que
   todos los campos pueden operar con horarios distintos entre semana y
   fin de semana, o si algunos casos requieren excepciones por feriado.
4. **Comportamiento si el Core de Recaudaciones no responde**: A diferencia
   de la arquitectura anterior, ya no existe un "segundo proveedor" al cual
   recurrir si el primero falla — el Core es la única vía de cobro. Falta
   decidir si esto se traduce en un mensaje simple de "servicio de pago no
   disponible temporalmente" sin alternativa, o si Canchas debe degradar de
   alguna otra forma (por ejemplo, seguir mostrando disponibilidad y
   permitir seleccionar franjas, pero bloquear el paso final de solicitar
   el cobro con un mensaje claro).
5. **Propietario del reporte de ingresos consolidados**: Definir si Canchas
   conserva su propio reporte de montos por campo (dato que ya tiene, por
   calcular `monto_total` en cada solicitud) o si Gerencia consulta esa
   cifra en el panel del Core, que es quien concilia el dinero efectivamente
   recibido.
6. **Fuente de verdad de la identidad del ciudadano**: Confirmar con el
   equipo del Core si la captura de nombre/teléfono/CI-NIT en Canchas
   duplica innecesariamente lo que el Core ya registra por su cuenta, o si
   ambos sistemas mantienen su propia copia con fines distintos
   (operativo vs. financiero) sin que eso sea un problema.
