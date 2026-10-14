-- ==========================================================================
-- POL-Bolivia — Usuario de base de datos con permisos mínimos
--
-- La aplicación NO debe conectarse con el superusuario "postgres".
-- Este usuario solo puede leer y escribir datos de la base pol_bolivia:
-- no puede crear bases, crear usuarios ni borrar tablas.
--
-- Ejecutar UNA vez como superusuario:
--   psql -U postgres -d pol_bolivia -f database/sql/crear_usuario_aplicacion.sql
-- Antes, reemplazar CAMBIAR_ESTA_CONTRASEÑA por una contraseña larga y aleatoria.
-- Las migraciones (php artisan migrate) se siguen ejecutando con el usuario dueño.
-- ==========================================================================

CREATE ROLE pol_bolivia_app WITH LOGIN PASSWORD 'CAMBIAR_ESTA_CONTRASEÑA'
    NOSUPERUSER NOCREATEDB NOCREATEROLE;

GRANT CONNECT ON DATABASE pol_bolivia TO pol_bolivia_app;
GRANT USAGE ON SCHEMA public TO pol_bolivia_app;

-- Solo operaciones sobre datos (sin DROP, ALTER ni TRUNCATE).
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO pol_bolivia_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO pol_bolivia_app;

-- La auditoría solo admite lectura e inserción.
REVOKE UPDATE, DELETE ON auditorias FROM pol_bolivia_app;

-- Tablas creadas en el futuro por migraciones heredan los mismos permisos.
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO pol_bolivia_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO pol_bolivia_app;
