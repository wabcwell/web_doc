<?php
/**
 * 系统初始化文件
 * 负责数据库连接和基础函数
 */

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 包含配置文件
require_once __DIR__ . '/../config.php';

/**
 * 初始化数据库
 */
function init_database() {
    global $db_path;
    
    if (!file_exists($db_path)) {
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建表
        $db->exec("CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER DEFAULT 0,
            title TEXT NOT NULL,
            content TEXT,
            sort_order INTEGER DEFAULT 0,
            view_count INTEGER DEFAULT 0,
            user_id INTEGER,
            is_public INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'editor',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 创建编辑日志表
        $db->exec("CREATE TABLE IF NOT EXISTS edit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            old_title TEXT,
            new_title TEXT,
            old_content TEXT,
            new_content TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // 创建文档版本表
        $db->exec("CREATE TABLE IF NOT EXISTS documents_version (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT,
            user_id INTEGER NOT NULL,
            version_number INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // 添加默认数据
        $db->exec("INSERT OR IGNORE INTO users (username, password, role) VALUES 
            ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin')");
        
        $db->exec("INSERT OR IGNORE INTO documents (parent_id, title, content, user_id) VALUES 
            (0, '欢迎使用', '# 欢迎使用\\n\\n这是您的第一篇文档。', 1)");
    }
}

/**
 * 获取数据库连接
 */
function get_db() {
    global $db_path;
    static $db = null;
    
    if ($db === null) {
        init_database();
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
function get_documents($parent_id = null) {
    $db = get_db();
    
    if ($parent_id !== null) {
        $stmt = $db->prepare("SELECT * FROM documents WHERE parent_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$parent_id]);
    } else {
        $stmt = $db->query("SELECT * FROM documents ORDER BY sort_order ASC, id ASC");
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取单个文档
 */
function get_document($id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 搜索文档
 */
function search_documents($keyword) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM documents WHERE title LIKE ? OR content LIKE ? ORDER BY created_at DESC");
    $keyword = "%$keyword%";
    $stmt->execute([$keyword, $keyword]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 记录编辑日志
 */
function log_edit($document_id, $user_id, $action, $old_title = null, $new_title = null, $old_content = null, $new_content = null) {
    $db = get_db();
    $stmt = $db->prepare("INSERT INTO edit_log (document_id, user_id, action, old_title, new_title, old_content, new_content) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$document_id, $user_id, $action, $old_title, $new_title, $old_content, $new_content]);
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
 */
function save_document_version($document_id, $title, $content, $user_id) {
    $db = get_db();
    
    // 获取下一个版本号
    $stmt = $db->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 as next_version 
                        FROM documents_version 
                        WHERE document_id = ?");
    $stmt->execute([$document_id]);
    $next_version = $stmt->fetch(PDO::FETCH_ASSOC)['next_version'];
    
    // 插入新版本
    $stmt = $db->prepare("INSERT INTO documents_version (document_id, title, content, user_id, version_number) 
                        VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$document_id, $title, $content, $user_id, $next_version]);
    
    // 清理旧版本，只保留最近20个
    $stmt = $db->prepare("DELETE FROM documents_version 
                        WHERE document_id = ? 
                        AND id NOT IN (
                            SELECT id FROM documents_version 
                            WHERE document_id = ? 
                            ORDER BY version_number DESC 
                            LIMIT 20
                        )");
    $stmt->execute([$document_id, $document_id]);
    
    return $next_version;
}

/**
 * 获取文档的版本历史
 */
function get_document_versions($document_id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT dv.*, u.username 
                        FROM documents_version dv 
                        JOIN users u ON dv.user_id = u.id 
                        WHERE dv.document_id = ? 
                        ORDER BY dv.version_number DESC 
                        LIMIT 20");
    $stmt->execute([$document_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

?>