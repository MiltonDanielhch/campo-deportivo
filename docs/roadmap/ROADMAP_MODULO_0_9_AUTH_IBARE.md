# 📁 ROADMAP_MODULO_0_9_AUTH_IBARE.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 1.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Ubicación:** Reemplaza y supera la Fase 0.8.2 del Módulo 0.8 (autenticación Sanctum local)

> **Objetivo del Módulo:** Migrar la autenticación del panel administrativo desde
> tokens Sanctum locales (Módulo 0.8) hacia el **Identity Provider corporativo
> Ibare** (Gobierno Autónomo Departamental del Beni), usando el patrón
> **Backend-for-Frontend (BFF)**: el navegador nunca toca los tokens OAuth; el
> backend de Laravel orquesta el flujo Authorization Code + PKCE, guarda los
> tokens en una cookie de sesión `httpOnly`, y valida el JWT contra el JWKS de
> Ibare en cada petición. La autorización ("qué puede hacer cada quien") sigue
> siendo 100% local, mapeando el claim `sub` del token (el `mamore_id`) contra
> la tabla local `funcionarios`.

> **Efecto sobre el Módulo 0.8:** la Fase 0.8.2 de ese módulo (AuthController +
> AuthService + tokens Sanctum + ADR-004) queda **superada** por este módulo.
> Al retomar cualquier módulo que referencie la autenticación, debe apuntarse a
> este roadmap, no al 0.8. El ADR-004 se marca como *reemplazado por ADR-007*
> (Fase OAUTH-5), no se elimina: los ADR obsoletos se conservan por trazabilidad.

> **Efecto sobre el Módulo 2 (paramétricas):** ya no necesita construir ninguna
> capa de autenticación propia. Sus controladores asumen que el middleware
> `auth.oauth` ya inyectó al `Funcionario` autenticado en la request
> (`$request->user()` y `$request->attributes->get('funcionario')`), y que el
> `RoleMiddleware` ya filtró por rol/permiso. El Módulo 2 arranca directo en la
> gestión de campos y tarifas.

> **Por qué BFF y no tokens en el navegador:** el roadmap 0.8 guardaba el token
> Sanctum en `localStorage`, lo cual es aceptable para un panel interno pero
> frágil ante XSS. Con Ibare como IdP corporativo, exponer el `access_token` al
> navegador multiplicaría el riesgo (es un token firmado por RSA válido en todo
> el ecosistema GAD Beni, no solo en este satélite). El patrón BFF confina los
> tokens a la sesión server-side del backend; el navegador solo ve una cookie
> `httpOnly` + `SameSite=None` + `Secure`. Esta decisión se documenta en el
> ADR-007 (Fase OAUTH-5).

---

## 🗺️ Mapa del Módulo

```
Módulo 0.9 (Auth Ibare)
├── Fase OAUTH-1 → Integración OAuth2 backend (PKCE, callback, JWKS)      [COMPLETADA]
├── Fase OAUTH-2 → Cierre del flujo login (sesión cross-origin, /me, v1)  [COMPLETADA]
├── Fase OAUTH-3 → Guards de rutas y permisos en web-admin                [PENDIENTE]
├── Fase OAUTH-4 → UX de sesión expirada y refresh transparente           [PENDIENTE]
├── Fase OAUTH-5 → Hardening pre-producción y ADR-007                     [PENDIENTE]
└── Fase OAUTH-6 → Tests de integración y commit de cierre                [PENDIENTE]
```

---

## 🟩 Estado: Fases OAUTH-1 y OAUTH-2 completadas · OAUTH-3 a OAUTH-6 pendientes

---

# FASE OAUTH-1 — Integración OAuth2 Backend (Authorization Code + PKCE)

## Por qué Authorization Code + PKCE, y no Client Credentials

Ibare autentica **personas** (funcionarios del GAD Beni), no servicios. El flujo
Authorization Code con PKCE es el recomendado por el RFC 8252 para clientes
públicos y SPAs, y es el único que permite que Ibare muestre su propia pantalla
de login corporativa (con verificación de contrato activo contra Mamoré) antes
de emitir ningún token. Client Credentials quedaría reservado para
integraciones servidor-a-servidor (ver HU-009 de Ibare), que este satélite no
necesita.

## Tareas de la Fase OAUTH-1

