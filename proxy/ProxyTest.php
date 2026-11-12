<?php
// Pruebas del CU1 — Proxy. No usan base de datos: TareaReal se reemplaza por un espía.
// Uso: php proxy/ProxyTest.php

require_once __DIR__ . '/TareaInterface.php';
require_once __DIR__ . '/ProxyTarea.php';

// TareaInterface falsa: no usa base de datos y anota cada llamada.
function crearTareaEspia(): TareaInterface
{
    return new class implements TareaInterface {
        public array $creadas = [];
        public array $eliminadas = [];

        public function listarTareas(): array
        {
            return $this->creadas;
        }

        public function crearTarea(string $titulo, string $descripcion, string $estado): int
        {
            $id = count($this->creadas) + 1;
            $this->creadas[] = ['id' => $id, 'titulo' => $titulo, 'descripcion' => $descripcion, 'estado' => $estado];
            return $id;
        }

        public function eliminarTarea(int $id): bool
        {
            $this->eliminadas[] = $id;
            return true;
        }
    };
}

function afirmarIgual(mixed $esperado, mixed $real, string $mensaje): void
{
    if ($esperado !== $real) {
        throw new Exception($mensaje . ' (esperado: ' . var_export($esperado, true) . ', obtenido: ' . var_export($real, true) . ')');
    }
}

// Ejecuta la acción y comprueba que lance DomainException con ese código.
function afirmarLanzaCodigo(int $codigo, callable $accion): void
{
    try {
        $accion();
    } catch (DomainException $error) {
        afirmarIgual($codigo, $error->getCode(), 'Código de error incorrecto');
        return;
    }
    throw new Exception("Debió lanzar un error $codigo");
}

$pruebas = [
    'admin elimina y ProxyTarea delega en TareaReal' => function () {
        $espia = crearTareaEspia();
        $proxy = new ProxyTarea($espia, 'admin');

        afirmarIgual(true, $proxy->eliminarTarea(7), 'Debió devolver true');
        afirmarIgual([7], $espia->eliminadas, 'TareaReal debió recibir eliminarTarea(7)');
    },

    'usuario normal recibe error 403' => function () {
        $proxy = new ProxyTarea(crearTareaEspia(), 'usuario');

        afirmarLanzaCodigo(403, fn () => $proxy->eliminarTarea(7));
    },

    'si se deniega, TareaReal NO es llamada' => function () {
        $espia = crearTareaEspia();
        $proxy = new ProxyTarea($espia, 'usuario');

        try {
            $proxy->eliminarTarea(7);
        } catch (DomainException $error) {
            // Esperado.
        }

        afirmarIgual([], $espia->eliminadas, 'TareaReal no debió ser llamada');
    },

    'sin sesión (rol null) también se deniega' => function () {
        $espia = crearTareaEspia();
        $proxy = new ProxyTarea($espia, null);

        afirmarLanzaCodigo(403, fn () => $proxy->eliminarTarea(7));
        afirmarIgual([], $espia->eliminadas, 'TareaReal no debió ser llamada');
    },

    'listar y crear delegan igual para cualquier rol (transparencia)' => function () {
        $espia = crearTareaEspia();
        $proxy = new ProxyTarea($espia, 'usuario');

        $id = $proxy->crearTarea('Probar proxy', '', 'pendiente');

        afirmarIgual(1, $id, 'crearTarea() debió delegar y devolver el id');
        afirmarIgual(1, count($proxy->listarTareas()), 'listarTareas() debió devolver lo de TareaReal');
    },
];

$fallidas = 0;
foreach ($pruebas as $nombre => $prueba) {
    try {
        $prueba();
        echo "  OK     Proxy: $nombre\n";
    } catch (Throwable $error) {
        $fallidas++;
        echo "  FALLA  Proxy: $nombre\n         {$error->getMessage()}\n";
    }
}
echo "\n" . (count($pruebas) - $fallidas) . ' de ' . count($pruebas) . " pruebas pasaron.\n";
exit($fallidas > 0 ? 1 : 0);
