<?php
// require_once __DIR__ . '/../models/BaseModel.php';
// require_once __DIR__ . '/Schema.php';

class User extends Base {
    protected $table = "usuario";
    // protected $requiredFields = ['nombre', 'apellido', 'email', 'contrasena', 'fecha_nacimiento', 'telefono', 'direccion', 'rol'];
    // protected $updatableFields = ['nombre', 'apellido', 'email', 'contrasena', 'fecha_nacimiento', 'telefono', 'direccion', 'estado'];

    public function __construct($conn) {
        parent::__construct($conn);
    }

    public function extraColumns(): array {
        if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol_id'] === 1) {
            return ['email', 'rol_id', 'activo'];
        }
        return [];
    }

    // Sobrescribir para cifrar la contraseña
    public function create($data) {
        if (isset($data['contrasena'])) {
            // Hashear la contraseña si el campo es 'contrasena'
            $data['contrasena'] = password_hash($data['contrasena'], PASSWORD_DEFAULT);
        }
        return parent::create($data);
    }
    
    public function update($id, $data) {
        if (isset($data['contrasena'])) {
            // Hashear la contraseña si el campo es 'contrasena'
            $data['contrasena'] = password_hash($data['contrasena'], PASSWORD_DEFAULT);
        }
        return parent::update($id, $data);
    }
}