```
[x] Registrar el cliente OAuth en Ibare
    → client_id: "canchas-web-admin", con redirect_uri
      http://localhost:8000/auth/callback y scope "canchas:admin".
    → Credenciales en backend/.env bajo el bloque "Ibare OAuth2"
      (IBARE_BASE_URL, IBARE_CLIENT_ID, IBARE_CLIENT_SECRET,
      IBARE_REDIRECT_URI, IBARE_SPA_URL, IBARE_JWKS_URL, IBARE_ISSUER).

[x] Rutas de orquestación en routes/web.php (NO en api.php)
    → GET /auth/login-redirect  → AuthController@loginRedirect
    → GET /auth/callback        → AuthController@callback
    → Van en web.php porque son navegaciones completas del navegador
      (redirects 302), no llamadas XHR; así la cookie de sesión se
      establece en el contexto de primera parte del backend.

[x] Generación de state + PKCE en loginRedirect()
    → state aleatorio de 40 chars (anti-CSRF) y code_verifier de 64
      chars con su code_challenge S256, ambos guardados en sesión.

[x] Canje del código en callback()
    → POST a Ibare /oauth/token con grant_type=authorization_code,
      client_secret y code_verifier. Validación de state antes del
      canje; redirect con ?auth_error=... al SPA en cualquier fallo.

[x] Guardado de tokens en sesión server-side (patrón BFF)
    → Session::put('access_token' | 'refresh_token' | 'token_expires_at').
    → El navegador NUNCA recibe estos valores; solo la cookie de sesión.

[x] Middleware VerificaTokenOAuth (Resource Server)
    → Lee el token del header Bearer o, en su defecto, de la sesión.
    → Valida firma RSA contra el JWKS de Ibare (firebase/php-jwt + JWK).
    → Caché del JWKS por 600s (Cache::remember) para no golpear a Ibare
      en cada request.
    → Refresh transparente: si el token de sesión expiró, usa el
      refresh_token contra Ibare y re-guarda los tokens nuevos.
    → Inyecta al Funcionario local: $request->setUserResolver() y
      $request->attributes->set('funcionario', ...).

[x] Migración 2026_09_23_100757_add_mamore_id_to_funcionarios_table
    → Columna mamore_id (varchar, nullable, index) en funcionarios:
      es el claim "sub" que Ibare emite y la llave del mapeo local.

[x] Comando artisan VincularFuncionarioMamore
    → Asocia un funcionario local existente con su mamore_id de Ibare
      (operación manual de aprovisionamiento, no automática).

[x] Registro del alias de middleware
    → 'auth.oauth' => VerificaTokenOAuth en bootstrap/app.php, y
      'role' => RoleMiddleware para la autorización por rol/permiso.

[x] Commit de la fase
    → Mensaje: "feat(backend): integración OAuth2 BFF con Ibare (PKCE + JWKS)"
```

---

# FASE OAUTH-2 — Cierre del Flujo de Login (sesión cross-origin, /me, rutas v1)

## El problema que esta fase resolvió, y por qué fue tan esquivo

La Fase OAUTH-1 dejaba el flujo técnico funcionando (Ibare emitía tokens y el
backend los guardaba), pero el panel seguía expulsando al usuario al login con
un 401 en `GET /api/v1/auth/me`. Había **cuatro causas encadenadas**, y cada
una enmascaraba a la siguiente — por eso esta fase se trabajó como una
auditoría de punta a punta (middleware → controlador → config → frontend) en
vez de ir parcheando síntomas:

1. **PHP 8.4 y el claim `iss` ausente.** Ibare (league/oauth2-server) no emite
   el claim `iss` en el access_token por defecto. El middleware intentaba leer
   `$payload->iss` directo, lo que en PHP 8+ lanza *Undefined property* y caía
   en el catch genérico → "Token inválido o expirado". Se corrigió validando el
   issuer **solo si está presente** (la firma JWKS ya garantiza procedencia) y
   exigiendo en cambio el claim `sub`, que es el crítico para el mapeo local.
2. **Cookies cross-origin rechazadas.** Frontend en `:5173` y backend en
   `:8000` son orígenes distintos. Con `SameSite=Lax` (default de Laravel) el
   navegador no enviaba la cookie de sesión en las llamadas XHR, y cada request
   nacía con una sesión nueva → el `state` del callback aparecía como `null`.
   Se resolvió con `SESSION_SAME_SITE=none` **más** `SESSION_SECURE_COOKIE=true`
   (los navegadores exigen `Secure` cuando `SameSite=None`, y aceptan `Secure`
   sobre `localhost` por considerarlo origen confiable).
