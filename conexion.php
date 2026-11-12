<?php
// Conexión a la base PostgreSQL.
// No subas este archivo con tu clave real a un repositorio público.

const BD_HOST    = 'localhost';
const BD_PUERTO  = 5433;      // Este PostgreSQL 18 escucha en 5433 (no en el 5432 por defecto).
const BD_NOMBRE  = 'DBArqui';
const BD_USUARIO = 'postgres';
const BD_CLAVE   = 'tu_clave';  // Pon aquí la contraseña de tu PostgreSQL.
const BD_SSLMODE = 'prefer';  // Usa SSL si el servidor lo ofrece; si no, conecta sin SSL.

// Abre una conexión NUEVA cada vez que se llama.
// No guarda la conexión en ningún lado (no es Singleton): quien la crea la pasa por constructor.
function crearConexion(): PDO
{
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        BD_HOST,
        BD_PUERTO,
        BD_NOMBRE,
        BD_SSLMODE
    );

    return new PDO($dsn, BD_USUARIO, BD_CLAVE, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
