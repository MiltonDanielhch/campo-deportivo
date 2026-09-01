# 📁 ROADMAP_MODULO_2_PARAMETRICAS_USUARIOS.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 6–8 horas (antes 8–10 — dos fases ya están resueltas) · **Bloquea:** Módulos 3 al 8

> **Objetivo del Módulo:** Completar la Épica A y la Épica B del Documento 3
> v3: que un Administrador de Paramétricas pueda dar de alta campos
> deportivos con sus horarios, fijar y actualizar tarifas conservando el
> historial, crear funcionarios y asignarles canchas — todo desde el panel
> real. El login y la base de UI **ya existen**, construidos por adelantado
> en el Módulo 0.8; este módulo construye la gestión de negocio sobre esa
> base, no la autenticación en sí.

> **Punto de partida:** las Fases 2.1 y 2.5 de la versión original de este
> módulo (autenticación backend, y login + layout en el panel web) ya están
> resueltas — se ejecutaron por adelantado en el Módulo 0.8. Se mantienen
> en el mapa de este documento, marcadas como ya hechas, para no perder la
> numeración de referencia ni la trazabilidad con el resto del roadmap.

> **Sobre Hub & Spoke:** este módulo no tiene ningún punto de contacto con
> el Core de Recaudaciones. La Épica A y la Épica B del Documento 3 v3
> están marcadas "sin cambios" frente a la arquitectura anterior — ni los
> campos deportivos, ni las tarifas, ni los funcionarios tuvieron nunca
> relación con cómo se procesa un pago.

---

## 🗺️ Mapa del Módulo

```
Módulo 2
├── Fase 2.1 → Autenticación y control de acceso por rol         [x] (Módulo 0.8)
├── Fase 2.2 → Paramétricas: tipos de campo, campos deportivos y horarios
├── Fase 2.3 → Versionado de tarifas + bitácora de auditoría reutilizable
├── Fase 2.4 → Usuarios y asignación de control
├── Fase 2.5 → Panel Web: login, sesión y navegación por rol      [x] (Módulo 0.8)
├── Fase 2.6 → Panel Web: pantallas de Paramétricas
├── Fase 2.7 → Panel Web: pantallas de Usuarios y Asignaciones
└── Fase 2.8 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente` (Fases 2.1 y 2.5 `[x]` — resueltas en el Módulo 0.8)

---

# FASE 2.1 — Autenticación y Control de Acceso por Rol

## ✅ Ya resuelta — ejecutada por adelantado en el Módulo 0.8 (Fase 0.8.2)

No se repite. El Módulo 0.8 ya construyó:

- ADR-004 (tokens Bearer de Sanctum sobre cookies de sesión SPA).
- `AuthService` y `AuthController`: `POST /api/v1/auth/login`,
  `GET /api/v1/auth/me`, `POST /api/v1/auth/logout`.
- El middleware de rol (`role:...`) y su registro en `bootstrap/app.php`.
- Tests de login correcto, login con funcionario inactivo, y acceso sin
  token.

---

## Tareas de verificación de la Fase 2.1

```
[x] Confirmar que POST /api/v1/auth/login, GET /api/v1/auth/me y
    POST /api/v1/auth/logout responden correctamente contra el
    funcionario sembrado en el Módulo 0.8.

[x] Confirmar que el middleware 'role' está disponible para usarse en las
    rutas nuevas que este módulo va a crear (Fases 2.2 a 2.4).

[x] No se requiere commit nuevo — ya fue commiteado como parte del cierre
    del Módulo 0.8.
```

---

# FASE 2.2 — Paramétricas: Tipos de Campo, Campos Deportivos y Horarios

Implementa HU-A1, HU-A2 y HU-A4 del Documento 3 v3. Todo bajo
`role:admin_parametricas`. Sin cambios respecto a la lógica de negocio de
la versión anterior de este módulo — esta fase nunca tuvo relación con
pagos.

---

## Tareas de la Fase 2.2

