<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';

// 检查用户权限
Auth::requireLogin();

// 获取文档ID
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php?error=未指定文档ID');
    exit;
}

$db = get_db();

// 检查文档是否存在，并获取父级ID
$stmt = $db->prepare("SELECT document_id, id, title, parent_id FROM documents WHERE document_id = ? AND del_status = 0");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php?error=文档不存在');
    exit;
}

// 获取被删除文档的直接子文档（使用document_id而不是数据库内部id）
$stmt = $db->prepare("SELECT document_id, sort_order FROM documents WHERE parent_id = ? AND del_status = 0 ORDER BY sort_order ASC");
$stmt->execute([$id]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 开始事务
$db->beginTransaction();

try {
    if (!empty($children)) {
        // 获取新的父级ID（被删除文档的原父级）
        $new_parent_id = $document['parent_id'];
        
        // 更新子文档的父级，但保持原有的排序值不变
        $stmt = $db->prepare("UPDATE documents SET parent_id = ? WHERE document_id = ?");
        foreach ($children as $child) {
            $stmt->execute([$new_parent_id, $child['document_id']]);
        }
    }
    
    // 生成唯一的update_code用于删除操作
    $update_code = uniqid() . '_' . time();
    
    // 记录删除日志
    log_edit(
        $document['document_id'],
        $_SESSION['user_id'],
        'delete',
        [],
        $update_code
    );
    
    // 执行软删除（标记为已删除）
    $stmt = $db->prepare("UPDATE documents SET del_status = 1, deleted_at = datetime('now'), update_code = ? WHERE document_id = ?");
    $stmt->execute([$update_code, $document['document_id']]);
    
    // 提交事务
    $db->commit();
    
    header('Location: index.php?success=delete');
    exit;
    
} catch (Exception $e) {
    // 回滚事务
    $db->rollBack();
    header('Location: index.php?error=删除失败：' . $e->getMessage());
    exit;
}
?>