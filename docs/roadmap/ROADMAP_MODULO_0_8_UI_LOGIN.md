# 📁 ROADMAP_MODULO_0_8_UI_LOGIN.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 1.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 3–4 horas · **Ubicación:** Entre el Módulo 0 (setup) y el Módulo 1 (base de datos)

> **Objetivo del Módulo:** El panel administrativo necesita un sistema de
> diseño (shadcn/ui) y un login funcional **antes** de construir cualquier
> pantalla de negocio — de lo contrario, cada módulo posterior tendría que
> improvisar su propia base visual y su propia forma de proteger rutas. El
> login depende únicamente de la Fase 1.1 del Módulo 1 (roles + funcionarios),
> no del módulo completo de base de datos — por eso esa fase se adelanta
> aquí, en vez de esperar a que todo el modelo de reservas exista.

> **Efecto sobre el Módulo 1:** la Fase 0.8.2 de este módulo ejecuta,
> adelantada, la Fase 1.1 del Módulo 1 completa (extensiones, roles,
> funcionarios). Al retomar el Módulo 1, esa fase ya está hecha — se marca
> `[x]` sin repetirla, y el trabajo real empieza en la Fase 1.2.

> **Efecto sobre el Módulo 2:** por el mismo motivo, la Fase 0.8.2 también
> adelanta buena parte de lo que originalmente era la Fase 2.1 del Módulo 2
> (Autenticación por token y control de acceso por rol) — el `AuthController`,
> el login, y el ADR sobre tokens vs. cookies se construyen aquí, no allá. El
> Módulo 2, cuando se ajuste, deberá marcar esa fase como ya resuelta y
> arrancar directamente en la gestión de paramétricas.

---

## 🗺️ Mapa del Módulo

```
Módulo 0.8
├── Fase 0.8.1 → Base de UI: Tailwind + shadcn/ui
├── Fase 0.8.2 → Autenticación backend (adelanta Fase 1.1 del Módulo 1 y parte de la Fase 2.1 del Módulo 2)
├── Fase 0.8.3 → Login y layout en web-admin
└── Fase 0.8.4 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 0.8.1 — Base de UI: Tailwind + shadcn/ui

## Por qué esta elección, y por qué no necesita un ADR propio

`shadcn/ui` (sobre Tailwind + Radix) es una elección de bajo riesgo y
fácilmente reversible: a diferencia de la base de datos o de con quién se
integra el backend, cambiar de sistema de diseño más adelante no obliga a
rediseñar ninguna regla de negocio ni migrar datos — solo estilos y
componentes de presentación. Por eso no amerita el mismo nivel de ceremonia
que el ADR-002 (PostgreSQL) o el ADR-003 (Hub & Spoke); basta con dejarlo
documentado aquí, igual que se hizo con la elección de `recharts` para
gráficos en el módulo de reportes.

La particularidad de shadcn/ui frente a una librería de componentes
tradicional es que el código de cada componente se copia dentro del propio
repositorio (no es una dependencia de node_modules que se actualiza sola) —
eso da control total sobre el estilo, a cambio de que las actualizaciones de
la librería haya que aplicarlas a mano cuando se necesiten.

---

## Tareas de la Fase 0.8.1

```
[ ] Instalar Tailwind CSS v4 en web-admin
    → tailwindcss + @tailwindcss/vite, configurado como plugin en
      vite.config.ts (Tailwind v4 se integra directo al pipeline de
      Vite, ya no requiere postcss.config.js ni tailwind.config.js
      separados como en versiones anteriores).

[ ] Inicializar shadcn (npx shadcn@latest init)
    → Genera components.json (aliases de rutas), y el tema base con
      variables CSS (colores, radios de borde) en el archivo de
      estilos global.

[ ] Agregar los componentes base que el resto del proyecto va a reutilizar
    → button, input, label, card, form, sonner (para notificaciones
      tipo toast) — el mínimo necesario para construir el login de la
      Fase 0.8.3; el resto de componentes (table, dialog, select, etc.)
      se agregan a demanda en cada módulo que los necesite, no todos de
      una vez ahora.

[ ] Limpiar el boilerplate del template de Vite
    → Eliminar App.css y los assets de demostración que trae el
      template por defecto de "npm create vite" — no tiene sentido
      arrastrar estilos que se van a pisar de inmediato.

      → La pantalla de verificación de conectividad NO se elimina:
        se muda a una ruta pública /health (o se integra como indicador
        de estado en el layout de la Fase 0.8.3).

[ ] Commit de la fase
    → Mensaje: "feat(web): base de sistema de diseño con Tailwind + shadcn/ui"
