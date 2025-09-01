<?php
session_start();

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 读取配置文件
$config_path = __DIR__ . '/../config.php';
if (!file_exists($config_path)) {
    die('配置文件不存在');
}

// 包含配置文件以获取当前配置
include $config_path;

// 获取当前配置值
$current_site_name = $site_name;
$current_site_subtitle = $site_subtitle ?? '简洁高效的文档管理系统';
$current_site_url = $site_url;
$current_logo_type = $logo_type;
$current_logo_path = $logo_path;

$message = '';
$message_type = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_site_name = trim($_POST['site_name'] ?? '');
    $new_site_subtitle = trim($_POST['site_subtitle'] ?? '');
    $new_site_url = trim($_POST['site_url'] ?? '');
    $new_logo_type = trim($_POST['logo_type'] ?? '');
        
        // 验证输入
        if (empty($new_site_name)) {
            $message = '网站名称不能为空';
            $message_type = 'error';
        } elseif (empty($new_site_subtitle)) {
            $message = '网站副标题不能为空';
            $message_type = 'error';
        } elseif (!filter_var($new_site_url, FILTER_VALIDATE_URL)) {
            $message = '请输入有效的网站URL';
            $message_type = 'error';
        } elseif (!in_array($new_logo_type, ['text', 'img'])) {
            $message = '请选择有效的Logo类型';
            $message_type = 'error';
        } else {
            $new_logo_path = $current_logo_path; // 默认保持现有路径
        
        // 处理Logo上传（与logo类型无关）
        if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK && !empty($_FILES['logo_image']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/logo/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['logo_image'];
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // 检查文件扩展名
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // 检查MIME类型（使用$_FILES数组中的type）
            $mime_type = $file['type'];
            
            if (!in_array($file_extension, $allowed_extensions) || !in_array($mime_type, $allowed_mime_types)) {
                $message = '请上传有效的图片文件（JPG、PNG、GIF、WEBP）';
                $message_type = 'error';
            } elseif ($file['size'] > $max_size) {
                $message = '文件大小不能超过2MB';
                $message_type = 'error';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'logo_' . time() . '.' . strtolower($ext);
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $new_logo_path = '/uploads/logo/' . $filename;
                    
                    // 删除旧logo（保留默认logo）
                    $old_logo_path = __DIR__ . '/../' . ltrim($current_logo_path, '/');
                    if (file_exists($old_logo_path) && basename($old_logo_path) !== 'default-logo.png' && $current_logo_path !== '') {
                        unlink($old_logo_path);
                    }
                    
                    $message = 'Logo上传成功！';
                    $message_type = 'success';
                } else {
                    $message = '文件上传失败，请重试';
                    $message_type = 'error';
                }
            }
        }
        
        // 如果验证通过，更新配置文件
        if ($message_type !== 'error') {
            // 读取配置文件内容
            $config_content = file_get_contents($config_path);
            
            // 更新配置
            $config_content = preg_replace(
                '/(\$site_name\s*=\s*["\']).*?(["\'])/',
                '$1' . addslashes($new_site_name) . '$2',
                $config_content
            );

            $config_content = preg_replace(
                '/(\$site_subtitle\s*=\s*["\']).*?(["\'])/',
                '$1' . addslashes($new_site_subtitle) . '$2',
                $config_content
            );
            
            $config_content = preg_replace(
                '/(\$site_url\s*=\s*["\']).*?(["\'])/',
                '$1' . addslashes($new_site_url) . '$2',
                $config_content
            );

            $config_content = preg_replace(
                '/(\$logo_type\s*=\s*["\']).*?(["\'])/',
                '$1' . addslashes($new_logo_type) . '$2',
                $config_content
            );

            $config_content = preg_replace(
                '/(\$logo_path\s*=\s*["\']).*?(["\'])/',
                '$1' . addslashes($new_logo_path) . '$2',
                $config_content
            );
            
            if (file_put_contents($config_path, $config_content) !== false) {
                $message = '系统设置已成功更新';
                $message_type = 'success';
                
                // 重新读取配置
                include $config_path;
                $current_site_name = $site_name;
                $current_site_subtitle = $site_subtitle;
                $current_site_url = $site_url;
                $current_logo_type = $logo_type;
                $current_logo_path = $logo_path;
            } else {
                $message = '配置文件写入失败，请检查文件权限';
                $message_type = 'error';
            }
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
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1><i class="bi bi-gear"></i> 系统设置</h1>
            
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
                    <form method="POST" action="settings.php" enctype="multipart/form-data">
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
                            <label for="site_subtitle" class="form-label">
                                <i class="bi bi-text-paragraph"></i> 网站副标题
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="site_subtitle" 
                                   name="site_subtitle" 
                                   value="<?php echo htmlspecialchars($current_site_subtitle ?? '简洁高效的文档管理系统'); ?>" 
                                   required>
                            <div class="form-text">网站的副标题，将显示在网站名称下方，简短描述网站功能</div>
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

                        <div class="mb-3">
                            <label for="logo_type" class="form-label">
                                <i class="bi bi-image"></i> Logo类型
                            </label>
                            <select class="form-select" id="logo_type" name="logo_type" required>
                                        <option value="text" <?php echo $current_logo_type === 'text' ? 'selected' : ''; ?>>文本Logo</option>
                                        <option value="img" <?php echo $current_logo_type === 'img' ? 'selected' : ''; ?>>图片Logo</option>
                                    </select>
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> 
                                选择文本Logo将显示网站名称，选择图片Logo将显示上传的图片
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="logo_image" class="form-label">
                                <i class="bi bi-upload"></i> Logo图片上传
                            </label>
                            <input type="file" 
                                   class="form-control" 
                                   id="logo_image" 
                                   name="logo_image" 
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> 
                                支持 JPG、PNG、GIF、WEBP 格式，最大 2MB
                            </div>
                            
                            <!-- 当前Logo预览 -->
                            <?php if (!empty($current_logo_path)): ?>
                                <div class="mt-3">
                                    <label class="form-label">当前Logo预览</label>
                                    <div class="border rounded p-3 text-center">
                                        <img src="<?php echo htmlspecialchars($current_logo_path); ?>" 
                                             alt="当前Logo" 
                                             class="img-fluid" 
                                             style="max-height: 100px;">
                                        <div class="mt-2 text-muted small">
                                            <i class="bi bi-link-45deg"></i> <?php echo htmlspecialchars($current_logo_path); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <div class="border rounded p-3 text-center text-muted">
                                        <i class="bi bi-image" style="font-size: 2rem;"></i>
                                        <div class="mt-2">暂无Logo图片</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> 保存设置
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>