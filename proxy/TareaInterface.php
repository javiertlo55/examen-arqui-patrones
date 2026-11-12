<?php
// Patrón Proxy — rol: Sujeto
// Interfaz común de TareaReal y ProxyTarea. index.php solo conoce esta interfaz.

interface TareaInterface
{
    public const ESTADOS = ['pendiente', 'en_progreso', 'completada'];

    /** @return array[] Cada tarea: ['id' => int, 'titulo' => string, 'descripcion' => string, 'estado' => string] */
    public function listarTareas(): array;

    /** @return int Id de la tarea creada */
    public function crearTarea(string $titulo, string $descripcion, string $estado): int;

    /** Operación protegida: solo administradores. */
    public function eliminarTarea(int $id): bool;
}
