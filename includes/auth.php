<?php

require_once __DIR__ . '/init.php';

class Auth {
    // 检查用户是否已登录
    public static function isLoggedIn() {
        return check_login();
    }

    // 检查是否为管理员
    public static function isAdmin() {
        return check_admin();
    }

    // 要求用户必须登录，否则跳转到登录页面
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            // 使用绝对路径跳转到admin目录下的登录页面
            header('Location: /admin/login.php');
            exit();
        }
    }

    // 要求管理员权限，否则跳转到仪表盘
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            $_SESSION['error'] = '权限不足，需要管理员权限';
            header('Location: /admin/dashboard.php');
            exit();
        }
    }

    // 检查是否为普通用户
    public static function isUser() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user';
    }

    // 用户登录
    public static function login($username, $password) {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }

        return false;
    }

    // 用户登出
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }

    // 获取当前用户信息
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>