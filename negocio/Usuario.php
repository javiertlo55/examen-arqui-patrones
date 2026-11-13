<?php
// Capa de negocio (no es parte de ningún patrón)
// Regla de login: el usuario existe y la contraseña coincide con su hash. El SQL lo hace DUsuario.

class Usuario
{
    public function __construct(private DUsuario $datos) {}

    /** @return array|null ['nombre' => string, 'rol' => string] si las credenciales son correctas; si no, null */
    public function autenticar(string $nombre, string $clave): ?array
    {
        $usuario = $this->datos->buscarPorNombre($nombre);
        if ($usuario === null || !password_verify($clave, $usuario['password_hash'])) {
            return null;
        }
        return ['nombre' => $usuario['nombre'], 'rol' => $usuario['rol']];
    }
}
