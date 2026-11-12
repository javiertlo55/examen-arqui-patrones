<?php
// Patrón Adapter — rol: Adapter
// Implementa FuenteTareasInterface usando LectorCSV: traduce registros de texto a tareas.

class AdapterTareasCSV implements FuenteTareasInterface
{
    private array $errores = [];

    public function __construct(
        private LectorCSV $adaptee,
        private string $contenido
    ) {}

    public function obtenerTareas(): array
    {
        $this->errores = [];
        $tareas = [];

        $registros = $this->adaptee->leerRegistros($this->contenido, true);
        foreach ($registros as $indice => $registro) {
            $numeroFila = $indice + 2; // +1 por el encabezado y +1 porque se cuenta desde 1.
            $tarea = $this->registroATarea($registro, $numeroFila);
            if ($tarea !== null) {
                $tareas[] = $tarea;
            }
        }
        return $tareas;
    }

    public function obtenerErrores(): array
    {
        return $this->errores;
    }

    // Registro posicional [titulo, descripcion, estado] → tarea. Devuelve null si es inválido.
    private function registroATarea(array $registro, int $numeroFila): ?array
    {
        $titulo = trim($registro[0] ?? '');
        $descripcion = trim($registro[1] ?? '');
        $estado = trim($registro[2] ?? '');

        if ($estado === '') {
            $estado = 'pendiente';
        }

        if ($titulo === '') {
            $this->errores[] = "fila $numeroFila: falta el título";
            return null;
        }
        if (!in_array($estado, TareaInterface::ESTADOS, true)) {
            $this->errores[] = "fila $numeroFila: estado '$estado' no permitido";
            return null;
        }

        return ['titulo' => $titulo, 'descripcion' => $descripcion, 'estado' => $estado];
    }
}
