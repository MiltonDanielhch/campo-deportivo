# 📁 ROADMAP_WEB_PUBLICA.md
**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Canal público web (React + Vite + TypeScript), complementario a la app móvil
**Versión:** 1.1.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 20–26 horas (ver nota de estimación) · **Dependencias:** Módulos 3, 4 y 5 completos
**Bloquea:** Nada — es un canal adicional, paralelo al roadmap principal, no bloquea el flujo 0→7

> **Objetivo del Módulo:** Crear un canal web público completo que permita
> al ciudadano consultar campos deportivos, ver disponibilidad, reservar y
> pagar sin instalar nada, complementando la app móvil. Este canal es
> crítico para la adopción masiva: los links se comparten por WhatsApp,
> funcionan en cualquier navegador, y no requieren descarga.

> **Nota de estimación:** la versión original de este documento estimaba
> 12–16 horas. Se ajusta a 20–26: ocho fases que replican prácticamente
> toda la Épica C y la Épica D del móvil, más SEO real, generación de PDF,
> y un compromiso explícito con WCAG 2.1 AA y Lighthouse >90 —ninguno de
> esos tres últimos se logra bien en el tiempo que sobra al final, hay que
> presupuestarlos desde el principio.

> **Este módulo ya no es "puro frontend".** Al diseñar la pantalla de pago
> pensando en que un link se puede cerrar y reabrir —el caso de uso central
> de compartir por WhatsApp— apareció un hueco real en el backend ya
> construido: el QR o el enlace de checkout que el Core devuelve **nunca se
> guarda**, solo viaja una vez, en la respuesta del `POST` que crea la
> solicitud. Si el ciudadano recarga la página o reabre el link más tarde,
> no hay forma de volvérselo a mostrar. Este documento agrega una fase 0
> para cerrar ese hueco antes de construir la pantalla de pago web. **El
> mismo problema existe en la app Flutter** si alguien la cierra y la
> reabre — no se había notado porque el flujo típico de la app es más
> lineal; se recomienda aplicar el mismo parche a los Módulos 4 y 5 como
> seguimiento, aunque no es tema de este documento.

> **Por qué React y no Flutter Web:** aunque la app móvil ya compila a web,
> Flutter Web tiene problemas de SEO (SPA con renderizado sintético), carga
> inicial pesada, y accesibilidad web nativa inferior. Para un canal
> público institucional que el GAD quiere que Google indexe y que funcione
> en celulares con datos limitados, React + Vite es la mejor opción.
> Reutiliza el stack del web-admin (TypeScript + shadcn/ui + Tailwind) y
> consume los mismos endpoints públicos ya construidos en los Módulos 3, 4
> y 5. Esta es una decisión consecuente —agrega un tercer cliente que
> duplica una parte real de la lógica de negocio (carrito, cuenta
> regresiva, grilla de disponibilidad) en un segundo lenguaje/framework, con
> el costo de mantenimiento que eso implica— y se documenta como ADR-006,
> no como una elección de bajo riesgo tipo librería de gráficos.

> **Arquitectura:** el proyecto vive en `web-public/` dentro del monorepo
> (ADR-001), junto a `web-admin/` y `mobile/`. Es una SPA con prerendering
> de rutas para SEO (landing, listado, **y el detalle de cada campo activo**
> —ver Fase WP.2, corregido respecto a la versión anterior de este
> documento, que solo prerenderizaba landing y listado, dejando sin
> vista previa correcta justo los links que más se comparten). El backend
> Laravel ya tiene casi todos los endpoints necesarios en `/api/v1/public/`;
> el único cambio de backend real es el de la Fase WP.0.

---

## 🗺️ Mapa del Módulo

