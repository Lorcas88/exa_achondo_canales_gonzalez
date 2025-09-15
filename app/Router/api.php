<?php
/**
 * Sistema de Enrutamiento de la API
 *
 * Este archivo es el núcleo del enrutamiento de la aplicación. Se encarga de:
 * 1. Inicializar la base de datos y la sesión.
 * 2. Definir todas las rutas de la API, tanto públicas como protegidas.
 * 3. Procesar la URI de la petición entrante.
 * 4. Ejecutar middlewares de seguridad (autenticación, roles).
 * 5. Dirigir la petición al controlador y método adecuados.
 * 6. Manejar rutas no encontradas (404).
 */

// --- Inicialización ---

// Crea una instancia de la clase Database y obtiene la conexión.
$db = new Database();
$conn = $db->getConnection();

// --- Configuración de la Sesión ---

// Define el tiempo de vida de la sesión en segundos (30 minutos).
$timeout = 1800; 
// Configura los parámetros de las cookies de sesión para mayor seguridad.
session_set_cookie_params([
    'lifetime' => $timeout,    // Tiempo de vida de la cookie.
    'path' => '/',             // Disponible en todo el dominio.
    'domain' => 'localhost',   // Dominio específico.
    'secure' => false,         // Debe ser 'true' en un entorno de producción con HTTPS.
    'httponly' => true,        // La cookie solo es accesible a través del protocolo HTTP, no por scripts de cliente.
    'samesite' => 'Strict'     // Ayuda a prevenir ataques CSRF.
]);
// Inicia la sesión. Si ya hay una sesión, PHP la reutiliza.
session_start();

// --- Definición de Prefijos de Ruta ---

// Define constantes para los prefijos de las rutas para facilitar su mantenimiento.
define('PUBLIC_PREFIX',    '/public');    // Rutas que no requieren autenticación.
define('PROTECTED_PREFIX', '/private');   // Rutas que requieren que el usuario esté autenticado.

// --- Procesamiento de la Petición ---

// Obtiene el método HTTP de la petición (GET, POST, PUT, DELETE, etc.).
$method = $_SERVER['REQUEST_METHOD'];
// Parsea la URI para obtener solo la ruta, ignorando query strings.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Limpia la URI base del proyecto para que las rutas coincidan correctamente.
$uri = substr($uri, strlen('/todocamisetas_server')) ?: '/';

// --- Definición de Rutas ---

/**
 * Define los recursos que seguirán el patrón RESTful estándar.
 * El sistema generará automáticamente las rutas CRUD (index, show, store, put, destroy)
 * para cada uno de estos recursos.
 * Formato: 'nombre-del-recurso' => 'NombreDelControlador'
 */
$resources = [
    'usuario'   => 'UserController',
    'producto'  => 'ProductController',
    'cliente'   => 'ClientController',
    'talla'     => 'SizeController',
];

/**
 * Define rutas personalizadas que no siguen el patrón RESTful estándar.
 * Se agrupan por método HTTP y usan expresiones regulares para definir el patrón de la URL.
 * Formato: 'METODO' => ['#^patron-regex$#' => ['Controlador', 'metodo']]
 */
$extraRoutes = [
    'POST' => [
        '#^' . PUBLIC_PREFIX . '/register/?$#' => ['UserController', 'store'],
        '#^' . PUBLIC_PREFIX . '/login/?$#'    => ['AuthController', 'login'],
        '#^' . PROTECTED_PREFIX . '/logout/?$#'   => ['AuthController', 'logout'],
        '#^' . PROTECTED_PREFIX . '/producto/([0-9]+)/stock/?$#' => ['StockController', 'store'],
    ],
    'GET' => [
        '#^' . PROTECTED_PREFIX . '/me/?$#' => ['AuthController', 'me'],
        '#^' . PROTECTED_PREFIX . '/producto/([0-9]+)/stock/?$#' => ['StockController', 'index'],
        '#^' . PROTECTED_PREFIX . '/producto/([0-9]+)/stock/([0-9]+)/?$#' => ['StockController', 'show'],
        '#^' . PROTECTED_PREFIX . '/cliente/descuentos/?$#' => ['ClientController', 'discountClients'],
    ],
    'PATCH' => [
        '#^' . PROTECTED_PREFIX . '/usuario/me/unsubscribe/?$#' => ['UserController', 'unsubscribe'],
        '#^' . PROTECTED_PREFIX . '/producto/([0-9]+)/stock/([0-9]+)/?$#' => ['StockController', 'put'],
    ],
    'DELETE' => [
        '#^' . PROTECTED_PREFIX . '/producto/([0-9]+)/stock/([0-9]+)/?$#' => ['StockController', 'destroy'],
    ],
];

