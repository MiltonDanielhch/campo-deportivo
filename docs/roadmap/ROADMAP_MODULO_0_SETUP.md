# 📁 ROADMAP_MODULO_0_SETUP.md

**Proyecto:** Sistema de Administración del Alquiler de Campos Deportivos — GAD Beni
**Arquitectura:** Módulo Satélite (_Spoke_) del ecosistema GAD Beni — patrón Hub & Spoke
**Versión:** 2.0.0 · **Formato:** Guía Arquitectónica Explicativa
**Tiempo estimado:** 4–6 horas · **Bloquea:** Todos los módulos posteriores (1→8)

> **Objetivo del Módulo:** Construir el andamio completo del sistema de
> Campos Deportivos como **satélite** dentro del ecosistema GAD Beni: tres
> aplicaciones (backend, web administrativa y móvil) que gestionan
> infraestructura deportiva, horarios, asignación de funcionarios y
> reservas — pero que **ya no procesan pagos directamente**. Todo cobro se
> delega, vía API, al **Core de Recaudaciones**. Aquí no se escribe lógica de
> negocio: se establecen las convenciones, la estructura de carpetas, los
> entornos y los guardianes de calidad que todo el proyecto respetará de aquí
> en adelante. Un error en este módulo se propaga a los 8 módulos restantes.

> **Relación con el Core de Recaudaciones:** el Core es un sistema central
> autónomo (Laravel + React), con su propio repositorio y equipo, que
> registra clientes (CI/NIT), genera liquidaciones, integra pasarelas de pago
> (AGETIC/SINTESIS), recibe webhooks bancarios, procesa pagos manuales,
> concilia y factura. **Es el único sistema del ecosistema que habla con
> bancos.** El backend de Canchas nunca se conecta directamente a una
> pasarela: cuando un ciudadano quiere reservar, le pide al Core, por API
> HTTP autenticada, que genere el cobro y le devuelva el QR correspondiente.
> Este roadmap cubre exclusivamente el satélite de Canchas — el Core se
> construye y se versiona en su propio repositorio, por otro equipo.

> **Nota de alcance:** los Documentos 1, 2 y 3 (arquitectura, diccionario de
> datos y backlog) que este proyecto ya tiene fueron escritos para la
> arquitectura anterior, donde Canchas manejaba sus propias pasarelas de pago.
> Ese contenido se seguirá usando como referencia de las reglas de negocio de
> reservas (horarios, tarifas, prevención de doble reserva), pero las
> secciones relativas a `ordenes_pago`, pasarelas, Circuit Breaker y
> contingencia quedan **desactualizadas** por este cambio y se revisarán
> módulo por módulo, empezando por este.

---

## 🗺️ Mapa del Módulo

```
Módulo 0
├── Fase 0.1 → Monorepo + Git + Convenciones de equipo
├── Fase 0.2 → Backend / API Gateway (Laravel) — esqueleto limpio y cliente del Core
├── Fase 0.3 → Web Administrativa (React + Vite + TypeScript) — esqueleto limpio
├── Fase 0.4 → App Móvil (Flutter) — esqueleto limpio, sin autenticación
├── Fase 0.5 → Infraestructura Docker local (PostgreSQL + Redis)
├── Fase 0.6 → CI/CD con GitHub Actions
└── Fase 0.7 → Smoke test final y commit de cierre
```

---

## 🟥 Estado: `[ ] Pendiente`

---

# FASE 0.1 — Monorepo, Git y Convenciones de Equipo

## ¿Qué es un Monorepo y por qué lo usamos?

Un **Monorepo** significa que las tres aplicaciones del _satélite_ de Canchas
— backend (Laravel), panel web (React + Vite + TS) y app móvil (Flutter) —
viven dentro de un único repositorio Git. Importante: este monorepo cubre
**solo el satélite de Canchas**, no el ecosistema GAD Beni completo. El Core
de Recaudaciones es un proyecto independiente, con su propio repositorio,
equipo y ciclo de despliegue — la única relación entre ambos es una API HTTP,
nunca código ni base de datos compartida.

Para un equipo pequeño con un dominio de negocio cohesionado (infraestructura
deportiva, horarios, reservas), el Monorepo simplifica la coordinación entre
las tres apps propias: un solo `git clone`, un solo pipeline por app, y una
única fuente de verdad para las convenciones compartidas.

---

## Estructura de carpetas objetivo

