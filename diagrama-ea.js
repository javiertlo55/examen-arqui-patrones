// =====================================================================
// Script JScript para Enterprise Architect (EA 15 o superior)
// Crea las clases del gimnasio en 3 capas (PSocio -> negocio -> DSocio -> Conexion),
// con los patrones Proxy y Adapter, colores por capa y 3 diagramas: General, CU1 Proxy y CU2 Adapter.
//
// Uso: seleccionar un paquete en el Navegador de EA y ejecutar este script
// desde la ventana de Scripting (Specialize > Tools > Scripting).
// =====================================================================

var PUBLICO = "Public";
var PRIVADO = "Private";

// Colores por capa, como el ejemplo (EA usa formato BGR: 0xBBGGRR).
var VERDE    = { fondo: 0xD4E8D5, borde: 0x66B382 };   // Presentación
var AZUL     = { fondo: 0xFCE8DA, borde: 0xBF8E6C };   // Negocio
var AMARILLO = { fondo: 0xCCF2FF, borde: 0x56B6D6 };   // Datos

var SOCIO_PARAMS = [["nombre", "string"], ["telefono", "string"], ["plan", "string"]];

function nuevoPaquete(padre, nombre) {
    var paquete = padre.Packages.AddNew(nombre, "Package");
    paquete.Update();
    padre.Packages.Refresh();
    return paquete;
}

function nuevoElemento(paquete, nombre, tipo, nota) {
    var elemento = paquete.Elements.AddNew(nombre, tipo);
    elemento.Notes = nota;
    elemento.Update();
    paquete.Elements.Refresh();
    return elemento;
}

// Un tipo puede ser texto ("array") o un elemento de EA (para enlazar el clasificador).
function nombreTipo(tipo) {
    return (typeof tipo == "string") ? tipo : tipo.Name;
}

function atributo(elemento, nombre, tipo, visibilidad, esEstatico, esConstante) {
    var a = elemento.Attributes.AddNew(nombre, nombreTipo(tipo));
    a.Visibility = visibilidad;
    if (typeof tipo != "string") a.ClassifierID = tipo.ElementID;
    if (esEstatico) a.IsStatic = true;
    if (esConstante) a.IsConst = true;
    a.Update();
    elemento.Attributes.Refresh();
}

// parametros: [["nombre", tipo], ...]
function metodo(elemento, nombre, retorno, visibilidad, parametros, esAbstracto) {
    var m = elemento.Methods.AddNew(nombre, nombreTipo(retorno));
    m.Visibility = visibilidad;
    if (typeof retorno != "string") m.ClassifierID = "" + retorno.ElementID;
    if (esAbstracto) m.Abstract = true;
    m.Update();

    for (var i = 0; i < parametros.length; i++) {
        var p = m.Parameters.AddNew(parametros[i][0], nombreTipo(parametros[i][1]));
        p.Kind = "in";
        p.Position = i;
        if (typeof parametros[i][1] != "string") p.ClassifierID = "" + parametros[i][1].ElementID;
        p.Update();
    }
    m.Parameters.Refresh();
    elemento.Methods.Refresh();
}

// tipo: "Association", "Realisation" (implementa interfaz), "Dependency", "NoteLink".
function relacionar(origen, destino, tipo, nombre, estereotipo) {
    var conector = origen.Connectors.AddNew(nombre, tipo);
    conector.SupplierID = destino.ElementID;
    if (estereotipo) conector.Stereotype = estereotipo;
    if (tipo == "Association" || tipo == "Dependency") conector.Direction = "Source -> Destination";
    conector.Update();
    origen.Connectors.Refresh();
    return conector;
}

// Agregación con rombo en el origen y nombre de rol en el destino: origen o--> destino.
function agregar(origen, destino, rol) {
    var conector = relacionar(origen, destino, "Association", "", "");
    conector.ClientEnd.Aggregation = 1;            // 1 = agregación (rombo vacío)
    conector.ClientEnd.Update();
    conector.SupplierEnd.Role = rol;
    conector.SupplierEnd.Cardinality = "1";
    conector.SupplierEnd.Navigable = "Navigable";
    conector.SupplierEnd.Update();
    conector.Update();
}

// Pinta la caja con el color de su capa.
function pintar(elemento, color) {
    try {
        elemento.SetAppearance(1, 0, color.fondo);  // 1 = local, 0 = color de fondo
        elemento.SetAppearance(1, 2, color.borde);  // 2 = color de borde
        elemento.Update();
    } catch (e) {
        // Versiones de EA sin SetAppearance: la caja queda con el color por defecto.
    }
}

