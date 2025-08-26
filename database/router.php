<?php
// 数据库保护路由
if (preg_match('/\.db$/', $_SERVER['REQUEST_URI'])) {
    http_response_code(403);
    echo "403 Forbidden - Database Access Denied";
    exit();
}

// 其他文件正常处理
return false;