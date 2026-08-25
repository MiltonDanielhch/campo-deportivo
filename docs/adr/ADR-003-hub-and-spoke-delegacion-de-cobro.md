# ADR-003: Hub & Spoke — delegación de cobro al Core de Recaudaciones

## Estado

Aceptado

## Contexto

El GAD Beni está construyendo varios sistemas; Canchas es uno de varios
satélites posibles a futuro. Si cada satélite reimplementa su propia
integración bancaria, conciliación y facturación:

- El costo de mantenimiento se multiplica por cada sistema nuevo.
- El riesgo de seguridad (credenciales bancarias, tarjetas, webhooks)
  queda innecesariamente distribuido.
- Cada equipo necesitaría dominar AGETIC/SINTESIS, webhooks bancarios
  y facturación.

El Core de Recaudaciones es un sistema central autónomo (Laravel + React),
con su propio repositorio y equipo, que registra clientes (CI/NIT), genera
liquidaciones, integra pasarelas, recibe webhooks bancarios, procesa pagos
manuales, concilia y factura. **Es el único sistema del ecosistema que
habla con bancos.**

## Decisión

Canchas nunca se integra directamente con una pasarela de pago. Delega la
generación y confirmación del cobro al Core de Recaudaciones mediante una
API HTTP autenticada (token machine-to-machine `RECAUDACIONES_API_TOKEN`,
solo en `backend/.env`).

Canchas mantiene su propia base de datos PostgreSQL, separada de la del
Core, con la única responsabilidad de campos, horarios, tarifas,
funcionarios y reservas. El único acoplamiento entre ambos es el contrato
de la API HTTP.

## Alternativas consideradas

- **Cada satélite con su propia pasarela** (arquitectura anterior):
  rechazada por el contexto anterior; exigía además patrón Strategy y
  Circuit Breaker por banco en cada sistema.
- **Base de datos compartida con el Core**: rechazada. Ningún satélite
  lee ni escribe tablas financieras del Core; se rompería la separación
  de esquemas, equipos y responsabilidades.

## Consecuencias positivas

- El backend de Canchas se simplifica radicalmente: sin patrón Strategy,
  sin Circuit Breaker de pasarelas, sin datos bancarios ni de tarjetas.
- La lógica de conciliación y facturación vive en un solo lugar del
  ecosistema.
- Seguridad: las credenciales bancarias quedan concentradas en un único
  sistema auditable.
- Futuros satélites del GAD Beni podrán conectarse al mismo Core sin
  reinventar el cobro.

## Consecuencias negativas / riesgos

- Canchas depende de la disponibilidad del Core para generar cobros nuevos
  (puede seguir mostrando disponibilidad y horarios sin él).
- Acoplamiento al contrato de API que exponga el Core: debe versionarse
  con cuidado desde ambos lados.
- La confirmación del pago dependerá de un mecanismo externo (webhook
  entrante del Core o consulta activa desde Canchas), que se definirá en
  los módulos de lógica de negocio.

## Nota

Los Documentos 1, 2 y 3 de la arquitectura anterior (que incluían
`ordenes_pago`, pasarelas, Circuit Breaker y contingencia) se conservan
como referencia de las reglas de negocio de reservas y horarios, pero sus
secciones financieras quedan desactualizadas por este ADR.
