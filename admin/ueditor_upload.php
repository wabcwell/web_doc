<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 获取action参数
$action = $_GET['action'] ?? '';

// 处理配置请求（无需权限检查）
if ($action === 'config') {
    // 设置正确的Content-Type
    header('Content-Type: application/json; charset=utf-8');
    
    // 读取配置文件
    $configPath = __DIR__ . '/documents/ueditor_config.json';
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        if ($config) {
            // 修正路径格式 - 使用相对于根目录的路径
            $config['imagePathFormat'] = '/uploads/images/{yyyy}{mm}{dd}/{time}{rand:6}';
            $config['filePathFormat'] = '/uploads/files/{yyyy}{mm}{dd}/{time}{rand:6}';
            $config['videoPathFormat'] = '/uploads/videos/{yyyy}{mm}{dd}/{time}{rand:6}';
            
            // 确保所有必需的配置项都存在
            $config['imageUrlPrefix'] = '';
            $config['scrawlUrlPrefix'] = '';
            $config['snapscreenUrlPrefix'] = '';
            $config['catcherUrlPrefix'] = '';
            $config['videoUrlPrefix'] = '';
            $config['fileUrlPrefix'] = '';
            
            echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }
    
    // 默认配置
    $config = [
        'imageActionName' => 'uploadimage',
        'imageFieldName' => 'upfile',
        'imageMaxSize' => 10485760,
        'imageAllowFiles' => ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'],
        'imageCompressEnable' => true,
        'imageCompressBorder' => 1600,
        'imageInsertAlign' => 'none',
        'imageUrlPrefix' => '',
        'imagePathFormat' => '/uploads/images/{yyyy}{mm}{dd}/{time}{rand:6}',
        'scrawlActionName' => 'uploadscrawl',
        'scrawlFieldName' => 'upfile',
        'scrawlMaxSize' => 10485760,
        'videoActionName' => 'uploadvideo',
        'videoFieldName' => 'upfile',
        'videoMaxSize' => 104857600,
        'videoAllowFiles' => ['.mp4', '.avi', '.wmv', '.mov', '.flv', '.webm'],
        'fileActionName' => 'uploadfile',
        'fileFieldName' => 'upfile',
        'fileMaxSize' => 52428800,
        'fileAllowFiles' => ['.doc', '.docx', '.xls', '.xlsx', '.pdf', '.txt', '.md', '.zip', '.rar']
    ];
    echo json_encode($config);
    exit;
}

// 检查用户权限（仅对上传操作）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 调试信息
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['state' => '无权限上传 - 请先登录']);
    exit;
}

// 根据action处理不同的上传请求
switch ($action) {
    case 'uploadimage':
    case 'uploadscrawl':
    case 'uploadvideo':
    case 'uploadfile':
        handleUpload();
        break;
        
    case 'listimage':
    case 'listfile':
        handleList();
        break;
        
    case 'catchimage':
        handleCatch();
        break;
        
    default:
        handleUpload();
        break;
}

