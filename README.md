# Campos Deportivos — GAD Beni

> **Satélite de Canchas del ecosistema GAD Beni (patrón Hub & Spoke).**
> Este repositorio contiene ÚNICAMENTE el satélite de Canchas: backend,
> panel web administrativo y app móvil. **Los pagos NO se gestionan aquí:**
> todo cobro se delega vía API al **Core de Recaudaciones**, un proyecto
> independiente con su propio repositorio, equipo y base de datos.

## ¿Qué es este sistema?

Sistema de administración del alquiler de campos deportivos del GAD Beni:
infraestructura deportiva, horarios, tarifas, asignación de funcionarios y
reservas con prevención de doble reserva garantizada a nivel de base de
datos (PostgreSQL `tstzrange` + `EXCLUDE USING gist`).

Cuando un ciudadano reserva, este backend le pide al Core de Recaudaciones
—por API HTTP autenticada— que genere el cobro y devuelva el QR.
Canchas **nunca** habla con bancos ni pasarelas de pago.

## Stack tecnológico

| Capa            | Tecnología                | Versión                             |
| --------------- | ------------------------- | ----------------------------------- |
| Backend         | Laravel (PHP)             | 8.4 de PHP · Laravel 13             |
| Web admin       | React + Vite + TypeScript | Node 22 · React 19                  |
| Móvil           | Flutter (Android/iOS/Web) | 3.44.6                              |
| Base de datos   | PostgreSQL                | 18.4 (local) · 16 (producción)      |
| Caché / Colas   | Redis (Memurai en Windows)| 7                                   |
| Infraestructura | Docker + Nginx (prod)     | —                                   |

> **Nota sobre versiones:** En desarrollo local usamos PostgreSQL 18.4 y
> Memurai (Redis nativo para Windows). En producción se usará PostgreSQL 16
> y Redis 7 en Docker. Las features requeridas (`tstzrange` + `EXCLUDE USING
> gist`) están disponibles desde PostgreSQL 9.x, garantizando compatibilidad.

## Toolchain local (desarrollo)

| Herramienta    | Versión | Windows                       | Linux/Mac        |
| -------------- | ------- | ----------------------------- | ---------------- |
| PHP            | 8.4     | Laravel Herd (`herd use 8.4`) | mise o apt       |
| Node           | 22      | nvm-windows (`nvm use 22`)    | mise o nvm       |
| Composer       | 2.x     | Incluido en Herd              | getcomposer.org  |
| PostgreSQL     | 18.4    | Instalación nativa            | apt o brew       |
| Redis          | Memurai | memurai.com                   | apt install redis|
| GUI PostgreSQL | —       | Beekeeper Studio              | Beekeeper Studio |

## Estructura

```
campos-deportivos-gad-beni/
├── backend/          # API Laravel del satélite de Canchas
├── web-admin/        # Panel React + Vite + TS (backoffice)
├── mobile/           # App Flutter pública, sin login
├── infrastructure/   # Docker, Nginx y scripts de deploy (solo producción)
├── docs/
│   ├── roadmap/      # Roadmaps de los módulos 0 → 8
│   ├── architecture/ # Docs 1-3 (análisis, diccionario, historias)
│   ├── infrastructure/ # LOCAL_SETUP.md (configuración local)
│   └── adr/          # Architecture Decision Records
└── .github/workflows # CI/CD: un pipeline por app, filtrado por rutas
```

## Arranque del entorno de desarrollo

```powershell
# 1. Base de datos: PostgreSQL 18.4 local
#    (Instalación nativa, no Docker — ver docs/infrastructure/LOCAL_SETUP.md)
psql -h localhost -p 5432 -U postgres -c "CREATE DATABASE gad_beni_campos_deportivos;"

# 2. Redis/Memurai local
#    (Memurai para Windows — https://www.memurai.com/get-memurai)
memurai-cli ping  # debe responder PONG

# 3. Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve

# 4. Panel web administrativo
cd web-admin
npm install
npm run dev

# 5. App móvil (Flutter)
cd mobile
flutter pub get
flutter run -d chrome  # o flutter run para Android/iOS
```

## Integración con el Core de Recaudaciones

Para generar cobros necesitas credenciales que emite el equipo del Core:

- `RECAUDACIONES_API_URL` — URL base de la API del Core.
- `RECAUDACIONES_API_TOKEN` — token machine-to-machine. Vive SOLO en
  `backend/.env`, nunca en el código ni en commits.

Sin ellas, el sistema puede mostrar disponibilidad y horarios,
pero no puede generar cobros nuevos.

## Verificación rápida

```powershell
# Backend operativo
curl http://localhost:8000/api/v1/health

# Panel web conectado
# Abre http://localhost:5173 — debe mostrar "Backend conectado"

# App móvil conectada
# flutter run -d chrome — debe mostrar "Backend conectado"
```

## Producción

En producción se usará Docker con PostgreSQL 16 y Redis 7.
Ver `infrastructure/docker-compose.prod.yml` (pendiente de completar).
