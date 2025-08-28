<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';

// 检查用户权限
Auth::requireLogin();

// 获取参数
$document_id = $_POST['document_id'] ?? null;
$version_id = $_POST['version_id'] ?? null;

if (!$document_id || !$version_id) {
    header('Location: view_his.php?id=' . ($document_id ?? '') . '&error=参数不完整');
    exit;
}

try {
    $db = get_db();
    
    // 开始事务
    $db->beginTransaction();
    
    // 获取要回滚的版本信息
    $stmt = $db->prepare("SELECT * FROM documents_version WHERE id = ? AND document_id = ?");
    $stmt->execute([$version_id, $document_id]);
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$version) {
        throw new Exception('版本不存在');
    }
    
    // 获取当前文档信息
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$document_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        throw new Exception('文档不存在');
    }
    
    // 获取下一个版本号
    $stmt = $db->prepare("SELECT MAX(version_number) as max_version FROM documents_version WHERE document_id = ?");
    $stmt->execute([$document_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_version = ($result['max_version'] ?? 0) + 1;
    
    // 生成唯一的update_code用于回滚操作
    $update_code = uniqid() . '_' . time();
    
    // 将回滚版本的数据保存为新版本（确保数据一致）
    $stmt = $db->prepare("INSERT INTO documents_version (document_id, title, content, tags, version_number, created_by, created_at, update_code) VALUES (?, ?, ?, ?, ?, ?, datetime('now'), ?)");
    $stmt->execute([
        $document_id,
        $version['title'],
        $version['content'],
        $version['tags'] ?? '',
        $next_version,
        $_SESSION['user_id'],
        $update_code
    ]);
    
    // 更新文档为回滚版本的内容（与documents_version表数据完全一致）
    $stmt = $db->prepare("UPDATE documents SET title = ?, content = ?, tags = ?, updated_at = datetime('now'), update_code = ? WHERE id = ?");
    $stmt->execute([
        $version['title'],
        $version['content'],
        $version['tags'] ?? '',
        $update_code,
        $document_id
    ]);
    
    // 记录回滚操作日志
    log_edit(
        $document_id,
        $_SESSION['user_id'],
        'rollback',
        [],
        $update_code
    );
    
    // 提交事务
    $db->commit();
    
    // 重定向回历史页面
    header('Location: view_his.php?id=' . $document_id . '&success=已回滚到版本 ' . $version['version_number']);
    
} catch (Exception $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // 记录错误并跳转
    error_log('Rollback error: ' . $e->getMessage());
    header('Location: view_his.php?id=' . $document_id . '&error=' . urlencode('回滚失败：' . $e->getMessage()));
}
exit;