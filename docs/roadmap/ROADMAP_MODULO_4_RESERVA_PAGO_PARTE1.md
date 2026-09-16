# 📁 ROADMAP_MODULO_4_RESERVA_PAGO_PARTE1.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (*Spoke*) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 8–10 horas (antes 12–16 — desaparece toda la maquinaria de Circuit Breaker) · **Bloquea:** El módulo que confirma el pago (parte 2 de la Épica D)

> **Objetivo del Módulo:** Implementar HU-D1, HU-D2, HU-D3 y HU-D8 del
> Documento 3 v3: que un ciudadano pueda seleccionar una o varias franjas
> horarias libres, ingresar sus datos de contacto, y que el sistema le
> solicite el cobro correspondiente al **Core de Recaudaciones** — ya no a
> una pasarela propia. Este es, de todo el roadmap adaptado a Hub & Spoke,
> el módulo con el cambio más profundo: desaparece por completo el patrón
> Strategy y el Circuit Breaker que ocupaban buena parte de la versión
> anterior, y nace en código real la integración con el Core.

> **Lo que ya no hace falta hacer, y por qué:** la versión anterior de este
> módulo abría con una fase de "ajustes al modelo de datos" —agregar
> columnas para guardar la referencia pendiente de la pasarela, agregar un
> parámetro de timeout—. Esa fase **ya no existe**: `referencia_recaudaciones`
> y `monto_confirmado` se incorporaron directamente al Documento 2 v3 cuando
> se revisó ese documento, así que el modelo de datos ya está listo antes de
> llegar acá. El módulo pasa de 6 fases a 5.

> Este módulo **no confirma pagos todavía**. Termina en el momento en que el
> ciudadano ve el cobro solicitado en pantalla. La confirmación —webhook del
> Core, generación de reservas, comprobante— es el siguiente módulo.

---

## 🗺️ Mapa del Módulo

```
Módulo 4
├── Fase 4.1 → Backend: creación de la solicitud de reserva multi-franja
├── Fase 4.2 → Backend: integración con el Core de Recaudaciones
├── Fase 4.3 → App Móvil: carrito de selección e identidad del solicitante
├── Fase 4.4 → App Móvil: pantalla de cobro con cuenta regresiva
└── Fase 4.5 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 4.1 — Backend: Creación de la Solicitud de Reserva Multi-Franja

Implementa HU-D1 (recepción de la solicitud) y HU-D2 (creación protegida).
Esta fase pone a trabajar, contra tráfico real, la restricción `EXCLUDE`
que se construyó en el Módulo 1 — y en eso, **no cambió nada** respecto a la
arquitectura anterior. El problema de "dos personas, la misma franja" nunca
tuvo que ver con cómo se cobra.

## Dos capas de protección, exactamente como antes

1. **Verificación rápida (aplicativa):** se reutiliza la lógica del
   `DisponibilidadService` del Módulo 3 para devolver un error amigable en
   el caso común, sin tocar la base de datos.
2. **Escritura protegida (la garantía real):** el `INSERT` ocurre dentro de
   una transacción; si dos personas llegan a la misma franja al mismo
   tiempo, la restricción `no_solape_horario` decide cuál falla.

```php
try {
    $solicitud = DB::transaction(function () use ($datos, $expiracionMinutos) {
        $solicitudNueva = SolicitudReserva::create([
            'codigo_seguimiento' => $this->generarCodigoSeguimiento(),
            'monto_total'        => $this->calcularMontoTotal($datos->franjas),
            'nombre_pagador'     => $datos->nombrePagador,
            'telefono_pagador'   => $datos->telefonoPagador,
            'ci_nit_pagador'     => $datos->ciNitPagador,
            'estado'             => EstadoSolicitudReserva::Pendiente,
            'expira_en'          => now()->addMinutes($expiracionMinutos),
        ]);

        foreach ($datos->franjas as $franja) {
            $solicitudNueva->detalles()->create([
                'campo_id'        => $franja->campoId,
                'fecha_reserva'   => $franja->fecha,
                'hora_inicio'     => $franja->horaInicio,
                'hora_fin'        => $franja->horaFin,
                'tarifa_aplicada' => $this->tarifaVigente($franja->campoId),
            ]);
        }

        return $solicitudNueva;
    });
} catch (QueryException $e) {
    if ($e->getCode() === '23P01') {
        throw new FranjaNoDisponibleException(
            'Una de las franjas seleccionadas ya no está disponible.'
        );
    }
    throw $e;
}
```

Recién **después** de que esta transacción tiene éxito se llama al Core de
Recaudaciones (Fase 4.2) — nunca antes.

---

## Tareas de la Fase 4.1

```
[x] Crear el DTO FranjaSolicitadaDTO
    → app/DTOs/FranjaSolicitadaDTO.php (el mismo anticipado desde el
      Módulo 0): campo_id, fecha, hora_inicio, hora_fin.