```
Web Pública
├── Fase WP.0 → Backend: persistir los datos de cobro pendiente (prerequisito)
├── Fase WP.1 → Scaffold del proyecto y configuración base
├── Fase WP.2 → Landing institucional (SEO + presencia del GAD)
├── Fase WP.3 → Listado de campos con filtros y mapa interactivo
├── Fase WP.4 → Detalle de campo con grilla de disponibilidad
├── Fase WP.5 → Flujo de reserva: carrito + datos del solicitante
├── Fase WP.6 → Pantalla de pago con QR/checkout
├── Fase WP.7 → Comprobante digital y consulta de estado
└── Fase WP.8 → Smoke test final, optimización y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE WP.0 — Backend: Persistir los Datos de Cobro Pendiente

## El hueco, explicado con detalle

`SolicitudReservaService::crear()` (Módulo 4) recibe del Core un
`RespuestaCobroDTO` con `qrString`/`qrImageBase64`/`checkoutUrl`, y esos
datos solo se devuelven en la respuesta HTTP de ese único `POST`. Nunca se
guardan en `solicitudes_reserva`. `EstadoSolicitudResource` (Módulo 5) —el
endpoint que la pantalla de pago consulta cada 5 segundos, y el que se
llamaría al reabrir un link— no tiene de dónde sacar esos datos si el
estado sigue en `pendiente`.

Esto no rompía nada mientras la única forma de ver esa pantalla fuera
completar el flujo de principio a fin sin recargar ni cerrar la app. Para
un canal cuyo caso de uso central es *"te mando el link por WhatsApp y lo
abrís cuando puedas"*, es un bloqueante real.

## La corrección

```sql
ALTER TABLE solicitudes_reserva
  ADD COLUMN datos_cobro_pendiente jsonb NULL;
```

Un único campo `jsonb`, no columnas separadas por cada posible dato del
Core — es información opaca que Canchas solo necesita volver a mostrar tal
cual, no consultar ni filtrar por ella.

---

## Tareas de la Fase WP.0

```
[x] Crear la migración
    → add_datos_cobro_pendiente_to_solicitudes_reserva —
      datos_cobro_pendiente jsonb, nullable.

[x] Actualizar SolicitudReservaService::crear() (Módulo 4)
    → Justo después de guardar referencia_recaudaciones, guardar también
      datos_cobro_pendiente con el contenido relevante del
      RespuestaCobroDTO (qrString, qrImageBase64, checkoutUrl).

[x] Actualizar EstadoSolicitudResource (Módulo 5)
    → Incluir datos_cobro_pendiente en la respuesta ÚNICAMENTE cuando
      estado === 'pendiente' — una vez confirmada, rechazada o expirada,
      ya no tiene sentido mostrarlo y no debería seguir viajando en cada
      respuesta.

[x] Tests
    → Crear una solicitud, y sin tocar nada más, consultar el endpoint
      de estado dos veces seguidas: ambas respuestas deben incluir el
      mismo QR/checkout_url, byte por byte.
    → Confirmar una solicitud y verificar que datos_cobro_pendiente ya
      no aparece en la respuesta del endpoint de estado.

[ ] Nota de seguimiento (fuera del alcance de este documento)
    → Aplicar el mismo criterio a la pantalla pago_qr_screen.dart del
      Módulo 4/5: si la app se cierra y se reabre con una solicitud
      pendiente en curso, debería poder recuperar el QR de la misma
      forma, en vez de perderlo.

[ ] Commit de la fase
    → Mensaje: "fix(recaudaciones): persistir datos de cobro pendiente para reapertura del link de pago"
```

---

# FASE WP.1 — Scaffold del Proyecto y Configuración Base

## Stack técnico elegido

- **Framework:** React 18 + TypeScript
- **Bundler:** Vite (mismo que web-admin)
- **UI:** shadcn/ui + Tailwind CSS
- **Routing:** React Router v6, con `React.lazy` por ruta (ver nota de
  bundle size en la Fase WP.8 — sin code splitting por ruta, esa meta no
  se alcanza)
- **Mapas:** React-Leaflet con tiles OSM — **misma decisión ya tomada dos
  veces antes** (ADR-005, aplicada tanto en la app móvil como en el panel
  web-admin, Módulo 6, Fase 6.5). No es una elección nueva, es coherencia
  con lo ya construido.
- **Estado global:** zustand
- **HTTP:** axios
- **Prerendering:** vite-plugin-prerender
- **Deploy:** decisión pendiente de confirmar con el equipo — ver nota
  abajo

## Sobre compartir código con web-admin

`web-admin` y `web-public` son ambos React dentro del mismo monorepo. Sin
ningún mecanismo de código compartido, los tipos TypeScript de
`CampoDeportivo`, `SolicitudReserva`, etc. terminan copiados en los dos
proyectos, con el riesgo de que diverjan con el tiempo (uno se actualiza
tras un cambio de backend, el otro se olvida). Se recomienda crear un
paquete compartido liviano —`packages/shared-types/` con los `npm
workspaces` de la raíz del monorepo alcanza, no hace falta nada más
sofisticado— con los tipos y, si se justifica, el cliente HTTP base. No es
bloqueante para arrancar este módulo, pero conviene resolverlo antes de que
la duplicación crezca.

## Sobre el destino de despliegue

Vercel/Netlify es una elección razonable para un sitio prerenderizado —CDN
gratis, cero mantenimiento—, pero es una infraestructura **distinta** a la
del resto del proyecto (Docker Compose + VPS, Módulo 0). Antes de decidir,
confirmar con el equipo quién administra la cuenta de Vercel/Netlify, el
dominio, y si el GAD prefiere mantener todo bajo su propia infraestructura
por política institucional. Se documenta esta elección, junto con la de
React sobre Flutter Web, en el mismo ADR-006.

---

## Tareas de la Fase WP.1

```
[x] Crear docs/adr/ADR-006-canal-web-publico-react.md
    → Documentar: por qué un tercer cliente (alcance/adopción vs. costo
      de mantener lógica de reserva duplicada en dos frameworks), por
      qué React sobre Flutter Web, y la decisión de destino de despliegue
      una vez confirmada con el equipo.

