<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/init.php';

// 检查管理员权限
if (!check_admin()) {
    header('Location: /admin/login.php');
    exit();
}

// 设置内容类型为JSON
header('Content-Type: application/json; charset=utf-8');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只支持POST请求']);
    exit();
}

// 获取文档ID
$document_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($document_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '无效的文档ID']);
    exit();
}

try {
    $db = get_db();
    
    // 验证文档是否存在且已被删除
    $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ? AND del_status = 1");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => '文档不存在或未被删除']);
        exit();
    }
    
    // 开始事务
    $db->beginTransaction();
    
    try {
        // 删除版本历史
        $stmt = $db->prepare("DELETE FROM documents_version WHERE document_id = ?");
        $stmt->execute([$document_id]);
        
        // 删除编辑日志
        $stmt = $db->prepare("DELETE FROM edit_log WHERE document_id = ?");
        $stmt->execute([$document_id]);
        
        // 删除文档
        $stmt = $db->prepare("DELETE FROM documents WHERE document_id = ?");
        $stmt->execute([$document_id]);
        
        // 提交事务
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => '文档已永久删除',
            'document' => [
                'id' => $document['document_id'],
                'title' => $document['title']
            ]
        ]);
        
    } catch (Exception $e) {
        // 回滚事务
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => '删除失败：' . $e->getMessage()
    ]);
}