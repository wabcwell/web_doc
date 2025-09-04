<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/auth.php';

Auth::requireAdmin();

$db = get_db();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$user_id = (int)$_GET['id'];

// 防止删除最后一个管理员
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$admin_count = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_role = $stmt->fetchColumn();

if ($user_role === 'admin' && $admin_count <= 1) {
    $_SESSION['error'] = '无法删除最后一个管理员账户';
    header('Location: index.php');
    exit;
}

// 防止删除自己
$current_user_id = $_SESSION['user_id'] ?? 0;
if ($user_id === $current_user_id) {
    $_SESSION['error'] = '无法删除当前登录的账户';
    header('Location: index.php');
    exit;
}

// 执行删除
$stmt = $db->prepare("DELETE FROM users WHERE id = ?");
if ($stmt->execute([$user_id])) {
    $_SESSION['success'] = '用户删除成功';
} else {
    $_SESSION['error'] = '删除用户失败';
}

header('Location: index.php');
exit;