```
campos-deportivos-gad-beni/     ← Raíz del Monorepo del satélite de Canchas. El único lugar con .git/
│
├── backend/                    ← Backend de Canchas (Laravel) — NO el Core de Recaudaciones
├── web-admin/                  ← Todo lo de React + Vite + TS vive aquí
├── mobile/                     ← Todo lo de Flutter vive aquí
│
├── infrastructure/             ← Archivos de Docker, Nginx y scripts de despliegue
│   ├── docker/
│   │   ├── nginx/              ← Configuración del proxy inverso
│   │   └── php/                ← Overrides de PHP para el contenedor
│   ├── scripts/                ← Scripts de healthcheck y deploy automatizado
│   ├── docker-compose.yml          ← Entorno de desarrollo local
│   └── docker-compose.prod.yml     ← Configuración de referencia para producción
│
├── docs/
│   ├── roadmap/                ← Aquí viven todos estos archivos ROADMAP_*.md
│   ├── architecture/           ← Documentos 1, 2 y 3 (a revisar por este cambio)
│   └── adr/                    ← Architecture Decision Records
│
├── .github/
│   └── workflows/              ← Pipelines de CI/CD automáticos
│
├── .gitignore                  ← Exclusiones unificadas para TODO el monorepo
├── .editorconfig                ← Reglas de formato para todo el equipo
└── README.md                   ← Descripción del ecosistema
```

**¿Por qué `infrastructure/` existe desde el Módulo 0?**
El motor de base de datos (PostgreSQL) usa características específicas —
`tstzrange`, restricciones `EXCLUDE USING gist` — que dependen de la versión y
extensiones instaladas. Tener Docker desde el principio garantiza que tu
máquina local y el servidor de producción usan exactamente la misma versión
de PostgreSQL, Redis y PHP. Esto es independiente de cómo se resuelva el
cobro — es sobre la integridad del calendario de reservas, no sobre dinero.

---

## Tareas de la Fase 0.1

