<?php
require_once 'config.php';

// 检查用户权限
if (!isset($_SESSION['user_id']) && !check_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限上传']);
    exit;
}

// 设置响应头
header('Content-Type: application/json');

// 检查是否有文件上传
if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => '没有上传文件']);
    exit;
}

$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$file = $_FILES['image'];

// 检查上传错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '上传错误：' . $file['error']]);
    exit;
}

// 验证文件类型
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => '不支持的文件类型']);
    exit;
}

// 验证文件大小 (最大 2MB)
$maxSize = 2 * 1024 * 1024; // 2MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => '文件过大，最大支持2MB']);
    exit;
}

// 生成唯一文件名
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('img_') . '.' . $extension;
$uploadPath = $uploadDir . $filename;

// 移动上传的文件
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // 生成完整URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $uploadPath;
    
    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '文件移动失败']);
}
?>