3. **Contrato JSON de `/me` incompleto.** El frontend esperaba el objeto `rol`
   completo (`id`, `nombre`, `descripcion`, `permisos`) para alimentar
   `tienePermiso()`, pero el backend devolvía solo el nombre como string.
4. **Prefijo `/v1` inconsistente.** El `baseURL` de Axios apuntaba a `/api`
   mientras varios services llamaban a rutas sin `/v1/`, produciendo 404 que se
   confundían con fallos de autenticación.

## Tareas de la Fase OAUTH-2

```
[x] Validación condicional y segura de claims en VerificaTokenOAuth
    → Casting (array) $payload + isset() antes de comparar 'iss'.
    → Exigencia explícita del claim 'sub' (mamore_id) con excepción clara.
    → Logs de diagnóstico del payload decodificado (solo en local).

[x] Contrato JSON completo en AuthController@me
    → Devuelve { data: { id, nombre_completo, ci, usuario, estado,
      rol: { id, nombre, descripcion, permisos }, creado_en } }.
    → loadMissing('rol') para evitar N+1 y garantizar permisos presentes.

[x] Configuración de sesión cross-origin en backend/.env
    → SESSION_SAME_SITE=none, SESSION_SECURE_COOKIE=true,
      SESSION_COOKIE=laravel_session, SESSION_DRIVER=database.
    → Verificado con la ruta temporal /debug-session: el session_id se
      mantiene estable y la cookie laravel_session viaja en cada request.

[x] Alineación de rutas /v1 en el frontend
    → AuthContext.tsx: GET /v1/auth/me y POST /v1/auth/logout.
    → apiClient.ts: baseURL desde VITE_API_BASE_URL=http://localhost:8000/api
      (sin /v1, para que cada service declare su versión explícitamente).
    → Los 5 services de web-admin con prefijo /v1: camposService,
      funcionariosService, tiposCampoService, tarifasService y
      asignacionesService (estos dos últimos con rutas anidadas embebidas).

[x] Limpieza de rutas duplicadas en routes/api.php
    → Eliminados los endpoints de prueba /v1/oauth/me duplicados al final
      del archivo; queda un único grupo auth.oauth con /v1/auth/me y
      /v1/auth/logout, más el subgrupo role:admin_parametricas.

[x] Verificación end-to-end del flujo completo
    → Login en Ibare (admin / Password123!) → callback con state válido →
      canje 200 → tokens en sesión → /me 200 con permisos → Dashboard
      renderizado en /panel/parametricas/tipos-campo sin 401 ni 404.

[x] Commit de la fase
    → Mensaje: "fix(auth): sesión BFF cross-origin y contrato /me con Ibare"
```

---

# FASE OAUTH-3 — Guards de Rutas y Permisos en Web Admin  `[PENDIENTE]`

## Por qué guards en el frontend si el backend ya protege todo

El backend ya rechaza con 403 cualquier operación sin permiso (RoleMiddleware).
Los guards del frontend **no son una capa de seguridad**, son una capa de
*experiencia*: evitan que un funcionario_vea enlaces y botones que al hacer
clic le devolverán un error. La regla de oro sigue siendo: el frontend oculta
por conveniencia, el backend niega por seguridad. Nunca confiar en el guard del
cliente como única barrera.

## Tareas de la Fase OAUTH-3

```
[ ] Crear el guard RequirePermiso
    → web-admin/src/components/RequirePermiso.tsx — envoltorio de rutas
      que lee useAuth().tienePermiso(permiso) y redirige a /login si no
      hay sesión, o a una página /sin-permisos si la hay pero falta el
      permiso. Equivalente funcional del RequireAuth existente, pero
      parametrizado por permiso; unificar ambos en un solo componente
      con props opcionales para no tener dos guards divergentes.

[ ] Proteger las rutas del panel en el router
    → /panel/parametricas/*  → requiere 'parametricas:gestionar'
    → /panel/usuarios/*      → requiere 'usuarios:gestionar'
    → /panel/reservas/*      → requiere 'reservas:ver'
    → Mapear cada ruta a un permiso concreto del jsonb de roles, no a un
      rol por nombre (los permisos son la unidad estable; los roles cambian).

[ ] Filtrar el sidebar según permisos
    → components/app-sidebar.tsx: cada item se renderiza solo si
      tienePermiso(...) devuelve true. Un funcionario_control no debe
      ver "Paramétricas"; una gerencia ve todo en modo lectura, etc.

[ ] Deshabilitar acciones en tablas y formularios
    → data-table.tsx y páginas de parametricas/usuarios: ocultar o
      deshabilitar botones Crear/Editar/Eliminar cuando el permiso no
      esté presente, usando el mismo tienePermiso() del contexto.

[ ] Página /sin-permisos
    → Vista simple con shadcn (Card + Button "Volver al inicio") para el
      caso de sesión válida pero permiso insuficiente; que el 403 del
      backend no quede como un toast críptico.

[ ] Manejo uniforme del 403 en apiClient
    → El interceptor actual solo maneja 401; agregar un caso 403 que
      muestre un toast de sonner con el mensaje del backend
      ('No tienes permisos...') sin expulsar de la sesión.

[ ] Verificación manual
    → Crear (o seedear) un funcionario con rol funcionario_control y
      confirmar que: no ve Paramétricas en el sidebar, no puede navegar
      directo por URL (el guard lo frena), y sí opera sus asignaciones.

[ ] Commit de la fase
    → Mensaje: "feat(web): guards de rutas y permisos derivados del JWT de Ibare"
```