```
[x] Crear el directorio raíz: campos-deportivos-gad-beni/
    → Es el contenedor del satélite de Canchas. Nada sale de esta
      carpeta. El Core de Recaudaciones NO vive aquí.

[x] Inicializar Git SOLO en la raíz
    → Entrar al directorio y ejecutar git init, luego renombrar la rama
      principal a 'main' (convención moderna estándar).
    → ADVERTENCIA CRÍTICA: nunca ejecutar git init dentro de backend/,
      web-admin/ ni mobile/. Composer, npm y Flutter pueden intentar crear
      su propio .git al instalar el proyecto. Si eso ocurre, eliminar ese
      .git interno inmediatamente antes de hacer cualquier commit.

[x] Crear el árbol de directorios completo
    → Todos los directorios del diagrama de arriba, incluyendo los
      subdirectorios de infrastructure/, docs/ y .github/workflows/.
    → Los directorios vacíos no se trackean en Git. Para que existan en
      el repositorio, crear un archivo .gitkeep vacío dentro de cada
      carpeta que no tenga archivos todavía.

[x] Copiar los Documentos 1, 2 y 3 a docs/architecture/
    → doc-1-analisis-modulos-bd.md, doc-2-diccionario-datos.md,
      doc-3-historias-usuario.md. Se versionan igual, marcados como
      pendientes de actualizar a la arquitectura Hub & Spoke — no se
      descartan, siguen siendo la referencia de las reglas de negocio de
      reservas y horarios.

[x] Definir el toolchain local por plataforma y documentarlo en el README
    → Windows (caso actual): PHP 8.4 vía Laravel Herd (`herd use 8.4`
      por proyecto) y Node 22 vía nvm-windows (`nvm install 22` +
      `nvm use 22`). Verificar con `php -v`, `node -v` y
      `composer --version`.
    → Linux/Mac/WSL: quien quiera puede usar mise (`mise use php@8.4
      node@22`) o instaladores nativos. El repo no impone herramienta,
      solo versiones.
    → NO se commitea mise.toml: las fuentes de verdad de versiones son
      Docker (runtime autoritativo) + CI + README.
    → Añadir al .gitignore: `.local/` y `.mise/` (por si algún dev usa
      mise localmente, sus binarios nunca se suben).

[x] Crear el archivo .editorconfig en la raíz
    → Reglas clave: 4 espacios de indentación para la mayoría de
      archivos, UTF-8, fin de línea LF, línea en blanco al final.
      2 espacios para .dart, .ts, .tsx, .yml y .json.

[ ] Crear el .gitignore unificado en la raíz
    → Secciones: BACKEND (backend/.env pero no .env.example,
      backend/vendor/, backend/bootstrap/cache/, backend/storage/logs/
      y demás carpetas de storage/framework/), WEB ADMINISTRATIVA
      (web-admin/.env, node_modules/, dist/, .vite/), MOBILE (build/,
      .dart_tool/, .packages, .flutter-plugins, android/.gradle/,
      android/local.properties, ios/Pods/, ios/.symlinks/), SEGURIDAD
      CRÍTICA (*.pem, *.key, *.cert, *.p12, *.jks — certificados y
      llaves privadas; sigue siendo crítico aunque Canchas ya no hable
      con bancos directamente, porque puede necesitarlos para
      autenticarse contra el Core), IDEs Y ENTORNO LOCAL (.idea/,
      .vscode/, .DS_Store, Thumbs.db).
    → Verificación inmediata: después de crear este archivo, ejecutar
      git status. Si aparecen vendor/, node_modules/ o build/ en la
      lista, el .gitignore tiene un error y debe corregirse antes de
      continuar.

[ ] Crear el README.md principal
    → Debe responder en 30 segundos: ¿qué es este sistema?, ¿cómo se
      arranca el entorno local?, ¿cuál es la estructura? Debe dejar
      explícito, desde la primera sección, que este repositorio es el
      satélite de Canchas y que los pagos se gestionan en el Core de
      Recaudaciones (proyecto aparte).
    → Secciones mínimas: descripción del sistema, tabla del stack
      tecnológico con versiones exactas, árbol de carpetas resumido, y
      los 3 comandos exactos para arrancar el entorno de desarrollo
      (se completan en la Fase 0.5).

[x] Crear el primer Architecture Decision Record (ADR)
    → docs/adr/ADR-001-monorepo-estructura.md — por qué Monorepo sobre
      Polyrepo para las tres apps propias del satélite (backend, web,
      mobile), aclarando que esto no incluye al Core de Recaudaciones.

[x] Crear el segundo ADR sobre la elección de PostgreSQL
    → docs/adr/ADR-002-postgresql-sobre-mysql.md — se detalla en la
      Fase 0.5, con la justificación actualizada a la arquitectura
      Hub & Spoke.

[x] Crear el tercer ADR sobre la arquitectura Hub & Spoke
    → docs/adr/ADR-003-hub-and-spoke-delegacion-de-cobro.md
    → Contexto: el GAD Beni está construyendo varios sistemas
      (Canchas es uno de varios satélites posibles a futuro), y cada
      uno reimplementando su propia integración bancaria, conciliación
      y facturación sería costoso de mantener y un riesgo de seguridad
      innecesariamente distribuido.
    → Decisión: Canchas nunca se integra directamente con una pasarela
      de pago. Delega la generación y confirmación del cobro al Core de
      Recaudaciones mediante una API HTTP autenticada
      (RECAUDACIONES_API_TOKEN). Canchas mantiene su propia base de
      datos PostgreSQL, separada de la del Core, con la única
      responsabilidad de horarios, campos y reservas.
    → Consecuencias positivas: el backend de Canchas se simplifica
      radicalmente (no necesita patrón Strategy ni Circuit Breaker de
      pasarelas, no maneja datos bancarios ni de tarjetas); la lógica de
      conciliación y facturación vive en un solo lugar del ecosistema.
    → Consecuencias negativas / riesgos a documentar: Canchas depende
      de la disponibilidad del Core para generar cobros nuevos (aunque
      puede seguir mostrando disponibilidad y horarios sin él); existe
      acoplamiento al contrato de API que el Core exponga, que debe
      versionarse con cuidado desde ambos lados.

[x] Realizar el primer commit
    → En este punto el repositorio solo debe tener archivos de texto:
      .gitignore, .editorconfig, README.md, los .gitkeep, los tres ADR
      y los documentos de arquitectura. Sin código de Laravel, React ni
      Flutter todavía.
    → Mensaje: "chore: setup inicial del monorepo satélite de Canchas"
```

---

# FASE 0.2 — Inicialización del Backend (Laravel)

## Qué es este backend en la arquitectura Hub & Spoke

Es el punto de entrada de las tres apps propias de Canchas (panel web y app
móvil) — sigue siendo el guardián de que ninguna reserva se confirme sin un
pago verificado. La diferencia clave frente a la arquitectura anterior: este
backend **ya no decide ni gestiona cómo se cobra**. Esa responsabilidad
completa —pasarela, Circuit Breaker, verificación de webhooks bancarios—
queda fuera de este sistema, en el Core de Recaudaciones. El backend de
Canchas solo necesita saber pedirle un cobro al Core y enterarse cuándo ese
cobro se confirmó.

## Capas internas que crearás dentro de backend/app/

