<?php
/**
 * Punto de Entrada Principal de la Aplicación
 *
 * Este archivo es el primer script que se ejecuta cuando un usuario accede a la aplicación.
 * Su responsabilidad principal es cargar todos los archivos necesarios para que la aplicación
 * funcione correctamente, incluyendo la configuración, controladores, modelos, servicios,
 * middlewares y el sistema de enrutamiento.
 */

// --- Carga de Archivos de Configuración ---
// Establece la conexión con la base de datos y otras configuraciones globales.
require_once __DIR__ . '/../config/database.php';

// --- Carga de Controladores ---
// Los controladores manejan la lógica de las peticiones entrantes de los usuarios.
require_once __DIR__ . '/../app/Controllers/BaseController.php'; // Controlador base con lógica común.
require_once __DIR__ . '/../app/Controllers/AuthController.php';   // Controlador para autenticación (login, logout).
require_once __DIR__ . '/../app/Controllers/UserController.php';     // Controlador para la gestión de usuarios.
require_once __DIR__ . '/../app/Controllers/ProductController.php';  // Controlador para la gestión de productos.
require_once __DIR__ . '/../app/Controllers/ClientController.php';   // Controlador para la gestión de clientes.
require_once __DIR__ . '/../app/Controllers/SizeController.php';     // Controlador para la gestión de tallas.
require_once __DIR__ . '/../app/Controllers/StockController.php';    // Controlador para la gestión de stock.


// --- Carga de Modelos ---
// Los modelos interactúan directamente con la base de datos para obtener y guardar información.
require_once __DIR__ . '/../app/Models/BaseModel.php'; // Modelo base con lógica de base de datos común.
require_once __DIR__ . '/../app/Models/User.php';      // Modelo para la tabla de usuarios.
require_once __DIR__ . '/../app/Models/Product.php';   // Modelo para la tabla de productos.
require_once __DIR__ . '/../app/Models/Client.php';    // Modelo para la tabla de clientes.
require_once __DIR__ . '/../app/Models/Size.php';      // Modelo para la tabla de tallas.
require_once __DIR__ . '/../app/Models/Stock.php';     // Modelo para la tabla de stock.

// --- Carga de Servicios ---
// Clases de ayuda o con lógica de negocio específica.
require_once __DIR__ . '/../app/Services/Schema.php'; // Servicio para interactuar con el esquema de la DB.

// --- Carga de Middlewares ---
// El middleware actúa como un filtro para las peticiones, ejecutándose antes del controlador.
// Se usa para verificar, por ejemplo, si un usuario está autenticado.
require_once __DIR__ . '/../app/Middlewares/MiddlewareInterface.php'; // Interfaz para los middlewares.
require_once __DIR__ . '/../app/Middlewares/AuthMiddleware.php';      // Middleware para la autenticación de usuarios.
require_once __DIR__ . '/../app/Middlewares/RoleMiddleware.php';      // Middleware para verificar roles de usuario.

// --- Carga de Vistas y Ayudantes ---
// Componentes para generar las respuestas.
require_once __DIR__ . '/../app/Views/jsonResponse.php'; // Función para estandarizar las respuestas JSON.

// --- Carga del Enrutador ---
// El enrutador dirige cada petición al controlador y método correctos.
require_once __DIR__ . '/generateSwagger.php'; // Generador de documentación para la API.
require_once __DIR__ . '/../app/Router/api.php';   // Contiene la lógica de enrutamiento de la API.