<?php
/**
 * Controlador para el Recurso Talla
 *
 * Esta clase maneja la lógica de negocio para la gestión de las tallas de productos.
 * Extiende `BaseController` para heredar las operaciones CRUD estándar.
 */
class SizeController extends BaseController {
    /**
     * Constructor del SizeController.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        // Se crea una instancia del modelo Size y se pasa al constructor del padre.
        $model = new Size($conn);
        parent::__construct($model);
    }

    /**
     * Valida los datos para la creación y actualización de una talla.
     *
     * @param array $data Los datos de la talla a validar.
     * @param bool $isUpdate Flag para diferenciar entre creación y actualización.
     * @return array Un array con los errores de validación.
     */
    protected function validate($data, $isUpdate = false) {
        $errors = [];

        // --- Campos Obligatorios (solo al crear) ---
        if (!$isUpdate) {
            $mandatoryFields = ['talla'];
            foreach ($mandatoryFields as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = 'El campo ' . $field . ' es obligatorio.';
                }
            }
        }

        // --- Validación de Longitud Máxima ---
        $maxLengthFields = [
            'talla' => 20,
        ];
        foreach ($maxLengthFields as $field => $maxLength) {
            if (isset($data[$field]) && strlen($data[$field]) > $maxLength) {
                $errors[$field] = 'El campo ' . $field . ' no puede exceder los ' . $maxLength . ' caracteres.';
            }
        }

        return $errors;
    }
}