**`Http/Controllers/Api/V1/`**
Los controladores de la API, versionados desde el día uno con `/V1/`.
Delgados: reciben la petición, llaman a un Service, devuelven JSON.

**`Services/`**
La lógica de negocio de Canchas: disponibilidad, creación de reservas,
asignación de funcionarios. Sin lógica de pasarelas — eso ya no existe aquí.

**`Integrations/Recaudaciones/`**
Reemplaza por completo lo que en la arquitectura anterior era
`Integrations/Pasarelas/`. Esta es ahora la **única** integración externa de
este backend: un cliente HTTP hacia la API del Core de Recaudaciones. Al
haber un solo sistema externo con el que hablar —no varios proveedores
compitiendo entre sí— **ya no hace falta el patrón Strategy ni el Circuit
Breaker** que la arquitectura anterior necesitaba para tolerar la caída de un
banco. Esa complejidad desaparece del satélite de Canchas por completo; si el
Core cae, es el Core quien debe resolver su propia resiliencia interna (con
sus propios proveedores), no Canchas.

**`DTOs/`**
Estructuras tipadas para los datos que viajan entre capas — por ejemplo, la
franja horaria que se envía al solicitar un cobro (`FranjaSolicitadaDTO`).

**`Enums/`**
Valores con opciones fijas — por ejemplo, el estado local de una solicitud de
reserva (pendiente de confirmación del Core, confirmada, expirada, cancelada).
Evitan strings mágicos dispersos por el código.

**`Exceptions/`**
Manejadores de excepción personalizados, incluyendo el caso de que el Core de
Recaudaciones no responda o responda con error.

**`Jobs/`**
Tareas en segundo plano — la más importante: el job que expira
automáticamente una reserva cuya confirmación de pago no llegó a tiempo desde
el Core. Se prepara la carpeta ahora; el job se implementa en un módulo
posterior, sobre el Redis que se deja listo en la Fase 0.5.

---

## Tareas de la Fase 0.2