---

# FASE OAUTH-4 — UX de Sesión Expirada y Refresh Transparente  `[PENDIENTE]`

## El refresh ya existe en el backend; falta que el frontend lo acompañe

VerificaTokenOAuth ya renueva el access_token con el refresh_token cuando
detecta expiración. Pero si el **refresh_token** también expiró (o Ibare lo
revocó), el middleware devuelve 401 y el interceptor de Axios expulsa al
usuario a /login sin explicación. Esta fase convierte ese final abrupto en una
experiencia controlada, y evita perder trabajo no guardado en formularios.

## Tareas de la Fase OAUTH-4

```
[ ] Distinguir 401 "sesión expirada" de 401 "sin sesión"
    → El backend ya responde {'error':'Sesion expirada'} en el caso de
      refresh fallido; el interceptor debe leer ese cuerpo y mostrar un
      diálogo "Tu sesión expiró, iniciá sesión de nuevo" en vez de un
      redirect silencioso.

[ ] Conservar la ruta de origen tras el re-login
    → Guardar window.location.pathname en sessionStorage antes de
      redirigir a /login, y restaurarla después del callback exitoso
      (el AuthContext puede leerla al montar tras autenticar).

[ ] Advertencia de expiración próxima (opcional, bajo flag)
    → El access_token dura 600s; un timer en el AuthContext puede avisar
      a los 9 minutos con un toast "Tu sesión se renovará automáticamente"
      y disparar una llamada liviana (GET /v1/auth/me) que fuerza el
      refresh transparente del middleware.

[ ] Logout completo contra Ibare (opcional)
    → Hoy /v1/auth/logout solo limpia la sesión local del satélite.
      Evaluar con el equipo de Ibare si existe endpoint de revocación
      (RFC 7009) para invalidar también el refresh_token del lado del IdP;
      si no existe, documentar la limitación en el ADR-007.

[ ] Verificación manual
    → Reducir temporalmente el TTL del token en Ibare (o esperar 10 min
      con la app abierta) y confirmar: refresh silencioso si hay
      refresh_token válido; diálogo amable y redirect conservando ruta
      si no lo hay.

[ ] Commit de la fase
    → Mensaje: "feat(web): manejo de sesión expirada y re-login con retorno"
```

---

# FASE OAUTH-5 — Hardening Pre-Producción y ADR-007  `[PENDIENTE]`

## Lo que es aceptable en local y no lo será en producción

Toda la Fase OAUTH-2 se apoyó en que los navegadores tratan `localhost` como
origen confiable (cookies `Secure` sobre HTTP). En producción eso desaparece:
habrá HTTPS real, dominios propios y un Ibare en su URL oficial. Esta fase
cierra esa brecha y deja la decisión arquitectónica documentada.

## Tareas de la Fase OAUTH-5

