<?php
/**
 * Modelo para la tabla 'usuario'
 *
 * Esta clase representa la tabla de usuarios en la base de datos.
 * Extiende la clase 'Base' para heredar la funcionalidad CRUD, pero sobrescribe
 * los métodos `create` y `update` para manejar de forma segura el hash de las contraseñas.
 */
class User extends Base {
    /**
     * @var string El nombre de la tabla en la base de datos.
     */
    protected $table = "usuario";

    /**
     * Constructor del modelo User.
     *
     * @param PDO $conn La conexión a la base de datos.
     */
    public function __construct($conn) {
        parent::__construct($conn);
    }

    /**
     * Define columnas adicionales que un administrador puede actualizar.
     * Sobrescribe un método de la clase Base.
     *
     * @return array Un array de nombres de columnas.
     */
    public function extraColumns(): array {
        // Si el usuario en sesión es un administrador (rol_id = 1),
        // se le permite modificar estas columnas adicionales en otros usuarios.
        if (isset($_SESSION['usuario']) && $_SESSION['usuario']['rol_id'] === 1) {
            return ['email', 'rol_id', 'activo'];
        }
        // Para otros roles, no se permiten columnas extra.
        return [];
    }

    /**
     * Crea un nuevo usuario, hasheando la contraseña antes de guardarla.
     * Sobrescribe el método `create` de la clase Base.
     *
     * @param array $data Los datos del usuario a crear.
     * @return mixed El ID del nuevo usuario o false si falla.
     */
    public function create($data) {
        // Verifica si se proporcionó una contraseña en los datos.
        if (isset($data['contrasena'])) {
            // Hashea la contraseña utilizando el algoritmo por defecto de PHP (actualmente bcrypt).
            // Esto es una medida de seguridad crucial para no guardar contraseñas en texto plano.
            $data['contrasena'] = password_hash($data['contrasena'], PASSWORD_DEFAULT);
        }
        // Llama al método `create` del padre (Base) para realizar la inserción en la base de datos.
        return parent::create($data);
    }
    
    /**
     * Actualiza un usuario, hasheando la contraseña si se proporciona una nueva.
     * Sobrescribe el método `update` de la clase Base.
     *
     * @param int $id El ID del usuario a actualizar.
     * @param array $data Los nuevos datos del usuario.
     * @return bool True si la actualización fue exitosa, false si no.
     */
    public function update($id, $data) {
        // Al igual que en `create`, si se está actualizando la contraseña, se hashea.
        if (isset($data['contrasena'])) {
            $data['contrasena'] = password_hash($data['contrasena'], PASSWORD_DEFAULT);
        }
        // Llama al método `update` del padre para realizar la actualización.
        return parent::update($id, $data);
    }
}