```
[x] Activar el entorno local (estrategia de dos capas)
    → Docker = runtime autoritativo (Fase 0.5): PostgreSQL + Redis +
      PHP-FPM 8.4 idénticos a producción.
    → Tu máquina = CLI local rápido: PHP 8.4 de Herd y Node 22 de
      nvm-windows, para correr `composer`, `php artisan`, `pint` y
      `npm` sin levantar contenedores.
    → Verificar antes de crear el proyecto Laravel:
      `php -v` → 8.4.x · `node -v` → 22.x · `composer --version` → 2.x

[x] Crear el proyecto Laravel dentro de backend/
    → Usar Composer apuntando a la última versión estable, creando el
      proyecto directamente dentro de backend/ (terminando el comando
      con un punto).
    → Verificar: php artisan --version.

[x] Configurar el archivo backend/.env inicial
    → APP_NAME: "Backend Canchas Deportivas - GAD Beni"
    → APP_TIMEZONE: America/La_Paz — sigue siendo crítico: aunque el
      dinero ya no lo gestiona este sistema, el momento exacto en que
      una reserva expira o un horario queda libre sigue siendo un dato
      sensible que depende de la zona horaria correcta desde el primer
      día.
    → DB_CONNECTION: pgsql (no mysql — ver ADR-002).
    → DB_DATABASE: gad_beni_campos_deportivos — la base de datos de
      Canchas, completamente separada de la base de datos del Core de
      Recaudaciones. Ningún dato bancario ni de conciliación vive aquí.
    → QUEUE_CONNECTION: sync en desarrollo, 'redis' cuando se
      implemente el job de expiración.
    → CACHE_STORE: database en desarrollo, redis en producción.
    → RECAUDACIONES_API_URL: URL base de la API del Core de
      Recaudaciones (ej. https://recaudaciones.beni.gob.bo/api/v1 —
      valor real a confirmar con el equipo del Core).
    → RECAUDACIONES_API_TOKEN: token de autenticación de servicio
      (machine-to-machine) que el Core emite para que Canchas pueda
      llamar a su API. Nunca se escribe en el código, solo en .env —
      mismo criterio de secretos que cualquier otra credencial sensible.

[x] Generar la Application Key
    → php artisan key:generate.

[x] Instalar los paquetes de producción
    → laravel/sanctum: sigue siendo necesario — es la autenticación del
      panel web administrativo de Canchas (funcionarios), que no tiene
      relación con el Core de Recaudaciones.
    → predis/predis: sigue siendo necesario para Redis (colas, caché).

[x] Instalar los paquetes de desarrollo
    → laravel/pint y PHPUnit (incluido por defecto con Laravel).

[x] Publicar la configuración de la API nativa de Laravel
    → php artisan install:api — publica config/sanctum.php, crea
      routes/api.php, genera la migración de personal_access_tokens.

[x] Crear la estructura de directorios desacoplada dentro de backend/app/
    → Http/Controllers/Api/V1/, Services/, Integrations/Recaudaciones/,
      DTOs/, Enums/, Exceptions/, Jobs/.
    → NO se crea Integrations/Pasarelas/ ni ningún archivo relacionado
      a pasarelas bancarias directas — esa carpeta no existe en este
      backend.
    → Crear también tests/Feature/Api/V1/.

[x] Crear RecaudacionesApiClient
    → app/Integrations/Recaudaciones/RecaudacionesApiClient.php — un
      cliente HTTP (usando el facade Http de Laravel, que internamente
      usa Guzzle) configurado con RECAUDACIONES_API_URL y
      RECAUDACIONES_API_TOKEN como header Bearer en cada llamada.
    → Nota honesta de alcance: este roadmap puede dejar lista la
      estructura del cliente (constructor, manejo de timeout, manejo de
      errores HTTP), pero no puede inventar el contrato exacto de la
      API del Core (rutas, forma del payload de "solicitar cobro",
      forma de la respuesta con el QR) — eso depende de la
      documentación que el equipo del Core entregue. Dejar un método
      de ejemplo con la firma esperada (ej. solicitarCobro(array
      $franjas, array $datosSolicitante): RespuestaCobroDTO) con un TODO
      explícito hasta contar con esa documentación real.
    → A diferencia de las antiguas SintesisGateway/BancoUnionGateway,
      aquí NO se implementa Circuit Breaker: es una única dependencia
      externa, y su disponibilidad es responsabilidad del Core, no de
      Canchas.

[x] Crear el HealthController y registrar su ruta
    → app/Http/Controllers/Api/V1/HealthController.php
    → GET /api/v1/health, sin autenticación. Responde status
      ("operational"), nombre del servicio, versión, y timestamp ISO
      8601 en hora boliviana.
    → Registrar en routes/api.php bajo el prefijo /v1/.

[x] Crear el primer test de PHPUnit
    → tests/Feature/Api/V1/HealthTest.php — GET /api/v1/health
      devuelve 200 y status "operational".

[x] Configurar Pint con las reglas del proyecto
    → backend/pint.json con el preset "laravel". Ejecutar
      ./vendor/bin/pint --test, debe pasar en verde.

[x] Verificar que el servidor local levanta sin errores
    → php artisan serve, y confirmar
      http://localhost:8000/api/v1/health responde correctamente.
```

---

# FASE 0.3 — Inicialización de la Web Administrativa (React + Vite + TypeScript)

## Qué es este panel y quién lo usa

El backoffice donde trabajan el Administrador de Paramétricas, el
Funcionario de Control y Gerencia — gestión de infraestructura deportiva,
horarios, tarifas, usuarios y visualización de reservas. **Ya no incluye**
reportes de ingresos globales ni el flujo de aprobación de contingencia de
pagos: ambas cosas viven ahora en el panel del Core de Recaudaciones, que es
quien tiene la visibilidad financiera completa del ecosistema.

## Estructura de carpetas objetivo dentro de web-admin/src/

**`pages/`**
Organizadas por dominio, únicamente sobre lo que Canchas sigue gestionando:

- `campos/` — CRUD de campos deportivos, tipos y horarios de atención.
- `tarifas/` — versionado de precios por hora.
- `usuarios/` — funcionarios, roles, asignación de control.
- `ocupacion/` — mapa y grilla de ocupación en tiempo real.
- `reservas/` — listado y búsqueda de reservas confirmadas (de solo
  lectura respecto al pago: el detalle financiero de cada cobro, si se
  necesita, se consulta en el panel del Core).

Deliberadamente **no** existe `contingencia/` ni una sección de reportes de
ingresos — eso ya no es responsabilidad de este panel.

**`components/`, `services/`, `types/`, `hooks/`, `context/`**
Mismo criterio que en cualquier módulo anterior: componentes reutilizables
sin lógica de negocio propia, un archivo de servicio por dominio que
encapsula las llamadas HTTP al backend de Canchas (nunca al Core
directamente — el panel de Canchas no le habla al Core, solo el backend de
Canchas lo hace), tipos alineados al diccionario de datos, y el estado de
sesión/rol del usuario autenticado.

---

## Tareas de la Fase 0.3

