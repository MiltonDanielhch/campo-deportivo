# 1. Recomendación arquitectónica

No metas piscina dentro de la lógica de canchas.

Hoy canchas es:

```text
campo + franja horaria + tarifa diurna/nocturna/unica + EXCLUDE anti-solape
```

Piscina es:

```text
campo + turno + perfil + cantidad de personas + cupo disponible + tarifa por perfil/horario
```

Son invariantes distintos. Si lo mezclás en `solicitudes_reserva` / `solicitud_reserva_detalles`, terminás rompiendo anti-doble-reserva, grilla, disponibilidad, pagos y reportes.

Propuesta: **módulo separado `piscina`**, pero reutilizando el espejo SIREB y el flujo de cobro/polling/webhook lo más posible.

---

# 2. Reglas de negocio que hay que confirmar antes de codificar

Estas son decisiones de PO/negocio. Necesito que me las confirmes o que me digas “usá default sensato”.

## 2.1 Turnos de piscina

¿La piscina se reserva por turnos fijos?

Ejemplo:

```text
08:00–10:00
10:00–12:00
14:00–16:00
16:00–18:00
18:00–20:00
```

O ¿se reserva por hora suelta como cancha?

Mi recomendación: **turnos fijos configurables por admin**, no hora suelta.

## 2.2 Perfiles

Los perfiles ya vienen del espejo SIREB:

```text
mayor_12
menor_12
institucion
```

¿Se pueden reservar mezclados en una misma solicitud?

Ejemplo:

```text
3 mayores + 2 menores
```

Mi recomendación: sí, pero cada línea lleva `perfil`, `cantidad`, `tarifa_sireb_id`.

## 2.3 Horario diurno/nocturno en piscina

Tus tarifas de piscina tienen etiquetas tipo:

```text
Bañista mayores de 12 (diurno)
Bañista mayores de 12 (nocturno)
Institución (diurno)
Institución (nocturno)
```

Entonces cada turno debe resolverse a `diurna` o `nocturna` según su hora de inicio y la `hora_inicio_noche` del campo piscina.

¿Correcto?

## 2.4 Cupos

¿Cada turno tiene un cupo total por perfil?

Ejemplo:

```text
Turno 18:00–20:00
Mayores de 12: 20 cupos
Menores de 12: 10 cupos
Institución: 1 sola institución o X personas
```

O ¿el cupo es global del turno?

Mi recomendación MVP: **cupo por turno + perfil**.

## 2.5 Instituciones

¿“Institución” significa una entidad que reserva muchos cupos, o una tarifa por persona institucional?

Ejemplo:

```text
Colegio X reserva 25 personas con tarifa institucional
```

o

```text
Institución paga Bs 20 por entrada
```

Esto afecta si `cantidad` es personas o “grupos”.

## 2.6 Pago

¿La piscina usa el mismo flujo SIREB?

Es decir:

```text
crear solicitud pendiente
crear liquidación en SIREB
QR/boleta simulada o real
polling/webhook confirma
reserva confirmada
```

Mi suposición: sí.

## 2.7 Asistencia/control

¿El funcionario debe marcar asistencia por QR/código como en canchas?

Para MVP puedo dejar tabla preparada, pero no UI compleja todavía.

---

# 3. Plan de implementación P7

## Fase P7.0 — Cerrar contrato de dominio

Objetivo: definir entidades y reglas sin tocar UI.

Entregables:

- Enums:
  - `PerfilPiscina` o usar string del espejo.
  - `EstadoPiscinaReserva`: `pendiente`, `confirmada`, `expirada`, `rechazada`, `cancelada`.
  - `ModalidadReservaCampo`: `franja_horaria`, `turno_por_perfil`, `sin_reserva_online`.
- Decisiones de negocio confirmadas.

Archivos nuevos probables:

```text
app/Enums/EstadoPiscinaReserva.php
app/Enums/ModalidadReservaCampo.php
```

---

## Fase P7.1 — Modelo de datos piscina

Objetivo: crear tablas limpias, sin contaminar canchas.

Tablas propuestas:

### `piscina_turnos`

Representa un turno configurable para un campo piscina.

Campos:

```text
id uuid pk
campo_id fk campos_deportivos
fecha date
hora_inicio time
hora_fin time
cupo_total int nullable
estado activo/inactivo
observacion text nullable
creado_en
actualizado_en
```

Índice único:

```text
(campo_id, fecha, hora_inicio, hora_fin)
```

### `piscina_turno_perfiles`

