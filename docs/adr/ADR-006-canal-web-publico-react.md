# ADR-006: Canal Web Público en React (no Flutter Web)

**Estado:** Aceptado  
**Fecha:** 2026-09-16  
**Decisores:** Equipo de desarrollo

## Contexto

El sistema necesita un canal web público para que los ciudadanos consulten
campos deportivos, vean disponibilidad, reserven y paguen sin instalar nada.
Este canal es crítico para la adopción masiva: los links se comparten por
WhatsApp, funcionan en cualquier navegador, y no requieren descarga.

La app móvil (Flutter) ya compila a web, pero Flutter Web tiene problemas
de SEO (SPA con renderizado sintético), carga inicial pesada (varios MB de
WASM/JS), y accesibilidad web nativa inferior.

## Decisión

Crear un tercer cliente: `web-public/` con React 18 + Vite + TypeScript,
en lugar de usar Flutter Web.

## Consecuencias

**Positivas:**
- SEO real: el contenido se prerenderiza en build time, Google y los
  scrapers de WhatsApp pueden leerlo sin ejecutar JavaScript
- Carga inicial liviana: bundle < 500KB gzipped por ruta (con code splitting)
- Accesibilidad web nativa superior (HTML semántico, ARIA, navegación por teclado)
- Reutiliza el stack del web-admin (TypeScript + shadcn/ui + Tailwind)

**Negativas:**
- Duplica una parte real de la lógica de negocio (carrito, cuenta regresiva,
  grilla de disponibilidad) en un segundo lenguaje/framework
- Costo de mantenimiento: tres clientes (mobile, web-admin, web-public)
  consumiendo los mismos endpoints

**Mitigaciones:**
- Los tipos TypeScript compartidos se extraerán a `packages/shared-types/`
  cuando la duplicación crezca (no bloqueante para arrancar)
- La lógica de negocio crítica (validación de franjas, cálculo de montos,
  idempotencia) vive en el backend, no se duplica

## Alternativas consideradas

1. **Flutter Web:** descartado por SEO, peso de carga inicial y accesibilidad
2. **Next.js (SSR):** descartado por complejidad innecesaria; el prerendering
   de Vite alcanza para un catálogo que no cambia todos los días
3. **Astro:** descartado porque el equipo ya conoce React y no aporta valor
   adicional para este caso de uso

## Nota de despliegue

El destino de despliegue (Vercel/Netlify vs infraestructura propia del GAD)
queda pendiente de confirmar con el equipo. Se documenta acá cuando se resuelva.
