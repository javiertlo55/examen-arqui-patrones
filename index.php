<?php
// Único punto de entrada y cliente de los dos patrones.
// main() procesa la acción (POST con campo oculto "accion") y muestra la vista: login o tareas.
// Uso: php -S localhost:3000

session_start();

require_once __DIR__ . '/conexion.php';
// CU1 — Patrón Proxy
require_once __DIR__ . '/proxy/TareaInterface.php';
require_once __DIR__ . '/proxy/TareaReal.php';
require_once __DIR__ . '/proxy/ProxyTarea.php';
// CU2 — Patrón Adapter
require_once __DIR__ . '/adapter/FuenteTareasInterface.php';
require_once __DIR__ . '/adapter/LectorCSV.php';
require_once __DIR__ . '/adapter/AdapterTareasCSV.php';

// =====================================================================
// Funciones de apoyo
// =====================================================================

// Único lugar donde se construye TareaReal. El resto del código solo ve un TareaInterface (el Proxy).
function crearProxyTarea(): TareaInterface
{
    $rol = $_SESSION['usuario']['rol'] ?? null;
    return new ProxyTarea(new TareaReal(crearConexion()), $rol);
}

function redirigir(): never
{
    header('Location: index.php');
    exit;
}

// Escapa texto antes de mostrarlo en HTML.
function e(mixed $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Devuelve true si el usuario y la clave son correctos (y guarda la sesión).
function iniciarSesion(string $nombre, string $clave): bool
{
    $consulta = crearConexion()->prepare(
        'SELECT nombre, password_hash, rol FROM usuarios WHERE lower(nombre) = lower(?)'
    );
    $consulta->execute([trim($nombre)]);
    $usuario = $consulta->fetch();

    if ($usuario === false || !password_verify($clave, $usuario['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['usuario'] = ['nombre' => $usuario['nombre'], 'rol' => $usuario['rol']];
    return true;
}

// CU2 — Envuelve el LectorCSV (adaptee) en el Adapter y guarda cada tarea por medio del Proxy.
// Devuelve [mensaje, error, códigoHttp].
function importarCsv(TareaInterface $tareas, ?array $archivo): array
{
    if ($archivo === null || $archivo['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Debes seleccionar un archivo CSV', 400];
    }
    $contenido = file_get_contents($archivo['tmp_name']);
    if (trim($contenido) === '') {
        return [null, 'El archivo CSV está vacío', 400];
    }

    $fuente = new AdapterTareasCSV(new LectorCSV(), $contenido);
    $nuevas = $fuente->obtenerTareas();
    $errores = $fuente->obtenerErrores();

    if (count($nuevas) === 0 && count($errores) === 0) {
        return [null, 'El archivo CSV no tiene filas de tareas', 400];
    }
    foreach ($nuevas as $tarea) {
        $tareas->crearTarea($tarea['titulo'], $tarea['descripcion'], $tarea['estado']);
    }

    // Ej.: "Se importaron 3 tareas, 1 fila omitida (fila 4: falta el título)."
    $mensaje = 'Se importaron ' . count($nuevas) . ' tareas';
    if (count($errores) > 0) {
        $filas = count($errores) === 1 ? 'fila omitida' : 'filas omitidas';
        $mensaje .= ', ' . count($errores) . " $filas (" . implode('; ', $errores) . ')';
    }
    return [$mensaje . '.', null, 200];
}

// =====================================================================
// main(): procesa la acción y muestra la vista
// =====================================================================

function main(): void
{
    $accion = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['accion'] ?? '') : '';
    $mensaje = null;
    $error = null;
    $codigoHttp = 200;

    if ($accion === 'login') {
        if (iniciarSesion($_POST['nombre'] ?? '', $_POST['clave'] ?? '')) {
            redirigir();
        }
        $error = 'Usuario o contraseña incorrectos';
        $codigoHttp = 401;
    } elseif ($accion === 'salir') {
        $_SESSION = [];
        session_destroy();
        redirigir();
    } elseif ($accion !== '' && !isset($_SESSION['usuario'])) {
        redirigir(); // Sin sesión no se permite ninguna otra acción.
    }

    $tareas = isset($_SESSION['usuario']) ? crearProxyTarea() : null;

    if ($accion === 'crear') {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $estado = $_POST['estado'] ?? 'pendiente';

        if ($titulo === '' || !in_array($estado, TareaInterface::ESTADOS, true)) {
            $error = 'El título es obligatorio y el estado debe ser válido';
            $codigoHttp = 400;
        } else {
            $tareas->crearTarea($titulo, $descripcion, $estado);
            redirigir();
        }
    } elseif ($accion === 'eliminar') {
        // CU1 — index.php solo conoce TareaInterface; no sabe que detrás hay un Proxy.
        try {
            $tareas->eliminarTarea((int) ($_POST['id'] ?? 0));
            redirigir();
        } catch (DomainException $excepcion) {
            // 403 si el Proxy lo deniega, 404 si TareaReal no encuentra la tarea.
            $error = $excepcion->getMessage();
            $codigoHttp = $excepcion->getCode();
        }
    } elseif ($accion === 'importar') {
        [$mensaje, $error, $codigoHttp] = importarCsv($tareas, $_FILES['archivo'] ?? null);
    }

    http_response_code($codigoHttp);
    mostrarVista(
        $_SESSION['usuario'] ?? null,
        $tareas !== null ? $tareas->listarTareas() : [],
        $mensaje,
        $error
    );
}

// =====================================================================
// Vistas: login (sin sesión) o tareas (con sesión)
// =====================================================================

function mostrarVista(?array $usuario, array $listaTareas, ?string $mensaje, ?string $error): void
{
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de tareas</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f5f7; margin: 0; }
        main { max-width: 860px; margin: 24px auto; padding: 0 16px; }
        main.login { max-width: 360px; margin-top: 80px; }
        header { display: flex; justify-content: space-between; align-items: center; }
        section { background: #fff; padding: 16px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; }
        label { display: block; margin-top: 12px; }
        input, select { padding: 6px; }
        .login input { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 6px 12px; border: 0; border-radius: 4px; cursor: pointer; background: #2563eb; color: #fff; }
        .login button { margin-top: 16px; width: 100%; padding: 10px; }
        button.peligro { background: #dc2626; }
        button.secundario { background: #6b7280; }
        .mensaje { background: #dcfce7; color: #166534; padding: 10px; border-radius: 4px; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 4px; }
        .rol { font-size: .8em; background: #e5e7eb; padding: 2px 6px; border-radius: 4px; }
        .ayuda { color: #6b7280; font-size: .85em; margin-top: 16px; }
        form.fila { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    </style>
</head>
<body>

<?php if ($usuario === null): ?>
<!-- ======================= Vista: login ======================= -->
<main class="login">
    <section>
        <h1>Gestión de tareas</h1>

        <?php if ($error !== null): ?>
            <p class="error"><?= e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="index.php">
            <input type="hidden" name="accion" value="login">
            <label>Usuario <input name="nombre" required autofocus></label>
            <label>Contraseña <input name="clave" type="password" required></label>
            <button type="submit">Entrar</button>
        </form>

        <p class="ayuda">Usuarios de prueba: <b>admin</b> / admin123 · <b>usuario</b> / usuario123</p>
    </section>
</main>

<?php else: ?>
<!-- ======================= Vista: tareas ======================= -->
<main>
    <header>
        <h1>Tareas</h1>
        <form method="post" action="index.php">
            <input type="hidden" name="accion" value="salir">
            <?= e($usuario['nombre']) ?> <span class="rol"><?= e($usuario['rol']) ?></span>
            <button class="secundario" type="submit">Cerrar sesión</button>
        </form>
    </header>

    <?php if ($mensaje !== null): ?>
        <p class="mensaje"><?= e($mensaje) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>

    <section>
        <table>
            <thead><tr><th>#</th><th>Título</th><th>Descripción</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($listaTareas as $posicion => $tarea): ?>
                <tr>
                    <!-- Número consecutivo en pantalla; el id real va oculto en el formulario de eliminar. -->
                    <td><?= e($posicion + 1) ?></td>
                    <td><?= e($tarea['titulo']) ?></td>
                    <td><?= e($tarea['descripcion']) ?></td>
                    <td><?= e($tarea['estado']) ?></td>
                    <td>
                        <!-- Solo el admin ve el botón (comodidad visual). La seguridad real está en ProxyTarea:
                             aunque alguien envíe la petición a mano, el Proxy la rechaza con 403. -->
                        <?php if ($usuario['rol'] === 'admin'): ?>
                            <form method="post" action="index.php">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= e($tarea['id']) ?>">
                                <button class="peligro" type="submit">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($listaTareas) === 0): ?>
                <tr><td colspan="5">No hay tareas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Nueva tarea</h2>
        <form class="fila" method="post" action="index.php">
            <input type="hidden" name="accion" value="crear">
            <input name="titulo" placeholder="Título" required>
            <input name="descripcion" placeholder="Descripción">
            <select name="estado">
                <?php foreach (TareaInterface::ESTADOS as $estado): ?>
                    <option value="<?= e($estado) ?>"><?= e($estado) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Crear</button>
        </form>
    </section>

    <section>
        <h2>Importar desde CSV</h2>
        <p>Columnas: <code>titulo,descripcion,estado</code> (la primera fila es el encabezado).</p>
        <form class="fila" method="post" action="index.php" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="importar">
            <input type="file" name="archivo" accept=".csv,text/csv">
            <button type="submit">Importar</button>
        </form>
    </section>
</main>
<?php endif; ?>

</body>
</html>
<?php
}

main();
