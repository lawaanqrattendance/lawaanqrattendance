<?php
function sendApiResponse($success, $data = null, $error = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'error' => $error
    ]);
    exit();
}

function sendUnauthorizedResponse() {
    sendApiResponse(false, null, 'Unauthorized access', 401);
}

function sendValidationError($message) {
    sendApiResponse(false, null, $message, 400);
}

function sendServerError($message) {
    sendApiResponse(false, null, $message, 500);
} 