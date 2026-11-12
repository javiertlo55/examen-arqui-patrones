<?php
// Patrón Proxy — rol: SujetoReal
// Hace el trabajo real contra PostgreSQL. No sabe nada de roles ni permisos.

class TareaReal implements TareaInterface
{
    public function __construct(private PDO $db) {}

    public function listarTareas(): array
    {
        return $this->db
            ->query('SELECT id, titulo, descripcion, estado FROM tareas ORDER BY creado_en, id')
            ->fetchAll();
    }

    public function crearTarea(string $titulo, string $descripcion, string $estado): int
    {
        $consulta = $this->db->prepare(
            'INSERT INTO tareas (titulo, descripcion, estado) VALUES (?, ?, ?) RETURNING id'
        );
        $consulta->execute([$titulo, $descripcion, $estado]);
        return (int) $consulta->fetchColumn();
    }

    public function eliminarTarea(int $id): bool
    {
        $consulta = $this->db->prepare('DELETE FROM tareas WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            throw new DomainException("No existe la tarea con id $id", 404);
        }
        $this->reiniciarNumeracionSiVacia();
        return true;
    }

    // Si ya no quedan tareas, la siguiente que se cree vuelve a tener el id 1.
    private function reiniciarNumeracionSiVacia(): void
    {
        $total = (int) $this->db->query('SELECT count(*) FROM tareas')->fetchColumn();
        if ($total === 0) {
            $this->db->exec('ALTER TABLE tareas ALTER COLUMN id RESTART WITH 1');
        }
    }
}
