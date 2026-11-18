<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/UsuarioModel.php';
require_once __DIR__ . '/../config/Security.php';

class AuthController {
    private UsuarioModel $model;

    public function __construct() {
        $this->model = new UsuarioModel();
    }

    public function login(array $data): array {
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = (string)($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Ingrese un correo electrónico y contraseña válidos.'];
        }

        $authResult = $this->model->autenticar($email, $password);

        if ($authResult['status'] === 'locked') {
            http_response_code(429);
            return ['success' => false, 'message' => 'Cuenta bloqueada temporalmente por múltiples intentos fallidos. Intente en 5 minutos.'];
        }

        if ($authResult['status'] === 'ok') {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$authResult['user']['id_usuario'];
            $_SESSION['user_data'] = $authResult['user'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            return [
                'success'    => true,
                'user'       => $authResult['user'],
                'csrf_token' => $_SESSION['csrf_token']
            ];
        }

        http_response_code(401);
        return ['success' => false, 'message' => 'Credenciales inválidas.'];
    }

    public function register(array $data): array {
        $nombre = Security::sanitizeText((string)($data['nombre'] ?? ''), 100);
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = (string)($data['password'] ?? '');

        if (empty($nombre) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Nombre o correo electrónico con formato inválido.'];
        }

        if (strlen($password) < 8) {
            http_response_code(400);
            return ['success' => false, 'message' => 'La contraseña debe contener al menos 8 caracteres.'];
        }

        try {
            $this->model->registrar($nombre, $email, $password);
            http_response_code(201);
            return ['success' => true, 'message' => 'Registro completado. Ahora puede iniciar sesión.'];
        } catch (PDOException $e) {
            http_response_code(409);
            return ['success' => false, 'message' => 'No se pudo completar el registro. Verifique si el correo ya existe.'];
        }
    }

    public function logout(): array {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        return ['success' => true];
    }
}
