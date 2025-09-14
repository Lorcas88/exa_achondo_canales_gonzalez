<?php
/**
 * Controlador para el Recurso Usuario
 *
 * Esta clase maneja la lógica de negocio para la gestión de usuarios.
 * Extiende `BaseController` para heredar las operaciones CRUD estándar.
 */
class UserController extends BaseController {
    /**
     * @var array Campos que se ocultarán en las respuestas JSON para proteger datos sensibles.
     */
    protected $hiddenFields = ['contrasena', 'rol_id', 'cliente_id'];
    
    /**
     * Constructor del UserController.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        // Se crea una instancia del modelo User y se pasa al constructor del padre.
        $model = new User($conn);
        parent::__construct($model);
    }

    /**
     * Valida los datos para la creación y actualización de un usuario.
     *
     * @param array $data Los datos del usuario a validar.
     * @param bool $isUpdate Flag para diferenciar entre creación (false) y actualización (true).
     * @return array Un array con los errores de validación. Vacío si no hay errores.
     */
    protected function validate($data, $isUpdate = false) {
        $errors = [];

        // Reglas que solo aplican al crear un usuario nuevo.
        if (!$isUpdate) {
            if (empty($data['nombre'])) $errors['nombre'] = 'El nombre es obligatorio.';
            if (empty($data['apellido'])) $errors['apellido'] = 'El apellido es obligatorio.';
            if (empty($data['email'])) $errors['email'] = 'El email es obligatorio.';
            if (empty($data['contrasena'])) $errors['contrasena'] = 'La contraseña es obligatoria.';
            if (empty($data['rol_id'])) $errors['rol_id'] = 'El rol es obligatorio.';
        }

        // Valida que el email tenga un formato correcto.
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido.';
        }

        // Valida que el rol_id sea uno de los valores permitidos.
        if (isset($data['rol_id'])) {
            $validRoles = [1, 2, 3]; // Asumiendo 1:Admin, 2:Editor, 3:Cliente
            if (!in_array($data['rol_id'], $validRoles)) {
                $errors['rol_id'] = 'Rol inválido.';
            }
        }

        // Valida que el campo 'activo' sea 0 o 1.
        if (isset($data['activo'])) {
            $validStatus = [0, 1];
            if (!in_array($data['activo'], $validStatus)) {
                $errors['activo'] = 'Estado inválido (debe ser 0 o 1).';
            }
        }

        // Valida que la fecha de nacimiento tenga el formato YYYY-MM-DD.
        if (isset($data['fecha_nacimiento']) && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $data['fecha_nacimiento'])) {
            $errors['fecha_nacimiento'] = 'Formato de fecha inválido (debe ser YYYY-MM-DD).';
        }

        return $errors;
    }

    /**
     * Da de baja al usuario actualmente autenticado.
     * Este es un método personalizado que no forma parte del CRUD estándar.
     */
    public function unsubscribe() {
        // Obtiene el ID del usuario de la sesión actual.
        $userId = $_SESSION['usuario']['id'];

        // Llama al modelo para actualizar el campo 'activo' a 0 (inactivo).
        $this->model->updateField($userId, ['activo' => 0]);

        // Destruye la sesión del usuario para cerrar su sesión inmediatamente.
        session_unset();
        session_destroy();
        
        // Envía una respuesta de éxito.
        jsonResponse(['message' => 'Usuario dado de baja satisfactoriamente'], 200);
    }
}
