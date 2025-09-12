<?php

// Controla los permisos según el rol del usuario y la tabla y la acción asociada.
// Si el usuario no cumple, devuelve 403 (prohibido).
class RoleMiddleware implements MiddlewareInterface {
    private $controller;
    private $action;
    private $id;

    public function __construct(string $controller, string $action, $id = null) {
        $this->controller = $controller;
        $this->action = $action;
        $this->id = $id;
    }

    public function handle(array $request): void {
        $user = $_SESSION['usuario'] ?? null;

        if (!$user) {
            jsonResponse(['error' => 'Debes iniciar sesión'], 401, false);
            exit;
        }

        switch ($this->controller) {
            case 'UserController':
                $adminOnly = ['index','destroy'];
                if (in_array($this->action, $adminOnly) && $user['rol_id'] !== 1) {
                    jsonResponse(['error' => 'Acceso denegado'], 403, false);
                    exit;
                }

                if ($this->action === 'put' && $user['rol_id'] !== 1) {
                    if ($user['id'] != $this->id) {
                        jsonResponse(['error' => 'No puedes modificar otros usuarios'], 403, false);
                        exit;
                    }
                }
                break;

            case 'CategoryController':
            case 'ProductController':
            case 'StockController':
                $restricted = ['store','put','destroy'];
                if (in_array($this->action, $restricted) && !in_array($user['rol_id'], [1,2])) {
                    jsonResponse(['error' => 'Acceso denegado'], 403, false);
                    exit;
                }
                break;

            case 'HoldController':
                $rules = [
                    'store' => [3],
                    'put' => [1,2],
                    'destroy' => [1]
                ];
                if (isset($rules[$this->action]) && !in_array($user['rol_id'], $rules[$this->action])) {
                    jsonResponse(['error' => 'Acceso denegado'], 403, false);
                    exit;
                }
                break;

            case 'ShipmentController':
                $rules = [
                    'store' => [2],
                    'put' => [1,2],
                    'destroy' => [3]
                ];
                if (isset($rules[$this->action]) && !in_array($user['rol_id'], $rules[$this->action])) {
                    jsonResponse(['error' => 'Acceso denegado'], 403, false);
                    exit;
                }
                break;
        }
    }
}
