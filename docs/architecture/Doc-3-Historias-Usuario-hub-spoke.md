# Documento 3: Historias de Usuario (Product Backlog)

**Sistema de Administración del Alquiler de Campos Deportivos**

**Gobierno Autónomo Departamental del Beni — Secretaría Departamental de Deportes**

*Versión 3 — revisada para la arquitectura Hub & Spoke. Reemplaza a la
Versión 2. La Épica D se reescribe de fondo (ya no hay Circuit Breaker ni
selección entre proveedores) y la **Épica E desaparece por completo** — la
contingencia de pagos es responsabilidad exclusiva del Core de
Recaudaciones. Consecuencia directa y positiva: el backlog completo pasa de
**8 sprints a 7**.*

---

## Definición de Criterios

### Prioridad

* **Prioridad 0**: Requisito técnico o infraestructura base (bloqueante).
* **Prioridad Alta**: Funcionalidad principal del negocio.
* **Prioridad Media**: Operatividad avanzada y reportes.

### Estimación Relativa

* **S (Small)**: Tarea sencilla, CRUD estándar o ajuste de UI.
* **M (Medium)**: Lógica de negocio moderada, validaciones o componentes complejos.
* **L (Large)**: Integración externa, lógica transaccional sensible o componentes móviles complejos.

---

## Épica 0 — Preparación Técnica y Scaffolding (Sprint 0)

*Sin cambios de fondo respecto a la v2 — nunca dependió de la arquitectura
de pagos.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-01** | Como equipo de desarrollo, quiero la estructura base del repositorio del **Backend de Canchas (Laravel API)**, con autenticación Sanctum configurada, para exponer los endpoints iniciales. | 0 | S |  |
| **HU-02** | Como equipo de desarrollo, quiero la estructura base de la **App Web Administrativa (React + Vite + TS)** conectada al backend de Canchas, para iniciar el desarrollo del backoffice. | 0 | S |  |
| **HU-03** | Como equipo de desarrollo, quiero la estructura base de la **App Móvil (Flutter)** configurada para Android/iOS, para construir las pantallas de consulta pública. | 0 | M |  |
| **HU-04** | Como equipo de desarrollo, quiero las **migraciones de las tablas definidas en el diccionario de datos vigente** escritas y versionadas, junto con sus restricciones de integridad, para desplegar el modelo de base de datos en cualquier entorno con un solo comando. | 0 | M |  |
| **HU-05** | Como equipo de desarrollo, quiero un **Worker / Scheduler (Job)** que marque como `EXPIRADA` toda `solicitud_reserva` cuyo `expira_en` haya vencido sin confirmación del Core de Recaudaciones, para liberar automáticamente todas las franjas incluidas. | 0 | M |  |

---

## Épica A — Gestión de Paramétricas y Campos Deportivos (Sprint 1)

*Sin cambios respecto a la v2.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-A1** | Como Admin Paramétricas, quiero registrar y listar los **tipos de campos**, para categorizar la oferta de escenarios. | Alta | S |  |
| **HU-A2** | Como Admin Paramétricas, quiero dar de alta un **campo deportivo** con código, nombre, dirección, coordenadas y horario de atención por día de la semana, para habilitarlo en la oferta pública. | Alta | M |  |
| **HU-A3** | Como Admin Paramétricas, quiero **actualizar la tarifa por hora** de un campo, cerrando la vigencia de la tarifa previa y creando una nueva, para preservar el historial de cobros antiguos. | Alta | M |  |
| **HU-A4** | Como Admin Paramétricas, quiero cambiar el estado de un campo a `mantenimiento` o `inactivo`, para suspender temporalmente la generación de reservas. | Alta | S |  |

---

## Épica B — Usuarios, Roles y Asignación de Control (Sprint 1)

*Sin cambios de fondo. HU-B2 se construye antes en la práctica —en el
Módulo 0.8 del roadmap, junto con la base de UI—, pero como historia de
negocio sigue perteneciendo a esta épica.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-B1** | Como Administrador, quiero crear funcionarios asignándoles roles, para controlar el acceso al panel administrativo. | Alta | S |  |
| **HU-B2** | Como Funcionario, quiero **iniciar sesión** con usuario y contraseña, para acceder a las pantallas correspondientes a mi rol. | Alta | M |  |
| **HU-B3** | Como Administrador, quiero **asignar escenarios deportivos específicos** a un Funcionario de Control, para delimitar las canchas que supervisará en sitio. | Alta | M |  |

---

## Épica C — Consulta Pública en App Móvil (Sprint 2)

*Sin cambios — nunca tuvo relación con el procesamiento de pagos.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-C1** | Como Ciudadano, quiero ver el **listado y mapa interactivo** de campos deportivos en la app móvil, sin requerir inicio de sesión, para ubicar las canchas disponibles. | Alta | M |  |
| **HU-C2** | Como Ciudadano, quiero seleccionar un campo y una fecha para **ver la grilla horaria disponible**, identificando qué franjas están libres, ocupadas o bloqueadas temporalmente por una solicitud en proceso. | Alta | M |  |

