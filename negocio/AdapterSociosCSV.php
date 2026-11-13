<?php
// Capa de negocio
// Patrón Adapter — rol: Adapter
// Implementa IFuenteSocios usando LectorCSV (capa de datos): traduce registros de texto a socios.

class AdapterSociosCSV implements IFuenteSocios
{
    private array $errores = [];

    public function __construct(
        private LectorCSV $adaptee,
        private string $contenido
    ) {}

    public function obtenerSocios(): array
    {
        $this->errores = [];
        $socios = [];

        $registros = $this->adaptee->leerRegistros($this->contenido, true);
        foreach ($registros as $indice => $registro) {
            $numeroFila = $indice + 2; // +1 por el encabezado y +1 porque se cuenta desde 1.
            $socio = $this->registroASocio($registro, $numeroFila);
            if ($socio !== null) {
                $socios[] = $socio;
            }
        }
        return $socios;
    }

    public function obtenerErrores(): array
    {
        return $this->errores;
    }

    // Registro posicional [nombre, telefono, plan] → socio. Devuelve null si es inválido.
    private function registroASocio(array $registro, int $numeroFila): ?array
    {
        $nombre = trim($registro[0] ?? '');
        $telefono = trim($registro[1] ?? '');
        $plan = strtolower(trim($registro[2] ?? ''));

        if ($plan === '') {
            $plan = 'mensual';
        }

        if ($nombre === '') {
            $this->errores[] = "fila $numeroFila: falta el nombre";
            return null;
        }
        if (mb_strlen($nombre) > ISocio::LARGO_MAXIMO_NOMBRE) {
            $this->errores[] = "fila $numeroFila: el nombre supera " . ISocio::LARGO_MAXIMO_NOMBRE . ' caracteres';
            return null;
        }
        if (!in_array($plan, ISocio::PLANES, true)) {
            $this->errores[] = "fila $numeroFila: plan '$plan' no permitido";
            return null;
        }

        return ['nombre' => $nombre, 'telefono' => $telefono, 'plan' => $plan];
    }
}
