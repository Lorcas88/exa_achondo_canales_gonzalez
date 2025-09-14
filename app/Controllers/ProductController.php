<?php
/**
 * Controlador para el Recurso Producto
 *
 * Esta clase maneja la lógica de negocio para todo lo relacionado con los productos.
 * Extiende `BaseController` para heredar las operaciones CRUD básicas, pero sobrescribe
 * los métodos `index` y `show` para añadir funcionalidades específicas como el cálculo
 * de precios dinámicos y el filtrado.
 */
class ProductController extends BaseController {
    /**
     * @var array Campos que se ocultarán en las respuestas JSON.
     */
    protected $hiddenFields = ['creado_at'];

    /**
     * Constructor del ProductController.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        // Se crea una instancia del modelo Product y se pasa al constructor del padre (BaseController).
        $model = new Product($conn);
        parent::__construct($model);
    }

    /**
     * Muestra una lista de productos, con precios calculados y aplicando filtros.
     * Sobrescribe el método `index` de `BaseController`.
     */
    public function index() {
        try {
            // Obtiene el ID del cliente de la sesión actual, si existe.
            $clienteId = $_SESSION['usuario']['cliente_id'] ?? null;
            
            // Recoge los posibles filtros desde la query string de la URL (ej. /productos?pais=Chile).
            $filters = [];
            if (isset($_GET['pais'])) {
                $filters['pais'] = $_GET['pais'];
            }
            if (isset($_GET['tipo'])) {
                $filters['tipo'] = $_GET['tipo'];
            }
            if (isset($_GET['color'])) {
                $filters['color'] = $_GET['color'];
            }

            // Llama a un método especializado del modelo que calcula el precio final y aplica los filtros.
            $data = $this->model->allWithFinalPrice($clienteId, $filters);
            
            // Oculta los campos no deseados antes de enviar la respuesta.
            foreach ($this->hiddenFields as $field) {
                foreach ($data as &$item) {
                     unset($item[$field]);
                }
            }
            // Envía los datos como una respuesta JSON.
            jsonResponse(['data' => $data], 200);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un producto específico con su precio final calculado.
     * Sobrescribe el método `show` de `BaseController`.
     *
     * @param int $id El ID del producto a mostrar.
     */
    public function show($id) {
        try {
            // Obtiene el ID del cliente de la sesión actual.
            $clienteId = $_SESSION['usuario']['cliente_id'] ?? null;
            // Llama al método especializado del modelo para encontrar el producto y calcular su precio.
            $data = $this->model->findWithFinalPrice($id, $clienteId);
            
            if ($data) {
                // Oculta los campos no deseados.
                foreach ($this->hiddenFields as $field) {
                    unset($data[$field]);
                }
                // Envía la respuesta JSON.
                jsonResponse(['data' => $data], 200);
            } else {
                jsonResponse(['error' => 'Registro no encontrado'], 404, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Valida los datos para la creación y actualización de un producto.
     * Implementa el método abstracto de `BaseController`.
     *
     * @param array $data Los datos del producto a validar.
     * @param bool $isUpdate Flag para diferenciar entre creación y actualización.
     * @return array Un array con los errores de validación. Vacío si no hay errores.
     */
    protected function validate($data, $isUpdate = false) {
        $errors = [];

        // Reglas de validación para la creación (cuando no es una actualización).
        if (!$isUpdate) {
            $mandatoryFields = ['titulo', 'precio', 'sku'];
            foreach ($mandatoryFields as $field) {
                if (empty($data[$field])) {
                    $errors[$field] = 'El campo ' . $field . ' es obligatorio.';
                }
            }
        }

        // Reglas de longitud máxima para campos de tipo string.
        $maxLengthFields = [
            'titulo' => 200,
            'club' => 150,
            'pais' => 80,
            'tipo' => 80,
            'color' => 120,
            'sku' => 80,
        ];
        foreach ($maxLengthFields as $field => $maxLength) {
            if (isset($data[$field]) && strlen($data[$field]) > $maxLength) {
                $errors[$field] = 'Máximo ' . $maxLength . ' caracteres.';
            }
        }

        // Reglas de validación para campos numéricos (precios).
        $numericFields = ['precio', 'precio_oferta'];
        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                if (!is_numeric($data[$field])) {
                    $errors[$field] = 'El campo ' . $field . ' debe ser un número válido.';
                } elseif ($data[$field] < 0) {
                    $errors[$field] = 'El campo ' . $field . ' no puede ser negativo.';
                }
            }
        }

        return $errors;
    }
}