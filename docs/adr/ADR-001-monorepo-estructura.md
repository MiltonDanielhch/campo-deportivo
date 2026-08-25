# ADR-001: Monorepo para el satélite de Canchas

## Estado

Aceptado

## Contexto

El satélite de Canchas tiene tres aplicaciones —backend (Laravel), panel web
(React + Vite + TS) y app móvil (Flutter)— con un dominio de negocio
cohesionado: infraestructura deportiva, horarios, tarifas y reservas.
El equipo es pequeño y las tres apps evolucionan juntas.
El Core de Recaudaciones es un proyecto independiente con su propio
repositorio, equipo y ciclo de despliegue.

## Decisión

Un único repositorio Git (Monorepo) para las tres aplicaciones propias del
satélite. El Core de Recaudaciones **no** vive en este repositorio: la única
relación entre ambos es una API HTTP, nunca código ni base de datos compartida.

## Alternativas consideradas

- **Polyrepo** (un repo por app): máxima independencia, pero overhead de
  coordinación injustificable para un equipo pequeño: tres clones,
  convenciones que divergen, versionado duplicado y cambios que tocan dos
  apps obligarían a sincronizar repos y releases.
- **Monorepo del ecosistema completo** (incluyendo el Core): rechazado.
  El Core pertenece a otro equipo, maneja bancos y factura; mezclarlo
  acoplaría permisos, secretos y ciclos de despliegue que deben ser
  independientes.

## Consecuencias

- Un solo `git clone`, una fuente de verdad para convenciones compartidas.
- Cambios atómicos que tocan backend + web + mobile en un mismo commit.
- Un pipeline de CI por app, filtrado por rutas, para no desperdiciar minutos.

* El repositorio crece con las tres apps; el CI debe mantener los filtros
  de rutas (`backend/**`, `web-admin/**`, `mobile/**`) siempre al día.
