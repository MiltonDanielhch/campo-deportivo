Voy a crear el roadmap de la web pública siguiendo exactamente el mismo formato que los otros. Te lo preparo completo:


# 📁 ROADMAP_WEB_PUBLICA.md

**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni  
**Arquitectura:** Canal público web (React + Vite + TypeScript), complementario a la app móvil  
**Versión:** 1.0.0 · **Formato:** Guía Arquitectónica Explicativa  
**Tiempo estimado:** 12–16 horas · **Dependencias:** Módulos 3, 4 y 5 completos  
**Bloquea:** Nada (es un canal adicional, no bloquea el flujo principal)

> **Objetivo del Módulo:** Crear un canal web público completo que permita al ciudadano consultar campos deportivos, ver disponibilidad, reservar y pagar **sin instalar nada**, complementando la app móvil. Este canal es crítico para la adopción masiva: los links se comparten por WhatsApp, funcionan en cualquier navegador, y no requieren descarga.
>
> **Por qué React y no Flutter web:** aunque la app móvil ya compila a web, Flutter web tiene problemas de SEO (es una SPA con renderizado sintético), carga inicial pesada (varios MB de WASM/JS), y accesibilidad web nativa inferior. Para un canal público institucional que el GAD quiere que Google indexe y que funcione en celulares con datos limitados, React + Vite es la mejor opción. Reutiliza el stack del web-admin (TypeScript + shadcn/ui + Tailwind) y consume los mismos endpoints públicos que ya creamos en los Módulos 3, 4 y 5.
>
> **Arquitectura:** el proyecto vive en `web-public/` dentro del monorepo (ADR-001), junto a `web-admin/` y `mobile/`. Es una SPA con prerendering de rutas estáticas (landing, listado) para SEO, y rutas dinámicas (detalle, pago, comprobante) que se renderizan en cliente. El backend Laravel ya tiene todos los endpoints necesarios en `/api/v1/public/`, así que este módulo es **puro frontend**.

---

## 🗺️ Mapa del Módulo

```
Web Pública
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

# FASE WP.1 — Scaffold del Proyecto y Configuración Base

## Stack técnico elegido

- **Framework:** React 18 + TypeScript
- **Bundler:** Vite (mismo que web-admin, configuración similar)
- **UI:** shadcn/ui + Tailwind CSS (reutiliza componentes del web-admin donde aplique)
- **Routing:** React Router v6
- **Mapas:** React-Leaflet (más liviano que MapLibre, suficiente para marcadores simples)
- **Estado global:** zustand (más simple que Redux, suficiente para carrito + autenticación futura si aplica)
- **HTTP:** axios (más ergonómico que fetch nativo para interceptores y tipado)
- **Prerendering:** vite-plugin-prerender (genera HTML estático para rutas clave: landing, listado)
- **Deploy:** Vercel/Netlify (gratis para estáticos) o VPS con nginx (si el GAD tiene infraestructura)

## Por qué este stack

- **React + Vite:** el mismo stack del web-admin permite compartir componentes UI, tipos TypeScript, y conocimiento del equipo.
- **TypeScript:** los contratos JSON de los endpoints ya están tipados en el backend; generar tipos compartidos evita bugs de desincronización.
- **shadcn/ui:** componentes accesibles, personalizables y sin dependencias pesadas.
- **React-Leaflet:** librería madura, bien documentada, con tiles OSM gratuitos (mismo criterio del ADR-005 del móvil).
- **zustand:** store global liviana para el carrito (similar al `CarritoReservaProvider` de Flutter, pero con zustand en lugar de provider de Flutter).
- **Prerendering:** la landing y el listado de campos se renderizan en build time (HTML estático), lo que da SEO perfecto y carga instantánea. Las rutas dinámicas (detalle, pago) se renderizan en cliente.

---

## Tareas de la Fase WP.1

```
[ ] Crear la carpeta web-public/ en la raíz del monorepo
    → Al mismo nivel que web-admin/ y mobile/ (ADR-001).

[ ] Inicializar el proyecto con Vite
    → npm create vite@latest web-public -- --template react-ts
    → Limpiar el boilerplate de Vite (App.tsx, index.css, assets).

[ ] Instalar dependencias base
    → npm install react-router-dom axios zustand
    → npm install -D tailwindcss postcss autoprefixer @types/react @types/react-dom
    → npm install -D vite-plugin-prerender

