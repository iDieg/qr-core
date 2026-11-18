<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/EnvLoader.php';
require_once __DIR__ . '/../config/Security.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/QRController.php';

EnvLoader::load(__DIR__ . '/../.env');
Security::initSession();
Security::applyApiHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$rawInput = file_get_contents('php://input');
$payload = !empty($rawInput) ? (json_decode($rawInput, true) ?? []) : [];

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Carga útil JSON malformada.']);
    exit;
}

try {
    if ($action === 'session' && $method === 'GET') {
        echo json_encode([
            'success'    => true,
            'user'       => $_SESSION['user_data'] ?? null,
            'csrf_token' => Security::getCsrfToken()
        ]);
        exit;
    }

    // Verify CSRF token on mutating requests
    if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && !Security::verifyCsrf()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad CSRF inválido. Recargue la página.']);
        exit;
    }

    if ($action === 'login' && $method === 'POST') {
        $auth = new AuthController();
        echo json_encode($auth->login($payload));
        exit;
    }

    if ($action === 'register' && $method === 'POST') {
        $auth = new AuthController();
        echo json_encode($auth->register($payload));
        exit;
    }

    if ($action === 'logout' && $method === 'POST') {
        $auth = new AuthController();
        echo json_encode($auth->logout());
        exit;
    }

    $userId = Security::requireAuth();
    $qrController = new QRController();

    if ($action === 'qrs') {
        switch ($method) {
            case 'GET':
                echo json_encode($qrController->index($userId));
                break;
            case 'POST':
                echo json_encode($qrController->store($userId, $payload));
                break;
            case 'PUT':
                echo json_encode($qrController->update($userId, $payload));
                break;
            case 'DELETE':
                echo json_encode($qrController->destroy($userId, $payload));
                break;
            default:
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Método HTTP no permitido.']);
        }
        exit;
    }

    if ($action === 'stats' && $method === 'GET') {
        $idQr = isset($_GET['id_qr']) ? (int)$_GET['id_qr'] : 0;
        echo json_encode($qrController->stats($userId, $idQr));
        exit;
    }

    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Recurso no encontrado.']);

} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor.']);
}