[x] Crear la carpeta web-public/ en la raíz del monorepo
    → Al mismo nivel que web-admin/ y mobile/ (ADR-001).

[ ] Inicializar el proyecto con Vite
    → npm create vite@latest web-public -- --template react-ts
    → Limpiar el boilerplate de Vite.

[x] Instalar dependencias base (solo lo que se usa desde esta fase)
    → npm install react-router-dom axios zustand
    → npm install -D tailwindcss postcss autoprefixer vite-plugin-prerender
    → El resto de dependencias (react-hook-form, zod, qrcode.react,
      react-helmet-async, react-to-print) se instalan en la fase donde
      se usan por primera vez, no todas de una vez acá.

[x] Configurar Tailwind CSS
    → npx tailwindcss init -p
    → Copiar tailwind.config.js del web-admin (mismo tema).

[x] Configurar shadcn/ui
    → npx shadcn@latest init
    → Copiar components.json del web-admin.
    → Instalar componentes base: button, card, input, label, select,
      badge, dialog, sheet.

[x] Configurar React Router con carga diferida por ruta
    → src/router.tsx con las rutas (/, /campos, /campos/:id, /reserva,
      /pago/:codigo, /comprobante/:codigo, /estado), cada componente de
      página importado con React.lazy() y envuelto en <Suspense> — es lo
      que hace posible la meta de bundle size de la Fase WP.8.
    → Configurar prerendering para / y /campos (el detalle /campos/:id
      se agrega en la Fase WP.4, ver ahí el porqué).

[x] Configurar axios con interceptor base
    → src/lib/api.ts con baseURL desde import.meta.env.VITE_API_BASE_URL
      —mismo nombre de variable que usa web-admin desde el Módulo 0, no
      VITE_API_URL: son dos proyectos del mismo monorepo, no hay razón
      para que la convención de nombres difiera entre ellos.
    → Interceptor para manejar errores 409 y 503 de forma centralizada.

[x] Crear el store global con zustand
    → src/store/carritoStore.ts — agregarFranja, quitarFranja, limpiar,
      calcularTotal.

[x] Configurar scripts de package.json
    → "dev": "vite" (puerto 5174, distinto al 5173 de web-admin)
    → "build": "vite build"
    → "preview": "vite preview"

[x] Actualizar el CORS del backend (el único ajuste de backend además de
    la Fase WP.0)
    → backend/config/cors.php: agregar http://localhost:5174 a los
      orígenes permitidos en desarrollo. Anotar como pendiente agregar
      también el dominio de producción una vez que exista (Fase WP.8).

[x] Agregar .env.example
    → VITE_API_BASE_URL=http://localhost:8000/api/v1

[x] Documentar en README.md
    → Cómo correr en dev, cómo hacer build, cómo desplegar (una vez
      resuelto el ADR-006).

[x] Commit de la fase
    → Mensaje: "chore(web-public): scaffold inicial con Vite + React + TypeScript + shadcn/ui"
