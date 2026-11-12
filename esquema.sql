-- =====================================================================
-- Sistema web de gestión de tareas — Esquema de base de datos (PostgreSQL 14+)
-- Diseño: 2 tablas independientes, mínimas y con integridad en la BD.
-- Idempotente: se puede ejecutar varias veces sin error.
-- Uso: ejecutar en el editor SQL de tu PostgreSQL (o: psql -f esquema.sql)
-- =====================================================================

-- ---------------------------------------------------------------------
-- usuarios: quién inicia sesión y con qué rol.
-- El rol lo consulta el Proxy (proxy/Proxy.php, CU1) antes de eliminar.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id            INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre        VARCHAR(50)  NOT NULL
                CHECK (length(trim(nombre)) BETWEEN 3 AND 50),
  password_hash VARCHAR(100) NOT NULL,                 -- hash bcrypt, nunca texto plano
  rol           VARCHAR(10)  NOT NULL DEFAULT 'usuario'
                CHECK (rol IN ('admin', 'usuario')),
  creado_en     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- "Admin" y "admin" son el mismo usuario.
CREATE UNIQUE INDEX IF NOT EXISTS ux_usuarios_nombre ON usuarios (lower(nombre));

-- ---------------------------------------------------------------------
-- tareas: lo que se lista, se crea, se elimina (CU1) y se importa (CU2).
-- Las columnas titulo, descripcion y estado coinciden 1 a 1 con el CSV.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tareas (
  id          INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  titulo      VARCHAR(120) NOT NULL
              CHECK (length(trim(titulo)) >= 1),
  descripcion TEXT         NOT NULL DEFAULT '',
  estado      VARCHAR(20)  NOT NULL DEFAULT 'pendiente'
              CHECK (estado IN ('pendiente', 'en_progreso', 'completada')),
  creado_en   TIMESTAMPTZ  NOT NULL DEFAULT now()
);
