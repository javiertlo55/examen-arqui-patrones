<?php
// Capa de presentación
// Cliente de los dos patrones: solo conoce ISocio (Proxy) e IFuenteSocios (Adapter) de la capa de negocio.
// Atiende la petición, llama al negocio y muestra la página (plantilla vista.php).

class PSocio
{
    private ?string $mensaje = null;
    private ?string $error = null;
    private int $codigoHttp = 200;

    // Ejecuta la acción del formulario (campo oculto "accion") y luego muestra la página.
    public function procesar(): void
    {
        $accion = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['accion'] ?? '') : '';

        if ($accion === 'login') {
            $this->iniciarSesion($_POST);
        } elseif ($accion === 'salir') {
            $this->cerrarSesion();
        } elseif ($accion !== '' && !isset($_SESSION['usuario'])) {
            $this->redirigir(); // Sin sesión no se permite ninguna otra acción.
        } elseif ($accion === 'guardar') {
            $this->guardar($_POST);
        } elseif ($accion === 'eliminar') {
            $this->eliminar((int) ($_POST['id'] ?? 0));
        } elseif ($accion === 'importar') {
            $this->importar($_FILES['archivo'] ?? null);
        }

        $this->verPagina();
    }

    // Muestra el login (sin sesión) o la lista de socios (con sesión).
    public function verPagina(): void
    {
        $usuario = $_SESSION['usuario'] ?? null;
        $listaSocios = $usuario !== null ? $this->crearSocio()->listarSocios() : [];
        $mensaje = $this->mensaje;
        $error = $this->error;

        http_response_code($this->codigoHttp);
        require __DIR__ . '/vista.php';
    }

    public function iniciarSesion(array $request): void
    {
        $usuario = (new Usuario(new DUsuario(new Conexion())))
            ->autenticar($request['nombre'] ?? '', $request['clave'] ?? '');

        if ($usuario === null) {
            $this->error = 'Usuario o contraseña incorrectos';
            $this->codigoHttp = 401;
            return;
        }
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        $this->redirigir();
    }

    public function cerrarSesion(): void
    {
        $_SESSION = [];
        session_destroy();
        $this->redirigir();
    }

    public function guardar(array $request): void
    {
        try {
            $this->crearSocio()->registrarSocio(
                $request['nombre'] ?? '',
                $request['telefono'] ?? '',
                $request['plan'] ?? 'mensual'
            );
            $this->redirigir();
        } catch (DomainException $excepcion) {
            $this->mostrarError($excepcion); // 400 si Socio rechaza los datos
        }
    }

    // CU1 — PSocio solo conoce ISocio; no sabe que detrás hay un Proxy.
    public function eliminar(int $id): void
    {
        try {
            $this->crearSocio()->eliminarSocio($id);
            $this->redirigir();
        } catch (DomainException $excepcion) {
            $this->mostrarError($excepcion); // 403 si el Proxy lo deniega, 404 si el socio no existe
        }
    }

    // CU2 — Envuelve LectorCSV (adaptee) en el Adapter y registra cada socio por medio del Proxy.
    public function importar(?array $archivo): void
    {
        if ($archivo === null || $archivo['error'] !== UPLOAD_ERR_OK) {
            $this->error = 'Debes seleccionar un archivo CSV';
            $this->codigoHttp = 400;
            return;
        }
        $contenido = file_get_contents($archivo['tmp_name']);
        if (trim($contenido) === '') {
            $this->error = 'El archivo CSV está vacío';
            $this->codigoHttp = 400;
            return;
        }

        $fuente = $this->crearFuenteSocios($contenido);
        $nuevos = $fuente->obtenerSocios();
        $errores = $fuente->obtenerErrores();

        if (count($nuevos) === 0 && count($errores) === 0) {
            $this->error = 'El archivo CSV no tiene filas de socios';
            $this->codigoHttp = 400;
            return;
        }
        $socios = $this->crearSocio();
        foreach ($nuevos as $socio) {
            $socios->registrarSocio($socio['nombre'], $socio['telefono'], $socio['plan']);
        }

        // Ej.: "Se importaron 3 socios, 1 fila omitida (fila 4: falta el nombre)."
        $this->mensaje = 'Se importaron ' . count($nuevos) . ' socios';
        if (count($errores) > 0) {
            $filas = count($errores) === 1 ? 'fila omitida' : 'filas omitidas';
            $this->mensaje .= ', ' . count($errores) . " $filas (" . implode('; ', $errores) . ')';
        }
        $this->mensaje .= '.';
    }

    // Único lugar con "new Socio": el resto del código solo ve un ISocio (el Proxy).
    private function crearSocio(): ISocio
    {
        $rol = $_SESSION['usuario']['rol'] ?? null;
        return new ProxySocio(new Socio(new DSocio(new Conexion())), $rol);
    }

    // Único lugar que sabe que la fuente es un CSV.
    private function crearFuenteSocios(string $contenido): IFuenteSocios
    {
        return new AdapterSociosCSV(new LectorCSV(), $contenido);
    }

    private function mostrarError(DomainException $excepcion): void
    {
        $this->error = $excepcion->getMessage();
        $this->codigoHttp = $excepcion->getCode();
    }

    private function redirigir(): never
    {
        header('Location: index.php');
        exit;
    }

    // Escapa texto antes de mostrarlo en HTML (se usa en vista.php como $this->e()).
    private function e(mixed $texto): string
    {
        return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
    }
}
