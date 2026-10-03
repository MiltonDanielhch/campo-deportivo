# Plan Técnico Mejorado - Integración Paitití/SIREB como Fuente de Verdad

**Fecha:** 2026-10-03
**Estado:** Análisis completado, implementación pendiente

---

## Resumen Ejecutivo

El proyecto ya tiene **70% de la infraestructura base implementada**:
- ✅ Cliente real SIREB con OAuth2
- ✅ Servicio de catálogo cacheado
- ✅ Presenter público con SIREB
- ✅ Controller público usando presenter
- ✅ Command de sincronización de tarifas
- ✅ Binding simulador/real
- ✅ Columna `servicio_sireb_id` en campos
- ✅ `creado_por` nullable en tarifas_campo

**Faltan 30% críticos:**
- Configuración de paths SIREB en `config/services.php`
- Endpoints admin para vinculación/desvinculación
- Rediseño de admin web basado en servicios SIREB
- Actualización de tipos en frontend (web-public y mobile)
- Validación de `reservable_online` en UI
- Índice único parcial para evitar duplicados

---

## Estado Actual Detallado

### ✅ Ya Implementado (No requiere cambios)

#### Backend
1. **RecaudacionesApiClient.php** - Cliente real con OAuth2 client_credentials
   - Cache de token JWT
   - Reintento en 401 TOKEN_INVALIDO
   - ⚠️ Rutas hardcodeadas (`/api/v1/catalogo/servicios`, etc.)

2. **CatalogoSirebService.php** - Servicio de catálogo
   - Cache 10 minutos
   - Métodos: `catalogoCacheado()`, `servicioPorId()`, `ultimaActualizacion()`
   - ✅ Ya expone timestamp de actualización

3. **CampoPublicoSirebPresenter.php** - Presenter público
   - ✅ Ya implementa lógica SIREB vs fallback local
   - ✅ Ya expone `sireb`, `reservable_online`, `fuente_precios`, `meta`
   - ✅ Ya maneja servicios sin tarifa, no vinculados, etc.
   - **NO REQUIERE CAMBIOS**

4. **CampoController.php** (Public)
   - ✅ Ya inyecta `CampoPublicoSirebPresenter`
   - ✅ Ya devuelve respuesta enriquecida
   - **NO REQUIERE CAMBIOS**

5. **SincronizarTarifasSireb.php** - Command
   - ✅ Sincroniza etiquetas "Diurno"/"Nocturno" a tipos diurna/nocturna
   - ✅ Crea/actualiza tarifas locales
   - ⚠️ Usa `creado_por = null` (ya es nullable, OK)

6. **Migraciones**
   - ✅ `servicio_sireb_id` nullable en campos_deportivos
   - ✅ `creado_por` nullable en tarifas_campo

7. **AppServiceProvider.php**
   - ✅ Binding condicional simulador/real
   - **NO REQUIERE CAMBIOS**

### ❌ Faltan Cambios Críticos

#### Backend - Configuración

**Archivo:** `backend/config/services.php`

**Problema:** Paths SIREB hardcodeados en código, no configurables por entorno.

**Cambios requeridos:**
```php
'recaudaciones' => [
    // ... existente ...
    'paths' => [
        'catalogo_servicios' => env('SIREB_PATH_CATALOGO_SERVICIOS', '/api/v1/catalogo/servicios'),
        'clientes' => env('SIREB_PATH_CLIENTES', '/api/v1/clientes'),
        'liquidaciones' => env('SIREB_PATH_LIQUIDACIONES', '/api/v1/liquidaciones'),
    ],
    'catalogo_publico_fallback_local' => env('CATALOGO_PUBLICO_FALLBACK_LOCAL', true),
],
```

#### Backend - Cliente Real

**Archivo:** `backend/app/Integrations/Recaudaciones/RecaudacionesApiClient.php`

**Problema:** Rutas hardcodeadas en todos los métodos.

**Cambios requeridos:**
- `listarCatalogo()`: Usar `config('services.recaudaciones.paths.catalogo_servicios')`
- `buscarCliente()`: Usar `config('services.recaudaciones.paths.clientes')`
- `registrarCliente()`: Usar `config('services.recaudaciones.paths.clientes')`
- `crearLiquidacion()`: Usar `config('services.recaudaciones.paths.liquidaciones')`
- `consultarLiquidacionPorCodigo()`: Usar `config('services.recaudaciones.paths.liquidaciones')`
- `consultarLiquidacionDetalle()`: Usar `config('services.recaudaciones.paths.liquidaciones')`
- `anularLiquidacion()`: Usar `config('services.recaudaciones.paths.liquidaciones')`
- `registrarPagoManual()`: Usar `config('services.recaudaciones.paths.liquidaciones')`

