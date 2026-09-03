# ADR-005: Librería de mapas para la app móvil

**Fecha:** 2026-09-03  
**Estado:** Aceptada  
**Contexto:** Módulo 3 - Consulta Pública (Épica C, HU-C1)

## Contexto

La aplicación móvil necesita mostrar los campos deportivos en un mapa interactivo para que los ciudadanos puedan ubicarlos visualmente dentro del departamento del Beni. Esto es parte de la Épica C (HU-C1) del Documento 3 v3.

**Requisitos funcionales:**
- Mostrar marcadores para cada campo deportivo activo
- Diferenciar visualmente los campos en mantenimiento (no disponibles para reserva)
- Permitir al usuario ver su ubicación actual (opcional, con permiso de GPS)
- Sin necesidad de autenticación (consulta pública)

**Restricciones:**
- El sistema opera dentro de un departamento específico (Beni), no requiere cobertura global
- No se justifica complejidad de APIs de pago para este alcance
- La app debe funcionar sin conexión a internet para mapas (cache local deseable)

## Decisión

**Opción elegida:** `flutter_map` + OpenStreetMap (OSM) por defecto

### Razones técnicas

1. **Sin API key ni cuenta de facturación:** OSM es libre, no requiere registrarse en Google Cloud ni configurar billing
2. **Licencia compatible:** OSM usa Open Database License (ODbL), compatible con software propietario
3. **Suficiente para el alcance:** Para marcadores dentro de un departamento, OSM tiene cobertura adecuada sin necesidad de features premium
4. **Cache offline:** `flutter_map` soporta descarga de tiles para uso sin conexión
5. **Sin vendor lock-in:** Si en el futuro se necesita Google Maps, el cambio es transparente (solo se reemplaza el tile provider)

### Alternativa considerada (y descartada)

**Google Maps (`google_maps_flutter`):**
- Requiere API key y cuenta de facturación activa (aunque tenga free tier)
- Mayor precisión en zonas rurales del Beni (pero no justifica la complejidad)
- Features avanzadas (Street View, Directions) que no se usan en este módulo
- Costo operativo si el tráfico escala

**Criterio de cambio futuro:** Si el equipo cuenta con presupuesto aprobado y necesita features específicas de Google (ej: Street View para ver las canchas), se puede migrar a `google_maps_flutter` sin cambios en la arquitectura (solo se reemplaza el widget de mapa).

## Consecuencias

### Positivas

- ✅ Zero-config: no hay que gestionar API keys en CI/CD ni en deployments
- ✅ Sin costos operativos por uso de mapas
- ✅ Cumple con el principio de "funcionalidad mínima viable" del roadmap
- ✅ Los desarrolladores pueden probar localmente sin configuración adicional

### Negativas

- ⚠️ Calidad de tiles en zonas rurales del Beni puede ser inferior a Google Maps
- ⚠️ Si en el futuro se necesita geocoding inverso (dirección a partir de coordenadas), habrá que agregar otro servicio (ej: Nominatim, también de OSM)
- ⚠️ No hay soporte oficial de Google (depende de la comunidad de OSM)

### Técnicas

- **Paquetes a instalar:** `flutter_map: ^6.1.0` + `latlong2: ^0.9.0`
- **Tile provider:** OpenStreetMap estándar (`https://tile.openstreetmap.org/{z}/{x}/{y}.png`)
- **Atribución requerida:** Mostrar "© OpenStreetMap contributors" en la UI (obligatorio por licencia ODbL)

## Referencias

- [flutter_map documentación](https://docs.fleaflet.dev/)
- [OpenStreetMap Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
- Documento 3 v3, Épica C (HU-C1): Listado y mapa de campos deportivos