[x] Crear el DTO SolicitudReservaDTO
    → nombre_pagador, telefono_pagador, ci_nit_pagador (nullable), y un
      arreglo de FranjaSolicitadaDTO.

[x] Crear la excepción FranjaNoDisponibleException
    → Se traduce a HTTP 409 (Conflict).

[x] Crear SolicitudReservaService::crear()
    → Implementa el flujo de dos capas de arriba. calcularMontoTotal()
      suma la tarifa vigente de cada franja, congelándola en
      tarifa_aplicada. Valida que cada franja esté dentro de la ventana
      permitida ya usada en el Módulo 3 (hoy hasta +60 días).
    → Al final, llama a RecaudacionesApiClient (Fase 4.2) — ver ahí el
      manejo de éxito y de fallo.

[x] Crear el SolicitudReservaController y su ruta pública
    → POST /api/v1/public/solicitudes-reserva (sin autenticación,
      mismo throttle básico del Módulo 3).

[x] Tests
    → Crear una solicitud con 2 franjas de fechas distintas genera 1
      fila en solicitudes_reserva y 2 en solicitud_reserva_detalle, con
      monto_total igual a la suma de ambas tarifas.
    → Intentar crear una solicitud para una franja ya cubierta por otra
      solicitud activa responde 409.
    → expira_en corresponde al parámetro
      solicitud_reserva_expiracion_minutos vigente.

[x] Commit de la fase
    → Mensaje: "feat(reserva): creación de solicitud de reserva multi-franja con protección de base de datos"
```

---

# FASE 4.2 — Backend: Integración con el Core de Recaudaciones

Implementa HU-D3 y HU-D8. Esta fase construye, por fin, sobre el esqueleto
`RecaudacionesApiClient` que quedó preparado desde el Módulo 0 — y es donde
se ve, en código real, cuánto se simplificó la arquitectura.

## Una advertencia honesta, igual que con las pasarelas antes

Este roadmap puede definir la **estructura** del cliente (interfaz, DTOs de
solicitud y respuesta, manejo de timeout y errores), pero no puede inventar
el contrato exacto de la API del Core —endpoints, formato del payload,
nombres de campo—. Esa información depende de la documentación de
integración que el equipo del Core entregue. Lo que sí queda resuelto acá es
la forma de trabajar con esa incertidumbre sin bloquear el desarrollo: un
cliente simulado que permite construir y probar todo el flujo sin acceso
real al Core.

## Por qué sigue existiendo una interfaz, sin que haya Circuit Breaker

A diferencia de la versión anterior, aquí **no hay múltiples proveedores
entre los cuales elegir** — hay un solo sistema externo, el Core. No hace
falta Strategy pattern para eso. La interfaz `RecaudacionesApiClientInterface`
existe por una razón distinta y más simple: poder **inyectar la
implementación simulada durante desarrollo y tests**, sin depender de que el
Core esté disponible. Es una interfaz para testabilidad, no para
polimorfismo en tiempo de ejecución — vale la pena tener clara esa
diferencia para no reconstruir, sin querer, la complejidad que
deliberadamente se eliminó.

## El diseño de la solicitud de cobro: doble referencia por seguridad

Canchas le envía al Core su propio `codigo_seguimiento` como referencia
externa, además de recibir de vuelta la `referencia_recaudaciones` del
Core. Esto da redundancia para el matching del webhook (módulo siguiente):
si el Core reenvía cualquiera de las dos referencias en su confirmación,
Canchas puede encontrar la solicitud correspondiente.

```php
final class SolicitudCobroDTO
{
    public function __construct(
        public readonly string $referenciaExterna,   // el codigo_seguimiento de Canchas
        public readonly float $monto,
        public readonly string $nombrePagador,
        public readonly string $telefonoPagador,
        public readonly ?string $ciNitPagador,
        public readonly string $descripcion,          // ej. "Reserva Cancha 3 - 12/09"
    ) {}
}

