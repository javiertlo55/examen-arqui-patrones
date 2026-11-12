-- =====================================================================
-- Datos de prueba. Ejecutar DESPUÉS de esquema.sql.
-- Uso: ejecutar en el editor SQL de tu PostgreSQL (o: psql -f seed.sql)
-- Contraseñas (solo para la demo):
--   admin   / admin123    -> rol admin   (puede eliminar)
--   usuario / usuario123  -> rol usuario (recibe 403 al eliminar)
-- Los hashes son bcrypt (costo 10) con prefijo $2y$, el que usa PHP: password_verify() los valida.
-- =====================================================================

INSERT INTO usuarios (nombre, password_hash, rol) VALUES
  ('admin',   '$2y$10$9GiuNNN317BkXfsZHLen0.61N9PaVQm5ggI77INvea1zjAr/0n6TW', 'admin'),
  ('usuario', '$2y$10$ID2ny1DYRj8AAc6PN41kKuJQqwOC04oHYXcEsLTKq6VM8s1j9OfoS', 'usuario')
ON CONFLICT DO NOTHING;

-- Solo inserta tareas si la tabla está vacía (evita duplicados al re-ejecutar).
INSERT INTO tareas (titulo, descripcion, estado)
SELECT v.titulo, v.descripcion, v.estado
FROM (VALUES
  ('Leer el enunciado del parcial', 'Identificar los casos de uso', 'completada'),
  ('Diseñar diagramas UML',         'Proxy y Adapter en Mermaid',  'en_progreso'),
  ('Preparar la demo',              '',                            'pendiente')
) AS v(titulo, descripcion, estado)
WHERE NOT EXISTS (SELECT 1 FROM tareas);