[ ] Configurar Tailwind CSS
    → npx tailwindcss init -p
    → Copiar tailwind.config.js del web-admin (mismo tema, colores del GAD).
    → Crear src/index.css con @tailwind base/components/utilities.

[ ] Configurar shadcn/ui
    → npx shadcn@latest init
    → Copiar components.json del web-admin.
    → Instalar componentes necesarios: button, card, input, label, select, badge, dialog, sheet.

[ ] Configurar React Router
    → src/router.tsx con las rutas:
        / → Landing
        /campos → Listado
        /campos/:id → Detalle
        /reserva → Carrito + datos solicitante
        /pago/:codigo → Pantalla de pago
        /comprobante/:codigo → Comprobante digital
        /estado → Consulta manual de estado
    → Configurar prerendering para / y /campos.

[ ] Configurar axios con interceptor base
    → src/lib/api.ts con baseURL desde import.meta.env.VITE_API_URL
    → Interceptor para manejar errores 409 (franja no disponible) y 503 (cobro caído).

[ ] Crear el store global con zustand
    → src/store/carritoStore.ts (similar a CarritoReservaProvider de Flutter)
    → Métodos: agregarFranja, quitarFranja, limpiar, calcularTotal.

[ ] Configurar scripts de package.json
    → "dev": "vite" (puerto 5174 para no chocar con web-admin en 5173)
    → "build": "vite build"
    → "preview": "vite preview"

[ ] Agregar .env.example
    → VITE_API_URL=http://localhost:8000/api/v1

[ ] Documentar en README.md
    → Cómo correr en dev (npm run dev)
    → Cómo hacer build (npm run build)
    → Cómo desplegar (Vercel/Netlify/nginx)

[ ] Commit de la fase
    → Mensaje: "chore(web-public): scaffold inicial con Vite + React + TypeScript + shadcn/ui"
```

---

# FASE WP.2 — Landing Institucional (SEO + Presencia del GAD)

## Objetivo

Crear la página de entrada del sistema: información institucional del GAD Beni, explicación del servicio, y llamadas a la acción claras para que el ciudadano comience a reservar. Esta página debe indexar bien en Google y cargar rápido.

---

## Tareas de la Fase WP.2

```
[ ] Crear src/pages/Landing.tsx
    → Hero section con título, subtítulo y CTA "Ver canchas disponibles"
    → Sección "Cómo funciona" (3 pasos: elegir cancha, pagar, jugar)
    → Sección de campos destacados (últimos 3 campos activos del backend)
    → Sección de información institucional (GAD Beni, contacto, horarios)
    → Footer con links legales y redes sociales

[ ] Implementar componentes de la landing
    → src/components/landing/Hero.tsx
    → src/components/landing/ComoFunciona.tsx
    → src/components/landing/CamposDestacados.tsx (consume GET /public/campos)
    → src/components/landing/InfoInstitucional.tsx
    → src/components/landing/Footer.tsx

[ ] Optimizar SEO de la landing
    → react-helmet-async para meta tags dinámicos
    → Título: "Reserva de Canchas Deportivas - GAD Beni"
    → Descripción: "Reserva canchas de fútbol, tenis y más en Trinidad, Beni. Pago online, confirmación inmediata."
    → Open Graph tags para compartir en redes sociales
    → Sitemap.xml generado automáticamente (vite-plugin-sitemap)

[ ] Configurar prerendering para la landing
    → vite-plugin-prerender genera /index.html estático en build time
    → Verificar que Google pueda crawlear la página (sin JS requerido para contenido crítico)

[ ] Responsive mobile-first
    → La landing debe verse perfecta en celular (ancho 375px)
    → Menú hamburguesa en móvil, navegación horizontal en desktop

[ ] Tests visuales manuales
    → Verificar que la landing carga en < 2 segundos (Lighthouse score > 90)
    → Verificar que los meta tags se renderizan correctamente (View Source)
    → Verificar que los links "Ver canchas" navegan a /campos

[ ] Commit de la fase
    → Mensaje: "feat(web-public): landing institucional con SEO optimizado y campos destacados"
