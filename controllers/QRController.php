<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/QRModel.php';
require_once __DIR__ . '/../config/Security.php';

class QRController {
    private QRModel $model;

    public function __construct() {
        $this->model = new QRModel();
    }

    public function index(int $userId): array {
        $qrs = $this->model->obtenerPorUsuario($userId);
        return ['success' => true, 'data' => $qrs];
    }

    public function store(int $userId, array $data): array {
        $alias = Security::sanitizeText((string)($data['alias'] ?? ''), 100);
        $url = Security::validateSafeUrl((string)($data['url'] ?? ''));

        if (empty($alias) || $url === null) {
            http_response_code(400);
            return ['success' => false, 'message' => 'URL bloqueada o inválida. Use únicamente dominios públicos (http:// o https://).'];
        }

        if ($this->model->contarPorUsuario($userId) >= 100) {
            http_response_code(429);
            return ['success' => false, 'message' => 'Ha alcanzado el límite máximo de códigos QR permitidos por cuenta.'];
        }

        $fileName = 'qr_' . $userId . '_' . bin2hex(random_bytes(8)) . '.png';
        $relativePath = 'qrs/' . $fileName;
        $absolutePath = __DIR__ . '/../' . $relativePath;

        $db = ConexionBD::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            $qrId = $this->model->crear($userId, $alias, $url, $relativePath);
            $dynamicUrl = $this->buildDynamicEndpoint($qrId);
            $this->generarImagenQR($dynamicUrl, $absolutePath);

            $db->commit();
            http_response_code(201);
            return ['success' => true, 'message' => 'Código QR dinámico generado exitosamente.'];
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            error_log($e->getMessage());
            http_response_code(500);
            return ['success' => false, 'message' => 'No se pudo generar la imagen del código QR.'];
        }
    }

    public function update(int $userId, array $data): array {
        $idQr = (int)($data['id_qr'] ?? 0);
        $alias = Security::sanitizeText((string)($data['alias'] ?? ''), 100);
        $url = Security::validateSafeUrl((string)($data['url'] ?? ''));
        $estado = (isset($data['estado_activo']) && (int)$data['estado_activo'] === 0) ? 0 : 1;

        if ($idQr <= 0 || empty($alias) || $url === null) {
            http_response_code(400);
            return ['success' => false, 'message' => 'URL bloqueada o datos inválidos para actualizar el enlace.'];
        }

        $this->model->actualizar($idQr, $userId, $alias, $url, $estado);
        return ['success' => true, 'message' => 'Destino actualizado correctamente sin modificar el QR físico.'];
    }

    public function destroy(int $userId, array $data): array {
        $idQr = (int)($data['id_qr'] ?? 0);
        if ($idQr <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => 'Identificador de QR inválido.'];
        }

        $rutaImagen = $this->model->eliminar($idQr, $userId);
        if ($rutaImagen !== null) {
            $safeName = basename($rutaImagen);
            $fullPath = realpath(__DIR__ . '/../qrs') . DIRECTORY_SEPARATOR . $safeName;
            if (file_exists($fullPath) && strpos($safeName, 'seed_') !== 0) {
                @unlink($fullPath);
            }
            return ['success' => true, 'message' => 'Código QR eliminado permanentemente.'];
        }

        http_response_code(404);
        return ['success' => false, 'message' => 'Registro no encontrado o acceso denegado.'];
    }

    public function stats(int $userId, int $idQr): array {
        if ($idQr <= 0) {
            http_response_code(400);
            return ['success' => false, 'message' => 'ID inválido.'];
        }
        $logs = $this->model->obtenerEstadisticas($idQr, $userId);
        return ['success' => true, 'data' => $logs];
    }

    // Build redirect URL using environment config or active request host
    private function buildDynamicEndpoint(int $qrId): string {
        $configuredUrl = rtrim(EnvLoader::get('APP_URL', ''), '/');
        if ($configuredUrl !== '' && !str_contains($configuredUrl, 'localhost')) {
            return "{$configuredUrl}/api/redirect.php?id={$qrId}";
        }

        $protocol = Security::isHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $lanIp = gethostbyname(gethostname());
            if (filter_var($lanIp, FILTER_VALIDATE_IP) && $lanIp !== '127.0.0.1') {
                $port = $_SERVER['SERVER_PORT'] ?? '80';
                $host = ($port !== '80' && $port !== '443') ? "{$lanIp}:{$port}" : $lanIp;
            }
        }

        $baseFolder = dirname(dirname($_SERVER['SCRIPT_NAME']));
        $baseFolder = rtrim(str_replace('\\', '/', $baseFolder), '/');
        return "{$protocol}://{$host}{$baseFolder}/api/redirect.php?id={$qrId}";
    }

    private function generarImagenQR(string $contenido, string $rutaDestino): void {
        $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . urlencode($contenido);
        $binario = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($res !== false && $code === 200) {
                $binario = $res;
            }
        }

        if ($binario === false) {
            $ctx = stream_context_create(['http' => ['timeout' => 5]]);
            $binario = @file_get_contents($apiUrl, false, $ctx);
        }

        if ($binario !== false && strlen($binario) > 100) {
            file_put_contents($rutaDestino, $binario);
            return;
        }

        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor(300, 300);
            $blanco = imagecolorallocate($im, 255, 255, 255);
            $negro = imagecolorallocate($im, 15, 23, 42);
            imagefill($im, 0, 0, $blanco);
            imagerectangle($im, 20, 20, 280, 280, $negro);
            imagestring($im, 5, 85, 135, 'QR DINAMICO', $negro);
            imagepng($im, $rutaDestino);
            imagedestroy($im);
            return;
        }

        throw new Exception('Image generation unavailable');
    }
}
