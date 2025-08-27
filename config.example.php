<?php
/**
 * 系统配置示例文件
 * 基于config.php创建的配置模板
 * 
 * 使用方法：
 * 1. 将此文件复制为config.php
 * 2. 根据实际环境修改配置值
 * 3. 确保数据库和上传目录有正确权限
 */

// =============================================================================
// 数据库配置
// =============================================================================
// SQLite数据库文件路径（建议使用绝对路径）
$db_path = __DIR__ . '/database/docs.db';

// MySQL配置示例（如需使用MySQL，请取消注释并配置）
// $db_host = 'localhost';
// $db_name = 'docs_system';
// $db_user = 'your_username';
// $db_pass = 'your_password';
// $db_charset = 'utf8mb4';

// =============================================================================
// 网站基本信息配置
// =============================================================================
// Logo类型：text（文字）或 img（图片）
$logo_type = 'text';

// 网站名称（显示在浏览器标题和页面头部）
$site_name = '文档中心';

// 网站URL（必须以http://或https://开头，末尾不要加/）
$site_url = 'http://localhost:8000';

// Logo文件路径（当$logo_type为'img'时使用）
// 建议使用相对路径，文件放在uploads/logo/目录下
$logo_path = '/uploads/logo/logo_example.png';

// =============================================================================
// 文件上传配置
// =============================================================================
// 上传文件存储目录（绝对路径）
$upload_path = __DIR__ . '/uploads';

// 上传文件访问URL（相对路径）
$upload_url = '/uploads';

// 允许上传的文件类型
$allowed_file_types = [
    'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md'],
    'archive' => ['zip', 'rar', '7z', 'tar', 'gz']
];

// 单个文件最大大小（单位：MB）
$max_file_size = 10;

// =============================================================================
// 系统功能配置
// =============================================================================
// 时区设置
$timezone = 'Asia/Shanghai';

// 每页显示文档数量
$items_per_page = 20;

// 是否启用用户注册功能
$enable_registration = false;

// 是否启用文档回收站功能
$enable_recycle_bin = true;

// 文档版本历史保留天数（0为不限制）
$history_retention_days = 30;

// =============================================================================
// 安全相关配置
// =============================================================================
// 会话超时时间（单位：秒，默认2小时）
$session_timeout = 7200;

// 密码最小长度要求
$min_password_length = 6;

// 是否启用验证码功能
$enable_captcha = false;

// 允许上传的文件MIME类型白名单
$allowed_mime_types = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/markdown', 'application/zip', 'application/x-rar-compressed'
];

// =============================================================================
// 邮件配置（可选）
// =============================================================================
// SMTP服务器配置示例
// $smtp_host = 'smtp.gmail.com';
// $smtp_port = 587;
// $smtp_username = 'your_email@gmail.com';
// $smtp_password = 'your_app_password';
// $smtp_from_email = 'noreply@yourdomain.com';
// $smtp_from_name = $site_name;

// =============================================================================
// 高级配置
// =============================================================================
// 是否启用调试模式（开发环境可设为true）
$debug_mode = false;

// 缓存目录路径
$cache_path = __DIR__ . '/cache';

// 日志文件路径
$log_path = __DIR__ . '/logs/app.log';

// CDN加速域名（可选，留空则使用本地资源）
$cdn_url = '';

// 是否启用Gzip压缩
$enable_gzip = true;

// =============================================================================
// 环境检测与自动配置
// =============================================================================
// 自动检测HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $protocol = 'https://';
} else {
    $protocol = 'http://';
}

// 自动检测域名（仅在$site_url使用默认值时生效）
if ($site_url === 'http://localhost:8000') {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $site_url = $protocol . $host;
}

// =============================================================================
// 目录权限检查清单
// =============================================================================
// 部署前请确保以下目录存在且可写：
// - uploads/ 目录: 755
// - cache/ 目录: 755  
// - logs/ 目录: 755
// - database/ 目录: 755
// 
// 如果数据库不存在，确保database目录可写以便自动创建

// =============================================================================
// 配置说明
// =============================================================================
/*
 * 部署步骤：
 * 1. 复制此文件为config.php
 * 2. 修改数据库路径和网站URL
 * 3. 确保uploads、cache、logs目录存在且可写
 * 4. 设置正确的文件权限：
 *    - uploads/ 目录: 755
 *    - cache/ 目录: 755
 *    - logs/ 目录: 755
 *    - database/ 目录: 755
 * 5. 如果使用MySQL，配置数据库连接信息
 * 6. 测试文件上传功能
 * 
 * 安全建议：
 * - 定期备份数据库和上传文件
 * - 限制上传文件类型和大小
 * - 使用强密码
 * - 定期更新系统和依赖
 * 
 * 目录权限检查：
 * 部署前请检查以下目录权限：
 * - uploads/ 目录: 必须可写用于文件上传
 * - cache/ 目录: 必须可写用于缓存功能
 * - logs/ 目录: 必须可写用于日志记录
 * - database/ 目录: 必须可写用于数据库操作
 */