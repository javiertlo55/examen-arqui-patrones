-- =====================================================================
-- Datos de prueba. Ejecutar DESPUÉS de esquema.sql.
-- Uso: ejecutar en el editor SQL de tu PostgreSQL (o: psql -f seed.sql)
-- Contraseñas (solo para la demo):
--   admin   / admin123    -> rol admin   (puede dar de baja socios)
--   usuario / usuario123  -> rol usuario (recepcionista: recibe 403 al dar de baja)
-- Los hashes son bcrypt (costo 10) con prefijo $2y$, el que usa PHP: password_verify() los valida.
-- =====================================================================

INSERT INTO usuarios (nombre, password_hash, rol) VALUES
  ('admin',   '$2y$10$9GiuNNN317BkXfsZHLen0.61N9PaVQm5ggI77INvea1zjAr/0n6TW', 'admin'),
  ('usuario', '$2y$10$ID2ny1DYRj8AAc6PN41kKuJQqwOC04oHYXcEsLTKq6VM8s1j9OfoS', 'usuario')
ON CONFLICT DO NOTHING;

-- Solo inserta socios si la tabla está vacía (evita duplicados al re-ejecutar).
INSERT INTO socios (nombre, telefono, plan)
SELECT v.nombre, v.telefono, v.plan
FROM (VALUES
  ('María Fernández', '76543210', 'anual'),
  ('Jorge Quispe',    '78901234', 'mensual'),
  ('Sofía Mamani',    '',         'trimestral')
) AS v(nombre, telefono, plan)
WHERE NOT EXISTS (SELECT 1 FROM socios);
