<?php
require_once 'includes/init.php';

try {
    $db = get_db();
    
    echo "=== 当前数据库外键约束检查 ===\n";
    
    // 检查所有表的外键约束
    $stmt = $db->query("SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND REFERENCED_TABLE_NAME IS NOT NULL");
    
    $constraints = $stmt->fetchAll();
    echo "当前数据库的所有外键约束:\n";
    if (empty($constraints)) {
        echo "  无外键约束\n";
    } else {
        foreach ($constraints as $constraint) {
            echo "  - {$constraint['TABLE_NAME']}.{$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    echo "\n=== 更新edit_log表外键约束 ===\n";
    
    // 删除edit_log表的所有外键约束
    $constraints_to_remove = [
        'edit_log_ibfk_1',
        'edit_log_ibfk_2'
    ];
    
    foreach ($constraints_to_remove as $constraint) {
        try {
            $db->exec("ALTER TABLE edit_log DROP FOREIGN KEY $constraint");
            echo "已删除外键约束: $constraint\n";
        } catch (Exception $e) {
            echo "删除外键约束 $constraint 时出错: " . $e->getMessage() . "\n";
        }
    }
    
    // 删除documents_version表的外键约束
    $constraints_to_remove = [
        'documents_version_ibfk_1',
        'documents_version_ibfk_2'
    ];
    
    foreach ($constraints_to_remove as $constraint) {
        try {
            $db->exec("ALTER TABLE documents_version DROP FOREIGN KEY $constraint");
            echo "已删除外键约束: $constraint\n";
        } catch (Exception $e) {
            echo "删除外键约束 $constraint 时出错: " . $e->getMessage() . "\n";
        }
    }
    
    // 删除file_upload表的外键约束
    $constraints_to_remove = [
        'file_upload_ibfk_1',
        'file_upload_ibfk_2'
    ];
    
    foreach ($constraints_to_remove as $constraint) {
        try {
            $db->exec("ALTER TABLE file_upload DROP FOREIGN KEY $constraint");
            echo "已删除外键约束: $constraint\n";
        } catch (Exception $e) {
            echo "删除外键约束 $constraint 时出错: " . $e->getMessage() . "\n";
        }
    }
    
    // 删除document_id_apportion表的外键约束
    $constraints_to_remove = [
        'document_id_apportion_ibfk_1'
    ];
    
    foreach ($constraints_to_remove as $constraint) {
        try {
            $db->exec("ALTER TABLE document_id_apportion DROP FOREIGN KEY $constraint");
            echo "已删除外键约束: $constraint\n";
        } catch (Exception $e) {
            echo "删除外键约束 $constraint 时出错: " . $e->getMessage() . "\n";
        }
    }
    
    // 删除documents表的外键约束（如果有的话）
    // 注意：documents表可能没有其他外键约束，因为parent_id约束已被移除
    
    echo "\n=== 验证外键约束删除结果 ===\n";
    
    // 重新检查外键约束
    $stmt = $db->query("SELECT 
        TABLE_NAME,
        COLUMN_NAME,
        CONSTRAINT_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND REFERENCED_TABLE_NAME IS NOT NULL");
    
    $remaining_constraints = $stmt->fetchAll();
    echo "剩余的外键约束:\n";
    if (empty($remaining_constraints)) {
        echo "  已删除所有外键约束\n";
    } else {
        foreach ($remaining_constraints as $constraint) {
            echo "  - {$constraint['TABLE_NAME']}.{$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    echo "\n数据库外键约束清理完成！\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}