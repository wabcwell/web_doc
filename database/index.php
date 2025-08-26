<?php
// 禁止直接访问数据库目录
http_response_code(403);
echo "403 Forbidden - Access Denied";
exit();