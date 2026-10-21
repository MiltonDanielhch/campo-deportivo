¡Perfecto! Es una excelente idea dejar un "punto de guardado" claro para que puedas trabajar en otras áreas del sistema sin perder el hilo. 

Aquí tienes el **resumen ejecutivo de lo que falta y lo que está pendiente** para cuando regreses a este módulo. Puedes copiar y pegar esto en tus notas.

---

### 🟢 ESTADO ACTUAL (Punto de Guardado Seguro)
- **Backend**: 224 tests pasando. Lógica de variantes, servicios adicionales, tramos diurnos/nocturnos y sync con SIREB 100% funcional.
- **Frontend**: Build optimizado. El chunk principal de la app bajó de **1.36 MB a 259 KB** gracias al *code splitting* de vendor.
- **Base de datos**: Catálogo de Palacio y Estadio cargado y vinculado correctamente.

---

### 📝 LISTA DE PENDIENTES (Para cuando regreses)

#### 1. P8.6-3: Chequeo de Imports Circulares (Higiene, 15 min)
- **Qué es**: Activar temporalmente la detección de dependencias circulares en Vite/Rolldown para asegurar que no haya ciclos ocultos que inflen el bundle o causen errores de ejecución.
- **Cómo**: Agregar `circularDependency: true` en `build.rolldownOptions.input.checks` de `vite.config.ts`, correr `npm run build`, ver si hay warnings, arreglarlos si los hay, y volver a ponerlo en `false`.

#### 2. P8.6-4: Lazy Loading de Componentes Pesados (Performance UX, 1-2 horas)
- **Qué es**: Aunque el chunk principal ya bajó a 259 KB, podemos bajarlo más cargando bajo demanda los componentes que no se usan al iniciar.
- **Cómo**: Usar `React.lazy` y `<Suspense>` para diálogos pesados como:
  - `DialogCrearReservaVentanilla`
  - `DialogDetalleReserva`
  - `AnularLiquidacionDialog`
- **Beneficio**: El funcionario solo descarga ese código cuando realmente abre el modal, haciendo la carga inicial del panel aún más rápida en redes lentas.

#### 3. P8.5: Apertura Pública con Verificación (BACKLOG DE NEGOCIO)
- **Estado**: **PAUSADO**. No se debe tocar hasta nueva orden.
- **Por qué**: Hoy todas las variantes de asociación (`MUNI`, `DPTO`, `ENTRENAMIENTO`) tienen `visible_publico = false`. Esto es correcto, porque abrirlo al público sin un mecanismo de validación documental permitiría que cualquiera se registre como "Asociación" y pague tarifas con descuento.
- **Disparador para retomarlo**: Solo se abre cuando el negocio defina el flujo de validación (ej. login ciudadano + aprobación de un admin, o restricción a variantes "Generales" únicamente).

#### 4. Smoke Visual Final (5 min)
- Al regresar, hacer una prueba rápida en el navegador:
  1. Entrar a Ventanilla → Nueva Reserva.
  2. Elegir "Palacio de los Deportes" o "Estadio Gran Mamoré".
  3. Verificar que el checkbox de **Escenario** (u otro servicio con tramo) muestre los precios Diurno/Nocturno en pequeño debajo del precio principal.
  4. Crear la reserva y confirmar que el total suma correctamente.

---

### 🚫 QUÉ NO HACER AHORA
- **No modificar** la lógica de `SolicitudReservaService` ni los DTOs de variantes/servicios (ya están estables y probados).
- **No habilitar** `visible_publico = true` en las variantes de asociación sin la validación de negocio mencionada en el punto 3.
- **No borrar** el seeder `ArancelVarianteCatalogoCompletoSeeder` (ya se verificó que no hay referencias huérfanas, está limpio).

---

### 🔍 CÓMO VERIFICAR QUE TODO SIGUE BIEN AL REGRESAR
Cuando vuelvas a esta rama, solo corre estos dos comandos para confirmar que el entorno está intacto:

```bash
# 1. Verificar backend
cd backend
php artisan test

# 2. Verificar frontend
cd ../web-admin
npm run build
```
Si ambos dan verde (224 passed y build exitoso sin errores de TS), puedes retomar exactamente desde el punto 1 o 2 de la lista de pendientes.

¡Éxito con las otras tareas del sistema! Cuando estés listo para continuar, solo dime "volvemos a P8" y atacamos el siguiente punto de la lista.



1. Fase 3 — la pregunta, redactada para copiar y mandar
Mandale esto tal cual a tu contacto de SIREB / Recaudaciones (o al PO que habla con ellos):
Consulta tarifaria — ¿cobran fracción de hora?
Hoy generamos liquidaciones en horas completas (cantidad = 1 hora). Necesitamos saber si el tarifario de ustedes soporta cantidad fraccionada (ej. 0.5 hora) o bloques de 30 minutos, o si el mínimo de cobro es siempre 1 hora.
Caso concreto: un ciudadano quiere jugar 08:30–09:00 (30 min). ¿Pueden emitir una liquidación de Bs X por media hora, o el mínimo es Bs 2X (1 hora)? Si el mínimo es 1 hora, ¿existe en el catálogo alguna tarifa/horario de 30 min, o habría que crearla?
Lo que definimos con la respuesta:
(A) Sí cobran fracción / 30 min → habilitamos reserva online de 30 min.
(B) No, solo hora entera → los 30 min quedan solo en ventanilla y la web pública sigue en bloques de 1 hora.
