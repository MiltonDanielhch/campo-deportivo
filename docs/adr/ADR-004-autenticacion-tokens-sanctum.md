# ADR-004: Autenticación del panel con tokens Bearer de Sanctum

## Fecha
2026-08-27

## Contexto
El panel administrativo (`web-admin`) corre en un origen distinto al
backend API (`localhost:5173` vs `localhost:8000`). Sanctum ofrece dos
modos de autenticación:

1. **Cookies de sesión (modo SPA):** requiere que frontend y backend
   compartan dominio o configurar `SESSION_DOMAIN`, además del manejo
   explícito de CSRF.
2. **Tokens Bearer:** el cliente hace login, recibe un token, y lo
   adjunta como `Authorization: Bearer <token>` en cada petición.

## Decisión
Se eligen **tokens Bearer** para la autenticación de funcionarios del
panel administrativo.

## Razones
- **Independencia de dominio:** funciona igual en desarrollo (orígenes
  distintos) y en un eventual despliegue a subdominios separados.
- **Simplicidad:** no requiere configuración de `SESSION_DOMAIN` ni
  manejo de CSRF explícito.
- **Consistencia con la app móvil:** la app Flutter ya usa tokens Bearer
  (aunque sea pública sin login, el cliente HTTP está preparado para
  enviarlos). Usar el mismo mecanismo en panel y móvil unifica la
  estrategia de autenticación del satélite.

## Consecuencias
- El frontend debe guardar el token en `localStorage` y adjuntarlo
  manualmente en cada request (lo hace el `apiClient`).
- `localStorage` es accesible desde JavaScript; en un escenario de XSS,
  el token puede ser robado. Para este sistema (panel administrativo
  interno del GAD Beni, sin datos financieros críticos propios) es
  aceptable. Queda anotado como mejora de seguridad pendiente migrar a
  `httpOnly` cookies si el equipo de seguridad lo considera necesario
  antes de producción.
- El modelo `Funcionario` implementa `HasApiTokens` de Sanctum y
  sobrescribe `getAuthPassword()` para apuntar a la columna
  `password_hash` (no `password`).
