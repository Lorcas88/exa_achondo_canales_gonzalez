<?php
/**
 * Controlador para el Recurso Cliente
 *
 * Esta clase maneja la lógica de negocio para la gestión de los clientes (empresas B2B).
 * Extiende `BaseController` para heredar las operaciones CRUD estándar.
 */
class ClientController extends BaseController {
    /**
     * Constructor del ClientController.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        // Se crea una instancia del modelo Client y se pasa al constructor del padre.
        $model = new Client($conn);
        parent::__construct($model);
    }

    /**
     * Valida los datos para la creación y actualización de un cliente.
     *
     * @param array $data Los datos del cliente a validar.
     * @param bool $isUpdate Flag para diferenciar entre creación y actualización.
     * @return array Un array con los errores de validación.
     */
    protected function validate($data, $isUpdate = false) {
        $errors = [];

        // --- Campos Obligatorios (solo al crear) ---
        if (!$isUpdate) {
            $mandatoryFields = ['nombre_comercial', 'rut'];
            foreach ($mandatoryFields as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = 'El campo ' . $field . ' es obligatorio.';
                }
            }
        }

        // --- Validación de Formato de RUT Chileno ---
        if (isset($data['rut']) && !preg_match('/^\d{7,8}-[0-9kK]$/', $data['rut'])) {
            $errors['rut'] = 'El formato del RUT debe ser sin puntos y con guión (ej: 12345678-9).';
        }

        // --- Validación de Formato de Email ---
        if (isset($data['contacto_email']) && !filter_var($data['contacto_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contacto_email'] = 'El formato del email de contacto es inválido.';
        }

        // --- Validación de Campos Numéricos (Porcentaje de Descuento) ---
        if (isset($data['porcentaje_descuento'])) {
            if (!is_numeric($data['porcentaje_descuento'])) {
                $errors['porcentaje_descuento'] = 'El porcentaje de descuento debe ser un número válido.';
            } elseif ($data['porcentaje_descuento'] < 0 || $data['porcentaje_descuento'] > 100) {
                $errors['porcentaje_descuento'] = 'El porcentaje de descuento debe estar entre 0 y 100.';
            } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $data['porcentaje_descuento'])) {
                $errors['porcentaje_descuento'] = 'El porcentaje de descuento puede tener hasta 2 decimales.';
            }
        }

        // --- Validación de Longitud Máxima de Campos de Texto ---
        $maxLengthFields = [
            'nombre_comercial' => 150,
            'rut' => 30,
            'direccion' => 255,
            'contacto_nombre' => 120,
            'contacto_email' => 120,
        ];
        foreach ($maxLengthFields as $field => $maxLength) {
            if (isset($data[$field]) && strlen($data[$field]) > $maxLength) {
                $errors[$field] = 'El campo ' . $field . ' no puede exceder los ' . $maxLength . ' caracteres.';
            }
        }
        
        // --- Validación de Categoría (Comentado) ---
        // if (isset($data['categoria']) && !in_array($data['categoria'], ['Regular', 'Preferencial'])) {
        //     $errors['categoria'] = 'La categoría debe ser "Regular" o "Preferencial".';
        // }

        return $errors;
    }
}