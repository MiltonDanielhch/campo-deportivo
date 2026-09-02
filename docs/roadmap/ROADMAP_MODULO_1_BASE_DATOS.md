# 📁 ROADMAP_MODULO_1_BASE_DATOS.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.1.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 4–6 horas (antes 5–7 — la Fase 1.1 ya está resuelta) · **Bloquea:** Módulos 2 al 8

> **Objetivo del Módulo:** Traducir el modelo de datos de Canchas —ya
> reducido a lo que un satélite realmente necesita— en migraciones reales de
> PostgreSQL: **12 tablas** (antes 16), la restricción crítica anti-doble-
> reserva, y los modelos Eloquent y datos semilla correspondientes.

> **Punto de partida — Módulo 0.8 ya resuelto:** este módulo asume que el
> Módulo 0.8 (UI base y login) ya se ejecutó. Eso significa que las
> extensiones de PostgreSQL, las tablas `roles` y `funcionarios`, sus
> modelos Eloquent y el `RolesSeeder` **ya existen** — se marcan como
> resueltos donde corresponde en vez de repetirse. El trabajo real de este
> módulo arranca en la Fase 1.2.

> **Qué desapareció y por qué:** `proveedores_pasarela`, `pagos_confirmados`,
> `pagos_contingencia` e `intentos_pasarela` ya no existen en esta base de
> datos. Esas cuatro tablas modelaban una responsabilidad —hablar con bancos,
> conciliar, aprobar comprobantes manuales— que el ADR-003 (Módulo 0) movió
> por completo al Core de Recaudaciones. La base de datos de Canchas no
> almacena un solo dato financiero: solo sabe que le pidió un cobro al Core,
> con qué referencia, y si el Core ya le confirmó que se pagó o no.

> **Una decisión que queda marcada como pendiente de alinear con el equipo
> del Core:** este módulo asume que Canchas sigue capturando nombre,
> teléfono y CI/NIT del solicitante al momento de reservar —igual que antes—,
> aunque el Core "registra clientes" por su cuenta. La razón: Canchas
> necesita esos datos para su propio uso operativo (contactar al ciudadano,
> reportar qué clientes usan más una cancha), independientemente de que el
> Core mantenga su propio registro para fines de facturación. Si el equipo
> del Core prefiere ser la única fuente de verdad de identidad del
> ciudadano, esta parte del modelo se ajusta.

---

## 🗺️ Mapa del Módulo

```
Módulo 1
├── Fase 1.1 → Extensiones de PostgreSQL + Usuarios y Control de Acceso  [x] (Módulo 0.8)
├── Fase 1.2 → Catálogos y Paramétricas (campos, tarifas, horarios)
├── Fase 1.3 → Núcleo de Reservas + restricción anti-doble-reserva
├── Fase 1.4 → Configuración del Sistema y Auditoría
├── Fase 1.5 → Modelos Eloquent y Relaciones (Rol y Funcionario ya existen)
├── Fase 1.6 → Seeders de Datos Base e Índices de Rendimiento
└── Fase 1.7 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente` (Fase 1.1 `[x]` — resuelta en el Módulo 0.8)

---

# FASE 1.1 — Extensiones de PostgreSQL y Usuarios/Control de Acceso

## ✅ Ya resuelta — ejecutada por adelantado en el Módulo 0.8 (Fase 0.8.2)

Esta fase no se repite. El Módulo 0.8 ya creó, en este orden:

- Las extensiones `pgcrypto` y `btree_gist`.
- La tabla `roles` (id, nombre, descripcion, permisos).
- La tabla `funcionarios` (id, nombre_completo, ci, usuario, password_hash,
  rol_id, estado, creado_en).
- Los modelos Eloquent `Rol` y `Funcionario` (con `HasUuids`,
  `Authenticatable` + `HasApiTokens`, `getAuthPassword()` apuntando a
  `password_hash`, y la relación `Funcionario belongsTo(Rol)`).
- El `RolesSeeder` (3 roles: admin_parametricas, funcionario_control,
  gerencia).

Lo único que queda pendiente de esa fase, si no se hizo ya, es la migración
de índices de rendimiento sobre `asignaciones_funcionario` — pero esa tabla
todavía no existe en este punto (se crea en la Fase 1.2), así que ese índice
puntual se deja para la Fase 1.6, sin cambios respecto al plan original.

---

## Tareas de verificación de la Fase 1.1

