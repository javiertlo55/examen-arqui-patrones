<?php
// Patrón Adapter — rol: Target
// Lo que la aplicación nueva espera: tareas ya listas para guardar.

interface FuenteTareasInterface
{
    /** @return array[] Cada tarea: ['titulo' => string, 'descripcion' => string, 'estado' => string] */
    public function obtenerTareas(): array;

    /** @return string[] Motivos de los registros omitidos */
    public function obtenerErrores(): array;
}
