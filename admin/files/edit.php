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
    $fileName = $_POST['fileName'] ?? '';
    $description = $_POST['description'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // 获取文件扩展名并确保文件名包含扩展名
    $fileExtension = pathinfo($file['file_path'], PATHINFO_EXTENSION);
    if (!empty($fileName) && !empty($fileExtension)) {
        // 确保文件名包含正确的扩展名
        if (pathinfo($fileName, PATHINFO_EXTENSION) !== $fileExtension) {
            $fileName .= '.' . $fileExtension;
        }
    }
    
    // 更新文件信息
    $stmt = $db->prepare("UPDATE file_upload SET alias = ?, description = ?, notes = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$fileName, $description, $notes, $id]);
    
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
    <link rel="stylesheet" href="../assets/css/static/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        /* 图片预览样式 */
        .preview-image {
            max-height: 300px;
            object-fit: contain;
            cursor: pointer;
        }
        
        /* 隐藏滚动条 */
        body.modal-open {
            overflow: hidden !important;
        }
        
        /* 黑色背景的全屏模态框 */
        #imageModal.modal-fullscreen .modal-content {
            background-color: #000;
        }
        
        #imageModal.modal-fullscreen .modal-header {
            background-color: #000;
            border-bottom: 1px solid #333;
        }
        
        #imageModal.modal-fullscreen .modal-title {
            color: #fff;
        }
        
        #imageModal.modal-fullscreen .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        
        #imageModal.modal-fullscreen .modal-body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #000;
            padding: 0;
        }
        
        #imageModal.modal-fullscreen .modal-body img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* 确保模态框背景完全覆盖 */
        .modal-backdrop.show {
            opacity: 1 !important;
        }
            background-color: rgba(0, 0, 0, 0.8) !important;
        }
        
        /* 确保body在模态框打开时隐藏滚动条 */
        body.modal-open {
            overflow: hidden !important;
            padding-right: 0 !important;
        }
    </style>
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
                                <!-- 点击图片查看大图 -->
                                <img src="/<?php echo ltrim($file['file_path'], '/'); ?>" class="img-fluid mb-3 preview-image" alt="<?php echo htmlspecialchars($file['alias'] ?? basename($file['file_path'])); ?>" data-bs-toggle="modal" data-bs-target="#imageModal" data-image-src="/<?php echo ltrim($file['file_path'], '/'); ?>" data-image-name="<?php echo htmlspecialchars($file['alias'] ?? basename($file['file_path'])); ?>">
                                
                                <!-- 大图浏览模态框在页面底部 -->
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
                                <?php if ($file['file_type'] == 'image' && !empty($file['image_width']) && !empty($file['image_height'])): ?>
                                    <span><?php echo $file['image_width']; ?> × <?php echo $file['image_height']; ?></span>
                                <?php endif; ?>
                                <span><?php echo format_file_size($file['file_size']); ?></span>
                                <?php if (!empty($file['document_title'])): ?>
                                    <span>文档: <?php echo htmlspecialchars($file['document_title']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($file['username'])): ?>
                                    <span>上传者: <?php echo htmlspecialchars($file['username']); ?></span>
                                <?php endif; ?>
                                <span>上传时间: <?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></span>
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
                                    <input type="text" class="form-control" id="fileName" name="fileName" value="<?php echo htmlspecialchars(pathinfo($file['alias'] ?? $file['file_path'], PATHINFO_FILENAME)); ?>">
                                    <input type="hidden" id="fileExtension" name="fileExtension" value="<?php echo pathinfo($file['file_path'], PATHINFO_EXTENSION); ?>">
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
    
    <script src="../assets/js/static/bootstrap.bundle.min.js"></script>
    <script>
        // 图片预览功能
        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const imageModalLabel = document.getElementById('imageModalLabel');

            imageModal.addEventListener('show.bs.modal', function(event) {
                console.log('Modal show event triggered');
                const thumbnail = event.relatedTarget;
                const imageSrc = thumbnail.getAttribute('data-image-src');
                const imageName = thumbnail.getAttribute('data-image-name');
                
                console.log('Image source:', imageSrc);
                console.log('Image name:', imageName);
                
                modalImage.src = imageSrc;
                modalImage.alt = imageName;
                imageModalLabel.textContent = imageName;
                
                // 始终使用全屏模式
                imageModal.classList.add('modal-fullscreen');
                
                console.log('Modal content updated');
            });
        });
    </script>
    <!-- 图片预览模态框 -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">图片预览</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <img id="modalImage" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
    
</body>
</html>