function colocar(diagrama, elemento, izquierda, arriba, derecha, abajo) {
    var posicion = "l=" + izquierda + ";r=" + derecha + ";t=" + arriba + ";b=" + abajo + ";";
    var objeto = diagrama.DiagramObjects.AddNew(posicion, "");
    objeto.ElementID = elemento.ElementID;
    objeto.Update();
}

function main() {
    var raiz = Repository.GetTreeSelectedPackage();
    if (raiz == null) {
        Session.Prompt("Primero selecciona un paquete en el Navegador.", 2);
        return;
    }

    // ---------------- Paquetes: uno por capa ----------------
    var modelo = nuevoPaquete(raiz, "Diseño Procedimental - Gimnasio 3 capas");
    var pkgP = nuevoPaquete(modelo, "presentacion");
    var pkgN = nuevoPaquete(modelo, "negocio");
    var pkgD = nuevoPaquete(modelo, "datos");

    // ================= CAPA DE DATOS (amarillo) =================
    var conexion = nuevoElemento(pkgD, "Conexion", "Class",
        "Capa de datos. Abre un PDO nuevo en cada conectar() (no es Singleton).");
    metodo(conexion, "conectar", "PDO", PUBLICO, []);

    var dSocio = nuevoElemento(pkgD, "DSocio", "Class",
        "Capa de datos. SQL de la tabla socios. No sabe de roles ni validaciones.");
    atributo(dSocio, "conexion", "PDO", PRIVADO);
    metodo(dSocio, "__construct", "void", PUBLICO, [["conexion", conexion]]);
    metodo(dSocio, "listar", "array", PUBLICO, []);
    metodo(dSocio, "registrar", "int", PUBLICO, SOCIO_PARAMS);
    metodo(dSocio, "eliminar", "bool", PUBLICO, [["id", "int"]]);
    metodo(dSocio, "reiniciarNumeracionSiVacia", "void", PRIVADO, []);

    var dUsuario = nuevoElemento(pkgD, "DUsuario", "Class",
        "Capa de datos. Consulta la tabla usuarios (login).");
    atributo(dUsuario, "conexion", "PDO", PRIVADO);
    metodo(dUsuario, "__construct", "void", PUBLICO, [["conexion", conexion]]);
    metodo(dUsuario, "buscarPorNombre", "array", PUBLICO, [["nombre", "string"]]);

    var lectorCSV = nuevoElemento(pkgD, "LectorCSV", "Class",
        "Capa de datos. Patrón Adapter — rol: Adaptee. Devuelve filas de texto por posición (string[][]).");
    metodo(lectorCSV, "leerRegistros", "array", PUBLICO,
        [["contenido", "string"], ["tieneEncabezado", "bool"]]);

    // ================= CAPA DE NEGOCIO (azul) =================
    // CU1 — Proxy
    var iSocio = nuevoElemento(pkgN, "ISocio", "Interface",
        "Capa de negocio. Patrón Proxy — rol: Sujeto.");
    atributo(iSocio, "PLANES", "array", PUBLICO, true, true);
    atributo(iSocio, "LARGO_MAXIMO_NOMBRE", "int", PUBLICO, true, true);
    metodo(iSocio, "listarSocios", "array", PUBLICO, [], true);
    metodo(iSocio, "registrarSocio", "int", PUBLICO, SOCIO_PARAMS, true);
    metodo(iSocio, "eliminarSocio", "bool", PUBLICO, [["id", "int"]], true);

    var socio = nuevoElemento(pkgN, "Socio", "Class",
        "Capa de negocio. Patrón Proxy — rol: SujetoReal. Valida y delega el SQL en DSocio.");
    atributo(socio, "datos", dSocio, PRIVADO);
    metodo(socio, "__construct", "void", PUBLICO, [["datos", dSocio]]);
    metodo(socio, "listarSocios", "array", PUBLICO, []);
    metodo(socio, "registrarSocio", "int", PUBLICO, SOCIO_PARAMS);
    metodo(socio, "eliminarSocio", "bool", PUBLICO, [["id", "int"]]);

    var proxySocio = nuevoElemento(pkgN, "ProxySocio", "Class",
        "Capa de negocio. Patrón Proxy — rol: Proxy (protección). Verifica usuarioRol antes de eliminarSocio().");
    atributo(proxySocio, "socioReal", iSocio, PRIVADO);
    atributo(proxySocio, "usuarioRol", "string|null", PRIVADO);
    metodo(proxySocio, "__construct", "void", PUBLICO, [["socioReal", iSocio], ["usuarioRol", "string|null"]]);
    metodo(proxySocio, "listarSocios", "array", PUBLICO, []);
    metodo(proxySocio, "registrarSocio", "int", PUBLICO, SOCIO_PARAMS);
    metodo(proxySocio, "eliminarSocio", "bool", PUBLICO, [["id", "int"]]);

    // CU2 — Adapter
    var iFuente = nuevoElemento(pkgN, "IFuenteSocios", "Interface",
        "Capa de negocio. Patrón Adapter — rol: Target. Socios ya validados.");
    metodo(iFuente, "obtenerSocios", "array", PUBLICO, [], true);
    metodo(iFuente, "obtenerErrores", "array", PUBLICO, [], true);

    var adapter = nuevoElemento(pkgN, "AdapterSociosCSV", "Class",
        "Capa de negocio. Patrón Adapter — rol: Adapter. Traduce las filas de LectorCSV a socios.");
    atributo(adapter, "adaptee", lectorCSV, PRIVADO);
    atributo(adapter, "contenido", "string", PRIVADO);
    atributo(adapter, "errores", "array", PRIVADO);
    metodo(adapter, "__construct", "void", PUBLICO, [["adaptee", lectorCSV], ["contenido", "string"]]);
    metodo(adapter, "obtenerSocios", "array", PUBLICO, []);
    metodo(adapter, "obtenerErrores", "array", PUBLICO, []);
    metodo(adapter, "registroASocio", "array|null", PRIVADO, [["registro", "array"], ["numeroFila", "int"]]);

    // Login (no es patrón)
    var usuario = nuevoElemento(pkgN, "Usuario", "Class",
        "Capa de negocio. Verifica la contraseña (password_verify); el SQL lo hace DUsuario.");
    atributo(usuario, "datos", dUsuario, PRIVADO);
    metodo(usuario, "__construct", "void", PUBLICO, [["datos", dUsuario]]);
    metodo(usuario, "autenticar", "array|null", PUBLICO, [["nombre", "string"], ["clave", "string"]]);

    // ================= CAPA DE PRESENTACIÓN (verde) =================
    var pSocio = nuevoElemento(pkgP, "PSocio", "Class",
        "Capa de presentación. Cliente de los dos patrones: solo conoce ISocio e IFuenteSocios. HTML en vista.php.");
    atributo(pSocio, "mensaje", "string|null", PRIVADO);
    atributo(pSocio, "error", "string|null", PRIVADO);
    atributo(pSocio, "codigoHttp", "int", PRIVADO);
    metodo(pSocio, "procesar", "void", PUBLICO, []);
    metodo(pSocio, "verPagina", "void", PUBLICO, []);
    metodo(pSocio, "iniciarSesion", "void", PUBLICO, [["request", "array"]]);
    metodo(pSocio, "cerrarSesion", "void", PUBLICO, []);
    metodo(pSocio, "guardar", "void", PUBLICO, [["request", "array"]]);
    metodo(pSocio, "eliminar", "void", PUBLICO, [["id", "int"]]);
    metodo(pSocio, "importar", "void", PUBLICO, [["archivo", "array"]]);
    metodo(pSocio, "crearSocio", iSocio, PRIVADO, []);
    metodo(pSocio, "crearFuenteSocios", iFuente, PRIVADO, [["contenido", "string"]]);

    // ---------------- Colores por capa ----------------
    pintar(pSocio, VERDE);
    var azules = [iSocio, socio, proxySocio, iFuente, adapter, usuario];
    for (var i = 0; i < azules.length; i++) pintar(azules[i], AZUL);
    var amarillos = [conexion, dSocio, dUsuario, lectorCSV];
    for (var j = 0; j < amarillos.length; j++) pintar(amarillos[j], AMARILLO);

    // ---------------- Notas de los patrones ----------------
    var notaProxy = nuevoElemento(pkgN, "", "Note",
        "eliminarSocio(id):\r\n  if (usuarioRol != 'admin') -> error 403\r\n  return socioReal->eliminarSocio(id)");
    var notaAdapter = nuevoElemento(pkgN, "", "Note",
        "obtenerSocios():\r\n  foreach (adaptee->leerRegistros(contenido, true) as registro)\r\n    socios[] = registroASocio(registro)");

    // ---------------- Relaciones ----------------
    // Presentación -> negocio (interfaces de los patrones, y Usuario para el login)
    relacionar(pSocio, iSocio, "Association", "usa", "");
    relacionar(pSocio, iFuente, "Association", "usa", "");
    relacionar(pSocio, usuario, "Association", "usa", "");

    // Proxy: Socio y ProxySocio implementan ISocio; el Proxy contiene al objeto real.
    relacionar(socio, iSocio, "Realisation", "", "");
    relacionar(proxySocio, iSocio, "Realisation", "", "");
    agregar(proxySocio, socio, "socioReal");

    // Adapter: implementa el Target y contiene al Adaptee.
    relacionar(adapter, iFuente, "Realisation", "", "");
    agregar(adapter, lectorCSV, "adaptee");

    // Negocio -> datos (como Evento -> DEvento) y datos -> Conexion (como DEvento -> Conexion)
    relacionar(socio, dSocio, "Association", "", "");
    relacionar(usuario, dUsuario, "Association", "", "");
    relacionar(dSocio, conexion, "Association", "", "");
    relacionar(dUsuario, conexion, "Association", "", "");

    relacionar(notaProxy, proxySocio, "NoteLink", "", "");
    relacionar(notaAdapter, adapter, "NoteLink", "", "");

    // ================= DIAGRAMAS =================
    // Las mismas clases aparecen en los 3 diagramas (no se duplican): si cambias una, cambia en todos.
    // Las relaciones se muestran solas cuando las dos clases están en el diagrama.

    // ---------- 1) General: las 3 capas y los 2 patrones ----------
    var general = nuevoDiagrama(modelo, "Diseño Procedimental - General",
        "Gimnasio en 3 capas. Verde: presentación. Azul: negocio. Amarillo: datos.\r\n" +
        "CU1 Proxy (dar de baja socio) y CU2 Adapter (importar socios CSV).");
    //                        izq   arriba  der   abajo
    colocar(general, pSocio,        20,  300,  260,  560);
    colocar(general, usuario,       20,  660,  260,  740);
    colocar(general, iSocio,       420,   40,  760,  180);
    colocar(general, socio,        320,  280,  600,  420);
    colocar(general, proxySocio,   660,  280,  980,  420);
    colocar(general, notaProxy,    660,  450,  960,  520);
    colocar(general, iFuente,      420,  600,  720,  690);
    colocar(general, adapter,      380,  760,  760,  920);
    colocar(general, notaAdapter,  800,  790, 1150,  870);
    colocar(general, dSocio,      1100,   40, 1420,  200);
    colocar(general, conexion,    1150,  300, 1370,  360);
    colocar(general, dUsuario,    1100,  440, 1420,  520);
    colocar(general, lectorCSV,    400, 1000,  740, 1070);
    general.Update();

    // ---------- 2) CU1: Proxy — dar de baja a un socio (solo admin) ----------
    var cu1 = nuevoDiagrama(modelo, "Diseño Procedimental - CU1 Proxy",
        "CU1: Dar de baja a un socio (solo administradores). Patrón Proxy de protección.\r\n" +
        "PSocio solo conoce ISocio. ProxySocio verifica el rol y delega en Socio; Socio delega el SQL en DSocio.");
    colocar(cu1, pSocio,        20,  200,  260,  460);
    colocar(cu1, iSocio,       380,   40,  720,  180);
    colocar(cu1, socio,        300,  280,  580,  420);
    colocar(cu1, proxySocio,   640,  280,  960,  420);
    colocar(cu1, notaProxy,   1000,  300, 1300,  380);
    colocar(cu1, dSocio,       280,  520,  600,  680);
    colocar(cu1, conexion,     330,  760,  550,  820);
    cu1.Update();

    // ---------- 3) CU2: Adapter — importar socios desde CSV ----------
    var cu2 = nuevoDiagrama(modelo, "Diseño Procedimental - CU2 Adapter",
        "CU2: Importar socios desde un archivo CSV. Patrón Adapter.\r\n" +
        "PSocio solo conoce IFuenteSocios. AdapterSociosCSV traduce las filas de LectorCSV a socios,\r\n" +
        "que luego se guardan por ISocio -> Socio -> DSocio.");
    colocar(cu2, pSocio,        20,  200,  260,  460);
    colocar(cu2, iFuente,      380,   40,  680,  130);
    colocar(cu2, adapter,      340,  220,  720,  380);
    colocar(cu2, lectorCSV,    360,  460,  700,  530);
    colocar(cu2, notaAdapter,  340,  590,  700,  670);
    colocar(cu2, iSocio,       800,   40, 1140,  180);
    colocar(cu2, socio,        820,  240, 1100,  380);
    colocar(cu2, dSocio,       800,  460, 1120,  620);
    colocar(cu2, conexion,     850,  700, 1070,  760);
    cu2.Update();

    Repository.RefreshModelView(raiz.PackageID);
    Repository.ReloadDiagram(cu2.DiagramID);
    Repository.ReloadDiagram(cu1.DiagramID);
    Repository.ReloadDiagram(general.DiagramID);
    Repository.OpenDiagram(general.DiagramID);
    Session.Prompt("Listo: 3 diagramas (General, CU1 Proxy y CU2 Adapter) con 13 clases/interfaces en 3 capas.", 2);
}

function nuevoDiagrama(modelo, nombre, nota) {
    var diagrama = modelo.Diagrams.AddNew(nombre, "Logical");
    diagrama.Notes = nota;
    diagrama.Update();
    modelo.Diagrams.Refresh();
    return diagrama;
}

main();
