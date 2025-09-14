<?php
/**
 * Controlador para el Recurso Stock
 *
 * Este controlador maneja la lógica para el stock de productos y tallas.
 * A diferencia de otros controladores, este NO extiende `BaseController` porque
 * el stock se trata como un sub-recurso de un producto.
 *
 * Las rutas para el stock dependen de un producto, por ejemplo:
 * - GET /private/producto/{producto_id}/stock -> Listar el stock de un producto.
 * - POST /private/producto/{producto_id}/stock -> Añadir stock a un producto.
 *
 * Esto requiere una lógica ligeramente diferente a la del CRUD estándar.
 */
class StockController {
    /**
     * @var Stock Instancia del modelo Stock.
     */
    private $model;
    /**
     * @var array Campos a ocultar en la respuesta.
     */
    protected $hiddenFields = ['producto_id', 'talla_id'];

    /**
     * Constructor del StockController.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        $this->model = new Stock($conn);
    }

    /**
     * Valida los datos para la creación y actualización de stock.
     *
     * @param array $data Los datos a validar.
     * @param bool $isUpdate Flag para diferenciar creación de actualización.
     * @return array Un array de errores.
     */
    protected function validate($data, $isUpdate = false) {
        $errors = [];
        // Al crear, la talla y el stock son obligatorios.
        if (!$isUpdate) {
            $mandatoryFields = ['talla_id', 'stock'];
            foreach ($mandatoryFields as $field) {
                if (empty($data[$field])) $errors[$field] = 'El campo ' . $field . ' es obligatorio.';
            }
        }

        // Valida que el stock sea un número.
        if (isset($data['stock']) && !is_numeric($data['stock'])) {
            $errors['stock'] = 'El campo stock debe ser un número válido.';
        }

        return $errors;
    }

    /**
     * Lista todo el stock para un producto específico.
     * Corresponde a: GET /producto/{producto_id}/stock
     *
     * @param int $id El ID del producto del cual se quiere listar el stock.
     */
    public function index($id) {
        try {
            // Llama al método `all` del modelo, que está especializado para buscar por producto_id.
            $data = $this->model->all($id);
            
            // Oculta campos antes de enviar la respuesta.
            foreach ($this->hiddenFields as $field) {
                foreach ($data as &$item) {
                     unset($item[$field]);
                }
            }
            jsonResponse(['data' => $data], 200);
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra el stock de una talla específica para un producto.
     * Corresponde a: GET /producto/{producto_id}/stock/{talla_id}
     *
     * @param mixed ...$ids Un array que contiene producto_id y talla_id.
     */
    public function show(...$ids) {
        try {
            // Llama al método `find` del modelo, que busca por la clave primaria compuesta.
            $data = $this->model->find($ids);
            
            if ($data) {
                foreach ($this->hiddenFields as $field) {
                    unset($data[$field]);
                }
                jsonResponse(['data' => $data], 200);
            } else {
                jsonResponse(['error' => 'Registro no encontrado'], 404, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea una nueva entrada de stock para un producto.
     * Corresponde a: POST /producto/{producto_id}/stock
     *
     * @param int $id El ID del producto al que se añade stock.
     */
    public function store($id) {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(['error' => 'JSON inválido'], 400, false);
                return;
            }
            // Añade el producto_id (de la URL) a los datos de entrada.
            $input['producto_id'] = $id;
            
            $errors = $this->validate($input);
            if (!empty($errors)) {
                jsonResponse(['error' => $errors], 422, false);
                return;
            }

            $resId = $this->model->create($input);
            if ($resId) {
                // Busca el registro recién creado para devolverlo.
                $newData = $this->model->find([$input['producto_id'], $input['talla_id']]);
                foreach ($this->hiddenFields as $field) {
                    unset($newData[$field]);
                }
                jsonResponse(['data' => $newData, 'message' => 'Registro creado correctamente'], 201);
            } else {
                jsonResponse(['error' => 'No se pudo crear el registro'], 500, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Actualiza una entrada de stock específica.
     * Corresponde a: PUT o PATCH /producto/{producto_id}/stock/{talla_id}
     *
     * @param mixed ...$ids Un array que contiene producto_id y talla_id.
     */
    public function put(...$ids) {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(['error' => 'JSON inválido'], 400, false);
                return;
            }
            
            $errors = $this->validate($input, true);
            if (!empty($errors)) {
                jsonResponse(['error' => $errors], 422, false);
                return;
            }

            $exist = $this->model->find($ids);
            if (!$exist) {
                jsonResponse(['error' => 'Registro no encontrado'], 404, false);
                return;
            }

            $ok = $this->model->update($ids, $input);
            if ($ok) {
                $updatedData = $this->model->find($ids);
                foreach ($this->hiddenFields as $field) {
                    unset($updatedData[$field]);
                }
                jsonResponse(['data' => $updatedData, 'message' => 'Registro actualizado correctamente'], 200);
            } else {
                jsonResponse(['error' => 'No se pudo actualizar el registro'], 500, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Elimina una entrada de stock.
     * Corresponde a: DELETE /producto/{producto_id}/stock/{talla_id}
     *
     * @param mixed ...$ids Un array que contiene producto_id y talla_id.
     */
    public function destroy(...$ids) {
        try {
            $exist = $this->model->find($ids);
            if (!$exist) {
                jsonResponse(['success' => false, 'error' => 'Registro no encontrado'], 404);
                return;
            }

            $ok = $this->model->delete($ids);
            if ($ok) {
                jsonResponse(['success' => true, 'message' => 'Registro eliminado correctamente'], 200);
            } else {
                jsonResponse(['success' => false, 'error' => 'No se pudo eliminar el registro'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}