Cupo por perfil dentro de un turno.

Campos:

```text
id
turno_id fk piscina_turnos
perfil string mayor_12/menor_12/institucion
cupo int
reservado int default 0
disponible computed o service
tarifa_sireb_id nullable
precio_unitario decimal
estado activo/inactivo
```

Índice único:

```text
(turno_id, perfil)
```

Alternativa más simple: meter `perfil`, `cupo`, `reservado` directamente en `piscina_turnos` si un turno es por perfil. Pero creo que turno con varios perfiles es más flexible.

### `piscina_reservas`

Solicitud principal de pago/reserva.

Campos:

```text
id uuid pk
codigo_seguimiento string unique
campo_id fk
estado enum
monto_total decimal
nombre_pagador
telefono_pagador
ci_nit_pagador
ip_cobrador
expira_en
referencia_recaudaciones nullable
liquidacion_id nullable
datos_cobro_pendiente jsonb nullable
motivo_rechazo nullable
monto_confirmado nullable
creado_en
actualizado_en
```

### `piscina_reserva_detalles`

Líneas de la reserva.

Campos:

```text
id
reserva_id fk piscina_reservas
turno_id fk piscina_turnos
perfil string
cantidad int
precio_unitario decimal
tarifa_sireb_id string nullable
servicio_sireb_id string nullable
etiqueta_sireb string nullable
hora_inicio time
hora_fin time
fecha date
```

Esto permite:

```text
Reserva RES-...
- 3 mayores de 12 turno 18:00
- 2 menores de 12 turno 18:00
```

### `piscina_asistencias` opcional

```text
id
detalle_id fk
marcada_en
funcionario_id
codigo_escaneado
```

---

## Fase P7.2 — Resolver de tarifas piscina desde el espejo SIREB

Objetivo: no duplicar lógica de etiquetas.

Nuevo service:

```text
app/Services/Piscina/PiscinaTarifaResolver.php
```

Responsabilidad:

Dado:

```php
campo_id
perfil
hora_inicio
```

devuelve la tarifa espejo válida:

```php
TarifaCampo::query()
    ->where('campo_id', $campoId)
    ->where('perfil', $perfil)
    ->where('liquidable', true)
    ->where('tarifario_estado', 'vigente')
    ->whereNull('vigente_hasta')
    ->whereIn('clasificacion', ['diurna', 'nocturna', 'unica'])
    ->first();
```

Pero con regla horaria:

- si `clasificacion = unica`, sirve para cualquier turno;
- si `clasificacion = diurna/nocturna`, se elige según `hora_inicio` vs `campo.hora_inicio_noche`.

Fail-closed:

- si no hay tarifa para ese perfil/horario, el turno no se ofrece.

Esto reutiliza el espejo que ya está verde.

---

## Fase P7.3 — Disponibilidad pública de piscina

Nuevo endpoint:

```http
GET /api/v1/public/piscina/campos/{campoId}/turnos?fecha=2026-10-16
```

Respuesta:

```json
{
  "data": {
    "campo_id": "...",
    "fecha": "2026-10-16",
    "abierto": true,
    "turnos": [
      {
        "id": "...",
        "hora_inicio": "18:00",
        "hora_fin": "20:00",
        "horario": "nocturna",
        "perfiles": [
          {
            "perfil": "mayor_12",
            "etiqueta": "Mayores de 12",
            "precio": 15,
            "cupo": 20,
            "disponible": 17,
            "tarifa_sireb_id": "..."
          },
          {
            "perfil": "menor_12",
            "etiqueta": "Menores de 12",
            "precio": 10,
            "cupo": 10,
            "disponible": 10,
            "tarifa_sireb_id": "..."
          }
        ]
      }
    ]
  }
}
```

Service:

```text
app/Services/Piscina/PiscinaDisponibilidadService.php
```

Reglas:

- solo turnos activos;
- solo fecha futura;
- solo perfiles con tarifa espejo vigente;
- disponible = cupo - reservado confirmado/pendiente según regla;
- si no hay turnos, `abierto: false` o lista vacía.

---

## Fase P7.4 — Crear reserva piscina

Endpoint:

```http
POST /api/v1/public/piscina/reservas
```

Payload:

```json
{
  "fecha": "2026-10-16",
  "turno_id": "...",
  "items": [
    { "perfil": "mayor_12", "cantidad": 3 },
    { "perfil": "menor_12", "cantidad": 2 }
  ],
  "nombre_pagador": "Juan Pérez",
  "telefono_pagador": "70000000",
  "ci_nit_pagador": "12345678",
  "ip_cobrador": "..."
}
```

