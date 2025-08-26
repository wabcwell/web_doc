<?php
// 完整的图片上传演示

// 1. 处理图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['logo_image'])) {
    $upload_dir = __DIR__ . '/uploads/logo/';
    
    // 确保目录存在
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['logo_image'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // 获取文件扩展名
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'logo_' . time() . '.' . $ext;
        $upload_path = $upload_dir . $filename;
        
        // 2. 移动上传的文件
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $new_logo_path = '/uploads/logo/' . $filename;
            
            // 3. 更新config.php
            $config_path = __DIR__ . '/config.php';
            if (file_exists($config_path)) {
                $config_content = file_get_contents($config_path);
                
                // 使用正则表达式更新logo_path
                $config_content = preg_replace(
                    '/(\$logo_path\s*=\s*["\'])(.*?)(["\'])/',
                    '$1' . $new_logo_path . '$3',
                    $config_content
                );
                
                file_put_contents($config_path, $config_content);
                
                // 4. 重新读取并显示
                include $config_path;
                
                echo "<div style='padding: 20px; background: #f0f0f0; margin: 20px;'>";
                echo "<h2>上传成功！</h2>";
                echo "<p>上传后的图片路径: <strong>{$new_logo_path}</strong></p>";
                echo "<p>config.php中的logo_path已更新为: <strong>{$logo_path}</strong></p>";
                echo "<img src='{$logo_path}' style='max-width: 200px; border: 1px solid #ccc;'>";
                echo "<br><a href='demo_upload.php'>返回继续上传</a>";
                echo "</div>";
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>图片上传演示</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .upload-form { max-width: 400px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>图片上传演示</h1>
    <div class="upload-form">
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="logo_image">选择Logo图片:</label>
                <input type="file" name="logo_image" id="logo_image" accept="image/*" required>
            </div>
            <button type="submit">上传并更新配置</button>
        </form>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #f9f9f9;">
        <h3>当前配置:</h3>
        <?php
        include 'config.php';
        echo "<p>当前logo_path: <strong>{$logo_path}</strong></p>";
        if ($logo_path && file_exists(__DIR__ . $logo_path)) {
            echo "<img src='{$logo_path}' style='max-width: 150px; border: 1px solid #ccc;'>";
        } else {
            echo "<p>暂无Logo图片</p>";
        }
        ?>
    </div>
</body>
</html>