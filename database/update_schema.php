<?php
/**
 * 数据库结构更新脚本
 * 用于在现有数据库中添加file_upload表
 */

// 设置脚本执行时间
set_time_limit(0);

// 包含配置文件
require_once __DIR__ . '/../config.php';

// 数据库路径
$db_path = __DIR__ . '/docs.db';

echo "开始更新数据库结构...\n";

try {
    // 连接数据库
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 开启事务
    $db->beginTransaction();
    
    // 检查file_upload表是否已存在
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='file_upload'");
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        echo "创建file_upload表...\n";
        
        // 创建file_upload表
        $db->exec("CREATE TABLE IF NOT EXISTS file_upload (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_type TEXT NOT NULL CHECK (file_type IN ('image', 'video', 'audio', 'document', 'archive', 'other')),
            file_format TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            document_id INTEGER,
            description TEXT,
            notes TEXT,
            uploaded_by INTEGER NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            del_status INTEGER DEFAULT 0,
            deleted_at TEXT,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // 创建索引
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_document_id ON file_upload(document_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_uploaded_by ON file_upload(uploaded_by)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_file_type ON file_upload(file_type)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_uploaded_at ON file_upload(uploaded_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_del_status ON file_upload(del_status)");
        
        // 创建触发器
        $db->exec("CREATE TRIGGER IF NOT EXISTS update_file_upload_timestamp 
                   AFTER UPDATE ON file_upload
                   BEGIN
                       UPDATE file_upload SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
                   END");
        
        echo "file_upload表创建成功！\n";
    } else {
        echo "file_upload表已存在，跳过创建...\n";
    }
    
    // 检查是否已有测试数据
    $stmt = $db->query("SELECT COUNT(*) FROM file_upload");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "插入测试模拟数据...\n";
        
        // 插入测试数据
        $testData = [
            ['image', 'jpg', 102400, 'uploads/test1.jpg', 1, '测试图片1', '这是测试图片的描述', 1],
            ['document', 'pdf', 204800, 'uploads/test2.pdf', 1, '测试文档', 'PDF测试文档', 1],
            ['image', 'png', 51200, 'uploads/test3.png', null, '未关联的测试图片', '这是一个未关联到文档的测试图片', 1],
            ['video', 'mp4', 1048576, 'uploads/test4.mp4', 1, '测试视频', '测试视频文件', 1],
            ['archive', 'zip', 307200, 'uploads/test5.zip', null, '测试压缩包', '包含多个文件的测试压缩包', 1],
            ['audio', 'mp3', 204800, 'uploads/test6.mp3', 1, '测试音频', '测试音频文件', 1],
            ['document', 'docx', 153600, 'uploads/test7.docx', 1, 'Word文档', 'Word测试文档', 1],
            ['other', 'txt', 10240, 'uploads/test8.txt', null, '测试文本', '纯文本测试文件', 1]
        ];
        
        $stmt = $db->prepare("INSERT INTO file_upload 
            (file_type, file_format, file_size, file_path, document_id, description, notes, uploaded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($testData as $data) {
            $stmt->execute($data);
        }
        
        echo "测试数据插入成功！共插入 " . count($testData) . " 条记录\n";
    } else {
        echo "file_upload表中已有数据，跳过插入测试数据...\n";
    }
    
    // 提交事务
    $db->commit();
    
    echo "数据库结构更新完成！\n";
    
    // 显示表结构信息
    echo "\n表结构信息：\n";
    $stmt = $db->query("PRAGMA table_info(file_upload)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "file_upload表字段：\n";
    foreach ($columns as $column) {
        echo "  - {$column['name']}: {$column['type']} " . ($column['notnull'] ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    // 显示测试数据
    echo "\n测试数据预览：\n";
    $stmt = $db->query("SELECT * FROM file_upload ORDER BY id LIMIT 5");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        echo "  ID:{$file['id']} {$file['file_type']}/{$file['file_format']} {$file['file_path']} ({$file['file_size']} bytes)\n";
    }
    
} catch (Exception $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    echo "数据库更新失败：" . $e->getMessage() . "\n";
    exit(1);
}

echo "\n数据库更新脚本执行完成！\n";
?>