final class RespuestaCobroDTO
{
    public function __construct(
        public readonly string $referenciaRecaudaciones,
        public readonly ?string $qrString,
        public readonly ?string $qrImageBase64,
        public readonly ?string $checkoutUrl, // por si el Core devuelve un enlace en vez de QR directo
    ) {}
}
```

## Qué pasa si el Core no responde — resuelve HU-D8

A diferencia de la arquitectura anterior, donde "ambas pasarelas caídas"
abría un flujo de contingencia de hasta 2 horas, acá la respuesta es
**inmediata**: si Canchas no logra siquiera pedirle el cobro al Core, no
tiene sentido mantener el horario bloqueado a la espera de algo que ni
arrancó. La solicitud se marca `rechazada` en el momento, liberando la
franja de inmediato, y se le informa al ciudadano con un mensaje claro.

```php
try {
    $respuestaCore = $this->recaudacionesClient->solicitarCobro(
        new SolicitudCobroDTO(
            referenciaExterna: $solicitud->codigo_seguimiento,
            monto: $solicitud->monto_total,
            nombrePagador: $solicitud->nombre_pagador,
            telefonoPagador: $solicitud->telefono_pagador,
            ciNitPagador: $solicitud->ci_nit_pagador,
            descripcion: $this->describirSolicitud($solicitud),
        )
    );
} catch (ConnectionException|RequestException $e) {
    $solicitud->update(['estado' => EstadoSolicitudReserva::Rechazada]);

    $this->auditoria->registrar(
        'solicitudes_reserva', $solicitud->id, 'fallo_conexion_core',
        null, null, ['error' => $e->getMessage()]
    );

    throw new ServicioDeCobroNoDisponibleException(
        'El sistema de cobro no está disponible en este momento. Intenta nuevamente en unos minutos.'
    );
}

$solicitud->update(['referencia_recaudaciones' => $respuestaCore->referenciaRecaudaciones]);
```

Nótese que **no hace falta despachar ningún job de expiración** en este
camino de error — el estado ya quedó resuelto de forma síncrona, algo que
la arquitectura de contingencia anterior nunca podía garantizar.

---

## Tareas de la Fase 4.2

```
[x] Agregar el parámetro de timeout que faltaba
    → Documento 2 v3 no anticipó este valor porque no hacía falta hasta
      ahora: nueva fila en parametros_sistema,
      recaudaciones_timeout_segundos = 8.

[x] Crear RecaudacionesApiClientInterface
    → app/Integrations/Recaudaciones/RecaudacionesApiClientInterface.php
    → Un método: solicitarCobro(SolicitudCobroDTO $datos): RespuestaCobroDTO.

[x] Completar RecaudacionesApiClient (implementación real)
    → Usa el facade Http de Laravel, con
      Http::timeout($segundos)->withToken(RECAUDACIONES_API_TOKEN)->post(...).
    → El endpoint exacto, el formato del payload y de la respuesta
      quedan como TODO explícito hasta contar con la documentación
      oficial del Core.

[x] Crear RecaudacionesApiClientSimulado
    → Implementación exclusiva para desarrollo y pruebas, que permite
      forzar éxito, timeout o error de forma controlada. Se registra
      como el binding por defecto de RecaudacionesApiClientInterface en
      el entorno local/testing —nunca en producción—, mismo criterio de
      cautela ya aplicado a los seeders de desarrollo desde el Módulo 0.8.

