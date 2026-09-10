# 📁 ROADMAP_MODULO_7_SEGURIDAD_PUBLICACION.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 6–8 horas (antes 8–12 — la superficie de seguridad se redujo con la contingencia) · **Bloquea:** Nada — es el módulo de cierre del proyecto

> **Nota de renumeración:** este módulo se llamaba "Módulo 8" en la versión
> anterior del roadmap. Con la Épica E retirada, la numeración se corrió un
> lugar — ver la nota equivalente en el Módulo 6.

> **Objetivo del Módulo:** Implementar la Épica G del Documento 3 v3 (HU-G1
> a HU-G3) — el último módulo antes de operar con datos reales y distribuir
> la app públicamente. No agrega funcionalidad de negocio nueva: endurece
> lo que los siete módulos anteriores ya construyeron.

> **Lo que se simplifica respecto a la versión anterior:** con la
> contingencia eliminada, desaparece toda la superficie de seguridad
> relacionada a subida de archivos (no existe ningún upload en Canchas) y a
> coordinar credenciales de dos pasarelas bancarias distintas. Queda una
> sola integración externa que verificar antes de producción: el Core de
> Recaudaciones.

---

## 🗺️ Mapa del Módulo

```
Módulo 7
├── Fase 7.1 → Rate limiting real contra abuso en la app anónima
├── Fase 7.2 → Pruebas de concurrencia formales y repetibles
├── Fase 7.3 → Revisión de seguridad transversal
├── Fase 7.4 → Publicación en Google Play Store
└── Fase 7.5 → Cierre general del proyecto
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 7.1 — Rate Limiting Real Contra Abuso en la App Anónima

Implementa HU-G1. Desde el Módulo 3, las rutas públicas llevan un
`throttle:60,1` genérico, señalado como "una barrera mínima, no la solución
robusta". Esta fase construye esa solución robusta.

## El riesgo concreto que se protege

La app móvil no requiere cuenta, lo cual significa que nada impide,
técnicamente, que alguien genere cientos de `solicitudes_reserva` por
minuto para las mismas franjas, solo para mantenerlas bloqueadas —una
solicitud en `pendiente` bloquea el horario aunque nadie complete el
cobro— e impedir que ciudadanos reales reserven.

---

## Tareas de la Fase 7.1

```
[ ] Crear un limitador nombrado específico para creación de solicitudes
    → En bootstrap/app.php, RateLimiter::for('creacion_solicitudes', ...)
      con un límite estricto (ej. 5 solicitudes por IP cada 10 minutos),
      aplicado únicamente a POST /api/v1/public/solicitudes-reserva.

[ ] Registrar también un límite por dispositivo, no solo por IP
    → Complementar con un identificador de instalación de la app
      (generado una vez, reenviado en un header propio, sin ser
      información personal identificable), porque una IP compartida
      (NAT de operadora móvil) puede castigar a ciudadanos legítimos.

[ ] Registrar los límites alcanzados
    → Log con IP e identificador de dispositivo cuando un límite se
      dispara, para que TI distinga un pico legítimo de un intento de
      abuso sostenido.

[ ] Tests
    → Superar el límite responde 429, no un error genérico.
    → El límite no afecta las rutas de solo lectura (listado de
      campos, disponibilidad, consulta de estado).

[ ] Commit de la fase
    → Mensaje: "feat(seguridad): rate limiting específico contra abuso de creación de solicitudes"
```

---

# FASE 7.2 — Pruebas de Concurrencia Formales y Repetibles

Implementa HU-G2. Los Módulos 4 y 5 verificaron manualmente que la
restricción `EXCLUDE`, la resolución inmediata cuando el Core no responde,
y la unicidad de `reservas.solicitud_reserva_detalle_id` funcionan bajo
concurrencia real. Esta fase convierte esas verificaciones en pruebas que
corren solas en cada Pull Request.

---

## Tareas de la Fase 7.2

```
[ ] Crear un script de prueba de concurrencia de doble reserva
    → infrastructure/scripts/test-concurrencia-reserva.sh — dispara N
      peticiones HTTP simultáneas pidiendo exactamente la misma franja
      del mismo campo. Verifica que exactamente una tuvo éxito (201) y
      las demás fallaron con 409, y que en la base existe exactamente
      una fila viva en solicitud_reserva_detalle para esa franja.

