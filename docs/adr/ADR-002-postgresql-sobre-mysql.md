# ADR-002: PostgreSQL sobre MySQL

## Estado

Aceptado

## Contexto

Canchas necesita impedir, a nivel de base de datos, que dos personas reserven
el mismo campo deportivo a la misma hora. Este es un problema de concurrencia
física sobre un recurso compartido (el horario de una cancha), no un problema
financiero.

En la arquitectura Hub & Spoke, Canchas ya no almacena datos de pagos,
tarjetas ni conciliación — toda esa información vive exclusivamente en la
base de datos del Core de Recaudaciones. Si mañana Canchas dejara de cobrar
cualquier cosa, seguiría necesitando esta capacidad igual, solo para
garantizar que el calendario de reservas nunca se pisa a sí mismo.

## Decisión

PostgreSQL 16 como motor de base de datos del satélite de Canchas, usando:

- Tipo `tstzrange` (rango de timestamps con zona horaria) para representar
  la franja horaria de cada reserva.
- Restricción `EXCLUDE USING gist` sobre `(campo_id WITH =, rango_horario
WITH &&)` para que la propia base de datos rechace cualquier inserción
  que se solape con una reserva existente en el mismo campo.

## Alternativas consideradas

- **MySQL 8.x:** No ofrece tipo de dato de rango nativo ni restricciones
  de exclusión GiST. La prevención de solapamiento tendría que
  implementarse en código PHP (Laravel), lo cual es vulnerable a
  condiciones de carrera bajo concurrencia alta (dos usuarios reservando
  la misma franja al mismo instante).
- **SQLite:** Descartado por las mismas limitaciones de tipos y por no
  ser adecuado para un entorno multiusuario en producción.

## Consecuencias

- La integridad del calendario se garantiza a nivel de base de datos,
  independientemente de bugs en el código de la aplicación.
- La zona horaria queda embebida en el tipo de dato (`tstzrange` respeta
  `America/La_Paz`), eliminando errores de conversión.
- Soporte nativo para consultas de disponibilidad eficientes usando
  operadores de rango (`&&`, `@>`, `<@`).

* Exige Docker local para garantizar que todos los desarrolladores y CI
  usan exactamente la misma versión de PostgreSQL y las mismas
  extensiones (`btree_gist`).
* El equipo debe familiarizarse con la sintaxis de rangos de PostgreSQL
  (curva de aprendizaje menor pero existente).