```

---

# FASE WP.2 — Landing Institucional (SEO + Presencia del GAD)

## Objetivo

Página de entrada: información institucional, explicación del servicio, y
llamadas a la acción claras. Debe indexar bien en Google y cargar rápido.

---

## Tareas de la Fase WP.2

```
[ ] Instalar react-helmet-async y vite-plugin-sitemap
    → Se usan por primera vez en esta fase.

[ ] Crear src/pages/Landing.tsx
    → Hero con título, subtítulo y CTA "Ver canchas disponibles"
    → Sección "Cómo funciona" (3 pasos)
    → Sección de campos destacados (últimos 3 campos activos)
    → Sección institucional (GAD Beni, contacto, horarios)
    → Footer con links legales y redes sociales

[ ] Implementar componentes de la landing
    → src/components/landing/Hero.tsx
    → src/components/landing/ComoFunciona.tsx
    → src/components/landing/CamposDestacados.tsx (consume
      GET /public/campos — Módulo 3, Fase 3.1)
    → src/components/landing/InfoInstitucional.tsx
    → src/components/landing/Footer.tsx

[ ] Optimizar SEO de la landing
    → react-helmet-async para meta tags dinámicos.
    → Título y descripción institucional. Open Graph tags para
      compartir en redes.
    → sitemap.xml generado con vite-plugin-sitemap, cubriendo las rutas
      prerenderizadas (landing, listado, y detalle de campo — ver Fase
      WP.4). No cubre rutas transaccionales (/reserva, /pago/:codigo),
      que no tiene sentido indexar.

[ ] Responsive mobile-first
    → Verse bien en celular (375px). Menú hamburguesa en móvil.

[ ] Tests visuales manuales
    → Lighthouse score > 90 en la landing.
    → Meta tags correctos en View Source (sin ejecutar JS).
    → Los links "Ver canchas" navegan a /campos.

[ ] Commit de la fase
    → Mensaje: "feat(web-public): landing institucional con SEO optimizado y campos destacados"
```

---

# FASE WP.3 — Listado de Campos con Filtros y Mapa Interactivo

## Objetivo

Listado de campos con toggle lista/mapa, filtros por tipo, y mapa con
React-Leaflet.

---

## Tareas de la Fase WP.3

```
[ ] Crear src/pages/Campos.tsx
    → Toggle "Lista" / "Mapa". Filtros: tipo de campo, estado.
    → Vista lista: cards. Vista mapa: React-Leaflet con marcadores.

[ ] Instalar react-leaflet y leaflet
    → Se usan por primera vez en esta fase.

[ ] Implementar el listado
    → src/components/campos/CampoCard.tsx
    → src/components/campos/FiltrosCampos.tsx
    → Consumir GET /public/campos. Skeleton loaders mientras carga.

[ ] Implementar el mapa
    → src/components/campos/MapaCampos.tsx
    → Tiles OSM, un marcador por campo (verde activo, naranja
      mantenimiento). Click → navegar a /campos/:id.
    → Atribución OSM visible (obligatorio por licencia ODbL).
    → Centrar en Trinidad, Beni (lat: -14.84, lng: -64.90, zoom: 13).

[ ] Responsive
    → Móvil: mapa 100% del viewport, lista debajo. Desktop: lista 40% /
      mapa 60%.

[ ] Tests
    → El listado carga los campos del backend.
    → El toggle lista/mapa funciona.
    → Click en un campo navega a /campos/:id.
    → El mapa muestra marcadores en las coordenadas correctas.
    → Los campos en mantenimiento aparecen deshabilitados.

[ ] Commit de la fase
    → Mensaje: "feat(web-public): listado de campos con filtros y mapa interactivo"
