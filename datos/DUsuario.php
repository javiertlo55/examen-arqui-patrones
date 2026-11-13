<?php
// Capa de datos — tabla usuarios (no es parte de ningún patrón).
// Solo consulta. Verificar la contraseña es regla de negocio (Usuario).

class DUsuario
{
    private PDO $conexion;

    public function __construct(Conexion $conexion)
    {
        $this->conexion = $conexion->conectar();
    }

    /** @return array|null ['nombre' => string, 'password_hash' => string, 'rol' => string] o null si no existe */
    public function buscarPorNombre(string $nombre): ?array
    {
        $consulta = $this->conexion->prepare(
            'SELECT nombre, password_hash, rol FROM usuarios WHERE lower(nombre) = lower(?)'
        );
        $consulta->execute([trim($nombre)]);
        $usuario = $consulta->fetch();
        return $usuario === false ? null : $usuario;
    }
}
