<?php
require_once '../../config.php';
require_once '../../includes/init.php';

// 检查用户是否已登录
if (!check_login()) {
    header('Location: /admin/login.php');
    exit();
}

// 检查是否为管理员
if (!check_admin()) {
    die('权限不足');
}

// 获取文件ID
$file_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($file_id <= 0) {
    die('无效的文件ID');
}

// 查询文件信息
$db = get_db();
$stmt = $db->prepare("SELECT * FROM file_upload WHERE id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die('文件不存在');
}

// 检查文件是否存在
$file_path = dirname(__DIR__, 2) . '/' . ltrim($file['file_path'], '/');
$file_path = str_replace('/', DIRECTORY_SEPARATOR, $file_path); // Windows路径兼容

if (!file_exists($file_path)) {
    die('文件不存在于服务器上');
}

// 设置下载头信息
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['file_path']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));

// 清除缓冲区
ob_clean();
flush();

// 输出文件内容
readfile($file_path);
exit;
?>