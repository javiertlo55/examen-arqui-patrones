<?php
// Capa de negocio
// Patrón Proxy — rol: SujetoReal
// Aplica las reglas del socio (nombre obligatorio, plan válido, debe existir) y delega el SQL en DSocio.
// No sabe nada de roles ni permisos: eso lo controla ProxySocio.

class Socio implements ISocio
{
    public function __construct(private DSocio $datos) {}

    public function listarSocios(): array
    {
        return $this->datos->listar();
    }

    public function registrarSocio(string $nombre, string $telefono, string $plan): int
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > self::LARGO_MAXIMO_NOMBRE || !in_array($plan, self::PLANES, true)) {
            throw new DomainException('El nombre es obligatorio (máx. 100 caracteres) y el plan debe ser válido', 400);
        }
        return $this->datos->registrar($nombre, trim($telefono), $plan);
    }

    public function eliminarSocio(int $id): bool
    {
        if (!$this->datos->eliminar($id)) {
            throw new DomainException("No existe el socio con id $id", 404);
        }
        return true;
    }
}
