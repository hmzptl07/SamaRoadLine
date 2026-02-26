<?php

require_once __DIR__ . "/../config/database.php";

class Admin {

    public static function login($username, $password) {
        global $pdo;

        $stmt = $pdo->prepare("SELECT * FROM AdminMaster WHERE Username = ? AND IsActive = 'Yes'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['Password'])) {
            $_SESSION['admin_id'] = $admin['AdminId'];
            $_SESSION['username'] = $admin['Username'];
            return true;
        }

        return false;
    }

    public static function logout() {
        session_destroy();
        header("Location:/Sama_Roadlines/login.php");
        exit();
    }

    public static function checkAuth() {
        if (!isset($_SESSION['admin_id'])) {
            header("Location:/Sama_Roadlines/login.php");
            exit();
        }
    }

    public static function changePassword($adminId, $newPassword) {
        global $pdo;

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE AdminMaster SET Password = ? WHERE AdminId = ?");
        return $stmt->execute([$hashed, $adminId]);
    }
}
