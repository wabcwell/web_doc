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

/**
 * 初始化数据库 - 严格按照实际数据库结构创建
 */
function init_database() {
    global $db_path;
    
    if (!file_exists($db_path)) {
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 严格按照实际数据库结构创建表
        
        // 创建documents表 - 与docs.db完全一致
        $db->exec("CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT,
            parent_id INTEGER,
            sort_order INTEGER DEFAULT 0,
            user_id INTEGER DEFAULT 1,
            is_public INTEGER DEFAULT 1,
            tags TEXT,
            view_count INTEGER DEFAULT 0,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            del_status INTEGER DEFAULT 0,
            deleted_at TEXT,
            is_formal INTEGER DEFAULT 0,
            update_code TEXT,
            FOREIGN KEY (parent_id) REFERENCES documents(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        
        // 创建users表 - 与docs.db完全一致
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'editor',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 创建edit_log表 - 与docs.db完全一致
        $db->exec("CREATE TABLE IF NOT EXISTS edit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            action TEXT NOT NULL CHECK (action IN ('create', 'update', 'delete', 'rollback', 'restore')),
            op_title INTEGER DEFAULT 0 CHECK (op_title IN (0, 1)),
            op_content INTEGER DEFAULT 0 CHECK (op_content IN (0, 1)),
            op_tags INTEGER DEFAULT 0 CHECK (op_tags IN (0, 1)),
            op_parent INTEGER DEFAULT 0 CHECK (op_parent IN (0, 1)),
            op_corder INTEGER DEFAULT 0 CHECK (op_corder IN (0, 1)),
            op_public INTEGER DEFAULT 0 CHECK (op_public IN (0, 1, 2)),
            op_formal INTEGER DEFAULT 0 CHECK (op_formal IN (0, 1, 2)),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            temp_action TEXT,
            update_code TEXT,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // 创建documents_version表 - 与docs.db完全一致
        $db->exec("CREATE TABLE IF NOT EXISTS documents_version (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT,
            tags TEXT,
            version_number INTEGER NOT NULL,
            created_by INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            update_code TEXT,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // 创建索引 - 与docs.db完全一致
        $db->exec("CREATE INDEX IF NOT EXISTS idx_documents_version_document_id ON documents_version(document_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_documents_version_created_by ON documents_version(created_by)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_edit_log_document_id ON edit_log(document_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_edit_log_user_id ON edit_log(user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_edit_log_created_at ON edit_log(created_at)");

        // 创建file_upload表 - 文件上传管理
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

        // 创建file_upload表索引
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_document_id ON file_upload(document_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_uploaded_by ON file_upload(uploaded_by)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_file_type ON file_upload(file_type)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_uploaded_at ON file_upload(uploaded_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_file_upload_del_status ON file_upload(del_status)");

        // 创建触发器 - 自动更新updated_at字段
        $db->exec("CREATE TRIGGER IF NOT EXISTS update_file_upload_timestamp 
                   AFTER UPDATE ON file_upload
                   BEGIN
                       UPDATE file_upload SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
                   END");
        
        // 添加默认数据
        $db->exec("INSERT OR IGNORE INTO users (username, password, role) VALUES 
            ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin')");
        
        $db->exec("INSERT OR IGNORE INTO documents (parent_id, title, content, user_id) VALUES 
            (0, '欢迎使用', '# 欢迎使用\\n\\n这是您的第一篇文档。', 1)");

        // 插入测试文件数据
        $db->exec("INSERT OR IGNORE INTO file_upload (file_type, file_format, file_size, file_path, document_id, description, notes, uploaded_by) VALUES 
            ('image', 'jpg', 102400, 'uploads/test1.jpg', 1, '测试图片1', '这是测试图片的描述', 1),
            ('document', 'pdf', 204800, 'uploads/test2.pdf', 1, '测试文档', 'PDF测试文档', 1),
            ('image', 'png', 51200, 'uploads/test3.png', NULL, '未关联的测试图片', '这是一个未关联到文档的测试图片', 1),
            ('video', 'mp4', 1048576, 'uploads/test4.mp4', 1, '测试视频', '测试视频文件', 1),
            ('archive', 'zip', 307200, 'uploads/test5.zip', NULL, '测试压缩包', '包含多个文件的测试压缩包', 1)");
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
        $stmt = $db->prepare("SELECT * FROM documents WHERE parent_id = ? AND del_status = 0 ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$parent_id]);
    } else {
        $stmt = $db->query("SELECT * FROM documents WHERE del_status = 0 ORDER BY sort_order ASC, id ASC");
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取单个文档
 */
function get_document($id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ? AND del_status = 0");
    $stmt->execute([$id]);
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
    $stmt = $db->query("SELECT d.*, u.username, COUNT(dv.id) as version_count FROM documents d LEFT JOIN users u ON d.user_id = u.id LEFT JOIN documents_version dv ON d.id = dv.document_id WHERE d.del_status = 0 GROUP BY d.id, d.title, u.username ORDER BY version_count DESC LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取操作最多的文档
 */
function get_documents_with_most_operations($limit = 5) {
    $db = get_db();
    $stmt = $db->query("SELECT d.*, u.username, COUNT(el.id) as operation_count FROM documents d LEFT JOIN users u ON d.user_id = u.id LEFT JOIN edit_log el ON d.id = el.document_id WHERE d.del_status = 0 GROUP BY d.id, d.title, u.username ORDER BY operation_count DESC LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取文档统计信息
 */
function get_document_stats() {
    $db = get_db();
    
    $total_docs = $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 0")->fetchColumn();
    $total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $recent_docs = $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 0 AND created_at >= datetime('now', '-7 days')")->fetchColumn();
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
    return $stmt->execute([
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
        $doc = $db->prepare("SELECT tags FROM documents WHERE id = ?");
        $doc->execute([$document_id]);
        $tags = $doc->fetchColumn() ?: '';
    }
    
    // 插入新版本
    $stmt = $db->prepare("INSERT INTO documents_version (document_id, title, content, tags, created_by, version_number, update_code) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$document_id, $title, $content, $tags, $user_id, $next_version, $update_code]);
    
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