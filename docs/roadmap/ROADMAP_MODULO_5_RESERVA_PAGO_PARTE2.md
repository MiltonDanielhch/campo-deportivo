# 📁 ROADMAP_MODULO_5_RESERVA_PAGO_PARTE2.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 8–10 horas (antes 10–14 — un solo webhook que atender, no varios proveedores) · **Bloquea:** El módulo de Operación en Sitio y Dashboard (antes Módulo 6, ahora Módulo 5)

> **Objetivo del Módulo:** Cerrar la Épica D del Documento 3 v3 (HU-D4 a
> HU-D7): confirmar el cobro —por webhook del Core de Recaudaciones o por
> consulta activa opcional, de forma idempotente—, generar las reservas
> definitivas, mostrarle al ciudadano su comprobante digital, y permitirle
> consultar el estado de su solicitud en cualquier momento.

> **Lo que se simplifica respecto a la versión anterior:** ya no existe la
> tabla `pagos_confirmados` ni la restricción `UNIQUE
> (proveedor_pasarela_id, transaccion_externa_id)` que protegía contra
> webhooks duplicados. Esa garantía la da ahora la restricción `UNIQUE`
> sobre `reservas.solicitud_reserva_detalle_id` —que ya existía por otro
> motivo (Documento 2 v3, Anexo A.2)—, así que el servicio de confirmación
> queda más corto: ya no escribe en dos tablas (pago + reserva), solo en
> una. Tampoco existe la Épica E de contingencia a la cual esta lógica
> tuviera que coordinarse —eso simplifica también la regla de la
> confirmación tardía, que ahora solo tiene que lidiar con vencimientos
> normales, no con aprobaciones manuales en curso.

---

## 🗺️ Mapa del Módulo

