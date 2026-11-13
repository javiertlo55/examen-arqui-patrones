# CLAUDE.md — Gimnasio: gestión de socios en 3 capas (Parcial de Patrones de Diseño)

## 1. Rol

Actúas como **arquitecto de software senior** que además escribe el código. Tu prioridad es que el proyecto sea **simple, correcto y fácil de explicar en un examen**. Cada decisión debe poder justificarse en una frase. Si dudas entre una solución elegante y una simple, elige la simple.

## 2. Objetivo

Sistema web en **3 capas** (presentación, negocio, datos) para gestionar los socios de un gimnasio, con base de datos real, que demuestra dos patrones GoF estructurales:

| # | Caso de uso | Patrón |
|---|-------------|--------|
| CU1 | Dar de baja a un socio (solo administradores) | **Proxy** de protección |
| CU2 | Importar socios desde un archivo CSV | **Adapter** |

### Reglas del curso (obligatorias)
- Solo patrones **GoF**. **Prohibidos: Singleton, Decorator y Facade.**
- 1 patrón por caso de uso. No agregues más patrones.

### Fuera del alcance
Registro de personal, editar socios, pagos, asistencias, vencimiento de membresías, búsqueda, notificaciones, APIs externas, frameworks, Composer, ORMs, Docker.

## 3. Problemática que resuelve cada patrón

> El Adaptee **no es antiguo** (a diferencia del ejemplo del banco, `LegacyMainframeDB`): es una clase existente cuya interfaz no coincide con la que espera el sistema. Lo que define al Adaptee es la **incompatibilidad de interfaz**.

### CU1 — Proxy de protección
Administrador y recepcionista (rol `usuario`) pueden listar y registrar socios. Dar de baja es destructivo e irreversible: solo el administrador. `Socio` (negocio) ya aplica las reglas del socio y delega el SQL en `DSocio`; no conviene mezclarle reglas de permisos: cada cambio de permisos tocaría una clase que ya funciona. Ocultar el botón en la vista no basta, porque cualquiera puede enviar la petición directamente.

**Solución:** `ProxySocio` implementa la misma `ISocio` que `Socio`. `PSocio` (presentación) solo conoce la interfaz. El proxy deja pasar `listarSocios()` y `registrarSocio()`, y en `eliminarSocio()` verifica que `usuarioRol` sea `admin` antes de delegar; si no, 403 y `Socio` nunca se ejecuta.

### CU2 — Adapter
El gimnasio quiere cargar los socios que tenía en una planilla de recepción exportada como CSV. `LectorCSV` (capa de datos) solo entrega **filas de texto por posición** (`string[][]`) con `leerRegistros(contenido, tieneEncabezado)`, p. ej. `["Ana Pérez", "70012345", "mensual"]`: sin nombres de campo, sin validar, sin valores por defecto. El negocio espera `IFuenteSocios::obtenerSocios()` → `['nombre', 'telefono', 'plan']` con nombre obligatorio (≤ 100) y plan permitido. Son **interfaces incompatibles**. No queremos que el negocio dependa del formato CSV ni modificar el lector.

**Solución:** `AdapterSociosCSV` implementa `IFuenteSocios` (Target) y usa `LectorCSV` (Adaptee), convirtiendo cada fila con `registroASocio()`: recorta espacios, plan por defecto `mensual`, omite (informando el motivo) las filas inválidas. Si mañana llega otro formato (JSON, Excel, torniquete), se crea otro adapter.

| Diagrama genérico | Este sistema |
|-------------------|--------------|
| `Client` | `PSocio` |
| `Target` / `Request()` | `IFuenteSocios` / `obtenerSocios()` |
| `Adapter` / `Request()` | `AdapterSociosCSV` / `obtenerSocios()` |
| `Adaptee` / `SpecificRequest()` | `LectorCSV` / `leerRegistros()` |
| asociación `adaptee` | atributo `$adaptee` de `AdapterSociosCSV` |
| nota `adaptee.SpecificRequest()` | `$this->adaptee->leerRegistros(...)` + `registroASocio()` |

