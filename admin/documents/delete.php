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
$stmt = $db->prepare("SELECT id, title, parent_id FROM documents WHERE id = ?");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php?error=文档不存在');
    exit;
}

// 获取被删除文档的直接子文档
$stmt = $db->prepare("SELECT id FROM documents WHERE parent_id = ? ORDER BY sort_order ASC");
$stmt->execute([$id]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取被删除文档的排序值
$stmt = $db->prepare("SELECT sort_order FROM documents WHERE id = ?");
$stmt->execute([$id]);
$deleted_sort_order = $stmt->fetchColumn();

// 开始事务
$db->beginTransaction();

try {
    if (!empty($children)) {
        // 获取新的父级ID（被删除文档的原父级）
        $new_parent_id = $document['parent_id'];
        
        // 子文档继承被删除文档的排序值
        $inherited_sort_order = $deleted_sort_order;
        
        // 更新子文档的父级和排序权重（继承被删除文档的排序值）
        $stmt = $db->prepare("UPDATE documents SET parent_id = ?, sort_order = ? WHERE id = ?");
        foreach ($children as $child) {
            $stmt->execute([$new_parent_id, $inherited_sort_order, $child['id']]);
        }
    }
    
    // 执行删除文档
    $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    
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