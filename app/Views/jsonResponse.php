
<?php
function jsonResponse($data = [], $status = 200, $success = null) {
    http_response_code($status);
    header('Content-Type: application/json');

    $response = [
        'success' => $success !== null ? $success : ($status >= 200 && $status < 300),
        'data'    => $data['data'] ?? null,
        'message' => $data['message'] ?? null,
        'error'   => $data['error'] ?? null
    ];
    echo json_encode($response);
    exit;
}