```
[x] Crear TipoCampoService y TipoCampoController
    → CRUD estándar: listar, crear, actualizar, e inhabilitar (cambiar
      estado a 'inactivo' en vez de eliminar físicamente).
    → Rutas: GET/POST /api/v1/tipos-campo, PUT /api/v1/tipos-campo/{id}.

[ ] Crear CampoDeportivoService y CampoDeportivoController
    → store(): crea el campo y sus horarios_atencion en una única
      transacción — si la creación de cualquiera de los 7 horarios
      falla, el campo tampoco debe quedar creado. Recibe un DTO
      (CrearCampoDTO) con los datos del campo y un arreglo de horarios.
    → index(): listado con filtros opcionales por tipo_campo_id y estado.
    → update(): edición de datos generales del campo — NO del estado,
      que tiene su propio endpoint.
    → cambiarEstado(): PATCH /api/v1/campos-deportivos/{id}/estado,
      recibe el nuevo estado (activo/mantenimiento/inactivo) y registra
      el cambio en auditoria (ver Fase 2.3) — implementa HU-A4.

[ ] Crear el FormRequest de campo deportivo
    → Código único, coordenadas dentro de rangos geográficos válidos, y
      que la lista de horarios no tenga dos entradas para el mismo día.

[ ] Crear el DTO CrearCampoDTO
    → app/DTOs/CrearCampoDTO.php — datos del campo más un arreglo
      tipado de franjas de horario.

[ ] Tests
    → Crear un campo con 7 horarios queda con las 7 filas en
      horarios_atencion.
    → Crear un campo con código duplicado falla con 422.
    → Crear un campo con dos horarios para el mismo día falla con 422.
    → Cambiar el estado a 'mantenimiento' se refleja correctamente
      (se verifica la auditoría en la Fase 2.3).

[ ] Commit de la fase
    → Mensaje: "feat(parametricas): CRUD de tipos de campo, campos deportivos y horarios"
```

---

# FASE 2.3 — Versionado de Tarifas y Auditoría Reutilizable

Implementa HU-A3. Sin cambios respecto a la versión anterior — la
transacción con `lockForUpdate()` y el índice único parcial
`uq_tarifa_activa` son exactamente el mismo mecanismo, y siguen siendo
igual de necesarios: la garantía de "nunca dos tarifas activas para el
mismo campo" nunca dependió de cómo se procesa un pago.

```php
DB::transaction(function () use ($campo, $nuevoPrecio, $funcionario) {
    $tarifaAnterior = TarifaCampo::where('campo_id', $campo->id)
        ->whereNull('vigente_hasta')
        ->lockForUpdate()
        ->first();

    if ($tarifaAnterior) {
        $tarifaAnterior->update(['vigente_hasta' => now()]);
    }

    return TarifaCampo::create([
        'campo_id'        => $campo->id,
        'precio_por_hora' => $nuevoPrecio,
        'vigente_desde'   => now(),
        'vigente_hasta'   => null,
        'creado_por'      => $funcionario->id,
    ]);
});
```

```sql
CREATE UNIQUE INDEX uq_tarifa_activa
ON tarifas_campo (campo_id)
WHERE vigente_hasta IS NULL;
```

## El servicio de auditoría: se construye una vez, se reutiliza siempre

Esta sigue siendo la primera fase del proyecto (en su orden de
construcción real) que escribe en la tabla `auditoria`. El
`AuditoriaService` genérico que se construye aquí será reutilizado, más
adelante, por el módulo que reemplaza a la antigua Épica D —por ejemplo,
para registrar cada confirmación de pago que llega del Core de
Recaudaciones—, así que conviene mantenerlo genérico desde el día uno, sin
acoplarlo a ningún detalle específico de paramétricas.

---

## Tareas de la Fase 2.3

```
[ ] Crear la migración del índice único parcial
    → add_unique_active_tarifa_index — CREATE UNIQUE INDEX
      uq_tarifa_activa, vía DB::statement().

[ ] Crear el AuditoriaService genérico
    → app/Services/AuditoriaService.php con
      registrar(string $tabla, string $registroId, string $accion,
      ?string $usuarioId, ?array $datosAnteriores, ?array $datosNuevos).

[ ] Crear TarifaCampoService
    → actualizarTarifa(): el bloque de código de arriba completo,
      terminando con AuditoriaService::registrar().
    → historial(): tarifas de un campo ordenadas por vigente_desde
      descendente.

[ ] Actualizar CampoDeportivoService::cambiarEstado() (de la Fase 2.2)
    → Llamar a AuditoriaService::registrar() con
      tabla='campos_deportivos', accion='cambiar_estado'.

[ ] Crear TarifaCampoController
    → POST /api/v1/campos-deportivos/{id}/tarifas (nueva tarifa).
    → GET /api/v1/campos-deportivos/{id}/tarifas (historial).

[ ] Tests
    → Crear una segunda tarifa cierra automáticamente la anterior.
    → Insertar dos tarifas activas saltando el Service falla por el
      índice único parcial.
    → Cada cambio de tarifa y cada cambio de estado de campo generan
      una fila nueva en auditoria.

[ ] Commit de la fase
    → Mensaje: "feat(parametricas): versionado de tarifas con lock transaccional y auditoría reutilizable"
```

---

# FASE 2.4 — Usuarios y Asignación de Control

Implementa HU-B1 y HU-B3. Todo bajo `role:admin_parametricas`. Distinto de
la Fase 2.1: aquella construyó *cómo* un funcionario inicia sesión; esta
construye *cómo un administrador crea funcionarios nuevos* desde el panel
— son dos capacidades distintas, y solo la primera está resuelta desde el
Módulo 0.8.

