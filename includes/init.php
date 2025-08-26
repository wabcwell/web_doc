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
        $db->exec("CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            parent_id INTEGER DEFAULT 0,
            content TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
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
        
        // 添加默认数据
        $db->exec("INSERT OR IGNORE INTO users (username, password, role) VALUES 
            ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin')");
        
        $db->exec("INSERT OR IGNORE INTO categories (name, parent_id, content) VALUES 
            ('开始使用', 0, '# 欢迎使用文档系统\n\n这是一个简洁的文档管理系统。')");
        
        $db->exec("INSERT OR IGNORE INTO documents (category_id, title, content, user_id) VALUES 
            (1, '欢迎使用', '# 欢迎使用\n\n这是您的第一篇文档。', 1)");
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
 * 获取目录树
 */
function get_category_tree($parent_id = 0) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$parent_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 获取文档列表
 */
function get_documents($category_id = null) {
    $db = get_db();
    
    if ($category_id) {
        $stmt = $db->prepare("SELECT * FROM documents WHERE category_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$category_id]);
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
 * 获取单个目录
 */
function get_category($id) {
    $db = get_db();
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
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
 * 获取面包屑导航
 */
function get_breadcrumb($category_id) {
    $breadcrumb = [];
    
    while ($category_id > 0) {
        $category = get_category($category_id);
        if ($category) {
            array_unshift($breadcrumb, $category);
            $category_id = $category['parent_id'];
        } else {
            break;
        }
    }
    
    return $breadcrumb;
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