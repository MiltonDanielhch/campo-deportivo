# Setup Local de Infraestructura

Este proyecto usa infraestructura local en lugar de Docker para desarrollo.

## PostgreSQL 18.4

- **Host:** localhost
- **Puerto:** 5432
- **Base de datos:** gad_beni_campos_deportivos
- **Usuario:** postgres
- **Contraseña:** (configurada en .env)

**Nota:** Aunque el ADR-002 menciona PostgreSQL 16, se usa 18.4 por disponibilidad local.
Las features requeridas (`tstzrange` + `EXCLUDE USING gist`) están disponibles desde PostgreSQL 9.x,
por lo que la compatibilidad está garantizada.

## Redis (Memurai)

- **Host:** 127.0.0.1
- **Puerto:** 6379
- **Cliente:** predis
- **Instalación:** https://www.memurai.com/get-memurai

Memurai es una implementación nativa de Redis para Windows, compatible con el protocolo Redis.

## Configuración

Ver `backend/.env.example` para las variables de entorno requeridas.

## Verificación rápida

```powershell
# PostgreSQL
psql -h localhost -p 5432 -U postgres -c "SELECT version();"

# Redis/Memurai
memurai-cli ping

# Migraciones
cd backend && php artisan migrate:status

# Caché
php artisan tinker
>>> Cache::put('test', 'ok', 60);
>>> Cache::get('test');