---

## Épica D — Reserva y Solicitud de Cobro (Sprints 3 y 4)

*Reescrita de fondo. Antes se llamaba "Reserva y Pago QR" — el nombre
cambia porque Canchas ya no genera ni gestiona el cobro: lo solicita al
Core de Recaudaciones y muestra lo que el Core le devuelve. Desaparece toda
la complejidad de Circuit Breaker y selección entre proveedores; HU-D3 baja
de estimación L a M como consecuencia directa de esa simplificación.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-D1** | Como Ciudadano, quiero seleccionar **una o varias franjas horarias libres** (incluso en distintas fechas, para casos como un campeonato de varios días) e ingresar mi nombre, teléfono y, opcionalmente, CI/NIT, para solicitar el cobro correspondiente por el monto total. | Alta | M |  |
| **HU-D2** | Como Sistema, quiero validar mediante una **restricción a nivel de base de datos** que ninguna de las franjas solicitadas esté ya ocupada por otra solicitud activa, y generar una `solicitud_reserva` con su detalle de franjas asociado, bloqueando provisionalmente todas las franjas incluidas. | Alta | L |  |
| **HU-D3** | Como Sistema, quiero **solicitarle el cobro al Core de Recaudaciones** mediante `RecaudacionesApiClient`, guardando la referencia que el Core devuelve (`referencia_recaudaciones`), para poder relacionar su confirmación con esta solicitud más adelante. | Alta | M |  |
| **HU-D4** | Como Sistema, quiero **recibir el webhook de confirmación del Core de Recaudaciones** (verificando su firma, y de forma idempotente ante reenvíos duplicados), actualizar el estado de la solicitud a `CONFIRMADA` y **crear automáticamente un registro en `reservas` por cada franja incluida**. | Alta | L |  |
| **HU-D5** | Como Sistema, quiero **(opcionalmente)** ejecutar consulta activa contra la API del Core mientras la solicitud esté vigente, para confirmar el pago aunque el webhook del Core falle o se demore. | Media | M |  |
| **HU-D6** | Como Ciudadano, quiero ver la confirmación inmediata en la App Móvil con mi **comprobante digital** (código de reserva por cada franja pagada) una vez que el Core confirma el pago. | Alta | S |  |
| **HU-D7** | Como Ciudadano, quiero **consultar el estado de mi solicitud** ingresando mi código de seguimiento, para saber si fue confirmada, rechazada o expiró — incluso si cerré la app o volví horas más tarde. | Alta | S |  |
| **HU-D8** | Como Ciudadano, quiero recibir un **mensaje claro** si el sistema de cobro no está disponible en este momento, para entender que debo intentar más tarde y no pensar que la app está fallando. | Media | S |  |

**Criterios de aceptación clave** (por su sensibilidad transaccional):
- HU-D2 debe demostrarse con una prueba de concurrencia real (dos
  solicitudes simultáneas sobre el mismo campo/franja) que solo una
  prospera; la restricción `EXCLUDE` de base de datos (Documento 2 v3,
  Anexo A.1) es la que debe impedir la segunda.
- HU-D4 debe demostrarse idempotente: recibir la misma confirmación del
  Core dos veces no debe generar una reserva duplicada. La garantía no es
  una tabla nueva —es la restricción `UNIQUE` sobre
  `reservas.solicitud_reserva_detalle_id`, que ya existe por otro motivo
  (Documento 2 v3, Anexo A.2)—, así que la prueba debe confirmar
  específicamente que esa restricción, y no solo la lógica de aplicación,
  es la que rechaza el duplicado.
- HU-D3 debe demostrarse con manejo explícito de error de red/timeout hacia
  el Core (no solo el camino feliz), dado que ya no hay un segundo
  proveedor al cual recurrir automáticamente si el primero falla.

---

## Épica E — ~~Contingencia de Pasarelas (Nivel 3)~~ *(eliminada)*

Esta épica completa **desaparece del backlog de Canchas**. Las dos
historias que contenía (HU-E1: subir comprobante manual; HU-E2: aprobación
administrativa) describían un flujo que ahora es responsabilidad exclusiva
del Core de Recaudaciones — si el Core no puede cobrar por sus vías
normales, cómo lo resuelve internamente (transferencia manual, lo que sea)
no es un problema que Canchas necesite construir ni operar. Lo único que le
queda a Canchas de este escenario es HU-D8, en la épica anterior: informar
con claridad que el cobro no está disponible en este momento.

---

## Épica F — Operación en Sitio y Dashboard Gerencial (Sprint 5)

