<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

// 检查是否为管理员，普通用户直接拒绝访问
if (!Auth::isAdmin()) {
    $_SESSION['error'] = '权限不足，需要管理员权限才能访问系统设置';
    header('Location: dashboard.php');
    exit();
}

// 读取配置文件
$config_file = __DIR__ . '/../config.php';
$config_content = file_get_contents($config_file);

// 解析当前配置值
$current_site_name = '';
$current_site_url = '';

if (preg_match('/\$site_name\s*=\s*["\'](.*?)["\']/', $config_content, $matches)) {
    $current_site_name = $matches[1];
}

if (preg_match('/\$site_url\s*=\s*["\'](.*?)["\']/', $config_content, $matches)) {
    $current_site_url = $matches[1];
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_site_name = trim($_POST['site_name'] ?? '');
    $new_site_url = trim($_POST['site_url'] ?? '');
    
    // 验证输入
    if (empty($new_site_name) || empty($new_site_url)) {
        $message = '请填写所有必填字段';
        $message_type = 'error';
    } elseif (!filter_var($new_site_url, FILTER_VALIDATE_URL)) {
        $message = '请输入有效的网站URL';
        $message_type = 'error';
    } else {
        // 更新配置文件
        $new_config_content = preg_replace(
            '/(\$site_name\s*=\s*["\']).*?(["\'])/',
            '$1' . addslashes($new_site_name) . '$2',
            $config_content
        );
        
        $new_config_content = preg_replace(
            '/(\$site_url\s*=\s*["\']).*?(["\'])/',
            '$1' . addslashes($new_site_url) . '$2',
            $new_config_content
        );
        
        if (file_put_contents($config_file, $new_config_content) !== false) {
            $message = '系统设置已成功更新';
            $message_type = 'success';
            
            // 更新当前显示值
            $current_site_name = $new_site_name;
            $current_site_url = $new_site_url;
        } else {
            $message = '配置文件写入失败，请检查文件权限';
            $message_type = 'error';
        }
    }
}

$title = '系统设置';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1>系统设置</h1>
                        
                        <!-- 消息提示 -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 设置表单 -->
                        <div class="card settings-form">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-sliders"></i> 基本设置
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="settings.php">
                                    <div class="mb-3">
                                        <label for="site_name" class="form-label">
                                            <i class="bi bi-globe"></i> 网站名称
                                        </label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="site_name" 
                                               name="site_name" 
                                               value="<?php echo htmlspecialchars($current_site_name); ?>" 
                                               required>
                                        <div class="form-text">网站的显示名称，将显示在页面标题和页眉</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="site_url" class="form-label">
                                            <i class="bi bi-link-45deg"></i> 网站URL
                                        </label>
                                        <input type="url" 
                                               class="form-control" 
                                               id="site_url" 
                                               name="site_url" 
                                               value="<?php echo htmlspecialchars($current_site_url); ?>" 
                                               required>
                                        <div class="form-text">网站的基础URL，用于生成完整的链接地址</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> 保存设置
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- 配置信息 -->
                        <div class="card settings-form mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i> 配置信息
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>配置文件路径：</strong><br>
                                    <code><?php echo realpath($config_file); ?></code>
                                </p>
                                <p class="mb-0">
                                    <strong>说明：</strong>
                                    所有配置修改将直接写入config.php文件，不涉及数据库操作。
                                </p>
                            </div>
                        </div>
        </div>
    </div>
</body>
</html>