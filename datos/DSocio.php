<?php
// Capa de datos — tabla socios.
// Solo ejecuta SQL. No sabe nada de roles, permisos ni validaciones (eso es la capa de negocio).

class DSocio
{
    private PDO $conexion;

    public function __construct(Conexion $conexion)
    {
        $this->conexion = $conexion->conectar();
    }

    public function listar(): array
    {
        return $this->conexion
            ->query('SELECT id, nombre, telefono, plan FROM socios ORDER BY creado_en, id')
            ->fetchAll();
    }

    public function registrar(string $nombre, string $telefono, string $plan): int
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO socios (nombre, telefono, plan) VALUES (?, ?, ?) RETURNING id'
        );
        $consulta->execute([$nombre, $telefono, $plan]);
        return (int) $consulta->fetchColumn();
    }

    // Devuelve false si no existía un socio con ese id.
    public function eliminar(int $id): bool
    {
        $consulta = $this->conexion->prepare('DELETE FROM socios WHERE id = ?');
        $consulta->execute([$id]);

        if ($consulta->rowCount() === 0) {
            return false;
        }
        $this->reiniciarNumeracionSiVacia();
        return true;
    }

    // Si ya no quedan socios, el siguiente que se registre vuelve a tener el id 1.
    private function reiniciarNumeracionSiVacia(): void
    {
        $total = (int) $this->conexion->query('SELECT count(*) FROM socios')->fetchColumn();
        if ($total === 0) {
            $this->conexion->exec('ALTER TABLE socios ALTER COLUMN id RESTART WITH 1');
        }
    }
}
