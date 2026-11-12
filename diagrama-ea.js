// =====================================================================
// Script JScript para Enterprise Architect (EA 15 o superior)
// Crea los objetos del sistema de tareas (Proxy + Adapter), sus relaciones
// y el diagrama "Diseño Procedimental" con las cajas ubicadas.
//
// Uso: seleccionar un paquete en el Navegador de EA y ejecutar este script
// desde la ventana de Scripting (Specialize > Tools > Scripting).
// =====================================================================

var PUBLICO = "Public";
var PRIVADO = "Private";

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

// Asociación dirigida con nombre de rol y multiplicidad en el extremo destino.
function asociarConRol(origen, destino, rol, multiplicidad) {
    var conector = relacionar(origen, destino, "Association", "", "");
    conector.SupplierEnd.Role = rol;
    conector.SupplierEnd.Cardinality = multiplicidad;
    conector.SupplierEnd.Navigable = "Navigable";
    conector.SupplierEnd.Update();
    conector.Update();
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

    // ---------------- Paquetes ----------------
    var modelo = nuevoPaquete(raiz, "Diseño Procedimental - Gestión de Tareas");
    var pkgProxy = nuevoPaquete(modelo, "proxy");
    var pkgAdapter = nuevoPaquete(modelo, "adapter");

    // ---------------- Clase externa ----------------
    var pdo = nuevoElemento(modelo, "PDO", "Class",
        "Clase nativa de PHP para acceder a PostgreSQL. La crea crearConexion() (conexion.php) y se pasa por constructor.");

    // ---------------- CU1: Proxy ----------------
    var tareaInterface = nuevoElemento(pkgProxy, "TareaInterface", "Interface",
        "Patrón Proxy — rol: Sujeto. Interfaz común de TareaReal y ProxyTarea.");
    atributo(tareaInterface, "ESTADOS", "array", PUBLICO, true, true);
    metodo(tareaInterface, "listarTareas", "array", PUBLICO, [], true);
    metodo(tareaInterface, "crearTarea", "int", PUBLICO,
        [["titulo", "string"], ["descripcion", "string"], ["estado", "string"]], true);
    metodo(tareaInterface, "eliminarTarea", "bool", PUBLICO, [["id", "int"]], true);

    var tareaReal = nuevoElemento(pkgProxy, "TareaReal", "Class",
        "Patrón Proxy — rol: SujetoReal. Accede a PostgreSQL; no sabe nada de roles.");
    atributo(tareaReal, "db", pdo, PRIVADO);
    metodo(tareaReal, "__construct", "void", PUBLICO, [["db", pdo]]);
    metodo(tareaReal, "listarTareas", "array", PUBLICO, []);
    metodo(tareaReal, "crearTarea", "int", PUBLICO,
        [["titulo", "string"], ["descripcion", "string"], ["estado", "string"]]);
    metodo(tareaReal, "eliminarTarea", "bool", PUBLICO, [["id", "int"]]);
    metodo(tareaReal, "reiniciarNumeracionSiVacia", "void", PRIVADO, []);

    var proxyTarea = nuevoElemento(pkgProxy, "ProxyTarea", "Class",
        "Patrón Proxy — rol: Proxy (protección). Verifica usuarioRol antes de delegar eliminarTarea().");
    atributo(proxyTarea, "tareaReal", tareaInterface, PRIVADO);
    atributo(proxyTarea, "usuarioRol", "string|null", PRIVADO);
    metodo(proxyTarea, "__construct", "void", PUBLICO,
        [["tareaReal", tareaInterface], ["usuarioRol", "string|null"]]);
    metodo(proxyTarea, "listarTareas", "array", PUBLICO, []);
    metodo(proxyTarea, "crearTarea", "int", PUBLICO,
        [["titulo", "string"], ["descripcion", "string"], ["estado", "string"]]);
    metodo(proxyTarea, "eliminarTarea", "bool", PUBLICO, [["id", "int"]]);

    // ---------------- CU2: Adapter ----------------
    var fuenteInterface = nuevoElemento(pkgAdapter, "FuenteTareasInterface", "Interface",
        "Patrón Adapter — rol: Target. Lo que el sistema espera: tareas validadas.");
    metodo(fuenteInterface, "obtenerTareas", "array", PUBLICO, [], true);
    metodo(fuenteInterface, "obtenerErrores", "array", PUBLICO, [], true);

    var lectorCSV = nuevoElemento(pkgAdapter, "LectorCSV", "Class",
        "Patrón Adapter — rol: Adaptee. Clase existente con interfaz incompatible: devuelve filas de texto por posición (string[][]).");
    atributo(lectorCSV, "BOM_UTF8", "string", PRIVADO, true, true);
    metodo(lectorCSV, "leerRegistros", "array", PUBLICO,
        [["contenido", "string"], ["tieneEncabezado", "bool"]]);

    var adapter = nuevoElemento(pkgAdapter, "AdapterTareasCSV", "Class",
        "Patrón Adapter — rol: Adapter. Implementa FuenteTareasInterface usando LectorCSV.");
    atributo(adapter, "adaptee", lectorCSV, PRIVADO);
    atributo(adapter, "contenido", "string", PRIVADO);
    atributo(adapter, "errores", "array", PRIVADO);
    metodo(adapter, "__construct", "void", PUBLICO, [["adaptee", lectorCSV], ["contenido", "string"]]);
    metodo(adapter, "obtenerTareas", "array", PUBLICO, []);
    metodo(adapter, "obtenerErrores", "array", PUBLICO, []);
    metodo(adapter, "registroATarea", "array|null", PRIVADO, [["registro", "array"], ["numeroFila", "int"]]);

    // ---------------- Cliente ----------------
    var index = nuevoElemento(modelo, "index.php", "Class",
        "Cliente de ambos patrones. Solo conoce TareaInterface y FuenteTareasInterface.");
    atributo(index, "_SESSION", "array", PRIVADO);
    metodo(index, "main", "void", PUBLICO, []);
    metodo(index, "crearProxyTarea", tareaInterface, PRIVADO, []);
    metodo(index, "importarCsv", "array", PRIVADO, [["tareas", tareaInterface], ["archivo", "array"]]);

    // ---------------- Notas de los patrones ----------------
    var notaProxy = nuevoElemento(pkgProxy, "", "Note",
        "eliminarTarea(id):\r\n  if (usuarioRol != 'admin') -> error 403\r\n  return tareaReal->eliminarTarea(id)");
    var notaAdapter = nuevoElemento(pkgAdapter, "", "Note",
        "obtenerTareas():\r\n  foreach (adaptee->leerRegistros(contenido, true) as registro)\r\n    tareas[] = registroATarea(registro)");

    // ---------------- Relaciones ----------------
    // Cliente: index.php usa las dos interfaces (como index.php -> CuentaInterface).
    relacionar(index, tareaInterface, "Association", "usa", "");
    relacionar(index, fuenteInterface, "Association", "usa", "");

    // Proxy: las dos clases implementan el Sujeto; el Proxy delega en el SujetoReal.
    relacionar(tareaReal, tareaInterface, "Realisation", "", "");
    relacionar(proxyTarea, tareaInterface, "Realisation", "", "");
    relacionar(proxyTarea, tareaReal, "Dependency", "delega", "");
    relacionar(tareaReal, pdo, "Dependency", "", "use");

    // Adapter: implementa el Target y contiene al Adaptee.
    relacionar(adapter, fuenteInterface, "Realisation", "", "");
    asociarConRol(adapter, lectorCSV, "adaptee", "1");

    // Notas enlazadas a la clase que explican.
    relacionar(notaProxy, proxyTarea, "NoteLink", "", "");
    relacionar(notaAdapter, adapter, "NoteLink", "", "");

    // ---------------- Diagrama ----------------
    var diagrama = modelo.Diagrams.AddNew("Diseño Procedimental", "Logical");
    diagrama.Notes = "Sistema de gestión de tareas: Proxy (CU1) y Adapter (CU2).";
    diagrama.Update();
    modelo.Diagrams.Refresh();

    //                        izq  arriba der  abajo
    colocar(diagrama, index,           30,  40, 250, 150);
    colocar(diagrama, tareaInterface, 360,  30, 700, 150);
    colocar(diagrama, tareaReal,       40, 250, 420, 420);
    colocar(diagrama, proxyTarea,     560, 250, 960, 420);
    colocar(diagrama, notaProxy,     1000, 270, 1290, 350);
    colocar(diagrama, pdo,            140, 500, 320, 560);
    colocar(diagrama, fuenteInterface,460, 500, 760, 590);
    colocar(diagrama, adapter,        420, 670, 800, 850);
    colocar(diagrama, notaAdapter,    840, 700, 1200, 790);
    colocar(diagrama, lectorCSV,      440, 930, 780, 1020);
    diagrama.Update();

    Repository.RefreshModelView(raiz.PackageID);
    Repository.ReloadDiagram(diagrama.DiagramID);
    Repository.OpenDiagram(diagrama.DiagramID);
    Session.Prompt("Listo: se creó el diagrama 'Diseño Procedimental' con 8 objetos, 2 notas y 10 relaciones.", 2);
}

main();