function handleUpload() {
    if (!isset($_FILES['upfile'])) {
        echo json_encode(['state' => '未找到上传文件']);
        return;
    }
    
    $file = $_FILES['upfile'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = [
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件上传不完整',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '上传被扩展中断'
        ];
        echo json_encode(['state' => $errorMsg[$file['error']] ?? '上传错误']);
        return;
    }
    
    // 获取上传类型
    $action = $_GET['action'] ?? 'uploadimage';
    
    // 根据action设置不同的文件类型和大小限制
    switch ($action) {
        case 'uploadimage':
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            $subDir = 'images/';
            break;
        case 'uploadvideo':
            $allowedTypes = ['video/mp4', 'video/avi', 'video/wmv', 'video/mpeg', 'video/quicktime', 'video/webm'];
            $maxSize = 100 * 1024 * 1024; // 100MB
            $subDir = 'videos/';
            break;
        case 'uploadfile':
            $allowedTypes = [
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'text/markdown', 'application/zip', 'application/x-rar-compressed'
            ];
            $maxSize = 50 * 1024 * 1024; // 50MB
            $subDir = 'files/';
            break;
        default:
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
            $maxSize = 10 * 1024 * 1024; // 10MB
            $subDir = 'images/';
    }
    
    // 验证文件类型 - 使用文件扩展名作为替代方案
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extToMime = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'webp' => 'image/webp',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt' => 'text/plain',
        'md' => 'text/markdown'
    ];
    
    if (!in_array($fileExt, ['zip', 'rar', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'md']) && 
        (!isset($extToMime[$fileExt]) || !in_array($extToMime[$fileExt], $allowedTypes))) {
        echo json_encode(['state' => '不支持的文件类型: ' . $fileExt]);
        return;
    }
    
    // 对于文件上传，直接使用扩展名验证
    $allowedExts = [];
    switch ($action) {
        case 'uploadfile':
            $allowedExts = ['zip', 'rar', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'md'];
            break;
        case 'uploadimage':
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
            break;
        case 'uploadvideo':
            $allowedExts = ['mp4', 'avi', 'wmv', 'mov', 'flv', 'webm', 'mkv'];
            break;
    }
    
    if (!in_array($fileExt, $allowedExts)) {
        echo json_encode(['state' => '不支持的文件类型: ' . $fileExt]);
        return;
    }
    
    // 验证文件大小
    if ($file['size'] > $maxSize) {
        $maxSizeMB = $maxSize / (1024 * 1024);
        echo json_encode(['state' => '文件过大，最大支持' . $maxSizeMB . 'MB']);
        return;
    }
    
    // 创建上传目录
    $uploadDir = __DIR__ . '/../uploads/';
    $dateDir = date('Ymd') . '/';
    $fullDir = $uploadDir . $subDir . $dateDir;
    
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }
    
    // 生成文件名
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = $fullDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // 生成相对URL - 便于部署
        $url = '/uploads/' . $subDir . $dateDir . $filename;
        
        // 向file_upload表插入数据
        try {
            $db = new PDO('sqlite:' . __DIR__ . '/../database/docs.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 根据文件扩展名确定文件类型
            $fileType = 'other';
            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'bmp':
                case 'webp':
                    $fileType = 'image';
                    break;
                case 'mp4':
                case 'avi':
                case 'wmv':
                case 'mov':
                case 'flv':
                case 'webm':
                case 'mkv':
                    $fileType = 'video';
                    break;
                case 'mp3':
                case 'wav':
                case 'ogg':
                case 'flac':
                    $fileType = 'audio';
                    break;
                case 'pdf':
                case 'doc':
                case 'docx':
                case 'xls':
                case 'xlsx':
                case 'txt':
                case 'md':
                    $fileType = 'document';
                    break;
                case 'zip':
                case 'rar':
                case '7z':
                case 'tar':
                case 'gz':
                    $fileType = 'archive';
                    break;
            }
            
            // 获取文档ID
            $document_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
            
            // 获取UEditor上传表单中的描述信息（title字段），如果没有则留空
            $file_description = isset($_POST['title']) ? trim($_POST['title']) : '';
            
            $stmt = $db->prepare("INSERT INTO file_upload (file_type, file_format, file_size, file_path, uploaded_by, document_id, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $fileType,
                $extension,
                $file['size'],
                $url,
                $_SESSION['user_id'] ?? 1,
                $document_id,
                $file_description
            ]);
            
        } catch (PDOException $e) {
            // 记录错误但不影响上传功能
            error_log("插入file_upload表失败: " . $e->getMessage());
        }
        
        echo json_encode([
            'state' => 'SUCCESS',
            'url' => $url,
            'title' => $file['name'],
            'original' => $file['name'],
            'type' => '.' . $extension,
            'size' => $file['size']
        ]);
    } else {
        echo json_encode(['state' => '文件保存失败，请检查目录权限']);
    }
}

function handleList() {
    // 返回空列表，避免404
    echo json_encode([
        'state' => 'SUCCESS',
        'list' => [],
        'start' => 0,
        'total' => 0
    ]);
}

function handleCatch() {
    // 远程抓图功能
    echo json_encode([
        'state' => 'SUCCESS',
        'list' => []
    ]);
}
?>