# Documento 3: Historias de Usuario (Product Backlog)

**Sistema de Administración del Alquiler de Campos Deportivos**

**Gobierno Autónomo Departamental del Beni — Secretaría Departamental de Deportes**

*Versión 2 — revisada. Incorpora reservas multi-franja, consulta pública de estado, reubicación del failover automático de pasarela dentro de la Épica D, mapa global para Admin/Gerencia y una nueva Épica G de seguridad y publicación. Algunas historias fueron renumeradas respecto a la versión anterior.*

---

## Definición de Criterios

### Prioridad

* **Prioridad 0**: Requisito técnico o infraestructura base (bloqueante).
* **Prioridad Alta**: Funcionalidad principal del negocio.
* **Prioridad Media**: Operatividad avanzada, reportes y contingencia.

### Estimación Relativa

* **S (Small)**: Tarea sencilla, CRUD estándar o ajuste de UI.
* **M (Medium)**: Lógica de negocio moderada, validaciones o componentes complejos.
* **L (Large)**: Integración externa (pasarelas, webhooks), lógica transaccional sensible o componentes móviles complejos.

---

## Épica 0 — Preparación Técnica y Scaffolding (Sprint 0)

Precondición de todo el desarrollo. Configura los repositorios, entornos y migraciones DDL.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-01** | Como equipo de desarrollo, quiero la estructura base del repositorio del **Backend (Laravel API)** con autenticación Sanctum/JWT configurada, para exponer los endpoints iniciales. | 0 | S |  |
| **HU-02** | Como equipo de desarrollo, quiero la estructura base de la **App Web Administrativa (React + Vite + TS)** conectada al backend, para iniciar el desarrollo de la interfaz de backoffice. | 0 | S |  |
| **HU-03** | Como equipo de desarrollo, quiero la estructura base de la **App Móvil (Flutter)** configurada para Android/iOS, para construir las pantallas de consulta pública. | 0 | M |  |
| **HU-04** | Como equipo de desarrollo, quiero las **migraciones de las tablas definidas en el diccionario de datos vigente** (incluyendo `orden_pago_detalle`, `proveedores_pasarela`, `intentos_pasarela` y `parametros_sistema`) escritas y versionadas, junto con sus restricciones de integridad (índices únicos, `EXCLUDE`), para desplegar el modelo de base de datos en cualquier entorno con un solo comando. | 0 | M |  |
| **HU-05** | Como equipo de desarrollo, quiero un **Worker / Scheduler (Job)** programado para consultar y marcar como `EXPIRADA` toda `orden_pago` cuyo timestamp `expira_en` haya superado la hora actual sin confirmación, usando como intervalo el valor configurable `orden_pago_expiracion_minutos` de `parametros_sistema`, para liberar automáticamente todas las franjas incluidas en la orden. | 0 | M |  |

---

## Épica A — Gestión de Paramétricas y Campos Deportivos (Sprint 1)

Módulo web para registrar la infraestructura física y los precios por hora.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-A1** | Como Admin Paramétricas, quiero registrar y listar los **tipos de campos** (Fútbol, Futsal, Ráquet, Piscina, etc.), para categorizar la oferta de escenarios. | Alta | S |  |
| **HU-A2** | Como Admin Paramétricas, quiero dar de alta un **campo deportivo** registrando su código, nombre, dirección, coordenadas (`latitud`, `longitud`) y **horario de atención por día de la semana**, para habilitarlo en la oferta pública con la flexibilidad de tener horarios distintos, por ejemplo, en fin de semana. | Alta | M |  |
| **HU-A3** | Como Admin Paramétricas, quiero asignar y **actualizar la tarifa por hora** de un campo deportivo, cerrando la vigencia de la tarifa previa y creando una nueva, para preservar el historial de cobros antiguos. | Alta | M |  |
| **HU-A4** | Como Admin Paramétricas, quiero cambiar el estado de un campo a `mantenimiento` o `inactivo`, para suspender temporalmente la generación de reservas en la app móvil. | Alta | S |  |

---

## Épica B — Usuarios, Roles y Asignación de Control (Sprint 1)

