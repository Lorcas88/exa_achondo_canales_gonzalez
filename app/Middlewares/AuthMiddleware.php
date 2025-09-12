<?php

// Verifica si el usuario está autenticado mirando la sesión
// Si no está autenticado, devuelve una respuesta 401
// Si sí está autenticado, actualiza la actividad y continúa con la ejecución ($next($request)).
class AuthMiddleware implements MiddlewareInterface {
    private $timeout;

    public function __construct(int $timeout) {
        $this->timeout = $timeout;
    }

    public function handle(array $request): void {
        $user = $_SESSION['usuario'] ?? null;
        if (!$user) {
            jsonResponse(['error' => 'Debes iniciar sesión'], 401, false);
        }

        if (isset($user['last_activity']) && (time() - $user['last_activity']) > $this->timeout) {
            session_unset();
            session_destroy();
            jsonResponse(['error' => 'Sesión expirada por inactividad'], 440, false);
        }

        // Actualiza actividad
        $_SESSION['usuario']['last_activity'] = time();
    }
}