```
[x] Crear el proyecto con Vite dentro de web-admin/
    → npm create vite@latest . -- --template react-ts
    → Verificar: npm run dev levanta sin errores.

[x] Configurar el archivo web-admin/.env inicial
    → VITE_API_BASE_URL=http://localhost:8000/api/v1 (el backend de
      Canchas — este panel nunca apunta directamente a la URL del Core
      de Recaudaciones).
    → Crear también .env.example.

[x] Instalar las dependencias base
    → react-router-dom, axios (o fetch nativo), ESLint + Prettier.

[x] Crear la estructura de carpetas descrita arriba
    → pages/ (con las 5 subcarpetas listadas — sin contingencia/),
      components/, services/, types/, hooks/, context/.

[x] Crear el cliente HTTP base
    → services/apiClient.ts — URL base, headers de autenticación
      (token de Sanctum), manejo uniforme de errores.

[x] Crear una pantalla de verificación de conectividad
    → Llama a GET /api/v1/health del backend de Canchas y muestra el
      resultado — prueba de humo de que el frontend y el backend de
      Canchas se comunican (no involucra al Core en absoluto).

[x] Configurar CORS en el backend de Canchas
    → backend/config/cors.php — agregar http://localhost:5173.

[x] Verificar el build de producción
    → npm run build sin errores de TypeScript ni de lint.

[x] Primer commit del panel web
    → Mensaje: "chore: scaffolding inicial del panel web administrativo de Canchas"
```

---

# FASE 0.4 — Inicialización de la App Móvil (Flutter)

## Recordatorio de diseño: app pública, sin autenticación, y sin lógica de cobro propia

La app móvil sigue sin requerir login. El cambio importante para este módulo
es conceptual, y vale la pena dejarlo escrito desde el scaffolding: cuando más
adelante se construya la pantalla de pago, **esa pantalla no generará ni
gestionará el QR por sí misma** — solo mostrará el QR (string o imagen) que el
backend de Canchas le entregue en la respuesta de su propia API. El backend
de Canchas, a su vez, obtuvo ese QR pidiéndoselo al Core de Recaudaciones. La
app móvil nunca se comunica con el Core directamente, ni sabe que existe.

## Estructura de carpetas objetivo dentro de mobile/lib/

**`screens/`**
Una pantalla por vista: listado de campos, mapa, grilla de disponibilidad,
formulario de datos del solicitante, pantalla de pago (mostrando el QR que
entrega el backend de Canchas), confirmación, y consulta de estado.

**`widgets/`, `services/`, `models/`**
Mismo criterio que en cualquier módulo anterior — el cliente HTTP de
`services/` apunta siempre y únicamente al backend de Canchas.

---

## Tareas de la Fase 0.4

```
[x] Crear el proyecto Flutter dentro de mobile/
    → flutter create . con el org de la organización y las plataformas
      objetivo (android, ios).
    → Verificar: flutter doctor sin errores bloqueantes.

[x] Configurar la gestión de variables de entorno
    → flutter_dotenv (o --dart-define) con API_BASE_URL apuntando al
      backend de Canchas (http://10.0.2.2:8000/api/v1 en el emulador
      Android) — nunca una URL del Core de Recaudaciones.

[x] Instalar las dependencias base
    → http o dio para consumir la API del backend de Canchas.

[x] Crear la estructura de carpetas descrita arriba
    → screens/, widgets/, services/, models/.

[x] Crear una pantalla de verificación de conectividad
    → Llama a GET /api/v1/health del backend de Canchas.

[x] Verificar que la app corre en un emulador o dispositivo
    → flutter run, confirmar que la pantalla de verificación muestra
      "operational".

[x] Primer commit de la app móvil
    → Mensaje: "chore: scaffolding inicial de la app móvil de Canchas"
```

---

# FASE 0.5 — Infraestructura Docker Local (PostgreSQL + Redis)

## Por qué PostgreSQL, con la justificación actualizada

En la arquitectura anterior, PostgreSQL se justificaba en parte por el
volumen de datos financieros que Canchas manejaba. Esa razón **ya no aplica**:
Canchas no almacena datos de pagos, tarjetas, ni concilia nada — toda esa
información vive exclusivamente en la base de datos del Core de
Recaudaciones, a la que Canchas no tiene ni necesita acceso directo.

