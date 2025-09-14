<?php
/**
 * Middleware de Roles
 *
 * Este middleware se encarga de la autorización, es decir, de verificar si un usuario
 * autenticado tiene los permisos necesarios para realizar una acción específica en un recurso.
 *
 * Funciona después del `AuthMiddleware`, por lo que asume que ya hay un usuario en la sesión.
 */
class RoleMiddleware implements MiddlewareInterface {
    /**
     * @var string El nombre del controlador al que se intenta acceder.
     */
    private $controller;
    /**
     * @var string La acción (método) que se intenta ejecutar en el controlador.
     */
    private $action;

    /**
     * Constructor del middleware.
     *
     * @param string $controller El nombre del controlador.
     * @param string $action El nombre de la acción.
     */
    public function __construct(string $controller, string $action) {
        $this->controller = $controller;
        $this->action = $action;
    }

    /**
     * Maneja la lógica de autorización por roles.
     *
     * Comprueba el rol del usuario y lo compara con las reglas de acceso definidas
     * para el controlador y la acción solicitados.
     *
     * @param array $request Datos de la petición.
     * @return void
     */
    public function handle(array $request): void {
        // Obtiene el usuario de la sesión. Se asume que ya ha sido validado por AuthMiddleware.
        $user = $_SESSION['usuario'] ?? null;

        // Doble verificación por si este middleware se usara sin AuthMiddleware antes.
        if (!$user) {
            jsonResponse(['error' => 'Debes iniciar sesión'], 401, false);
            return;
        }

        // El `switch` aplica diferentes lógicas de permisos según el controlador.
        switch ($this->controller) {
            // --- Reglas para el Controlador de Usuarios ---
            case 'UserController':
                // Define las acciones que solo un administrador puede realizar.
                $adminOnlyActions = ['index', 'destroy'];
                // Si la acción es solo para administradores y el rol del usuario no es 1 (Admin)...
                if (in_array($this->action, $adminOnlyActions) && $user['rol_id'] !== 1) {
                    // ...se deniega el acceso con un error 403 (Forbidden).
                    jsonResponse(['error' => 'Acceso denegado. Se requieren privilegios de administrador.'], 403, false);
                }
                break;

            // --- Reglas para Controladores de Datos (Productos, Clientes, etc.) ---
            case 'ClientController':
            case 'ProductController':
            case 'SizeController':
            case 'StockController':
                // Define las acciones de escritura (crear, actualizar, eliminar).
                $writeActions = ['store', 'put', 'destroy'];
                // Si la acción es de escritura y el rol del usuario no es 1 (Admin) ni 2 (Editor)...
                if (in_array($this->action, $writeActions) && !in_array($user['rol_id'], [1, 2])) {
                    // ...se deniega el acceso. Solo Admins y Editores pueden modificar estos datos.
                    jsonResponse(['error' => 'Acceso denegado. Se requieren privilegios de administrador o editor.'], 403, false);
                }
                break;
        }
    }
}