```
[x] Confirmar que las extensiones existen
    → \dx en psql debe listar pgcrypto y btree_gist.

[x] Confirmar que roles y funcionarios existen con la estructura esperada
    → \d roles y \d funcionarios en psql.

[x] Confirmar que los modelos Rol y Funcionario existen y funcionan
    → php artisan tinker → Funcionario::with('rol')->first() no debe
      lanzar error si ya hay datos sembrados.

[x] Confirmar que RolesSeeder está registrado en DatabaseSeeder
    → Se reutiliza tal cual en la Fase 1.6, sin volver a crearlo.

[x] No se requiere commit nuevo — esta fase ya fue commiteada como parte
    del cierre del Módulo 0.8.
```

---

# FASE 1.2 — Catálogos y Paramétricas

Sin cambios respecto a la versión anterior de este módulo. Todo lo que el
Administrador de Paramétricas gestiona —tipos de campo, campos físicos,
horarios por día de la semana, historial de tarifas, asignación de
funcionarios— es responsabilidad exclusiva de Canchas y no tiene ninguna
relación con el Core de Recaudaciones, salvo que `tarifas_campo.precio_por_hora`
es el dato que Canchas usará para decirle al Core cuánto cobrar.

`tarifas_campo.creado_por` y `asignaciones_funcionario.funcionario_id`
dependen de `funcionarios`, que ya existe desde el Módulo 0.8 — esta fase
puede ejecutarse sin ningún bloqueo de dependencias.

---

## Tareas de la Fase 1.2

```
[x] Crear la migración de tipos_campo
    → id (uuid, PK), nombre (varchar(100), único), descripcion (text,
      nullable), estado (enum: activo/inactivo).

[x] Crear la migración de campos_deportivos
    → id (uuid, PK), tipo_campo_id (FK → tipos_campo.id), codigo
      (varchar(30), único), nombre (varchar(150)), direccion (text),
      latitud (numeric(10,8)), longitud (numeric(11,8)), estado (enum:
      activo/mantenimiento/inactivo), creado_en (timestamptz).

[x] Crear la migración de horarios_atencion
    → id (uuid, PK), campo_id (FK → campos_deportivos.id), dia_semana
      (smallint, 0=Domingo…6=Sábado), hora_apertura (time), hora_cierre
      (time). Restricción: UNIQUE (campo_id, dia_semana).

[x] Crear la migración de tarifas_campo
    → id (uuid, PK), campo_id (FK → campos_deportivos.id),
      precio_por_hora (numeric(12,2)), vigente_desde (timestamptz),
      vigente_hasta (timestamptz, nullable — NULL = tarifa activa),
      creado_por (FK → funcionarios.id).

[x] Crear la migración de asignaciones_funcionario
    → id (uuid, PK), funcionario_id (FK → funcionarios.id), campo_id
      (FK → campos_deportivos.id), asignado_en (timestamptz).

[x] Ejecutar las migraciones y verificar
    → php artisan migrate.

[x] Commit de la fase
    → Mensaje: "feat(db): catálogos y paramétricas de campos deportivos"
```

---

# FASE 1.3 — Núcleo de Reservas y Restricción Anti-Doble-Reserva

## Lo que cambió y lo que no, respecto a la arquitectura anterior

Lo que **no** cambió: la técnica. El tipo `tstzrange`, la columna generada,
el trigger de sincronización, la restricción `EXCLUDE USING gist` — es
exactamente el mismo mecanismo de siempre, porque resuelve exactamente el
mismo problema (dos personas reservando la misma cancha a la misma hora),
que nunca tuvo nada que ver con cómo se procesaba el pago.

Lo que **sí** cambió: qué representa la tabla cabecera. Ya no se llama
`ordenes_pago` sino **`solicitudes_reserva`**, y modela algo más simple:
*"un ciudadano quiere estas franjas, le pedí al Core de Recaudaciones que
genere un cobro por el monto correspondiente, y esta es la referencia que el
Core me devolvió para poder enterarme cuándo confirme el pago."* Canchas ya
no necesita saber ni le importa si ese cobro se resolvió con un QR, una
transferencia manual, o cualquier otro medio — eso es enteramente interno al
Core.

## Máquina de estados simplificada

