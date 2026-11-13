<?php
// Capa de negocio
// Patrón Proxy — rol: Sujeto
// Interfaz común de Socio y ProxySocio. PSocio (presentación) solo conoce esta interfaz.

interface ISocio
{
    public const PLANES = ['mensual', 'trimestral', 'anual'];
    public const LARGO_MAXIMO_NOMBRE = 100; // Igual que la columna socios.nombre.

    /** @return array[] Cada socio: ['id' => int, 'nombre' => string, 'telefono' => string, 'plan' => string] */
    public function listarSocios(): array;

    /** @return int Id del socio registrado */
    public function registrarSocio(string $nombre, string $telefono, string $plan): int;

    /** Operación protegida: solo administradores pueden dar de baja a un socio. */
    public function eliminarSocio(int $id): bool;
}