Service:

```text
app/Services/Piscina/PiscinaReservaService.php
```

Flujo:

1. Validar campo piscina activo y modalidad `turno_por_perfil`.
2. Validar turno existente, futuro, activo.
3. Validar items:
   - perfil permitido;
   - cantidad > 0;
   - no excede disponible.
4. Resolver tarifas desde espejo.
5. Calcular monto:

```text
Σ precio_unitario * cantidad
```

6. Transacción:
   - crear `piscina_reservas` pendiente;
   - crear `piscina_reserva_detalles`;
   - reservar cupo de forma atómica.
7. Después de commit, crear liquidación SIREB.
8. Si SIREB falla, despachar reintento igual que canchas.
9. Dispatch expiración + polling.

Concurrencia:

Para evitar overbooking, usar lock en turno/perfil:

```php
PiscinaTurnoPerfil::where('turno_id', $turnoId)
    ->where('perfil', $perfil)
    ->lockForUpdate()
    ->first();
```

O constraint + transacción.

---

## Fase P7.5 — Liquidación SIREB para piscina

Hay que decidir cómo mandar items a SIREB.

Opción recomendada:

```json
items: [
  { "tarifa_id": "tarifa_sireb_mayor_12_nocturno", "cantidad": 3 },
  { "tarifa_id": "tarifa_sireb_menor_12_nocturno", "cantidad": 2 }
]
```

Es decir, `cantidad` = personas.

Si SIREB espera `cantidad = 1` por persona, entonces habría que mandar N items. Pero normalmente una liquidación acepta cantidad. Hay que confirmar con el contrato real de SIREB.

Mientras tanto, el simulado puede manejar `cantidad`.

---

## Fase P7.6 — Confirmación / polling / webhook

Ideal: reutilizar la lógica existente, pero genérica.

Hoy tenés:

```text
PollingSolicitudJob
ExpirarSolicitudJob
ConfirmacionCobroService
WebhookRecaudacionesController
```

Esos están atados a `SolicitudReserva`.

Para piscina hay dos caminos:

### Camino A — rápido pero duplicado

Crear:

```text
PiscinaPollingJob
PiscinaExpirarJob
PiscinaConfirmacionService
```

Ventaja: no tocan canchas.

Desventaja: duplicación.

### Camino B — limpio pero más grande

Extraer un contrato común:

```text
App\Contracts\ReservaPagable
```

y servicios genéricos que reciban:

```text
modelo
codigo_seguimiento
liquidacion_id
detalles
```

Ventaja: reusable.

Desventaja: refactor de flujo verde.

Mi recomendación para no arriesgar lo que ya está verde: **Camino A en MVP**, con interfaces parecidas, y después un refactor si hace falta.

---

## Fase P7.7 — Panel admin piscina

Endpoints admin:

```http
GET    /api/v1/admin/piscina/campos/{campoId}/turnos
POST   /api/v1/admin/piscina/turnos
PATCH  /api/v1/admin/piscina/turnos/{turnoId}
DELETE /api/v1/admin/piscina/turnos/{turnoId}
GET    /api/v1/admin/piscina/reservas
GET    /api/v1/admin/piscina/reservas/{id}
POST   /api/v1/admin/piscina/reservas/{id}/cancelar
```

UI admin:

```text
Piscina → Turnos
Piscina → Reservas
```

Turnos:

- crear turno por fecha/rango;
- asignar cupo por perfil;
- ver ocupación.

Reservas:

- listar pendientes/confirmadas/expiradas;
- ver detalle por perfil;
- cancelar si aplica;
- marcar asistencia después.

---

## Fase P7.8 — Web pública piscina

Hoy `CampoDetalle.tsx` asume cancha. Para piscina hay que bifurcar presentación.

Opción limpia: agregar al campo una modalidad:

```text
modalidad_reserva = franja_horaria | turno_por_perfil | sin_reserva_online
```

Entonces:

- canchas: `franja_horaria`;
- piscina: `turno_por_perfil`;
- servicios ventanilla: `sin_reserva_online`.

En `CampoDetalle.tsx`:

```tsx
if (campo.modalidad_reserva === 'turno_por_perfil') {
  return <PiscinaDetalle campo={campo} />;
}

return <CanchaDetalle campo={campo} />;
```

Nuevos componentes:

