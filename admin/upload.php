<?php
require_once '../config.php';

// 检查用户权限
if (!isset($_SESSION['user_id']) && !check_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '无权限上传']);
    exit;
}

// 设置响应头
header('Content-Type: application/json');

// 检查是否有文件上传
if (!isset($_FILES['upfile']) && !isset($_FILES['image']) && !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => '没有上传文件']);
    exit;
}

$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 确定上传类型和文件
if (isset($_FILES['upfile'])) {
    $file = $_FILES['upfile'];
    $uploadType = 'ueditor'; // UEditorPlus上传
} else {
    $uploadType = isset($_FILES['image']) ? 'image' : 'file';
    $file = $_FILES[$uploadType];
}

// 检查上传错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '上传错误：' . $file['error']]);
    exit;
}

// 验证文件类型和大小
$isImage = strpos($file['type'], 'image/') === 0;
if ($isImage) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $maxSize = 10 * 1024 * 1024; // 图片最大10MB
} else {
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/markdown'
    ];
    $maxSize = 50 * 1024 * 1024; // 文件最大50MB
}

$fileType = mime_content_type($file['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => '不支持的文件类型: ' . $fileType]);
    exit;
}

if ($file['size'] > $maxSize) {
    $sizeLimit = $isImage ? '10MB' : '50MB';
    echo json_encode(['success' => false, 'message' => '文件过大，最大支持' . $sizeLimit]);
    exit;
}

// 生成唯一文件名
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid($isImage ? 'img_' : 'file_') . '.' . $extension;
$uploadPath = $uploadDir . $filename;

// 移动上传的文件
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // 生成相对URL - 便于部署
    $url = '/uploads/' . $filename;
    
    // 统一使用UEditor格式响应（兼容UEditorPlus和wangEditor）
    echo json_encode([
        'state' => 'SUCCESS',
        'url' => $url,
        'title' => $file['name'],
        'original' => $file['name'],
        'type' => '.' . $extension,
        'size' => $file['size']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '文件移动失败']);
}
?>