<?php
// require_once __DIR__ . '/BaseController.php';
// require_once __DIR__ . '/../models/UserModel.php';
// require_once __DIR__ . '/../views/jsonResponse.php';

// // Iniciar sesión PHP en cada request
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

class UserController extends BaseController {
    protected $hiddenFields = ['contrasena', 'rol_id'];
    
    public function __construct($conn) {
        $model = new User($conn);
        parent::__construct($model);
    }

    // protected para que el padre lo pueda usar
    protected function validate($data, $isUpdate = false) {
        $errores = [];
        if (!$isUpdate) {
            if (empty($data['nombre'])) $errores['nombre'] = 'El nombre es obligatorio.';
            if (empty($data['apellido'])) $errores['apellido'] = 'El apellido es obligatorio.';
            if (empty($data['email'])) $errores['email'] = 'El email es obligatorio.';
            if (empty($data['contrasena'])) $errores['contrasena'] = 'La contraseña es obligatoria.';
            if (empty($data['rol_id'])) $errores['rol_id'] = 'El rol es obligatorio.';
        }
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'Email inválido.';
        }
        
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
        if (isset($data['fecha_nacimiento']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['fecha_nacimiento'])) {
            $errores['fecha_nacimiento'] = 'Formato de fecha inválido (YYYY-MM-DD).';
        }
        return $errores;
    }

    public function unsubscribe() {
        $userId = $_SESSION['usuario']['id'];

        $this->model->updateField($userId, ['activo' => 0]);

        // Destruir sesión que fue dada de baja
        session_unset();
        session_destroy();
        
        jsonResponse(['message' => 'Usuario dado de baja satisfactoriamente'], 200);
    }

}