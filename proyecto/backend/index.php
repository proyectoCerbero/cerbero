<?php

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API de autenticación lista.',
    'endpoints' => [
        'POST /backend/api/auth.php?action=register',
        'POST /backend/api/auth.php?action=login'
    ]
]);
