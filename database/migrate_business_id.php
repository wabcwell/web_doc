<?php
/**
 * 文档业务ID迁移脚本
 * 渐进式迁移方案：保持现有ID不变，新增业务ID
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/init.php';

try {
    $db = get_db();
    
    // 1. 添加document_id字段（先不添加UNIQUE约束）
    $db->exec("ALTER TABLE documents ADD COLUMN document_id INTEGER");
    
    // 2. 为现有数据生成document_id
    $stmt = $db->query("SELECT id FROM documents WHERE document_id IS NULL ORDER BY id ASC");
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $db->prepare("UPDATE documents SET document_id = ? WHERE id = ?");
    
    foreach ($documents as $index => $doc) {
        // 生成简单的数字ID，从10001开始
        $documentId = 10001 + $index;
        
        $updateStmt->execute([$documentId, $doc['id']]);
    }
    
    // 3. 创建索引优化查询
    $db->exec("CREATE INDEX IF NOT EXISTS idx_documents_document_id ON documents(document_id)");
    
    echo "✅ 业务ID迁移完成！\n";
    echo "共处理文档：" . count($documents) . " 个\n";
    
} catch (Exception $e) {
    echo "❌ 迁移失败：" . $e->getMessage() . "\n";
}