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
| Backend         | Laravel (PHP)             | 8.4 de PHP · Laravel última estable |
| Web admin       | React + Vite + TypeScript | Node 22                             |
| Móvil           | Flutter (Android/iOS)     | 3.x                                 |
| Base de datos   | PostgreSQL                | 16                                  |
| Caché / Colas   | Redis                     | 7                                   |
| Infraestructura | Docker + Nginx            | —                                   |

> **Regla de las 3 fuentes:** las versiones de PHP, Node y PostgreSQL deben
> coincidir exactamente en: (1) `infrastructure/docker/php/Dockerfile`,
> (2) `.github/workflows/backend-ci.yml` y (3) este README.
> Si cambias una, cambias las tres en el mismo commit.

## Toolchain local

| Herramienta    | Versión | Windows                       | Linux/Mac        |
| -------------- | ------- | ----------------------------- | ---------------- |
| PHP            | 8.4     | Laravel Herd (`herd use 8.4`) | mise o apt       |
| Node           | 22      | nvm-windows (`nvm use 22`)    | mise o nvm       |
| Composer       | 2.x     | Incluido en Herd              | getcomposer.org  |
| GUI PostgreSQL | —       | Beekeeper Studio              | Beekeeper Studio |

## Estructura

```
campos-deportivos-gad-beni/
├── backend/          # API Laravel del satélite de Canchas
├── web-admin/        # Panel React + Vite + TS (backoffice)
├── mobile/           # App Flutter pública, sin login
├── infrastructure/   # Docker, Nginx y scripts de deploy
├── docs/
│   ├── roadmap/      # Roadmaps de los módulos 0 → 8
│   ├── architecture/ # Docs 1-3 (pendientes de revisión Hub & Spoke)
│   └── adr/          # Architecture Decision Records
└── .github/workflows # CI/CD: un pipeline por app, filtrado por rutas
```

## Arranque del entorno de desarrollo

```bash
# 1. Infraestructura: PostgreSQL 16 + Redis 7 (se completa en Fase 0.5)
docker compose -f infrastructure/docker-compose.yml up -d

# 2. Backend
cd backend && composer install && php artisan serve

# 3. Panel web administrativo
cd web-admin && npm install && npm run dev
```

## Integración con el Core de Recaudaciones

Para generar cobros necesitas credenciales que emite el equipo del Core:

- `RECAUDACIONES_API_URL` — URL base de la API del Core.
- `RECAUDACIONES_API_TOKEN` — token machine-to-machine. Vive SOLO en
  `backend/.env`, nunca en el código ni en commits.

Sin ellas, el sistema puede mostrar disponibilidad y horarios,
pero no puede generar cobros nuevos.
