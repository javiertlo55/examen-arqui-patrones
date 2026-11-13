-- =====================================================================
-- Sistema web de gestión de socios de un gimnasio — Esquema (PostgreSQL 14+)
-- Diseño: 2 tablas independientes, mínimas y con integridad en la BD.
-- Idempotente: se puede ejecutar varias veces sin error.
-- Uso: ejecutar en el editor SQL de tu PostgreSQL (o: psql -f esquema.sql)
-- =====================================================================

-- ---------------------------------------------------------------------
-- usuarios: personal del gimnasio que inicia sesión y su rol.
--   admin   -> puede dar de baja socios
--   usuario -> recepcionista: lista, registra e importa socios
-- El rol lo consulta el Proxy (negocio/ProxySocio.php, CU1) antes de eliminar.
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
-- socios: lo que se lista, se registra, se da de baja (CU1) y se importa (CU2).
-- Las columnas nombre, telefono y plan coinciden 1 a 1 con el CSV.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS socios (
  id         INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  nombre     VARCHAR(100) NOT NULL
             CHECK (length(trim(nombre)) >= 1),
  telefono   VARCHAR(30)  NOT NULL DEFAULT '',
  plan       VARCHAR(20)  NOT NULL DEFAULT 'mensual'
             CHECK (plan IN ('mensual', 'trimestral', 'anual')),
  creado_en  TIMESTAMPTZ  NOT NULL DEFAULT now()
);