```

---

# FASE WP.4 — Detalle de Campo con Grilla de Disponibilidad

## Objetivo

Información del campo, selector de fecha (próximos 14 días), y grilla de
disponibilidad con los 3 estados (libre/ocupada/bloqueada_temporal —
Módulo 3, Fase 3.2).

## Por qué esta ruta también se prerenderiza, a diferencia de la versión anterior de este documento

Un link a un campo específico —`/campos/:id`— es, en la práctica, el tipo
de link que más se comparte por WhatsApp ("mirá esta cancha"). Si esta ruta
no está prerenderizada, el scraper de vista previa de WhatsApp (que
históricamente no ejecuta JavaScript) no puede leer el nombre ni la
descripción del campo, y la vista previa del link sale vacía o genérica —
justo el caso de uso que este canal existe para resolver. Se genera una
página prerenderizada por cada campo activo en build time (la lista de IDs
se obtiene de la API durante el build). El costo es que agregar un campo
nuevo requiere un rebuild para que su página tenga vista previa correcta
hasta ese momento —aceptable para un catálogo que no cambia todos los
días—; mientras tanto, el campo ya es visible y reservable con normalidad,
solo sin vista previa optimizada hasta el próximo build.

---

## Tareas de la Fase WP.4

```
[ ] Crear src/pages/CampoDetalle.tsx
    → Ruta: /campos/:id
    → Info del campo arriba, selector horizontal de fechas (14 días),
      grilla de disponibilidad debajo, barra inferior de carrito.

[ ] Configurar el prerendering de /campos/:id
    → Extender la configuración de vite-plugin-prerender (Fase WP.1)
      para incluir una ruta por cada campo con estado 'activo' o
      'mantenimiento', obtenidas de GET /public/campos en build time.
    → Meta tags (Open Graph) específicos por campo: nombre, tipo, y una
      descripción corta con la dirección.

[ ] Implementar el selector de fechas
    → src/components/campo/SelectorFechas.tsx — 14 días desde hoy,
      scroll horizontal en móvil. Al cambiar de fecha, recargar la
      grilla (GET /public/campos/:id/disponibilidad?fecha=...).

[ ] Implementar la grilla de disponibilidad
    → src/components/campo/GrillaDisponibilidad.tsx — grid de 2
      columnas en móvil, 4 en desktop. Libre (verde, clickable), ocupada
      (rojo, bloqueado, ícono candado), bloqueada_temporal (naranja,
      bloqueado, "En proceso de cobro").

[ ] Implementar la barra inferior del carrito
    → src/components/campo/BarraCarrito.tsx — cantidad seleccionada,
      total estimado, botón "Ver carrito" (solo visible si hay algo
      seleccionado).

[ ] Refresco automático
    → setInterval cada 45 segundos para recargar la grilla, limpiado en
      cleanup del useEffect.

[ ] Tests
    → Info del campo correcta. Selector de fecha cambia la grilla.
    → Bloques libres clickables, ocupados/bloqueados no.
    → Barra inferior con total correcto. Refresco automático funciona.

[ ] Commit de la fase
    → Mensaje: "feat(web-public): detalle de campo con selector de fecha, grilla de disponibilidad y prerendering por campo"
```

---

# FASE WP.5 — Flujo de Reserva: Carrito + Datos del Solicitante

## Objetivo

Resumen del carrito y formulario de datos del solicitante, con envío a
`POST /public/solicitudes-reserva` (Módulo 4, Fase 4.1).

---

## Tareas de la Fase WP.5

```
[ ] Instalar react-hook-form y zod
    → Se usan por primera vez en esta fase.

[ ] Crear src/pages/Reserva.tsx
    → Ruta: /reserva. Dos pasos en la misma página: resumen del carrito,
      luego datos del solicitante.

[ ] Implementar el resumen del carrito
    → src/components/reserva/ResumenCarrito.tsx — franjas
      seleccionadas con opción de quitar, total estimado, botón
      "Continuar".

[ ] Implementar el formulario de datos del solicitante
    → src/components/reserva/FormularioSolicitante.tsx — nombre
      (requerido), teléfono (requerido), CI/NIT (opcional). Validación
      con react-hook-form + zod.

[ ] Manejo de errores del POST
    → 201: limpiar el carrito (carritoStore.limpiar()) y navegar a
      /pago/:codigo_seguimiento — limpiar el carrito acá, no con trucos
      de historial de navegador, es lo que hace que volver atrás
      muestre el estado vacío ya contemplado más abajo, en vez de
      requerir lógica extra para bloquear el botón "atrás".
    → 409: quitar la franja afectada del carrito, toast "Una franja ya
      no estaba disponible".
    → 503: toast "El sistema de cobro no está disponible, intenta más
      tarde", sin tocar el carrito.
    → 422: errores específicos debajo de cada campo.

[ ] Empty state
    → Si el carrito está vacío al llegar a /reserva (incluyendo después
      de un envío exitoso, si el usuario vuelve atrás), mostrar "Tu
      carrito está vacío" y botón "Ver canchas" — esto reemplaza
      cualquier intento de bloquear la navegación del navegador.

