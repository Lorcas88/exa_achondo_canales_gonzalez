<?php

// Inicializar la base de datos
$db = new Database();
$conn = $db->getConnection();

// Configuración sesión con cookies
$timeout = 1800; // Variable para timeout de sesión en segundos (30 minutos)
session_set_cookie_params([
    'lifetime' => $timeout,
    'path' => '/',
    'domain' => 'localhost',
    'secure' => false, // true en producción con HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start(); // PHP ignora session_start() si ya hay una sesión iniciada.

// Configuración de rutas
define('PUBLIC_PREFIX',    '/public');    // Rutas públicas
define('PROTECTED_PREFIX', '/private');     // Rutas que necesitan login

// Este archivo enruta las peticiones entrantes a los controladores correspondientes.
// Todas las respuestas usan jsonResponse() para mantener formato uniforme.
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
//Quita los slashes al inicio y final de la URL y divide en partes que guarda en un Array
$segments = explode('/', trim($uri, '/'));

// session_unset();     // Limpiar variables de sesión
// session_destroy();   // Destruir sesión
// error_log(print_r($_SESSION, true));

// $esPublica = (strpos($uri, PUBLIC_PREFIX) === 0 || $uri === '/api-docs.json');
// if (!$esPublica && !isset($_SESSION['usuario'])) {
//     jsonResponse(['error' => 'Debe estar autenticado para acceder a este recurso'], 401, false);
//     exit;
// }

// if ($segments[0] !== 'auth' && (!isset($_SESSION['usuario']))) {
//     jsonResponse(['error' => 'Debe estar autenticado para acceder a este recurso'], 401, false);
// }

// Rutas RESTfull clásico
$resources = [
    'usuario' => 'UserController',
    'producto'  => 'ProductController',
];

// Rutas adicionales que no siguen el esquema RESTful clásico
$extraRoutes = [
    'POST' => [
        '#^' . PUBLIC_PREFIX . '/register/?$#' => ['UserController', 'store'],
        '#^' . PUBLIC_PREFIX . '/login/?$#'    => ['AuthController', 'login'],
        '#^' . PROTECTED_PREFIX . '/logout/?$#'   => ['AuthController', 'logout'],
    ],
    'GET' => [
        '#^' . PROTECTED_PREFIX . '/me/?$#' => ['AuthController', 'me'],
        // '#^' . PROTECTED_PREFIX . '/stock/out/?$#' => ['StockController', 'getOutOfStock'],
    ],
    'PATCH' => [
        '#^' . PROTECTED_PREFIX . '/usuarios/me/unsubscribe/?$#' => ['UserController', 'unsubscribe'],
        // '#^' . PROTECTED_PREFIX . '/reserva/([0-9]+)/estado/?$#' => ['HoldController', 'updateEstado'],
        // '#^' . PROTECTED_PREFIX . '/envio/([0-9]+)/estado/?$#' => ['ShipmentController', 'updateEstado'],
    ],
];

// Construcción RESTful
// Cada recurso tendrá URLs bajo PROTECTED_PREFIX
// Para que solo usuarios autenticados puedan realizar CRUD
$routes = $extraRoutes;
foreach ($resources as $resource => $controller) {
    $p = PROTECTED_PREFIX . '/' . $resource; // /api/usuarios
    $routes['GET']["#^$p/?$#"]             = [$controller, 'index'];   // listar todos
    $routes['GET']["#^$p/([0-9]+)$#"]      = [$controller, 'show'];    // detalle
    $routes['POST']["#^$p/?$#"]            = [$controller, 'store'];   // crear
    $routes['PUT']["#^$p/([0-9]+)$#"]      = [$controller, 'put'];     // actualizar
    $routes['DELETE']["#^$p/([0-9]+)$#"]   = [$controller, 'destroy']; // eliminar
}

// Buscar la ruta que coincide con el método y URI
$found = false;
foreach ($routes[$method] as $pattern => $handler) {
    // Si la URL coincide con el patrón de la ruta
    if (preg_match($pattern, $uri, $matches)) {
        [$controllerName, $action] = $handler; // Desempaqueta el array handler en dos variables
        $controller = new $controllerName($conn);
        $id = $matches[1] ?? null; // Si la URL incluía un ID capturado, lo pasamos

        // Pipeline: auth -> role -> controller
        $request = []; // Puedes pasar info al middleware
        $publicPaths = [PUBLIC_PREFIX, '/api-docs.json'];
        $globalMiddlewares = [
            'protected' => [
                new AuthMiddleware($timeout),
                new RoleMiddleware($controllerName, $action, $id),
                // new CsrfMiddleware([
                //     '#^' . PUBLIC_PREFIX . '/login/?$#',
                //     '#^' . PROTECTED_PREFIX . '/logout/?$#'
                // ])
            ],
            'public' => [] // Si la ruta es pública, los middleware no se ejecutarán
        ];

        $esPublica = (strpos($uri, PUBLIC_PREFIX) === 0 || $uri === '/api-docs.json');
        $middlewares = $esPublica ? $globalMiddlewares['public'] : $globalMiddlewares['protected'];

        // Ejecutar middlewares uno por uno
        // Las clases Middleware contienen funciones sin retorno (void)
        // Por lo que se ejecutará todo el código, a no ser que sea verdad
        // una condición con jsonResponse (la cual tiene su propio exit)
        // esto en un futuro se cambiará a funciones con callback
        foreach ($middlewares as $middleware) {
            $middleware->handle($request);
        }

        // Si todos pasaron, ejecutamos el controlador
        $controller->$action($id);
        
        $found = true;
        break;
    }
}

// Swagger y 404
if ($uri === '/api-docs.json') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: http://localhost:8000'); // o '*'
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    $swagger = generateSwagger($routes, $conn);
    echo json_encode($swagger, JSON_PRETTY_PRINT);
    exit;
}

if (!$found) {
    // Si la ruta no coincide con ninguna anterior, responder 404 uniforme
    jsonResponse(['error' => 'Endpoint no encontrado'], 404, false);
}