```
Módulo 5
├── Fase 5.1 → Servicio de confirmación compartido y la regla de la confirmación tardía
├── Fase 5.2 → Backend: Webhook idempotente de confirmación del Core
├── Fase 5.3 → Backend: Jobs de expiración y consulta activa, por solicitud
├── Fase 5.4 → Backend: Consulta pública de estado por código de seguimiento
├── Fase 5.5 → App Móvil: detección de confirmación, comprobante y consulta de estado
└── Fase 5.6 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 5.1 — Servicio de Confirmación Compartido y la Regla de la Confirmación Tardía

## Por qué el webhook y el polling deben compartir exactamente la misma lógica

El webhook (HU-D4) y la consulta activa opcional (HU-D5) son dos caminos
distintos para enterarse de lo mismo: que el Core confirmó el cobro. Ambos
llaman al mismo `ConfirmacionCobroService::confirmar()`, sin excepción, para
que la lógica nunca se desincronice entre los dos caminos.

## Una regla que sigue siendo necesaria, aunque más simple que antes

Puede llegar una confirmación del Core para una solicitud que Canchas ya
marcó `expirada` (el job de expiración le ganó por segundos a la
confirmación). Igual que en la arquitectura anterior, **la solicitud nunca
se revive** — podría chocar contra un horario que mientras tanto ya tomó
otra persona. Se registra como una **confirmación tardía**, y el
Administrador queda con el rastro en `auditoria` para gestionar por fuera
del sistema lo que corresponda (esto ahora es, en última instancia, un
problema a coordinar con el Core, que es quien tiene el dinero real).

## El servicio, más corto que en la versión anterior

```php
class ConfirmacionCobroService
{
    public function confirmar(SolicitudReserva $solicitud, ?float $montoConfirmado = null): void
    {
        if ($solicitud->estado === EstadoSolicitudReserva::Confirmada) {
            return; // idempotencia: ya se procesó, no hay nada más que hacer
        }

        if ($solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            $this->registrarConfirmacionTardia($solicitud, $montoConfirmado);
            return;
        }

        if ($montoConfirmado !== null && abs($montoConfirmado - (float) $solicitud->monto_total) > 0.01) {
            // No bloquea la confirmación —Canchas no concilia activamente,
            // eso es tarea del Core— pero deja un rastro para revisión.
            $this->auditoria->registrar(
                'solicitudes_reserva', $solicitud->id, 'discrepancia_monto_confirmado',
                null, ['monto_total' => $solicitud->monto_total], ['monto_confirmado' => $montoConfirmado]
            );
        }

        try {
            DB::transaction(function () use ($solicitud, $montoConfirmado) {
                $solicitud->update([
                    'estado'           => EstadoSolicitudReserva::Confirmada,
                    'monto_confirmado' => $montoConfirmado,
                ]);

                foreach ($solicitud->detalles as $detalle) {
                    Reserva::create([
                        'solicitud_reserva_id'         => $solicitud->id,
                        'solicitud_reserva_detalle_id' => $detalle->id,
                        'codigo_reserva'                => $this->generarCodigoReserva(),
                        'campo_id'                      => $detalle->campo_id,
                        'fecha_reserva'                 => $detalle->fecha_reserva,
                        'hora_inicio'                   => $detalle->hora_inicio,
                        'hora_fin'                      => $detalle->hora_fin,
                        'monto_pagado'                  => $detalle->tarifa_aplicada,
                        'confirmado_en'                 => now(),
                    ]);
                }
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                return; // UNIQUE sobre reservas.solicitud_reserva_detalle_id: ya procesado
            }
            throw $e;
        }
    }
}
```

Comparado con la versión anterior, este método ya no escribe primero en
`pagos_confirmados` y después en `reservas` —dos inserciones, dos posibles
puntos de fallo—. Ahora es una sola tabla la que importa, y la misma
restricción que ya protegía la integridad de `reservas` por otro motivo es
la que también protege contra el doble procesamiento.

---

## Tareas de la Fase 5.1

```
[ ] Crear ConfirmacionCobroService
    → app/Services/ConfirmacionCobroService.php, tal como se muestra
      arriba, incluyendo confirmar() y registrarConfirmacionTardia()
      (esta última llama a AuditoriaService::registrar() con
      accion='confirmacion_tardia_no_conciliada').

[ ] Crear el generador de código de reserva
    → Igual criterio que ya se aplicó a codigo_seguimiento: código
      corto alfanumérico único, con reintento en caso de colisión.

[ ] Tests
    → confirmar() sobre una solicitud 'pendiente' la deja 'confirmada'
      y genera una reserva por cada franja.
    → Llamar a confirmar() dos veces seguidas no genera reservas
      duplicadas.
    → confirmar() sobre una solicitud ya 'expirada' no la revive, no
      genera reservas, y genera una fila en auditoria con la acción
      'confirmacion_tardia_no_conciliada'.
    → confirmar() con un monto_confirmado que no coincide con
      monto_total igual confirma la solicitud, pero deja una fila en
      auditoria con la discrepancia.

[ ] Commit de la fase
    → Mensaje: "feat(recaudaciones): servicio de confirmación compartido y regla de confirmación tardía"
```

---

# FASE 5.2 — Backend: Webhook Idempotente de Confirmación del Core

Implementa HU-D4. Un webhook es, por definición, un endpoint que cualquiera
en internet puede intentar llamar — verificar que la llamada realmente
viene del Core y no de un tercero simulándola no es opcional.

## Un solo webhook, no uno por proveedor

La versión anterior de este módulo tenía un controlador que resolvía "cuál
proveedor" antes de verificar la firma, porque podían llegar webhooks de
distintos bancos. Acá **solo hay un remitente posible: el Core de
Recaudaciones**. El endpoint es uno solo, y la verificación de firma vive en
un único lugar, no repartida entre varias clases de pasarela.

Igual que con las llamadas salientes, el mecanismo exacto de verificación
—HMAC con secreto compartido, lo que el Core defina— depende de su
documentación real. Lo no negociable es que el webhook rechace con 401
cualquier solicitud que no la pase.

---

## Tareas de la Fase 5.2

```
[ ] Agregar verificarFirma() y parsearWebhook() a RecaudacionesApiClientInterface
    → Amplía la interfaz del Módulo 4 con estos dos métodos.
      verificarFirma() valida el request contra el secreto compartido
      (RECAUDACIONES_WEBHOOK_SECRET, nueva variable de entorno).
      parsearWebhook() devuelve un DTO con referenciaExterna (o
      referenciaRecaudaciones), montoConfirmado (si el Core lo incluye)
      y el payload crudo.
    → RecaudacionesApiClientSimulado implementa una verificación simple
      (ej. un header de prueba), suficiente para testear el flujo
      completo sin depender del Core real.

[ ] Crear el WebhookRecaudacionesController
    → app/Http/Controllers/Api/V1/WebhookRecaudacionesController.php —
      vive fuera del namespace Public/, en su propio espacio: no
      requiere sesión de usuario, pero tampoco es tráfico de la app
      móvil, es tráfico servidor-a-servidor del Core.
    → POST /api/v1/webhooks/recaudaciones (una sola ruta, sin parámetro
      de proveedor).
    → Flujo: verifica la firma (401 si falla), parsea el payload, busca
      la solicitud por referencia_recaudaciones o por
      codigo_seguimiento (cualquiera de las dos, por la redundancia
      diseñada en el Módulo 4).
    → Si no encuentra la solicitud: responde 200 igual (para que el
      Core no reintente indefinidamente) pero deja un log de
      advertencia.
    → Si la encuentra: llama a ConfirmacionCobroService::confirmar() y
      responde 200.

[ ] Tests
    → Un webhook con firma inválida responde 401 y no crea ninguna
      reserva.
    → Un webhook válido para una solicitud pendiente la confirma
      correctamente.
    → Enviar el mismo webhook dos veces seguidas —la segunda llamada
      responde 200 sin generar ningún registro adicional. Mismo
      criterio de aceptación explícito que en la versión anterior, ahora
      validando la restricción UNIQUE de reservas en vez de la de
      pagos_confirmados.
    → Un webhook para una solicitud ya expirada no genera reservas y
      deja la fila de confirmación tardía en auditoria.

[ ] Commit de la fase
    → Mensaje: "feat(recaudaciones): webhook idempotente de confirmación con verificación de firma"
```

---

# FASE 5.3 — Backend: Jobs de Expiración y Consulta Activa, por Solicitud

Implementa HU-05 (reubicada, igual que en la versión anterior de este
módulo) y HU-D5. Mismo diseño de siempre: cada solicitud despacha su propio
job diferido al crearse, apoyado en la cola de Redis que quedó lista desde
el Módulo 0.

```php
class ExpirarSolicitudJob implements ShouldQueue
{
    public function __construct(private string $solicitudReservaId) {}

