<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/init.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 检查管理员权限
if (!check_admin()) {
    echo json_encode(['success' => false, 'message' => '权限不足，请先登录管理员账号']);
    exit();
}

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit();
}

// 获取并验证文档ID
$document_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($document_id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的文档ID']);
    exit();
}

// 获取数据库连接
$db = get_db();

try {
    // 验证文档是否存在且已被删除
    $stmt = $db->prepare("SELECT id, title FROM documents WHERE id = ? AND del_status = 1");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo json_encode(['success' => false, 'message' => '文档不存在或未被删除']);
        exit();
    }
    
    // 开始事务
    $db->beginTransaction();
    
    // 生成唯一的update_code用于恢复操作
    $update_code = uniqid() . '_' . time();
    
    // 恢复文档
    $stmt = $db->prepare("UPDATE documents SET del_status = 0, deleted_at = NULL, update_code = ? WHERE id = ?");
    $stmt->execute([$update_code, $document_id]);
    
    // 记录恢复操作到编辑日志
    $stmt = $db->prepare("INSERT INTO edit_log (document_id, user_id, action, created_at, update_code) VALUES (?, ?, 'restore', datetime('now'), ?)");
    $stmt->execute([$document_id, $_SESSION['user_id'], $update_code]);
    
    // 提交事务
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => '文档已成功恢复到原文档库',
        'document_id' => $document_id,
        'document_title' => $document['title']
    ]);
    
} catch (Exception $e) {
    // 回滚事务
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => '恢复失败：' . $e->getMessage()
    ]);
}
?>