#### Backend - Endpoints Admin

**Archivo:** Nuevo `backend/app/Http/Controllers/Api/V1/Admin/CatalogoSirebController.php`

**Endpoints requeridos:**

1. **GET /api/v1/admin/catalogo-sireb/campos**
   - Lista servicios SIREB con campo local vinculado
   - Muestra: servicio SIREB, campo local, estado de vinculación, reservable_online
   - Meta: total_servicios, vinculados, sin_vincular

2. **PATCH /api/v1/admin/campos-deportivos/{campoId}/vinculo-sireb**
   - Vincula campo local a servicio SIREB
   - Valida: servicio existe, no duplicado, estado SIREB
   - Auditoría

3. **DELETE /api/v1/admin/campos-deportivos/{campoId}/vinculo-sireb**
   - Desvincula campo de SIREB
   - Auditoría

4. **POST /api/v1/admin/sireb/sincronizar-tarifas**
   - Ejecuta sincronización manual
   - Retorna estadísticas

**Archivo:** `backend/routes/api.php`
- Agregar rutas con middleware auth:oauth y roles apropiados

#### Backend - Migraciones

**Archivo:** Nueva migración

**Cambios requeridos:**

1. **Índice único parcial** (CRÍTICO para evitar duplicados)
```sql
CREATE UNIQUE INDEX uq_campos_servicio_sireb
ON campos_deportivos (servicio_sireb_id)
WHERE servicio_sireb_id IS NOT NULL;
```

2. **Columna servicio_sireb_codigo** (OPCIONAL pero recomendado)
```sql
ALTER TABLE campos_deportivos ADD COLUMN servicio_sireb_codigo VARCHAR(50) NULL;
CREATE INDEX idx_campos_servicio_sireb_codigo ON campos_deportivos (servicio_sireb_codigo);
```

#### Web-public - Tipos

**Archivo:** `web-public/src/types/campo.ts` (nuevo) o actualizar tipos existentes

**Problema:** Tipos actuales no incluyen campos SIREB.

**Cambios requeridos:**
```typescript
export interface TarifaSireb {
  id?: string | null;
  tipo: string | null;
  etiqueta: string;
  precio: number | null;
  unidad_medida?: string | null;
  vigente_desde?: string | null;
}

export interface ServicioSireb {
  id: string;
  codigo: string;
  nombre: string;
  rubro?: string | null;
  estado?: string | null;
  tarifario?: string | null;
  unidad_medida?: string | null;
  precio_min?: number | null;
  precio_max?: number | null;
  tarifas: TarifaSireb[];
  fuente: string;
}

export interface CampoPublico {
  id: string;
  nombre: string;
  tipo_campo?: { id: string; nombre: string } | null;
  direccion?: string | null;
  imagen_url?: string | null;
  latitud?: number | null;
  longitud?: number | null;
  estado: string;
  hora_inicio_noche?: string | null;
  servicio_sireb_id?: string | null;
  horarios_atencion?: Array<{ dia_semana: number; hora_apertura: string; hora_cierre: string }>;
  tarifas?: {
    diurna?: { precio_por_hora: number; etiqueta?: string; tipo?: string; sireb_tarifa_id?: string | null; vigente_desde?: string | null } | null;
    nocturna?: { precio_por_hora: number; etiqueta?: string; tipo?: string; sireb_tarifa_id?: string | null; vigente_desde?: string | null } | null;
  };
  sireb?: ServicioSireb | null;
  reservable_online: boolean;
  mensaje_no_reservable?: string | null;
  fuente_precios?: string;
}
```

#### Web-public - CampoCard

**Archivo:** `web-public/src/components/campo/CampoCard.tsx`

**Problema:** No valida `reservable_online`, no muestra origen de precios.

**Cambios requeridos:**
- Validar `campo.reservable_online` antes de permitir navegación
- Mostrar `campo.mensaje_no_reservable` si corresponde
- Mostrar rango de precios desde `sireb.precio_min/max`
- Mostrar aviso si `fuente_precios === 'LOCAL_FALLBACK'`

#### Web-public - Página Campos

**Archivo:** `web-public/src/pages/Campos.tsx`

**Cambios requeridos:**
- Mostrar meta.sincronizado_en en footer
- Mostrar meta.aviso si existe
- Mostrar origen de precios (SIREB vs LOCAL_FALLBACK)

#### Web-admin - Pantalla Campos

