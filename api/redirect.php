<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/QRModel.php';
require_once __DIR__ . '/../config/Security.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$idQr = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($idQr <= 0) {
    http_response_code(400);
    die('<h1>400 Bad Request</h1><p>Identificador de código QR inválido.</p>');
}

$qrModel = new QRModel();
$qr = $qrModel->obtenerParaRedireccion($idQr);

if (!$qr) {
    http_response_code(404);
    die('<h1>404 Not Found</h1><p>El código QR solicitado no existe.</p>');
}

if ((int)$qr['estado_activo'] !== 1) {
    http_response_code(410);
    die('<h1>Enlace Pausado</h1><p>Este código QR dinámico se encuentra desactivado temporalmente.</p>');
}

$safeDestination = Security::validateSafeUrl($qr['url_destino']);
if ($safeDestination === null) {
    http_response_code(400);
    die('<h1>Destino Bloqueado</h1><p>La URL de destino no cumple con los protocolos de seguridad permitidos.</p>');
}

$ip = Security::getClientIp();
$userAgent = Security::sanitizeText((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido'), 255);
$qrModel->registrarEscaneo($idQr, $ip, $userAgent);

header('Location: ' . $safeDestination, true, 302);
exit;
