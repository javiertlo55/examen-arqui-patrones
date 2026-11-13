<?php
// Capa de presentación — plantilla HTML de PSocio::verPagina().
// Usa las variables $usuario, $listaSocios, $mensaje y $error, y $this->e() para escapar.
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gimnasio — Socios</title>
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
        <h1>Gimnasio</h1>

        <?php if ($error !== null): ?>
            <p class="error"><?= $this->e($error) ?></p>
        <?php endif; ?>

        <form method="post" action="index.php">
            <input type="hidden" name="accion" value="login">
            <label>Usuario <input name="nombre" required autofocus></label>
            <label>Contraseña <input name="clave" type="password" required></label>
            <button type="submit">Entrar</button>
        </form>

        <p class="ayuda">Usuarios de prueba: <b>admin</b> / admin123 · <b>usuario</b> / usuario123 (recepcionista)</p>
    </section>
</main>

<?php else: ?>
<!-- ======================= Vista: socios ======================= -->
<main>
    <header>
        <h1>Socios del gimnasio</h1>
        <form method="post" action="index.php">
            <input type="hidden" name="accion" value="salir">
            <?= $this->e($usuario['nombre']) ?> <span class="rol"><?= $this->e($usuario['rol']) ?></span>
            <button class="secundario" type="submit">Cerrar sesión</button>
        </form>
    </header>

    <?php if ($mensaje !== null): ?>
        <p class="mensaje"><?= $this->e($mensaje) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
        <p class="error"><?= $this->e($error) ?></p>
    <?php endif; ?>

    <section>
        <table>
            <thead><tr><th>#</th><th>Nombre</th><th>Teléfono</th><th>Plan</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($listaSocios as $posicion => $socio): ?>
                <tr>
                    <!-- Número consecutivo en pantalla; el id real va oculto en el formulario de baja. -->
                    <td><?= $this->e($posicion + 1) ?></td>
                    <td><?= $this->e($socio['nombre']) ?></td>
                    <td><?= $this->e($socio['telefono']) ?></td>
                    <td><?= $this->e($socio['plan']) ?></td>
                    <td>
                        <!-- Solo el admin ve el botón (comodidad visual). La seguridad real está en ProxySocio:
                             aunque alguien envíe la petición a mano, el Proxy la rechaza con 403. -->
                        <?php if ($usuario['rol'] === 'admin'): ?>
                            <form method="post" action="index.php">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= $this->e($socio['id']) ?>">
                                <button class="peligro" type="submit">Dar de baja</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($listaSocios) === 0): ?>
                <tr><td colspan="5">No hay socios registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Registrar socio</h2>
        <form class="fila" method="post" action="index.php">
            <input type="hidden" name="accion" value="guardar">
            <input name="nombre" placeholder="Nombre completo" maxlength="100" required>
            <input name="telefono" placeholder="Teléfono">
            <select name="plan">
                <?php foreach (ISocio::PLANES as $plan): ?>
                    <option value="<?= $this->e($plan) ?>"><?= $this->e($plan) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Registrar</button>
        </form>
    </section>

    <section>
        <h2>Importar socios desde CSV</h2>
        <p>Columnas: <code>nombre,telefono,plan</code> (la primera fila es el encabezado).</p>
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
