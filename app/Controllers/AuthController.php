<?php
/**
 * Controlador de Autenticación
 *
 * Esta clase maneja toda la lógica relacionada con la autenticación de usuarios,
 * incluyendo el inicio de sesión (login), el cierre de sesión (logout) y la
 * obtención de los datos del usuario actualmente autenticado (me).
 */
class AuthController extends BaseController {
    /**
     * @var array Campos que se ocultarán en las respuestas JSON.
     */
    protected $hiddenFields = ['contrasena', 'fecha_registro'];
    
    /**
     * Constructor del AuthController.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        // Utiliza el modelo User para buscar y verificar las credenciales de los usuarios.
        $model = new User($conn);
        parent::__construct($model);
    }

    /**
     * Valida los datos de entrada.
     * Nota: Este método parece no ser utilizado directamente por las funciones de este controlador,
     * pero está aquí por si se quisiera extender la funcionalidad.
     *
     * @param array $data Los datos a validar.
     * @param bool $isUpdate Flag para diferenciar creación de actualización.
     * @return array Un array de errores.
     */
    protected function validate($data, $isUpdate = false) {
        $errors = [];
        if (!$isUpdate) {
            if (empty($data['nombre'])) $errors['nombre'] = 'El nombre es obligatorio.';
            if (empty($data['contrasena'])) $errors['contrasena'] = 'La contraseña es obligatoria.';
        }
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email inválido.';
        }
        if (isset($data['rol_id'])) {
            $validos = [1, 2, 3];
            if (!in_array($data['rol_id'], $validos)) {
                $errors['rol_id'] = 'Rol inválido.';
            }
        }
        if (isset($data['activo'])) {
            $validos = [0,1];
            if (!in_array($data['activo'], $validos)) {
                $errors['activo'] = 'Estado inválido.';
            }
        }
        return $errors;
    }

    /**
     * Maneja el inicio de sesión de un usuario.
     *
     * Verifica las credenciales (email y contraseña) y, si son correctas,
     * crea una sesión para el usuario.
     */
    public function login() 
    {
        // 1. Lee y decodifica los datos JSON del cuerpo de la petición.
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsonResponse(['error' => 'JSON inválido'], 400, false);
            return;
        }

        // 2. Valida que se hayan enviado el email y la contraseña.
        if (empty($input['email']) || empty($input['contrasena'])) {
            jsonResponse(['error' => 'Email y contraseña son obligatorios'], 422, false);
            return;
        }
        
        // 3. Busca al usuario en la base de datos por su email.
        $user = $this->model->findBy('email', $input['email']);

        // 4. Verifica si el usuario existe y si la contraseña es correcta.
        // Se usa `password_verify` para comparar la contraseña enviada con el hash guardado.
        if (!$user || !password_verify($input['contrasena'], $user['contrasena'])) {
            jsonResponse(['error' => 'Credenciales incorrectas'], 401, false); // 401 Unauthorized
            return;
        }

        // 5. Verifica si la cuenta de usuario está activa.
        if ($user['activo'] != 1) {
            jsonResponse(['error' => 'Usuario inactivo, contacte al administrador'], 403, false); // 403 Forbidden
            return;
        }

        // 6. Regenera el ID de la sesión para prevenir ataques de fijación de sesión.
        session_regenerate_id(true);

        // 7. Oculta campos sensibles del array de usuario antes de guardarlo en la sesión.
        foreach ($this->hiddenFields as $field) {
            unset($user[$field]);
        }

        // 8. Guarda la información del usuario en la variable superglobal $_SESSION.
        $_SESSION['usuario'] = $user;
        $_SESSION['usuario']['last_activity'] = time(); // Guarda la hora de la última actividad.

        // 9. Prepara la respuesta JSON, eliminando datos sensibles adicionales.
        unset($user["rol_id"], $user['activo'], $user['id']);
        jsonResponse(['data' => $user, 'message' => 'Login exitoso'], 200);
    }

    /**
     * Devuelve los datos del usuario actualmente autenticado.
     * Corresponde a la ruta GET /private/me
     */
    public function me() {
        // Verifica si existe información de usuario en la sesión.
        if (!isset($_SESSION['usuario'])) {
            jsonResponse(['error' => 'No autenticado'], 401, false);
            return;
        }

        // Prepara y envía los datos del usuario de la sesión.
        $response = $_SESSION['usuario'];
        unset($response["rol_id"], $response['activo'], $response['id']);
        jsonResponse(['data' => $response], 200);
    }

    /**
     * Cierra la sesión del usuario.
     */
    public function logout() {
        if (isset($_SESSION['usuario'])) {
            // Limpia todas las variables de sesión.
            session_unset();
            // Destruye la sesión actual.
            session_destroy();
            jsonResponse(['message' => 'Cierre de sesión exitoso'], 200);
        } else {
            jsonResponse(['error' => 'No hay sesión iniciada'], 400, false);
        }
    }
}
