<?php
// require_once __DIR__ . '/BaseController.php';
// require_once __DIR__ . '/../models/UserModel.php';
// require_once __DIR__ . '/../views/jsonResponse.php';

// // Iniciar sesión PHP en cada request
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

class AuthController extends BaseController {
    protected $hiddenFields = ['contrasena'];
    
    public function __construct($conn) {
        $model = new User($conn);
        parent::__construct($model);
    }

    // protected para que el padre lo pueda usar
    protected function validate($data, $isUpdate = false) {
        $errores = [];
        if (!$isUpdate) {
            if (empty($data['nombre'])) $errores['nombre'] = 'El nombre es obligatorio.';
            if (empty($data['contrasena'])) $errores['contrasena'] = 'La contraseña es obligatoria.';
            // if (empty($data['rol_id'])) $errores['rol_id'] = 'El rol es obligatorio.';
        }
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Email inválido.';
        }
        // Agregar que si el rol del usuario con sesión iniciada, no es admin, no pueda cambiar rol
        if (isset($data['rol_id'])) {
            $validos = [1, 2, 3];
            if (!in_array($data['rol_id'], $validos)) {
                $errores['rol_id'] = 'Rol inválido.';
            }
        }
        if (isset($data['activo'])) {
            $validos = [0,1];
            if (!in_array($data['activo'], $validos)) {
                $errores['activo'] = 'Estado inválido.';
            }
        }
        return $errores;
    }

    // Login: email y contraseña, retorna rol si es correcto
    public function login() 
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) jsonResponse(['error' => 'JSON inválido'], 400, false);

        // Requerir email y contraseña
        if (empty($input['email']) || empty($input['contrasena'])) {
            jsonResponse(['error' => 'Email y contraseña son obligatorios'], 422, false);
        }

        // Si ya hay sesión activa para este email
        if (isset($_SESSION['usuario']) && $input['email'] === $_SESSION['usuario']['email']) {
            jsonResponse(['data' => null, 'message' => 'La sesión ya está abierta'], 200);
        }
        
        // Buscar usuario por email
        $usuario = $this->model->findBy('email', $input['email']);
        if (!$usuario || !password_verify($input['contrasena'], $usuario['contrasena'])) {
        // if (!$usuario || !($input['contrasena'] === $usuario['contrasena'])) {
            jsonResponse(['error' => 'Credenciales incorrectas'], 401, false);
        }

        if ($usuario['activo'] != 1) {
            jsonResponse(['error' => 'Usuario inactivo, contacte al administrador'], 403, false);
        }

        // Regenerar ID de sesión para seguridad
        session_regenerate_id(true);

        // Filtrar campos ocultos
        foreach ($this->hiddenFields as $field) {
            unset($usuario[$field]);
        }

        // Guardar usuario en sesión
        $_SESSION['usuario'] = $usuario;
        $_SESSION['usuario']['last_activity'] = time();

        // session_create_id();
        unset($usuario["rol_id"], $usuario['activo'], $usuario['id']); // No enviar ciertos datos a la respuesta
        jsonResponse(['data' => $usuario, 'message' => 'Login exitoso'], 200);
    }

    // Devuelve los datos del usuario autenticado
    public function me() {
        // error_log(session_id());
        // error_log(session_status());
        if (!isset($_SESSION['usuario'])) {
            jsonResponse(['error' => 'No autenticado'], 401, false);
        }

        $response = $_SESSION['usuario'];
        unset($response["rol_id"], $response['activo'], $response['id']); // No enviar ciertos datos a la respuesta
        jsonResponse(['data' => $response], 200);
    }

    // Logout simulado (no hay sesión real, solo respuesta estándar)
    public function logout() {
        if (isset($_SESSION['usuario'])) {
            unset($_SESSION['usuario']);
            session_destroy();
            jsonResponse(['message' => 'Cierre de sesión exitoso'], 200);
        } else {
            jsonResponse(['error' => 'No hay sesión iniciada'], 400, false);
        }
    }
}