Administración del personal del GAD Beni y delimitación de vistas.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-B1** | Como Administrador, quiero crear funcionarios asignándoles roles (`admin_parametricas`, `funcionario_control`, `gerencia`), para controlar el acceso al panel administrativo. | Alta | S |  |
| **HU-B2** | Como Funcionario, quiero **iniciar sesión** con usuario y contraseña, para acceder a las pantallas correspondientes a mi rol. | Alta | M |  |
| **HU-B3** | Como Administrador, quiero **asignar escenarios deportivos específicos** a un Funcionario de Control, para delimitar las canchas que supervisará en sitio. | Alta | M |  |

---

## Épica C — Consulta Pública en App Móvil (Sprint 2)

Navegación pública sin autenticación para los deportistas.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-C1** | Como Ciudadano, quiero ver el **listado y mapa interactivo** de campos deportivos en la app móvil en Flutter (sin requerir inicio de sesión), para ubicar las canchas disponibles. | Alta | M |  |
| **HU-C2** | Como Ciudadano, quiero seleccionar un campo y una fecha para **ver la grilla horaria disponible**, identificando qué franjas están libres, ocupadas o bloqueadas temporalmente por una orden en proceso. | Alta | M |  |

---

## Épica D — Reserva y Pago QR (Sprints 3 y 4)

Lógica transaccional crítica del sistema. Incluye, desde el inicio, la resiliencia de proveedor de pago (Circuit Breaker) y el soporte de reservas de una o varias franjas bajo un mismo cobro — ninguna de las dos cosas se trata como funcionalidad "de contingencia" a añadir después, porque forman parte del comportamiento normal del sistema desde la primera integración real con una pasarela.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-D1** | Como Ciudadano, quiero seleccionar **una o varias franjas horarias libres** (incluso en distintas fechas, para casos como un campeonato de varios días) e ingresar mi nombre, teléfono y, opcionalmente, CI/NIT, para solicitar la generación de un único código QR de cobro por el monto total. | Alta | M |  |
| **HU-D2** | Como Sistema, quiero validar mediante una **restricción a nivel de base de datos** (no solo un bloqueo aplicativo) que ninguna de las franjas solicitadas esté ya ocupada por otra orden activa, y generar una `orden_pago` con su detalle de franjas asociado (`orden_pago_detalle`) y vigencia configurable, bloqueando provisionalmente todas las franjas incluidas. | Alta | L |  |
| **HU-D3** | Como Sistema, quiero conectarme con la API del proveedor QR principal para solicitar el cobro, y **cambiar automáticamente al proveedor secundario mediante Circuit Breaker** si el principal no responde dentro del tiempo de espera configurado, de forma transparente para el ciudadano. | Alta | L |  |
| **HU-D4** | Como Sistema, quiero **recibir el Webhook/Callback de la pasarela de pago** al confirmarse el cobro (de forma idempotente ante reenvíos duplicados del mismo proveedor), actualizar el estado de la orden a `CONFIRMADA` y **crear automáticamente un registro en la tabla `reservas` por cada franja incluida** en la orden. | Alta | L |  |
| **HU-D5** | Como Sistema, quiero ejecutar **Active Polling** contra la pasarela mientras la orden esté vigente, para confirmar el pago aunque el Webhook del banco falle o se demore. | Alta | M |  |
| **HU-D6** | Como Ciudadano, quiero ver la confirmación inmediata en la App Móvil con mi **comprobante digital** (código de reserva por cada franja pagada) una vez procesado el pago. | Alta | S |  |
| **HU-D7** | Como Ciudadano, quiero **consultar el estado de mi orden** ingresando mi código de seguimiento, para saber si fue confirmada, rechazada o expiró — incluso si cerré la app o volví horas más tarde. | Alta | S |  |

**Criterios de aceptación clave** (por su sensibilidad transaccional):
- HU-D2 debe demostrarse con una prueba de concurrencia real (dos solicitudes simultáneas sobre el mismo campo/franja) que solo una prospera; la restricción de base de datos definida en el Documento 2 (Anexo A) es la que debe impedir la segunda, no únicamente la lógica de la aplicación.
- HU-D4 debe demostrarse idempotente: recibir el mismo `transaccion_externa_id` dos veces no debe generar una reserva duplicada ni un doble registro contable.

---

## Épica E — Contingencia de Pasarelas — Nivel 3 (Sprint 5)