```text
web-public/src/components/piscina/SelectorTurnosPiscina.tsx
web-public/src/components/piscina/FormReservaPiscina.tsx
web-public/src/pages/PiscinaReserva.tsx
web-public/src/services/piscinaService.ts
web-public/src/types/piscina.ts
```

Flujo público:

1. Usuario entra a piscina.
2. Ve fecha + turnos.
3. Elige turno.
4. Elige cantidad por perfil.
5. Ve monto.
6. Completa datos pagador.
7. Crea reserva.
8. Va a pago con QR/boleta simulada.
9. Polling confirma.
10. Comprobante piscina.

---

## Fase P7.9 — Comprobante piscina

Puede reutilizar `Comprobante.tsx` o crear uno específico.

Datos:

```text
Código de seguimiento
Campo: H. Piscina Olímpica
Fecha
Turno
Perfiles y cantidades
Monto
QR de asistencia opcional
```

---

## Fase P7.10 — Tests

Tests mínimos:

Backend:

```text
tests/Feature/Piscina/PiscinaTarifaResolverTest.php
tests/Feature/Piscina/PiscinaDisponibilidadTest.php
tests/Feature/Piscina/PiscinaReservaCreationTest.php
tests/Feature/Piscina/PiscinaOverbookingTest.php
tests/Feature/Piscina/PiscinaPaymentFlowTest.php
tests/Feature/Piscina/PiscinaAdminTurnosTest.php
```

Casos clave:

- piscina no entra a grilla de canchas;
- tarifa por perfil resuelve diurna/nocturna;
- si no hay tarifa vigente, turno no ofrecible;
- no se puede reservar más que cupo;
- dos requests concurrentes no overbookean;
- reserva pendiente expira y libera cupo;
- confirmación reduce cupo definitivo;
- liquidación SIREB usa `tarifa_sireb_id` correctos.

---

# 4. Diseño mínimo que yo implementaría primero

Para no hacer una fase gigante, MVP P7:

## Backend MVP

- tabla `piscina_turnos`;
- tabla `piscina_turno_perfiles`;
- tabla `piscina_reservas`;
- tabla `piscina_reserva_detalles`;
- resolver tarifa desde `tarifas_campo`;
- endpoint público turnos;
- endpoint público crear reserva;
- jobs expirar/polling duplicados pero simples;
- admin CRUD turnos;
- admin listado reservas.

## Frontend MVP

- web pública: página piscina con selector de turno/perfil/cantidad;
- pago: reutilizar pantalla de pago actual si el backend devuelve misma shape;
- admin: pantalla simple de turnos.

## No entra en MVP

- cuotas;
- membresías;
- instructores;
- reservas recurrentes;
- reportes avanzados piscina;
- mobile piscina;
- validación manual de instituciones;
- check-in por QR complejo.

---

# 5. Archivos que necesito que me pases para empezar

No me pases todo el repo. Necesito inventario preciso.

## 5.1 Backend: esquema actual

Corré esto desde `backend/`:

```powershell
php artisan schema:dump
```

Después mandame el archivo generado, normalmente:

```text
backend/database/schema/pgsql-schema.sql
```

o si usás otro driver:

```text
backend/database/schema/mysql-schema.sql
backend/database/schema/sqlite-schema.sql
```

Si el archivo es muy grande, no lo pegues entero. Mandame solo las tablas relevantes:

```text
campos_deportivos
tarifas_campo
solicitudes_reserva
solicitud_reserva_detalles
reservas
auditorias
parametros_sistema
```

Pero si podés, el schema completo ayuda.

## 5.2 Backend: modelos clave

Pasame estos archivos enteros:

```text
app/Models/CampoDeportivo.php
app/Models/TarifaCampo.php
app/Models/SolicitudReserva.php
app/Models/SolicitudReservaDetalle.php
app/Models/Reserva.php
```

## 5.3 Backend: servicios clave

Ya vi partes, pero necesito completos:

```text
app/Services/DisponibilidadService.php
app/Services/ConfirmacionCobroService.php
app/Services/CatalogoSirebService.php
app/Services/SolicitudReservaService.php
```

De `SolicitudReservaService.php` ya vimos mucho, pero conviene completo para replicar patrón de jobs/liquidación.

## 5.4 Backend: jobs y comandos

```text
app/Jobs/ExpirarSolicitudJob.php
app/Jobs/PollingSolicitudJob.php
app/Jobs/ReintentarSolicitudJob.php
app/Console/Commands/SincronizarTarifasSireb.php
```