```

---

# FASE WP.3 — Listado de Campos con Filtros y Mapa Interactivo

## Objetivo

Replicar la funcionalidad de `campos_listado_screen.dart` del móvil, pero en web: listado de campos con toggle lista/mapa, filtros por tipo de campo, y mapa interactivo con React-Leaflet usando tiles OSM (mismo ADR-005).

---

## Tareas de la Fase WP.3

```
[ ] Crear src/pages/Campos.tsx
    → Toggle "Lista" / "Mapa" en la parte superior
    → Filtros: tipo de campo (dropdown), estado (activo/mantenimiento)
    → Vista de lista: cards con info del campo (nombre, tipo, dirección, tarifa)
    → Vista de mapa: React-Leaflet con marcadores por campo

[ ] Implementar el listado
    → src/components/campos/CampoCard.tsx (card individual)
    → src/components/campos/FiltrosCampos.tsx (dropdown de tipo de campo)
    → Consumir GET /public/campos con axios
    → Mostrar skeleton loaders mientras carga

[ ] Implementar el mapa
    → src/components/campos/MapaCampos.tsx
    → React-Leaflet con tiles OSM (https://tile.openstreetmap.org/{z}/{x}/{y}.png)
    → Un marcador por campo (icono de fútbol, verde si activo, naranja si mantenimiento)
    → Click en marcador → navegar a /campos/:id
    → Atribución OSM en esquina inferior derecha (obligatorio por licencia ODbL)
    → Centrar el mapa en Trinidad, Beni (lat: -14.84, lng: -64.90, zoom: 13)

[ ] Responsive
    → En móvil: mapa ocupa 100% del viewport, lista debajo
    → En desktop: lista a la izquierda (40% del ancho), mapa a la derecha (60%)

[ ] Tests
    → Verificar que el listado carga los campos del backend
    → Verificar que el toggle lista/mapa funciona
    → Verificar que click en un campo navega a /campos/:id
    → Verificar que el mapa muestra marcadores en las coordenadas correctas
    → Verificar que los campos en mantenimiento aparecen deshabilitados

[ ] Commit de la fase
    → Mensaje: "feat(web-public): listado de campos con filtros y mapa interactivo"
```

---

# FASE WP.4 — Detalle de Campo con Grilla de Disponibilidad

## Objetivo

Replicar `campo_detalle_screen.dart` del móvil: información del campo, selector de fecha (próximos 14 días), y grilla de disponibilidad horaria con los 3 estados (libre/ocupada/bloqueada_temporal). Las franjas libres son seleccionables y se agregan al carrito global (zustand store).

---

## Tareas de la Fase WP.4

```
[ ] Crear src/pages/CampoDetalle.tsx
    → Ruta: /campos/:id
    → Información del campo arriba (nombre, tipo, dirección, tarifa vigente)
    → Selector horizontal de fechas (próximos 14 días, formato "Hoy", "Mañana", "Jue 5/9")
    → Grilla de disponibilidad horaria debajo
    → Barra inferior con contador de franjas seleccionadas y botón "Ver carrito"

[ ] Implementar el selector de fechas
    → src/components/campo/SelectorFechas.tsx
    → 14 días desde hoy, scroll horizontal en móvil
    → Al cambiar de fecha, recargar la grilla (GET /public/campos/:id/disponibilidad?fecha=YYYY-MM-DD)

[ ] Implementar la grilla de disponibilidad
    → src/components/campo/GrillaDisponibilidad.tsx
    → Grid de 2 columnas en móvil, 4 columnas en desktop
    → Bloques con 3 estados visuales:
        • libre (verde, click para agregar al carrito)
        • ocupada (rojo, deshabilitado, icono lock)
        • bloqueada_temporal (naranja, deshabilitado, "En proceso de cobro")
    → Click en bloque libre → toggle en carritoStore (agregar/quitar)

[ ] Implementar la barra inferior del carrito
    → src/components/campo/BarraCarrito.tsx
    → Muestra cantidad de franjas seleccionadas y total estimado
    → Botón "Ver carrito" → navega a /reserva
    → Solo visible si el carrito no está vacío

[ ] Refresco automático
    → useEffect con setInterval cada 45 segundos para recargar la grilla
    → Limpiar el intervalo en cleanup (cuando el usuario cambia de página)

[ ] Tests
    → Verificar que la información del campo se muestra correctamente
    → Verificar que el selector de fechas cambia la grilla
    → Verificar que los bloques libres son clickables y se agregan al carrito
    → Verificar que los bloques ocupados/bloqueados no son clickables
    → Verificar que la barra inferior muestra el total correcto
    → Verificar que el refresco automático funciona (cambiar estado en BD y esperar 45s)

[ ] Commit de la fase
    → Mensaje: "feat(web-public): detalle de campo con selector de fecha y grilla de disponibilidad"
```

---

# FASE WP.5 — Flujo de Reserva: Carrito + Datos del Solicitante

## Objetivo

Replicar las pantallas `carrito_resumen_screen.dart` y `datos_solicitante_screen.dart` del móvil: resumen del carrito con opción de quitar franjas, formulario de datos del solicitante, y envío de la solicitud (POST /public/solicitudes-reserva).

---

## Tareas de la Fase WP.5

```
[ ] Crear src/pages/Reserva.tsx
    → Ruta: /reserva
    → Dos pasos en la misma página (wizard):
        1. Resumen del carrito
        2. Datos del solicitante
    → Navegación entre pasos con estado local (no rutas separadas)

[ ] Implementar el resumen del carrito
    → src/components/reserva/ResumenCarrito.tsx
    → Lista de franjas seleccionadas con:
        • Nombre del campo, fecha, horario, precio
        • Botón "Quitar" para eliminar del carrito
    → Total estimado al final
    → Botón "Continuar" para ir al paso 2

[ ] Implementar el formulario de datos del solicitante
    → src/components/reserva/FormularioSolicitante.tsx
    → Campos: nombre completo (requerido), teléfono (requerido), CI/NIT (opcional)
    → Validación en cliente con react-hook-form + zod
    → Botón "Confirmar reserva" que hace POST /public/solicitudes-reserva

[ ] Manejo de errores del POST
    → Si 201: navegar a /pago/:codigo_seguimiento
    → Si 409 (franja no disponible): quitar la franja del carrito, mostrar toast "Una franja ya no estaba disponible"
    → Si 503 (cobro caído): mostrar toast "El sistema de cobro no está disponible, intenta más tarde"
    → Si 422 (validación): mostrar errores específicos debajo de cada campo

[ ] Empty state
    → Si el carrito está vacío al llegar a /reserva, mostrar mensaje "Tu carrito está vacío" y botón "Ver canchas"

[ ] Tests
    → Verificar que el resumen muestra las franjas del carrito correctamente
    → Verificar que "Quitar" elimina la franja del carrito
    → Verificar que el formulario valida campos requeridos
    → Verificar que el POST exitoso navega a /pago/:codigo
    → Verificar que el error 409 quita la franja y muestra toast
    → Verificar que el error 503 muestra toast sin tocar el carrito
    → Verificar el empty state cuando el carrito está vacío

[ ] Commit de la fase
    → Mensaje: "feat(web-public): flujo de reserva con carrito y formulario de solicitante"
```

---

# FASE WP.6 — Pantalla de Pago con QR/Checkout

## Objetivo

Replicar `pago_qr_screen.dart` del móvil: pantalla de pago con cuenta regresiva, QR o botón de checkout (según lo que devuelva el Core), y código de seguimiento visible. Esta pantalla consulta el estado cada 5 segundos y navega automáticamente al comprobante cuando la solicitud se confirma.

---

## Tareas de la Fase WP.6

```
[ ] Crear src/pages/Pago.tsx
    → Ruta: /pago/:codigo_seguimiento
    → Información de la solicitud: código, monto total, tiempo restante
    → QR renderizado o botón de checkout (según respuesta del Core)
    → Polling cada 5 segundos a GET /public/solicitudes-reserva/:codigo/estado

[ ] Implementar la cuenta regresiva
    → src/components/pago/CuentaRegresiva.tsx
    → Calcular tiempo restante desde expira_en (no desde hora local)
    → Formato MM:SS, cambiar a rojo cuando queden < 60 segundos
    → Cuando llegue a 0, mostrar "El tiempo para pagar expiró" y botón "Volver a elegir franjas"

[ ] Implementar el medio de pago
    → src/components/pago/MedioDePago.tsx
    → Si qr_string está presente: renderizar QR con qrcode.react
    → Si qr_image_base64 está presente: mostrar imagen con base64
    → Si checkout_url está presente: botón "Pagar en el navegador" (window.open)
    → Si ninguno está presente: mensaje de error

[ ] Implementar el polling de estado
    → useEffect con setInterval cada 5 segundos
    → Consultar GET /public/solicitudes-reserva/:codigo/estado
    → Si estado === 'confirmada': navegar a /comprobante/:codigo
    → Si estado === 'expirada': mostrar vista de expiración
    → Si estado === 'rechazada': mostrar "Tu pago no pudo procesarse, intenta nuevamente"
    → Limpiar intervalo en cleanup

[ ] Prevenir navegación atrás
    → window.history.pushState para evitar que el botón atrás del navegador devuelva al formulario con el carrito vacío
    → Botón "Volver al inicio" que navega a / (no atrás)

[ ] Tests
    → Verificar que la cuenta regresiva decrece correctamente
    → Verificar que el QR se renderiza cuando qr_string está presente
    → Verificar que el botón de checkout abre la URL en nueva pestaña
    → Verificar que el polling detecta cambio a 'confirmada' y navega al comprobante
    → Verificar que el polling detecta cambio a 'expirada' y muestra vista de expiración
    → Verificar que el botón "Volver al inicio" navega a /

[ ] Commit de la fase
    → Mensaje: "feat(web-public): pantalla de pago con QR, cuenta regresiva y detección automática de confirmación"
```

---

# FASE WP.7 — Comprobante Digital y Consulta de Estado

## Objetivo

Crear dos páginas finales: el comprobante digital (que muestra las reservas confirmadas con sus códigos) y la consulta manual de estado (para ciudadanos que cerraron la pantalla de pago y quieren ver el estado de su solicitud).

---

## Tareas de la Fase WP.7

```
[ ] Crear src/pages/Comprobante.tsx
    → Ruta: /comprobante/:codigo_seguimiento
    → Consumir GET /public/solicitudes-reserva/:codigo/estado
    → Mostrar: código de seguimiento, monto total pagado, fecha de confirmación
    → Lista de reservas confirmadas con:
        • Código de reserva (RSV-YYYYMMDD-XXXXXX)
        • Nombre del campo, fecha, hora inicio, hora fin
    → Botón "Descargar comprobante" (genera PDF con react-to-print)
    → Botón "Volver al inicio"

[ ] Implementar el generador de PDF
    → src/components/comprobante/ComprobantePDF.tsx
    → Layout imprimible con logo del GAD, datos de la reserva, códigos
    → react-to-print para convertir el componente a PDF

[ ] Crear src/pages/ConsultarEstado.tsx
    → Ruta: /estado
    → Campo de texto para ingresar código de seguimiento
    → Botón "Consultar"
    → Si el código existe: mostrar el mismo layout que Comprobante.tsx
    → Si el código no existe: mostrar "No se encontró ninguna reserva con ese código"

[ ] Reutilizar componentes
    → src/components/comprobante/EstadoReserva.tsx (usado en ambas páginas)
    → Muestra el estado actual de la solicitud (pendiente/confirmada/expirada/rechazada)
    → Si está confirmada, muestra las reservas
    → Si está pendiente, muestra tiempo restante
    → Si está expirada/rechazada, muestra mensaje de error

[ ] Tests
    → Verificar que /comprobante/:codigo muestra las reservas correctamente
    → Verificar que el PDF se genera correctamente
    → Verificar que /estado permite consultar un código válido
    → Verificar que /estado muestra error para código inexistente
    → Verificar que la respuesta del endpoint no incluye datos sensibles (nombre_pagador, telefono_pagador, referencia_recaudaciones)

[ ] Commit de la fase
    → Mensaje: "feat(web-public): comprobante digital con PDF y consulta manual de estado"
```

---

# FASE WP.8 — Smoke Test Final, Optimización y Commit de Cierre

## Objetivo

Verificar que todo el flujo web funciona de punta a punta, optimizar el bundle para producción, y cerrar el módulo con un tag.

---

## Checklist de cierre

```
[ ] Flujo feliz completo en web
    → Landing → Listado → Detalle → Seleccionar 2 franjas → Carrito → Datos solicitante → Pago → Comprobante
    → Verificar que el comprobante muestra las reservas correctas
    → Verificar que el PDF se descarga correctamente

[ ] Flujo de expiración
    → Crear una solicitud y dejarla expirar (sin confirmar)
    → Verificar que la pantalla de pago muestra "El tiempo para pagar expiró"
    → Verificar que /estado muestra el estado "expirada"

[ ] Flujo de error 409
    → Crear una solicitud, simular que otra persona reserva la misma franja
    → Verificar que el web quita la franja del carrito y muestra toast

[ ] SEO de la landing
    → Verificar que Google indexa la página (Google Search Console)
    → Verificar que los meta tags se renderizan correctamente
    → Verificar que el sitemap.xml se genera y es accesible

[ ] Performance (Lighthouse)
    → Landing: score > 90 en Performance, Accessibility, Best Practices, SEO
    → Listado: score > 85 en Performance (el mapa es más pesado)
    → Tiempo de carga inicial < 2 segundos en 4G

[ ] Responsive
    → Verificar que todas las páginas se ven bien en móvil (375px), tablet (768px) y desktop (1440px)
    → Verificar que el menú hamburguesa funciona en móvil

[ ] Accesibilidad (WCAG 2.1 AA)
    → Verificar que todos los botones tienen aria-labels
    → Verificar que el contraste de colores es suficiente
    → Verificar que la navegación por teclado funciona (Tab, Enter, Escape)
    → Verificar que los lectores de pantalla pueden leer el contenido

[ ] Bundle size
    → npm run build
    → Verificar que el bundle total < 500KB gzipped
    → Verificar que el code splitting funciona (chunks separados por ruta)

[ ] Deploy de prueba
    → Desplegar en Vercel/Netlify (gratis para estáticos)
    → Verificar que todas las rutas funcionan en producción
    → Verificar que las variables de entorno se configuran correctamente

[ ] Documentación
    → README.md actualizado con instrucciones de deploy
    → Capturas de pantalla de todas las páginas en docs/screenshots/

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Web Pública - canal web completo con SEO, reserva y pago"
    → Tag sugerido: v0.8.0-web-publica
```

---

## 🎯 Resultado Final del Módulo

Al completar este módulo, el GAD Beni tiene **tres canales** para que los ciudadanos reserven canchas:

1. **App móvil** (Flutter) — para usuarios frecuentes que quieren notificaciones push
2. **Web pública** (React) — para adopción masiva, links compartibles por WhatsApp, SEO
3. **Web admin** (React) — para funcionarios del GAD

Los tres canales consumen los mismos endpoints públicos del backend Laravel, garantizando consistencia de datos y reglas de negocio. El ciudadano puede empezar en el móvil y terminar en la web (o viceversa) sin perder su carrito, porque el carrito vive en el navegador local (zustand/provider), no en el backend.

---

## 📊 Métricas de Éxito

- **Adopción:** > 50% de las reservas vienen del canal web (vs app móvil) en los primeros 3 meses
- **SEO:** la landing aparece en la primera página de Google para "reserva canchas Beni"
- **Performance:** Lighthouse score > 90 en la landing, > 85 en el listado
- **Conversión:** > 30% de los visitantes de la landing llegan hasta la pantalla de pago

---

## 🔮 Próximos Pasos (Fuera de este Roadmap)

- **PWA (Progressive Web App):** agregar manifest.json y service worker para que la web se pueda "instalar" en el celular sin pasar por la app store
- **Notificaciones push web:** cuando el ciudadano confirma una reserva, enviar notificación al navegador (Web Push API)
- **Integración con Google Calendar:** botón "Agregar a mi calendario" en el comprobante
- **Chat de soporte:** widget de chat en vivo (Tawk.to o similar) para ayudar a ciudadanos con problemas
- **Analytics:** integración con Google Analytics 4 para medir conversión y abandono del carrito

---

## 💡 Notas para guardar el roadmap

Guardá este contenido como `docs/roadmap/ROADMAP_WEB_PUBLICA.md` en tu proyecto. Cuando quieras empezar, avisame y arrancamos con la **Fase WP.1** (scaffold del proyecto).




1. **(E) Limpieza + roles** primero (30-60 min): dejás el repo sano y el sidebar coherente. Es rápido y no compite con nada.
2. Después elegí según tu apetito:
   - **Si querés seguir empujando el núcleo del producto:** **(A) Módulo 4 Parte 1 con cobro mockeado**. No es doble trabajo y es lo que más desbloquea (sin solicitudes reales no podés probar bien ni la pantalla Reservas del panel ni el dashboard).
   - **Si querés algo visible para mostrar al jefe/SEDEDE ya:** **(B) landing React de consulta**.
   - **Si querés cerrar el panel:** **(C) pantalla Reservas del Módulo 6**.
