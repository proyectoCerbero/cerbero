<<<<<<< HEAD
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
=======
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
>>>>>>> b90c71c8d22dc91fbb18d9ef11642352875cfa4e