[x] Crear ServicioDeCobroNoDisponibleException
    → Se traduce a un HTTP 503 (Service Unavailable) con el mensaje de
      HU-D8, distinto del 409 de FranjaNoDisponibleException —son dos
      situaciones distintas y la app necesita distinguirlas.

[x] Completar SolicitudReservaService::crear() (de la Fase 4.1)
    → Agregar el bloque de llamada al Core mostrado arriba, incluyendo
      el manejo de fallo con liberación inmediata de la franja.

[x] Tests (contra RecaudacionesApiClientSimulado)
    → Con el simulado devolviendo éxito, la solicitud queda con
      referencia_recaudaciones poblada y el QR/checkoutUrl disponible.
    → Con el simulado forzando una falla de conexión, la solicitud
      queda 'rechazada' de inmediato, se genera la fila en auditoria
      con accion='fallo_conexion_core', y una nueva solicitud para la
      misma franja puede crearse sin chocar con el EXCLUDE —confirma
      que el horario efectivamente se liberó en el acto.
    → La respuesta HTTP ante un fallo de conexión es 503 con el mensaje
      de HU-D8, no un 500 genérico.

[x] Commit de la fase
    → Mensaje: "feat(recaudaciones): integración con el Core vía RecaudacionesApiClient"
```

---

# FASE 4.3 — App Móvil: Carrito de Selección e Identidad del Solicitante

Implementa HU-D1. Retoma el punto que el Módulo 3 dejó preparado, sin
conectar, en la grilla de disponibilidad. Sin cambios de fondo respecto a
la versión anterior de este módulo — esta pantalla nunca supo nada de
pasarelas ni de Circuit Breaker, solo arma la solicitud.

---

## Tareas de la Fase 4.3

```
[ ] Elegir un gestor de estado simple para el carrito
    → Se recomienda el paquete provider — suficiente para este alcance.

[ ] Crear CarritoReservaProvider
    → Lista de franjas seleccionadas (posiblemente de fechas distintas),
      con métodos para agregar, quitar, y calcular el monto total
      estimado en el cliente (el monto real y definitivo siempre lo
      calcula el backend).

[ ] Conectar la grilla horaria del Módulo 3 al carrito
    → Tocar un bloque 'libre' lo agrega o quita del carrito, con una
      marca visual de "seleccionado".

[ ] Crear la pantalla de resumen del carrito
    → screens/carrito_resumen_screen.dart — franjas elegidas, monto
      total estimado, opción de quitar cualquier franja, botón
      "Continuar".

[ ] Crear la pantalla de datos del solicitante
    → screens/datos_solicitante_screen.dart — nombre, teléfono
      (obligatorio), CI/NIT (opcional), validación básica.

[ ] Conectar el envío de la solicitud
    → Al confirmar, llamar a POST /public/solicitudes-reserva. Manejar
      dos respuestas de error distintas:
        • 409 (franja no disponible): identificar cuál franja falló,
          quitarla del carrito, invitar a revisar el resto o elegir
          otra.
        • 503 (servicio de cobro no disponible, HU-D8): mostrar el
          mensaje de "intenta más tarde" sin tocar el carrito —el
          ciudadano puede simplemente reintentar en unos minutos, su
          selección de franjas sigue siendo válida.

[ ] Commit de la fase
    → Mensaje: "feat(mobile): carrito de selección multi-franja y datos del solicitante"
```

---

# FASE 4.4 — App Móvil: Pantalla de Cobro con Cuenta Regresiva

## Una pantalla deliberadamente flexible

A diferencia de la versión anterior —donde Canchas sabía con certeza que
recibiría un QR, porque ella misma orquestaba la pasarela—, ahora el
contrato exacto de lo que el Core devuelve no está confirmado. Puede ser un
QR renderizable (string o imagen) o un enlace de checkout que se abre por
fuera de la app. La pantalla se diseña para aceptar cualquiera de los dos,
hasta que quede confirmado con el equipo del Core cuál es el caso real.

---

## Tareas de la Fase 4.4

```
[ ] Instalar el paquete qr_flutter
    → Para renderizar el QR cuando la respuesta incluye qrString. Si en
      cambio incluye qrImageBase64, se muestra con Image.memory().