---

## Tareas de la Fase 2.4

```
[ ] Crear FuncionarioService y FuncionarioController
    → store(): valida CI y usuario únicos, aplica Hash::make() sobre la
      contraseña, valida que rol_id exista. Registra en auditoria.
    → index(): listado con filtro opcional por rol y estado.
    → cambiarEstado(): activar/inactivar sin eliminar (para no perder
      la trazabilidad de tarifas que haya creado en el pasado).

[ ] Crear AsignacionFuncionarioService y AsignacionFuncionarioController
    → asignar() / desasignar(): vincula o desvincula un funcionario_id
      y un campo_id.
    → misAsignaciones() / porFuncionario(): lista los campos asignados
      a un funcionario (alimentará la pantalla de ocupación filtrada
      del Módulo de Épica F más adelante).
    → Regla de negocio: solo se puede asignar un campo a un funcionario
      con rol funcionario_control — rechazar con mensaje claro si se
      intenta con admin_parametricas o gerencia.

[ ] Tests
    → Crear un funcionario guarda la contraseña hasheada y genera una
      fila en auditoria.
    → Crear un funcionario con CI ya existente falla con 422.
    → Asignar un campo a un funcionario_control funciona correctamente.
    → Asignar un campo a un funcionario con rol gerencia es rechazado.

[ ] Commit de la fase
    → Mensaje: "feat(usuarios): gestión de funcionarios y asignación de control"
```

---

# FASE 2.5 — Panel Web: Login, Sesión y Navegación por Rol

## ✅ Ya resuelta — ejecutada por adelantado en el Módulo 0.8 (Fase 0.8.3)

No se repite. El Módulo 0.8 ya construyó:

- `AuthContext` (login/logout, sesión restaurada vía `GET /auth/me`).
- El cliente HTTP con el header `Authorization: Bearer` adjuntado
  automáticamente, y manejo de 401/403.
- La pantalla `/login` con componentes de shadcn/ui.
- `RequireAuth` (equivalente al `ProtectedRoute` del resto del roadmap).
- El layout general con sidebar (Dashboard, Campos, Horarios, Reservas,
  Funcionarios) y header con logout — con esas secciones todavía
  deshabilitadas o apuntando a un placeholder, porque ninguna pantalla de
  negocio existía aún en ese momento.

---

## Tareas de verificación de la Fase 2.5

```
[x] Confirmar que el login funciona de punta a punta desde el panel web
    real, y que la sesión persiste al recargar la página.

[x] Confirmar que RequireAuth sigue redirigiendo correctamente a /login
    ante una ruta protegida sin sesión.

[x] Revisar el layout del Módulo 0.8: el ítem de sidebar "Horarios"
    quedó listado como sección propia en el borrador original, pero en
    el diseño real (ver Fase 2.6) los horarios de atención se editan
    dentro del formulario de un campo, no en una pantalla aparte. Ajustar
    el sidebar para que "Horarios" no sea un enlace independiente —se
    cubre desde "Campos"—, dejando el resto de la estructura igual.

[x] No se requiere commit nuevo, salvo el ajuste puntual del ítem de
    sidebar señalado arriba, que se resuelve como parte de la Fase 2.6.
```

---

# FASE 2.6 — Panel Web: Pantallas de Paramétricas

Esta es la primera fase del roadmap que construye pantallas de negocio
reales sobre la base de UI del Módulo 0.8 — todo se construye con los
componentes de shadcn/ui ya instalados (Card, Form, Input, Table, Dialog),
no con HTML plano.

---

## Tareas de la Fase 2.6

```
[ ] Crear los tipos TypeScript del dominio
    → web-admin/src/types/parametricas.ts — TipoCampo, CampoDeportivo,
      HorarioAtencion, TarifaCampo, reflejando los modelos Eloquent del
      Módulo 1.

[ ] Crear los servicios de API
    → services/tiposCampoService.ts, services/camposService.ts,
      services/tarifasService.ts — un método por endpoint de las Fases
      2.2 y 2.3, usando el apiClient centralizado (ya adjunta el token
      automáticamente desde el Módulo 0.8).

[ ] Pantalla de Tipos de Campo
    → pages/parametricas/TiposCampo.tsx — Table de shadcn para el
      listado, Dialog + Form para alta/edición.

[ ] Pantalla de Campos Deportivos
    → pages/parametricas/CamposDeportivos.tsx — listado con filtro por
      estado, formulario de alta con datos generales, un input de
      latitud/longitud (el selector visual sobre un mapa se deja para
      el módulo de Épica F), y una grilla de 7 filas para los horarios
      de atención — esta pantalla es la que efectivamente cubre lo que
      el ítem de sidebar "Horarios" iba a representar por separado
      (ver nota de la Fase 2.5).
    → Acción de "Cambiar estado" desde el listado, con AlertDialog de
      confirmación antes de aplicar (shadcn) — cambiar a mantenimiento
      bloquea reservas futuras, no debe ser un clic accidental.

[ ] Pantalla de Tarifas
    → pages/parametricas/Tarifas.tsx — accesible desde el detalle de un
      campo: línea de tiempo de tarifas y formulario para fijar una
      nueva, dejando claro en la UI que la tarifa anterior se cierra
      automáticamente al guardar.

[ ] Habilitar el ítem "Campos" del sidebar (Módulo 0.8)
    → Reemplazar el placeholder/enlace deshabilitado por la ruta real
      hacia CamposDeportivos.tsx. Eliminar el ítem "Horarios" del
      sidebar, tal como se decidió en la Fase 2.5.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): pantallas de tipos de campo, campos deportivos y tarifas"
```