[ ] Crear un script de prueba de webhook duplicado
    → infrastructure/scripts/test-webhook-idempotente.sh — crea una
      solicitud, la confirma con RecaudacionesApiClientSimulado, y
      dispara el mismo payload de webhook varias veces en paralelo.
      Verifica que el número de filas en reservas coincide exactamente
      con las franjas de la solicitud, sin importar cuántos webhooks
      duplicados se hayan disparado.

[ ] Crear un script de prueba de indisponibilidad del Core
    → infrastructure/scripts/test-core-no-disponible.sh — con
      RecaudacionesApiClientSimulado forzando una falla de conexión,
      crea una solicitud y verifica: la respuesta es 503, la solicitud
      queda 'rechazada' de inmediato (no 15 minutos después, ver Módulo
      4), y una segunda solicitud para la misma franja se puede crear
      exitosamente justo a continuación —confirma que el horario se
      liberó en el acto.

[ ] Integrar los tres scripts al pipeline de CI del backend
    → Job en .github/workflows/backend-ci.yml que levante el entorno
      completo (Postgres + Redis + backend) y corra los tres scripts,
      fallando el Pull Request si cualquiera de las tres garantías no
      se sostiene.

[ ] Documentar el resultado esperado de cada script
    → Un comentario al inicio de cada uno explicando qué garantiza, de
      qué módulo/documento proviene la restricción que lo hace posible
      (Documento 2 v3, Anexos A.1 y A.2), y qué significa que falle.

[ ] Commit de la fase
    → Mensaje: "test(concurrencia): pruebas repetibles de doble reserva, webhook duplicado e indisponibilidad del Core en CI"
```

---

# FASE 7.3 — Revisión de Seguridad Transversal

Un repaso final de las notas de seguridad que quedaron señaladas a lo largo
del roadmap. Más corta que en la versión anterior: sin contingencia, no hay
superficie de uploads que revisar.

---

## Tareas de la Fase 7.3

```
[ ] Revisar el almacenamiento del token de sesión del panel web
    → Desde el Módulo 0.8: el token se guarda en localStorage, con una
      nota de que migrar a una cookie httpOnly era una mejora
      pendiente. Decidir con el equipo si se hace ahora o se documenta
      como riesgo aceptado para esta primera versión.

[ ] Confirmar la integración real con el Core de Recaudaciones
    → Los Módulos 4 y 5 dejaron RecaudacionesApiClient con TODOs
      explícitos: el contrato exacto de solicitarCobro() y
      consultarEstado(), y la verificación de firma del webhook, a la
      espera de la documentación oficial del Core. Este es el punto de
      control final para confirmar que esos TODOs se cerraron con la
      documentación real antes de aceptar tráfico de producción — no
      pueden quedar pendientes al momento de publicar. Es una sola
      integración que confirmar, no dos como en la arquitectura anterior.

[ ] Revisar los secretos de entorno
    → Confirmar que ningún .env real (credenciales de base de datos,
      RECAUDACIONES_API_TOKEN, RECAUDACIONES_WEBHOOK_SECRET) quedó
      accidentalmente commiteado en el repositorio en ningún punto del
      historial — el .gitignore del Módulo 0 debería haberlo evitado,
      pero vale la pena una verificación final con una herramienta de
      escaneo de secretos sobre todo el historial de Git.

[ ] Confirmar que no queda ninguna referencia residual a la arquitectura anterior
    → Buscar en todo el repositorio (código, comentarios, nombres de
      variable) referencias sueltas a "pasarela", "circuit breaker",
      "contingencia", "orden_pago" o "pagos_confirmados" que hayan
      sobrevivido por error a la migración a Hub & Spoke — cualquier
      resto de la arquitectura anterior es, en el mejor de los casos,
      código muerto, y en el peor, una fuente de confusión para quien
      lea el proyecto después.

