<?php
// Capa de negocio
// Patrón Proxy — rol: Proxy (proxy de protección)
// Misma interfaz que Socio. Antes de delegar eliminarSocio(), verifica que el usuario sea admin.

class ProxySocio implements ISocio
{
    public function __construct(
        private ISocio $socioReal,
        private ?string $usuarioRol
    ) {}

    public function listarSocios(): array
    {
        return $this->socioReal->listarSocios();
    }

    public function registrarSocio(string $nombre, string $telefono, string $plan): int
    {
        return $this->socioReal->registrarSocio($nombre, $telefono, $plan);
    }

    public function eliminarSocio(int $id): bool
    {
        if ($this->usuarioRol !== 'admin') {
            throw new DomainException('Solo administradores pueden dar de baja a un socio', 403);
        }
        return $this->socioReal->eliminarSocio($id);
    }
}