[ ] Tests
    → Resumen muestra las franjas correctamente. "Quitar" funciona.
    → Formulario valida campos requeridos.
    → POST exitoso limpia el carrito y navega a /pago/:codigo.
    → Error 409 quita la franja y muestra toast. Error 503 no toca el
      carrito.
    → Empty state se muestra correctamente, incluyendo al volver atrás
      después de un envío exitoso.

[ ] Commit de la fase
    → Mensaje: "feat(web-public): flujo de reserva con carrito y formulario de solicitante"
```

---

# FASE WP.6 — Pantalla de Pago con QR/Checkout

## Objetivo

Cuenta regresiva, QR o checkout (según lo que devuelva el Core), código de
seguimiento visible. Consulta el estado cada 5 segundos y navega
automáticamente al comprobante cuando se confirma.

---

## Tareas de la Fase WP.6

```
[ ] Instalar qrcode.react
    → Se usa por primera vez en esta fase.

[ ] Crear src/pages/Pago.tsx
    → Ruta: /pago/:codigo_seguimiento
    → Al montar, hacer un GET inicial a
      /public/solicitudes-reserva/:codigo/estado — la página debe
      autoabastecerse de datos desde la URL, no depender de recibir
      información de la pantalla anterior por navegación. Esto es lo
      que permite que el link funcione al reabrirlo directamente,
      apoyado en la Fase WP.0.

[ ] Implementar la cuenta regresiva
    → src/components/pago/CuentaRegresiva.tsx — tiempo restante desde
      expira_en del servidor, nunca desde la hora local. Formato MM:SS,
      rojo bajo 60 segundos. Al llegar a 0: "El tiempo para pagar
      expiró" y botón "Volver a elegir franjas".

[ ] Implementar el medio de pago
    → src/components/pago/MedioDePago.tsx — a partir de
      datos_cobro_pendiente (Fase WP.0): QR con qrcode.react si hay
      qr_string, imagen si hay qr_image_base64, botón "Pagar en el
      navegador" (window.open) si hay checkout_url.

[ ] Implementar el polling de estado
    → setInterval cada 5 segundos sobre el mismo endpoint del montaje
      inicial. 'confirmada' → navegar a /comprobante/:codigo.
      'expirada' → vista de expiración. 'rechazada' → "Tu pago no pudo
      procesarse, intenta nuevamente". Limpiar intervalo en cleanup.

[ ] Tests
    → La cuenta regresiva decrece correctamente.
    → Recargar la página muestra el mismo QR/checkout que antes de
      recargar (confirma que la Fase WP.0 funciona de punta a punta).
    → El polling detecta 'confirmada' y navega al comprobante.
    → El polling detecta 'expirada' y muestra la vista correspondiente.

[ ] Commit de la fase
    → Mensaje: "feat(web-public): pantalla de pago con QR, cuenta regresiva y detección automática de confirmación"
```

---

# FASE WP.7 — Comprobante Digital y Consulta de Estado

## Objetivo

Comprobante digital (reservas confirmadas con sus códigos) y consulta
manual de estado.

---

## Tareas de la Fase WP.7

```
[ ] Instalar react-to-print
    → Se usa por primera vez en esta fase. Nota de alcance: genera un
      diálogo de impresión del navegador (donde el usuario elige
      "Guardar como PDF"), no un archivo PDF generado en servidor —
      suficiente para el caso de uso, pero vale dejarlo claro en la UI
      ("Imprimir / Guardar como PDF" en vez de solo "Descargar PDF").

[ ] Crear src/pages/Comprobante.tsx
    → Ruta: /comprobante/:codigo_seguimiento
    → Consumir GET /public/solicitudes-reserva/:codigo/estado.
    → Código de seguimiento, monto total, fecha de confirmación, lista
      de reservas (codigo_reserva, campo, fecha, hora_inicio, hora_fin).
    → Botón "Imprimir / Guardar como PDF" y botón "Volver al inicio".

[ ] Implementar el componente imprimible
    → src/components/comprobante/ComprobantePDF.tsx — layout con logo
      del GAD, datos de la reserva, códigos. Usado por react-to-print.

