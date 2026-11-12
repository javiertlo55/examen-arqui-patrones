<?php
// Pruebas del CU2 — Adapter. No usan base de datos.
// Uso: php adapter/AdapterTest.php

require_once __DIR__ . '/../proxy/TareaInterface.php'; // Estados válidos (TareaInterface::ESTADOS)
require_once __DIR__ . '/FuenteTareasInterface.php';
require_once __DIR__ . '/LectorCSV.php';
require_once __DIR__ . '/AdapterTareasCSV.php';

function afirmarIgual(mixed $esperado, mixed $real, string $mensaje): void
{
    if ($esperado !== $real) {
        throw new Exception($mensaje . ' (esperado: ' . var_export($esperado, true) . ', obtenido: ' . var_export($real, true) . ')');
    }
}

// Atajo: crea el Adapter sobre el LectorCSV (adaptee) con el contenido dado.
function adaptar(string $csv): AdapterTareasCSV
{
    return new AdapterTareasCSV(new LectorCSV(), $csv);
}

$pruebas = [
    'LectorCSV entrega registros de texto (no sabe de tareas)' => function () {
        $csv = "\xEF\xBB\xBFtitulo,descripcion,estado\r\nA,desc A,pendiente\r\n\r\nB,,completada\r\n";

        $registros = (new LectorCSV())->leerRegistros($csv, true);

        afirmarIgual([['A', 'desc A', 'pendiente'], ['B', '', 'completada']], $registros, 'Registros mal separados');
    },

    'AdapterTareasCSV cumple FuenteTareasInterface' => function () {
        afirmarIgual(true, adaptar('') instanceof FuenteTareasInterface, 'Debió implementar el Target');
    },

    'AdapterTareasCSV traduce registros a tareas' => function () {
        $tareas = adaptar("titulo,descripcion,estado\nEstudiar Proxy,Repasar diagrama,en_progreso\n")->obtenerTareas();

        afirmarIgual(
            [['titulo' => 'Estudiar Proxy', 'descripcion' => 'Repasar diagrama', 'estado' => 'en_progreso']],
            $tareas,
            'Traducción incorrecta'
        );
    },

    'AdapterTareasCSV aplica valores por defecto y recorta espacios' => function () {
        $tareas = adaptar("titulo,descripcion,estado\n  Sin estado  , , \nSolo titulo\n")->obtenerTareas();

        afirmarIgual(
            [
                ['titulo' => 'Sin estado', 'descripcion' => '', 'estado' => 'pendiente'],
                ['titulo' => 'Solo titulo', 'descripcion' => '', 'estado' => 'pendiente'],
            ],
            $tareas,
            'Valores por defecto mal aplicados'
        );
    },

    'AdapterTareasCSV omite registros inválidos e informa el motivo' => function () {
        $adapter = adaptar("titulo,descripcion,estado\nValida,,pendiente\n,sin titulo,pendiente\nMala,,terminada\n");

        afirmarIgual(1, count($adapter->obtenerTareas()), 'Solo 1 fila era válida');
        afirmarIgual(
            ['fila 3: falta el título', "fila 4: estado 'terminada' no permitido"],
            $adapter->obtenerErrores(),
            'Errores mal informados'
        );
    },

    'el CSV de ejemplo da 3 tareas y 1 fila omitida' => function () {
        $adapter = adaptar(file_get_contents(__DIR__ . '/tareas-ejemplo.csv'));

        afirmarIgual(3, count($adapter->obtenerTareas()), 'Debió leer 3 tareas válidas');
        afirmarIgual(['fila 4: falta el título'], $adapter->obtenerErrores(), 'Debió omitir la fila 4');
    },
];

$fallidas = 0;
foreach ($pruebas as $nombre => $prueba) {
    try {
        $prueba();
        echo "  OK     Adapter: $nombre\n";
    } catch (Throwable $error) {
        $fallidas++;
        echo "  FALLA  Adapter: $nombre\n         {$error->getMessage()}\n";
    }
}
echo "\n" . (count($pruebas) - $fallidas) . ' de ' . count($pruebas) . " pruebas pasaron.\n";
exit($fallidas > 0 ? 1 : 0);
