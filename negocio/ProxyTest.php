<?php
// Pruebas del CU1 — Proxy. No usan base de datos: Socio se reemplaza por un espía.
// Uso: php negocio/ProxyTest.php

require_once __DIR__ . '/ISocio.php';
require_once __DIR__ . '/ProxySocio.php';

// ISocio falsa: no usa base de datos y anota cada llamada.
function crearSocioEspia(): ISocio
{
    return new class implements ISocio {
        public array $registrados = [];
        public array $eliminadas = [];

        public function listarSocios(): array
        {
            return $this->registrados;
        }

        public function registrarSocio(string $nombre, string $telefono, string $plan): int
        {
            $id = count($this->registrados) + 1;
            $this->registrados[] = ['id' => $id, 'nombre' => $nombre, 'telefono' => $telefono, 'plan' => $plan];
            return $id;
        }

        public function eliminarSocio(int $id): bool
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
    'admin elimina y ProxySocio delega en Socio' => function () {
        $espia = crearSocioEspia();
        $proxy = new ProxySocio($espia, 'admin');

        afirmarIgual(true, $proxy->eliminarSocio(7), 'Debió devolver true');
        afirmarIgual([7], $espia->eliminadas, 'Socio debió recibir eliminarSocio(7)');
    },

    'usuario normal recibe error 403' => function () {
        $proxy = new ProxySocio(crearSocioEspia(), 'usuario');

        afirmarLanzaCodigo(403, fn () => $proxy->eliminarSocio(7));
    },

    'si se deniega, Socio NO es llamada' => function () {
        $espia = crearSocioEspia();
        $proxy = new ProxySocio($espia, 'usuario');

        try {
            $proxy->eliminarSocio(7);
        } catch (DomainException $error) {
            // Esperado.
        }

        afirmarIgual([], $espia->eliminadas, 'Socio no debió ser llamada');
    },

    'sin sesión (rol null) también se deniega' => function () {
        $espia = crearSocioEspia();
        $proxy = new ProxySocio($espia, null);

        afirmarLanzaCodigo(403, fn () => $proxy->eliminarSocio(7));
        afirmarIgual([], $espia->eliminadas, 'Socio no debió ser llamada');
    },

    'listar y registrar delegan igual para cualquier rol (transparencia)' => function () {
        $espia = crearSocioEspia();
        $proxy = new ProxySocio($espia, 'usuario');

        $id = $proxy->registrarSocio('Ana Pérez', '70012345', 'mensual');

        afirmarIgual(1, $id, 'registrarSocio() debió delegar y devolver el id');
        afirmarIgual(1, count($proxy->listarSocios()), 'listarSocios() debió devolver lo de Socio');
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