[ ] Crear src/pages/ConsultarEstado.tsx
    → Ruta: /estado — campo de texto para el código, botón "Consultar".
    → Código existente: mismo layout que Comprobante.tsx. Código
      inexistente: "No se encontró ninguna reserva con ese código".

[ ] Reutilizar componentes
    → src/components/comprobante/EstadoReserva.tsx (usado en ambas
      páginas), mostrando el estado actual y su información
      correspondiente según pendiente/confirmada/expirada/rechazada.

[ ] Tests
    → /comprobante/:codigo muestra las reservas correctamente.
    → El PDF (vía impresión) se genera correctamente.
    → /estado consulta un código válido y muestra error para uno
      inexistente.
    → La respuesta del endpoint no incluye nombre_pagador,
      telefono_pagador, ni referencia_recaudaciones.

[ ] Commit de la fase
    → Mensaje: "feat(web-public): comprobante digital imprimible y consulta manual de estado"
```

---

# FASE WP.8 — Smoke Test Final, Optimización y Commit de Cierre

---

## Checklist de cierre

```
[ ] Flujo feliz completo: Landing → Listado → Detalle → Seleccionar 2
    franjas → Carrito → Datos solicitante → Pago → Comprobante.

[ ] Recargar /pago/:codigo a mitad del flujo muestra el mismo
    QR/checkout que antes de recargar (Fase WP.0 funcionando en
    conjunto con esta pantalla).

[ ] Flujo de expiración: crear una solicitud, dejarla expirar, verificar
    el mensaje en /pago y el estado 'expirada' en /estado.

[ ] Flujo de error 409: crear una solicitud, simular que otra persona
    reserva la misma franja, verificar que el web quita la franja del
    carrito y muestra el toast.

[ ] SEO: Google indexa la landing, el listado y las páginas de detalle
    de campo (Google Search Console). Meta tags correctos en View
    Source, sin ejecutar JS, para /, /campos y al menos un /campos/:id.
    sitemap.xml accesible y correcto.

[ ] Performance (Lighthouse): landing > 90 en las 4 categorías,
    listado > 85 (el mapa pesa más), carga inicial < 2s en 4G.

[ ] Responsive en 375px, 768px y 1440px. Menú hamburguesa en móvil.

[ ] Accesibilidad (WCAG 2.1 AA): correr axe DevTools o el audit de
    accesibilidad de Lighthouse sobre cada página, no solo una revisión
    visual. Confirmar con el área legal/institucional del GAD si existe
    un requisito normativo boliviano de accesibilidad para sitios de
    gobierno — de ser así, esto deja de ser buena práctica y pasa a ser
    una obligación a cumplir, no a "verificar si sobra tiempo".

[ ] Bundle size: npm run build, bundle total < 500KB gzipped por chunk
    de ruta (gracias al React.lazy configurado desde la Fase WP.1 —
    sin eso, esta meta no es alcanzable).

[ ] Deploy de prueba, según lo que resuelva el ADR-006 (Vercel/Netlify o
    infraestructura propia). Verificar que todas las rutas funcionan en
    producción y que las variables de entorno se configuran
    correctamente.

[ ] Actualizar el CORS del backend con el dominio de producción
    → Agregar el dominio real de web-public a config/cors.php, además
      del localhost:5174 ya agregado en la Fase WP.1.

[ ] Documentación: README.md actualizado con instrucciones de deploy,
    capturas de pantalla en docs/screenshots/.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Web Pública - canal web completo con SEO, reserva y pago"
    → Tag sugerido: v0.8.0-web-publica
```

---

## 🎯 Resultado Final del Módulo

Al completar este módulo, el GAD Beni tiene **tres canales** para que los
ciudadanos reserven canchas:

1. **App móvil** (Flutter) — para usuarios frecuentes.
2. **Web pública** (React) — para adopción masiva, links compartibles por
   WhatsApp, SEO.
3. **Web admin** (React) — para funcionarios del GAD.

Los tres consumen los mismos endpoints públicos del backend Laravel. El
carrito vive en el navegador/dispositivo de cada canal por separado (no en
el backend), así que no hay continuidad de carrito entre app y web — el
ciudadano que empieza en una y quiere terminar en la otra vuelve a
seleccionar sus franjas, lo cual es una limitación razonable de aceptar,
no algo que este módulo intente resolver.