[ ] Manejar el caso de checkoutUrl
    → Si la respuesta del Core no trae QR sino un enlace de checkout,
      abrirlo con url_launcher (navegador externo o WebView, según lo
      que el flujo del Core requiera).

[ ] Crear la pantalla de cobro
    → screens/pago_qr_screen.dart — muestra el QR o el botón de
      checkout según corresponda, el monto total, el
      codigo_seguimiento (visible, por si el ciudadano necesita
      referenciarlo en soporte), y una cuenta regresiva.
    → La cuenta regresiva se calcula contra el expira_en que devuelve
      el backend, nunca sumando minutos a la hora local del dispositivo.

[ ] Alcance explícito de esta fase
    → Esta pantalla, en este módulo, NO detecta automáticamente cuándo
      el Core confirmó el pago —esa conexión se construye en el
      siguiente módulo (parte 2 de la Épica D), junto con el webhook.
      Por ahora, al llegar la cuenta regresiva a cero, la pantalla
      muestra "el tiempo para pagar expiró" con un botón para volver a
      intentar la selección desde cero.

[ ] Commit de la fase
    → Mensaje: "feat(mobile): pantalla de cobro con cuenta regresiva"
```

---

# FASE 4.5 — Smoke Test Final y Commit de Cierre

## Objetivo de esta fase

Confirmar que las dos protecciones críticas del módulo —anti-doble-reserva,
y la resolución inmediata cuando el Core no responde— funcionan bajo
condiciones reales, no solo en tests aislados.

---

## Checklist de cierre del Módulo 4

```
[ ] Desde la app: seleccionar 2 franjas de fechas distintas del mismo
    campo, completar los datos del solicitante, y confirmar. Verificar
    en la base que se creó 1 fila en solicitudes_reserva y 2 en
    solicitud_reserva_detalle, con monto_total correcto.

[ ] expira_en de esa solicitud corresponde exactamente a los minutos
    configurados en parametros_sistema.

[ ] Prueba de concurrencia real (script de shell, no un test de
    PHPUnit): disparar dos solicitudes HTTP simultáneas pidiendo
    exactamente la misma franja. Confirmar que solo una responde 201 y
    la otra 409, y que solicitud_reserva_detalle solo tiene una fila
    viva para esa franja.

[ ] Con RecaudacionesApiClientSimulado devolviendo éxito, la app
    muestra el QR (o el botón de checkout) y una cuenta regresiva
    correcta.

[ ] Con RecaudacionesApiClientSimulado forzando una falla de conexión:
    la respuesta es 503 con el mensaje de HU-D8, la solicitud queda
    'rechazada' de inmediato (no 15 minutos después), y una nueva
    solicitud para la misma franja se puede crear sin problema
    inmediatamente después —confirma que el horario se liberó en el
    acto, no que quedó esperando un job de expiración.

[ ] La app distingue correctamente el mensaje de "franja no disponible"
    (409, invita a elegir otra) del de "servicio no disponible" (503,
    invita a reintentar más tarde) — no deben verse iguales para el
    ciudadano.

[ ] Los pipelines de CI de backend y mobile pasan en verde sobre un
    Pull Request que incluya todo el módulo.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 4 - solicitud de reserva y cobro delegado al Core"
    → Tag sugerido: v0.6.0-reserva-cobro-parte1
```

> **Siguiente módulo:** con la solicitud generándose correctamente y el
> cobro ya pedido al Core, el siguiente módulo completa la Épica D — el
> webhook idempotente que confirma el pago y genera las reservas
> definitivas (reutilizando la restricción `UNIQUE` de `reservas` como
> barrera de idempotencia, Documento 2 v3 Anexo A.2), el polling opcional,
> el comprobante digital, y la consulta pública de estado (HU-D4 a HU-D7).
