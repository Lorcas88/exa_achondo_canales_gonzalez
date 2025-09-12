<?php
// --------------------------------------------------
//  Front‑Controller
// --------------------------------------------------

// // Utilidades simples para servir Swagger UI estático o una página /docs
// $__uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
// if ($__uri === '/docs') {
//     header('Content-Type: text/html; charset=utf-8');
//     echo '<!doctype html><html><head><meta charset="utf-8"><title>API Docs</title></head><body style="margin:0">'
//        . '<iframe src="/swagger-ui/index.html" style="border:0;width:100%;height:100vh"></iframe>'
//        . '</body></html>';
//     exit;
// }
// if (strpos($__uri, '/swagger-ui') === 0) {
//     $path = __DIR__ . $__uri;
//     if (is_dir($path)) {
//         $index = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
//         if (file_exists($index)) {
//             header('Content-Type: text/html; charset=utf-8');
//             readfile($index);
//             exit;
//         }
//     } elseif (file_exists($path)) {
//         $ext = pathinfo($path, PATHINFO_EXTENSION);
//         $mime = [
//             'html' => 'text/html; charset=utf-8',
//             'css' => 'text/css',
//             'js' => 'application/javascript',
//             'svg' => 'image/svg+xml',
//             'png' => 'image/png',
//             'json' => 'application/json',
//         ][$ext] ?? 'application/octet-stream';
//         header('Content-Type: ' . $mime);
//         readfile($path);
//         exit;
//     }
// }

// Cargar configuraciones
require_once __DIR__ . '/../config/database.php';

// Registrar clases manualmente
// require_once __DIR__ . '/../app/Http/Request.php';
// require_once __DIR__ . '/../app/Http/Response.php';
// require_once __DIR__ . '/../app/Http/Kernel.php';

// require_once __DIR__ . '/../app/Router/Router.php';
require_once __DIR__ . '/../app/Controllers/BaseController.php';
require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/UserController.php';
require_once __DIR__ . '/../app/Controllers/ProductController.php';

require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/User.php';
require_once __DIR__ . '/../app/Models/Product.php';

// require_once __DIR__ . '/../app/Services/AuthService.php';
// require_once __DIR__ . '/../app/Services/RoleService.php';
require_once __DIR__ . '/../app/Services/Schema.php';

require_once __DIR__ . '/../app/Middlewares/MiddlewareInterface.php';
require_once __DIR__ . '/../app/Middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../app/Middlewares/RoleMiddleware.php';
require_once __DIR__ . '/../app/Middlewares/CsrfMiddleware.php';

require_once __DIR__ . '/../app/Views/jsonResponse.php';

// require_once __DIR__ . '/../app/Routes.php';   // $routes array

require_once __DIR__ . '/generateSwagger.php';
require_once __DIR__ . '/../app/Router/api.php';