La razón por la que PostgreSQL sigue siendo, sin ambigüedad, la elección
correcta es **una sola y sigue siendo crítica**: el sistema de Canchas
necesita evitar, a nivel de base de datos, que dos personas reserven la misma
cancha a la misma hora — un problema de **concurrencia física sobre un
recurso compartido** (el horario de una cancha), no un problema financiero.
Eso se resuelve con el tipo `tstzrange` y una restricción `EXCLUDE USING
gist` sobre `(campo_id, rango_horario)`, una capacidad que MySQL no ofrece de
forma nativa equivalente. Dicho de otro modo: si mañana Canchas dejara de
cobrar cualquier cosa, seguiría necesitando PostgreSQL igual, solo para
garantizar que el calendario de reservas nunca se pisa a sí mismo.

## Sobre Redis

Redis se deja disponible desde ahora para los procesos en segundo plano que
la lógica de reservas necesitará más adelante: expirar una reserva cuya
confirmación de pago no llegó a tiempo desde el Core, y sincronizar
periódicamente el estado de una solicitud de cobro pendiente contra la API
del Core (el mecanismo exacto —webhook entrante del Core, o consulta activa
desde Canchas— se define al construir esos módulos de lógica de negocio, no
aquí).

---

## Tareas de la Fase 0.5

```
[N/A] Crear infrastructure/docker-compose.yml con los siguientes servicios:
    → db: imagen postgres:16, con variables de entorno para usuario,
      contraseña y nombre de base de datos coincidentes con
      backend/.env, volumen nombrado para persistencia, healthcheck con
      pg_isready. Esta base de datos es exclusiva de Canchas.
    → redis: imagen redis:7, con volumen nombrado.
    → backend: build desde infrastructure/docker/php/, montando
      backend/ como volumen, dependiente de db y redis "healthy".
    → nginx: proxy inverso hacia el contenedor de PHP.

[N/A] Crear infrastructure/docker/nginx/default.conf
    → Proxy inverso hacia php-fpm, sirviendo desde backend/public/.

[N/A] Crear infrastructure/docker/php/Dockerfile (Runtime Autoritativo)
    → Imagen base: `php:8.4-fpm` (debe coincidir EXACTAMENTE con
      lo declarado en `mise.toml`).
    → Instalar dependencias del sistema (`libpq-dev`, `libzip-dev`,
      `unzip`) y compilar las extensiones `pdo_pgsql`, `zip`, `bcmath`
      e `intl`.
    → Copiar el binario oficial de Composer 2.
    → **Regla de oro de versionado:** la versión de PHP debe aparecer
      idéntica en exactamente 3 lugares del repositorio. Si mañana
      actualizas a PHP 8.5, cambias los tres en el mismo commit:
      1. `infrastructure/docker/php/Dockerfile` (runtime autoritativo).
      2. `.github/workflows/backend-ci.yml` (CI).
      3. `README.md` (tabla de toolchain local para devs).

[x] Actualizar backend/.env para apuntar a los servicios locales
    → DB_HOST=localhost, REDIS_HOST=127.0.0.1 (Memurai).
    → Confirmar que RECAUDACIONES_API_URL sigue apuntando a una URL
      externa real (o a un mock local de desarrollo, ver nota abajo),
      no a un servicio dentro de esta infraestructura local — el Core
      de Recaudaciones no es parte de esta infraestructura.

[ ] (Opcional, recomendado) Preparar un mock local del Core para desarrollo
    → Mientras el equipo del Core no entregue un ambiente de pruebas
      accesible, puede ser útil un servicio adicional y simple (ej. un
      contenedor liviano que simule las respuestas esperadas de
      RecaudacionesApiClient) para poder seguir desarrollando Canchas
      sin bloquear por la disponibilidad del otro equipo. Se deja como
      tarea opcional a criterio del equipo, no como parte obligatoria
      del cierre de este módulo.

[N/A] Verificar que el entorno completo levanta
    → docker compose -f infrastructure/docker-compose.yml up -d desde
      la raíz. Confirmar que db, redis y backend/nginx quedan
      "healthy"/"running" sin reinicios en bucle.

[x] Verificar la conexión de Laravel a PostgreSQL
    → php artisan migrate dentro del entorno local.
    → 4 migraciones ejecutadas: users, cache, jobs, personal_access_tokens.

[x] Verificar la conexión a Redis
    → php artisan tinker y Illuminate\Support\Facades\Redis::ping()
      respondió "PONG" (Memurai).

[N/A] Dejar un docker-compose.prod.yml de referencia (sin completar)

[x] Documentar el setup local de infraestructura
    → Crear docs/infrastructure/LOCAL_SETUP.md con instrucciones de
      PostgreSQL 18.4 local y Memurai como Redis nativo para Windows.

[x] Commit de infraestructura
    → Mensaje: "chore: configuración de infraestructura local (PostgreSQL + Memurai)"
```

