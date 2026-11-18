<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/ConexionBD.php';

class QRModel {
    private PDO $db;

    public function __construct() {
        $this->db = ConexionBD::getInstance()->getConnection();
    }

    public function obtenerPorUsuario(int $idUsuario): array {
        $sql = "SELECT q.id_qr, q.alias_identificador, q.url_destino, q.ruta_imagen, q.estado_activo, q.fecha_creacion,
                       COUNT(e.id_escaneo) AS total_escaneos 
                FROM CODIGO_QR q 
                LEFT JOIN ESTADISTICA_ESCANEO e ON q.id_qr = e.id_qr 
                WHERE q.id_usuario = :id_usuario 
                GROUP BY q.id_qr 
                ORDER BY q.fecha_creacion DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_usuario' => $idUsuario]);
        return $stmt->fetchAll();
    }

    public function contarPorUsuario(int $idUsuario): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM CODIGO_QR WHERE id_usuario = :id_usuario");
        $stmt->execute([':id_usuario' => $idUsuario]);
        return (int)$stmt->fetchColumn();
    }

    public function crear(int $idUsuario, string $alias, string $urlDestino, string $rutaImagen): int {
        $sql = "INSERT INTO CODIGO_QR (id_usuario, alias_identificador, url_destino, ruta_imagen, estado_activo) 
                VALUES (:id_usuario, :alias, :url, :ruta, 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':alias'      => $alias,
            ':url'        => $urlDestino,
            ':ruta'       => $rutaImagen
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $idQr, int $idUsuario, string $alias, string $urlDestino, int $estadoActivo): bool {
        $sql = "UPDATE CODIGO_QR 
                SET alias_identificador = :alias, url_destino = :url, estado_activo = :estado 
                WHERE id_qr = :id_qr AND id_usuario = :id_usuario";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':alias'      => $alias,
            ':url'        => $urlDestino,
            ':estado'     => $estadoActivo,
            ':id_qr'      => $idQr,
            ':id_usuario' => $idUsuario
        ]);
        return $stmt->rowCount() > 0;
    }

    public function eliminar(int $idQr, int $idUsuario): ?string {
        $sqlSelect = "SELECT ruta_imagen FROM CODIGO_QR WHERE id_qr = :id_qr AND id_usuario = :id_usuario LIMIT 1";
        $stmtSelect = $this->db->prepare($sqlSelect);
        $stmtSelect->execute([':id_qr' => $idQr, ':id_usuario' => $idUsuario]);
        $registro = $stmtSelect->fetch();

        if (!$registro) {
            return null;
        }

        $sqlDelete = "DELETE FROM CODIGO_QR WHERE id_qr = :id_qr AND id_usuario = :id_usuario";
        $stmtDelete = $this->db->prepare($sqlDelete);
        $stmtDelete->execute([':id_qr' => $idQr, ':id_usuario' => $idUsuario]);

        return $registro['ruta_imagen'];
    }

    public function obtenerEstadisticas(int $idQr, int $idUsuario): array {
        $sql = "SELECT e.id_escaneo, e.direccion_ip, e.user_agent, e.fecha_acceso 
                FROM ESTADISTICA_ESCANEO e
                INNER JOIN CODIGO_QR q ON e.id_qr = q.id_qr
                WHERE e.id_qr = :id_qr AND q.id_usuario = :id_usuario
                ORDER BY e.fecha_acceso DESC LIMIT 50";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_qr' => $idQr, ':id_usuario' => $idUsuario]);
        return $stmt->fetchAll();
    }

    public function obtenerParaRedireccion(int $idQr): ?array {
        $sql = "SELECT url_destino, estado_activo FROM CODIGO_QR WHERE id_qr = :id_qr LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_qr' => $idQr]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function registrarEscaneo(int $idQr, string $ip, string $userAgent): void {
        $sql = "INSERT INTO ESTADISTICA_ESCANEO (id_qr, direccion_ip, user_agent) VALUES (:id_qr, :ip, :ua)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_qr' => $idQr,
            ':ip'    => substr($ip, 0, 45),
            ':ua'    => substr($userAgent, 0, 255)
        ]);
    }
}
