<?php
/**
 * 系统初始化文件
 * 负责数据库连接和基础函数
 * 以database/docs.db实际表结构为准
 */

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 包含配置文件
require_once __DIR__ . '/../config.php';

// 设置PHP默认时区（全局生效）
if (isset($timezone)) {
    date_default_timezone_set($timezone);
}

/**
 * 初始化数据库 - 严格按照实际数据库结构创建
 */
function init_database() {
    global $db_host, $db_name, $db_user, $db_pass, $db_charset, $db_port;
    
    $dsn = "mysql:host=$db_host;port=$db_port;charset=$db_charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`;");
        $pdo->exec("USE `$db_name`;");
        
        // 严格按照实际数据库结构创建表
        
        // 创建documents表 - 移除所有外键约束
        $pdo->exec("CREATE TABLE IF NOT EXISTS `documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `document_id` int(11) DEFAULT NULL,
            `title` text NOT NULL,
            `content` longtext,
            `parent_id` int(11) NOT NULL DEFAULT 0,
            `sort_order` int(11) DEFAULT 0,
            `user_id` int(11) DEFAULT 1,
            `is_public` int(11) DEFAULT 1,
            `tags` text DEFAULT NULL,
            `view_count` int(11) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `del_status` int(11) DEFAULT 0,
            `deleted_at` timestamp NULL DEFAULT NULL,
            `is_formal` int(11) DEFAULT 0,
            `update_code` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_documents_document_id` (`document_id`),
            KEY `idx_documents_parent_id` (`parent_id`),
            KEY `idx_documents_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // 创建users表 - 使用本地时间
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` varchar(50) DEFAULT 'editor',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // 创建edit_log表 - 移除所有外键约束
        $pdo->exec("CREATE TABLE IF NOT EXISTS `edit_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `document_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `action` varchar(50) NOT NULL,
            `op_title` int(11) DEFAULT 0,
            `op_content` int(11) DEFAULT 0,
            `op_tags` int(11) DEFAULT 0,
            `op_parent` int(11) DEFAULT 0,
            `op_corder` int(11) DEFAULT 0,
            `op_public` int(11) DEFAULT 0,
            `op_formal` int(11) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `temp_action` varchar(255) DEFAULT NULL,
            `update_code` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_edit_log_document_id` (`document_id`),
            KEY `idx_edit_log_user_id` (`user_id`),
            KEY `idx_edit_log_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // 创建documents_version表 - 移除所有外键约束
        $pdo->exec("CREATE TABLE IF NOT EXISTS `documents_version` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `document_id` int(11) NOT NULL,
            `title` text NOT NULL,
            `content` longtext,
            `tags` text DEFAULT NULL,
            `version_number` int(11) NOT NULL,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `update_code` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_documents_version_document_id` (`document_id`),
            KEY `idx_documents_version_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // 创建file_upload表 - 文件上传管理（移除所有外键约束）
        $pdo->exec("CREATE TABLE IF NOT EXISTS `file_upload` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `file_type` varchar(50) NOT NULL,
            `file_format` varchar(50) NOT NULL,
            `file_size` int(11) NOT NULL,
            `file_path` varchar(255) NOT NULL,
            `image_width` int(11) DEFAULT NULL,
            `image_height` int(11) DEFAULT NULL,
            `alias` varchar(255) DEFAULT NULL,
            `document_id` int(11) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `uploaded_by` int(11) NOT NULL,
            `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `del_status` int(11) DEFAULT 0,
            `deleted_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_file_upload_document_id` (`document_id`),
            KEY `idx_file_upload_uploaded_by` (`uploaded_by`),
            KEY `idx_file_upload_file_type` (`file_type`),
            KEY `idx_file_upload_uploaded_at` (`uploaded_at`),
            KEY `idx_file_upload_del_status` (`del_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // 创建document_id_apportion表 - 管理文档ID使用状态（移除所有外键约束）
        $pdo->exec("CREATE TABLE IF NOT EXISTS `document_id_apportion` (
            `document_id` int(11) NOT NULL AUTO_INCREMENT,
            `usage_status` int(11) DEFAULT 0,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`document_id`),
            KEY `idx_document_id_apportion_status` (`usage_status`),
            KEY `idx_document_id_apportion_created_by` (`created_by`),
            KEY `idx_document_id_apportion_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // 添加默认管理员用户（不自动创建文档）
        $stmt = $pdo->prepare("INSERT IGNORE INTO `users` (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);

        // 不插入任何测试数据
    } catch (PDOException $e) {
        die("数据库初始化失败: " . $e->getMessage());
    }
}

/**
 * 获取数据库连接
 */
function get_db() {
    global $db_host, $db_name, $db_user, $db_pass, $db_charset, $db_port;
    static $db = null;
    
    if ($db === null) {
        init_database();
        $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $db = new PDO($dsn, $db_user, $db_pass, $options);
    }
    
    return $db;
}

/**
 * 安全过滤输入数据
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * 获取文档列表
 */
function get_documents($parent_document_id = null) {
    $db = get_db();
    
    if ($parent_document_id !== null) {
        if ($parent_document_id == 0) {
            $stmt = $db->query("SELECT * FROM documents WHERE parent_id = 0 AND del_status = 0 ORDER BY sort_order ASC, document_id ASC");
        } else {
            // 通过document_id找到对应的内部ID
            $stmt = $db->prepare("SELECT id FROM documents WHERE document_id = ? AND del_status = 0");
            $stmt->execute([$parent_document_id]);
            $internal_parent_id = $stmt->fetchColumn();
            
            if (!$internal_parent_id) {
                return [];
            }
            
            $stmt = $db->prepare("SELECT * FROM documents WHERE parent_id = ? AND del_status = 0 ORDER BY sort_order ASC, document_id ASC");
            $stmt->execute([$internal_parent_id]);
        }
    } else {
        $stmt = $db->query("SELECT * FROM documents WHERE del_status = 0 ORDER BY sort_order ASC, document_id ASC");
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取单个文档
 */
function get_document($document_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ? AND del_status = 0");
    $stmt->execute([$document_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 搜索文档
 */
function search_documents($keyword) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM documents WHERE (title LIKE ? OR content LIKE ?) AND del_status = 0 ORDER BY updated_at DESC");
    $keyword = "%{$keyword}%";
    $stmt->execute([$keyword, $keyword]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取回收站文档
 */
function get_deleted_documents() {
    $db = get_db();
    $stmt = $db->query("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.del_status = 1 ORDER BY d.deleted_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取用户列表
 */
function get_users() {
    $db = get_db();
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取单个用户
 */
function get_user($id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取用户通过用户名
 */
function get_user_by_username($username) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取文档版本历史
 */
function get_document_versions($document_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT dv.*, u.username FROM documents_version dv LEFT JOIN users u ON dv.created_by = u.id WHERE dv.document_id = ? ORDER BY dv.version_number DESC");
    $stmt->execute([$document_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取文档编辑日志
 */
function get_document_edit_log($document_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT el.*, u.username FROM edit_log el LEFT JOIN users u ON el.user_id = u.id WHERE el.document_id = ? ORDER BY el.created_at DESC");
    $stmt->execute([$document_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取最近创建的文档
 */
function get_recently_created_documents($limit = 5) {
    $db = get_db();
    $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.del_status = 0 ORDER BY d.created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取最近删除的文档
 */
function get_recently_deleted_documents($limit = 5) {
    $db = get_db();
    $stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.del_status = 1 ORDER BY d.deleted_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取版本最多的文档
 */
function get_documents_with_most_versions($limit = 5) {
    $db = get_db();
    $stmt = $db->query("SELECT d.*, u.username, COUNT(dv.id) as version_count FROM documents d LEFT JOIN users u ON d.user_id = u.id LEFT JOIN documents_version dv ON d.document_id = dv.document_id WHERE d.del_status = 0 GROUP BY d.document_id, d.title, u.username ORDER BY version_count DESC LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取操作最多的文档
 */
function get_documents_with_most_operations($limit = 5) {
    $db = get_db();
    $stmt = $db->query("SELECT d.*, u.username, COUNT(el.id) as operation_count FROM documents d LEFT JOIN users u ON d.user_id = u.id LEFT JOIN edit_log el ON d.document_id = el.document_id WHERE d.del_status = 0 GROUP BY d.document_id, d.title, u.username ORDER BY operation_count DESC LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取文档统计信息
 */
function get_document_stats() {
    $db = get_db();
    
    $total_docs = $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 0")->fetchColumn();
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $recent_docs = $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $deleted_docs = $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 1")->fetchColumn();
    
    return [
        'total_documents' => $total_docs,
        'total_users' => $total_users,
        'recent_documents' => $recent_docs,
        'deleted_documents' => $deleted_docs
    ];
}

/**
 * 记录编辑日志
 * @param array $changes 变更信息数组，仅在action='update'时有效
 * @param string $update_code 更新代码，用于关联相关记录
 */
function log_edit($document_id, $user_id, $action, $changes = [], $update_code = null) {
    $db = get_db();
    
    // 默认值设置
    $defaults = [
        'op_title' => 0,
        'op_content' => 0,
        'op_tags' => 0,
        'op_parent' => 0,
        'op_corder' => 0,
        'op_public' => 0,
        'op_formal' => 0
    ];
    
    // 合并变更信息
    $params = array_merge($defaults, $changes);
    
    // 仅在action为update时使用变更标记，其他action使用默认值
    if ($action !== 'update') {
        $params = $defaults;
    }
    
    $stmt = $db->prepare("INSERT INTO edit_log (document_id, user_id, action, op_title, op_content, op_tags, op_parent, op_corder, op_public, op_formal, update_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        $document_id, $user_id, $action,
        $params['op_title'],
        $params['op_content'],
        $params['op_tags'],
        $params['op_parent'],
        $params['op_corder'],
        $params['op_public'],
        $params['op_formal'],
        $update_code
    ]);
    
    // 记录日志后自动清理该文档的旧操作记录
    cleanup_operation_logs($document_id);
    
    return $result;
}

/**
 * 获取文档的编辑日志
 */
function get_edit_logs($document_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT el.*, u.username 
                        FROM edit_log el 
                        JOIN users u ON el.user_id = u.id 
                        WHERE el.document_id = ? 
                        ORDER BY el.created_at DESC");
    $stmt->execute([$document_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 保存文档版本
 * @param string $update_code 更新代码，用于关联相关记录
 */
function save_document_version($document_id, $title, $content, $user_id, $tags = null, $update_code = null) {
    $db = get_db();
    
    // 获取下一个版本号
    $stmt = $db->prepare("SELECT MAX(version_number) as max_version FROM documents_version WHERE document_id = ?");
    $stmt->execute([$document_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $next_version = ($result['max_version'] ?? 0) + 1;
    
    // 如果未提供tags，从documents表中获取
    if ($tags === null) {
        $doc = $db->prepare("SELECT tags FROM documents WHERE document_id = ?");
        $doc->execute([$document_id]);
        $tags = $doc->fetchColumn() ?: '';
    }
    
    // 插入新版本
    $stmt = $db->prepare("INSERT INTO documents_version (document_id, title, content, tags, created_by, version_number, update_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$document_id, $title, $content, $tags, $user_id, $next_version, $update_code]);
    
    // 清理旧版本，只保留最近配置的最大历史版本数
    global $max_history_versions;
    if ($max_history_versions > 0) {
        // 使用兼容的DELETE JOIN语法
        $stmt = $db->prepare("DELETE FROM documents_version 
                            WHERE document_id = ? 
                            AND id NOT IN (
                                SELECT * FROM (
                                    SELECT id FROM documents_version 
                                    WHERE document_id = ? 
                                    ORDER BY version_number DESC 
                                    LIMIT $max_history_versions
                                ) AS temp
                            )");
        $stmt->execute([$document_id, $document_id]);
    }
    
    return $next_version;
}

/**
 * 获取特定版本的文档
 */
function get_document_version($document_id, $version_number) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM documents_version 
                        WHERE document_id = ? AND version_number = ?");
    $stmt->execute([$document_id, $version_number]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 获取文档的当前版本号
 */
function get_current_version_number($document_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT COALESCE(MAX(version_number), 0) as current_version 
                        FROM documents_version 
                        WHERE document_id = ?");
    $stmt->execute([$document_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC)['current_version'];
}

/**
 * 检查用户是否已登录
 */
function check_login() {
    return isset($_SESSION['user_id']);
}

/**
 * 检查是否为管理员
 */
function check_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}



/**
 * 标记文档ID为已使用
 */
function mark_document_id_used($document_id, $user_id) {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO document_id_apportion (document_id, usage_status, created_by) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE usage_status = 1, created_by = ?");
    return $stmt->execute([$document_id, $user_id, $user_id]);
}



/**
 * 标记文档ID为已删除
 * @param int $document_id 要标记的文档ID
 * @return bool 是否成功
 */
function mark_document_id_deleted($document_id) {
    $db = get_db();
    
    try {
        $stmt = $db->prepare("UPDATE document_id_apportion SET usage_status = 3 WHERE document_id = ?");
        return $stmt->execute([$document_id]);
    } catch (Exception $e) {
        error_log("标记文档ID为已删除时出错: " . $e->getMessage());
        return false;
    }
}



/**
 * 获取已使用的文档ID列表
 */
function get_used_document_ids() {
    $db = get_db();
    $stmt = $db->query("SELECT document_id FROM document_id_apportion WHERE usage_status = 1 ORDER BY document_id ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}



/**
 * 获取下一个可用的文档ID
 * @return int 可用的文档ID
 */
function get_next_available_document_id() {
    $db = get_db();
    
    try {
        // 直接插入一条新记录，使用自增主键生成ID
        $user_id = $_SESSION['user_id'] ?? 1;
        $stmt = $db->prepare("INSERT INTO document_id_apportion (usage_status, created_by) VALUES (0, ?)");
        $stmt->execute([$user_id]);
        
        return (int)$db->lastInsertId();
        
    } catch (Exception $e) {
        // 如果发生错误，返回一个安全的默认值
        error_log("获取下一个可用文档ID时出错: " . $e->getMessage());
        return 1;
    }
}

/**
 * 标记文档ID为已分配
 * @param int $document_id 要标记的文档ID
 * @param int $user_id 用户ID
 * @return bool 是否成功
 */
function mark_document_id_allocated($document_id, $user_id) {
    $db = get_db();
    
    try {
        $stmt = $db->prepare("UPDATE document_id_apportion SET usage_status = 2, created_by = ?, updated_at = CURRENT_TIMESTAMP WHERE document_id = ? AND usage_status = 0");
        $stmt->execute([$user_id, $document_id]);
        
        // 只更新已存在的记录，不创建新记录
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("标记文档ID为已分配时出错: " . $e->getMessage());
        return false;
    }
}



/**
 * 获取已分配的文档ID列表
 * @return array 已分配的文档ID列表
 */
function get_allocated_document_ids() {
    $db = get_db();
    
    try {
        $stmt = $db->query("SELECT document_id FROM document_id_apportion WHERE usage_status = 2 ORDER BY document_id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("获取已分配的文档ID列表时出错: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取已删除的文档ID列表
 * @return array 已删除的文档ID列表
 */
function get_deleted_document_ids() {
    $db = get_db();
    
    try {
        $stmt = $db->query("SELECT document_id FROM document_id_apportion WHERE usage_status = 3 ORDER BY document_id ASC");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("获取已删除的文档ID列表时出错: " . $e->getMessage());
        return [];
    }
}

/**
 * 清理操作记录
 * 根据配置的最大操作记录数清理edit_log表中单个文档的旧记录
 */
function cleanup_operation_logs($document_id = null) {
    global $max_operation_logs;
    
    if ($max_operation_logs > 0 && $document_id !== null) {
        $db = get_db();
        
        try {
            // 删除单个文档超出限制的旧操作记录
            $stmt = $db->prepare("DELETE FROM edit_log 
                                WHERE document_id = ? 
                                AND id NOT IN (
                                    SELECT * FROM (
                                        SELECT id FROM edit_log 
                                        WHERE document_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT $max_operation_logs
                                    ) AS temp
                                )");
            $deleted_count = $stmt->execute([$document_id, $document_id]);
            
            if ($deleted_count > 0) {
                error_log("清理了文档 " . $document_id . " 的 " . $deleted_count . " 条旧操作记录");
            }
            
            return $deleted_count;
        } catch (Exception $e) {
            error_log("清理操作记录时出错: " . $e->getMessage());
            return 0;
        }
    }
    
    return 0;
}

/**
 * 获取文档ID使用统计
 * @return array 统计信息
 */
function get_document_id_stats() {
    $db = get_db();
    
    try {
        $stats = [];
        
        // 未使用
        $stmt = $db->query("SELECT COUNT(*) FROM document_id_apportion WHERE usage_status = 0");
        $stats['unused'] = (int)$stmt->fetchColumn();
        
        // 已使用
        $stmt = $db->query("SELECT COUNT(*) FROM document_id_apportion WHERE usage_status = 1");
        $stats['used'] = (int)$stmt->fetchColumn();
        
        // 已分配
        $stmt = $db->query("SELECT COUNT(*) FROM document_id_apportion WHERE usage_status = 2");
        $stats['allocated'] = (int)$stmt->fetchColumn();
        
        // 已删除
        $stmt = $db->query("SELECT COUNT(*) FROM document_id_apportion WHERE usage_status = 3");
        $stats['deleted'] = (int)$stmt->fetchColumn();
        
        // 总数
        $stmt = $db->query("SELECT COUNT(*) FROM document_id_apportion");
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // 最小和最大ID
        $stmt = $db->query("SELECT COALESCE(MIN(document_id), 0) as min_id, COALESCE(MAX(document_id), 0) as max_id FROM document_id_apportion");
        $range = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['min_id'] = (int)$range['min_id'];
        $stats['max_id'] = (int)$range['max_id'];
        
        return $stats;
    } catch (Exception $e) {
        error_log("获取文档ID使用统计时出错: " . $e->getMessage());
        return [
            'unused' => 0,
            'used' => 0,
            'allocated' => 0,
            'deleted' => 0,
            'total' => 0,
            'min_id' => 0,
            'max_id' => 0
        ];
    }
}