```

---

# FASE 0.8.2 — Autenticación Backend

## Lo que esta fase adelanta, y por qué es seguro adelantarlo

Roles y funcionarios no tienen ninguna dependencia hacia el modelo de
reservas (`solicitudes_reserva`, `solicitud_reserva_detalle`, `reservas`) —
son un subsistema de acceso completamente autónomo. Construirlos ahora, antes
que el resto del Módulo 1, no genera ningún trabajo que luego haya que
deshacer o repetir.

Sobre las extensiones de PostgreSQL: esta fase habilita tanto `pgcrypto`
(necesaria de inmediato, para `gen_random_uuid()`) como `btree_gist`
—que en rigor no se usa hasta la Fase 1.3 del Módulo 1, cuando se construya
la restricción `EXCLUDE` anti-doble-reserva—. Se habilita igual desde ahora
porque `CREATE EXTENSION IF NOT EXISTS` es una operación inofensiva e
idempotente, y mantener toda la configuración de extensiones en una única
migración (en vez de fragmentarla entre dos módulos distintos) es más fácil
de mantener. No es un error ni un cabo suelto: es una decisión deliberada de
agrupar el setup de infraestructura de base de datos en un solo lugar.

Recordatorio: `php artisan install:api` ya se ejecutó en el Módulo 0 (Fase
0.2) — `config/sanctum.php`, `routes/api.php` y la migración de
`personal_access_tokens` ya existen. Esta fase no repite ese paso, solo
construye la lógica de autenticación sobre esa base ya instalada.

## La decisión de tokens Sanctum sobre cookies de sesión SPA

Sanctum ofrece dos modos: cookies de sesión (modo "SPA", que exige que
frontend y backend compartan dominio o se configure `SESSION_DOMAIN`, más el
manejo de CSRF) o tokens Bearer. Se elige **tokens**, por la misma razón
válida en cualquier configuración de desarrollo con orígenes distintos
(`localhost:5173` para el panel, `localhost:8000` para el backend): el panel
hace login, recibe un token, y lo envía en `Authorization: Bearer <token>` en
cada petición, sin depender de que ambos compartan dominio ni en desarrollo
ni en un eventual despliegue a subdominios separados.

---

## Tareas de la Fase 0.8.2

```
[ ] Crear docs/adr/ADR-004-autenticacion-tokens-sanctum.md
    → Documentar la decisión anterior: contexto (SPA en origen distinto
      al backend), decisión (tokens Bearer, no cookies), consecuencias
      (más simple de operar, pero el frontend debe guardar el token de
      forma segura y adjuntarlo manualmente en cada request).
    → Nota de numeración: es ADR-004 porque ADR-001 (monorepo),
      ADR-002 (PostgreSQL) y ADR-003 (Hub & Spoke) ya existen desde el
      Módulo 0 — no reutilizar ADR-003 para esto.

[ ] Migración de extensiones (pgcrypto + btree_gist)
    → Igual que la Fase 1.1 original del Módulo 1: ambas vía
      DB::statement('CREATE EXTENSION IF NOT EXISTS "..."').

[ ] Migración de roles
    → id (uuid, PK), nombre (varchar(50), único — sin enum), descripcion
      (varchar(200), nullable), permisos (jsonb, nullable).

[ ] Migración de funcionarios
    → id (uuid, PK), nombre_completo (varchar(200)), ci (varchar(20),
      único), usuario (varchar(50), único), password_hash
      (varchar(255)), rol_id (FK → roles.id), estado (enum:
      activo/inactivo), creado_en (timestamptz).

[ ] Modelos Eloquent Rol y Funcionario
    → Ambos con HasUuids, $incrementing = false, $keyType = 'string'.
    → Funcionario además implementa Authenticatable + HasApiTokens
      (Sanctum), con getAuthPassword() apuntando a password_hash (la
      columna no se llama 'password', que es lo que Sanctum espera por
      defecto).
    → Relación Funcionario belongsTo(Rol).

[ ] Seeders: 3 roles + funcionario administrador inicial
    → RolesSeeder: admin_parametricas, funcionario_control, gerencia.
    → FuncionarioSeeder: un usuario admin_parametricas de prueba, con
      contraseña de desarrollo.
    → ADVERTENCIA CRÍTICA: el FuncionarioSeeder es exclusivamente para
      entornos de desarrollo — la contraseña de prueba quedaría
      documentada en el propio código fuente del repositorio si se
      corriera en producción. Condicionarlo con
      app()->environment('local') dentro del propio seeder, para que
      db:seed en producción lo omita automáticamente aunque alguien lo
      ejecute por error.

[ ] Actualizar config/auth.php
    → providers.users.model => App\Models\Funcionario
      (o agregar un provider/guard 'funcionarios').
    → Decidir qué hacer con el modelo User y la tabla users del
      template: se conservan por ahora como deuda técnica
      documentada, no se eliminan en este módulo.

[ ] Crear AuthService y AuthController
    → AuthService::login() busca por 'usuario', verifica Hash::check()
      contra password_hash, rechaza si estado no es 'activo', y genera
      un token con createToken('panel-web')->plainTextToken.
    → AuthService::logout() revoca solo el token actual
      (currentAccessToken()->delete()), no todos los tokens del
      funcionario.
    → Rutas: POST /api/v1/auth/login (sin middleware), GET
      /api/v1/auth/me y POST /api/v1/auth/logout (ambas con
      auth:sanctum).

