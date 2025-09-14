<?php
function generateSwagger($routes, PDO $pdo) {
    $swagger = [
        "openapi" => "3.0.3",
        "info" => [
            "title" => "API TodoCamisetas",
            "version" => "1.0.0",
            "description" => "Documentación generada automáticamente desde el router para Examen - IPSS.\n\nEsquemas inferidos dinámicamente desde la base de datos.\n\nNOTA: Existen rutas protegidas bajo el prefijo /api que requieren autenticación de sesión. Primero use /auth/register o /auth/login para obtener acceso; luego pruebe los endpoints protegidos."
        ],
        "servers" => [["url" => "http://localhost/todocamisetas_server/"]],
        "paths" => [],
        "tags" => [],
        "components" => ["schemas" => []]
    ];

    // Auxiliares locales
    function regexToSwaggerPath($pattern) {
        // Normaliza patrones del router como #^/api/usuarios/?$# a /api/usuarios
        $url = $pattern;
        $url = preg_replace('/^#\\^?/', '', $url);      // quita inicio #^
        $url = preg_replace('/\\$#$/', '', $url);       // quita fin $#
        $url = preg_replace('/\\(\\[0-9\\]\\+\\)/', '{id}', $url); // id
        $url = preg_replace('/\/\?$/', '', $url);       // quita /? final
        $url = preg_replace('/#$/', '', $url);            // quita # final residual
        $url = preg_replace('/\\^|\\$/', '', $url);   // quita ^ o $ sueltos si quedaran
        return $url;
    }

    function generateParameters($url) {
        $params = [];
        if (strpos($url, '{id}') !== false) {
            $params[] = [
                "name" => "id",
                "in" => "path",
                "required" => true,
                "schema" => ["type" => "integer"],
                "description" => "ID del recurso"
            ];
        }
        // Parámetros de paginación para listados (cuando no hay {id})
        if (strpos($url, '{id}') === false) {
            $params = array_merge($params, [
                ["name" => "page", "in" => "query", "schema" => ["type" => "integer", "default" => 1], "description" => "Número de página"],
                ["name" => "limit", "in" => "query", "schema" => ["type" => "integer", "default" => 10], "description" => "Tamaño de página"],
                ["name" => "sort", "in" => "query", "schema" => ["type" => "string"], "description" => "Campo para ordenar"]
            ]);
        }
        return $params;
    }

    // Servicio de esquemas dinámicos desde la BD
    $schemaService = new SchemeService($pdo);
    $entitySchemas = [];
    $entityExamples = [];
    $postExamples = [];
    $putExamples = [];
    // Mapeo controlador -> tabla
    $controllerToTable = [
        'UserController' => 'usuario',
        'ProductController' => 'producto',
        'StockController' => 'producto_talla_stock',
        'ClientController' => 'cliente',
    ];
    
    $tagSet = [];
    $tagDescriptions = [
        'AuthController' => 'Autenticación: registro, inicio y cierre de sesión. Endpoints públicos en /auth, protegidos en /api.',
        'UserController' => 'Gestión de usuarios. La mayoría de endpoints son modificables solo por el administrador (rol_id=1) con excepción de register, el cual es de acceso público.',
        'ProductController' => 'Gestión de productos del catálogo. PUT y POST son solo para el administrador (rol_id=1).',
        'StockController' => 'Gestión de stock e inventario. PUT y POST son solo para el administrador (rol_id=1).',
    ];
    
    // Detectar columnas ENUM para excluir de PUT
    $enumColumns = [];
    foreach ($controllerToTable as $controller => $table) {
        try {
            $enumColumns[$table] = $schemaService->getEnumColumns($table);
        } catch (Throwable $e) {
            $enumColumns[$table] = [];
        }
    }
    
    foreach ($routes as $method => $methodRoutes) {
        foreach ($methodRoutes as $pattern => $handler) {
            [$controller, $action] = $handler;
            $path = regexToSwaggerPath($pattern);
            $tagSet[$controller] = true;

            // Resolver entidad y construir schema dinámico si aplica
            $entity = null;
            if (isset($controllerToTable[$controller])) {
                $table = $controllerToTable[$controller];
                $entity = $table;
                if (!isset($entitySchemas[$entity])) {
                    try {
                        $schema = $schemaService->getSwaggerSchema($table);
                        $entitySchemas[$entity] = $schema;
                        $swagger['components']['schemas'][$entity] = $schema;
                        // construir ejemplo para la entidad
                        $example = [];
                        $props = $schema['properties'] ?? [];
                        foreach ($props as $propName => $propSpec) {
                            if (array_key_exists('example', $propSpec)) {
                                $example[$propName] = $propSpec['example'];
                            } else {
                                $ptype = $propSpec['type'] ?? 'string';
                                $example[$propName] = $ptype === 'integer' ? 1 : ($ptype === 'number' ? 1.0 : ($ptype === 'boolean' ? true : $propName . '_ejemplo'));
                            }
                        }
                        $entityExamples[$entity] = $example;
                        // ejemplos filtrados para escritura
                        try {
                            $fillable = $schemaService->fillableColumns($table);
                            $updatable = $schemaService->updatableColumns($table);
                            $enumCols = $enumColumns[$table] ?? [];
                            
                            // POST: solo columnas fillable
                            $postEx = [];
                            foreach ($fillable as $colName) {
                                if (array_key_exists($colName, $example)) {
                                    $postEx[$colName] = $example[$colName];
                                }
                            }
                            
                            // PUT: solo columnas updatable EXCEPTO ENUM
                            $putEx = [];
                            foreach ($updatable as $colName) {
                                if (!array_key_exists($colName, $enumCols) && array_key_exists($colName, $example)) {
                                    $putEx[$colName] = $example[$colName];
                                }
                            }
                            
                            $postExamples[$entity] = $postEx;
                            $putExamples[$entity] = $putEx;

                            // Casos especiales para ejemplos
                            if ($entity === 'cliente' && isset($postExamples[$entity]['rut'])) {
                                $postExamples[$entity]['rut'] = '99999999-9';
                            }
                            if ($entity === 'cliente' && isset($postExamples[$entity]['contacto_email'])) {
                                $postExamples[$entity]['contacto_email'] = 'test@dominio.cl';
                            }
                            if ($entity === 'usuario' && isset($postExamples[$entity]['email'])) {
                                $postExamples[$entity]['email'] = 'test@dominio.cl';
                            }
                            if ($entity === 'usuario' && isset($postExamples[$entity]['fecha_nacimiento'])) {
                                $postExamples[$entity]['fecha_nacimiento'] = '2000-10-01';
                            }
                        } catch (Throwable $e) {
                            // si falla, se mantiene ejemplo completo
                        }
                    } catch (Throwable $e) {
                        // Si no puede inferir, omite el schema para no romper la generación
                    }
                }
            }

            // requestBody segun método
            $requestBody = null;
            if (in_array($method, ['POST','PUT','PATCH'])) {
                $isAuthLogin = ($controller === 'AuthController' && $action === 'login');
                $isUnsiscribe = ($controller === 'UserController' && $action === 'unsubscribe');
                // $isEnumPatch = ($method === 'PATCH' && in_array($action, ['updateEstado']));
                
                if ($isAuthLogin) {
                    $requestBody = [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "examples" => [
                                    "admin" => [
                                        "summary" => "Administrador (rol_id=1)",
                                        "value" => ["email" => "carlos@example.com", "contrasena" => "123456"]
                                    ],
                                    "empleado" => [
                                        "summary" => "Empleado (rol_id=2)",
                                        "value" => ["email" => "ana@example.com", "contrasena" => "123456"]
                                    ],
                                    "cliente" => [
                                        "summary" => "Cliente (rol_id=3)",
                                        "value" => ["email" => "juan@example.com", "contrasena" => "123456"]
                                    ]
                                ]
                            ]
                        ]
                    ];
                } elseif ($isUnsiscribe) {
                    $table = $controllerToTable[$controller] ?? '';
                    $enumCols = $enumColumns[$table] ?? [];
                    
                    $requestBody = [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "example" => []
                            ]
                        ]
                    ];
                } else {
                    $requestBody = [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "example" => ($entity && $method === 'POST' && isset($postExamples[$entity]))
                                    ? $postExamples[$entity]
                                    : (($entity && in_array($method, ['PUT','PATCH']) && isset($putExamples[$entity]))
                                        ? $putExamples[$entity]
                                        : ($entity && isset($entityExamples[$entity]) ? $entityExamples[$entity] : new stdClass()))
                            ]
                        ]
                    ];
                }
            }

            $commonHeaders = [
                "X-RateLimit-Limit" => [
                    "schema" => ["type" => "integer"],
                    "description" => "Límite de peticiones por hora",
                    "example" => 100
                ],
                "X-RateLimit-Remaining" => [
                    "schema" => ["type" => "integer"],
                    "description" => "Número de peticiones restantes en la hora actual",
                    "example" => 95
                ],
                "X-RateLimit-Reset" => [
                    "schema" => ["type" => "integer"],
                    "description" => "Timestamp Unix en el que el límite de peticiones se reiniciará",
                    "example" => 1653571200
                ],
                "Cache-Control" => [
                    "schema" => ["type" => "string"],
                    "description" => "Directivas de control de caché",
                    "example" => "public, max-age=3600"
                ],
                "ETag" => [
                    "schema" => ["type" => "string"],
                    "description" => "Etiqueta de entidad para la validación de caché",
                    "example" => 'W/"abcdef12345"'
                ]
            ];

            // Operación básica
            $operation = [
                "tags" => [$controller],
                "summary" => "$action en $controller",
                "parameters" => generateParameters($path),
                "requestBody" => $requestBody,
                "responses" => [
                    "200" => ($controller === 'AuthController' && $action === 'login')
                        ? [
                            "description" => "Operación exitosa",
                            "headers" => $commonHeaders,
                        ]
                        : [
                            "description" => "Operación exitosa",
                            "headers" => $commonHeaders,
                        ],
                    "201" => ["description" => "Creado"],
                    "204" => ["description" => "Sin contenido"],
                    "400" => ["description" => "Solicitud inválida"],
                    "401" => ["description" => "No autorizado"],
                    "403" => ["description" => "Acceso denegado"],
                    "404" => ["description" => "Recurso no encontrado"],
                    "409" => ["description" => "Conflicto"],
                    "422" => ["description" => "Validación fallida"],
                    "500" => ["description" => "Error interno del servidor"]
                ]
            ];

            // // GET listado => array de entidad
            // if ($method === 'GET') {
            //     if (strpos($path, '{id}') === false) {
            //         if ($entity) {
            //             $operation['responses']['200']['content']['application/json']['schema'] = [
            //                 "type" => "array",
            //                 "items" => ['$ref' => "#/components/schemas/{$entity}"]
            //             ];
            //         }
            //     }
            // }

            $swagger['paths'][$path][strtolower($method)] = $operation;
        }
    }

    // Ordenar tags con AuthController primero
    $allTags = array_keys($tagSet);
    usort($allTags, function($a, $b) {
        if ($a === 'AuthController') return -1;
        if ($b === 'AuthController') return 1;
        return strcmp($a, $b);
    });
    foreach ($allTags as $tagName) {
        $swagger['tags'][] = [
            'name' => $tagName,
            'description' => $tagDescriptions[$tagName] ?? 'Operaciones CRUD protegidas: requieren sesión iniciada para probar desde Swagger UI.'
        ];
    }

    return $swagger;
}
