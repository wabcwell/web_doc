<?php
/**
 * 系统配置文件
 * 只包含基础配置信息，不包含任何函数
 */

// 数据库配置
$db_path = __DIR__ . '/database/docs.db';

// 网站配置
$logo_type = 'text'; //text或者img
$site_name = '文档中心1';
$site_subtitle = '简洁高效的文档管理系统'; // 网站副标题
$site_url = 'http://localhost:8000';
$logo_path = '/uploads/logo/logo_1756211306.png';  // Logo文件路径
// 上传配置
$upload_path = __DIR__ . '/uploads';
$upload_url = '/uploads';

// 其他配置
$timezone = 'Asia/Shanghai';
$items_per_page = 20;
$max_history_versions = 22; // 单文档最大历史版本数，0表示不限制
$max_operation_logs = 100; // 单文档最大操作记录数，0表示不限制

// 设置PHP默认时区
date_default_timezone_set($timezone);

?>