## 4. Diseño

| Rol GoF | Proxy (CU1) | Adapter (CU2) |
|---------|-------------|---------------|
| Cliente | `PSocio` (presentación) | `PSocio` (presentación) |
| Sujeto / Target | `ISocio` (negocio) | `IFuenteSocios` (negocio) |
| SujetoReal / Adaptee | `Socio` (negocio) → `DSocio` (datos) | `LectorCSV` (datos) |
| Proxy / Adapter | `ProxySocio` (negocio) | `AdapterSociosCSV` (negocio) |

Convención del ejemplo de eventos (`PEvento` → `Evento` → `DEvento` → `Conexion`): **P**resentación verde, negocio azul, **D**atos amarillo. Clases que **no son patrones**: `Usuario` (negocio, `password_verify`), `DSocio`/`DUsuario` (SQL) y `Conexion`. Diagramas EA (`diagrama-ea.js`): General, CU1 Proxy y CU2 Adapter. Diagrama Mermaid en el README.

## 5. Stack

- **PHP 8.4** puro (`pdo_pgsql` y `mbstring` activos). Servidor: `php -S localhost:3000`.
- **PostgreSQL 18** local: base `DBArqui`, usuario `postgres`, puerto **5433**. Datos en `datos/Conexion.php`. Tablas `usuarios` y `socios` (la tabla `tareas` de la versión anterior queda sin uso).
- PDO con sentencias preparadas, `password_hash` / `password_verify`, `$_SESSION`, `$_FILES`.
- Pruebas: scripts propios sin PHPUnit, en `negocio/`.

## 6. Estructura (3 capas)

> **Regla: tres carpetas, una por capa, y fuera de ellas solo `index.php`** (debe estar en la raíz para `php -S`). Convención del ejemplo de eventos: `P*` presentación, negocio sin prefijo (`I*` interfaces), `D*` datos. Cada capa solo llama a la de abajo; la presentación nunca escribe SQL.

```
index.php                 Punto de entrada: carga las 3 capas y llama a (new PSocio())->procesar()
presentacion/             (verde)
  PSocio.php              procesar(), verPagina(), iniciarSesion(), cerrarSesion(), guardar(), eliminar(), importar()
  vista.php               plantilla HTML de verPagina() (usa $this->e())
negocio/                  (azul)
  ISocio.php              Sujeto + const PLANES, LARGO_MAXIMO_NOMBRE
  Socio.php               SujetoReal: valida y delega en DSocio (404 si no existe)
  ProxySocio.php          Proxy: verifica usuarioRol antes de eliminar
  IFuenteSocios.php       Target
  AdapterSociosCSV.php    Adapter: registros → socios
  Usuario.php             login: password_verify (no es patrón)
  ProxyTest.php           php negocio/ProxyTest.php
  AdapterTest.php         php negocio/AdapterTest.php
datos/                    (amarillo)
  Conexion.php            constantes + conectar(): PDO nuevo
  DSocio.php              SQL de socios
  DUsuario.php            SQL de usuarios
  LectorCSV.php           Adaptee: leerRegistros(contenido, tieneEncabezado) → string[][]
  socios-ejemplo.csv      3 filas válidas + 1 inválida
esquema.sql, seed.sql, diagrama-ea.js, DISENO-BD.md, README.md, CLAUDE.md
```

- Los socios viajan como arrays asociativos `['id', 'nombre', 'telefono', 'plan']`.
- `PSocio::crearSocio()` es el **único** lugar con `new Socio(...)`: `new ProxySocio(new Socio(new DSocio(new Conexion())), $rol)`.
- La capa de datos no depende del negocio: `DSocio` solo recibe y devuelve datos.
- El botón **Dar de baja solo lo ve el admin** (comodidad visual). La protección real está en `ProxySocio`.
- La columna `#` muestra la **posición** en la lista, no el id. Si al eliminar la tabla queda vacía, `DSocio` reinicia la secuencia.
- Errores: `DomainException` con código 400 (Socio, datos inválidos), 403 (Proxy) o 404 (Socio, no existe); CSV vacío o sin archivo → 400.

