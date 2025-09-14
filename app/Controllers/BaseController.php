<?php
/**
 * Controlador Base Abstracto
 *
 * Esta clase abstracta sirve como plantilla para todos los controladores de recursos (como UserController, ProductController, etc.).
 * Proporciona la implementación estándar para las operaciones CRUD (Crear, Leer, Actualizar, Eliminar)
 * siguiendo los principios RESTful.
 *
 * Al heredar de esta clase, los controladores hijos obtienen automáticamente los métodos:
 * - index(): Listar todos los registros.
 * - show($id): Mostrar un registro específico.
 * - store(): Crear un nuevo registro.
 * - put($id): Actualizar un registro existente.
 * - destroy($id): Eliminar un registro.
 *
 * Esto promueve la reutilización de código y mantiene la consistencia en toda la API.
 */
abstract class BaseController {
    /**
     * @var object El modelo asociado al controlador (ej. User, Product).
     */
    protected $model;

    /**
     * @var array Lista de campos que se deben ocultar en las respuestas JSON.
     * Útil para no exponer datos sensibles como contraseñas o información interna.
     */
    protected $hiddenFields = [];
    
    /**
     * Constructor del BaseController.
     *
     * @param object $model Una instancia del modelo que el controlador manejará.
     *                      Esto es un ejemplo de Inyección de Dependencias.
     */
    public function __construct($model) {
        $this->model = $model;
    }

    /**
     * Método abstracto para la validación de datos.
     *
     * Cada controlador hijo DEBE implementar este método para definir
     * sus propias reglas de validación para los datos de entrada al crear (store) o actualizar (put).
     *
     * @param array $input Los datos a validar.
     * @return array Un array de errores. Si el array está vacío, la validación es exitosa.
     */
    abstract protected function validate($input);

    /**
     * Muestra una lista de todos los registros del recurso.
     * Corresponde a la operación GET /recurso
     */
    public function index() {
        try {
            // Llama al método `all()` del modelo para obtener todos los registros.
            $data = $this->model->all();
            
            // Filtra los campos definidos en la propiedad $hiddenFields.
            foreach ($this->hiddenFields as $field) {
                foreach ($data as &$item) {
                     unset($item[$field]);
                }
            }
            // Envía la respuesta en formato JSON con un código de estado 200 (OK).
            jsonResponse(['data' => $data], 200);
        } catch (Exception $e) {
            // En caso de un error inesperado, envía una respuesta de error 500.
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un único registro específico por su ID.
     * Corresponde a la operación GET /recurso/{id}
     *
     * @param int $id El ID del registro a buscar.
     */
    public function show($id) {
        try {
            // Llama al método `find()` del modelo para obtener el registro.
            $data = $this->model->find($id);
            
            if ($data) {
                // Si se encuentra el registro, filtra los campos ocultos.
                foreach ($this->hiddenFields as $field) {
                    unset($data[$field]);
                }
                // Envía el registro en formato JSON con un código 200 (OK).
                jsonResponse(['data' => $data], 200);
            } else {
                // Si no se encuentra, envía una respuesta de error 404 (No Encontrado).
                jsonResponse(['error' => 'Registro no encontrado'], 404, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Crea un nuevo registro.
     * Corresponde a la operación POST /recurso
     */
    public function store() {
        try {
            // Lee el cuerpo de la petición y lo decodifica de JSON a un array asociativo.
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(['error' => 'JSON inválido'], 400, false);
                return;
            }
            
            // Ejecuta la validación definida en el controlador hijo.
            if (method_exists($this, 'validate')) {
                $errors = $this->validate($input);
                if (!empty($errors)) {
                    // Si hay errores de validación, los devuelve con un código 422 (Unprocessable Entity).
                    jsonResponse(['error' => $errors], 422, false);
                    return;
                }
            }
            
            // Llama al método `create()` del modelo para insertar el nuevo registro.
            $id = $this->model->create($input);
            if ($id) {
                // Si se crea con éxito, busca el registro recién creado para devolverlo completo.
                $newData = $this->model->find($id);
                // Filtra los campos ocultos.
                foreach ($this->hiddenFields as $field) {
                    unset($newData[$field]);
                }
                // Envía el nuevo registro con un código 201 (Created).
                jsonResponse(['data' => $newData, 'message' => 'Registro creado correctamente'], 201);
            } else {
                jsonResponse(['error' => 'No se pudo crear el registro'], 500, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Actualiza un registro existente por su ID.
     * Corresponde a la operación PUT /recurso/{id}
     *
     * @param int $id El ID del registro a actualizar.
     */
    public function put($id) {
        try {
            // Lee y decodifica el cuerpo de la petición JSON.
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(['error' => 'JSON inválido'], 400, false);
                return;
            }
            
            // Valida los datos de entrada.
            if (method_exists($this, 'validate')) {
                $errors = $this->validate($input, true); // El segundo parámetro indica que es una actualización.
                if (!empty($errors)) {
                    jsonResponse(['error' => $errors], 422, false);
                    return;
                }
            }
            
            // Verifica si el registro que se intenta actualizar realmente existe.
            $exist = $this->model->find($id);
            if (!$exist) {
                jsonResponse(['error' => 'Registro no encontrado'], 404, false);
                return;
            }

            // Llama al método `update()` del modelo.
            $ok = $this->model->update($id, $input);
            if ($ok) {
                // Si la actualización es exitosa, busca y devuelve el registro actualizado.
                $updatedData = $this->model->find($id);
                // Filtra los campos ocultos.
                foreach ($this->hiddenFields as $field) {
                    unset($updatedData[$field]);
                }
                
                $message = 'Registro actualizado ';
                if (is_array($ok) && !empty($ok['not_updated'])) {
                    $message .= 'con errores. Estos campos no se pueden actualizar: ' . implode(', ', $ok['not_updated']);
                } else {
                    $message .= 'correctamente';
                }
                
                jsonResponse(['data' => $updatedData, 'message' => $message], 200);
            } else {
                jsonResponse(['error' => 'No se pudo actualizar el registro'], 500, false);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Elimina un registro por su ID.
     * Corresponde a la operación DELETE /recurso/{id}
     *
     * @param int $id El ID del registro a eliminar.
     */
    public function destroy($id) {
        try {
            // Verifica si el registro existe antes de intentar eliminarlo.
            $exist = $this->model->find($id);
            if (!$exist) {
                jsonResponse(['success' => false, 'error' => 'Registro no encontrado'], 404);
                return;
            }

            // Llama al método `delete()` del modelo.
            $ok = $this->model->delete($id);
            if ($ok) {
                // Si se elimina con éxito, devuelve una respuesta de éxito.
                jsonResponse(['success' => true, 'message' => 'Registro eliminado correctamente'], 200);
            } else {
                // Si falla, devuelve un error.
                jsonResponse(['success' => false, 'error' => 'No se pudo eliminar el registro'], 500);
            }
        } catch (Exception $e) {
            jsonResponse(['error' => $e->getMessage()], 400);
        }
    }
}