## 5.5 Backend: controllers públicos relevantes

```text
app/Http/Controllers/Api/V1/Public/CampoController.php
app/Http/Controllers/Api/V1/Public/DisponibilidadController.php
app/Http/Controllers/Api/V1/Public/SolicitudReservaController.php
app/Http/Controllers/Api/V1/Public/SolicitudEstadoController.php
```

## 5.6 Backend: routes

Corré:

```powershell
php artisan route:list --path=public --columns=method,uri,name,action
```

y pegame la salida.

También:

```powershell
php artisan route:list --path=campos --columns=method,uri,name,action
```

## 5.7 Backend: enums/DTOs

Si existen:

```text
app/Enums/EstadoSolicitudReserva.php
app/DTOs/SolicitudReservaDTO.php
app/DTOs/FranjaSolicitadaDTO.php
app/DTOs/SolicitudCreadaDTO.php
```

## 5.8 Frontend web-public

Pasame estos:

```text
web-public/src/types/campo.ts
web-public/src/store/carritoStore.ts
web-public/src/lib/api.ts
web-public/src/pages/CampoDetalle.tsx
web-public/src/pages/Reserva.tsx
web-public/src/pages/Pago.tsx
web-public/src/components/campo/GrillaDisponibilidad.tsx
```

Ya vi varios, pero necesito versión actual completa para no parchear sobre memoria.

## 5.9 Frontend web-admin

Pasame:

```text
web-admin/src/types/parametricas.ts
web-admin/src/services/camposService.ts
web-admin/src/services/catalogoSirebService.ts
web-admin/src/pages/parametricas/Campos.tsx
web-admin/src/pages/parametricas/Tarifas.tsx
```

Si hay un formulario de creación/edición de campo:

```text
web-admin/src/components/parametricas/CampoFormDialog.tsx
```

---

# 6. Comandos rápidos para que me pases inventario sin saturar

Desde `backend/`:

```powershell
php artisan route:list --path=public --columns=method,uri,name,action
php artisan route:list --path=campos --columns=method,uri,name,action
Get-ChildItem app\Models -Filter *.php | Select-Object Name, Length
Get-ChildItem app\Services -Filter *.php | Select-Object Name, Length
Get-ChildItem app\Jobs -Filter *.php | Select-Object Name, Length
Get-ChildItem database\migrations | Select-Object Name, Length
```

Desde raíz del proyecto:

```powershell
Get-ChildItem web-public\src\types, web-public\src\store, web-public\src\lib -Recurse -Include *.ts,*.tsx | Select-Object FullName, Length
Get-ChildItem web-admin\src\types, web-admin\src\services, web-admin\src\pages\parametricas -Recurse -Include *.ts,*.tsx | Select-Object FullName, Length
```

---

# 7. Qué archivos crearíamos nosotros después

Cuando tenga el inventario, yo te armo bloques en este orden:

## Bloque P7.1 — migraciones + modelos

Archivos nuevos:

```text
database/migrations/xxxx_create_piscina_turnos_table.php
database/migrations/xxxx_create_piscina_turno_perfiles_table.php
database/migrations/xxxx_create_piscina_reservas_table.php
database/migrations/xxxx_create_piscina_reserva_detalles_table.php
app/Models/PiscinaTurno.php
app/Models/PiscinaTurnoPerfil.php
app/Models/PiscinaReserva.php
app/Models/PiscinaReservaDetalle.php
app/Enums/EstadoPiscinaReserva.php
app/Enums/ModalidadReservaCampo.php
```

Y migración para campo:

```text
database/migrations/xxxx_add_modalidad_reserva_to_campos_deportivos_table.php
```

## Bloque P7.2 — resolver tarifas + disponibilidad

```text
app/Services/Piscina/PiscinaTarifaResolver.php
app/Services/Piscina/PiscinaDisponibilidadService.php
app/Http/Controllers/Api/V1/Public/PiscinaController.php
```

## Bloque P7.3 — reserva + cobro

```text
app/Services/Piscina/PiscinaReservaService.php
app/DTOs/PiscinaReservaDTO.php
app/Http/Requests/Piscina/StorePiscinaReservaRequest.php
app/Jobs/ExpirarPiscinaReservaJob.php
app/Jobs/PollingPiscinaReservaJob.php
app/Jobs/ReintentarPiscinaReservaJob.php
app/Services/Piscina/PiscinaConfirmacionCobroService.php
```

## Bloque P7.4 — admin

