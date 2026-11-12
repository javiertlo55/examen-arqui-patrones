<?php
// Patrón Proxy — rol: Proxy (proxy de protección)
// Misma interfaz que TareaReal. Antes de delegar eliminarTarea(), verifica que el usuario sea admin.

class ProxyTarea implements TareaInterface
{
    public function __construct(
        private TareaInterface $tareaReal,
        private ?string $usuarioRol
    ) {}

    public function listarTareas(): array
    {
        return $this->tareaReal->listarTareas();
    }

    public function crearTarea(string $titulo, string $descripcion, string $estado): int
    {
        return $this->tareaReal->crearTarea($titulo, $descripcion, $estado);
    }

    public function eliminarTarea(int $id): bool
    {
        if ($this->usuarioRol !== 'admin') {
            throw new DomainException('Solo administradores pueden eliminar tareas', 403);
        }
        return $this->tareaReal->eliminarTarea($id);
    }
}
