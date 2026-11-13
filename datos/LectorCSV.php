<?php
// Capa de datos
// Patrón Adapter — rol: Adaptee
// Clase existente cuya interfaz no coincide con la que espera el sistema:
// solo separa líneas y columnas y devuelve filas de texto por posición (string[][]).
// No conoce a los socios; el Adapter es quien la traduce.

class LectorCSV
{
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    /** @return string[][] Registros de texto, sin líneas vacías */
    public function leerRegistros(string $contenido, bool $tieneEncabezado): array
    {
        $texto = str_replace("\r", '', $contenido);
        if (str_starts_with($texto, self::BOM_UTF8)) {
            $texto = substr($texto, strlen(self::BOM_UTF8));
        }

        $registros = [];
        foreach (explode("\n", $texto) as $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $registros[] = str_getcsv($linea, ',', '"', '');
        }

        if ($tieneEncabezado) {
            array_shift($registros);
        }
        return $registros;
    }
}
