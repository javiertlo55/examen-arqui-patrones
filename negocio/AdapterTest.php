<?php
// Pruebas del CU2 — Adapter. No usan base de datos.
// Uso: php negocio/AdapterTest.php

require_once __DIR__ . '/ISocio.php'; // Planes válidos (ISocio::PLANES)
require_once __DIR__ . '/IFuenteSocios.php';
require_once __DIR__ . '/../datos/LectorCSV.php';
require_once __DIR__ . '/AdapterSociosCSV.php';

function afirmarIgual(mixed $esperado, mixed $real, string $mensaje): void
{
    if ($esperado !== $real) {
        throw new Exception($mensaje . ' (esperado: ' . var_export($esperado, true) . ', obtenido: ' . var_export($real, true) . ')');
    }
}

// Atajo: crea el Adapter sobre el LectorCSV (adaptee) con el contenido dado.
function adaptar(string $csv): AdapterSociosCSV
{
    return new AdapterSociosCSV(new LectorCSV(), $csv);
}

$pruebas = [
    'LectorCSV entrega registros de texto (no sabe de socios)' => function () {
        $csv = "\xEF\xBB\xBFnombre,telefono,plan\r\nAna,700,mensual\r\n\r\nLuis,,anual\r\n";

        $registros = (new LectorCSV())->leerRegistros($csv, true);

        afirmarIgual([['Ana', '700', 'mensual'], ['Luis', '', 'anual']], $registros, 'Registros mal separados');
    },

    'AdapterSociosCSV cumple IFuenteSocios' => function () {
        afirmarIgual(true, adaptar('') instanceof IFuenteSocios, 'Debió implementar el Target');
    },

    'AdapterSociosCSV traduce registros a socios' => function () {
        $socios = adaptar("nombre,telefono,plan\nAna Pérez,70012345,trimestral\n")->obtenerSocios();

        afirmarIgual(
            [['nombre' => 'Ana Pérez', 'telefono' => '70012345', 'plan' => 'trimestral']],
            $socios,
            'Traducción incorrecta'
        );
    },

    'AdapterSociosCSV aplica valores por defecto y recorta espacios' => function () {
        $socios = adaptar("nombre,telefono,plan\n  Sin plan  , , \nSolo nombre\nMayusculas,,ANUAL\n")->obtenerSocios();

        afirmarIgual(
            [
                ['nombre' => 'Sin plan', 'telefono' => '', 'plan' => 'mensual'],
                ['nombre' => 'Solo nombre', 'telefono' => '', 'plan' => 'mensual'],
                ['nombre' => 'Mayusculas', 'telefono' => '', 'plan' => 'anual'],
            ],
            $socios,
            'Valores por defecto mal aplicados'
        );
    },

    'AdapterSociosCSV omite registros inválidos e informa el motivo' => function () {
        $nombreLargo = str_repeat('a', 101);
        $adapter = adaptar("nombre,telefono,plan\nValido,,mensual\n,700,mensual\nMalo,,semanal\n$nombreLargo,,anual\n");

        afirmarIgual(1, count($adapter->obtenerSocios()), 'Solo 1 fila era válida');
        afirmarIgual(
            ['fila 3: falta el nombre', "fila 4: plan 'semanal' no permitido", 'fila 5: el nombre supera 100 caracteres'],
            $adapter->obtenerErrores(),
            'Errores mal informados'
        );
    },

    'el CSV de ejemplo da 3 socios y 1 fila omitida' => function () {
        $adapter = adaptar(file_get_contents(__DIR__ . '/../datos/socios-ejemplo.csv'));

        afirmarIgual(3, count($adapter->obtenerSocios()), 'Debió leer 3 socios válidos');
        afirmarIgual(['fila 4: falta el nombre'], $adapter->obtenerErrores(), 'Debió omitir la fila 4');
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
