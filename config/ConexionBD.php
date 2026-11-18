<?php
declare(strict_types=1);

require_once __DIR__ . '/EnvLoader.php';

// Singleton PDO database connection
class ConexionBD {
    private static ?ConexionBD $instancia = null;
    private PDO $pdo;

    private function __construct() {
        EnvLoader::load(__DIR__ . '/../.env');

        $host    = EnvLoader::get('DB_HOST', 'localhost');
        $port    = EnvLoader::get('DB_PORT', '3306');
        $db      = EnvLoader::get('DB_NAME', 'qrcore_db');
        $user    = EnvLoader::get('DB_USER', 'root');
        $pass    = EnvLoader::get('DB_PASS', '');
        $charset = EnvLoader::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode([
                'success' => false,
                'message' => 'Servicio de base de datos no disponible temporalmente.'
            ]));
        }
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }

    public static function getInstance(): ConexionBD {
        if (self::$instancia === null) {
            self::$instancia = new ConexionBD();
        }
        return self::$instancia;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}
