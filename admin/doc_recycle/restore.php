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
    
    // 恢复文档
    $stmt = $db->prepare("UPDATE documents SET del_status = 0, deleted_at = NULL WHERE id = ?");
    $stmt->execute([$document_id]);
    
    // 记录恢复操作到编辑日志
    $stmt = $db->prepare("INSERT INTO edit_log (document_id, user_id, action, old_title, new_title, created_at) VALUES (?, ?, 'rollback', ?, ?, datetime('now'))");
    $stmt->execute([$document_id, $_SESSION['user_id'], $document['title'], $document['title']]);
    
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