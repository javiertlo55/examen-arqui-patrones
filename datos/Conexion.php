<?php
// Capa de datos — conexión a la base PostgreSQL.
// No es Singleton: cada llamada a conectar() abre un PDO nuevo; no hay getInstance() ni atributos estáticos.
// No subas este archivo con tu clave real a un repositorio público.

class Conexion
{
    private const HOST    = 'localhost';
    private const PUERTO  = 5433;      // Este PostgreSQL 18 escucha en 5433 (no en el 5432 por defecto).
    private const NOMBRE  = 'DBArqui';
    private const USUARIO = 'postgres';
    private const CLAVE   = 'tu_clave';    // Pon aquí la contraseña de tu PostgreSQL.
    private const SSLMODE = 'prefer';  // Usa SSL si el servidor lo ofrece; si no, conecta sin SSL.

    public function conectar(): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            self::HOST,
            self::PUERTO,
            self::NOMBRE,
            self::SSLMODE
        );

        return new PDO($dsn, self::USUARIO, self::CLAVE, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