```text
app/Http/Controllers/Api/V1/Admin/PiscinaTurnoController.php
app/Http/Controllers/Api/V1/Admin/PiscinaReservaController.php
app/Services/Piscina/PiscinaAdminService.php
```

## Bloque P7.5 — web pública

```text
web-public/src/types/piscina.ts
web-public/src/services/piscinaService.ts
web-public/src/components/piscina/SelectorTurnosPiscina.tsx
web-public/src/components/piscina/FormReservaPiscina.tsx
web-public/src/pages/PiscinaDetalle.tsx
web-public/src/pages/PiscinaReserva.tsx
```

## Bloque P7.6 — admin UI

```text
web-admin/src/types/piscina.ts
web-admin/src/services/piscinaService.ts
web-admin/src/pages/piscina/Turnos.tsx
web-admin/src/pages/piscina/Reservas.tsx
```

## Bloque P7.7 — tests

```text
tests/Feature/Piscina/*.php
```

---

# 8. Decisiones que necesito que respondas ya

Para arrancar, respondeme esto en formato corto:

1. ¿Piscina usa turnos fijos configurables? Sí/No.
2. ¿Un turno puede tener varios perfiles con cupo separado? Sí/No.
3. ¿La cantidad es personas? Sí/No.
4. ¿Institución también es personas o es grupo? Personas/Grupo.
5. ¿Se puede reservar con anticipación máxima de 60 días como canchas? Sí/No.
6. ¿Las reservas pendientes bloquean cupo hasta expirar? Sí/No.
7. ¿Confirmación reduce cupo definitivo? Sí/No.
8. ¿Quieres MVP sin asistencia por QR? Sí/No.
9. ¿Reutilizar pantalla de pago actual con QR simulado? Sí/No.
10. ¿Te parece crear módulo separado `piscina` en vez de meterlo en canchas? Sí/No.

Mi propuesta default si decís “usá sensato”:

```text
1 Sí
2 Sí
3 Sí
4 Personas
5 Sí
6 Sí
7 Sí
8 Sí, MVP sin asistencia compleja
9 Sí
10 Sí
```

---

# 9. Orden práctico para vos ahora

## Paso 1

Respondé las 10 decisiones de arriba.

## Paso 2

Pasame estos archivos/salidas:

Backend:

```text
app/Models/CampoDeportivo.php
app/Models/TarifaCampo.php
app/Models/SolicitudReserva.php
app/Models/SolicitudReservaDetalle.php
app/Models/Reserva.php
app/Services/DisponibilidadService.php
app/Services/ConfirmacionCobroService.php
app/Services/CatalogoSirebService.php
app/Services/SolicitudReservaService.php
```

Routes:

```powershell
php artisan route:list --path=public --columns=method,uri,name,action
```

Schema:

```powershell
php artisan schema:dump
```

y el archivo generado, o al menos las tablas relevantes.

Frontend:

```text
web-public/src/types/campo.ts
web-public/src/store/carritoStore.ts
web-public/src/lib/api.ts
web-admin/src/types/parametricas.ts
web-admin/src/services/camposService.ts
```

## Paso 3

Yo te devuelvo **Bloque P7.1**:

- migraciones;
- modelos;
- enums;
- scope/modalidad;
- comando seed de turnos de ejemplo;
- test básico de resolver tarifa piscina.

No toco UI hasta que el backend piscina tenga contrato claro.

---

# 10. Advertencia importante

En la base de conocimiento aparece un log viejo con:

```text
validarFranjaFutura(): Argument #1 ($franja) must be of type FranjaSolicitadaDTO, TarifaCampo given
```

Ese era el bug de shadowing `$grupo` en `SolicitudReservaService`. Si tu última corrida real fue:

```text
185 passed
```

entonces ya está resuelto y ese log viejo hay que ignorarlo.

Pero si al correr `php artisan test` vuelve a aparecer, primero arreglá eso antes de abrir P7, porque P7 va a tocar flujo de reserva/cobro y no conviene construir sobre un hot path rojo.

---

## Resumen ejecutivo

Piscina/P7 se implementa como **módulo separado**:

```text
turnos + perfiles + cupos + reserva por cantidad + tarifa desde espejo SIREB + pago SIREB
```

No se mete en la grilla de canchas.

Para empezar, necesito:

1. tus respuestas a las 10 reglas;
2. modelos/services/controllers actuales;
3. routes públicas;
4. schema dump;
5. tipos/frontend base.

Con eso genero el primer bloque de código sin adivinar.
