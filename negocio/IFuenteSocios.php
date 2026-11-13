<?php
// Capa de negocio
// Patrón Adapter — rol: Target
// Lo que el sistema del gimnasio espera: socios ya validados, listos para registrar.

interface IFuenteSocios
{
    /** @return array[] Cada socio: ['nombre' => string, 'telefono' => string, 'plan' => string] */
    public function obtenerSocios(): array;

    /** @return string[] Motivos de los registros omitidos */
    public function obtenerErrores(): array;
}