---

# FASE 0.6 — CI/CD con GitHub Actions

Sin cambios respecto a la versión anterior de este módulo: se mantienen los
tres pipelines, uno por aplicación, cada uno filtrado por rutas para no
desperdiciar minutos de ejecución en cambios que no le corresponden.

---

## Tareas de la Fase 0.6

```
[ ] Crear .github/workflows/backend-ci.yml
    → Disparado solo con cambios en backend/**.
    → Pasos: checkout, instalar PHP 8.4 usando la acción
      `shivammathur/setup-php@v2` (paseando explícitamente las
      extensiones `pdo_pgsql, mbstring, zip, intl, bcmath`),
      composer install, ./vendor/bin/pint --test, php artisan test.
    → **Advertencia de CI:** Nunca dejes que la acción de GitHub elija
      la versión de PHP por defecto o use etiquetas como "latest".
      Pinea siempre la versión exacta (`php-version: '8.4'`) para
      evitar el clásico bug de "en mi máquina funciona" si GitHub
      actualiza sus runners en el futuro.

[ ] Crear .github/workflows/web-admin-ci.yml
    → Disparado solo con cambios en web-admin/**. Pasos: checkout,
      instalar Node, npm ci, npm run lint, npx tsc --noEmit, npm run build.

[ ] Crear .github/workflows/mobile-ci.yml
    → Disparado solo con cambios en mobile/**. Pasos: checkout,
      instalar Flutter, flutter pub get, flutter analyze, flutter test.

[ ] Verificar los tres pipelines con un Pull Request de prueba
    → Confirmar que cada uno se dispara únicamente ante cambios en su
      propia carpeta.

[ ] Commit de configuración de CI
    → Mensaje: "ci: pipelines de backend, web-admin y mobile con GitHub Actions"
```

---

# FASE 0.7 — Smoke Test Final y Commit de Cierre

## Objetivo de esta fase

Confirmar que el andamiaje del satélite de Canchas funciona de punta a punta
—incluyendo que su único punto de contacto externo, el cliente del Core de
Recaudaciones, está correctamente aislado y configurado— antes de escribir
la primera línea de lógica de negocio en el Módulo 1.

---

## Checklist de cierre del Módulo 0

```
[ ] docker compose up levanta PostgreSQL, Redis y el backend de Canchas
    sin errores.

[ ] GET /api/v1/health responde 200 con la hora en formato boliviano
    correcto.

[ ] El panel web se conecta al backend de Canchas y su pantalla de
    verificación muestra "operational".

[ ] La app móvil se conecta al backend de Canchas y su pantalla de
    verificación muestra "operational".

[ ] RECAUDACIONES_API_URL y RECAUDACIONES_API_TOKEN están definidos en
    backend/.env.example (sin valores reales) y documentados en el
    README, para que cualquiera que clone el repo sepa que debe
    solicitarlos al equipo del Core antes de poder generar cobros.

[ ] No existe ninguna carpeta ni clase relacionada a
    Integrations/Pasarelas/, SintesisGateway, BancoUnionGateway ni
    Circuit Breaker en este repositorio — esa responsabilidad quedó
    completamente fuera del satélite de Canchas.

[ ] Los tres pipelines de CI pasan en verde sobre un Pull Request de
    prueba, cada uno disparado únicamente por cambios en su carpeta.

[ ] git status no muestra vendor/, node_modules/, build/ ni ningún .env
    real.

[ ] Los tres ADR (monorepo, PostgreSQL, Hub & Spoke) y los tres
    Documentos de arquitectura (marcados como pendientes de revisión)
    están versionados en docs/.

[ ] Commit final de cierre del módulo
    → Mensaje: "chore: cierre Módulo 0 - setup del satélite de Canchas (Hub & Spoke)"
    → Tag sugerido: v0.1.0-setup-hub-spoke
```

> **Siguiente módulo:** con el andamiaje verificado, el Módulo 1 debe
> revisarse a fondo antes de continuar — el Documento 2 (Diccionario de
> Datos) tal como existe hoy incluye tablas enteras (`ordenes_pago`,
> `pagos_confirmados`, `pagos_contingencia`, `intentos_pasarela`,
> `proveedores_pasarela`) que pertenecían a una responsabilidad que ya no es
> de Canchas. Ese módulo se reescribe a continuación, con un modelo de datos
> reducido a lo que el satélite realmente necesita: campos, horarios,
> tarifas, funcionarios, y una referencia liviana a las solicitudes de cobro
> hechas al Core.
