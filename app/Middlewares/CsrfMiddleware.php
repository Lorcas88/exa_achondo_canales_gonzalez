<?php

// Protege contra ataques CSRF (Cross-Site Request Forgery).
// Genera un token CSRF y lo guarda en la sesión.
// Lo inserta en los formularios como hidden input.
// Al recibir una petición POST, compara el token recibido con el de la sesión.
// Si no coincide, devuelve 419 o 403.
class CsrfMiddleware implements MiddlewareInterface {
    private array $excludedRoutes;

    public function __construct(array $excludedRoutes = []) {
        $this->excludedRoutes = $excludedRoutes;
    }

    public function handle(array $request): void {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Saltar validación si la ruta está excluida
        foreach ($this->excludedRoutes as $route) {
            if (preg_match($route, $uri)) {
                return;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf'] ?? '';
            if (!isset($_SESSION['_csrf']) || $token !== $_SESSION['_csrf']) {
                http_response_code(419);
                echo json_encode(['error' => 'CSRF token inválido']);
                exit;
            }
        }
    }

    public static function generateToken() {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}