[ ] Tests de autenticación
    → Login correcto devuelve 200 y un token.
    → Login con funcionario 'inactivo' es rechazado con 401, aunque la
      contraseña sea correcta.
    → Una ruta protegida sin token devuelve 401.

[ ] Commit de la fase
    → Mensaje: "feat(backend): autenticación de funcionarios con Sanctum"
```

---

# FASE 0.8.3 — Login y Layout en Web Admin

---

## Tareas de la Fase 0.8.3

```
[ ] Crear AuthContext
    → web-admin/src/context/AuthContext.tsx — estado global del
      funcionario autenticado, su rol y el token; expone login(),
      logout() y el estado de carga inicial.
    → El token se guarda en localStorage (válido para este proyecto
      real, fuera de cualquier restricción de entorno de artifacts).
      Queda anotado como mejora de seguridad pendiente para el módulo
      de hardening: migrar a una cookie httpOnly si el equipo lo
      considera necesario antes de producción.
    → Al montar la aplicación, si hay un token guardado, llama a
      GET /api/v1/auth/me para restaurar la sesión sin pedir usuario y
      contraseña de nuevo.
      

[ ] Actualizar el cliente HTTP base (creado en el Módulo 0)
    → services/apiClient.ts: adjuntar Authorization: Bearer <token> en
      cada request si existe sesión. Un 401 limpia la sesión y redirige
      a /login.
    → CORS ya quedó configurado en el Módulo 0 (Fase 0.3) — no hace
      falta repetirlo aquí.
    → Alinear la clave de localStorage ('auth_token') entre
        AuthContext y apiClient, y reemplazar window.location.href
        por navegación del router (react-router-dom).

[ ] Crear la pantalla de login
    → pages/auth/Login.tsx — formulario de usuario/contraseña con
      componentes de shadcn (Card + Form + Input + Button), llama a
      AuthContext.login(), muestra el error del backend con el
      componente sonner si las credenciales son incorrectas.

[ ] Crear RequireAuth
    → Envuelve las rutas que requieren sesión iniciada; redirige a
      /login si no hay funcionario autenticado. Equivalente al
      ProtectedRoute usado en el resto del roadmap — incorporar el
      mismo nombre en las convenciones del equipo para no tener dos
      términos distintos para lo mismo.

[ ] Crear el layout general del panel
    → Sidebar con las secciones previstas (Dashboard, Campos, Horarios,
      Reservas, Funcionarios) y header con el nombre del funcionario
      autenticado y la acción de logout.
    → Ninguna de esas secciones tiene todavía una pantalla real detrás
      —el Módulo 1 recién va a crear el modelo de datos que las
      sustenta—, así que sus enlaces quedan deshabilitados o apuntan a
      un placeholder simple ("Próximamente"), nunca a una ruta rota. Es
      la misma disciplina aplicada en el resto del roadmap: se muestra
      la estructura completa del panel desde ya, pero no se construyen
      pantallas de negocio vacías antes de tiempo.

[ ] Verificación manual
    → Login → navegación por el layout → logout → cualquier ruta
      protegida vuelve a /login. Confirmar también que recargar la
      página con sesión activa no pide login de nuevo (por el
      GET /auth/me al montar la app).

[ ] Commit de la fase
    → Mensaje: "feat(web): login y layout del panel con shadcn"
```

---

# FASE 0.8.4 — Smoke Test Final y Commit de Cierre

---

## Checklist de cierre del Módulo 0.8

```
[ ] La infraestructura local (PostgreSQL 18.4 + Memurai) sigue operativa con las nuevas migraciones aplicadas.


[ ] Un funcionario con estado 'inactivo' no puede iniciar sesión, ni
    desde la UI ni llamando al endpoint directamente.

[ ] El login funciona de punta a punta desde el panel web real (no solo
    con Postman): usuario/contraseña → token → navegación por el
    layout → logout → 401 en cualquier ruta protegida.

[ ] La sesión persiste al recargar la página (verifica el flujo de
    GET /auth/me al montar la app).

[ ] ADR-004 está creado y versionado en docs/adr/, sin colisionar con
    los tres ADR ya existentes del Módulo 0.

[ ] git status no muestra el FuncionarioSeeder ejecutándose fuera del
    entorno local (verificar el guard de app()->environment('local')
    con una prueba manual simulando APP_ENV=production).

[ ] El pipeline de CI de backend y web-admin (Módulo 0) pasa en verde
    sobre un Pull Request que incluya todo este módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 0.8 - UI base y login funcional"
    → Tag sugerido: v0.1.5-ui-login
```

> **Siguiente paso:** al retomar el Módulo 1, la Fase 1.1 se marca `[x]` sin
> repetirse y el trabajo arranca en la Fase 1.2 (catálogos y paramétricas).
> Cuando se ajuste el Módulo 2, su Fase 2.1 original (autenticación) también
> debe marcarse como resuelta aquí, arrancando directamente en la gestión de
> campos y tarifas.