Procesamiento excepcional ante caída **simultánea** de todos los proveedores de cobro electrónico (es decir, cuando el failover automático de la Épica D ya se intentó y falló). Es distinta de la resiliencia normal del sistema: aquí interviene una persona.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-E1** | Como Ciudadano, si la red QR está caída, quiero poder **subir la foto del comprobante de transferencia bancaria manual**, dejando la orden en estado `PENDIENTE_VERIFICACION` con un bloqueo extendido (plazo configurable, por defecto 2 horas). | Media | M |  |
| **HU-E2** | Como Admin Paramétricas, quiero un módulo web de revisión para **aprobar o rechazar los comprobantes de contingencia**, generando la(s) reserva(s) definitiva(s) o liberando las franjas si se rechaza, y notificando al ciudadano al teléfono registrado en la orden. | Media | M |  |

---

## Épica F — Operación en Sitio y Dashboard Gerencial (Sprint 6)

Control físico en cancha e información de toma de decisiones.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-F1** | Como Funcionario de Control, quiero acceder a la **pantalla de ocupación en tiempo real filtrada ÚNICAMENTE por mis campos asignados**, para verificar que las personas en cancha tengan reserva confirmada. | Media | M |  |
| **HU-F2** | Como Gerencia / Secretaría, quiero consultar un **Dashboard con gráficos de ingresos** por campo, período de fechas e histograma de horas pico de uso. | Media | L |  |
| **HU-F3** | Como Gerencia / Secretaría, quiero consultar el **reporte de clientes/asociaciones frecuentes**, agrupando primero por CI/NIT cuando esté disponible y, en su defecto, por teléfono, para conocer qué entidades le dan mayor uso a la infraestructura pública. | Media | M |  |
| **HU-F4** | Como Administrador/Gerencia, quiero ver un **mapa interactivo en tiempo real de todos los campos deportivos** con su estado (libre, en proceso de pago, ocupado), sin estar limitado por asignaciones de funcionario, para tener visión global de la operación. | Media | M |  |

---

## Épica G — Seguridad, Pruebas de Concurrencia y Publicación (Sprint 7)

Historias de estabilización previas a operar con dinero real y a distribuir la app públicamente.

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-G1** | Como Sistema, quiero **limitar la tasa de creación de órdenes de pago** por IP/dispositivo en la app móvil, para prevenir el bloqueo malicioso de horarios por bots o abuso, dado que la app no requiere cuenta. | Alta | M |  |
| **HU-G2** | Como Equipo de QA, quiero ejecutar **pruebas de concurrencia** sobre la creación de órdenes de pago (solicitudes simultáneas al mismo horario) y sobre la recepción duplicada de webhooks, validando las restricciones de base de datos definidas en el Documento 2 antes de producción. | Alta | M |  |
| **HU-G3** | Como Equipo de Desarrollo, quiero completar el checklist de publicación en **Google Play Store** (ficha, política de privacidad, firma de la app), para habilitar la distribución pública de la app móvil. | Alta | S |  |

---

## Matriz de Sprints y Plan de Trabajo

```
┌───────────────────────────────────────────────────────────────┐
│ Sprint 0: Épica 0  (Repositorios, Scaffolding, DDL)            │
├───────────────────────────────────────────────────────────────┤
│ Sprint 1: Épica A y Épica B  (Campos, Tarifas, Usuarios)       │
├───────────────────────────────────────────────────────────────┤
│ Sprint 2: Épica C  (App Móvil — Consulta y Mapa)               │
├───────────────────────────────────────────────────────────────┤
│ Sprint 3: Épica D — parte 1                                    │
│   (Selección multi-franja, orden de pago + detalle,            │
│    integración con proveedor principal + failover automático) │
├───────────────────────────────────────────────────────────────┤
│ Sprint 4: Épica D — parte 2                                    │
│   (Webhook idempotente, Active Polling, confirmación,          │
│    comprobante digital, consulta pública de estado)            │
├───────────────────────────────────────────────────────────────┤
│ Sprint 5: Épica E  (Contingencia Nivel 3 — modo manual)        │
├───────────────────────────────────────────────────────────────┤
│ Sprint 6: Épica F  (Control en sitio, Dashboard, Mapa global)  │
├───────────────────────────────────────────────────────────────┤
│ Sprint 7: Épica G  (Seguridad, Concurrencia, Google Play)      │
└───────────────────────────────────────────────────────────────┘
```