**Archivo:** `web-admin/src/pages/parametricas/CamposDeportivos.tsx`

**Problema:** Solo muestra campos locales, no integración SIREB.

**Cambios requeridos:**
- Cambiar header a "Vinculación y control operativo de servicios de Paitití/SIREB"
- Cambiar botón "Nuevo campo" a "Vincular servicio de recaudaciones"
- Rediseñar tabla para mostrar:
  - Servicio SIREB (código, nombre)
  - Campo local vinculado
  - Estado SIREB
  - Estado operativo local
  - Reservable online
- Agregar modal de vinculación
- Agregar botón "Sincronizar tarifas desde SIREB"
- Bloquear edición de precios (solo operativos)
- Agregar indicador de estado SIREB

#### Web-admin - Service

**Archivo:** Nuevo o actualizar `web-admin/src/services/catalogoSirebService.ts`

**Cambios requeridos:**
- `listarCatalogoSireb()` - GET /api/v1/admin/catalogo-sireb/campos
- `vincularCampo(campoId, servicioSirebId)` - PATCH vinculo-sireb
- `desvincularCampo(campoId)` - DELETE vinculo-sireb
- `sincronizarTarifas()` - POST /api/v1/admin/sireb/sincronizar-tarifas

#### Mobile - Modelos

**Archivo:** `mobile/lib/models/campo_deportivo.dart`

**Problema:** No incluye campos SIREB.

**Cambios requeridos:**
```dart
class CampoDeportivo {
  // ... existente ...
  final String? servicioSirebId;
  final ServicioSireb? sireb;
  final bool reservableOnline;
  final String? mensajeNoReservable;
  final String? fuentePrecios;

  factory CampoDeportivo.fromJson(Map<String, dynamic> json) {
    return CampoDeportivo(
      // ... existente ...
      servicioSirebId: json['servicio_sireb_id'] as String?,
      sireb: json['sireb'] != null ? ServicioSireb.fromJson(json['sireb']) : null,
      reservableOnline: json['reservable_online'] as bool? ?? false,
      mensajeNoReservable: json['mensaje_no_reservable'] as String?,
      fuentePrecios: json['fuente_precios'] as String?,
    );
  }
}

class ServicioSireb {
  final String id;
  final String codigo;
  final String nombre;
  final String? rubro;
  final String? estado;
  final String? tarifario;
  final String? unidadMedida;
  final double? precioMin;
  final double? precioMax;
  final List<TarifaSireb> tarifas;
  final String fuente;

  // ... fromJson, toJson ...
}

class TarifaSireb {
  final String? id;
  final String? tipo;
  final String etiqueta;
  final double? precio;
  // ...
}
```

#### Mobile - UI

**Archivo:** `mobile/lib/screens/campos_listado_screen.dart`

**Cambios requeridos:**
- Validar `reservableOnline` antes de permitir navegación
- Mostrar `mensajeNoReservable` si corresponde
- Mostrar rango de precios desde `sireb.precioMin/max`
- Agregar pull-to-refresh
- Mostrar aviso si `fuentePrecios === 'LOCAL_FALLBACK'`

---

## Orden de Implementación (Optimizado)

### Fase 1: Backend Core (30 min)
1. Actualizar `config/services.php` con paths configurables
2. Actualizar `RecaudacionesApiClient.php` para usar config de paths
3. Crear migración: índice único parcial servicio_sireb_id
4. Crear migración: columna servicio_sireb_codigo (opcional)
5. Ejecutar migraciones

### Fase 2: Backend Admin (45 min)
1. Crear `CatalogoSirebController.php`
2. Agregar rutas en `routes/api.php`
3. Implementar endpoint GET catalogo-sireb/campos
4. Implementar endpoint PATCH vinculo-sireb
5. Implementar endpoint DELETE vinculo-sireb
6. Implementar endpoint POST sincronizar-tarifas
7. Tests backend admin

### Fase 3: Web-public (30 min)
1. Crear/actualizar tipos TypeScript para CampoPublico
2. Actualizar `CampoCard.tsx` para usar `reservable_online`
3. Actualizar `Campos.tsx` para mostrar meta
4. Agregar helper de formato de precio
5. Build y typecheck

### Fase 4: Web-admin (60 min)
1. Crear `catalogoSirebService.ts`
2. Rediseñar `CamposDeportivos.tsx`:
   - Header actualizado
   - Tabla basada en SIREB
   - Modal vinculación
   - Botón sincronizar
3. Actualizar `CampoFormDialog.tsx`:
   - Bloquear edición de precios
   - Solo operativos
4. Build y typecheck