```
[ ] Crear docs/adr/ADR-007-autenticacion-bff-oauth2-ibare.md
    → Contexto: IdP corporativo obligatorio, panel en origen distinto.
    → Decisión: patrón BFF con cookie httpOnly; tokens confinados al
      backend; autorización local por mamore_id → funcionarios.rol.
    → Consecuencias: el navegador no puede reutilizar el token en otras
      apps (ventaja de seguridad); el backend asume el costo de validar
      JWT en cada request (mitigado con caché de JWKS).
    → Marcar ADR-004 (tokens Sanctum) como SUPERSED por ADR-007, sin
      borrarlo: Sanctum queda solo como mecanismo residual/deuda técnica
      del modelo User del template.

[ ] Validación de scope en VerificaTokenOAuth (cerrar el cabo suelto)
    → El grupo de rutas usa 'auth.oauth:canchas:admin' pero el handle()
      del middleware ignora el parámetro. Agregar el tercer argumento
      ?string $scope y verificarlo contra $payload->scopes; si no está,
      responder 403 'SCOPE_INSUFICIENTE'. Hoy es un no-op silencioso.

[ ] Configuración de producción en .env.example documentada
    → SESSION_SECURE_COOKIE=true (ya obligatorio), SESSION_SAME_SITE=none
      solo si el SPA vive en otro subdominio; si comparten dominio,
      preferir 'lax' + cookie de primera parte (más simple y seguro).
    → APP_URL y IBARE_* con las URLs HTTPS definitivas del GAD Beni.

[ ] Registro de auditoría de eventos de autenticación
    → La tabla auditoria ya existe (Módulo 1); agregar eventos
      login_ibare_ok, login_ibare_fallo, refresh_ok, refresh_fallo y
      logout desde el middleware/controlador, con mamore_id y IP.

[ ] Rotación y limpieza de sesiones
    → Job programado (console.php) que purgue filas de sessions con
      last_activity antiguo, y revoque refresh_token locales huérfanos.

[ ] Revisión de logs de diagnóstico
    → Los Log::debug del payload y los logs verbosos del middleware deben
      quedar tras un flag (config('app.debug') o canal dedicado) para no
      filtrar claims a logs de producción.

[ ] Commit de la fase
    → Mensaje: "docs(auth): ADR-007 BFF OAuth2 y hardening pre-producción"
```

---

# FASE OAUTH-6 — Tests de Integración y Commit de Cierre  `[PENDIENTE]`

## Por qué tests ahora y no antes

Las Fases OAUTH-1 y OAUTH-2 se validaron a mano (ventana incógnito + logs)
porque el objetivo era desbloquear el flujo cuanto antes. Ahora que el
comportamiento esperado está estable y documentado, es el momento de
congelarlo en tests para que ningún refactor futuro rompa el login sin aviso.

## Tareas de la Fase OAUTH-6

```
[ ] Actualizar tests/Feature/AuthOAuthIbareTest.php
    → Simular el JWKS de Ibare con un par de claves RSA generadas en el
      test (phpseclib ya está en require-dev) y firmar un JWT de prueba
      con y sin claim 'iss': ambos deben pasar (regresión del bug PHP 8.4).
    → Un JWT sin claim 'sub' debe responder 401 con mensaje específico.
    → Un JWT firmado con otra clave debe responder 401 (firma inválida).

[ ] Test del mapeo local sub → funcionario
    → mamore_id existente y activo  → 200 con rol y permisos completos.
    → mamore_id existente inactivo  → 403 FUNCIONARIO_NO_HABILITADO.
    → mamore_id inexistente         → 403 FUNCIONARIO_NO_HABILITADO.

[ ] Test de RoleMiddleware sobre auth.oauth
    → Funcionario con permiso wildcard '*' accede a rutas admin.
    → Funcionario sin el permiso requerido recibe 403 con roles_requeridos.

[ ] Test del contrato JSON de /me
    → Assert de la estructura exacta que consume AuthContext.tsx
      (incluido rol.permisos como array), para detectar drifts futuros
      entre backend y frontend.

[ ] Smoke test final del módulo
    → Flujo completo en local: login Ibare → dashboard → operación CRUD
      en paramétricas → logout → ruta protegida vuelve a /login.

[ ] Checklist de cierre del Módulo 0.9
    → [ ] Fases OAUTH-1 a OAUTH-6 con sus tareas en [x].
    → [ ] ADR-007 versionado y ADR-004 marcado como superado.
    → [ ] Sin logs de claims en nivel debug activos por defecto.
    → [ ] CI de backend y web-admin en verde sobre el PR del módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 0.9 - autenticación centralizada Ibare"
    → Tag sugerido: v0.9.0-auth-ibare
```

> **Siguiente paso:** con el Módulo 0.9 cerrado, el sistema queda con
> identidad corporativa (Ibare) y autorización local (roles/permisos jsonb).
> Los módulos de negocio (2 en adelante) pueden asumir `$request->user()` como
> un `Funcionario` autenticado y `tienePermiso()` como única fuente de verdad
> de autorización. La deuda técnica restante conocida: el modelo `User` y la
> tabla `users` del template, y el endpoint Sanctum residual, ambos
> documentados en el ADR-007 como candidatos a eliminación en un módulo de
> limpieza futuro.