---

# FASE 2.7 — Panel Web: Pantallas de Usuarios y Asignaciones

---

## Tareas de la Fase 2.7

```
[ ] Crear los tipos TypeScript del dominio
    → web-admin/src/types/usuarios.ts — Funcionario, Rol,
      AsignacionFuncionario.

[ ] Crear los servicios de API
    → services/funcionariosService.ts, services/asignacionesService.ts.

[ ] Pantalla de Funcionarios
    → pages/usuarios/Funcionarios.tsx — Table de shadcn con filtro por
      rol y estado, Dialog + Form de alta (nombre, CI, usuario,
      contraseña inicial, rol), acción de activar/inactivar.

[ ] Pantalla de Asignación de Control
    → pages/usuarios/Asignaciones.tsx — Select de un funcionario con
      rol funcionario_control, y una lista de Checkbox (shadcn) con
      todos los campos deportivos para marcar cuáles supervisa. Solo
      aparecen en el selector los funcionarios con ese rol.

[ ] Habilitar la sección "Funcionarios" en la navegación
    → Reemplazar el placeholder del sidebar (Módulo 0.8) por la ruta
      real. "Dashboard" y "Reservas" siguen deshabilitados — todavía no
      existe ningún dato de reservas ni de reportes que mostrar ahí; se
      habilitan en los módulos que construyen esa lógica.

[ ] Commit de la fase
    → Mensaje: "feat(web-admin): pantallas de funcionarios y asignación de control"
```

---

# FASE 2.8 — Smoke Test Final y Commit de Cierre

---

## Checklist de cierre del Módulo 2

```
[ ] El login construido en el Módulo 0.8 sigue funcionando de punta a
    punta después de agregar todo lo de este módulo — confirma que
    ningún cambio rompió la autenticación ya resuelta.

[ ] Desde la UI: crear un tipo de campo, luego un campo deportivo con
    sus 7 horarios, y verificar en la base de datos que todo quedó
    guardado exactamente como se cargó.

[ ] Desde la UI: cambiar la tarifa de ese campo dos veces seguidas.
    Verificar en tarifas_campo que hay dos filas, la primera con
    vigente_hasta poblado y la segunda con NULL.

[ ] Intentar, fuera de la UI, insertar una segunda tarifa activa
    saltándose el Service — debe fallar por el índice único parcial.

[ ] Cambiar el campo a estado 'mantenimiento' desde la UI y confirmar
    la fila nueva en auditoria con el estado anterior y el nuevo.

[ ] Crear un funcionario con rol funcionario_control desde la UI,
    asignarle 2 de los campos existentes, y confirmar que un tercer
    campo no aparece marcado como asignado.

[ ] Iniciar sesión como ese funcionario_control y confirmar que el menú
    NO muestra las secciones de Paramétricas ni Usuarios, y que llamar
    directamente a esos endpoints devuelve 403.

[ ] El sidebar del Módulo 0.8 ya no muestra "Horarios" como ítem
    separado, y "Campos" y "Funcionarios" apuntan a pantallas reales en
    vez de placeholders.

[ ] Los pipelines de CI de backend y web-admin pasan en verde sobre un
    Pull Request que incluya todo el módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 2 - paramétricas y usuarios funcionales"
    → Tag sugerido: v0.3.0-parametricas-usuarios
```

> **Siguiente módulo:** el Módulo 3 (Épica C, consulta pública en la app
> móvil) no depende de nada de este módulo salvo que existan campos
> cargados para poder listarlos — su contenido ya estaba marcado "sin
> cambios" frente a Hub & Spoke en el Documento 3 v3, así que solo necesita
> una revisión ligera antes de continuar, no una reescritura. El cambio
> grande de verdad sigue siendo el módulo que reemplaza a la antigua Épica
> D, donde se construye `RecaudacionesApiClient` en código real.
