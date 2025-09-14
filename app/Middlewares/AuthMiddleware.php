<?php
/**
 * Middleware de Autenticación
 *
 * Este middleware se encarga de proteger las rutas de la aplicación, asegurando que
 * solo los usuarios autenticados puedan acceder a ellas.
 *
 * Implementa la interfaz `MiddlewareInterface`.
 */
class AuthMiddleware implements MiddlewareInterface {
    /**
     * @var int El tiempo máximo de inactividad en segundos antes de que la sesión expire.
     */
    private $timeout;

    /**
     * Constructor del middleware.
     *
     * @param int $timeout El tiempo de inactividad en segundos.
     */
    public function __construct(int $timeout) {
        $this->timeout = $timeout;
    }

    /**
     * Maneja la lógica del middleware.
     *
     * Verifica si hay un usuario en la sesión y si la sesión ha expirado por inactividad.
     * Si la autenticación falla, detiene la ejecución y envía una respuesta de error.
     *
     * @param array $request Datos de la petición (no se usa en este middleware, pero es parte de la interfaz).
     * @return void
     */
    public function handle(array $request): void {
        // 1. Verifica si existe la variable 'usuario' en la sesión.
        $user = $_SESSION['usuario'] ?? null;
        if (!$user) {
            // Si no hay usuario, envía una respuesta 401 (Unauthorized) y detiene la ejecución.
            jsonResponse(['error' => 'Debes iniciar sesión para acceder a este recurso'], 401, false);
        }

        // 2. Verifica si la sesión ha expirado por inactividad.
        if (isset($user['last_activity']) && (time() - $user['last_activity']) > $this->timeout) {
            // Si ha pasado más tiempo que el definido en $timeout desde la última actividad...
            session_unset();   // Limpia las variables de sesión.
            session_destroy(); // Destruye la sesión.
            // Envía una respuesta 440 (Login Time-out), un código no estándar pero descriptivo.
            jsonResponse(['error' => 'Sesión expirada por inactividad'], 440, false);
        }

        // 3. Si el usuario está autenticado y la sesión está activa, actualiza la marca de tiempo de la última actividad.
        // Esto reinicia el contador de inactividad en cada petición.
        $_SESSION['usuario']['last_activity'] = time();
    }
}
