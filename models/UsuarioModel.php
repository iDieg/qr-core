<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/ConexionBD.php';

class UsuarioModel {
    private PDO $db;

    public function __construct() {
        $this->db = ConexionBD::getInstance()->getConnection();
    }

    public function registrar(string $nombre, string $email, string $password): bool {
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $sql = "INSERT INTO USUARIO (nombre, email, password_hash) VALUES (:nombre, :email, :hash)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $nombre,
            ':email'  => $email,
            ':hash'   => $hash
        ]);
    }

    // Verify credentials and handle login lockouts
    public function autenticar(string $email, string $password): array {
        $sql = "SELECT id_usuario, nombre, email, password_hash, intentos_fallidos, bloqueado_hasta 
                FROM USUARIO WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return ['status' => 'invalid'];
        }

        if (!empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()) {
            return ['status' => 'locked'];
        }

        if (password_verify($password, $usuario['password_hash'])) {
            $reset = $this->db->prepare("UPDATE USUARIO SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = :id");
            $reset->execute([':id' => $usuario['id_usuario']]);

            unset($usuario['password_hash'], $usuario['intentos_fallidos'], $usuario['bloqueado_hasta']);
            return ['status' => 'ok', 'user' => $usuario];
        }

        $intentos = (int)$usuario['intentos_fallidos'] + 1;
        $bloqueo = ($intentos >= 5) ? date('Y-m-d H:i:s', time() + 300) : null;
        $upd = $this->db->prepare("UPDATE USUARIO SET intentos_fallidos = :intentos, bloqueado_hasta = :bloqueo WHERE id_usuario = :id");
        $upd->execute([
            ':intentos' => $intentos,
            ':bloqueo'  => $bloqueo,
            ':id'       => $usuario['id_usuario']
        ]);

        return ['status' => ($intentos >= 5) ? 'locked' : 'invalid'];
    }
}