## 7. Reglas de código

1. Nombres de métodos y variables en español; un archivo por clase con el mismo nombre.
2. Cada archivo indica su capa (`// Capa de ...`) y, si es de un patrón, `// Patrón X — rol: Y`.
3. **Sin Singleton:** sin `getInstance()`, sin `static`/`global` para la conexión. `Conexion::conectar()` devuelve un PDO nuevo; `DSocio`/`DUsuario` reciben `Conexion` por constructor.
4. **Sin Decorator ni Facade.** El Proxy controla acceso; no agrega funcionalidad.
5. SQL siempre con `prepare` + `execute` y `?`, y solo en `datos/`. Contraseñas con `password_hash`. Salida HTML con `e()`.
6. `LectorCSV` no se modifica para adaptarlo y no conoce a los socios.
7. Sin dependencias externas, métodos mágicos ni `extract()`.

## 8. Estado (verificado el 2026-11-12)

- [x] 11 pruebas pasan (5 Proxy, 6 Adapter).
- [x] Contra `DBArqui` por HTTP: login incorrecto → 401; `usuario` no ve "Dar de baja" y al enviarlo a mano → 403; `usuario` importa el CSV → "Se importaron 3 socios, 1 fila omitida (fila 4: falta el nombre)"; importar sin archivo → 400; plan inválido → 400; `admin` da de baja → 302; id inexistente → 404.

## 9. Guion de la demo

**3 capas (≈1 min):** mostrar las carpetas `presentacion/`, `negocio/`, `datos/`. *"PSocio no conoce SQL; el negocio decide reglas y permisos; DSocio solo ejecuta SQL."*

**Proxy (≈2 min):** diagrama (`Socio` y `ProxySocio` implementan `ISocio`; `Socio` → `DSocio` → `Conexion`) → entrar como `usuario`: no ve "Dar de baja" → enviar la petición a mano → 403 y el socio sigue → entrar como `admin`, dar de baja → desaparece → abrir `negocio/ProxySocio.php` y mostrar el `if`.

```powershell
# Como "usuario" (recepcionista), intentar dar de baja a mano al socio con id real 2:
curl.exe -s -c sesion.txt -d "accion=login&nombre=usuario&clave=usuario123" http://localhost:3000/index.php
curl.exe -s -b sesion.txt -o NUL -w "Código HTTP: %{http_code}`n" -d "accion=eliminar&id=2" http://localhost:3000/index.php
Remove-Item sesion.txt
```

**Adapter (≈2 min):** diagrama (`PSocio` → `IFuenteSocios` ← `AdapterSociosCSV` → `LectorCSV`) → abrir `datos/socios-ejemplo.csv` → importarlo → "Se importaron 3 socios, 1 fila omitida" → abrir `negocio/AdapterSociosCSV.php` y mostrar `registroASocio()`.

**Preguntas probables**
- *¿Por qué Proxy y no Decorator?* El Proxy controla el acceso al objeto real; no le agrega funcionalidad. Además Decorator está prohibido.
- *¿Por qué P, N y D?* Igual que el ejemplo de eventos: `PSocio` muestra, `Socio`/`ProxySocio` deciden, `DSocio` ejecuta el SQL. Quién puede dar de baja es negocio; el `DELETE` es datos.
- *¿Por qué Adapter?* Dos interfaces incompatibles (`leerRegistros()` → texto posicional vs. `obtenerSocios()` → socios validados).
- *¿Dónde está la conexión?* `Conexion::conectar()` crea un PDO nuevo cada vez; `DSocio` recibe la `Conexion` por constructor. No es Singleton.

## 10. Comunicación con Javier

- Si algo es ambiguo, elige lo más simple y anótalo en el README.
- Respeta la estructura (3 carpetas por capa + `index.php`); no la cambies sin preguntar.
- Al terminar cada cambio, informa en 3-5 líneas qué se hizo y cómo probarlo.