### Fase 5: Mobile (45 min)
1. Actualizar `campo_deportivo.dart` con campos SIREB
2. Actualizar `campos_listado_screen.dart`:
   - Validar reservableOnline
   - Mostrar mensajes
   - Pull-to-refresh
3. Build y test

### Fase 6: Mapeo y Operación (30 min)
1. Desactivar simulador en .env test
2. Consultar catálogo real SIREB
3. Mapear campos locales a UUID SIREB
4. Ejecutar sincronización de tarifas
5. Verificar end-to-end

**Total estimado:** ~4 horas de trabajo efectivo

---

## Variables de Entorno

### Desarrollo Local (Simulador)
```env
RECAUDACIONES_SIMULADOR_HABILITADO=true
RECAUDACIONES_API_URL=http://localhost
CATALOGO_PUBLICO_FALLBACK_LOCAL=true
```

### Test (SIREB Real)
```env
RECAUDACIONES_SIMULADOR_HABILITADO=false
RECAUDACIONES_API_URL=https://test.sireb.beni.gob.bo
SIREB_TOKEN_URL=https://test.ibare.beni.gob.bo/oauth/token
SIREB_CLIENT_ID=sedede
SIREB_CLIENT_SECRET=SECRET_TEST_REAL
SIREB_PATH_CATALOGO_SERVICIOS=/api/v1/catalogo/servicios
SIREB_PATH_CLIENTES=/api/v1/clientes
SIREB_PATH_LIQUIDACIONES=/api/v1/liquidaciones
CATALOGO_PUBLICO_FALLBACK_LOCAL=true
```

### Producción
```env
RECAUDACIONES_SIMULADOR_HABILITADO=false
RECAUDACIONES_API_URL=https://sireb.beni.gob.bo
SIREB_TOKEN_URL=https://ibare.beni.gob.bo/oauth/token
SIREB_CLIENT_ID=sedede
SIREB_CLIENT_SECRET=SECRET_PRODUCCION
SIREB_PATH_CATALOGO_SERVICIOS=/api/v1/catalogo/servicios
SIREB_PATH_CLIENTES=/api/v1/clientes
SIREB_PATH_LIQUIDACIONES=/api/v1/liquidaciones
CATALOGO_PUBLICO_FALLBACK_LOCAL=false
```

---

## Deuda Técnica Identificada

### Crítica
- Ninguna detectada (creado_por ya es nullable)

### Importante
- Paths hardcodeados en RecaudacionesApiClient → Se soluciona en Fase 1
- Falta índice único servicio_sireb_id → Se soluciona en Fase 1

### Menor
- No hay código SIREB legible en campos → Opcional, se soluciona en Fase 1
- Scheduler para sincronización periódica → Fuera de scope, puede ser tarea separada

---

## Criterios de Aceptación

### Backend
- [ ] Paths SIREB configurables por env
- [ ] Cliente real usa config de paths
- [ ] Endpoint admin catálogo SIREB funciona
- [ ] Endpoint vincular/desvincular funciona
- [ ] Endpoint sincronizar tarifas funciona
- [ ] Índice único parcial creado
- [ ] No permite doble vínculo

### Web-public
- [ ] CampoCard valida reservable_online
- [ ] Muestra mensaje si no reservable
- [ ] Muestra origen de precios
- [ ] Build exitoso sin errores TypeScript

### Web-admin
- [ ] Tabla muestra servicios SIREB
- [ ] Permite vincular/desvincular
- [ ] No permite editar precios
- [ ] Sincronizar tarifas funciona
- [ ] Build exitoso sin errores TypeScript

### Mobile
- [ ] Modelos actualizados con campos SIREB
- [ ] Valida reservableOnline
- [ ] Muestra mensajes apropiados
- [ ] Pull-to-refresh funciona
- [ ] Build exitoso

---

## Notas Importantes

1. **El presenter público ya está implementado correctamente** - No requiere cambios
2. **El controller público ya usa el presenter** - No requiere cambios
3. **El comando de sincronización ya funciona** - Solo necesita índice único
4. **El binding simulador/real ya está en AppServiceProvider** - No requiere cambios
5. **Mobile consume backend, no SIREB directo** - Ya cumple la regla arquitectónica

---

## Next Steps

1. Implementar Fase 1 (Backend Core)
2. Implementar Fase 2 (Backend Admin)
3. Implementar Fase 3 (Web-public)
4. Implementar Fase 4 (Web-admin)
5. Implementar Fase 5 (Mobile)
6. Implementar Fase 6 (Mapeo y Operación)
7. Documentar procedimiento de mapeo
8. Smoke test end-to-end
