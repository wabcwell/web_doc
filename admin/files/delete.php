<?php
require_once '../../includes/init.php';

// 物理删除文件（无视数据库状态，直接删除服务器文件）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $db = get_db();
        
        // 1. 获取文件物理路径（不管del_status是什么）
        $stmt = $db->prepare("SELECT file_path FROM file_upload WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            echo json_encode(['success' => false, 'message' => '文件记录不存在']);
            exit;
        }
        
        // 2. 直接物理删除文件（无视数据库状态）
        $file_path = dirname(__DIR__, 2) . '/' . ltrim($file['file_path'], '/');
        $file_path = str_replace('/', DIRECTORY_SEPARATOR, $file_path); // Windows路径兼容
        
        $file_deleted = false;
        if (file_exists($file_path)) {
            $file_deleted = @unlink($file_path);
        } else {
            $file_deleted = true; // 文件已不存在
        }
        
        // 3. 物理删除成功后，更新数据库状态为已删除
        if ($file_deleted) {
            $stmt = $db->prepare("UPDATE file_upload SET del_status = 1, deleted_at = datetime('now') WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        
        echo json_encode([
            'success' => $file_deleted,
            'message' => $file_deleted ? '文件已物理删除' : '物理删除失败'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '删除失败']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效请求']);
}
?>