// --- Construcción del Array Final de Rutas ---

// Combina las rutas personalizadas con las rutas RESTful generadas automáticamente.
$routes = $extraRoutes;
foreach ($resources as $resource => $controller) {
    $p = PROTECTED_PREFIX . '/' . $resource; // ej. /private/usuario
    $routes['GET']["#^$p/?$#"]           = [$controller, 'index'];   // Listar todos: GET /private/usuario
    $routes['GET']["#^$p/([0-9]+)$#"]    = [$controller, 'show'];    // Ver detalle:  GET /private/usuario/1
    $routes['POST']["#^$p/?$#"]          = [$controller, 'store'];   // Crear:        POST /private/usuario
    $routes['PUT']["#^$p/([0-9]+)$#"]    = [$controller, 'put'];     // Actualizar:   PUT /private/usuario/1
    $routes['DELETE']["#^$p/([0-9]+)$#"] = [$controller, 'destroy']; // Eliminar:     DELETE /private/usuario/1
}

// --- Bucle Principal de Enrutamiento ---

$found = false; // Bandera para saber si se encontró una ruta.
// Itera sobre las rutas definidas para el método HTTP actual.
foreach ($routes[$method] as $pattern => $handler) {
    // Comprueba si la URI actual coincide con el patrón de la ruta.
    if (preg_match($pattern, $uri, $matches)) {
        // Desempaqueta el manejador en el nombre del controlador y la acción.
        [$controllerName, $action] = $handler;
        // Crea una instancia del controlador, pasándole la conexión a la BD.
        $controller = new $controllerName($conn);
        
        // Captura los parámetros de la URL (ej. el ID de un recurso).
        $params = array_slice($matches, 1);

        // --- Pipeline de Middlewares ---
        // Define los middlewares que se ejecutarán antes de llegar al controlador.
        $request = []; // Array para pasar información entre middlewares si es necesario.
        $globalMiddlewares = [
            'protected' => [
                new AuthMiddleware($timeout),       // Verifica si el usuario está autenticado.
                new RoleMiddleware($controllerName, $action), // Verifica si el usuario tiene el rol adecuado.
            ],
            'public' => [] // Las rutas públicas no tienen middlewares globales.
        ];

        // Determina si la ruta es pública o protegida.
        $isPublic = (strpos($uri, PUBLIC_PREFIX) === 0 || $uri === '/api-docs.json');
        $middlewares = $isPublic ? $globalMiddlewares['public'] : $globalMiddlewares['protected'];

        // Ejecuta cada middleware en secuencia.
        // Si un middleware falla (ej. autenticación incorrecta), detendrá la ejecución
        // y enviará una respuesta de error.
        foreach ($middlewares as $middleware) {
            $middleware->handle($request);
        }
        
        // --- Ejecución del Controlador ---
        // Si todos los middlewares pasan, se llama al método (acción) del controlador.
        // Se usan los 'splat operator' (...) para pasar los parámetros de la URL como argumentos al método.
        $controller->$action(...$params);
        
        $found = true; // Marca que se encontró y manejó la ruta.
        break; // Termina el bucle ya que no es necesario seguir buscando.
    }
}

// --- Manejo de Rutas No Encontradas y Documentación ---

// Si la URI es para la documentación de Swagger, la genera y la muestra.
if ($uri === '/api-docs.json') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    $swagger = generateSwagger($routes, $conn);
    echo json_encode($swagger, JSON_PRETTY_PRINT);
    exit;
}

// Si después de recorrer todas las rutas no se encontró ninguna coincidencia,
// se devuelve una respuesta de error 404.
if (!$found) {
    jsonResponse(['error' => 'Endpoint no encontrado'], 404, false);
}
