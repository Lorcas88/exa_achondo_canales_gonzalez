<?php
/**
 * Función Auxiliar para Respuestas JSON Estandarizadas
 *
 * Esta función centraliza la forma en que la API devuelve las respuestas, asegurando
 * que todas sigan una estructura consistente. Esto facilita el consumo de la API
 * por parte de los clientes (como una aplicación frontend).
 *
 * @param array $data Un array que puede contener 'data', 'message' o 'error'.
 * @param int $status El código de estado HTTP para la respuesta (ej. 200, 404, 500).
 * @param bool|null $success Un valor booleano para indicar explícitamente el éxito.
 *                           Si es null, se determinará automáticamente basado en el código de estado.
 * @return void La función imprime la respuesta JSON y termina la ejecución del script.
 */
function jsonResponse($data = [], $status = 200, $success = null) {
    // 1. Establece el código de estado HTTP de la respuesta.
    http_response_code($status);
    // 2. Establece la cabecera para indicar que el contenido es de tipo JSON.
    header('Content-Type: application/json');

    // 3. Construye el cuerpo de la respuesta con una estructura estándar.
    $response = [
        // El campo 'success' es true si el status está en el rango 2xx, a menos que se fuerce otro valor.
        'success' => $success !== null ? $success : ($status >= 200 && $status < 300),
        // Los datos principales de la respuesta (ej. una lista de productos, un usuario).
        'data'    => $data['data'] ?? null,
        // Un mensaje opcional para el cliente (ej. "Registro creado correctamente").
        'message' => $data['message'] ?? null,
        // Un mensaje o array de errores si la operación falló.
        'error'   => $data['error'] ?? null
    ];

    // 4. Codifica el array de respuesta a formato JSON y lo imprime en el cuerpo de la respuesta HTTP.
    echo json_encode($response);
    
    // 5. Termina la ejecución del script para asegurar que no se envíe ningún otro contenido.
    exit;
}