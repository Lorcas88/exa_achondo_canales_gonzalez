<?php
// require_once __DIR__ . '/../views/jsonResponse.php';

abstract class BaseController {
    protected $model;
    protected $hiddenFields = [];
    
    public function __construct($model) {
        $this->model = $model;
    }

    // Cada controlador debe implementar su propia validación
    abstract protected function validate($input);

    public function index() {
        try {

            $data = $this->model->all();
            
            // Filtrar campos ocultos
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

    public function show($id) {
        try {
            $data = $this->model->find($id);
            
            if ($data) {
                // Filtrar campos ocultos
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
    
    // Crear nuevo registro
    public function store() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) jsonResponse(['error' => 'JSON inválido'], 400, false);
            
            // Chequear si el hijo tiene el método
            if (method_exists($this, 'validate')) {
                $errores = $this->validate($input);
                if (!empty($errores)) {
                    jsonResponse(['error' => $errores], 422, false);
                }
            }
            
            $id = $this->model->create($input);
            if ($id) {
                $nuevo = $this->model->find($id);
                // Filtrar campos ocultos
                foreach ($this->hiddenFields as $field) {
                    unset($nuevo[$field]);
                }

                jsonResponse(['data' => $nuevo, 'message' => 'Registro creado correctamente'], 201);
            } else {
                jsonResponse(['error' => 'No se pudo crear el registro'], 500, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    public function put($id) {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) jsonResponse(['error' => 'JSON inválido'], 400, false);
            
            // Chequear si el hijo tiene el método
            if (method_exists($this, 'validate')) {
                $errores = $this->validate($input, true);
                if (!empty($errores)) {
                    jsonResponse(['error' => $errores], 422, false);
                }
            }
            
            $exist = $this->model->find($id);
            if (!$exist) jsonResponse(['error' => 'Registro no encontrado'], 404, false);

            $ok = $this->model->update($id, $input);
            if ($ok) {
                $actualizado = $this->model->find($id);
                // Filtrar campos ocultos
                foreach ($this->hiddenFields as $field) {
                    unset($actualizado[$field]);
                }
                $message = 'Registro actualizado ';
                if (is_array($ok) && !empty($ok['not_updated'])) {
                    $message .= 'con errores. Estos campos no se pueden actualizar: ' . implode(', ', $ok['not_updated']);
                } else {
                    $message .= 'correctamente';
                }
                jsonResponse(['data' => $actualizado, 'message' => $message], 200);
            } else {
                jsonResponse(['error' => 'No se pudo actualizar el registro'], 500, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    // Eliminar registro
    public function destroy($id) {
        try {
            $exist = $this->model->find($id);
            if (!$exist) jsonResponse(['success' => false, 'error' => 'Registro no encontrado'], 404);

            $ok = $this->model->delete($id);
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