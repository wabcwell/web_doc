<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';

// 检查用户权限
Auth::requireLogin();

// 获取文件ID
$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// 获取文件信息
$db = get_db();

// 获取当前文件
$stmt = $db->prepare("SELECT f.*, u.username, d.title as document_title FROM file_upload f LEFT JOIN users u ON f.uploaded_by = u.id LEFT JOIN documents d ON f.document_id = d.document_id WHERE f.id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    header('Location: index.php');
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['description'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // 更新文件信息
    $stmt = $db->prepare("UPDATE file_upload SET description = ?, notes = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$description, $notes, $id]);
    
    header('Location: edit.php?id=' . $id . '&success=update');
    exit;
}

$title = '编辑文件';
include '../sidebar.php';

// 辅助函数：获取文件类型的中文名称
function get_file_type_chinese($file_type): string {
    $type_map = [
        'image' => '图片',
        'video' => '视频',
        'audio' => '音频',
        'document' => '文档',
        'archive' => '压缩包',
        'other' => '其他'
    ];
    return $type_map[$file_type] ?? $file_type;
}

// 辅助函数：格式化文件大小
function format_file_size($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }
    
    return round($size, 2) . ' ' . $units[$unitIndex];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-file-earmark"></i> 编辑文件</h1>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> 返回文件管理
                </a>
            </div>
            
            <?php if (isset($_GET['success']) && $_GET['success'] == 'update'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <strong>成功！</strong> 文件信息已更新
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- 文件预览 -->
                <div class="col-12 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">文件预览</h5>
                            <a href="/<?php echo ltrim($file['file_path'], '/'); ?>" target="_blank" class="btn btn-download btn-sm">
                                <i class="bi bi-download"></i> 下载
                            </a>
                        </div>
                        <div class="card-body text-center">
                            <?php if ($file['file_type'] == 'image'): ?>
                                <img src="/<?php echo ltrim($file['file_path'], '/'); ?>" class="img-fluid mb-3" alt="<?php echo htmlspecialchars($file['alias'] ?? basename($file['file_path'])); ?>">
                            <?php else: ?>
                                <?php if ($file['file_format'] === 'pdf'): ?>
                                    <a href="/<?php echo ltrim($file['file_path'], '/'); ?>" target="_blank" style="text-decoration: none;">
                                        <div class="file-icon-display mb-3">
                                            <i class="bi bi-file-earmark-pdf-fill" style="font-size: 4rem; color: #dc3545;"></i>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="file-icon-display mb-3">
                                        <?php 
                                        // 根据文件格式设置不同图标和颜色
                                        $icon_map = [
                                            'doc' => ['icon' => 'bi-file-earmark-word-fill', 'color' => '#1E90FF'],
                                            'docx' => ['icon' => 'bi-file-earmark-word-fill', 'color' => '#1E90FF'],
                                            'xls' => ['icon' => 'bi-file-earmark-excel-fill', 'color' => '#32CD32'],
                                            'xlsx' => ['icon' => 'bi-file-earmark-excel-fill', 'color' => '#32CD32'],
                                            'ppt' => ['icon' => 'bi-file-earmark-ppt-fill', 'color' => '#FF8C00'],
                                            'pptx' => ['icon' => 'bi-file-earmark-ppt-fill', 'color' => '#FF8C00'],
                                            'zip' => ['icon' => 'bi-file-earmark-zip-fill', 'color' => '#800080'],
                                            'rar' => ['icon' => 'bi-file-earmark-zip-fill', 'color' => '#800080'],
                                            'txt' => ['icon' => 'bi-file-earmark-text-fill', 'color' => '#6c757d'],
                                            'md' => ['icon' => 'bi-file-earmark-text-fill', 'color' => '#6c757d']
                                        ];
                                        
                                        // 默认图标和颜色
                                        $icon_class = 'bi-file-earmark-fill';
                                        $icon_color = '#6c757d';
                                        
                                        // 如果有特定格式的图标设置，则使用
                                        if (isset($icon_map[$file['file_format']])) {
                                            $icon_class = $icon_map[$file['file_format']]['icon'];
                                            $icon_color = $icon_map[$file['file_format']]['color'];
                                        }
                                        ?>
                                        <i class="<?php echo $icon_class; ?>" style="font-size: 4rem; color: <?php echo $icon_color; ?>;"></i>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <h5><?php echo htmlspecialchars($file['alias'] ?? basename($file['file_path'])); ?></h5>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <span class="text-muted"><?php echo get_file_type_chinese($file['file_type']); ?> (<?php echo strtoupper($file['file_format']); ?>)</span>
                                <span><?php echo format_file_size($file['file_size']); ?></span>
                                <?php if (!empty($file['document_title'])): ?>
                                    <span>文档: <?php echo htmlspecialchars($file['document_title']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($file['username'])): ?>
                                    <span>上传者: <?php echo htmlspecialchars($file['username']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 文件信息 -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">文件信息</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" id="fileForm">
                                <div class="mb-3">
                                    <label for="fileName" class="form-label">文件名</label>
                                    <input type="text" class="form-control" id="fileName" value="<?php echo htmlspecialchars($file['alias'] ?? basename($file['file_path'])); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="uploadedAt" class="form-label">上传时间</label>
                                    <input type="text" class="form-control" id="uploadedAt" value="<?php echo date('Y-m-d H:i:s', strtotime($file['uploaded_at'])); ?>" readonly>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">文件描述</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="请输入文件描述"><?php echo htmlspecialchars($file['description']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">备注</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="请输入备注信息"><?php echo htmlspecialchars($file['notes']); ?></textarea>
                                </div>
                                
                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-morandi">
                                        <i class="bi bi-save"></i> 保存信息
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>