```
                    ┌────────────────────────────┐
                    │ solicitud_reserva: PENDIENTE │
                    └──────────────┬──────────────┘
                                   │
      ┌───────────────┬───────────┼───────────────┬──────────────┐
      │ (Timeout)      │ (Core     │ (Core informa │ (Ciudadano   │
      │                │  confirma)│  rechazo)      │  cancela)    │
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

Desaparecieron `procesando` y `pendiente_verificacion` — existían
específicamente para modelar el Circuit Breaker entre proveedores y el flujo
de contingencia manual, ninguno de los cuales vive ya en Canchas.

---

## Tareas de la Fase 1.3

```
[x] Crear la migración de solicitudes_reserva
    → id (uuid, PK), codigo_seguimiento (varchar(40), único — el código
      público que el ciudadano usa para consultar su estado), monto_total
      (numeric(12,2)), nombre_pagador (varchar(150)), telefono_pagador
      (varchar(20)), ci_nit_pagador (varchar(20), nullable),
      referencia_recaudaciones (varchar(100), nullable, único — el ID
      que el Core de Recaudaciones devuelve al generar el cobro), estado
      (enum: pendiente/confirmada/expirada/cancelada/rechazada, default
      'pendiente'), creado_en (timestamptz), expira_en (timestamptz).

