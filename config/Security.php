<?php
declare(strict_types=1);

// Session, CSRF, and input validation helpers
class Security {
    public static function isHttps(): bool {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    }

    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function applyApiHeaders(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    public static function getCsrfToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function verifyCsrf(): bool {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return !empty($headerToken) && !empty($sessionToken) && hash_equals($sessionToken, $headerToken);
    }

    public static function requireAuth(): int {
        $userId = $_SESSION['user_id'] ?? 0;
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada.']);
            exit;
        }
        return (int)$userId;
    }

    // Validate URL scheme and block private network targets
    public static function validateSafeUrl(string $rawUrl): ?string {
        $clean = filter_var(trim($rawUrl), FILTER_SANITIZE_URL);
        if (!filter_var($clean, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($clean);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (!in_array($scheme, ['http', 'https'], true) || empty($host)) {
            return null;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            if (filter_var($host, FILTER_VALIDATE_IP, $flags) === false) {
                return null;
            }
        } else {
            $resolved = gethostbyname($host);
            if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
                $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
                if (filter_var($resolved, FILTER_VALIDATE_IP, $flags) === false) {
                    return null;
                }
            }
        }

        return $clean;
    }

    public static function sanitizeText(string $input, int $maxLength = 100): string {
        $trimmed = trim($input);
        $sliced = mb_substr($trimmed, 0, $maxLength, 'UTF-8');
        return htmlspecialchars($sliced, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function getClientIp(): string {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($forwarded)) {
            $firstIp = trim(explode(',', $forwarded)[0]);
            if (filter_var($firstIp, FILTER_VALIDATE_IP)) {
                return $firstIp;
            }
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
}