    public function handle(AuditoriaService $auditoria): void
    {
        $solicitud = SolicitudReserva::find($this->solicitudReservaId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return; // ya se resolvió por otra vía
        }

        $solicitud->update(['estado' => EstadoSolicitudReserva::Expirada]);

        $auditoria->registrar('solicitudes_reserva', $solicitud->id, 'expirar_automaticamente', null,
            ['estado' => 'pendiente'], ['estado' => 'expirada']);
    }
}
```

```php
class PollingSolicitudJob implements ShouldQueue
{
    public function __construct(private string $solicitudReservaId) {}

    public function handle(RecaudacionesApiClientInterface $client, ConfirmacionCobroService $confirmacion): void
    {
        $solicitud = SolicitudReserva::find($this->solicitudReservaId);

        if (! $solicitud || $solicitud->estado !== EstadoSolicitudReserva::Pendiente) {
            return;
        }

        if (now()->greaterThan($solicitud->expira_en)) {
            return; // que lo resuelva ExpirarSolicitudJob
        }

        $estado = $client->consultarEstado($solicitud->referencia_recaudaciones);

        if ($estado?->pagado) {
            $confirmacion->confirmar($solicitud, $estado->montoConfirmado);
            return;
        }

        $intervalo = (int) ParametroSistema::find('polling_intervalo_segundos')->valor;
        self::dispatch($this->solicitudReservaId)->delay(now()->addSeconds($intervalo));
    }
}
```

---

## Tareas de la Fase 5.3

```
[ ] Agregar consultarEstado() a RecaudacionesApiClientInterface
    → Ausente hasta ahora porque el Módulo 4 solo necesitaba
      solicitarCobro(). Se agrega aquí: consultarEstado(string
      $referencia): ?EstadoCobroDTO, implementada en
      RecaudacionesApiClient (real, con el mismo TODO honesto sobre el
      contrato exacto) y en RecaudacionesApiClientSimulado.

[ ] Crear ExpirarSolicitudJob y PollingSolicitudJob
    → Tal como se muestran arriba, en app/Jobs/.

[ ] Actualizar SolicitudReservaService::crear() del Módulo 4
    → Justo después de que RecaudacionesApiClient devuelve una
      respuesta exitosa, despachar ambos jobs:
      ExpirarSolicitudJob::dispatch($solicitud->id)->delay($solicitud->expira_en)
      y PollingSolicitudJob::dispatch($solicitud->id)->delay(now()->addSeconds($intervalo)).
    → Si la llamada al Core falla (Módulo 4, Fase 4.2), no se despacha
      ningún job — el estado ya quedó resuelto de forma síncrona en ese
      mismo momento.

[ ] Crear el comando de barrido de seguridad
    → app/Console/Commands/ExpirarSolicitudesVencidas.php — busca toda
      solicitud con estado 'pendiente' y expira_en < now(), aplicando
      la misma lógica de ExpirarSolicitudJob a cada una.
    → Registrar en routes/console.php:
      Schedule::command('solicitudes:expirar-vencidas')->everyFiveMinutes().

[ ] Configurar QUEUE_CONNECTION=redis
    → Cambiar de 'sync' (desarrollo, desde el Módulo 0) a 'redis'.
      Levantar un worker con php artisan queue:work redis.

[ ] Tests
    → ExpirarSolicitudJob sobre una solicitud vencida y pendiente la
      marca 'expirada'.
    → ExpirarSolicitudJob sobre una solicitud ya 'confirmada' no la toca.
    → PollingSolicitudJob que consulta y encuentra la solicitud pagada
      la confirma igual que lo haría el webhook.
    → PollingSolicitudJob que no encuentra pago todavía se reprograma a
      sí mismo respetando el intervalo configurado.
    → El comando de barrido expira correctamente una solicitud "huérfana
      de job" (creada sin pasar por SolicitudReservaService::crear()).

[ ] Commit de la fase
    → Mensaje: "feat(recaudaciones): expiración y consulta activa por solicitud, con barrido de seguridad"
```

---

# FASE 5.4 — Backend: Consulta Pública de Estado por Código de Seguimiento

Implementa HU-D7. El mismo endpoint que, en la Fase 5.5, la pantalla de
cobro usará para detectar automáticamente cuándo se confirmó.

---

## Tareas de la Fase 5.4

```
[ ] Crear EstadoSolicitudResource
    → Expone: codigo_seguimiento, estado, monto_total, expira_en (si
      sigue activa), y si el estado es 'confirmada', la lista de
      reservas (codigo_reserva, nombre del campo, fecha, hora_inicio,
      hora_fin).
    → NO expone nombre_pagador, telefono_pagador, ni
      referencia_recaudaciones (esta última es un detalle interno de
      la integración con el Core, no algo que el ciudadano necesite ver).

[ ] Crear el endpoint
    → GET /api/v1/public/solicitudes-reserva/{codigo_seguimiento}/estado
    → 404 genérico si el código no existe. Mismo throttle básico del
      Módulo 3.

[ ] Tests
    → Consultar un código válido de una solicitud confirmada devuelve
      sus reservas correctamente.
    → Consultar un código inexistente devuelve 404.
    → La respuesta nunca incluye datos de contacto del solicitante ni
      la referencia interna del Core.

[ ] Commit de la fase
    → Mensaje: "feat(publico): consulta de estado de solicitud por código de seguimiento"
```

---

# FASE 5.5 — App Móvil: Detección de Confirmación, Comprobante y Consulta de Estado

Implementa HU-D6 y la parte de interfaz de HU-D7. Cierra el punto que el
Módulo 4 dejó deliberadamente abierto en la pantalla de cobro.

---

## Tareas de la Fase 5.5

```
[ ] Actualizar pago_qr_screen.dart (creada en el Módulo 4)
    → Mientras la pantalla está visible, consultar
      GET /public/solicitudes-reserva/{codigo}/estado cada 5 segundos.
    → Si el estado pasa a 'confirmada', navegar automáticamente a la
      pantalla de comprobante.
    → Si pasa a 'expirada', mostrar el mensaje de expiración (ya
      existente desde el Módulo 4).
    → Si pasa a 'rechazada' en este punto —a diferencia del 503
      inmediato del Módulo 4, que ocurre antes de que exista ningún
      QR—, esto significa que el Core sí llegó a procesar el intento
      pero lo rechazó. Mostrar un mensaje distinto y apropiado ("tu
      pago no pudo procesarse, intenta nuevamente"), para no confundir
      dos situaciones distintas con el mismo texto.

[ ] Crear la pantalla de comprobante digital
    → screens/comprobante_screen.dart — codigo_seguimiento y, por cada
      reserva generada, su codigo_reserva, campo, fecha y horario.

[ ] Crear la pantalla de consulta de estado
    → screens/consultar_estado_screen.dart — accesible desde la
      pantalla principal en cualquier momento, con un campo para
      ingresar manualmente un código y ver su estado.

[ ] Commit de la fase
    → Mensaje: "feat(mobile): detección de confirmación, comprobante digital y consulta de estado"
```

---

# FASE 5.6 — Smoke Test Final y Commit de Cierre

---

## Checklist de cierre del Módulo 5

```
[ ] Flujo feliz completo: generar una solicitud (Módulo 4), simular su
    confirmación (llamando manualmente al webhook con
    RecaudacionesApiClientSimulado, o esperando a que el polling lo
    detecte), y confirmar que la pantalla de cobro navega
    automáticamente al comprobante con el/los código(s) de reserva
    correctos.

[ ] Enviar el mismo webhook de confirmación dos veces seguidas para la
    misma solicitud. Verificar que el número de filas en reservas
    coincide exactamente con el número de franjas — ni de más, ni de
    menos.

[ ] Crear una solicitud, dejarla vencer sin confirmación, y confirmar
    que queda 'expirada' automáticamente dentro del plazo configurado.

[ ] Crear una solicitud "huérfana de job" con expira_en en el pasado,
    correr el comando de barrido manualmente, y confirmar que igual
    queda expirada.

[ ] Simular una confirmación tardía: crear una solicitud, dejarla
    expirar, y recién después enviarle un webhook de confirmación.
    Confirmar que permanece 'expirada', que no se crea ninguna reserva,
    y que aparece una fila en auditoria con
    'confirmacion_tardia_no_conciliada'.

[ ] Desde la pantalla de "Consultar estado", ingresar el código de una
    solicitud confirmada de prueba y verificar que muestra sus reservas
    sin exponer datos de contacto ni la referencia interna del Core.

[ ] Confirmar que QUEUE_CONNECTION=redis está activo y que
    php artisan queue:work efectivamente procesa los jobs.

[ ] Los pipelines de CI de backend y mobile pasan en verde sobre un
    Pull Request que incluya todo el módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 5 - confirmación de cobro, comprobante y consulta de estado"
    → Tag sugerido: v0.7.0-reserva-cobro-parte2
```

> **Siguiente módulo:** con la Épica D completa, el siguiente módulo
> implementa la Épica F del Documento 3 v3 (renumerada de Módulo 6/7 a
> Módulo 5/6, ya que la Épica E desapareció): la pantalla de ocupación para
> el Funcionario de Control, el mapa global, y el dashboard gerencial —la
> primera vez que el sistema completo se mira "desde arriba", ahora con la
> nota pendiente de alinear con el Core sobre a quién le corresponde el
> reporte de ingresos consolidados.
