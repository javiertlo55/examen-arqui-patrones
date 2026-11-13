<?php
// Punto de entrada (php -S localhost:3000): carga las 3 capas y entrega la petición a la presentación.

session_start();

// Capa de datos
require_once __DIR__ . '/datos/Conexion.php';
require_once __DIR__ . '/datos/DSocio.php';
require_once __DIR__ . '/datos/DUsuario.php';
require_once __DIR__ . '/datos/LectorCSV.php';
// Capa de negocio
require_once __DIR__ . '/negocio/ISocio.php';            // CU1 — Proxy
require_once __DIR__ . '/negocio/Socio.php';
require_once __DIR__ . '/negocio/ProxySocio.php';
require_once __DIR__ . '/negocio/IFuenteSocios.php';     // CU2 — Adapter
require_once __DIR__ . '/negocio/AdapterSociosCSV.php';
require_once __DIR__ . '/negocio/Usuario.php';
// Capa de presentación
require_once __DIR__ . '/presentacion/PSocio.php';

(new PSocio())->procesar();