[x] Crear la migración de solicitud_reserva_detalle
    → Columnas estándar: id (uuid, PK), solicitud_reserva_id (FK →
      solicitudes_reserva.id), campo_id (FK → campos_deportivos.id),
      fecha_reserva (date), hora_inicio (time), hora_fin (time),
      tarifa_aplicada (numeric(12,2)).
    → La columna generada y la denormalizada, vía DB::statement()
      después del Schema::create():

        DB::statement("
          ALTER TABLE solicitud_reserva_detalle
          ADD COLUMN rango_horario tstzrange
          GENERATED ALWAYS AS (
            tstzrange(
              (fecha_reserva + hora_inicio) AT TIME ZONE 'America/La_Paz',
              (fecha_reserva + hora_fin) AT TIME ZONE 'America/La_Paz'
            )
          ) STORED
        ");

        DB::statement("
          ALTER TABLE solicitud_reserva_detalle
          ADD COLUMN estado_solicitud varchar(30) NOT NULL DEFAULT 'pendiente'
        ");

[x] Crear el trigger que sincroniza estado_solicitud
    → CREATE OR REPLACE FUNCTION sync_estado_solicitud() RETURNS trigger AS $$
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

[x] Crear la restricción de no-solape (EXCLUDE)
    → DB::statement("
        ALTER TABLE solicitud_reserva_detalle
        ADD CONSTRAINT no_solape_horario
        EXCLUDE USING gist (
          campo_id WITH =,
          rango_horario WITH &&
        )
        WHERE (estado_solicitud IN ('pendiente','confirmada'))
      ");
    → La lista de estados "activos" se redujo a solo dos — ya no hace
      falta incluir 'procesando' ni 'pendiente_verificacion'.
    → Si esta migración falla con un error sobre "operator class
      uuid_ops does not exist for access method gist", confirmar que
      btree_gist (ya habilitada desde el Módulo 0.8) sigue activa en
      esta base de datos.

[x] Crear la migración de reservas
    → id (uuid, PK), solicitud_reserva_id (FK → solicitudes_reserva.id),
      solicitud_reserva_detalle_id (FK → solicitud_reserva_detalle.id,
      único), codigo_reserva (varchar(20), único), campo_id (FK →
      campos_deportivos.id, denormalizado), fecha_reserva (date,
      denormalizado), hora_inicio (time, denormalizado), hora_fin (time,
      denormalizado), monto_pagado (numeric(12,2)), confirmado_en
      (timestamptz).

[x] Ejecutar todas las migraciones de la fase, en orden
    → php artisan migrate.
    → Verificar con \d solicitud_reserva_detalle en psql que aparecen la
      columna generada rango_horario y la restricción no_solape_horario.

[x] Prueba manual de la restricción
    → Insertar dos filas de solicitud_reserva_detalle para el mismo
      campo_id con horarios que se crucen, ambas con estado_solicitud =
      'pendiente'. La segunda inserción DEBE fallar con "conflicting key
      value violates exclusion constraint".

[x] Commit de la fase
    → Mensaje: "feat(db): núcleo de reservas con restricción anti-doble-reserva"
```

---

# FASE 1.4 — Configuración del Sistema y Auditoría

Sin cambios respecto a la versión anterior. Dos tablas, ninguna con datos
financieros: `parametros_sistema` (configuración operativa, sin los
parámetros de Circuit Breaker ni de contingencia, que ya no aplican) y
`auditoria` (bitácora transversal).

---

## Tareas de la Fase 1.4

```
[x] Crear la migración de parametros_sistema
    → clave (varchar(100), PK), valor (varchar(255)), descripcion
      (text, nullable), actualizado_por (FK → funcionarios.id,
      nullable), actualizado_en (timestamptz).

[x] Crear la migración de auditoria
    → id (uuid, PK), tabla (varchar(50)), registro_id (varchar(100)),
      accion (varchar(50), texto libre), usuario_id (FK →
      funcionarios.id, nullable — NULL si la ejecuta un job
      automático), datos_anteriores (jsonb, nullable), datos_nuevos
      (jsonb, nullable), fecha (timestamptz).

[x] Ejecutar las migraciones y verificar
    → php artisan migrate.

[x] Commit de la fase
    → Mensaje: "feat(db): parámetros del sistema y bitácora de auditoría"
```

---

# FASE 1.5 — Modelos Eloquent y Relaciones

## Rol y Funcionario ya existen — esta fase crea los 10 restantes

Los modelos `Rol` y `Funcionario` se crearon en el Módulo 0.8 (Fase 0.8.2),
con `HasUuids`, `Authenticatable` + `HasApiTokens`, y `getAuthPassword()` ya
resuelto. **No se recrean aquí.** Esta fase construye los 10 modelos
restantes: `TipoCampo`, `CampoDeportivo`, `HorarioAtencion`, `TarifaCampo`,
`AsignacionFuncionario`, `SolicitudReserva`, `SolicitudReservaDetalle`,
`Reserva`, `ParametroSistema`, `Auditoria`.

Todas las tablas usan `uuid`, así que cada modelo nuevo necesita `HasUuids`,
`$incrementing = false` y `$keyType = 'string'` (excepto `ParametroSistema`,
cuya PK es la clave natural `clave`).

---

## Tareas de la Fase 1.5

```
[x] Crear los 10 modelos Eloquent restantes
    → TipoCampo / CampoDeportivo / HorarioAtencion / TarifaCampo /
      AsignacionFuncionario / SolicitudReserva / SolicitudReservaDetalle
      / Reserva / ParametroSistema / Auditoria.
    → 9 con HasUuids + $incrementing = false + $keyType = 'string'.
      ParametroSistema con $primaryKey = 'clave', sin autoincrement.

[x] Configurar $fillable y $casts en cada modelo
    → 'estado' en SolicitudReserva castea al Enum de PHP correspondiente
      (app/Enums/EstadoSolicitudReserva.php — pendiente, confirmada,
      expirada, cancelada, rechazada).
    → Campos monetarios a 'decimal:2'. Campos de tiempo a 'datetime'.
      permisos y datos_anteriores/datos_nuevos a 'array'.

[x] Definir las relaciones Eloquent entre modelos
    → CampoDeportivo: belongsTo(TipoCampo), hasMany(TarifaCampo),
      hasMany(HorarioAtencion), hasMany(AsignacionFuncionario).
    → Funcionario (ya existente): agregar hasMany(AsignacionFuncionario)
      — la relación belongsTo(Rol) ya se definió en el Módulo 0.8.
    → SolicitudReserva: hasMany(SolicitudReservaDetalle),
      hasMany(Reserva).
    → SolicitudReservaDetalle: belongsTo(SolicitudReserva),
      belongsTo(CampoDeportivo), hasOne(Reserva).
    → Reserva: belongsTo(SolicitudReserva),
      belongsTo(SolicitudReservaDetalle), belongsTo(CampoDeportivo).
    → Nótese la ausencia total de relaciones hacia proveedores de
      pasarela o pagos — ya no existen en este modelo.

[x] Escribir un test rápido de humo por modelo nuevo
    → Confirmar que el mapeo Eloquent↔PostgreSQL funciona para los 10
      modelos nuevos (los de Rol y Funcionario ya se probaron en el
      Módulo 0.8).

[x] Commit de la fase
    → Mensaje: "feat(db): modelos Eloquent y relaciones del dominio de reservas"
```

---

# FASE 1.6 — Seeders de Datos Base e Índices de Rendimiento

## RolesSeeder ya existe

El `RolesSeeder` (3 roles) se creó y se registró en `DatabaseSeeder` desde el
Módulo 0.8. Esta fase solo agrega `ParametrosSistemaSeeder`, lo registra
junto al ya existente, y crea los índices de rendimiento.

---

## Tareas de la Fase 1.6

```
[x] Crear ParametrosSistemaSeeder (reducido)
    → solicitud_reserva_expiracion_minutos = 15 (cuánto tiempo Canchas
      mantiene bloqueada una franja mientras espera la confirmación del
      Core antes de liberarla).
    → polling_intervalo_segundos = 20 (si el mecanismo elegido para
      enterarse de la confirmación del Core incluye consulta activa
      además de webhook — se define en el módulo de reserva y pago).
    → Ya NO se siembran circuit_breaker_umbral_fallos,
      circuit_breaker_cooldown_segundos, pasarela_timeout_segundos ni
      contingencia_plazo_horas — ninguno aplica a este sistema.

[x] Registrar ParametrosSistemaSeeder en DatabaseSeeder
    → Se agrega junto al RolesSeeder ya existente, sin duplicarlo. Sin
      ProveedoresPasarelaSeeder — esa tabla no existe.

[x] Crear la migración de índices de rendimiento
    → Expiración de solicitudes pendientes: sobre
      solicitudes_reserva(estado, expira_en) WHERE estado = 'pendiente'.
    → Reservas por campo/fecha: reservas(campo_id, fecha_reserva).
    → Disponibilidad pública: solicitud_reserva_detalle(campo_id,
      fecha_reserva) WHERE estado_solicitud IN ('pendiente','confirmada').
    → Mapa: campos_deportivos(latitud, longitud).
    → Asignaciones: asignaciones_funcionario(funcionario_id) — el
      pendiente que había quedado abierto desde la Fase 1.1.
    → Clientes frecuentes: solicitudes_reserva(ci_nit_pagador,
      telefono_pagador).
    → Ya NO existe el índice sobre intentos_pasarela — esa tabla no existe.

[x] Ejecutar seeders y verificar
    → php artisan db:seed.
    → SELECT * FROM roles; → 3 filas (ya sembradas desde el Módulo 0.8,
      se re-verifican aquí como parte del flujo completo).
    → SELECT * FROM parametros_sistema; → 2 filas.

[x] Commit de la fase
    → Mensaje: "feat(db): parámetros del sistema e índices de rendimiento"
```

---

# FASE 1.7 — Smoke Test Final y Commit de Cierre

---

## Checklist de cierre del Módulo 1

```
[x] php artisan migrate:fresh --seed corre de principio a fin sin errores
    — incluye de nuevo las migraciones del Módulo 0.8, ya que
    migrate:fresh reconstruye la base completa desde cero.

[x] Existen exactamente 12 tablas en PostgreSQL (verificable con \dt),
    ninguna llamada proveedores_pasarela, pagos_confirmados,
    pagos_contingencia ni intentos_pasarela.

[x] La restricción no_solape_horario sigue rechazando dos franjas
    superpuestas del mismo campo en estado 'pendiente' o 'confirmada'.

[x] referencia_recaudaciones en solicitudes_reserva tiene restricción
    UNIQUE, verificable insertando dos filas con la misma referencia y
    confirmando que la segunda falla.

[x] roles y parametros_sistema quedan poblados correctamente tras
    db:seed, con la lista reducida de parámetros.

[x] El login construido en el Módulo 0.8 sigue funcionando de punta a
    punta después de correr migrate:fresh --seed — confirma que ningún
    cambio de este módulo rompió lo ya construido.

[x] El test de humo de los 10 modelos nuevos pasa en verde.

[x] El pipeline de CI de backend (Módulo 0) pasa en verde sobre un Pull
    Request con todas las migraciones de este módulo.

[x] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 1 - base de datos reducida del satélite de Canchas"
    → Tag sugerido: v0.2.0-base-datos-hub-spoke
```

> **Siguiente módulo:** con el modelo de datos completo y verificado, el
> Módulo 2 (Paramétricas y Usuarios) se ajusta para marcar su antigua Fase
> 2.1 (Autenticación) como ya resuelta —construida en el Módulo 0.8— y
> arrancar directamente en la gestión de campos y tarifas. El cambio grande
> sigue viniendo después, en el módulo que reemplaza a la antigua Épica D:
> ahí se construye `RecaudacionesApiClient` en la práctica, el webhook que
> recibe la confirmación del Core, y desaparece por completo la lógica de
> Circuit Breaker que ocupó buena parte del roadmap original.