[ ] Commit de la fase
    → Mensaje: "chore(seguridad): revisión transversal previa a producción"
```

---

# FASE 7.4 — Publicación en Google Play Store

Implementa HU-G3.

---

## Tareas de la Fase 7.4

```
[ ] Preparar la ficha de la aplicación
    → Nombre, descripción corta y larga, capturas de pantalla de las
      pantallas principales, ícono en los tamaños requeridos.

[ ] Redactar la política de privacidad
    → Obligatoria para publicar. Debe cubrir qué datos capta la app
      (nombre, teléfono, CI/NIT opcional al momento de reservar —
      Documento 1 v3, decisión 4). A diferencia de la arquitectura
      anterior, la política puede declarar explícitamente que el
      procesamiento del cobro en sí —datos bancarios, medios de
      pago— ocurre íntegramente en el Core de Recaudaciones, no en la
      app de Canchas, lo que reduce la superficie de datos sensibles
      que la app misma maneja y simplifica lo que hay que declarar.
      Publicarla en una URL accesible y enlazarla desde la ficha.

[ ] Configurar la firma de la aplicación
    → Generar el keystore de firma, resguardado fuera del repositorio.

[ ] Configurar el build de producción de Flutter
    → Apuntando a las URLs reales de producción, modo release.

[ ] Completar el cuestionario de contenido y clasificación de Play Store
    → Clasificación de edad, declaración de sin anuncios ni compras
      dentro de la app, declaración de manejo de datos personales.

[ ] Publicar en un canal de pruebas cerrado antes del lanzamiento público
    → Con un grupo reducido (ej. personal de la Secretaría de Deportes)
      antes de abrir la publicación a todo el público.

[ ] Commit de la fase
    → Mensaje: "chore(publicacion): preparación de la app móvil para Google Play Store"
```

---

# FASE 7.5 — Cierre General del Proyecto

## Checklist final, de todo el sistema

```
[ ] Los tres pipelines de CI, incluyendo las tres pruebas de
    concurrencia de la Fase 7.2, pasan en verde sobre la rama principal.

[ ] El rate limiting específico de creación de solicitudes está activo
    y verificado en el entorno que se usará para producción.

[ ] La integración con el Core de Recaudaciones (RecaudacionesApiClient)
    está completa con la documentación real del Core — ya no depende de
    RecaudacionesApiClientSimulado, que queda activo únicamente en
    entornos de desarrollo y pruebas.

[ ] La política de privacidad está publicada y enlazada, y la app está
    disponible al menos en el canal de pruebas cerrado de Play Store.

[ ] Los nueve módulos de este roadmap (0, 0.8, 1 a 7) tienen su commit
    de cierre correspondiente en el historial de Git, con sus tags de
    versión. El total de módulos no cambió respecto al plan original
    —nueve—, aunque la Épica E desapareció: el Módulo 0.8 ocupó
    exactamente el espacio que esa eliminación liberó.

[ ] docs/architecture/ contiene la versión 3 de los tres documentos de
    arquitectura (todos actualizados a Hub & Spoke), y docs/adr/
    contiene el registro completo de decisiones: ADR-001 (monorepo),
    ADR-002 (PostgreSQL), ADR-003 (Hub & Spoke), ADR-004 (autenticación
    por tokens), ADR-005 (librería de mapas).

[ ] Commit final de cierre del proyecto
    → Mensaje: "chore: cierre Módulo 7 - seguridad, concurrencia verificada y publicación"
    → Tag sugerido: v1.0.0
```

> **Fin del roadmap.** Los nueve módulos cubren el backlog completo del
> Documento 3 v3. A partir de aquí, el trabajo pasa de "construir el
> satélite" a "operarlo": monitorear la disponibilidad del Core de
> Recaudaciones, revisar periódicamente los logs de
> RecaudacionesApiClient si la latencia de sus respuestas se degrada, y
> mantener alineados los tres documentos de arquitectura con el Core cada
> vez que ese equipo cambie su propio contrato de API.
