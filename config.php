<?php
/**
 * 系统配置文件
 * 只包含基础配置信息，不包含任何函数
 */

// 数据库配置
$db_path = __DIR__ . '/database/docs.db';

// 网站配置
$site_name = '简洁文档系统';
$site_url = 'http://localhost:8000';

// 上传配置
$upload_path = __DIR__ . '/uploads';
$upload_url = '/uploads';

// 其他配置
$timezone = 'Asia/Shanghai';
$items_per_page = 20;

?>