*HU-F1 y HU-F4 sin cambios. HU-F2 y HU-F3 llevan una nota nueva.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-F1** | Como Funcionario de Control, quiero acceder a la **pantalla de ocupación en tiempo real filtrada ÚNICAMENTE por mis campos asignados**, para verificar que las personas en cancha tengan reserva confirmada. | Media | M |  |
| **HU-F2** | Como Gerencia / Secretaría, quiero consultar un **Dashboard con gráficos de ingresos** por campo y período de fechas, calculados sobre las solicitudes confirmadas por Canchas, para conocer la demanda y facturación operativa de la infraestructura deportiva. | Media | L |  |
| **HU-F3** | Como Gerencia / Secretaría, quiero consultar el **reporte de clientes/asociaciones frecuentes**, agrupando primero por CI/NIT cuando esté disponible y, en su defecto, por teléfono, para conocer qué entidades le dan mayor uso a la infraestructura pública. | Media | M |  |
| **HU-F4** | Como Administrador/Gerencia, quiero ver un **mapa interactivo en tiempo real de todos los campos deportivos** con su estado, sin estar limitado por asignaciones de funcionario, para tener visión global de la operación. | Media | M |  |

> **Nota sobre HU-F2 y HU-F3:** ambas dependen de datos que Canchas ya
> calcula por su cuenta (`monto_total`, `ci_nit_pagador`/`telefono_pagador`),
> así que pueden construirse sin esperar nada del Core. Queda pendiente de
> alinear con ese equipo (Documento 1 v3, puntos abiertos 5 y 6) si estas
> cifras deben reconciliarse contra el panel financiero del Core, o si
> ambos reportes conviven con propósitos distintos (operativo vs.
> financiero) sin que eso sea un problema.

---

## Épica G — Seguridad, Pruebas de Concurrencia y Publicación (Sprint 6)

*HU-G1 y HU-G3 sin cambios. HU-G2 se actualiza para probar la nueva
restricción de idempotencia.*

| ID | Historia de Usuario | Prioridad | Estim. | Responsable |
| --- | --- | --- | --- | --- |
| **HU-G1** | Como Sistema, quiero **limitar la tasa de creación de solicitudes de reserva** por IP/dispositivo en la app móvil, para prevenir el bloqueo malicioso de horarios por bots o abuso. | Alta | M |  |
| **HU-G2** | Como Equipo de QA, quiero ejecutar **pruebas de concurrencia** sobre la creación de solicitudes de reserva (solicitudes simultáneas al mismo horario) y sobre la recepción duplicada del webhook del Core, validando que la restricción `UNIQUE` de `reservas.solicitud_reserva_detalle_id` —no solo la lógica de aplicación— es la que rechaza la reserva duplicada. | Alta | M |  |
| **HU-G3** | Como Equipo de Desarrollo, quiero completar el checklist de publicación en **Google Play Store** (ficha, política de privacidad, firma de la app), para habilitar la distribución pública de la app móvil. | Alta | S |  |

> **Nota sobre HU-G3:** la política de privacidad de Canchas se simplifica
> respecto a la versión anterior — puede declarar explícitamente que el
> procesamiento del pago en sí (datos bancarios, medios de pago) ocurre
> íntegramente en el Core de Recaudaciones, no en la app de Canchas, lo cual
> reduce la superficie de datos sensibles que la app misma maneja.

---

## Matriz de Sprints y Plan de Trabajo

*Pasa de 8 sprints a 7 — la Épica E desaparece sin reemplazo, y ningún otro
sprint se alarga para compensarla.*

```
┌───────────────────────────────────────────────────────────────┐
│ Sprint 0: Épica 0  (Repositorios, Scaffolding, DDL)            │
├───────────────────────────────────────────────────────────────┤
│ Sprint 1: Épica A y Épica B  (Campos, Tarifas, Usuarios)       │
├───────────────────────────────────────────────────────────────┤
│ Sprint 2: Épica C  (App Móvil — Consulta y Mapa)               │
├───────────────────────────────────────────────────────────────┤
│ Sprint 3: Épica D — parte 1                                    │
│   (Selección multi-franja, solicitud de reserva + detalle,     │
│    solicitud de cobro al Core de Recaudaciones)                │
├───────────────────────────────────────────────────────────────┤
│ Sprint 4: Épica D — parte 2                                    │
│   (Webhook idempotente, polling opcional, confirmación,        │
│    comprobante digital, consulta pública de estado)            │
├───────────────────────────────────────────────────────────────┤
│ Sprint 5: Épica F  (Control en sitio, Dashboard, Mapa global)  │
├───────────────────────────────────────────────────────────────┤
│ Sprint 6: Épica G  (Seguridad, Concurrencia, Google Play)      │
└───────────────────────────────────────────────────────────────┘
```

> **Nota de secuenciación:** HU-B2 (login) se construye en la práctica antes
> de lo que esta matriz sugiere —en el Módulo 0.8 del roadmap de
> implementación, entre el setup y la base de datos—, porque el panel
> administrativo necesita autenticación funcional antes de tener pantallas
> de negocio que proteger. Esta matriz refleja la organización del backlog
> por épica de negocio; el orden de construcción real puede consultarse en
> el roadmap.
